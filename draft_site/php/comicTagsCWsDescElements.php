<?php
if (!defined('UNSETDEFAULT')) define('UNSETDEFAULT', '');

if (!isset($tags)) $tags = [UNSETDEFAULT];
if (!isset($contWarns)) $contWarns = [UNSETDEFAULT];
if (!isset($pageRecord)) $pageRecord = ['imagedesc' => UNSETDEFAULT];
?>

<section id="tag_section">
    <h2>Tags</h2>
    <p id="tags-para">
    <?php
        foreach ($tags as $tag) {
            echo "<a href=$searchPageLocation?tag=$tag>$tag</a>\n";
        }
    ?>
    </p>
</section>

<section id="cw_section">
    <h2>Content Warnings</h2>
    <p id="cws-para">
    <?php
        foreach ($contWarns as $cw) {
            echo "<a href=$searchPageLocation?cw=$cw>$cw</a>\n";
        }
    ?>
    </p>
</section>

<section id="desc_section">
    <h2><a href="#text_description" 
            class="text_desc_heading">Text Description</a></h2>
    <p class="text-desc">
        <?= $pageRecord["imagedesc"] ?>
    </p>
</section>
