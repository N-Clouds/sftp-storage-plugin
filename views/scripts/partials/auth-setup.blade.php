@if($useKeyAuth)
SFTP_PRIVKEY=$(mktemp /tmp/sftp_key_XXXXXX)
trap 'rm -f "$SFTP_PRIVKEY"' EXIT
cat > "$SFTP_PRIVKEY" <<'SFTPKEYEOF'
{!! $privateKey !!}
SFTPKEYEOF
chmod 600 "$SFTP_PRIVKEY"
@else
if ! command -v sshpass &> /dev/null; then
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y sshpass > /dev/null 2>&1
fi
export SSHPASS={!! $password !!}
@endif
