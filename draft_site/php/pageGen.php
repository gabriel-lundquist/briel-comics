<?php
namespace Briel;

require 'brielConstants.php';

const DISPLAYWIDTH1 = 1920;
const DISPLAYWIDTH2 = 3000;

const RESULTSPERPAGE = 17; //whimsy

const SEARCHPAGEINDEXKEY = 'p';

class resultInfo {
    public $resultRecord; 
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

    public function __construct($resultRecord, 
                                $prevLink, 
                                $prevUpd8Link, 
                                $fileRecords, 
                                $tags = [], 
                                $contWarns = [], 
                                $nextLink = null, 
                                $nextUpd8Link = null,
                                $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2],  
                                $srcDefaultWidths = [1400, 800, 2000]) {
    
        $this->resultRecord = $resultRecord;
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
                                                           $resultRecord['postdate']);
    }
}

class searchResultInfo {
    public $searchRecord; 
    public $tokenExecList;
    public $bareTokens;
    public $prefixTokens;
    public $date;
    private $matchtextTokens = null;
    private $matchDescTokens = null;
    public $pageTags;
    private $matchTags = null;
    private $nonMatchTags = null;
    public $pageContWarns;
    private $matchContWarns = null;
    private $nonMatchContWarns = null;
    public $thumbnailRecord;
    public $isExact;
    private $descMatches = null;

    public function __construct($searchRecord, 
                                $tokenExecList, 
                                $bareTokens, 
                                $prefixTokens, 
                                $pageTags, 
                                $pageContWarns, 
                                $thumbnailRecord, 
                                $isExact) {
        $this->searchRecord = $searchRecord;
        $this->tokenExecList = $tokenExecList;
        $this->bareTokens = $bareTokens;
        $this->prefixTokens = $prefixTokens;
        $this->pageTags = $pageTags;
        $this->pageContWarns = $pageContWarns;
        $this->thumbnailRecord = $thumbnailRecord;
        $this->isExact = $isExact;
        
        $this->date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', 
                                                            $searchRecord['postdate']);
        // $this->getMatchTags();
        // $this->getNonMatchTags();
        // $this->getMatchCWs();
        // $this->getNonMatchCWs();
        // $this->getDescMatches();
    }

    public function getMatchTags() {
        if ($this->matchTags === null) {
            $this->matchTags = [];
            foreach (array_filter($this->searchRecord, 
                                    fn($k) => str_starts_with($k, 'tag'), 
                                    ARRAY_FILTER_USE_KEY)
                    as $key => $match) {
                if ($match != 0) $this->matchTags[] = substr($key, 3);
            }
        }

        return $this->matchTags;
    }

    public function getNonMatchTags() {
        if ($this->nonMatchTags === null) {
            // Not sure why VS Code is marking this as unreachable...
            $this->nonMatchTags = array_diff($this->pageTags, $this->getMatchTags());
        }

        return $this->nonMatchTags;
    }

    public function getMatchCWs() {
        if ($this->matchContWarns === null) {
            $this->matchContWarns = [];
            foreach (array_filter($this->searchRecord, 
                                    fn($k) => str_starts_with($k, 'cw'), 
                                    ARRAY_FILTER_USE_KEY)
                    as $key => $match) {
                if ($match) $this->matchContWarns[] = substr($key, 2);
            }
        }

        return $this->matchContWarns;
    }

    public function getNonMatchCWs() {
        if ($this->nonMatchContWarns === null) {
            // Not sure why VS Code is marking this as unreachable...
            $this->nonMatchContWarns = array_diff(  $this->pageContWarns, 
                                                    $this->getMatchCWs()    );
        }

        return $this->nonMatchContWarns;
    }

    public function isTitleMatched() {
        return $this->searchRecord['titlematch'];
    }

    public function getDescMatches() {
        if ($this->descMatches === null) {
            // Not sure why VS Code is marking this as unreachable...
            $this->descMatches = array_map(  
                    fn($str) => strtolower(substr($str, \strlen('desc'))), 
                    array_filter(   $this->searchRecord, 
                                    fn($key) => str_starts_with($key, 'desc'), 
                                    ARRAY_FILTER_USE_KEY    )
            );
        }
        
        return $this->descMatches;
    }

    // private function getMatchesInText($recordColumnKey, $prefixKey) {
    //     $matchTokens = [];
    //     $textToken = strtok($this->searchRecord[$recordColumnKey], WHITESPACES);
    //     $searchTokens = array_map(  fn($s) => strtolower($s), 
    //                                 [   ...$this->bareTokens, 
    //                                     ...$this->prefixTokens[$prefixKey]  ]
    //                                 );
    //     for (   $textToken = strtok($this->searchRecord[$recordColumnKey], 
    //                                 WHITESPACES);
    //             $textToken !== false; 
    //             $textToken = strtok(WHITESPACES)   ) {
    //         $isMatch = $this->isExact ? \in_array(strtolower($textToken), $searchTokens) 
    //                                     : array_any($searchTokens, 
    //                                                 fn($tok, $key) => 
    //                                                     str_contains(strtolower($textToken), 
    //                                                                  $tok)
    //                                                     // $key does nothing
    //                                                 ); 
    //         $this->matchTokens[] = ['token' => $textToken, 
    //                                 'match' => $isMatch];
    //     }

    //     return $matchTokens;
    // }

}

class updateInfo {
    public $updateRecord;
    public $date;
    public $tags;
    public $contWarns;
    public $resultRecordsOrdered;
    public $thumbnailRecordsOrdered;

    public function __construct($updateRecord,
                                $resultRecordsOrdered,
                                $thumbnailRecordsOrdered,
                                $tags = [],
                                $contWarns = []) {
        $this->$updateRecord = $updateRecord;
        $this->$date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', 
                                                            $updateRecord['postdate']);
        $this->$tags = $tags;
        $this->contWarns = $contWarns;
        $this->$resultRecordsOrdered = $resultRecordsOrdered;
        $this->$thumbnailRecordsOrdered = $thumbnailRecordsOrdered;
    }
}

function classIs($node, $className) {
    return str_contains($node->className, $className);
}

function emphasizeIf($test, $str) {
    return $test ? "<em>$str</em>" : $str;
}

function formatGETParameters($params) {
    return '?' . \implode(  '&', 
                            array_map(  fn($val, $key) => "$key=$val", 
                                        $params, 
                                        array_keys($params) )
                            );
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

function generateComicpageDOMHTML($pageRecord, 
                                  $prevLink, 
                                  $nextLink, 
                                  $prevUpd8Link, 
                                  $nextUpd8Link,
                                  $widthFileLocations, 
                                      // values: locations, keys: widths
                                  $alttext, 
                                  $tags, // array of strings
                                  $searchpageLocation, 
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
    
    // Set srcset on the comic display
    $comicpage = $doc->getElementById("single_page");
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
    $comicpage->setAttribute("srcset", $srcsetStr);

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
    $comicpage->setAttribute("src", $srcStr);

    // Set the alt text
    $comicpage->setAttribute("alt", 
                             $alttext 
                                . " Described under the heading Text Description.");

    // Set the tag links
    $tagPara = $doc->getElementById("tags-para");
    foreach ($tags as $tag) {
        $tagEl = $doc->createElement("a");
        $tagEl->textContent = $tag;
        $tagEl->setAttribute("href", $searchpageLocation . "?tag=" . $tag);
        $tagPara->appendChild($tagEl);
    }

    // Set the description
    $doc->getElementById("desc-para")->textContent = $pageRecord["imagedesc"];
    
    return $doc->saveHtmlFile($pageRecord['location']);
}

/**
 * Summary of Briel\generateComicpage
 * @param mixed $pageRecord
 * @param mixed $prevLink
 * @param mixed $nextLink
 * @param mixed $prevUpd8Link
 * @param mixed $nextUpd8Link
 * @param mixed $fileRecords
 * @param mixed $tags
 * @param mixed $contWarns
 * @param mixed $windowWidthsOrder
 * @param mixed $searchpageLocation
 * @param mixed $srcDefaultWidths
 * @param mixed $defaultStyleURL
 * @return bool|int
 */
function generateComicpage( $pageRecord, 
                            $prevLink, 
                            $nextLink, 
                            $prevUpd8Link, 
                            $nextUpd8Link,
                            $fileRecords, 
                            $tags, 
                            $contWarns, 
                            $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2], 
                            $searchpageLocation = './' . SEARCHFILENAME, 
                            $srcDefaultWidths = [1400, 800, 2000], 
                            $defaultStyleURL = "../styles/reading_page_style.css"   ) {
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
        <link rel="icon" href="images/<?= SITEICONNAME ?>" type="image/x-icon">

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
                <?php 
    generateComicDisplayElements(   $fileRecords, 
                                    $prevLink, 
                                    $nextLink, 
                                    $srcDefaultWidths, 
                                    $windowWidthsOrder  ); 
                ?>
                <nav>
                    <p class="nav-line">
                        <a href="<?= $prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <a href="<?= $nextLink ?>" 
                           class="nav-button next-button">Next</a>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <a href="home_page.html" 
                           class="nav-button home-button">Home</a>
                        <a href="<?= $nextUpd8Link ?>" 
                           class="nav-button next-upd8-button">Skip forth</a>
                    </p>
                    <p class="nav-line">
                        <a href="archive_page.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>
                <?php 
    generateReadingAccessoryElements(   $pageRecord, 
                                        $tags, 
                                        $contWarns, 
                                        $searchpageLocation ); 
                ?>
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
function generateReadingStyle(  $bgColor, 
                                $textColor, 
                                $hiliteColor, 
                                $visitedColor,
                                $comicDefaultWidth = 800,
                                $fileWidthsOrder = [800, 1400, 2000], 
                                $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2],
                                $pageSectionShrinkFactor = 0.95,
                                $comicTopMargin = 8,
                                $gradient = NULL, 
                                $bgImageURL = NULL, 
                                $stretchBGImg = false   ) {
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
    return ob_get_flush();
}

function generateComicDisplayElements($fileRecords, 
                                        $prevLink, 
                                        $nextLink,
                                        $srcDefaultWidths = [1400, 800, 2000],
                                        $windowWidthsOrder = [DISPLAYWIDTH1, 
                                                              DISPLAYWIDTH2]) {
    $filesWidthOrder = array_combine(array_column($fileRecords, 'width'), 
                                 $fileRecords);
    ksort($filesWidthOrder);

    $srcWidth = null;
    foreach ($srcDefaultWidths as $width) {
        if (\array_key_exists($width, $filesWidthOrder)) {
            $srcWidth = $width;
            break;
        }
    }
    if ($srcWidth === null) {
        $srcWidth = \array_key_first($filesWidthOrder);
    }

    ob_start(); ?>
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
    id="single_page"
><?php 
    return ob_get_flush();
}

function generateReadingAccessoryElements($pageRecord, 
                                            $tags, 
                                            $contWarns, 
                                            $searchpageLocation = './' . SEARCHFILENAME) {
    ob_start();
?>
<section id="tag_section">
    <h2>Tags</h2>
    <p id="tags-para">
    <?php
    foreach ($tags as $tag) {
        echo "<a href=$searchpageLocation?tag=$tag>$tag</a>\n";
    }
    ?>
    </p>
</section>

<section id="cw_section">
    <h2>Content Warnings</h2>
    <p id="cws-para">
    <?php
    foreach ($contWarns as $cw) {
        echo "<a href=$searchpageLocation?cw=$cw>$cw</a>\n";
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
<?php 
    return ob_get_flush();
}

function generateHomepage(  $blogText, 
                            $blogDateElement, 
                            $pageRecord,        
                            $fileRecords, 
                            $prevLink, 
                            $prevUpd8Link, 
                            $tags, 
                            $contWarns,
                            $searchpageLocation = './' . SEARCHFILENAME, 
                            $srcDefaultWidths = [1400, 800, 2000],
                            $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2], 
                            $styleLocation = '/styles/reading_page_style.css',
                            $nextLink = null, 
                            $nextUpd8Link = null    ) {
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
        <meta name="description" content="A home page for a comics website.">
        
        <title>Breel Comix</title>
        <link href="./images/<?= SITEICONNAME ?>" rel="icon" type="image/x-icon">

        <link href="./styles/<?= FONTFACESCSSNAME ?>" rel="stylesheet">
        <link href="<?= $styleLocation ?>" rel="stylesheet" id="home_stylesheet"> 

        <script type="module" src="./js/reading_page_script.js"></script>
    </head>

    <body>
        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">         
            <header>
                <?php
    $bannerFileStem = '';
    $bannerAlt = 'Blank banner';
    switch(rand(0,1)) {
        case 1: 
            $bannerFileStem = '2026-04-21BrielComicsBanner';
            $bannerAlt = 'Briel Comics';
            break;
        default: 
            $bannerFileStem = '2026-04-20BreelComixBanner';
            $bannerAlt = 'Breel Comics';
    }   
                ?>
                <img
                    srcset="./images/<?= $bannerFileStem ?>_800w.png 800w, 
                            ./images/<?= $bannerFileStem ?>_1400w.png 1400w"
                    src="./images/<?= $bannerFileStem ?>_800w.png"
                    sizes="(max-width: 800px) 100vw, 
                           (max-width: 1920) 800px, 
                           1400px"
                    alt="<?= $bannerAlt ?>";
                >
            </header>

            <main> 
                <?php 
    generateComicDisplayElements($fileRecords, 
                                    $prevLink, 
                                    $nextLink, 
                                    $srcDefaultWidths, 
                                    $windowWidthsOrder); 
                ?>
                <nav>
                    <p class="nav-line">
                        <a href="<?= $prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <?php
    if ($nextLink) {
                        ?>
                        <a href="<?= $nextLink ?>" 
                           class="nav-button next-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <?php
    if ($nextUpd8Link) {
                        ?>
                        <a href="<?= $nextUpd8Link ?>" 
                           class="nav-button next-upd8-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="archive_page.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>

                <section id="blog_section">
                    <h2>Web log</h2>
                    <br>
                    <?= $blogText ?>
                    <br>
                    <?= $blogDateElement ?>
                    <p><a href="./weblog_archive.html">Web log archive</a></p>
                </section>
                <?php 
    generateReadingAccessoryElements($pageRecord, 
                                        $tags, 
                                        $contWarns, 
                                        $searchpageLocation); 
                ?>
            </main>

            <footer>
                <?php
    require 'copyrightElement.php';
                ?>
            </footer>

        </div>  
        
        <?php 
    if ($nextLink) { 
        ?>
        <a href="<?= $nextLink ?>" class="nav-button next-button" title="Next"></a>
        <?php
    }
        ?>
    </body>
</html>

<?php
    return ob_get_flush();
}

function formatSearchNavLink($navIndex) {
    return  '<a href="' 
            // if `$_GET(SEARCHPAGEINDEXKEY)` exists, this will change its value to `$navIndex`
            . formatGETParameters([...$_GET, SEARCHPAGEINDEXKEY => $navIndex]) 
            . '">' 
            . $navIndex 
            . '</a> ... ';
}

// TODO: will need to rip out where I use `$_GET` to access the current page number.
function generateSearchNav($resultPageCount, $currentIdx) {
    if ($resultPageCount > 1) { //only even have nav bar if we have more than one page
        ob_start();
?>
<nav>
    <h3>Archive navigation</h3>
    <?php
        // $currentIdx = \array_key_exists(SEARCHPAGEINDEXKEY, $_GET) 
        //                         ? $_GET(SEARCHPAGEINDEXKEY) 
        //                         : 0;

        if ($currentIdx > 2) {
            echo    '<a href="' 
                    // if `$_GET(SEARCHPAGEINDEXKEY)` exists, this new array will have a 
                    // 0 there instead
                    . formatGETParameters([...$_GET, SEARCHPAGEINDEXKEY => 0]) 
                    . '">Latest</a> ... ';
        }

        if ($currentIdx > 5) {
            echo formatSearchNavLink($currentIdx - 5) . ' ... ';
        }

        foreach ([-2, -1] as $offset) {
            if ($currentIdx > -$offset) {
                echo formatSearchNavLink($currentIdx + $offset) . ' ';
            }
        }

        echo $currentIdx; // no link, since we're already here
        
        foreach ([1, 2] as $offset) {
            if ($resultPageCount - $currentIdx > $offset) {
                echo ' ' . formatSearchNavLink($currentIdx + $offset);
            }
        }

        if ($resultPageCount - $currentIdx > 5) {
            echo ' ... ' . formatSearchNavLink($currentIdx + 5);
        }

        if ($resultPageCount - $currentIdx > 2) {
            echo    ' ... <a href="' 
                    . formatGETParameters([...$_GET, SEARCHPAGEINDEXKEY => $resultPageCount]) 
                    . '">Earliest</a>';
        }
    ?>
</nav>
<?php
        return ob_get_flush();
    }

    return '';
}

function generateSearchEntry($result) {
    ob_start();
?>
<article class="search-entry">
    <div class="thumbnails-div">
        <a href="<?= $result->searchRecord['location'] ?>" 
           class="page-link">
            <img
                class="thumbnail"
                attr-src="<?= $result->thumbnailRecord['location'] ?>"
                src=""
                alt="<?= $result->thumbnailRecord['alttext'] ?>"
                width="<?= $result->thumbnailRecord['width'] ?>px"
                height="<?= $result->thumbnailRecord['height'] ?>px"
                loading="lazy"
            >
        </a> 
    </div>
    <div class="detail-div">
        <h3><a href="<?= $result->pageRecord['location'] ?>" class="page-link"><?= 
        emphasizeIf($result->searchRecord['titlematch'], 
                    $result->searchRecord['title']  ) 
        ?></a></h3>
        <p> <h4>Date:</h4>
            <time date="<?= $result->date->format('Y-m-d') ?>"><?=
            implode(' ', 
                    [   emphasizeIf($result->searchRecord['yearmatch'], 
                                    $result->date->format('Y,')), 

                        emphasizeIf($result->searchRecord['monthmatch'], 
                                    $result->date->format('M.')), 

                        emphasizeIf($result->searchRecord['dayofmonthmatch'],
                                    $result->date->format('js,')), 

                        emphasizeIf($result->searchRecord['daynamematch'],
                                    $result->date->format('l'))
                    ]); 
            ?></time>
        </p>
<?php
    if ($result->pageTags) {
?>
        <p><h4>Tags:</h4><?= 
        implode(', ', 
                array_map(  fn($tag, $tagText) => 
                                "<a href=\"" . SEARCHFILENAME . "?tag=$tag\">$tagText</a>", 

                            [   ...$result->getMatchTags(), 
                                ...$result->getNonMatchTags()], 

                            [   ...array_map(   fn($t) => "<em>$t</em>", 
                                                $result->getMatchTags() ),
                                ...$result->getNonMatchTags()   ]
                )
        );
        ?></p>
<?php
    }

    if ($result->pageContWarns) {
?>
        <p><h4>Content warnings:</h4><?= 
        implode(', ', 
                array_map(  fn($cw, $cwText) => 
                                "<a href=\"" . SEARCHFILENAME . "?cw=$cw\">$cwText</a>", 

                            [   ...$result->getMatchCWs(), 
                                ...$result->getNonMatchCWs()    ], 

                            [   ...array_map(   fn($t) => "<em>$t</em>", 
                                                $result->getMatchCWs()  ),
                                ...$result->getNonMatchCWs()    ]
                )
        );
        ?></p>
<?php
    }
?>
        <p><h4>Description:</h4><?php  
    if ($result->isExact) {
        $hilitedDesc = '';
        for (   $token = strtok($result->searchRecord['imagedesc'], WHITESPACES); 
                $token !== false; 
                $token = strtok(WHITESPACES)    ) {
            $hilitedDesc .= ' ' . emphasizeIf(  \in_array(  strtolower($token), 
                                                            $result->getDescMatches() ), 
                                                $token  
                                    );
        }
        echo $hilitedDesc;
    } else {
        echo $result->searchRecord['imagedesc'];
    }
        ?></p>
    </div> <!-- class detail-div -->
</article>
<?php
    return ob_get_flush();
}

function generateSearchForm($searchStr, 
                            $idSuffix, 
                            $includeOptionsLink = false) {
    ob_start(); ?>
<form role="search" action="<?= SEARCHFILENAME ?>" method="get">
    <p>
        <input 
            type="search" 
            name="search<?= $idSuffix ?>" 
            id="search<?= $idSuffix ?>"
            placeholder="Search comic pages..."
            minlength="2"
            maxlength="256"
            size="34"
            pattern="[\w<?= htmlentities(` ,.?!();:'"-`) ?>]{2,256}"
            aria-label="Search comic pages"
            value="<?=  $searchStr ?>"/> 
        <button type="submit">Search</button>
    </p>
    <label for="search<?= $idSuffix ?>">
        Searches tags, dates, titles, and page numbers by default. 
    <?php if ($includeOptionsLink) { ?>
        See <a href="#search_options">search options</a>.
    <?php } ?>
    </label>
    <p>
        <div class="search-check">
            <input type="checkbox" name="desc<?= $idSuffix ?>" id="desc<?= $idSuffix ?>">
            <label for="desc<?= $idSuffix ?>">Search image descriptions too</label>
        </div>
        <div class="search-check">
            <input type="checkbox" name="exact<?= $idSuffix ?>" id="exact<?= $idSuffix ?>">
            <label for="exact<?= $idSuffix ?>">Match terms exactly</label>
        </div>
    </p>
</form>
<?php
    return ob_get_flush();
}

function generateSearchPage($searchStr, 
                            $searchResultInfos, 
                            $pageIndex = 0,
                            $numPages = 1) {
    ob_start();
?>
<!doctype html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta name="viewport" content="width=device-width"/>
        <meta name="author" content="Breel">
        <meta name="description" content="Breel comics search page.">
        
        <title>Search | Breel Comix</title>
        <link rel="icon" href="images/<?= SITEICONNAME ?>" type="image/x-icon" />

        <link href="styles/defaults.css" rel="stylesheet" />
        <link href="styles/<?= FONTFACESCSSNAME ?>" rel="stylesheet" />
        <link href="styles/update_list_style.css" rel="stylesheet" />
        <link href="styles/search_page_style.css" rel="stylesheet" />
        
        <script type="module" src="js/search_page_script.js"></script>
    </head>

    <body>
        <header>
            <h1>Search</h1>
            <?php generateSearchForm($searchStr, '-header', true); ?>
        </header>

        <main>
            <h2>Results</h2>
            <button class="toggle-nails">Hide thumbnails</button>
            <?php
    foreach ($searchResultInfos as $result) {
        generateSearchEntry($result);
    }
            ?>
        </main>

        <footer>
            <aside>
                <h3 id="search_options">Search options</h3>
                <ul>
                    <li>Enter <kbd>-blegh</kbd> to exclude any page that contains 
                        "blegh"</li>
                    <li>Enter <kbd>title:tuesday</kbd> to get pages with "tuesday" in their titles, 
                        as opposed to just searching <kbd>tuesday</kbd> which gets you any page 
                        with a "tuesday" tag or posted on a tuesday or whatever. Valid prefixes are:
                        <ul>
                            <?php 
    foreach (SEARCHFIELDSPECS as $field) {
                            ?> 
                            <li><kbd><?= $field ?>:</kbd><?php
        switch ($field) {
            case 'cw': echo ' (as in Content Warning)'; break;
            case 'day': echo ' (searches both day of the month and day of the week)'; break;
        }
                            ?></li>
                            <?php   
    }   ?>              </ul>
                    </li>
                    <li>You can use both at once, like <kbd>-cw:gore</kbd>
                        if you're okay with the word "gore" but you don't want to see 
                        any comics with guts.</li>
                    <li>Check "Search image descriptions too" and <strong>uncheck</strong>
                        "Match terms exactly" if you're looking for a specific comic but 
                        you can't remember exact dialogue. The machine will attempt to 
                        find image descriptions that have similar words (no AI, just 
                        MySQL's fulltext search algorithm).</li>
                </ul>
            </aside>
            <?php 
    generateSearchForm($searchStr, '-footer');

    generateSearchNav($numPages, $pageIndex);

    require 'copyrightElement.php'; 
            ?>
        </footer>
    </body>

</html>
<?php
    return ob_get_flush();
}

function generateAllSearchPages($searchStr, $searchResultInfos) {
    $infosPerPage = array_chunk($searchResultInfos, RESULTSPERPAGE);
    $pageStrs = [];
    for ($pageIdx = 0; $pageIdx < \count($infosPerPage); $pageIdx++) {
        ob_start();
        $pageStrs[] = generateSearchPage(   $searchStr, 
                                            $infosPerPage[$pageIdx], 
                                            $pageIdx, 
                                            \count($infosPerPage)   );
        ob_clean(); // Don't need to output the result in any way
    }
    return $pageStrs; // TODO: add file creation
}

?>