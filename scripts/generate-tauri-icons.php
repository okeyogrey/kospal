<?php

$script = __DIR__.'/generate-brand-icons.py';
$candidates = ['python', 'python3', 'py'];

foreach ($candidates as $binary) {
    $command = $binary.' '.escapeshellarg($script);
    passthru($command, $code);

    if ($code === 0) {
        exit(0);
    }
}

fwrite(STDERR, "Python with Pillow is required to regenerate brand icons.\n");
exit(1);
