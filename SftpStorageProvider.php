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
            'password'    => 'nullable|string',
            'private_key' => 'nullable|string',
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

        return [
            'host'        => $input['host'],
            'port'        => (int) $input['port'],
            'username'    => $input['username'],
            'password'    => $input['password'] ?? null,
            'private_key' => $input['private_key'] ?? null,
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
