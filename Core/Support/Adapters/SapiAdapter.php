<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for SAPI interaction.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Support\Adapters;

use Core\Support\Adapters\Interfaces\SapiInterface;

use function php_sapi_name;

class SapiAdapter implements SapiInterface
{
    private const SAPI_CGI = 'cgi';
    private const SAPI_CLI = 'cli';
    private const SAPI_PHPDBG = 'phpdbg';
    private const SAPI_CLI_SERVER = 'cli-server';

    private readonly string $currentSapi;

    public function __construct()
    {
        $this->currentSapi = php_sapi_name();
    }

    public function isCgi(): bool
    {
        return $this->currentSapi === self::SAPI_CGI;
    }

    public function isCli(): bool
    {
        return $this->currentSapi === self::SAPI_CLI || $this->currentSapi === self::SAPI_PHPDBG;
    }

    public function isPhpServer(): bool
    {
        return str_starts_with($this->currentSapi, self::SAPI_CLI_SERVER);
    }
}
