<?php

declare(strict_types=1);

namespace JTL\Filesystem;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

/**
 * Class AdapterFactory
 * @package JTL\Filesystem
 */
class AdapterFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @return FilesystemAdapter
     */
    public function getAdapter(): FilesystemAdapter
    {
        return match ($this->config['fs_adapter']) {
            'ftp'   => new FtpAdapter(FtpConnectionOptions::fromArray($this->getFtpConfig())),
            'sftp'  => new SftpAdapter($this->getSftpConfig(), \rtrim($this->config['sftp_path'], '/') . '/'),
            default => new LocalFilesystemAdapter(\PFAD_ROOT),
        };
    }

    public function setAdapter(string $adapter): void
    {
        $this->config['fs_adapter'] = $adapter;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setFtpConfig(array $config): void
    {
        $this->config = \array_merge($this->config, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setSftpConfig(array $config): void
    {
        $this->config = \array_merge($this->config, $config);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFtpConfig(): array
    {
        return [
            'host'                 => $this->config['ftp_hostname'],
            'port'                 => $this->config['ftp_port'],
            'username'             => $this->config['ftp_user'],
            'password'             => $this->config['ftp_pass'],
            'ssl'                  => (int)$this->config['ftp_ssl'] === 1,
            'root'                 => \rtrim($this->config['ftp_path'], '/') . '/',
            'timeout'              => $this->config['fs_timeout'],
            'passive'              => true,
            'ignorePassiveAddress' => false
        ];
    }

    public function getSftpConfig(): SftpConnectionProvider
    {
        $pass    = empty($this->config['sftp_pass']) ? null : $this->config['sftp_pass'];
        $key     = empty($this->config['sftp_privkey']) ? null : $this->config['sftp_privkey'];
        $keyPass = null;
        if ($key !== null && $pass !== null) {
            $keyPass = $pass;
        }

        return new SftpConnectionProvider(
            $this->config['sftp_hostname'],
            $this->config['sftp_user'],
            $pass,
            $key,
            $keyPass,
            $this->config['sftp_port'],
            false,
            $this->config['fs_timeout']
        );
    }
}
