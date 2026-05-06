<?php

namespace App\Vito\Plugins\NClouds\SftpStoragePlugin;

use App\Exceptions\SSHError;
use App\SSH\Storage\AbstractStorage;
use Illuminate\Contracts\View\View;

class SftpStorage extends AbstractStorage
{
    private const string VIEW_NAMESPACE = 'sftp-storage';

    /**
     * Upload file to SFTP server.
     *
     * @throws SSHError
     */
    public function upload(string $src, string $dest): array
    {
        $destPath = '/' . ltrim($dest, '/');
        $batchCmds = $this->buildMkdirBatchCmds(dirname($destPath));
        $batchCmds .= 'put ' . $this->quoteSftpPath($src) . ' ' . $this->quoteSftpPath($destPath) . "\n";

        $this->server->ssh()->exec(
            $this->view('scripts.upload', [
                ...$this->authVars(),
                'batchCmds' => $batchCmds,
                'errorMsg'  => escapeshellarg("SFTP upload failed for {$src} -> {$destPath}"),
            ]),
            'upload-to-sftp'
        );

        $size = (int) $this->server->ssh()->exec(
            'stat -c%s ' . escapeshellarg($src) . ' 2>/dev/null || echo 0',
            'get-file-size'
        );

        return [
            'size' => $size > 0 ? $size : null,
        ];
    }

    /**
     * Download file from SFTP server.
     *
     * @throws SSHError
     */
    public function download(string $src, string $dest): void
    {
        $srcPath = '/' . ltrim($src, '/');

        $this->server->ssh()->exec(
            $this->view('scripts.download', [
                ...$this->authVars(),
                'srcPath' => escapeshellarg($srcPath),
                'dest'    => escapeshellarg($dest),
            ]),
            'download-from-sftp'
        );
    }

    /**
     * Delete file from SFTP server.
     *
     * @throws SSHError
     */
    public function delete(string $src): void
    {
        if (trim($src) === '') {
            return;
        }

        $srcPath = '/' . ltrim($src, '/');
        $batchCmds = 'rm ' . $this->quoteSftpPath($srcPath) . "\n";

        $this->server->ssh()->exec(
            $this->view('scripts.delete-file', [
                ...$this->authVars(),
                'batchCmds' => $batchCmds,
            ]),
            'delete-from-sftp'
        );
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Render a Blade view, ensuring the view namespace is registered.
     */
    private function view(string $template, array $data): View
    {
        $finder = app('view')->getFinder();

        if (! isset($finder->getHints()[self::VIEW_NAMESPACE])) {
            app('view')->addNamespace(self::VIEW_NAMESPACE, __DIR__ . '/views');
        }

        return view(self::VIEW_NAMESPACE . '::' . $template, $data);
    }

    /**
     * Common auth-related variables for all views.
     */
    private function authVars(): array
    {
        return [
            'useKeyAuth' => ! empty($this->cred('private_key')),
            'privateKey'  => $this->cred('private_key') ?? '',
            'password'    => escapeshellarg($this->cred('password') ?? ''),
            'host'        => escapeshellarg($this->cred('host')),
            'port'        => (int) $this->cred('port'),
            'username'    => escapeshellarg($this->cred('username')),
        ];
    }

    /**
     * Build sftp -mkdir batch commands for each directory level.
     */
    private function buildMkdirBatchCmds(string $destDir): string
    {
        $cmds = '';
        $parts = explode('/', ltrim($destDir, '/'));
        $current = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current .= '/' . $part;
            $cmds .= '-mkdir ' . $this->quoteSftpPath($current) . "\n";
        }

        return $cmds;
    }

    /**
     * Quote a path for use in sftp batch commands.
     */
    private function quoteSftpPath(string $path): string
    {
        return '"' . str_replace('"', '\\"', $path) . '"';
    }

    private function cred(string $key): mixed
    {
        return $this->storageProvider->credentials[$key] ?? null;
    }
}
