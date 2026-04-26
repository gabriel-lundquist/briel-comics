<?php
namespace Briel;

const DISPLAYWIDTH1 = 1920;
const DISPLAYWIDTH2 = 3000;

class pageInfo {
    public $pageRecord; 
    public $prevLink;
    public $nextLink; 
    public $prevUpd8Link; 
    public $nextUpd8Link;
    public $fileRecords; 
    public $tags; 
    public $contWarns; 
    public $windowWidthsOrder;
    public $srcDefaultWidths;
    public $date;

    public function __construct($pageRecord, 
                                $prevLink, 
                                $prevUpd8Link, 
                                $fileRecords, 
                                $tags = [], 
                                $contWarns = [], 
                                $nextLink = null, 
                                $nextUpd8Link = null,
                                $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2],  
                                $srcDefaultWidths = [1400, 800, 2000]) {
    
        $this->pageRecord = $pageRecord;
        $this->prevLink = $prevLink;
        $this->prevUpd8Link = $prevUpd8Link; 
        $this->fileRecords = $fileRecords; 
        $this->tags = $tags;
        $this->contWarns = $contWarns;
        $this->nextLink = $nextLink;
        $this->nextUpd8Link = $nextUpd8Link;
        $this->windowWidthsOrder = $windowWidthsOrder;
        $this->srcDefaultWidths = $srcDefaultWidths;
        $this->date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', 
                                                           $pageRecord['postdate']);
    }
}

class updateInfo {
    public $updateRecord;
    public $date;
    public $tags;
    public $contWarns;
    public $pageRecordsOrdered;
    public $thumbnailRecordsOrdered;

    public function __construct($updateRecord,
                                $pageRecordsOrdered,
                                $thumbnailRecordsOrdered,
                                $tags = [],
                                $contWarns = []) {
        $this->$updateRecord = $updateRecord;
        $this->$date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', 
                                                            $updateRecord['postdate']);
        $this->$tags = $tags;
        $this->contWarns = $contWarns;
        $this->$pageRecordsOrdered = $pageRecordsOrdered;
        $this->$thumbnailRecordsOrdered = $thumbnailRecordsOrdered;
    }
}

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
                            $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2], 
                            $searchPageLocation = 'search_page.html', 
                            $srcDefaultWidths = [1400, 800, 2000], 
                            $defaultStyleURL = "../styles/reading_page_style.css") {
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
        
        <title><?= $pageRecord['title'] ?> | Breel Comix</title>
        <link rel="icon" href="images/smileicon.ico" type="image/x-icon">

        <link href="../styles/briel_font-faces.css" rel="stylesheet">
        <link href="<?= $pageRecord['stylelocation'] == 'NULL' 
                            ? $defaultStyleURL
                            : $pageRecord['stylelocation'] ?>" 
              rel="stylesheet" 
              id="reading_stylesheet">

        <script type="module" src="js/reading_page_script.js"></script>
    </head>

    <body>

        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">
            <main> 

                <?php require 'comicDisplayElements.php'; ?>

                <nav>
                    <p class="nav-line">
                        <a href="<?= $prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <a href="<?= $nextLink ?>" 
                           class="nav-button next-button">Next</a>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $prevUpd8Link ?>" class="nav-button prev-upd8-button">Skip back</a>
                        <a href="home_page.html" class="nav-button home-button">Home</a>
                        <a href="<?= $nextUpd8Link ?>" class="nav-button next-upd8-button">Skip forth</a>
                    </p>
                    <p class="nav-line">
                        <a href="archive_page.html" class="nav-button archive-button">Archive</a>
                    </p>
                </nav>
                
                <?php require 'comicTagsCWsDescElements.php'; ?>

            </main>

            <footer>
                <?php require 'copyrightElement.php'; ?>
            </footer>

        </div>  
        
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
                              $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2],
                              $pageSectionShrinkFactor = 0.95,
                              $comicTopMargin = 8,
                              $gradient = NULL, 
                              $bgImageURL = NULL, 
                              $stretchBGImg = false) {
    ob_start(); 
?>
html {
    <?= $gradient ? "background: $gradient; height: 100%; background-size: cover;" : ""; ?>
    <?= $bgImageURL ? "background-image: url($bgImageURL);" : "" ?>
    <?= $stretchBGImg ? "background-size: 100% 100%;" : "" ?>
    /* To stretch an image to always fit. 
     Otherwise, the background image repeats. */
    --bg-color: <?= $bgColor ?>;
    --text-color: <?= $textColor ?>;
    --hilite-color: <?= $hiliteColor ?>;
    --visited-color: <?= $visitedColor ?>;
    background-color: var(--bg-color); /* For just a solid color */

    font-family: "Briel Nib Bold", Courier, monospace;
    font-size: x-large;
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
@media screen and (min-width: <?= $windowWidthsOrder[$i] + 1 ?>px) {
    html {
        font-size: xx-large;
    }

    body {
        --comic-page-width: <?= $fileWidthsOrder[$i + 1] ?>px;
    }
}
<?php 
    } 
?>

body, 
footer {
    color: var(--text-color);
    text-align: center;
}

footer {
    font-size: smaller;
    font-family: "Briel Fixed Width", Courier, monospace;
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
    font-weight: inherit;
}

section[id="desc_section"], 
section[id="tag_section"],
section[id="cw_section"] {
    border-left: <?= $sectionBorderWidth = 0.3 ?>em solid var(--text-color);
    border-right: <?= $sectionBorderWidth ?>em solid var(--text-color);
    border-radius: 0.7em;
    margin: <?= $sectionMarginHori = 0.5 //wait, what? ?>em auto;
    padding: 0.3em <?= $sectionHoriPadding = 1 ?>em;
    max-width: calc(var(--shrink-factor) * var(--comic-page-width) - <?= 
        2*$sectionBorderWidth + 2*$sectionMarginHori + $sectionHoriPadding
    ?>em);
}

/* selects paragraph elements directly preceded by header 2 elements */
h2 + p {
    margin-top: 0;
    margin-bottom: 0;
}

/* Courier is more legible than my handmade font, for accessibility */
h2 + p.text-desc {
    font-family: "Courier New", Courier, monospace;
    font-size: smaller;
}

nav {
    font-size: larger;
    /* Make font size of text in the nav pane (previous, next, etc.) 4 
    points bigger than the inherited size */
    /* font-size: calc(1em + 4pt); */
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
    color: inherit;
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
    text-align: center;
    border-radius: 0;
    font-size: larger;
    --nav-btn-brdr-radius: 0.3em;
}

.nav-line a.prev-button, 
.nav-line a.prev-upd8-button, 
.nav-line a.archive-button {
    border-right: 0.3rem solid var(--text-color);
    border-top-right-radius: var(--nav-btn-brdr-radius);
    border-bottom-right-radius: var(--nav-btn-brdr-radius);
}

.nav-line a.next-button, 
.nav-line a.next-upd8-button, 
.nav-line a.archive-button {
    border-left: 0.3rem solid var(--text-color);
    border-top-left-radius: var(--nav-btn-brdr-radius);
    border-bottom-left-radius: var(--nav-btn-brdr-radius);
}

.nav-line a.next-button, 
.nav-line a.prev-button {
    border-width: 0.15rem;
    font-size: larger;
    --prev-next-btn-border-radius: calc(0.5 * var(--nav-btn-brdr-radius));
}

.nav-line a.next-button {
    border-top-left-radius: var(--prev-next-btn-border-radius);
    border-bottom-left-radius: var(--prev-next-btn-border-radius);
}

.nav-line a.prev-button {
    border-top-right-radius: var(--prev-next-btn-border-radius);
    border-bottom-right-radius: var(--prev-next-btn-border-radius);
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