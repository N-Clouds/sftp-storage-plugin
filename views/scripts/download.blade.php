#!/bin/bash

@include('sftp-storage::scripts.partials.auth-setup')

mkdir -p "$(dirname {!! $dest !!})"
@if($useKeyAuth)
scp -i "$SFTP_PRIVKEY" -P {!! $port !!} -o StrictHostKeyChecking=no {!! $username . '@' . $host !!}:{!! $srcPath !!} {!! $dest !!}
@else
sshpass -e scp -P {!! $port !!} -o StrictHostKeyChecking=no {!! $username . '@' . $host !!}:{!! $srcPath !!} {!! $dest !!}
@endif
RESULT=$?
@include('sftp-storage::scripts.partials.auth-cleanup')

if [ $RESULT -ne 0 ]; then
    echo "SCP download failed for {!! $srcPath !!}" >&2
    exit 1
fi
echo "downloaded {!! $srcPath !!} to {!! $dest !!}"
