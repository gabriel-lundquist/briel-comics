<?php
namespace Briel;
// var_dump($argv);

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

// function srcsetStrFromWidths($widthFileLocations, $desiredWidths) {
//     srcsetStr = "";
//     foreach ($desiredWidths as $width) {
//         if (array_key_exists($width, $widthFileLocations)) {

//     }
// }

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

function generateComicPage($pageRecord, 
                            $prevLink, 
                            $nextLink, 
                            $prevUpd8Link, 
                            $nextUpd8Link,
                            $widthFileLocations, // values: locations, keys: widths
                            $alttext, 
                            $tags, // array of strings
                            $contWarns, // array of strings
                            $searchPageLocation) {
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

        <!-- Can be changed with PHP -->
        <link href="<?= $pageRecord['stylelocation'] == 'NULL' 
                        ? "./styles/reading_page_style.css" 
                        : $pageRecord['stylelocation'] ?>" 
              rel="stylesheet" 
              id="reading_stylesheet">

        <script type="module" src="./js/reading_page_script.js"></script>
    </head>

    <body>
        <!-- To change with PHP (href) -->
        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">
            <main> 
                <!-- Will need to use javascript to make this responsive -->
                <map name="nav-on-comic">
                    <!-- Left quarter of image goes back -->
                    <!-- To change with PHP (href) -->
                    <!-- To change with javascript (coords) -->
                    <area
                        shape="rect"
                        coords="0,0,200,1000"
                        href="<?= $prevLink ?>"
                        alt="Previous"
                        class="nav-button prev-button"
                    />
                    <!-- Right quarter goes forward -->
                    <!-- To change with PHP (href)-->
                    <!-- To change with javascript (coords) -->
                    <area
                        shape="rect"
                        coords="600,0,800,1000"
                        href="<?= $nextLink ?>"
                        alt="Next"
                        class="nav-button next-button"
                    />
                </map>

                <!-- Specifying width and height are good -->
                <!-- To change with PHP (srcset, src, alt) -->
                <img
                    class="comic-page"
                    srcset="<?php
                        $usualWidthFileLocations = [];
                        $srcsetStr = "";
                        foreach (["800", "1400", "2000"] as $width) {
                            if (\array_key_exists($width, $widthFileLocations)) {
                                $usualWidthFileLocations[$width] = $widthFileLocations[$width];
                                $srcsetStr .= "{$widthFileLocations[$width]} {$width}w\n";
                            }
                        }
                        echo $srcsetStr;
                    ?>"
                    sizes="(max-width: 800px) 100vw, 
                            (max-width: 1500px) 800px, 
                            (max-width: 2400px) 1400px, 
                            2000px
                            "   
                    src="<?php 
                        $srcStr = "";
                        foreach (["1400", "800", "2000"] as $width) {
                            if (\array_key_exists($width, $usualWidthFileLocations)) {
                                $srcStr = $usualWidthFileLocations[$width];
                                break;
                            }
                        }
                        echo $srcStr;
                    ?>"
                    alt="<?= $alttext ?>"
                    usemap="#nav-on-comic"
                    id="single_page"
                >
                    <!-- style="display: inline;" -->
                    <!-- width="1080"
                    height="1350" -->
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
                    <h2>Text Description</h2>
                    <!-- To change with PHP (add description text) -->
                    <p id="text-para">
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

?>