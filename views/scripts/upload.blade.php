#!/bin/bash

@include('sftp-storage::scripts.partials.auth-setup')

SFTP_BATCH=$(mktemp /tmp/sftp_batch_XXXXXX)
cat > "$SFTP_BATCH" <<'BATCHEOF'
{!! $batchCmds !!}BATCHEOF

@if($useKeyAuth)
sftp -i "$SFTP_PRIVKEY" -o BatchMode=yes -P {!! $port !!} -o StrictHostKeyChecking=no -b "$SFTP_BATCH" {!! $username . '@' . $host !!}
@else
sshpass -e sftp -o BatchMode=no -P {!! $port !!} -o StrictHostKeyChecking=no -b "$SFTP_BATCH" {!! $username . '@' . $host !!}
@endif
RESULT=$?
rm -f "$SFTP_BATCH"
@include('sftp-storage::scripts.partials.auth-cleanup')

if [ $RESULT -ne 0 ]; then
    echo {!! $errorMsg !!} >&2
    exit 1
fi
