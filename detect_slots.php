<?php
$img = imagecreatefrompng('assets/frames/frame-film-strip.png');
$width = imagesx($img);
$height = imagesy($img);

echo "Size: {$width}x{$height}\n";

// Simple scan to find transparent rectangles.
// Since it might not be perfect rectangles, let's just find horizontal rows that are mostly transparent.
for ($y = 0; $y < $height; $y += 10) {
    $transparent_count = 0;
    for ($x = 0; $x < $width; $x += 10) {
        $color_index = imagecolorat($img, $x, $y);
        $color_tran = imagecolorsforindex($img, $color_index);
        if ($color_tran['alpha'] > 100) { // transparent
            $transparent_count++;
        }
    }
    if ($transparent_count > ($width / 10) * 0.5) {
        echo "Row $y is mostly transparent\n";
    }
}
