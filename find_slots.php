<?php
// find_slots.php
$file = __DIR__ . '/assets/frames/frame-Reality-Club-Presents....png';
if (!file_exists($file)) die("File not found\n");

$img = imagecreatefrompng($file);
$width = imagesx($img);
$height = imagesy($img);

echo "Image: $width x $height\n";

$is_transparent = function($x, $y) use ($img) {
    $color = imagecolorat($img, $x, $y);
    $colors = imagecolorsforindex($img, $color);
    return $colors['alpha'] >= 100; // high alpha means transparent in GD
};

$transparent_pixels = [];
for ($y = 0; $y < $height; $y+=5) { // step by 5 for speed
    for ($x = 0; $x < $width; $x+=5) {
        if ($is_transparent($x, $y)) {
            $transparent_pixels[] = [$x, $y];
        }
    }
}

// Simple clustering to find bounding boxes
$boxes = [];
foreach ($transparent_pixels as $p) {
    $x = $p[0]; $y = $p[1];
    $matched = false;
    foreach ($boxes as &$b) {
        // if close to existing box (within 50px)
        if ($x >= $b['minX'] - 50 && $x <= $b['maxX'] + 50 &&
            $y >= $b['minY'] - 50 && $y <= $b['maxY'] + 50) {
            $b['minX'] = min($b['minX'], $x);
            $b['maxX'] = max($b['maxX'], $x);
            $b['minY'] = min($b['minY'], $y);
            $b['maxY'] = max($b['maxY'], $y);
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $boxes[] = ['minX' => $x, 'maxX' => $x, 'minY' => $y, 'maxY' => $y];
    }
}

// Merge boxes that might have overlapped
$merged = true;
while ($merged) {
    $merged = false;
    for ($i = 0; $i < count($boxes); $i++) {
        for ($j = $i + 1; $j < count($boxes); $j++) {
            $b1 = $boxes[$i]; $b2 = $boxes[$j];
            if (!($b1['maxX'] < $b2['minX'] - 50 || $b1['minX'] > $b2['maxX'] + 50 ||
                  $b1['maxY'] < $b2['minY'] - 50 || $b1['minY'] > $b2['maxY'] + 50)) {
                $boxes[$i]['minX'] = min($b1['minX'], $b2['minX']);
                $boxes[$i]['maxX'] = max($b1['maxX'], $b2['maxX']);
                $boxes[$i]['minY'] = min($b1['minY'], $b2['minY']);
                $boxes[$i]['maxY'] = max($b1['maxY'], $b2['maxY']);
                array_splice($boxes, $j, 1);
                $merged = true;
                break 2;
            }
        }
    }
}

// Print percentages
usort($boxes, function($a, $b) { return $a['minY'] <=> $b['minY']; });
foreach ($boxes as $i => $b) {
    if ($b['maxX'] - $b['minX'] < 50 || $b['maxY'] - $b['minY'] < 50) continue; // ignore noise
    $x_pct = round(($b['minX'] / $width) * 100, 2);
    $y_pct = round(($b['minY'] / $height) * 100, 2);
    $w_pct = round((($b['maxX'] - $b['minX']) / $width) * 100, 2);
    $h_pct = round((($b['maxY'] - $b['minY']) / $height) * 100, 2);
    echo "Slot $i: ['x' => $x_pct, 'y' => $y_pct, 'width' => $w_pct, 'height' => $h_pct],\n";
}
