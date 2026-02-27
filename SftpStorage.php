<?php

namespace App\Vito\Plugins\NClouds\SftpStoragePlugin;

use App\Exceptions\SSHError;
use App\SSH\Storage\AbstractStorage;

class SftpStorage extends AbstractStorage
{
    /**
     * Upload file to SFTP server.
     *
     * @param string $src  Local file path on the worker server
     * @param string $dest Full remote path from BackupFile::path()
     *
     * @throws SSHError
     */
    public function upload(string $src, string $dest): array
    {
        $destPath = '/' . ltrim($dest, '/');
        $destDir = dirname($destPath);

        $this->server->ssh()->exec(
            $this->buildUploadScript($src, $destPath, $destDir),
            'upload-to-sftp'
        );

        // Get file size from the worker host
        $size = (int) $this->server->ssh()->exec(
            "stat -c%s " . escapeshellarg($src) . " 2>/dev/null || echo 0",
            'get-file-size'
        );

        return [
            'size' => $size > 0 ? $size : null,
        ];
    }

    /**
     * Download file from SFTP server.
     *
     * @param string $src  Full remote path from BackupFile::path()
     * @param string $dest Local destination path on the worker server
     *
     * @throws SSHError
     */
    public function download(string $src, string $dest): void
    {
        $srcPath = '/' . ltrim($src, '/');

        $host = $this->cred('host');
        $port = (int) $this->cred('port');
        $username = $this->cred('username');
        $password = $this->escapeForPhp($this->cred('password') ?? '');

        $script = <<<BASH
if ! php -r "extension_loaded('ssh2') or exit(1);" 2>/dev/null; then
    apt-get install -y php-ssh2 > /dev/null 2>&1
fi
mkdir -p $(dirname "{$dest}")
php -r '
\$conn = ssh2_connect("{$host}", {$port});
if (!\$conn) { fwrite(STDERR, "SSH2: connect failed\n"); exit(1); }
if (!ssh2_auth_password(\$conn, "{$username}", "{$password}")) { fwrite(STDERR, "SSH2: auth failed\n"); exit(1); }
\$sftp = ssh2_sftp(\$conn);
if (!\$sftp) { fwrite(STDERR, "SSH2: sftp init failed\n"); exit(1); }
if (!ssh2_scp_recv(\$conn, "{$srcPath}", "{$dest}")) {
    fwrite(STDERR, "SSH2: download failed for {$srcPath}\n"); exit(1);
}
echo "downloaded {$srcPath} to {$dest}\n";
'
BASH;

        $this->server->ssh()->exec($script, 'download-from-sftp');
    }

    /**
     * Delete file from SFTP server.
     *
     * @param string $src Full remote path from BackupFile::path()
     *
     * @throws SSHError
     */
    public function delete(string $src): void
    {
        if (trim($src) === '') {
            return;
        }

        $srcPath = '/' . ltrim($src, '/');

        $host = $this->cred('host');
        $port = (int) $this->cred('port');
        $username = $this->cred('username');
        $password = $this->escapeForPhp($this->cred('password') ?? '');

        $script = <<<BASH
if ! php -r "extension_loaded('ssh2') or exit(1);" 2>/dev/null; then
    apt-get install -y php-ssh2 > /dev/null 2>&1
fi
php -r '
\$conn = ssh2_connect("{$host}", {$port});
if (!\$conn) { fwrite(STDERR, "SSH2: connect failed\n"); exit(1); }
if (!ssh2_auth_password(\$conn, "{$username}", "{$password}")) { fwrite(STDERR, "SSH2: auth failed\n"); exit(1); }
\$sftp = ssh2_sftp(\$conn);
if (!\$sftp) { fwrite(STDERR, "SSH2: sftp init failed\n"); exit(1); }
if (@ssh2_sftp_stat(\$sftp, "{$srcPath}") !== false) {
    if (!ssh2_sftp_unlink(\$sftp, "{$srcPath}")) {
        fwrite(STDERR, "SSH2: delete failed for {$srcPath}\n"); exit(1);
    }
    echo "deleted {$srcPath}\n";
} else {
    echo "not found, skipping: {$srcPath}\n";
}
'
BASH;

        $this->server->ssh()->exec($script, 'delete-from-sftp');
    }

    private function buildUploadScript(string $src, string $destPath, string $destDir): string
    {
        $host = $this->cred('host');
        $port = (int) $this->cred('port');
        $username = $this->cred('username');
        $password = $this->escapeForPhp($this->cred('password') ?? '');

        // Build mkdir commands for each directory level
        $mkdirCode = '';
        $parts = explode('/', ltrim($destDir, '/'));
        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current .= '/' . $part;
            $mkdirCode .= "@ssh2_sftp_mkdir(\$sftp, \"{$current}\", 0755, false); ";
        }

        return <<<BASH
if ! php -r "extension_loaded('ssh2') or exit(1);" 2>/dev/null; then
    apt-get install -y php-ssh2 > /dev/null 2>&1
fi
php -r '
\$conn = ssh2_connect("{$host}", {$port});
if (!\$conn) { fwrite(STDERR, "SSH2: connect failed\n"); exit(1); }
if (!ssh2_auth_password(\$conn, "{$username}", "{$password}")) { fwrite(STDERR, "SSH2: auth failed\n"); exit(1); }
\$sftp = ssh2_sftp(\$conn);
if (!\$sftp) { fwrite(STDERR, "SSH2: sftp init failed\n"); exit(1); }
{$mkdirCode}
if (!ssh2_scp_send(\$conn, "{$src}", "{$destPath}", 0644)) {
    fwrite(STDERR, "SSH2: upload failed for {$src} -> {$destPath}\n");
    exit(1);
}
echo "uploaded {$src} to {$destPath}\n";
'
BASH;
    }

    private function cred(string $key): mixed
    {
        return $this->storageProvider->credentials[$key] ?? null;
    }

    /**
     * Escape string for use in PHP double-quoted string.
     */
    private function escapeForPhp(string $value): string
    {
        return addcslashes($value, '"\\$');
    }
}
