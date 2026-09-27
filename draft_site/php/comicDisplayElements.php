<?php
// 
//  THESE WOULD BE DEFINED IN PHP THAT REQUIRES THIS
//
if (!defined('UNSETDEFAULT')) define('UNSETDEFAULT', '');
if (!defined('DISPLAYWIDTH1')) define('DISPLAYWIDTH1', 1920);
if (!defined('DISPLAYWIDTH2')) define('DISPLAYWIDTH2', 3000);

if (!isset($fileRecords)) $fileRecords = [['width' => UNSETDEFAULT, 
                                           'height' => UNSETDEFAULT, 
                                           'location' => UNSETDEFAULT, 
                                           'alttext' => UNSETDEFAULT]];
if (!isset($srcDefaultWidths)) {
    $srcDefaultWidths = array_column($fileRecords, 'width');
    if (count($srcDefaultWidths) > 1) {
        sort($srcDefaultWidths);
        [$srcDefaultWidths[0], $srcDefaultWidths[1]] = [$srcDefaultWidths[1], $srcDefaultWidths[0]];
    }
}
if (!isset($prevLink)) $prevLink = UNSETDEFAULT;
if (!isset($nextLink)) $nextLink = UNSETDEFAULT;
if (!isset($windowWidthsOrder)) $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2];

//
//  START OF ACTUAL CODE
//
$filesWidthOrder = array_combine(array_column($fileRecords, 'width'), 
                                 $fileRecords);
ksort($filesWidthOrder);

$srcWidth = '';
foreach ($srcDefaultWidths as $width) {
    if (\array_key_exists($width, $filesWidthOrder)) {
        $srcWidth = $width;
        break;
    }
}
?>

<map name="nav-on-comic">
    <!-- Left quarter of image goes back -->
    <!-- To change with javascript (coords) -->
    <area
        shape="rect"
        coords="0,0,<?= 0.25 * $srcWidth ?>,<?= 
                $filesWidthOrder[$srcWidth]['height'] 
            ?>"
        href="<?= $prevLink ?>"
        alt="Previous"
        class="nav-button prev-button"
    />
    <!-- Right quarter goes forward -->
    <!-- To change with javascript (coords) -->
    <area
        shape="rect"
        coords="<?= 0.75 * $srcWidth ?>,0,<?= $srcWidth ?>,<?= 
                $filesWidthOrder[$srcWidth]['height'] 
                ?>"
        href="<?= $nextLink ?>"
        alt="Next"
        class="nav-button next-button"
    />
</map>

<img
    class="comic-page"
    srcset="<?php
foreach ($filesWidthOrder as $file) {
    echo $file['location'] . ' ' . $file['width'] . "w\n";
}
reset($filesWidthOrder);
    ?>"
    sizes="(max-width: <?= current($filesWidthOrder)['width'] ?>px) 100vw, 
    <?php 
// Undefined behavior if `count(filesWidthOrder) != count($windowWidthsOrder) + 1`
foreach ($windowWidthsOrder as $maxWidth) {
    echo "(max-width: {$maxWidth}px) "
            . next($filesWidthOrder)['width'] 
            . "px,\n";
}
    ?>
        <?= array_last($filesWidthOrder)['width'] ?>px"   
        src="<?= $filesWidthOrder[$srcWidth]['location'] ?>"
        alt="<?= $filesWidthOrder[$srcWidth]['alttext'] ?>"
        usemap="#nav-on-comic"
        id="single-page"
>