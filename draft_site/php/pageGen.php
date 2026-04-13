<?php
namespace Briel;

function classIs($node, $className) {
    return str_contains($node->className, $className);
}

function assignNavLink($node, 
                       $prevLink, 
                       $nextLink, 
                       $prevUpd8Link, 
                       $nextUpd8Link) {
    if (classIs($node, "prev-button")) {
        return $node->setAttribute("href", $prevLink);
    } else if (classIs($node, "next-button")) {
        return $node->setAttribute("href", $nextLink);
    } else if (classIs($node, "prev-upd8-button")) {
        return $node->setAttribute("href", $prevUpd8Link);
    } else if (classIs($node, "next-upd8-button")) {
        return $node->setAttribute("href", $nextUpd8Link);
    }

    return false;
}

function generateComicPageDOMHTML($pageRecord, 
                                    $prevLink, 
                                    $nextLink, 
                                    $prevUpd8Link, 
                                    $nextUpd8Link,
                                    $widthFileLocations, // values: locations, keys: widths
                                    $alttext, 
                                    $tags, // array of strings
                                    $searchPageLocation, 
                                    $templateFilepath = '../reading_page_template_draft.html'
                                    ) { 
    // Assumes page record has already been created
    $doc = \DOM\HTMLDocument::createFromFile($templateFilepath);

    // Set page title
    $doc->getElementsByTagName("title")->item(0) //should only be one title
        ->insertAdjacentText(\DOM\AdjacentPosition::AfterBegin, $pageRecord["title"]);

    // Set stylesheet, if not the default
    if ($pageRecord["stylelocation"] != "NULL") {
        $doc->getElementById("reading_stylesheet")
            ->setAttribute("href", $pageRecord["stylelocation"]);
    }

    // Set previous and next page links
    foreach ($doc->getElementsByTagName("a") as $a) {
        assignNavLink($a, $prevLink, $nextLink, $prevUpd8Link, $nextUpd8Link);
    }

    // Set previous and next page links in imagemap
    foreach ($doc->getElementsByTagName("area") as $area) {
        assignNavLink($area, $prevLink, $nextLink, $prevUpd8Link, $nextUpd8Link);
    }
    
    // $navMap = $doc->getElementsByTagName("map")->item(0);
    // foreach ($navMap->getElementsByTagName("area") as $area) {
    //     assignNavLink($area, $prevLink, $nextLink);
        // this is just initial assignment, these will change with window using js
        /*if (classIs($area, "prev-button")) {
            $area->setAttribute("coords", implode(",", [0, 
                                                        0, 
                                                        $pageRecord["width"]/4, 
                                                        $pageRecord["height"]]));
        } else if (classIs($area,"next-button")) {
            $area->setAttribute("coords", implode(",", [$pageRecord["width"] * 3/4, 
                                                        0,
                                                        $pageRecord["width"], 
                                                        $pageRecord["height"]]));
        }*/
        // Actually... maybe just do all of this image map stuff in js
        // We'll leave the default area sizes in the html
    // }

    // Set srcset on the comic display
    $comicPage = $doc->getElementById("single_page");
    $usualWidthFileLocations = [];
    $srcsetStr = "";
    foreach (["800", "1400", "2000"] as $width) {
        if (array_key_exists($width, $widthFileLocations)) {
            $usualWidthFileLocations[$width] = $widthFileLocations[$width];
            $srcsetStr .= "{$widthFileLocations[$width]} {$width}w\n";
        } else {
            echo "Image of width $width isn't in database\n";
        }
    }
    $comicPage->setAttribute("srcset", $srcsetStr);

    // Set default src on the comic display
    $srcStr = "";
    //we try to make 1400px the default src, then 800px since it's smallest
    foreach (["1400", "800", "2000"] as $width) {
        if (array_key_exists($width, $usualWidthFileLocations)) {
            $srcStr = $usualWidthFileLocations[$width];
            break;
        } else {
            echo "Image of width $width doesn't exist, so can't be used for src\n";
        }
    }
    $comicPage->setAttribute("src", $srcStr);

    // Set the alt text
    $comicPage->setAttribute("alt", 
                             $alttext 
                                . " Described under the heading Text Description.");

    // Set the tag links
    $tagPara = $doc->getElementById("tags-para");
    foreach ($tags as $tag) {
        $tagEl = $doc->createElement("a");
        $tagEl->textContent = $tag;
        $tagEl->setAttribute("href", $searchPageLocation . "?tag=" . $tag);
        $tagPara->appendChild($tagEl);
    }

    // Set the description
    $doc->getElementById("desc-para")->textContent = $pageRecord["imagedesc"];
    
    return $doc->saveHtmlFile($pageRecord['location']);
}

/**
 * Summary of Briel\generateComicPage
 * @param mixed $pageRecord
 * @param mixed $prevLink
 * @param mixed $nextLink
 * @param mixed $prevUpd8Link
 * @param mixed $nextUpd8Link
 * @param mixed $fileRecords
 * @param mixed $tags
 * @param mixed $contWarns
 * @param mixed $windowWidthsOrder
 * @param mixed $searchPageLocation
 * @param mixed $srcDefaultWidths
 * @param mixed $defaultStyleURL
 * @return bool|int
 */
function generateComicPage($pageRecord, 
                            $prevLink, 
                            $nextLink, 
                            $prevUpd8Link, 
                            $nextUpd8Link,
                            $fileRecords, 
                            $tags, 
                            $contWarns, 
                            $windowWidthsOrder = [1500, 2400], 
                            $searchPageLocation = 'search_page.html', 
                            $srcDefaultWidths = [1400, 800, 2000], 
                            $defaultStyleURL = "./styles/reading_page_style.css") {
    ob_start(); 
?>
<!doctype html>
<html lang="en-US">
    <head>
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta charset="utf-8">
        
        <!-- Prevents mobile browsers from screwing with you. -->
        <meta name="viewport" content="width=device-width">

        <meta name="author" content="Breel">
        <meta name="description" content="A page displaying a comic.">
        
        <title><<?= $pageRecord['title'] ?> | Breel Comix</title>
        <link rel="icon" href="./images/smileicon.ico" type="image/x-icon">

        <link href="<?= $pageRecord['stylelocation'] == 'NULL' 
                            ? $defaultStyleURL
                            : $pageRecord['stylelocation'] ?>" 
              rel="stylesheet" 
              id="reading_stylesheet">

        <script type="module" src="./js/reading_page_script.js"></script>
    </head>

    <body>
        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">
            <main> 
                <?php
    $filesWidthOrder = array_combine(array_column($fileRecords, 
                                                'width'), 
                                     $fileRecords);
    ksort($filesWidthOrder);

    $srcWidth = NULL;
    foreach ($srcDefaultWidths as $width) {
        if (\array_key_exists($width, $filesWidthOrder)) {
            $srcWidth = $width;
            break;
        }
    }
                ?>
                <!-- Will need to use javascript to make this responsive -->
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
                    sizes="(max-width: <?= 
                                current($filesWidthOrder)['width'] 
                            ?>px) 100vw, 
                            <?php 

    // Undefined behavior if `count(filesWidthOrder) != count($windowWidthsOrder) + 1`
    foreach ($windowWidthsOrder as $maxWidth) {
        echo "(max-width: {$maxWidth}px) "
                . next($filesWidthOrder)['width'] 
                . "px,\n";
    }
                            ?>
                            <?=array_last($filesWidthOrder)['width']?>px"   
                    src="<?=$filesWidthOrder[$width]['location']?>"
                    alt="<?= $filesWidthOrder[$srcWidth]['alttext'] ?>"
                    usemap="#nav-on-comic"
                    id="single_page"
                >
                <!-- Resource on web accessibility for complex images: 
                https://www.w3.org/WAI/tutorials/images/complex/ -->
                
                <!-- <p><a href="#text_description">Text Description</a></p> -->

                <nav>
                    <p class="nav-line">
                        <!-- To change with PHP (href) -->
                        <a href="<?= $prevLink ?>" 
                            class="nav-button prev-button">Previous</a>
                        <!-- To change with PHP (href) -->
                        <a href="<?= $nextLink ?>" 
                            class="nav-button next-button">Next</a>
                    </p>
                    <p class="nav-line">
                        <!-- To change with PHP (href) -->
                        <a href="<?= $prevUpd8Link ?>" class="nav-button prev-upd8-button">Skip back</a>
                        <a href="archive_page.html" class="nav-button">Archive</a>
                        <!-- To change with PHP (href) -->
                        <a href="<?= $nextUpd8Link ?>" class="nav-button next-upd8-button">Skip forth</a>
                    </p>
                    <p class="nav-line">
                        <a href="home_page.html" class="nav-button">Home</a>
                    </p>
                </nav>

                <section id="tag_section">
                    <h2>Tags</h2>
                    <!-- To change with PHP (add <a> tag links) -->
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
                    <!-- To change with PHP (add <a> tag links) -->
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
            </main>

            <footer>
                <!-- Can be changed with PHP (change years) -->
                <p class="copyright">
                    ©Copyright 2025-<?= getdate()['year'] ?> by Briel Comics.
                    All rights reserved.
                </p>
            </footer>

        </div>  
        
        <!-- To be changed with PHP (href)-->
        <a href="<?= $nextLink ?>" class="nav-button next-button" title="Next"></a>
    </body>
</html>

<?php
    return file_put_contents($pageRecord['location'], ob_get_flush());
}

/**
 * Summary of Briel\generateReadingStyle
 * @param mixed $filePath
 * @param mixed $bgColor
 * @param mixed $textColor
 * @param mixed $hiliteColor
 * @param mixed $visitedColor
 * @param mixed $comicDefaultWidth
 * @param mixed $pageSectionShrinkFactor
 * @param mixed $fileWidthsOrder
 * @param mixed $windowWidthsOrder
 * @param mixed $gradient
 * @param mixed $bgImageURL
 * @param mixed $stretchBGImg
 * @param mixed $comicTopMargin
 * @return bool|int
 */
function generateReadingStyle($filePath, 
                              $bgColor, 
                              $textColor, 
                              $hiliteColor, 
                              $visitedColor,
                              $comicDefaultWidth,
                              $fileWidthsOrder = [800, 1400, 2000], 
                              $windowWidthsOrder = [1500, 2400],
                              $pageSectionShrinkFactor = 0.95,
                              $comicTopMargin = 8,
                              $gradient = NULL, 
                              $bgImageURL = NULL, 
                              $stretchBGImg = false) {
    ob_start(); 
?>
html {
    <?= $gradient ? "background: $gradient;" : ""; ?>
    <?= $bgImageURL ? "background-image: url($bgImageURL);" : "" ?>
    <?= $stretchBGImg ? "background-size: 100% 100%;" : "" ?>
    /* To stretch an image to always fit. 
     Otherwise, the background image repeats. */
    --bg-color: <?= $bgColor ?>;
    --text-color: <?= $textColor ?>;
    --hilite-color: <?= $hiliteColor ?>;
    --visited-color: <?= $visitedColor ?>;
    background-color: var(--bg-color); /* For just a solid color */

    font-family: Courier, monospace;
    font-size: large;
}

body {
    margin: 0;

    display: flex;
    justify-content: space-between;
    --comic-page-width: <?= $comicDefaultWidth ?>px;
    --shrink-factor: <?= $pageSectionShrinkFactor ?>;
}

/* These don't affect image sizes, 
    but they do affect the text section sizes */
<?php
    for ($i = 0; $i < \count($windowWidthsOrder); $i++) {
?>
@media screen and (min-width: <?= $windowWidthsOrder[$i] ?>px) {
    body {
        --comic-page-width: <?= $fileWidthsOrder[$i + 1] ?>px;
    }
}
<?php } ?>

body, 
footer {
    color: var(--text-color);
    text-align: center;
}

/* Selects the big navigation buttons to the side of the page */
body > a.nav-button {
    width: max(0px, 0.25 * (100vw - var(--comic-page-width)));
    max-height: 100%;
}

@media screen and (max-width: <?= $fileWidthsOrder[0] ?>px) {
    body {
        --shrink-factor: 1;
    }

    body > a.nav-button {
        display: none;
    }
}

h2 {
    margin-top: 0;
    margin-bottom: 0;
}

section[id="desc_section"], 
section[id="tag_section"],
section[id="cw_section"] {
    border: <?= $sectionBorderWidth = 0.14 ?>em solid var(--text-color);
    margin: <?= $sectionMarginHori = 0.5 ?>em auto;
    padding: <?= $sectionPadding = 1 ?>em;
    padding-top: 0.7em;
    max-width: calc(var(--shrink-factor) * var(--comic-page-width) - <?= 
        2*$sectionBorderWidth + 2*$sectionMarginHori + $sectionPadding
    ?>em);
}

/* selects paragraph elements directly preceded by header 2 elements */
h2 + p {
    margin-top: 0;
    margin-bottom: 0;
}

nav {
    /* Make font size of text in the nav pane (previous, next, etc.) 4 
    points bigger than the inherited size */
    font-size: calc(1em + 4pt);
}

a:link {
    color: inherit;
    padding: 0.1em;
    border-radius: 0.1em;
}

a:visited {
    color: var(--visited-color)
}

a:hover {
    color: var(--text-color);
    background: var(--hilite-color);
}

a.text_desc_heading {
    color: var(--text-color);
    text-decoration: inherit;
    background: inherit;
}

.nav-line {
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: calc(var(--shrink-factor) * var(--comic-page-width));
    margin: 0 auto;
}

.nav-line a.nav-button {
    display: block;
    flex: 1 12em;
    padding: 0.5em;
    border-radius: 0;
    text-align: center;
}

.page-display img.comic-page {
    max-width: 100%;
    margin-top: clamp(0px, 0.5*(100vw - var(--comic-page-width)), <?= 
        $comicTopMargin ?>vh);
}

<?php
    return file_put_contents($filePath, ob_get_flush());
}

?>