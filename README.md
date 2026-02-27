# SFTP Storage Plugin for VitoDeploy

A VitoDeploy 3.x plugin that adds SFTP as a storage provider for database and file backups.

## Features

- **Upload**: Store database and file backups on remote SFTP servers
- **Download**: Restore backups from SFTP storage
- **Delete**: Automatic cleanup of old backups based on retention settings
- **Authentication**: Supports both password and SSH private key authentication
- **Connection Test**: Validates credentials and write permissions on setup

## Requirements

- VitoDeploy 3.x
- PHP 8.2+
- PHP SSH2 extension on target servers (auto-installed if missing)

## Installation

1. Copy the plugin to your VitoDeploy plugins directory:
   ```
   app/Vito/Plugins/NClouds/SftpStoragePlugin/
   ```

2. Enable the plugin in **Admin → Plugins**

3. Create a new storage provider in **Settings → Storage Providers** and select **SFTP**

## Configuration

| Field | Required | Description |
|-------|----------|-------------|
| Host | Yes | SFTP server hostname or IP address |
| Port | Yes | SFTP port (default: 22) |
| Username | Yes | SFTP username |
| Password | No | SFTP password (leave empty for key-based auth) |
| Private Key | No | PEM-formatted SSH private key |
| Remote Path | Yes | Base directory for backups (e.g., `/backups`) |

> **Note**: Either **Password** or **Private Key** must be provided.

## How It Works

### Backup Storage Structure

Backups are stored in the following structure:
```
/remote-path/database-name/backup-name-timestamp.zip     (database backups)
/remote-path/site-name/backup-name-timestamp.tar.gz      (file backups)
```

### Operations

| Operation | Description |
|-----------|-------------|
| **Upload** | Executed via SSH on the worker server using PHP's ssh2 extension. Creates remote directories automatically. |
| **Download** | Retrieves backup files from SFTP server to the worker server for restoration. |
| **Delete** | Removes backup files from SFTP storage when retention limits are exceeded or manual deletion is triggered. |

### Technical Note

This plugin registers with the provider ID `ftp` to leverage VitoDeploy's built-in path resolution. This ensures correct path generation for all backup operations without requiring core modifications.

**Important**: This plugin replaces the default FTP storage provider. If you need both FTP and SFTP storage simultaneously, this plugin cannot be used.

## Authentication

### Password Authentication
Enter username and password in the configuration form.

### SSH Key Authentication
1. Leave the password field empty
2. Paste your PEM-formatted private key in the "Private Key" field
3. Supported formats: RSA, DSA, ECDSA, Ed25519

SSH key authentication is preferred when both password and key are provided.

## Connection Test

When saving storage provider credentials, the plugin automatically:

1. Connects to the SFTP server
2. Verifies or creates the remote path
3. Performs a write test (creates and deletes a temporary file)

If any step fails, a clear error message is shown and the credentials are not saved.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Connection Failed | Verify hostname, port, and firewall rules |
| Authentication Failed | Check username, password, or private key format |
| Upload/Download Failed | Verify remote path exists and user has write permissions |
| Permission Denied | Check directory ownership and permissions on SFTP server |

## License

MIT
