<?php
if (!isset($fileRecords)) $fileRecords = [['width' => '', 
                                           'height' => '', 
                                           'location' => '', 
                                           'alttext' => '']];
if (!isset($srcDefaultWidths)) $srcDefaultWidths = [1400, 800, 2000];
if (!isset($prevLink)) $prevLink = '';
if (!isset($nextLink)) $nextLink = '';
if (!isset($windowWidthsOrder)) $windowWidthsOrder = [1500, 2400];

$filesWidthOrder = array_combine(array_column($fileRecords, 
                                              'width'), 
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
        coords="0,0,<?= $srcWidth / 4 ?>,<?= 
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
        coords="<?= 3 * $srcWidth / 4 ?>,0,<?= $srcWidth ?>,<?= 
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
        id="single_page"
>
<!-- Resource on web accessibility for complex images: 
https://www.w3.org/WAI/tutorials/images/complex/ -->