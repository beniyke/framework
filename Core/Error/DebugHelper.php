<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Helper class for debugging and error handling.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Error;

use Throwable;

class DebugHelper
{
    /**
     * Extracts the class name from an exception, with an option to include the full namespace.
     */
    public static function getSeverity(Throwable $exception, bool $fullNamespace = false): string
    {
        $className = get_class($exception);

        if ($fullNamespace) {
            return $className;
        }

        $parts = explode('\\', $className);

        return end($parts);
    }

    /**
     * Formats a raw exception trace string by wrapping file, line, and method/keywords in <span> tags.
     */
    public static function formatTrace(string $trace): string
    {
        $lines = explode("\n", $trace);
        $formatted = '';

        foreach ($lines as $line) {
            $line = preg_replace(
                '/^(\s+)([^:]+):(\d+)/',
                '$1<span class="file">$2</span>:<span class="line">$3</span>',
                $line
            );

            $line = preg_replace('/\b(?:at|in)\b/', '<span class="method">$0</span>', $line);

            $formatted .= $line . "\n";
        }

        return $formatted;
    }

    /**
     * Highlights PHP code using the token_get_all function for syntax highlighting.
     */
    public static function highlightPhpCode(string $code): string
    {
        $preprocessed_code = preg_replace_callback(
            '/^(\s*)(\*|\* )([^\/*]+)$/m',
            function ($matches) {
                return $matches[1] . '// ' . $matches[2] . $matches[3];
            },
            $code
        );

        $preprocessed_code = preg_replace('/^(\s*)(\*\/)$/m', '$1// $2', $preprocessed_code);

        $output = '';

        $classMap = [
            T_ABSTRACT => 'php-keyword',
            T_AS => 'php-keyword',
            T_BREAK => 'php-keyword',
            T_CASE => 'php-keyword',
            T_CATCH => 'php-keyword',
            T_CLASS => 'php-keyword',
            T_CLONE => 'php-keyword',
            T_CONST => 'php-keyword',
            T_CONTINUE => 'php-keyword',
            T_DEFAULT => 'php-keyword',
            T_DO => 'php-keyword',
            T_ECHO => 'php-keyword',
            T_ELSE => 'php-keyword',
            T_ELSEIF => 'php-keyword',
            T_EMPTY => 'php-keyword',
            T_FINAL => 'php-keyword',
            T_FOR => 'php-keyword',
            T_FOREACH => 'php-keyword',
            T_FUNCTION => 'php-keyword',
            T_GLOBAL => 'php-keyword',
            T_IF => 'php-keyword',
            T_IMPLEMENTS => 'php-keyword',
            T_INCLUDE => 'php-keyword',
            T_INCLUDE_ONCE => 'php-keyword',
            T_INSTANCEOF => 'php-keyword',
            T_INTERFACE => 'php-keyword',
            T_ISSET => 'php-keyword',
            T_LIST => 'php-keyword',
            T_NAMESPACE => 'php-keyword',
            T_NEW => 'php-keyword',
            T_PRIVATE => 'php-keyword',
            T_PROTECTED => 'php-keyword',
            T_PUBLIC => 'php-keyword',
            T_REQUIRE => 'php-keyword',
            T_REQUIRE_ONCE => 'php-keyword',
            T_RETURN => 'php-keyword',
            T_STATIC => 'php-keyword',
            T_SWITCH => 'php-keyword',
            T_THROW => 'php-keyword',
            T_TRAIT => 'php-keyword',
            T_TRY => 'php-keyword',
            T_UNSET => 'php-keyword',
            T_USE => 'php-keyword',
            T_VAR => 'php-keyword',
            T_WHILE => 'php-keyword',
            T_YIELD => 'php-keyword',
            T_YIELD_FROM => 'php-keyword',
            T_FN => 'php-keyword',
            T_MATCH => 'php-keyword',
            T_ENUM => 'php-keyword',
            T_FILE => 'php-constant',
            T_LINE => 'php-constant',
            T_DIR => 'php-constant',
            T_FUNC_C => 'php-constant',
            T_CLASS_C => 'php-constant',
            T_METHOD_C => 'php-constant',
            T_NS_C => 'php-constant',
            T_TRAIT_C => 'php-constant',
            T_COMMENT => 'php-comment',
            T_DOC_COMMENT => 'php-comment',
            T_VARIABLE => 'php-variable',
            T_STRING => 'php-identifier',
            T_NS_SEPARATOR => 'php-tag',
            T_DOUBLE_COLON => 'php-tag',
            T_LNUMBER => 'php-number',
            T_DNUMBER => 'php-number',
            T_CONSTANT_ENCAPSED_STRING => 'php-string',
            T_ENCAPSED_AND_WHITESPACE => 'php-string',
            T_IS_EQUAL => 'php-operator',
            T_IS_IDENTICAL => 'php-operator',
            T_IS_NOT_EQUAL => 'php-operator',
            T_IS_NOT_IDENTICAL => 'php-operator',
            T_IS_GREATER_OR_EQUAL => 'php-operator',
            T_IS_SMALLER_OR_EQUAL => 'php-operator',
            T_SL => 'php-operator',
            T_SR => 'php-operator',
            T_INC => 'php-operator',
            T_DEC => 'php-operator',
            T_PLUS_EQUAL => 'php-operator',
            T_MINUS_EQUAL => 'php-operator',
            T_MUL_EQUAL => 'php-operator',
            T_DIV_EQUAL => 'php-operator',
            T_POW => 'php-operator',
            T_POW_EQUAL => 'php-operator',
            T_SR_EQUAL => 'php-operator',
            T_SL_EQUAL => 'php-operator',
            T_CONCAT_EQUAL => 'php-operator',
            T_BOOLEAN_OR => 'php-operator',
            T_BOOLEAN_AND => 'php-operator',
            T_COALESCE => 'php-operator',
            T_COALESCE_EQUAL => 'php-operator',
            T_SPACESHIP => 'php-operator',
            T_OPEN_TAG => 'php-tag',
            T_OPEN_TAG_WITH_ECHO => 'php-tag',
            T_CLOSE_TAG => 'php-tag',
            T_WHITESPACE => 'php-whitespace',
            T_BAD_CHARACTER => 'php-error',
        ];

        $conditionalMap = [
            'T_HEREDOC' => 'php-string',
            'T_NOWDOC' => 'php-string',
            'T_READONLY' => 'php-keyword',
            'T_NEVER' => 'php-keyword',
            'T_NULLSAFE_OBJECT_OPERATOR' => 'php-operator',
        ];

        foreach ($conditionalMap as $constantName => $className) {
            if (defined($constantName)) {
                $classMap[constant($constantName)] = $className;
            }
        }

        $tokens = token_get_all('<?php ' . $preprocessed_code);
        $tokens = array_slice($tokens, 1);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$id, $content] = $token;

                if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                    $content = preg_replace('/^\s*\/\/\s*/', '', $content);
                }

                $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5);
                $class = $classMap[$id] ?? null;

                if ($class) {
                    $output .= '<span class="' . $class . '">' . $content . '</span>';
                } else {
                    $output .= $content;
                }
            } else {
                $output .= htmlspecialchars($token, ENT_QUOTES | ENT_HTML5);
            }
        }

        return $output;
    }

    /**
     * Fetches file content around a specific line number, highlights the line,
     * and wraps the output in an HTML table with line numbers.
     */
    public static function getFileContentWithLineNumbers(string $filePath, int $highlightLine, int $context = 3): string
    {
        if (! file_exists($filePath)) {
            return 'File not found.';
        }

        $lines = file($filePath);
        $totalLines = count($lines);
        $startLine = max(1, $highlightLine - $context);
        $endLine = min($totalLines, $highlightLine + $context);

        $content = '';

        for ($lineNumber = $startLine; $lineNumber <= $endLine; $lineNumber++) {
            $highlight = ($lineNumber == $highlightLine) ? 'highlight' : '';
            $line = $lines[$lineNumber - 1];

            $formattedLine = str_replace("\t", '    ', $line);
            $formattedLine = self::highlightPhpCode($formattedLine);

            $content .= sprintf(
                '<tr class="%s"><td class="line-number">%d</td><td class="code-line">%s</td></tr>',
                $highlight,
                $lineNumber,
                $formattedLine
            );
        }

        return '<table class="file-content-table">' . $content . '</table>';
    }

    /**
     * Highlights content enclosed in single or double quotes within an input string.
     */
    public static function highlightQuotedContent(string $input): string
    {
        $pattern = '/("([^"]*)"|\'([^\']*)\')/';

        $replacement = function (array $matches) {
            $quotedContent = $matches[2] ?? $matches[3];

            return str_replace(
                $quotedContent,
                '<strong>' . $quotedContent . '</strong>',
                $matches[0]
            );
        };

        return preg_replace_callback($pattern, $replacement, $input);
    }
}
