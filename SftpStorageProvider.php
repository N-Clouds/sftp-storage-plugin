<?php

namespace App\Vito\Plugins\NClouds\SftpStoragePlugin;

use App\Models\Server;
use App\SSH\Storage\Storage;
use App\StorageProviders\AbstractStorageProvider;
use Exception;
use Illuminate\Validation\ValidationException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpStorageProvider extends AbstractStorageProvider
{
    public static function id(): string
    {
        return 'ftp';
    }

    public function validationRules(): array
    {
        return [
            'host'        => 'required|string',
            'port'        => 'required|numeric|min:1|max:65535',
            'username'    => 'required|string',
            'password'    => 'required_without:private_key|nullable|string',
            'private_key' => 'required_without:password|nullable|string',
            'path'        => 'required|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function credentialData(array $input): array
    {
        $path = $input['path'] ?? '/';
        $path = rtrim($path, '/') ?: '/';

        $privateKey = $input['private_key'] ?? null;
        if (! empty($privateKey)) {
            $privateKey = $this->normalizePrivateKey($privateKey);
        }

        return [
            'host'        => $input['host'],
            'port'        => (int) $input['port'],
            'username'    => $input['username'],
            'password'    => $input['password'] ?? null,
            'private_key' => $privateKey,
            'path'        => $path,
        ];
    }

    /**
     * Test connection when saving credentials.
     * Establishes a real SFTP connection and verifies write permissions.
     */
    public function connect(): bool
    {
        try {
            $sftp = $this->buildConnection();

            $path     = $this->storageProvider->credentials['path'] ?? '/';
            $path     = rtrim($path, '/') ?: '/';
            $testFile = rtrim($path, '/') . '/.vitodeploy_test_' . time();

            // Create target path if it does not exist
            if ($path !== '/' && ! $sftp->is_dir($path)) {
                if (! $sftp->mkdir($path, -1, true)) {
                    throw new Exception(
                        "Remote path \"{$path}\" does not exist and could not be created."
                    );
                }
            }

            // Write test
            if (! $sftp->put($testFile, 'vitodeploy-sftp-test')) {
                throw new Exception(
                    "Write test failed. Check permissions on \"{$path}\"."
                );
            }

            // Cleanup
            $sftp->delete($testFile);

            return true;

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'host' => 'SFTP connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns the storage implementation for backup operations.
     */
    public function ssh(Server $server): Storage
    {
        return new SftpStorage($server, $this->storageProvider);
    }

    /**
     * Normalize a private key that may have been pasted without newlines.
     */
    private function normalizePrivateKey(string $key): string
    {
        $key = trim($key);

        // Already has newlines — just normalize line endings
        if (str_contains($key, "\n")) {
            return str_replace("\r\n", "\n", $key);
        }

        // Single-line paste: reconstruct the PEM structure
        // Matches both "BEGIN OPENSSH PRIVATE KEY" and "BEGIN RSA PRIVATE KEY" etc.
        if (preg_match('/^(-----BEGIN [A-Z ]+-----)\s*(.+?)\s*(-----END [A-Z ]+-----)$/', $key, $m)) {
            $body = trim($m[2]);
            // Split the base64 body into 70-char lines
            $body = wordwrap($body, 70, "\n", true);

            return $m[1] . "\n" . $body . "\n" . $m[3] . "\n";
        }

        return $key;
    }

    /**
     * Establishes the phpseclib SFTP connection.
     */
    public function buildConnection(): SFTP
    {
        $c = $this->storageProvider->credentials;

        $sftp = new SFTP($c['host'], (int) ($c['port'] ?? 22));
        $sftp->setTimeout(10);

        $authenticated = false;

        if (! empty($c['private_key'])) {
            try {
                $key           = PublicKeyLoader::load($c['private_key']);
                $authenticated = $sftp->login($c['username'], $key);
            } catch (Exception $e) {
                throw new Exception('Invalid private key: ' . $e->getMessage());
            }
        }

        if (! $authenticated && ! empty($c['password'])) {
            $authenticated = $sftp->login($c['username'], $c['password']);
        }

        if (! $authenticated) {
            throw new Exception(
                "Authentication failed for user \"{$c['username']}\" on {$c['host']}:{$c['port']}. " .
                'Check your username, password or private key.'
            );
        }

        return $sftp;
    }
}
