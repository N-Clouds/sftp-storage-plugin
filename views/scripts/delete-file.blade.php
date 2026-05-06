#!/bin/bash

@include('sftp-storage::scripts.partials.auth-setup')

SFTP_BATCH=$(mktemp /tmp/sftp_batch_XXXXXX)
cat > "$SFTP_BATCH" <<'BATCHEOF'
{!! $batchCmds !!}BATCHEOF

@if($useKeyAuth)
sftp -i "$SFTP_PRIVKEY" -o BatchMode=yes -P {!! $port !!} -o StrictHostKeyChecking=no -b "$SFTP_BATCH" {!! $username . '@' . $host !!} 2>/dev/null
@else
sshpass -e sftp -o BatchMode=no -P {!! $port !!} -o StrictHostKeyChecking=no -b "$SFTP_BATCH" {!! $username . '@' . $host !!} 2>/dev/null
@endif
RESULT=$?
rm -f "$SFTP_BATCH"
@include('sftp-storage::scripts.partials.auth-cleanup')
