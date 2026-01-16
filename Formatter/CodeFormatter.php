<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * CodeFormatter provides functionality to format code files, including cleaning up comments
 * and standardizing code style.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Formatter;

use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class CodeFormatter
{
    private FileReadWriteInterface $fileReadWrite;

    private array $config;

    private int $filesProcessed = 0;

    private int $filesSkipped = 0;

    private int $totalChanges = 0;

    private array $stats = [
        'docblocks_removed' => 0,
        'inline_comments_removed' => 0,
        'numbered_steps_removed' => 0,
        'obvious_actions_removed' => 0,
        'empty_lines_cleaned' => 0,
    ];

    private array $errors = [];

    /** @var callable|null */
    private $progressCallback = null;

    private const DEFAULT_CONFIG = [
        'max_file_size_mb' => 5,
        'create_backup' => false,
        'exclude_patterns' => [],
        'remove_empty_comments' => true,
        'remove_todo_markers' => true,
        'remove_numbered_steps' => true,
        'remove_obvious_actions' => true,
        'remove_commented_code' => true,
        'remove_redundant_docblocks' => true,
        'clean_empty_lines' => true,
        'aggressive_mode' => false,
    ];

    public function __construct(FileReadWriteInterface $fileReadWrite, array $config = [])
    {
        $this->fileReadWrite = $fileReadWrite;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    public function formatDirectory(string $directory, bool $dryRun = false): array
    {
        $this->resetCounters();

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$this->shouldProcessFile($file)) {
                    continue;
                }

                $this->processFile($file->getPathname(), $dryRun);
            }
        } catch (Throwable $e) {
            $this->errors[] = "Error scanning directory {$directory}: {$e->getMessage()}";
        }

        return $this->getResults();
    }

    public function formatFile(string $filePath, bool $dryRun = false): int
    {
        $this->resetCounters();
        $changes = $this->processFile($filePath, $dryRun);
        $this->totalChanges = $changes;

        return $changes;
    }

    private function shouldProcessFile(SplFileInfo $file): bool
    {
        if ($file->getExtension() !== 'php') {
            return false;
        }

        if ($this->isExcluded($file->getPathname())) {
            $this->filesSkipped++;
            $this->notifyProgress($file->getPathname(), 'skipped_excluded');

            return false;
        }

        if ($file->isLink()) {
            $this->filesSkipped++;

            return false;
        }

        $maxBytes = $this->config['max_file_size_mb'] * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            $this->filesSkipped++;
            $this->notifyProgress($file->getPathname(), 'skipped_size', $file->getSize());

            return false;
        }

        if (!$file->isReadable()) {
            $this->filesSkipped++;
            $this->errors[] = "Not readable: {$file->getPathname()}";
            $this->notifyProgress($file->getPathname(), 'skipped_permission');

            return false;
        }

        return true;
    }

    private function processFile(string $filePath, bool $dryRun): int
    {
        try {
            if ($this->config['create_backup'] && !$dryRun) {
                $this->createFileBackup($filePath);
            }

            $content = $this->fileReadWrite->get($filePath);
            $lines = explode("\n", $content);
            $result = $this->cleanComments($lines);

            if ($result['changes'] > 0) {
                $this->filesProcessed++;
                $this->totalChanges += $result['changes'];

                if (!$dryRun) {
                    if (!is_writable($filePath)) {
                        $this->errors[] = "Not writable: {$filePath}";

                        return 0;
                    }
                    $this->fileReadWrite->put($filePath, implode("\n", $result['lines']));
                }

                $this->notifyProgress($filePath, 'processed', $result['changes']);
            }

            return $result['changes'];
        } catch (Throwable $e) {
            $this->errors[] = "Error processing {$filePath}: {$e->getMessage()}";

            return 0;
        }
    }

    private function cleanComments(array $lines): array
    {
        $newLines = [];
        $changes = 0;
        $inDocBlock = false;
        $inBlockComment = false;
        $docBlockLines = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if (str_contains($trimmed, '/**')) {
                if (str_contains($trimmed, '*/')) {
                    // Single line docblock
                    $docBlockLines = [$line];
                    $nextNode = $this->findNextCodeLine($lines, $i);
                    if ($this->isRedundantDocblock($docBlockLines, $nextNode)) {
                        $changes++;
                        $this->stats['docblocks_removed']++;
                    } else {
                        $newLines[] = $line;
                    }
                    continue;
                }
                $inDocBlock = true;
                $docBlockLines = [$line];
                continue;
            }

            if ($inDocBlock && str_contains($trimmed, '*/')) {
                $inDocBlock = false;
                $docBlockLines[] = $line;

                $nextNode = $this->findNextCodeLine($lines, $i);

                if ($this->isRedundantDocblock($docBlockLines, $nextNode)) {
                    $changes += count($docBlockLines);
                    $this->stats['docblocks_removed'] += count($docBlockLines);
                } else {
                    foreach ($docBlockLines as $docLine) {
                        $newLines[] = $docLine;
                    }
                }
                $docBlockLines = [];
                continue;
            }

            if ($inDocBlock) {
                $docBlockLines[] = $line;
                continue;
            }

            if (str_contains($trimmed, '/*')) {
                $inBlockComment = true;
            }
            if ($inBlockComment && str_contains($trimmed, '*/')) {
                $inBlockComment = false;
                $newLines[] = $line;
                continue;
            }

            if ($inBlockComment) {
                $newLines[] = $line;
                continue;
            }

            if (!str_starts_with($trimmed, '//')) {
                $newLines[] = $line;
                continue;
            }

            $commentContent = trim(substr($trimmed, 2));

            if (empty($commentContent)) {
                $changes++;
                $this->stats['inline_comments_removed']++;
                continue;
            }

            if (preg_match('/^(TODO|FIXME|NOTE|XXX|HACK|TEMP)[\s:]/i', $commentContent)) {
                $changes++;
                $this->stats['inline_comments_removed']++;
                continue;
            }

            if ($this->isNumberedStepComment($commentContent)) {
                $changes++;
                $this->stats['numbered_steps_removed']++;
                continue;
            }

            if ($this->isPartOfExplanatoryComment($lines, $i)) {
                $newLines[] = $line;
                continue;
            }

            if ($this->isCommentedOutCode($commentContent)) {
                $changes++;
                $this->stats['inline_comments_removed']++;
                continue;
            }

            if ($this->isObviousActionComment($commentContent, $lines, $i)) {
                $changes++;
                $this->stats['obvious_actions_removed']++;
                continue;
            }

            if ($this->isRedundantComment($commentContent, $lines, $i)) {
                $changes++;
                $this->stats['inline_comments_removed']++;
                continue;
            }

            $newLines[] = $line;
        }

        if ($this->config['clean_empty_lines']) {
            $cleanResult = $this->cleanEmptyLines($newLines);
            $newLines = $cleanResult['lines'];
            $changes += $cleanResult['cleaned'];
            $this->stats['empty_lines_cleaned'] += $cleanResult['cleaned'];
        }

        return ['lines' => $newLines, 'changes' => $changes];
    }

    private function findNextCodeLine(array $lines, int $docblockEndIndex): ?string
    {
        for ($i = $docblockEndIndex + 1; $i < min($docblockEndIndex + 5, count($lines)); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            if (!str_starts_with($line, '//') && !str_starts_with($line, '/*') && !str_starts_with($line, '*')) {
                return $lines[$i];
            }
        }

        return null;
    }

    private function isRedundantDocblock(array $docblockLines, ?string $node): bool
    {
        if ($node === null) {
            return false;
        }

        $docContent = implode("\n", $docblockLines);
        $cleanDocContent = '';
        foreach ($docblockLines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '/**' && $trimmed !== '*/' && $trimmed !== '*') {
                if (str_starts_with($trimmed, '* ')) {
                    $cleanDocContent .= substr($trimmed, 2) . ' ';
                } else {
                    $cleanDocContent .= $trimmed . ' ';
                }
            }
        }
        $cleanDocContent = trim($cleanDocContent);

        // Property checks
        if (str_contains($node, '$table =')) {
            if (str_contains($cleanDocContent, 'The table associated with the model')) {
                return true;
            }
        }
        if (str_contains($node, '$fillable =')) {
            if (str_contains($cleanDocContent, 'The attributes that are mass assignable')) {
                return true;
            }
        }

        // Method checks
        if (!str_contains($node, 'function ')) {
            return false;
        }

        $methodSignature = $node;

        if (str_contains($docContent, '@throws')) {
            return false;
        }

        if (
            preg_match('/@return\s+array\s*[{\[]/', $docContent) ||
            preg_match('/@return\s+\w+</', $docContent)
        ) {
            return false;
        }

        if (preg_match('/@(author|package|copyright|license|version|since)/', $docContent)) {
            return false;
        }

        $explanationWords = ['because', 'ensures'];
        // We only check for these if they are not at the start of the description
        foreach ($explanationWords as $word) {
            if (stripos($docContent, $word) !== false) {
                return false;
            }
        }

        preg_match('/function\s+(\w+)/', $methodSignature, $methodMatch);
        $methodName = $methodMatch[1] ?? '';

        $hasReturnType = str_contains($methodSignature, '):');
        $hasParameterTypes = preg_match('/\(\s*([\w\\\\?|]+)\s+\$/', $methodSignature);

        $descriptionLine = '';
        foreach ($docblockLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '/**' || $trimmed === '*/' || $trimmed === '*') {
                continue;
            }
            if (str_starts_with($trimmed, '* ') && !str_starts_with($trimmed, '* @')) {
                $descriptionLine = substr($trimmed, 2);
                break;
            }
        }

        $methodWords = $this->splitCamelCase($methodName);

        $redundantPatterns = [
            '/^set\s+the\s+/i',
            '/^get\s+the\s+/i',
            '/^create\s+(a|the|an)?\s*/i',
            '/^update\s+(a|the|an)?\s*/i',
            '/^delete\s+(a|the|an)?\s*/i',
            '/^find\s+(a|the|an)?\s*/i',
            '/^check\s+if\s+/i',
            '/^validate\s+(the|a)?\s*/i',
            '/^add\s+(a|the|an)?\s*/i',
            '/^remove\s+(a|the|an)?\s*/i',
            '/^toggle\s+(the|a)?\s*/i',
            '/^pin\s+(the|a)?\s*/i',
            '/^unpin\s+(the|a)?\s*/i',
            '/^mark\s+as\s+/i',
            '/^generate\s+(the|a)?\s*/i',
            '/^handle\s+(the|a)?\s*/i',
            '/^process\s+(the|a)?\s*/i',
            '/^initialize\s+(the|a)?\s*/i',
            '/^perform\s+(the|a)?\s*/i',
            '/^calculate\s+(the|a)?\s*/i',
        ];

        foreach ($redundantPatterns as $pattern) {
            if (preg_match($pattern, $descriptionLine)) {
                $cleaned = preg_replace($pattern, '', $descriptionLine);
                $stopWords = ['the', 'a', 'an', 'is', 'are', 'for', 'to', 'of', 'in', 'with', 'that', 'this', 'by', 'on', 'at', 'from', 'who', 'which', 'that', 'has', 'have', 'if', 'whether'];
                $cleanedWords = array_map(
                    fn ($w) => rtrim($w, '.,;:!?'),
                    preg_split('/\s+/', strtolower(trim($cleaned)))
                );
                $cleanedWords = array_filter(
                    $cleanedWords,
                    fn ($w) => !in_array($w, $stopWords) && (strlen($w) > 2 || preg_match('/\d/', $w))
                );

                if (empty($cleanedWords)) {
                    return true;
                }

                $overlap = array_intersect($cleanedWords, array_map('strtolower', $methodWords));
                if (count($overlap) >= count($cleanedWords) * 0.5) {
                    if ($hasReturnType) {
                        return true;
                    }
                }
            }
        }

        // Catch very short obvious descriptions
        if (strlen($descriptionLine) < 30 && count(explode(' ', $descriptionLine)) <= 4) {
            $descLower = strtolower($descriptionLine);
            $methodLower = strtolower($methodName);
            if (str_contains($methodLower, str_replace(' ', '', $descLower)) || str_contains($descLower, $methodLower)) {
                return true;
            }
        }

        $hasOnlyTypeAnnotations = true;
        $foundAnnotations = false;
        foreach ($docblockLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '/**' || $trimmed === '*/' || $trimmed === '*') {
                continue;
            }
            if (str_starts_with($trimmed, '* @param') || str_starts_with($trimmed, '* @return')) {
                // Check if it has a description beyond the type and variable
                $parts = preg_split('/\s+/', $trimmed);
                if (count($parts) > 4) { // * @param Type $var Description
                    $hasOnlyTypeAnnotations = false;
                }
                $foundAnnotations = true;
                continue;
            }
            if (str_starts_with($trimmed, '* ') && strlen(trim(substr($trimmed, 2))) > 0) {
                // If the only text is a redundant description we already checked, ignore it here
                $hasOnlyTypeAnnotations = false;
            }
        }

        if ($foundAnnotations && $hasOnlyTypeAnnotations && $hasReturnType) {
            return true;
        }

        if (preg_match('/^\s*(?:Get|The|Check|Find|Add|Remove|Scope|Return|Handle|Process|Is|Has|Can)\s+(?:the\s+|a\s+|an\s+|if\s+|whether\s+)?([a-zA-Z0-9_\s\(\),]+)(?:\.*)\s*$/i', $descriptionLine, $matches)) {
            $nounPhrase = strtolower(trim($matches[1]));
            $methodLower = strtolower($methodName);

            // Strip common suffixes
            $nounPhrase = preg_replace('/\s+\(.*\)$/', '', $nounPhrase); // Remove (project, ticket, etc)
            $nounPhrase = preg_replace('/\s+(associated\s+with|belongs\s+to|for\s+this|of\s+this|in\s+this|that\s+submitted|who\s+submitted|to\s+this|on\s+a)\s+.*$/i', '', $nounPhrase);

            $phraseWords = array_filter(explode(' ', $nounPhrase), fn ($w) => !in_array($w, ['the', 'a', 'an', 'is', 'of', 'for', 'this']));
            foreach ($phraseWords as $word) {
                if (str_contains($methodLower, $word)) {
                    return true;
                }
            }
        }

        // Relationship specific check
        if (preg_match('/:\s*(MorphTo|BelongsTo|HasMany|HasOne|MorphMany|MorphToMany|BelongsToMany)/', $methodSignature)) {
            if (preg_match('/^\s*(Get|Return|The|Relation|Relationship)\s+(?:the\s+)?([a-zA-Z0-9_\s]+).*\s*$/i', $descriptionLine)) {
                return true;
            }
        }

        if (str_starts_with($methodName, 'scope')) {
            if (preg_match('/^\s*Scope\s+(?:for|:)\s+(?:[a-zA-Z0-9_\s]+)\.?\s*$/i', $descriptionLine)) {
                return true;
            }
        }

        return false;
    }

    private function isPartOfExplanatoryComment(array $lines, int $currentIndex): bool
    {
        $currentLine = trim($lines[$currentIndex]);
        if (!str_starts_with($currentLine, '//')) {
            return false;
        }

        $commentContent = trim(substr($currentLine, strpos($currentLine, '//') + 2));

        $narrativeWords = [
            'because',
            'since',
            'therefore',
            'thus',
            'however',
            'although',
            'explanation',
            'note',
            'important',
            'remember',
            'warning',
            'this is',
            'this will',
            'this should',
            'we need',
            'to avoid',
            'for example',
            'such as',
            'in this case',
            'when',
            'if',
        ];

        $lowerContent = strtolower($commentContent);
        foreach ($narrativeWords as $word) {
            if (str_contains($lowerContent, $word)) {
                return true;
            }
        }

        $consecutiveComments = 1;

        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            $prevTrimmed = trim($lines[$i]);
            if (str_starts_with($prevTrimmed, '//')) {
                $consecutiveComments++;
            } else {
                break;
            }
        }

        for ($i = $currentIndex + 1; $i < count($lines); $i++) {
            $nextTrimmed = trim($lines[$i]);
            if (str_starts_with($nextTrimmed, '//')) {
                $consecutiveComments++;
            } else {
                break;
            }
        }

        if ($consecutiveComments >= 3) {
            $codeLineCount = 0;
            $start = max(0, $currentIndex - 2);
            $end = min(count($lines), $currentIndex + 3);

            for ($i = $start; $i < $end; $i++) {
                $testLine = trim($lines[$i]);
                if (str_starts_with($testLine, '//')) {
                    $testContent = trim(substr($testLine, strpos($testLine, '//') + 2));
                    if ($this->isCommentedOutCode($testContent)) {
                        $codeLineCount++;
                    }
                }
            }

            return $codeLineCount < ($consecutiveComments / 2);
        }

        return false;
    }

    private function isCommentedOutCode(string $comment): bool
    {
        $codePatterns = [
            '/=/',
            '/\$\w+/',
            '/function\s+\w+/',
            '/if\s*\(/',
            '/foreach\s*\(/',
            '/for\s*\(/',
            '/while\s*\(/',
            '/return\s+/',
            '/echo\s+/',
            '/print\s+/',
            '/new\s+\w+/',
            '/->\w+/',
            '/\w+::\w+/',
            '/use\s+\w+/',
            '/namespace\s+\w+/',
            '/class\s+\w+/',
            '/public\s+function/',
            '/private\s+function/',
            '/protected\s+function/',
        ];

        foreach ($codePatterns as $pattern) {
            if (preg_match($pattern, $comment)) {
                return true;
            }
        }

        return false;
    }

    private function isRedundantComment(string $comment, array $lines, int $currentIndex): bool
    {
        if (!isset($lines[$currentIndex + 1])) {
            return false;
        }

        $nextLine = trim($lines[$currentIndex + 1]);

        $redundantPatterns = [
            '/^constructor$/i' => '/public\s+function\s+__construct/',
            '/^getter$/i' => '/public\s+function\s+get\w+/',
            '/^setter$/i' => '/public\s+function\s+set\w+/',
            '/^start\s+\w+$/i' => '/\w+/',
            '/^end\s+\w+$/i' => '/}/',
        ];

        foreach ($redundantPatterns as $commentPattern => $codePattern) {
            if (preg_match($commentPattern, $comment) && preg_match($codePattern, $nextLine)) {
                return true;
            }
        }

        return false;
    }

    private function isNumberedStepComment(string $comment): bool
    {
        return (bool) preg_match('/^(\d+[\.\)\-:\s]|step\s*\d+)/i', $comment);
    }

    private function isObviousActionComment(string $comment, array $lines, int $currentIndex): bool
    {
        if (!isset($lines[$currentIndex + 1])) {
            return false;
        }

        $nextLine = strtolower(trim($lines[$currentIndex + 1]));
        $commentLower = strtolower($comment);

        $actionMappings = [
            'create' => ['::create(', '->create(', 'new '],
            'update' => ['->update(', '::update('],
            'delete' => ['->delete(', '::delete('],
            'save' => ['->save('],
            'check' => ['if (', 'if('],
            'validate' => ['->validate(', 'validator'],
            'calculate' => ['->calculate', '$result =', '$total =', '$amount ='],
            'get' => ['->get(', '::get(', '= $this->'],
            'set' => ['->set(', '$this->'],
            'send' => ['->send(', 'mail::send', 'notification'],
            'return' => ['return '],
            'initialize' => ['new ', '::create('],
            'enable' => ['= true', "= 'true'"],
            'disable' => ['= false', "= 'false'"],
            'restore' => ['->restore(', '->set('],
            'lock' => ['->lock('],
            'unlock' => ['->unlock('],
            'credit' => ['->credit('],
            'debit' => ['->debit('],
        ];

        foreach ($actionMappings as $action => $codePatterns) {
            if (str_contains($commentLower, $action)) {
                foreach ($codePatterns as $pattern) {
                    if (str_contains($nextLine, $pattern)) {
                        return true;
                    }
                }
            }
        }

        if (preg_match('/^\w+$/', $comment)) {
            $methodName = strtolower($comment);
            if (
                str_contains($nextLine, '->' . $methodName . '(') ||
                str_contains($nextLine, '::' . $methodName . '(')
            ) {
                return true;
            }
        }

        return false;
    }

    private function splitCamelCase(string $input): array
    {
        return preg_split('/(?=[A-Z])/', $input, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function createFileBackup(string $filePath): void
    {
        $backupPath = $filePath . '.bak';
        copy($filePath, $backupPath);
    }

    private function notifyProgress(string $file, string $action, int|string $detail = ''): void
    {
        if ($this->progressCallback !== null) {
            ($this->progressCallback)([
                'file' => $file,
                'action' => $action,
                'detail' => $detail,
                'processed' => $this->filesProcessed,
                'skipped' => $this->filesSkipped,
                'changes' => $this->totalChanges,
            ]);
        }
    }

    private function isExcluded(string $filePath): bool
    {
        $normalizedPath = str_replace('\\', '/', $filePath);

        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (str_contains($normalizedPath, '/' . $pattern . '/') || str_ends_with($normalizedPath, '/' . $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function resetCounters(): void
    {
        $this->filesProcessed = 0;
        $this->filesSkipped = 0;
        $this->totalChanges = 0;
        $this->errors = [];
        $this->stats = [
            'docblocks_removed' => 0,
            'inline_comments_removed' => 0,
            'numbered_steps_removed' => 0,
            'obvious_actions_removed' => 0,
            'empty_lines_cleaned' => 0,
        ];
    }

    public function getResults(): array
    {
        return [
            'files_processed' => $this->filesProcessed,
            'files_skipped' => $this->filesSkipped,
            'total_changes' => $this->totalChanges,
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }

    private function cleanEmptyLines(array $lines): array
    {
        $cleaned = 0;
        $result = [];
        $consecutiveEmpty = 0;

        foreach ($lines as $line) {
            $isEmpty = trim($line) === '';

            if ($isEmpty) {
                $consecutiveEmpty++;
                if ($consecutiveEmpty <= 1) {
                    $result[] = $line;
                } else {
                    $cleaned++;
                }
            } else {
                $consecutiveEmpty = 0;
                $result[] = $line;
            }
        }

        return ['lines' => $result, 'cleaned' => $cleaned];
    }
}
