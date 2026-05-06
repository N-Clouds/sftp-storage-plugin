@if($useKeyAuth)
rm -f "$SFTP_PRIVKEY"
@else
unset SSHPASS
@endif
