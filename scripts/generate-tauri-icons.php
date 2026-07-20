<?php

$dir = dirname(__DIR__).'/src-tauri/icons';

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

function makePng(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    $bg = imagecolorallocate($im, 20, 83, 45);
    $fg = imagecolorallocate($im, 240, 253, 244);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);

    $pad = (int) round($size * 0.22);
    imagefilledrectangle($im, $pad, $pad, $size - $pad - 1, $size - $pad - 1, $fg);

    $inner = (int) round($size * 0.34);
    imagefilledrectangle($im, $inner, $inner, $size - $inner - 1, $size - $inner - 1, $bg);

    imagepng($im, $path);
    imagedestroy($im);
}

function pngToIco(string $pngPath, string $icoPath): void
{
    $png = file_get_contents($pngPath);
    $info = getimagesize($pngPath);
    $w = $info[0];
    $h = $info[1];

    $header = pack('vvv', 0, 1, 1);
    $entry = pack(
        'CCCCvvVV',
        $w >= 256 ? 0 : $w,
        $h >= 256 ? 0 : $h,
        0,
        0,
        1,
        32,
        strlen($png),
        6 + 16,
    );

    file_put_contents($icoPath, $header.$entry.$png);
}

makePng(32, $dir.'/32x32.png');
makePng(128, $dir.'/128x128.png');
makePng(256, $dir.'/128x128@2x.png');
makePng(512, $dir.'/icon.png');
pngToIco($dir.'/icon.png', $dir.'/icon.ico');

echo "Wrote icons to {$dir}\n";
foreach (scandir($dir) as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    echo $file.' '.filesize($dir.'/'.$file)."\n";
}
