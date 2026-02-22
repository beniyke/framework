<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The FTP connection.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;

class FtpAdapter extends StorageAdapter
{
    protected $connection = null;

    protected string $host;

    protected string $username;

    protected string $password;

    protected int $port;

    protected string $root;

    protected bool $ssl;

    protected int $timeout;

    protected bool $passive;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->host = $config['host'] ?? '';
        $this->username = $config['username'] ?? 'anonymous';
        $this->password = $config['password'] ?? '';
        $this->port = (int) ($config['port'] ?? 21);
        $this->root = rtrim($config['root'] ?? '', '/');
        $this->ssl = (bool) ($config['ssl'] ?? false);
        $this->timeout = (int) ($config['timeout'] ?? 90);
        $this->passive = (bool) ($config['passive'] ?? true);
    }

    protected function connect()
    {
        if ($this->connection && is_resource($this->connection)) {
            return $this->connection;
        }

        if ($this->ssl) {
            $this->connection = @ftp_ssl_connect($this->host, $this->port, $this->timeout);
        } else {
            $this->connection = @ftp_connect($this->host, $this->port, $this->timeout);
        }

        if (!$this->connection) {
            throw new RuntimeException("Could not connect to FTP host: {$this->host}");
        }

        if (!@ftp_login($this->connection, $this->username, $this->password)) {
            throw new RuntimeException("Could not login to FTP server with username: {$this->username}");
        }

        @ftp_pasv($this->connection, $this->passive);

        if ($this->root) {
            @ftp_chdir($this->connection, $this->root);
        }

        return $this->connection;
    }

    public function exists(string $path): bool
    {
        $conn = $this->connect();
        $path = $this->applyRoot($path);

        $list = ftp_nlist($conn, dirname($path));
        if ($list === false) {
            return false;
        }

        return in_array($path, $list) || in_array(basename($path), $list);
    }

    public function get(string $path): string
    {
        $conn = $this->connect();
        $path = $this->applyRoot($path);

        $temp = fopen('php://temp', 'r+');
        if (@ftp_fget($conn, $temp, $path, FTP_BINARY)) {
            rewind($temp);

            return stream_get_contents($temp);
        }

        return '';
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $conn = $this->connect();
        $path = $this->applyRoot($path);

        $directory = dirname($path);
        if ($directory !== '.') {
            $this->makeDirectory($directory);
        }

        $temp = fopen('php://temp', 'r+');
        fwrite($temp, $contents);
        rewind($temp);

        return @ftp_fput($conn, $path, $temp, FTP_BINARY);
    }

    public function delete(string $path): bool
    {
        $conn = $this->connect();

        return @ftp_delete($conn, $this->applyRoot($path));
    }

    public function copy(string $from, string $to): bool
    {
        $contents = $this->get($from);
        if ($contents !== '') {
            return $this->put($to, $contents);
        }

        return false;
    }

    public function move(string $from, string $to): bool
    {
        $conn = $this->connect();

        return @ftp_rename($conn, $this->applyRoot($from), $this->applyRoot($to));
    }

    public function size(string $path): int
    {
        $conn = $this->connect();
        $size = @ftp_size($conn, $this->applyRoot($path));

        return $size === -1 ? 0 : $size;
    }

    public function lastModified(string $path): int
    {
        $conn = $this->connect();
        $time = @ftp_mdtm($conn, $this->applyRoot($path));

        return $time === -1 ? 0 : $time;
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        $protocol = $this->ssl ? 'ftps' : 'ftp';

        return "{$protocol}://{$this->username}@{$this->host}/" . ltrim($this->normalizePath($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $conn = $this->connect();
        $directory = $this->applyRoot($directory);

        $files = [];
        $list = ftp_nlist($conn, $directory);

        if ($list === false) {
            return [];
        }

        foreach ($list as $item) {
            // Simple approach for now, doesn't handle recursive yet
            $files[] = $this->removeRoot($item);
        }

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        $conn = $this->connect();
        $path = $this->applyRoot($path);

        $parts = explode('/', $path);
        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current .= '/' . $part;
            if (!@ftp_chdir($conn, $current)) {
                @ftp_mkdir($conn, $current);
            }
        }

        if ($this->root) {
            @ftp_chdir($conn, $this->root);
        }

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $conn = $this->connect();
        $path = $this->applyRoot($path);

        // This is a simplified recursive delete
        $list = ftp_nlist($conn, $path);
        if ($list !== false) {
            foreach ($list as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                if (@ftp_delete($conn, $item) === false) {
                    $this->deleteDirectory($item);
                }
            }
        }

        return @ftp_rmdir($conn, $path);
    }

    protected function applyRoot(string $path): string
    {
        $path = ltrim($this->normalizePath($path), '/');

        return $this->root ? $this->root . '/' . $path : $path;
    }

    protected function removeRoot(string $path): string
    {
        if ($this->root && str_starts_with($path, $this->root)) {
            return ltrim(substr($path, strlen($this->root)), '/');
        }

        return $path;
    }

    public function __destruct()
    {
        if ($this->connection) {
            @ftp_close($this->connection);
        }
    }
}
