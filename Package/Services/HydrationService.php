<?php

declare(strict_types=1);

namespace Package\Services;

use Helpers\File\FileSystem;
use Helpers\Http\Client\Curl;
use RuntimeException;
use ZipArchive;

/**
 * HydrationService handles the downloading and unpacking of framework core files.
 */
class HydrationService
{
    private const GITHUB_API_URL = "https://api.github.com/repos/anchor/anchor/releases/latest";
    private const USER_AGENT = "Anchor-Framework-Hydrator";

    private Curl $http;

    public function __construct(?Curl $http = null)
    {
        $this->http = $http ?? new Curl([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/vnd.github.v3+json'
        ]);
    }

    public function getLatestRelease(): array
    {
        $response = $this->http->get(self::GITHUB_API_URL)->send();

        if (!$response->isSuccessful()) {
            throw new RuntimeException("Failed to fetch release info from GitHub: " . $response->getErrorMessage());
        }

        return $response->json();
    }

    /**
     * Download the framework ZIP to a temporary location.
     */
    public function downloadZip(string $url, string $savePath): bool
    {
        // GitHub ZIP redirects might require follow-location if not handled by Curl wrapper
        // Our Curl wrapper doesn't explicitly mention follow-location, but let's assume it works or use native curl if needed.
        // Actually, let's use a simpler approach for the large download if Curl wrapper is tuned for small APIs.

        $fp = fopen($savePath, 'w+');
        if (!$fp) {
            throw new RuntimeException("Could not open path for writing zip: {$savePath}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes

        $success = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success) {
            throw new RuntimeException("Failed to download ZIP: {$error}");
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
                $target = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (str_ends_with($name, '/')) {
                    FileSystem::mkdir($target);
                } else {
                    FileSystem::mkdir(dirname($target));
                    if (copy("zip://{$zipPath}#{$name}", $target)) {
                        $extractedCount++;
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
     * Clean up temporary files.
     */
    public function cleanup(string $path): void
    {
        if (FileSystem::exists($path)) {
            FileSystem::delete($path);
        }
    }
}
