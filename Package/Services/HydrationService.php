<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * HydrationService handles the downloading and unpacking of framework core files.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Package\Services;

use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Helpers\Http\Client\Curl;
use RuntimeException;
use ZipArchive;

class HydrationService
{
    private const GITHUB_API_URL = "https://api.github.com/repos/beniyke/anchor/releases/latest";
    private const GITHUB_TAGS_URL = "https://api.github.com/repos/beniyke/anchor/tags";
    private const USER_AGENT = "Anchor-Framework-Hydrator/1.0 (https://github.com/beniyke/anchor)";

    private Curl $http;

    public function __construct(?Curl $http = null)
    {
        $this->http = $http ?? new Curl();
        $this->http->withHeader('User-Agent', self::USER_AGENT)
            ->withHeader('Accept', 'application/vnd.github.v3+json');
    }

    public function getHttpClient(): Curl
    {
        return $this->http;
    }

    public function getLatestRelease(): array
    {
        $response = $this->http->get(self::GITHUB_API_URL)->send();

        if (!$response->isSuccessful()) {
            if ($response->httpCode() === 404) {
                return $this->getLatestTag();
            }

            throw new RuntimeException("Failed to fetch release info from GitHub: " . $response->getErrorMessage());
        }

        return $response->json();
    }

    /**
     * Fetch the latest tag from GitHub as a fallback when no releases exist.
     */
    public function getLatestTag(): array
    {
        $response = $this->http->get(self::GITHUB_TAGS_URL)->send();

        if (!$response->isSuccessful()) {
            throw new RuntimeException("Failed to fetch tags from GitHub: " . $response->getErrorMessage());
        }

        $tags = $response->json();

        if (empty($tags) || !is_array($tags)) {
            throw new RuntimeException("No releases or tags found for the framework on GitHub.");
        }

        $latestTag = $tags[0];

        return [
            'tag_name' => $latestTag['name'],
            'zipball_url' => $latestTag['zipball_url'],
            'is_fallback' => true
        ];
    }

    /**
     * Download the framework ZIP to a temporary location.
     */
    public function downloadZip(string $url, string $savePath): bool
    {
        // Increase timeout for large downloads
        $this->http->timeout(300000); // 5 minutes

        if (!$this->http->download($url, $savePath)) {
            throw new RuntimeException("Failed to download ZIP to: {$savePath}");
        }

        return true;
    }

    /**
     * Extract specific directories from the zip file.
     *
     * @param string $zipPath     Path to the downloaded zip.
     * @param string $extractPath Path to extract to (usually base path).
     * @param array  $directories List of directories to extract (e.g. ['System', 'libs']).
     */
    public function extract(string $zipPath, string $extractPath, array $directories = ['System', 'libs']): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZipArchive extension is required for hydration.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Failed to open downloaded ZIP file.");
        }

        $extractedCount = 0;
        $errors = [];

        // GitHub ZIPs usually have a top-level folder like "anchor-1.0.0/"
        $rootInZip = $zip->getNameIndex(0);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Remove the top-level GitHub folder name from the path
            $relativePath = str_replace($rootInZip, '', $name);

            if (empty($relativePath)) {
                continue;
            }

            $match = false;
            foreach ($directories as $dir) {
                if (str_starts_with($relativePath, $dir . '/') || $relativePath === $dir) {
                    $match = true;
                    break;
                }
            }

            if ($match) {
                // Ensure target directory exists
                $target = Paths::join($extractPath, str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

                if (str_ends_with($name, '/')) {
                    FileSystem::mkdir($target);
                } else {
                    FileSystem::mkdir(dirname($target));
                    $stream = $zip->getStream($name);
                    if ($stream !== false) {
                        $content = stream_get_contents($stream);
                        fclose($stream);
                        if ($this->safeWrite($target, $content)) {
                            $extractedCount++;
                        } else {
                            $errors[] = "Failed to write: {$relativePath}";
                        }
                    } else {
                        $errors[] = "Failed to extract: {$relativePath}";
                    }
                }
            }
        }

        $zip->close();

        return [
            'count' => $extractedCount,
            'errors' => $errors
        ];
    }

    /**
     * Write file content safely with atomic replace and retry logic.
     *
     * Handles transient file locks (common on Windows) by:
     * 1. Attempting an atomic replace (write to temp file, then rename over target).
     * 2. Falling back to a direct write.
     * 3. Retrying up to $retries times with a short delay between attempts.
     */
    private function safeWrite(string $path, string $content, int $retries = 3): bool
    {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            // Atomic replace: write to temp file then rename over the target
            if (FileSystem::replace($path, $content)) {
                return true;
            }

            // Direct write fallback
            if (FileSystem::put($path, $content)) {
                return true;
            }

            if ($attempt < $retries) {
                usleep(100_000); // 100ms delay before retry
            }
        }

        return false;
    }

    /**
     * Clean up temporary files.
     */
    public function cleanup(string $path): void
    {
        if (FileSystem::exists($path)) {
            FileSystem::delete($path);
        }
    }
}
