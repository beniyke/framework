<?php

declare(strict_types=1);

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;

use function ssh2_auth_password;
use function ssh2_auth_pubkey_file;
use function ssh2_connect;
use function ssh2_sftp;
use function ssh2_sftp_mkdir;
use function ssh2_sftp_rename;
use function ssh2_sftp_rmdir;
use function ssh2_sftp_stat;
use function ssh2_sftp_unlink;

class SftpAdapter extends StorageAdapter
{
    /**
     * The SSH2 connection.
     *
     * @var resource|null
     */
    protected $connection = null;

    /**
     * The SFTP resource.
     *
     * @var resource|null
     */
    protected $sftp = null;

    protected string $host;

    protected string $username;

    protected string $password;

    protected int $port;

    protected string $root;

    protected string $privateKey;

    protected string $passphrase;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->host = $config['host'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->port = (int) ($config['port'] ?? 22);
        $this->root = rtrim($config['root'] ?? '', '/');
        $this->privateKey = $config['privateKey'] ?? '';
        $this->passphrase = $config['passphrase'] ?? '';
    }

    protected function connect()
    {
        if ($this->connection && is_resource($this->connection) && $this->sftp && is_resource($this->sftp)) {
            return $this->sftp;
        }

        if (!function_exists('ssh2_connect')) {
            throw new RuntimeException('SSH2 extension is required for SFTP storage.');
        }

        $this->connection = @ssh2_connect($this->host, $this->port);

        if (!$this->connection) {
            throw new RuntimeException("Could not connect to SSH host: {$this->host}");
        }

        if ($this->privateKey) {
            if (!@ssh2_auth_pubkey_file($this->connection, $this->username, $this->privateKey . '.pub', $this->privateKey, $this->passphrase)) {
                throw new RuntimeException("Could not authenticate SSH using private key for user: {$this->username}");
            }
        } elseif (!@ssh2_auth_password($this->connection, $this->username, $this->password)) {
            throw new RuntimeException("Could not authenticate SSH using password for user: {$this->username}");
        }

        $this->sftp = @ssh2_sftp($this->connection);

        if (!$this->sftp) {
            throw new RuntimeException("Could not initialize SFTP subsystem.");
        }

        return $this->sftp;
    }

    public function exists(string $path): bool
    {
        $this->connect();
        $fullPath = $this->applyRoot($path);

        return file_exists("ssh2.sftp://" . (int)$this->sftp . $fullPath);
    }

    public function get(string $path): string
    {
        $this->connect();
        $fullPath = $this->applyRoot($path);

        $contents = @file_get_contents("ssh2.sftp://" . (int)$this->sftp . $fullPath);

        return $contents === false ? '' : $contents;
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $this->connect();
        $fullPath = $this->applyRoot($path);

        $directory = dirname($fullPath);
        if ($directory !== '.' && $directory !== '/') {
            $this->makeDirectory($directory);
        }

        return @file_put_contents("ssh2.sftp://" . (int)$this->sftp . $fullPath, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        $this->connect();

        return @ssh2_sftp_unlink($this->sftp, $this->applyRoot($path));
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
        $this->connect();

        return @ssh2_sftp_rename($this->sftp, $this->applyRoot($from), $this->applyRoot($to));
    }

    public function size(string $path): int
    {
        $this->connect();
        $stat = @ssh2_sftp_stat($this->sftp, $this->applyRoot($path));

        return $stat ? $stat['size'] : 0;
    }

    public function lastModified(string $path): int
    {
        $this->connect();
        $stat = @ssh2_sftp_stat($this->sftp, $this->applyRoot($path));

        return $stat ? $stat['mtime'] : 0;
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return "sftp://{$this->username}@{$this->host}/" . ltrim($this->normalizePath($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $this->connect();
        $directory = $this->applyRoot($directory);
        $files = [];

        $handle = opendir("ssh2.sftp://" . (int)$this->sftp . $directory);
        if (!$handle) {
            return [];
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = rtrim($directory, '/') . '/' . $file;
            $relative = $this->removeRoot($path);

            $files[] = $relative;

            if ($recursive && is_dir("ssh2.sftp://" . (int)$this->sftp . $path)) {
                $files = array_merge($files, $this->files($relative, true));
            }
        }

        closedir($handle);

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        $this->connect();
        $path = $this->applyRoot($path);

        return @ssh2_sftp_mkdir($this->sftp, $path, 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $this->connect();
        $path = $this->applyRoot($path);

        return @ssh2_sftp_rmdir($this->sftp, $path);
    }

    protected function applyRoot(string $path): string
    {
        $path = ltrim($this->normalizePath($path), '/');

        return $this->root ? $this->root . '/' . $path : '/' . $path;
    }

    protected function removeRoot(string $path): string
    {
        if ($this->root && str_starts_with($path, $this->root)) {
            return ltrim(substr($path, strlen($this->root)), '/');
        }

        return ltrim($path, '/');
    }
}
