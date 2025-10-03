<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum FS: string implements OptionInterface
{
    case ADAPTER      = 'fs_adapter';
    case TIMEOUT      = 'fs_timeout';
    case FTP_HOST     = 'ftp_hostname';
    case FTP_PORT     = 'ftp_port';
    case FTP_USER     = 'ftp_user';
    case FTP_PASS     = 'ftp_pass';
    case FTP_SSL      = 'ftp_ssl';
    case FTP_PATH     = 'ftp_path';
    case SFTP_HOST    = 'sftp_hostname';
    case SFTP_PORT    = 'sftp_port';
    case SFTP_USER    = 'sftp_user';
    case SFTP_PASS    = 'sftp_pass';
    case SFTP_PRIVKEY = 'sftp_privkey';
    case SFTP_PATH    = 'sftp_path';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::FILESYSTEM;
    }
}
