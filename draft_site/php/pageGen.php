<?php
namespace Briel;

const DISPLAYWIDTH1 = 1920;
const DISPLAYWIDTH2 = 3000;

enum ResultType: string {
    case Update = 'comicupdate';
    case result = 'result';
    // case File = 'file';
}

enum MatchedField {
    case Tag;
    case Date;
    case Title;
    case resultNum;
    case ImgDesc;
}

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
    public $pageRecord;
    public $searchRecord; 
    public $bareTokens;
    public $prefixTokens;
    public $date;
    public $pageTags;
    public $matchTags;
    public $pageContWarns;
    public $matchContWarns;
    public $thumbnailRecord;
    public $isExact;

    public function __construct($pageRecord, 
                                $searchRecord, 
                                $tokenExecList, 
                                $bareTokens, 
                                $prefixTokens, 
                                $date, 
                                $pageTags, 
                                // $matchTags, 
                                $pageContWarns, 
                                // $matchContWarns, 
                                $thumbnailRecord, 
                                $isExact) {
    $this->pageRecord = $pageRecord;
    $this->searchRecord = $searchRecord;
    $this->bareTokens = $bareTokens;
    $this->prefixTokens = $prefixTokens;
    $this->date = $date;
    $this->pageTags = $pageTags;
    $this->matchTags = [];
    foreach (array_keys($searchRecord) as $searchKey) {
        if (preg_match('/^tagt\d+/', $searchKey)) {
            $this->matchTags[] = ;    //... I think actually I'm just going to create some temporary tables.
        }
    }
    $this->pageContWarns = $pageContWarns;
    $this->matchContWarns = ;
    $this->thumbnailRecord = $thumbnailRecord;
    $this->isExact = $isExact;
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

function generateComicresultDOMHTML($resultRecord, 
                                  $prevLink, 
                                  $nextLink, 
                                  $prevUpd8Link, 
                                  $nextUpd8Link,
                                  $widthFileLocations, 
                                      // values: locations, keys: widths
                                  $alttext, 
                                  $tags, // array of strings
                                  $searchresultLocation, 
                                  $templateFilepath = '../reading_result_template_draft.html'
                                  ) { 
    // Assumes result record has already been created
    $doc = \DOM\HTMLDocument::createFromFile($templateFilepath);

    // Set result title
    $doc->getElementsByTagName("title")->item(0) //should only be one title
        ->insertAdjacentText(\DOM\AdjacentPosition::AfterBegin, $resultRecord["title"]);

    // Set stylesheet, if not the default
    if ($resultRecord["stylelocation"] != "NULL") {
        $doc->getElementById("reading_stylesheet")
            ->setAttribute("href", $resultRecord["stylelocation"]);
    }

    // Set previous and next result links
    foreach ($doc->getElementsByTagName("a") as $a) {
        assignNavLink($a, $prevLink, $nextLink, $prevUpd8Link, $nextUpd8Link);
    }

    // Set previous and next result links in imagemap
    foreach ($doc->getElementsByTagName("area") as $area) {
        assignNavLink($area, $prevLink, $nextLink, $prevUpd8Link, $nextUpd8Link);
    }
    
    // Set srcset on the comic display
    $comicresult = $doc->getElementById("single_result");
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
    $comicresult->setAttribute("srcset", $srcsetStr);

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
    $comicresult->setAttribute("src", $srcStr);

    // Set the alt text
    $comicresult->setAttribute("alt", 
                             $alttext 
                                . " Described under the heading Text Description.");

    // Set the tag links
    $tagPara = $doc->getElementById("tags-para");
    foreach ($tags as $tag) {
        $tagEl = $doc->createElement("a");
        $tagEl->textContent = $tag;
        $tagEl->setAttribute("href", $searchresultLocation . "?tag=" . $tag);
        $tagPara->appendChild($tagEl);
    }

    // Set the description
    $doc->getElementById("desc-para")->textContent = $resultRecord["imagedesc"];
    
    return $doc->saveHtmlFile($resultRecord['location']);
}

/**
 * Summary of Briel\generateComicresult
 * @param mixed $resultRecord
 * @param mixed $prevLink
 * @param mixed $nextLink
 * @param mixed $prevUpd8Link
 * @param mixed $nextUpd8Link
 * @param mixed $fileRecords
 * @param mixed $tags
 * @param mixed $contWarns
 * @param mixed $windowWidthsOrder
 * @param mixed $searchresultLocation
 * @param mixed $srcDefaultWidths
 * @param mixed $defaultStyleURL
 * @return bool|int
 */
function generateComicresult($resultRecord, 
                            $prevLink, 
                            $nextLink, 
                            $prevUpd8Link, 
                            $nextUpd8Link,
                            $fileRecords, 
                            $tags, 
                            $contWarns, 
                            $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2], 
                            $searchresultLocation = '/search.php', 
                            $srcDefaultWidths = [1400, 800, 2000], 
                            $defaultStyleURL = "../styles/reading_result_style.css") {
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
        <meta name="description" content="A result displaying a comic.">
        
        <title><?= $resultRecord['title'] ?> | Breel Comix</title>
        <link rel="icon" href="images/smileicon.ico" type="image/x-icon">

        <link href="../styles/briel_font-faces.css" rel="stylesheet">
        <link href="<?= $resultRecord['stylelocation'] == 'NULL' 
                            ? $defaultStyleURL
                            : $resultRecord['stylelocation'] ?>" 
              rel="stylesheet" 
              id="reading_stylesheet">

        <script type="module" src="js/reading_result_script.js"></script>
    </head>

    <body>

        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="result-display">
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
                        <a href="<?= $nextLink ?>" 
                           class="nav-button next-button">Next</a>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <a href="home_result.html" 
                           class="nav-button home-button">Home</a>
                        <a href="<?= $nextUpd8Link ?>" 
                           class="nav-button next-upd8-button">Skip forth</a>
                    </p>
                    <p class="nav-line">
                        <a href="archive_result.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>
                <?php 
    generateReadingAccessoryElements($resultRecord, 
                                        $tags, 
                                        $contWarns, 
                                        $searchresultLocation); 
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
    return file_put_contents($resultRecord['location'], ob_get_flush());
}

/**
 * Summary of Briel\generateReadingStyle
 * @param mixed $filePath
 * @param mixed $bgColor
 * @param mixed $textColor
 * @param mixed $hiliteColor
 * @param mixed $visitedColor
 * @param mixed $comicDefaultWidth
 * @param mixed $resultSectionShrinkFactor
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
                              $comicDefaultWidth = 800,
                              $fileWidthsOrder = [800, 1400, 2000], 
                              $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2],
                              $resultSectionShrinkFactor = 0.95,
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
    --comic-result-width: <?= $comicDefaultWidth ?>px;
    --shrink-factor: <?= $resultSectionShrinkFactor ?>;
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
        --comic-result-width: <?= $fileWidthsOrder[$i + 1] ?>px;
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

/* Selects the big navigation buttons to the side of the result */
body > a.nav-button {
    width: max(0px, 0.25 * (100vw - var(--comic-result-width)));
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
    max-width: calc(var(--shrink-factor) * var(--comic-result-width) - <?= 
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
    max-width: calc(var(--shrink-factor) * var(--comic-result-width));
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

.result-display img.comic-result {
    max-width: 100%;
    margin-top: clamp(0px, 0.5*(100vw - var(--comic-result-width)), <?= 
        $comicTopMargin ?>vh);
}

<?php
    return file_put_contents($filePath, ob_get_flush());
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
    class="comic-result"
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
    id="single_result"
>
<?php 
    return;
}

function generateReadingAccessoryElements($resultRecord, 
                                            $tags, 
                                            $contWarns, 
                                            $searchresultLocation = '/search.php') {
?>
<section id="tag_section">
    <h2>Tags</h2>
    <p id="tags-para">
    <?php
    foreach ($tags as $tag) {
        echo "<a href=$searchresultLocation?tag=$tag>$tag</a>\n";
    }
    ?>
    </p>
</section>

<section id="cw_section">
    <h2>Content Warnings</h2>
    <p id="cws-para">
    <?php
    foreach ($contWarns as $cw) {
        echo "<a href=$searchresultLocation?cw=$cw>$cw</a>\n";
    }
    ?>
    </p>
</section>

<section id="desc_section">
    <h2><a href="#text_description" 
            class="text_desc_heading">Text Description</a></h2>
    <p class="text-desc">
        <?= $resultRecord["imagedesc"] ?>
    </p>
</section>
<?php 
    return;
}

function generateHomepage($filePath, 
                            $blogText, 
                            $blogDateElement, 
                            $resultRecord,        
                            $fileRecords, 
                            $prevLink, 
                            $prevUpd8Link, 
                            $tags, 
                            $contWarns,
                            $searchresultLocation = '/search.php', 
                            $srcDefaultWidths = [1400, 800, 2000],
                            $windowWidthsOrder = [DISPLAYWIDTH1, DISPLAYWIDTH2], 
                            $styleLocation = '/styles/reading_result_style.css',
                            $nextLink = null, 
                            $nextUpd8Link = null) {
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
        <meta name="description" content="A home result for a comics website.">
        
        <title>Breel Comix</title>
        <link rel="icon" href="./images/smileicon.ico" type="image/x-icon">

        <link href="./styles/briel_font-faces.css" rel="stylesheet">
        <link href="<?= $styleLocation ?>" 
              rel="stylesheet" 
              id="home_stylesheet"> 

        <script type="module" src="./js/reading_result_script.js"></script>
    </head>

    <body>
        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="result-display">         
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
                        <a href="archive_result.html" 
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
    generateReadingAccessoryElements($resultRecord, 
                                        $tags, 
                                        $contWarns, 
                                        $searchresultLocation); 
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
    return file_put_contents($filePath, ob_get_flush());
}

function generateSearchpage($searchStr, 
                            $resultInfos) {
?>
<!doctype html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta name="viewport" content="width=device-width"/>
        <meta name="author" content="Breel">
        <meta name="description" content="Breel comics search result.">
        
        <title>Search | Breel Comix</title>
        <link rel="icon" href="images/smileicon.ico" type="image/x-icon" />

        <link href="styles/defaults.css" rel="stylesheet" />
        <link href="styles/briel_font-faces.css" rel="stylesheet" />
        <link href="styles/update_list_style.css" rel="stylesheet" />
        <link href="styles/search_result_style.css" rel="stylesheet" />
        
        <script type="module" src="js/search_result_script.js"></script>
    </head>

    <body>
        <header>
            <h1>Search</h1>

            <form role="search" action="search.php" method="get">
                <p>
                    <input 
                        type="search" 
                        name="search" 
                        id="search"
                        placeholder="Search comic results..."
                        minlength="2"
                        maxlength="256"
                        size="34"
                        pattern="[\w<?= htmlentities(` ,.?!();:'"`) ?>]{2,256}"
                        aria-label="Search comic results"/> 
                    <button type="submit">Search</button>
                </p>
                <label for="search">
                    Searches tags, dates, titles, and result numbers by default
                </label>
                <!-- <input type="checkbox" name="title" id="title">
                <label for="title">Search result titles too</label>
                <br /> -->
                <p>
                    <div class="search-check">
                        <input type="checkbox" name="desc" id="desc">
                        <label for="desc">Search image descriptions too</label>
                    </div>
                    <div class="search-check">
                        <input type="checkbox" name="exact" id="exact">
                        <label for="exact">Match terms exactly</label>
                    </div>
                </p>
            </form>
            
        </header>

        <main>
            <h2>Results</h2>
            <button class="toggle-nails">Hide thumbnails</button>
            <?php
    $emphasize = fn($test, $str) => ($test ? '<em>' : '') 
                                    . $str
                                    . ($test ? '</em>' : '');
    foreach ($resultInfos as $result) {
            ?>
            <article class="search-entry">
                <div class="thumbnails-div">
                    <a href="<?= $result->pageRecord['location'] ?>" 
                       class="result-link">
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
                    <!-- The update result should just lead to the first result? -->
                    <h3><a href="<?= $result->pageRecord['location'] ?>" 
                           class="result-link"><?php
        if ($result->searchRecord['titlematch']) {
            $titleText = [];
            $whitespaces = " \n\t\r";
            $titleToken = strtok($result->pageRecord['title'], $whitespaces);
            $hilite = false;
            $searchTokens = $result->bareTokens + $result->prefixTokens['title'];
            while ($titleToken !== false) {
                foreach ($searchTokens as $searchToken) {
                    if ($result->isExact ? $titleToken == $searchToken
                                        : str_contains(strtolower($titleToken), 
                                                        strtolower($searchToken))) {
                        $titleText[] = "<em>$titleToken</em>";
                        $hilite = true;
                        break;
                    }
                }
                if (!$hilite) $titleText[] = $titleToken;
                $titleToken = strtok($whitespaces);
            }
            echo implode(' ', $titleText);
        } else echo $result->pageRecord['title'];

                    ?></a></h3>
                    <p><h4>Date:</h4>
                        <time date="<?= $result->date->format('Y-m-d') ?>"><?php
        $dateText = implode(' ', 
                            [$emphasize($result->searchRecord['yearmatch'], 
                                        $result->date->format('Y,')), 
                                $emphasize($result->searchRecord['monthmatch'], 
                                            $result->date->format('M.')), 
                                $emphasize($result->searchRecord['dayofmonthmatch'],
                                            $result->date->format('js,')), 
                                $emphasize($result->searchRecord['daynamematch'],
                                            $result->date->format('l'))
                            ]); 
                        ?></time>
                    </p>
<?php
    if (\count($result->tags) != 0) {
?>
                    <p><h4>Tags:</h4>
<?php
        foreach ($update->tags as $tag) {
?>
                        <a href="search_result.php?tag=<?= $tag ?>"><?= $tag ?></a>
<?php
        }
?>                  </p>
<?php
    }
?>
                    <p><h4>Description:</h4> <?= $update->updateRecord['desc'] ?></p>

<?php
    if (\count($update->contWarns) != 0) {
?>
                    <p><h4>Content Warnings:</h4>
<?php
        foreach ($update->contWarns as $contWarn) {
?>
                        <a href="search_result.php?tag=<?= $contWarn ?>"><?= $contWarn ?></a>
<?php
        }
?>                  </p>
<?php
    }
?>
                </div>
                <div class="thumbnails-div">
<?php 
    foreach (array_map(null, $update->resultRecordsOrdered, $update->thumbnailRecordsOrdered) as [$result, $nail]) {
?>
                    <a href="<?= $result['location'] ?>" class="result-link">
                        <img
                            class="thumbnail"
                            attr-src="<?= $nail['location'] ?>"
                            src="<?= $nail['location'] ?>"
                            alt="<?= $nail['alttext'] ?>"
                            width="<?= $nail['width'] ?>px"
                            height="<?= $nail['height'] ?>px"
                            loading="lazy"
                        >
                    </a> 
<?php
    }
?>
                </div>
            </article>
<?php
}

if ($archiveresultCount > 1) {
?>
            <nav class='archive-pos'>
                <h3>Archive navigation</h3>
<?php
    // $archiveMoreRecentLink = function($pos, $link) use ($archiveresultPos) {
    //     if ($archiveresultPos > $pos) {
    //         echo '<a href="' . $link . '>' . ($archiveresultPos - $pos) . '</a> ';
    //         return true;
    //     } else return false;
    // };

    // $archiveEarlierLink = function($pos, $link) use ($archiveresultCount, $archiveresultPos) {
    //     if ($archiveresultCount - $archiveresultPos >= $pos) {
    //         echo ' <a href="' . $link . '>' . ($archiveresultPos + $pos) . '</a>';
    //         return true;
    //     } else return false;
    // };

    if ($archiveresultPos > 3) {
        echo '<a href="' . $recentestArchiveLink . '">Latest</a> ... ';
    }

    if ($archiveresultPos > 5) {
        echo '<a href="' . $recentArchiveLink5 . '" class="later5">' 
                . ($archiveresultPos - 5) . '</a> ... ';
    }

    if ($archiveresultPos > 2) {
        echo '<a href="' . $recentArchiveLink2 . '">' . ($archiveresultPos - 2) . '</a> ';
    }

    if ($archiveresultPos > 1) {
        echo '<a href="' . $recentArchiveLink1 . '" class="later1>' 
            . ($archiveresultPos - 1) . '</a> ';
    }

    echo $archiveresultPos;

    if ($archiveresultCount - $archiveresultPos >= 1) {
        echo ' <a href="' . $recentArchiveLink1 . '" class="earlier1">' 
            . ($archiveresultPos + 1) . '</a>';
    }

    if ($archiveresultCount - $archiveresultPos >= 2) {
        echo ' <a href="' . $recentArchiveLink2 . '">' . ($archiveresultPos + 2) . '</a>';
    }

    if ($archiveresultCount - $archiveresultPos >= 5) {
        echo ' ... <a href="' . $recentArchiveLink5 . '" class="earlier5">' 
            . ($archiveresultPos + 5) . '</a>';
    }

    if ($archiveresultCount - $archiveresultPos >= 3) {
        echo ' ... <a href="' . $earliestArchiveLink . '">Earliest</a>';
    }
?>
            </nav>
<?php 
}
?>
        </main>

        <footer>
            <?php require 'copyrightElement.php'; ?>
        </footer>
    </body>

</html>
<?php
}

?>