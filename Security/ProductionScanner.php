<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Production Readiness Scanner.
 * Validates environment, configuration, and code hygiene.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security;

use Core\Services\ConfigServiceInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\File\FileSystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProductionScanner
{
    private ConfigServiceInterface $config;

    private PathResolverInterface $paths;

    public function __construct(ConfigServiceInterface $config, PathResolverInterface $paths)
    {
        $this->config = $config;
        $this->paths = $paths;
    }

    public function scan(SymfonyStyle $io, bool $isDev = false): bool
    {
        $envPassed = $this->checkEnvironment($io, $isDev);
        $configPassed = $this->checkConfiguration($io, $isDev);
        $codebasePassed = $this->checkCodebase($io);

        return $envPassed && $configPassed && $codebasePassed;
    }

    private function checkEnvironment(SymfonyStyle $io, bool $isDev): bool
    {
        $io->section('2.1 Environment Security');
        $passed = true;

        $key = $this->config->get('encryption_key', '');

        $actualKey = $key;

        if (str_starts_with($key, 'base64:')) {
            $actualKey = substr($key, 7);
        }

        $decodedKey = base64_decode($actualKey, true);
        $keyLength = ($decodedKey !== false) ? strlen($decodedKey) : strlen($key);

        if ($keyLength !== 32) {
            $io->error("APP_KEY should be exactly 32 bytes (256-bit) after decoding. Current decoded length: " . $keyLength);
            $passed = false;
        } else {
            $io->text("✓ APP_KEY length is valid (32 bytes)");
        }

        $isDebug = $this->config->get('debug') === true;
        if ($isDebug && ! $isDev) {
            $io->error("APP_DEBUG is enabled! It must be disabled (false) for production.");
            $passed = false;
        } else {
            $io->text("✓ APP_DEBUG is " . ($isDebug ? "enabled (Dev Mode)" : "disabled"));
        }

        $env = $this->config->get('env');
        $isProdEnv = ($env === 'prod' || $env === 'production');
        if (! $isProdEnv && ! $isDev) {
            $io->error("APP_ENV must be set to 'prod' or 'production'. Current: " . $env);
            $passed = false;
        } else {
            $io->text("✓ APP_ENV is set to '{$env}'" . ($isDev ? " (Dev Mode)" : ""));
        }

        $isSecure = $this->config->get('secure') === true;

        if (! $isSecure) {
            if (! $isDev) {
                $io->error("APP_SECURE means HTTPS is enforced. It must be true for production.");
                $passed = false;
            } else {
                $io->warning("APP_SECURE is not true. Ensure your application is running over HTTPS in production.");
            }
        } else {
            $io->text("✓ APP_SECURE is enabled");
        }

        return $passed;
    }

    private function checkConfiguration(SymfonyStyle $io, bool $isDev): bool
    {
        $io->section('2.2 Configuration Hardening');
        $passed = true;

        // Session
        $session = $this->config->get('session.cookie');

        if (($session['secure'] ?? false) !== true) {
            if (! $isDev) {
                $io->error("Session Secure Cookie (SESSION_COOKIE_SECURE) must be enabled.");
                $passed = false;
            } else {
                $io->warning("Session Secure Cookie is disabled (Acceptable for Local Dev)");
            }
        } else {
            $io->text("✓ Session Cookies are Secure");
        }

        if (($session['samesite'] ?? '') !== 'Lax' && ($session['samesite'] ?? '') !== 'Strict') {
            $io->error("Session SameSite (SESSION_COOKIE_SAMESITE) should be 'Lax' or 'Strict'.");
            $passed = false;
        } else {
            $io->text("✓ Session SameSite is configured");
        }

        // CORS
        $cors = $this->config->get('cors');
        if (($cors['enabled'] ?? false) === true) {
            $origins = $cors['allowed_origins'] ?? [];
            if (in_array('*', $origins)) {
                $io->error("CORS Allowed Origins contains wildcard '*'. This is dangerous for production.");
                $passed = false;
            } else {
                $io->text("✓ CORS Origins validated");
            }
        } else {
            $io->text("✓ CORS is disabled (Safe)");
        }

        // Security Headers
        $headers = $this->config->get('security_headers', []);
        $requiredHeaders = [
            'x_frame_options' => 'X-Frame-Options',
            'x_content_type_options' => 'X-Content-Type-Options',
            'hsts_enabled' => 'HSTS',
        ];

        foreach ($requiredHeaders as $key => $name) {
            if (empty($headers[$key]) && $headers[$key] !== true) {
                if (!isset($headers[$key]) || $headers[$key] === false || $headers[$key] === null) {
                    if (! $isDev) {
                        $io->error("Security Header {$name} seems missing or disabled.");
                        $passed = false;
                    } else {
                        $io->warning("Security Header {$name} is missing or disabled.");
                    }
                }
            }
        }
        if ($passed) {
            $io->text("✓ Security Headers Check");
        }

        return $passed;
    }

    private function checkCodebase(SymfonyStyle $io): bool
    {
        $io->section('2.3 Codebase Hygiene');
        $passed = true;

        $dirs = [
            $this->paths->appPath(),
            $this->paths->systemPath(),
        ];

        $forbiddenPatterns = [
            '/\bprint_r\s*\(/' => 'print_r() found. Use Log instead.',
            '/\bvar_dump\s*\(/' => 'var_dump() found. Use Log or dd() instead.',
            '/\bdd\s*\(/' => 'dd() found. Remove before production.',
            '/\bdump\s*\(/' => 'dump() found. Remove before production.',
            '/\becho\s+/' => 'echo found. Use return View or Response.',
            '/\bexit\s*(\(|;)/' => 'exit found. Use proper Response instead.',
            '/\bdie\s*(\(|;)/' => 'die found. Use proper Response instead.',
            '/\benv\s*\(/' => 'env() used outside of config files.',
            '/\bgetenv\s*\(/' => 'getenv() used outside of config files.',
            '/\bDotenv\b/' => 'Direct Dotenv usage outside of core bootstrapper.',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $filename = $file->getPathname();

                // Skip this scanner entirely to avoid self-hits in $forbiddenPatterns
                if (str_contains($filename, 'ProductionScanner.php')) {
                    continue;
                }

                // Skip Config files and definition sites
                $isConfig = str_contains($filename, 'Config');
                $isSystemHelpers = str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Helpers');
                $isGlobals = str_contains($filename, 'Globals');
                $isDebugger = str_contains($filename, 'Debugger');
                $isVarDump = str_contains($filename, 'VarDump.php');
                $isDockCommand = str_contains($filename, 'DockCommand.php');
                $isEnvironment = str_contains($filename, 'Environment.php');
                $isDotenv = str_contains($filename, 'Dotenv');
                $isCli = str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Cli');
                $isLowLevelFramework = str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Core') ||
                    str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Queue') ||
                    str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Cron') ||
                    str_contains($filename, 'System' . DIRECTORY_SEPARATOR . 'Package');

                $content = FileSystem::get($filename);

                foreach ($forbiddenPatterns as $pattern => $message) {
                    $isEnvPattern = str_contains($pattern, 'env') || str_contains($pattern, 'Dotenv');
                    $isDebugPattern = str_contains($pattern, 'dd') || str_contains($pattern, 'dump') || str_contains($pattern, 'var_dump');
                    $isOutputPattern = str_contains($pattern, 'echo') || str_contains($pattern, 'exit') || str_contains($pattern, 'print_r');

                    // Skip env() in Config files, definition sites, etc.
                    if ($isEnvPattern) {
                        if ($isConfig || $isSystemHelpers || $isGlobals || $isDockCommand || $isEnvironment || $isDotenv || $isCli || $isLowLevelFramework) {
                            continue;
                        }
                    }

                    // Skip dd/dump in Debugger, definitions, and implementation sites
                    if ($isDebugPattern) {
                        if ($isDebugger || $isGlobals || $isVarDump) {
                            continue;
                        }
                    }

                    // Skip low-level framework output
                    if ($isOutputPattern) {
                        if ($isLowLevelFramework || $isSystemHelpers || $isGlobals || $isDockCommand || $isVarDump || $isCli) {
                            continue;
                        }
                    }

                    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        $matchIndex = $matches[0][1];
                        $line = (int) (substr_count(substr($content, 0, $matchIndex), "\n") + 1);
                        $relPath = str_replace($this->paths->basePath() . DIRECTORY_SEPARATOR, '', $filename);
                        $io->error("{$message} In: {$relPath}:{$line}");
                        $passed = false;
                    }
                }
            }
        }

        if ($passed) {
            $io->text("✓ No forbidden code usage found");
        }

        return $passed;
    }
}
