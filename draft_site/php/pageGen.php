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

function generateComicPageHTML($pageRecord, 
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
    $comicPage->setAttribute("alt", $alttext 
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
    
}

?>