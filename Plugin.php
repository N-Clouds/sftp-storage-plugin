<?php

namespace App\Vito\Plugins\NClouds\SftpStoragePlugin;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterStorageProvider;

class Plugin extends AbstractPlugin
{
    protected string $name = 'SFTP Storage Plugin';

    protected string $description = 'SFTP storage provider for VitoDeploy backups';

    public function boot(): void
    {
        RegisterStorageProvider::make('ftp')
            ->label('SFTP')
            ->handler(SftpStorageProvider::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('host')
                        ->text()
                        ->label('Host')
                        ->placeholder('sftp.example.com'),

                    DynamicField::make('port')
                        ->text()
                        ->label('Port')
                        ->default('22'),

                    DynamicField::make('username')
                        ->text()
                        ->label('Username'),

                    DynamicField::make('password')
                        ->text()
                        ->label('Password')
                        ->description('Leave empty if using SSH key authentication'),

                    DynamicField::make('private_key')
                        ->text()
                        ->label('Private Key')
                        ->description('PEM-formatted SSH private key (optional, alternative to password)'),

                    DynamicField::make('path')
                        ->text()
                        ->label('Remote Path')
                        ->placeholder('/backups')
                        ->description('Base path on the remote SFTP server'),
                ])
            )
            ->register();
    }
}
