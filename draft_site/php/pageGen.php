<?php
namespace Briel;

require_once 'brielConstants.php';

const DISPLAYWIDTH1 = 1920;
const DISPLAYWIDTH2 = 3000;

const RESULTSPERPAGE = 17; //whimsy

const SEARCHPAGEINDEXKEY = 'p';
const SEARCHCOMICSINPUTID = 'search-comics';

const CHECKBOXON = 'on';

const THUMBNAILWIDTH = '100px';

const DEFAULTWINDOWWIDTHS = [DISPLAYWIDTH1, DISPLAYWIDTH2];
const DEFAULTIMAGEWIDTHSORDERED = [1400, 800, 2000];

const DEFAULTPREVLINK = HOMEPATH;
const DEFAULTNEXTLINK = COMMENTPATH;
const DEFAULTPREVUPDATELINK = HOMEPATH;
const DEFAULTNEXTUPDATELINK = HOMEPATH;
const DEFAULTNEXTLINKS = [DEFAULTNEXTLINK, DEFAULTNEXTUPDATELINK];
const DEFAULTPREVLINKS = [DEFAULTPREVLINK, DEFAULTPREVUPDATELINK];

const HTMLDATEFORMAT = 'Y-m-d';
const BRIELDATEFORMAT = 'Y, M. jS, l';

class SearchResultInfo {
    public $searchRecord; 
    public $tokenExecList;
    public $date;
    public $pageTags;
    public $pageContWarns;
    public $thumbnailRecord;
    public $isExact;
    private $matchTags = NULL;
    private $nonMatchTags = NULL;
    private $matchContWarns = NULL;
    private $nonMatchContWarns = NULL;
    private $descMatches = NULL;

    public function __construct($searchRecord, 
                                $tokenExecList, 
                                $pageTags, 
                                $pageContWarns, 
                                $thumbnailRecord, 
                                $isExact) {
        $this->searchRecord = $searchRecord;
        $this->tokenExecList = $tokenExecList;
        $this->pageTags = $pageTags;
        $this->pageContWarns = $pageContWarns;
        $this->thumbnailRecord = $thumbnailRecord;
        $this->isExact = $isExact;
        
        $this->date = \DateTimeImmutable::createFromFormat( SQLDATETIMEFORMAT, 
                                                            $searchRecord['postdate'] );
    }

    public function getMatchTags() {
        if ($this->matchTags === NULL) {
            $this->matchTags = [];
            foreach (array_filter(  $this->searchRecord, 
                                    fn($k) => str_starts_with($k, 'tag'), 
                                    ARRAY_FILTER_USE_KEY    )
                    as $key => $match) {
                if ($match != 0) $this->matchTags[] = substr($key, 3);
            }
        }

        return $this->matchTags;
    }

    public function getNonMatchTags() {
        if ($this->nonMatchTags === NULL) {
            // Not sure why VS Code is marking this as unreachable...
            $this->nonMatchTags = array_diff($this->pageTags, $this->getMatchTags());
        }

        return $this->nonMatchTags;
    }

    public function getMatchCWs() {
        if ($this->matchContWarns === NULL) {
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
        if ($this->nonMatchContWarns === NULL) {
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
        if ($this->descMatches === NULL) {
            // Not sure why VS Code is marking this as unreachable...
            $this->descMatches = array_map(  
                    fn($str) => strtolower(substr($str, \strlen('desc'))), 
                    array_filter(   
                            array_keys($this->searchRecord), 
                            fn($key) => str_starts_with($key, 'desc')   
                    )
            );
        }
        
        return $this->descMatches;
    }

}

class UpdateInfo {
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
        $this->$date = \DateTimeImmutable::createFromFormat(SQLDATETIMEFORMAT, 
                                                            $updateRecord['postdate']);
        $this->tags = $tags;
        $this->contWarns = $contWarns;
        $this->pageRecordsOrdered = $pageRecordsOrdered;
        $this->thumbnailRecordsOrdered = $thumbnailRecordsOrdered;
    }
}

class PageInfo {
    public $record; 
    public $prevLink;
    public $nextLink; 
    public $prevUpd8Link;
    public $nextUpd8Link; 
    public $pageImgRecords;
    public $thumbnailRecords;
    public $tags;
    public $contWarns;
    public $spreadType;
    public $srcWidthsOrdered;
    public $stylePath = SITEROOT . '/styles/reading_page_style.css';
    public $windowWidthsOrdered = DEFAULTWINDOWWIDTHS;
    public function __construct($record, 
                                $prevLink, 
                                $nextLink,
                                $prevUpd8Link, 
                                $nextUpd8Link, 
                                $pageImgRecords, 
                                $thumbnailRecords, 
                                $tags, 
                                $contWarns, 
                                $spreadType, 
                                $srcWidthsOrdered = NULL, 
                                $stylePath = NULL, 
                                $windowWidthsOrdered = NULL) {
        $this->record       = $record;
        $this->prevLink     = $prevLink;
        $this->nextLink     = $nextLink;
        $this->prevUpd8Link = $prevUpd8Link;
        $this->nextUpd8Link = $nextUpd8Link;
        $this->pageImgRecords  = $pageImgRecords;
        $this->thumbnailRecords = $thumbnailRecords;
        $this->tags         = $tags;
        $this->contWarns    = $contWarns;
        $this->spreadType   = $spreadType;
        $this->srcWidthsOrdered = 
            $srcWidthsOrdered ?: // if $srcWidthsOrdered is falsy, alter based on spread type
            match($this->spreadType) {
                'double' => array_map(fn($n) => 2 * $n, DEFAULTIMAGEWIDTHSORDERED),
                default => DEFAULTIMAGEWIDTHSORDERED
            }
        ; // window widths stay the same
        if ($stylePath) $this->stylePath = $stylePath;
        if ($windowWidthsOrdered) $this->windowWidthsOrdered = $windowWidthsOrdered;
    }
}

class BlogInfo {
    public string $text;
    public \DateTimeImmutable $date;
    public function __construct(string $text, string $dateStr) {
        $this->text = $text;
        $this->date = \DateTimeImmutable::createFromFormat(SQLDATETIMEFORMAT, $dateStr);
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

function generateComicpage(PageInfo $page) {
    $searchPath = SEARCHPATH;
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
        
        <title><?= $page->record['title'] ?> | Breel Comix</title>
        <link rel="icon" href="<?= SITEROOT ?>/images/<?= SITEICONNAME ?>" type="image/x-icon">

        <link href="<?= SITEROOT ?>/styles/briel_font-faces.css" rel="stylesheet">
        <link href="<?= $page->record['stylepath'] === NULL 
                            ? $page->stylePath
                            : $page->record['stylepath'] ?>" 
              rel="stylesheet" 
              id="reading_stylesheet">

        <script type="module" src="js/reading_page_script.js"></script>
    </head>

    <body>

        <a href="<?= $page->prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">
            <main> 
                <?php 
    generateComicDisplayElements(   $page->pageImgRecords, 
                                    $page->prevLink, 
                                    $page->nextLink, 
                                    $page->srcWidthsOrdered, 
                                    $page->windowWidthsOrdered  ); 
                ?>
                <nav>
                    <p class="nav-line">
                        <a href="<?= $page->prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <a href="<?= $page->nextLink ?>" 
                           class="nav-button next-button">Next</a>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $page->prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <a href="<?= SITEROOT ?>/home_page.html" 
                           class="nav-button home-button">Home</a>
                        <a href="<?= $page->nextUpd8Link ?>" 
                           class="nav-button next-upd8-button"><?=
    $page->nextUpd8Link AND !\in_array($page->nextUpd8Link, [COMMENTPATH, HOMEPATH]) ?
            "Skip forth" : "More" ?></a>
                    </p>
                    <p class="nav-line">
                        <a href="<?= SITEROOT ?>/archive/archive_p1.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>
                <?php 
    generateReadingAccessoryElements(   $page->record, 
                                        $page->tags, 
                                        $page->contWarns, 
                                        $searchPath ); 
                ?>
            </main>

            <footer>
                <?php include 'copyrightElement.php'; ?>
            </footer>

        </div>  
        
        <a href="<?= $nextLink ?>" class="nav-button next-button" title="Next"></a>

    </body>
</html>

<?php
    return ob_get_flush();
}

function generateReadingStyle(  $bgColor, 
                                $textColor, 
                                $hiliteColor, 
                                $visitedColor,
                                $comicDefaultWidth = 800,
                                $fileWidths = DEFAULTIMAGEWIDTHSORDERED, 
                                $windowWidths = DEFAULTWINDOWWIDTHS,
                                $pageSectionShrinkFactor = 0.95,
                                $comicTopMargin = 8,
                                $gradient = NULL, 
                                $bgImageURL = NULL, 
                                $stretchBGImg = false   ) {
    sort($fileWidths);
    $fileWidthsOrdered = $fileWidths;
    sort($windowWidths);
    $windowWidthsOrdered = $windowWidths;
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
    for ($i = 0; $i < \count($windowWidthsOrdered); $i++) {
?>
@media screen and (min-width: <?= $windowWidthsOrdered[$i] + 1 ?>px) {
    html {
        font-size: xx-large;
    }

    body {
        --comic-page-width: <?= $fileWidthsOrdered[$i + 1] ?>px;
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

@media screen and (max-width: <?= $fileWidthsOrdered[0] ?>px) {
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

function generateComicDisplayElements(  $pageImgRecords, 
                                        $prevLink, 
                                        $nextLink,
                                        $srcDefaultWidths = [1400, 800, 2000],
                                        $windowWidthsOrder = [  DISPLAYWIDTH1, 
                                                                DISPLAYWIDTH2   ]   ) {
    // create an array of files ordered by width, keyed to width
    $filesWidthOrder = array_combine(array_column($pageImgRecords, 'width'), 
                                 $pageImgRecords);
    ksort($filesWidthOrder);

    // attempts to find an image with a width matching the defaults above as the default
    $srcWidth = NULL;
    foreach ($srcDefaultWidths as $width) {
        if (\array_key_exists($width, $filesWidthOrder)) {
            $srcWidth = $width;
            break;
        }
    }
    // if it can't find such an image, it just takes the smallest file provided
    // TODO: restrict this array to only files with a 'page' purpose
    if ($srcWidth === NULL) {
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
        echo $file['path'] . ' ' . $file['width'] . "w\n";
    }
        ?>"
    sizes="(max-width: <?= array_first($filesWidthOrder)['width'] ?>px) 100vw, <?php 
    reset($filesWidthOrder);
    // Undefined behavior if `count(filesWidthOrder) < count($windowWidthsOrder) + 1`
    foreach ($windowWidthsOrder as $maxWidth) {
        echo "(max-width: {$maxWidth}px) "
                . next($filesWidthOrder)['width'] 
                . "px,\n";
    }
    echo array_last($filesWidthOrder)['width'];
            ?>px"   
    src="<?= $filesWidthOrder[$srcWidth]['path'] ?>"
    alt="<?= $filesWidthOrder[$srcWidth]['alttext'] ?>"
    usemap="#nav-on-comic"
    id="single_page"
><?php 
    return ob_get_flush();
}

function tagLink($tag, $searchPath = SEARCHPATH) {
    return "<a href=\"$searchPath?search=" . urlencode("tag:$tag") . "\">$tag</a>";
}

function cwLink($cw, $searchPath = SEARCHPATH) {
    return "<a href=\"$searchPath?search=" . urlencode("cw:$cw") . "\">$cw</a>";
}

function generateReadingAccessoryElements(  
        $pageRecord, 
        $tags, 
        $contWarns, 
        $searchPath = SEARCHPATH
) {
    ob_start();
?>
<section id="tag_section">
    <h2>Tags</h2>
    <p id="tags-para">
    <?php
    foreach ($tags as $tag) {
        echo tagLink($tag) . "\n";
    }
    ?>
    </p>
</section>

<section id="cw_section">
    <h2>Content Warnings</h2>
    <p id="cws-para">
    <?php
    foreach ($contWarns as $cw) {
        echo cwLink($cw) . "\n";
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

function generateBlogEntryElements(BlogInfo $blog) {
    ob_start(); ?>
    <p><?= $blog->text ?></p>
    <p><time date="<?= $blog->date->format(HTMLDATEFORMAT) ?>">
        <?= $blog->date->format(BRIELDATEFORMAT) ?></time>
    </p>
<?php
    return ob_get_flush();
}

function generateHomepage(  BlogInfo $blogInfo, 
                            PageInfo $pageInfo, 
                            string $searchPath = SEARCHPATH, 
                            ?string $stylePath = NULL, 
                            string $scriptPath = SITEROOT . '/js/reading_page_script.js'   ) {
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
        
        <title><?= randomName() ?> web log</title>
        <link href="<?= SITEROOT ?>/images/<?= SITEICONNAME ?>" rel="icon" type="image/x-icon">

        <link href="<?= SITEROOT ?>/styles/<?= FONTFACESCSSNAME ?>" rel="stylesheet">
        <link href="<?= $pageInfo->stylePath ?>" rel="stylesheet" id="reading_stylesheet">
        <?php // Tries to apply page's reading style, but the homepage's style takes priority
        if ($stylePath) { ?>
            <link   href="<?= $stylePath ?>" 
                    rel="stylesheet" 
                    id="home_stylesheet"> 
        <?php
        }   ?>

        <script type="module" src="<?= $scriptPath ?>"></script>
    </head>

    <body>
        <a href="<?= $pageInfo->prevLink ?>" class="nav-button prev-button" title="Previous"></a>

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
                    srcset="<?= SITEROOT ?>/images/<?= $bannerFileStem ?>_800w.png 800w, 
                            <?= SITEROOT ?>/images/<?= $bannerFileStem ?>_1400w.png 1400w"
                    src="<?= SITEROOT ?>/images/<?= $bannerFileStem ?>_800w.png"
                    sizes="(max-width: 800px) 100vw, 
                           (max-width: 1920) 800px, 
                           1400px"
                    alt="<?= $bannerAlt ?>";
                >
            </header>

            <main> 
                <?php 
    generateComicDisplayElements(   $pageInfo->pageImgRecords, 
                                    $pageInfo->prevLink, 
                                    $pageInfo->nextLink, 
                                    $pageInfo->srcWidthsOrdered, 
                                    $pageInfo->windowWidthsOrdered  ); 
                ?>
                <nav>
                    <p class="nav-line">
                        <a href="<?= $pageInfo->prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <?php
    if ($pageInfo->nextLink AND !\in_array($pageInfo->nextLink, DEFAULTNEXTLINKS)) {
                        ?>
                        <a href="<?= $pageInfo->nextLink ?>" 
                           class="nav-button next-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $pageInfo->prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <?php
    if ($pageInfo->nextUpd8Link AND !\in_array($pageInfo->nextUpd8Link, DEFAULTNEXTLINKS)) {
                        ?>
                        <a href="<?= $pageInfo->nextUpd8Link ?>" 
                           class="nav-button next-upd8-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="<?= SITEROOT ?>/archive/archive_p1.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>

                <section id="blog_section">
                    <h2>Web log</h2>
                    <?php generateBlogEntryElements($blogInfo); ?>
                </section>

                <?php 
    generateReadingAccessoryElements(   $pageInfo->record, 
                                        $pageInfo->tags, 
                                        $pageInfo->contWarns, 
                                        $searchPath ); 
                ?>
            </main>

            <footer>
                <?php   include 'copyrightElement.php';     ?>
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

/**
 * Summary of Briel\formatSearchNavLink
 * @param int $navIndex
 * @param array $getParams
 * @return string
 */
function formatSearchNavLink(int $navIndex, array $getParams) {
    return  '<a href="' 
            . SEARCHPATH
            // if `$getParams(SEARCHPAGEINDEXKEY)` exists, 
            // this will change its value to `$navIndex`
            . formatGETParameters([...$getParams, SEARCHPAGEINDEXKEY => $navIndex]) 
            . '">' . $navIndex . '</a> ... ';
}

function randomName() {
    $name = match(rand(0,3)) { 
        0 => 'Briel', 
        1 => 'Breel', 
        2 => ' b r i e l ', 
        3 => 'BREEL'
    };

    $descriptor = match(rand(0,3)) { 
        0 => 'Comics', 
        1 => 'Comix', 
        2 => ' c o m i c s ', 
        3 => 'COMIX'
    };

    return "$name $descriptor";
}

/**
 * Summary of Briel\generateNav
 * Does flush element to output. Start a buffer to prevent this.
 * @param int $totalPageCount
 * @param int $pageOneIndex 1-indexed page index
 * @param array $allLinks
 * @return bool|string
 */
function generateNav(   int $totalPageCount, 
                        int $pageOneIndex, 
                        array $allLinks   ) {
    $pageZeroIndex = $pageOneIndex - 1;
    ob_start();
?>
<nav>
    <h3>Archive navigation</h3>
    <?php
    if ($pageOneIndex > 2) {
        echo "<a href=\"" . array_first($allLinks) . "\">Latest</a> ... ";
    }

    if ($pageOneIndex > 5 AND \array_key_exists($pageOneIndex - 5, $allLinks)) {
        echo "<a href=\"{$allLinks[$pageOneIndex - 5]}\">" . ($pageOneIndex - 5) . "</a> ... ";
    }

    foreach ([-2, -1] as $offset) {
        if ($pageOneIndex > -$offset AND \array_key_exists($pageOneIndex + $offset, $allLinks)) {
            echo "<a href=\"{$allLinks[$pageOneIndex + $offset]}\">" 
                . ($pageOneIndex + $offset) 
                . "</a> ";
        }
    }

    echo $pageOneIndex; // no link, since we're already here
    
    foreach ([1, 2] as $offset) {
        if (    $totalPageCount - $pageOneIndex >= $offset 
                AND \array_key_exists($pageOneIndex + $offset, $allLinks)   ) {
            echo " <a href=\"{$allLinks[$pageOneIndex + $offset]}\">" 
                . ($pageOneIndex + $offset) 
                . "</a>";
        }
    }

    if (    $totalPageCount - $pageOneIndex >= 5 
            AND \array_key_exists($pageOneIndex + 5, $allLinks) ) {
        echo " ... <a href=\"{$allLinks[$pageOneIndex + 5]}\">" 
            . ($pageOneIndex + 5) 
            . "</a>";
    }

    if ($totalPageCount - $pageOneIndex >= 2) {
        echo " ... <a href=\"" . array_last($allLinks) . "\">Earliest</a>";
    }
    ?>
</nav>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateSearchNav
 * Does flush element to output. Start a buffer to prevent this.
 * @param int $resultPageCount
 * @param int $resultPageIdx
 * @param array $getParams
 * @param array $allResultFilePaths
 * @return bool|string
 */
function generateSearchNav( int $resultPageCount, 
                            int $resultPageIdx,
                            array $getParams, 
                            array $allResultFilePaths = []) {
    if ($resultPageCount <= 1) { 
        //only even have nav bar if we have more than one page
        return '';
    }

    $links = $allResultFilePaths;
    $currentIdx = $resultPageIdx - 1;

    if (!$allResultFilePaths OR $resultPageCount != \count($allResultFilePaths)) {
        $linkPrefix = SEARCHPATH;
        $linkKeys = [   'latest', 
                        ...array_map(   fn($s) => $resultPageIdx + $s, 
                                        [-5, -2, -1, 1, 2, 5]   ), 
                        'earliest'  ];
        $links = array_fill_keys($linkKeys, '');

        // if `$getParams(SEARCHPAGEINDEXKEY)` exists, this new array will have a 
        // 1 there instead
        // remember: 1-indexed
        $links['latest'] = $linkPrefix . formatGETParameters(
                [...$getParams, SEARCHPAGEINDEXKEY => 1]
        );

        foreach (array_diff($linkKeys, ['latest', 'earliest']) as $index) {
            $links[$index] = $linkPrefix . formatGETParameters(
                    [...$getParams, SEARCHPAGEINDEXKEY => $index]
            );
        }
        // remember: 1-indexed
        $links['earliest'] = $linkPrefix . formatGETParameters(
                [...$getParams, SEARCHPAGEINDEXKEY => $resultPageCount]
        );
    }
    
    return generateNav($resultPageCount, $resultPageIdx, $links);
}

/**
 * Summary of Briel\generateSearchEntry
 * Does flush to output. Start a buffer to prevent this.
 * @param SearchResultInfo $result
 * @return bool|string
 */
function generateSearchEntry(SearchResultInfo $result) {
    ob_start();
?>
<article class="search-entry">
    <div class="thumbnails-div">
        <a href="<?= $result->searchRecord['path'] ?>" 
           class="page-link">
            <img
                class="thumbnail"
                attr-src="<?= $result->thumbnailRecord['path'] ?>"
                src=""
                alt="<?= $result->thumbnailRecord['alttext'] ?>"
                width="<?= $result->thumbnailRecord['width'] ?>px"
                height="<?= $result->thumbnailRecord['height'] ?>px"
                loading="lazy"
            >
        </a> 
    </div>
    <div class="detail-div">
        <h3><a href="<?= $result->searchRecord['path'] ?>" class="page-link"><?= 
        emphasizeIf($result->searchRecord['titlematch'], 
                    $result->searchRecord['title']  ) 
        ?></a></h3>
        <p> <h4>Date:</h4>
            <time date="<?= $result->date->format(HTMLDATEFORMAT) ?>"><?=
            implode(' ', 
                    [   emphasizeIf($result->searchRecord['yearmatch'], 
                                    $result->date->format('Y,')), 

                        emphasizeIf($result->searchRecord['monthmatch'], 
                                    $result->date->format('M.')), 

                        emphasizeIf($result->searchRecord['dayofmonthmatch'],
                                    $result->date->format('jS,')), 

                        emphasizeIf($result->searchRecord['daynamematch'],
                                    $result->date->format('l'))
                    ]); 
            ?></time>
        </p>
<?php
    if ($result->pageTags) {
?>
        <p><h4>Tags:</h4>
            <?= 
        implode(', ', 
                array_map(  fn($tag, $tagText) => 
                                '<a href="' . SEARCHPATH 
                                . "?search=" . urlencode("tag:$tag") . "\">$tagText</a>", 

                            [   ...$result->getMatchTags(), 
                                ...$result->getNonMatchTags()], 

                            [   ...array_map(   fn($t) => "<em>$t</em>", 
                                                $result->getMatchTags() ),
                                ...$result->getNonMatchTags()   ]
                )
        );
            ?>
        </p>
<?php
    }

    if ($result->pageContWarns) {
?>
        <p><h4>Content warnings:</h4>
            <?= 
        implode(', ', 
                array_map(  fn($cw, $cwText) => 
                                '<a href="' . SEARCHPATH 
                                . "?search=" . urlencode("cw:$cw") . "\">$cwText</a>", 

                            [   ...$result->getMatchCWs(), 
                                ...$result->getNonMatchCWs()    ], 

                            [   ...array_map(   fn($t) => "<em>$t</em>", 
                                                $result->getMatchCWs()  ),
                                ...$result->getNonMatchCWs()    ]
                )
        );
            ?>
        </p>
<?php
    }
?>
        <p><h4>Description:</h4>
            <?php  
    if ($result->isExact) {
        $hilitedDesc = $result->searchRecord['imagedesc'];
        foreach ($result->getDescMatches() as $match) {
            $hilitedDesc = str_replace( $match, 
                                        "<strong>$match</strong>", 
                                        $hilitedDesc);
            $hilitedDesc = str_replace( ucfirst($match), 
                                        '<strong>' . ucfirst($match) . '</strong>', 
                                        $hilitedDesc    );
            $hilitedDesc = str_replace( strtoupper($match), 
                                        '<strong>' . strtoupper($match) . '</strong>', 
                                        $hilitedDesc    );
        }
        
        echo $hilitedDesc;
    } else {
        echo $result->searchRecord['imagedesc'];
    }
            ?>
        </p>
    </div> <!-- class detail-div -->
</article>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateSearchForm
 * Does flush page to output. Start a buffer to prevent this.
 * @param string $idSuffix
 * @param string $searchStr
 * @param bool $isDescSearch
 * @param bool $isExact
 * @param bool $includeOptionsLink
 * @return bool|string
 */
function generateSearchForm(string $idSuffix = '', 
                            string $searchStr = '', 
                            bool $isDescSearch = false, 
                            bool $isExact = false, 
                            bool $includeOptionsLink = false) {
    $inputID = "search$idSuffix";
    ob_start(); ?>
<form role="search" action="<?= SEARCHPATH ?>" method="get">
    <p>
        <input 
            type="search" 
            name="search" 
            id="<?= $inputID ?>"
            placeholder="Search comic pages..."
            minlength="2"
            maxlength="256"
            size="34"
            pattern="[\w<?= htmlentities(' ,.?!();:-"\'') ?>]{2,256}"
            aria-label="Search comic pages"
            value="<?= $searchStr ?>"
            class="<?= SEARCHCOMICSINPUTID ?>"
        /> 
        <button type="submit">Search</button>
    </p>
    <label for="<?= $inputID ?>" class="search-label">
        Searches tags, dates, titles, and page numbers by default. 
    <?php if ($includeOptionsLink) { ?>
        See <a href="#search_options">search options</a>.
    <?php } ?>
    </label>
    <p>
        <div class="search-check">
            <input  type="checkbox" 
                    name="desc" 
                    id="desc<?= $idSuffix ?>"
                    <?= $isDescSearch ? 'checked="CHECKED"' : '' ?>
            >
            <label for="desc<?= $idSuffix ?>">Search image descriptions too</label>
        </div>
        <div class="search-check">
            <input  type="checkbox" 
                    name="exact" 
                    id="exact<?= $idSuffix ?>"
                    <?= $isExact ? 'checked="CHECKED"' : '' ?>
                    >
            <label for="exact<?= $idSuffix ?>">Match terms exactly</label>
        </div>
    </p>
</form>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateSearchPage
 * Does flush page to output. Start a buffer to prevent this.
 * @param string $searchStr
 * @param array $searchResultInfos
 * @param array $getParams
 * @param int $pageIndex
 * @param mixed $allFileNames
 * @return bool|string
 */
function generateSearchPage(string $searchStr, 
                            array $searchResultInfos, 
                            array $getParams, 
                            int $pageIndex = 1,
                            ?array $allFileNames = NULL) {
    
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
        
        <title>Search | <?= randomName() ?></title>
        <link rel="icon" href="<?= SITEROOT ?>/images/<?= SITEICONNAME ?>" type="image/x-icon" />

        <link href="<?= SITEROOT ?>/styles/defaults.css" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/<?= FONTFACESCSSNAME ?>" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/update_list_style.css" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/search_page_style.css" rel="stylesheet" />
        
        <script type="module" src="<?= SITEROOT ?>/js/search_page_script.js"></script>
    </head>

    <body>
        <header>
            <h1>Search</h1>
            <?php 
    generateSearchForm( '-header', 
                        $searchStr, 
                        $getParams['desc'] == CHECKBOXON, 
                        $getParams['exact'] == CHECKBOXON, 
                        true); 
            ?>
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
                    <li>Enter <kbd>-blegh</kbd> to exclude any page that contains "blegh"</li>
                    <li>Enter <kbd>title:tuesday</kbd> to get pages with "tuesday" in their 
                        titles, as opposed to just searching <kbd>tuesday</kbd> which gets you 
                        any page with a "tuesday" tag or posted on a tuesday or whatever. 
                        Valid prefixes are:
                        <ul>
                            <?php 
    foreach (SEARCHFIELDSPECS as $field) {
        echo "<li><kbd>$field:</kbd>";
        switch ($field) {
            case 'cw': echo ' (as in Content Warning)'; break;
            case 'day': echo ' (searches both day of the month and day of the week)'; break;
        }
        echo '</li>';
    }                       ?>              
                        </ul>
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
    generateSearchForm( '-footer', 
                        $searchStr, 
                        $getParams['desc'] == CHECKBOXON, 
                        $getParams['exact'] == CHECKBOXON   );

    generateSearchNav(\count($allFileNames), $pageIndex, $getParams, $allFileNames);

    include 'copyrightElement.php'; 
            ?>
        </footer>
    </body>

</html>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateAllSearchPages
 * Does *not* output anything. Starts, cleans, and ends a buffer.
 * @param string $searchStr
 * @param array $searchResultInfos
 * @param array $getParams
 * @param mixed $fileNames
 * @return array<bool|string>
 */
function generateAllSearchPages(string $searchStr, 
                                array $searchResultInfos, 
                                array $getParams, 
                                &$fileNames = []) {
    $infosOnPage = array_chunk($searchResultInfos, RESULTSPERPAGE);
    $pageStrs = array_fill(0, \count($infosOnPage), '');
    if ($fileNames == []) {
        $fileNameStem = implode('_', [  'search', 
                                        $getParams['search'], 
                                        $getParams['desc'], 
                                        $getParams['exact']   ]);
        $fileNames = array_map( fn($i) => "{$fileNameStem}_p{$i}.html", 
                                range(1, \count($infosOnPage) + 1) );
    }

    ob_start(); // generateSearchPage will try to flush to output
    for ($pageIdx = 0; $pageIdx < \count($infosOnPage); $pageIdx++) {
        $pageStrs[$pageIdx] = generateSearchPage(   $searchStr, 
                                                    $infosOnPage[$pageIdx], 
                                                    $getParams, 
                                                    $pageIdx, 
                                                    $fileNames   );
    }
    ob_end_clean(); // Don't need to output the result in any way

    return $pageStrs; 
}

function generateArchiveEntry($updateInfo) {
    ob_start();
?>
<article class="archive-entry">
    <div class="detail-div">
        <h3><a href="<?= $updateInfo->pageRecordsOrdered[0]['path'] ?>" 
            class="page-link"><?= $updateInfo->updateRecord['title'] ?></a></h3>
        <p><?= $updateInfo->updateRecord['updatedesc'] ?></p>
        <?php 
    if (\count($updateInfo->pageRecordsOrdered) > 1) {
        ?>
        <p class="pages-list"><h4>Pages:</h4> 
            <?php 
        for (   $i = 0; 
                $i < \count($updateInfo->pageRecordsOrdered);
                $i++    ) {
            echo '<a href="'
                . $updateInfo->pageRecordsOrdered[$i]['path']
                . '" class="page-link">'
                . ($i + 1)
                . "</a> ";
        }   ?>
        </p>
        <?php
    }   ?>
        <p><h4>Date:</h4>
            <time date="<?= $updateInfo->date->format(HTMLDATEFORMAT) ?>">
                <?= $updateInfo->date->format(BRIELDATEFORMAT); ?>
            </time>
        </p>
        <?php 
    if ($updateInfo->tags) {    ?> 
        <p><h4>Tags:</h4><?= 
        implode(', ', 
                array_map(  fn($tag) => tagLink($tag), 
                            $updateInfo->tags   )) ?>
        </p>
        <?php
    }

    if ($updateInfo->contwarns) {    ?> 
        <p><h4>Content Warnings:</h4><?= 
        implode(', ', 
                array_map(  fn($cw) => cwLink($cw), 
                            $updateInfo->contwarns  )) ?>
        </p>
        <?php
    }   ?>
    </div>
    <div class="thumbnails-div">
        <?php 
    foreach (   array_map(  NULL, 
                            $updateInfo->thumbnailRecordsOrdered, 
                            $updateInfo->pageRecordsOrdered ) 
                as [$thumbnail, $page]  ) {     ?> 
        <a href="<?= $page['path'] ?>" class="page-link">
            <img
                class="thumbnail"
                attr-src="<?= $thumbnail['path'] ?>"
                src="<?= $thumbnail['path'] ?>"
                alt="<?= $thumbnail['alttext'] ?>"
                width="<?= THUMBNAILWIDTH ?>"
                height="<?= THUMBNAILWIDTH ?>"
                loading="lazy"
            >
        </a>
        <?php
    }   ?>
    </div>
</article>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateArchivePage
 * @param mixed $updateInfos
 * @param mixed $pageIndex 1-indexed page index
 * @param mixed $totalPageCount
 * @param mixed $archivePagePaths
 * @return bool|string
 */
function generateArchivePage(   $updateInfos, 
                                $pageIndex, 
                                $totalPageCount, 
                                $archivePagePaths   ) {
    ob_start();
?>
<!doctype html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta name="viewport" content="width=device-width"/>
        <meta name="author" content="Breel">
        <meta name="description" content="Breel comics archive.">
        
        <title>Search | <?= randomName() ?></title>
        <link rel="icon" href="<?= SITEROOT ?>/images/<?= SITEICONNAME ?>" type="image/x-icon" />

        <link href="<?= SITEROOT ?>/styles/defaults.css" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/<?= FONTFACESCSSNAME ?>" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/update_list_style.css" rel="stylesheet" />
        <link href="<?= SITEROOT ?>/styles/archive_page_style.css" rel="stylesheet" />
        
        <script type="module" src="<?= SITEROOT ?>/js/archive_page_script.js"></script>
    </head>

    <body>
        <header>
            <h1>Archive</h1>
        </header>

        <main>
            <button class="toggle-nails">Hide thumbnails</button>
            <?php
    foreach ($updateInfos as $update) {
        generateArchiveEntry($update);
    }
            ?>
        </main>

        <footer>
            <?php 
            generateSearchForm(); 

            generateNav($totalPageCount, $pageIndex, $archivePagePaths);

            include 'copyrightElement.php';
            ?>
        </footer>
    </body>
</html>
<?php
    return ob_get_flush();
}

/**
 * Summary of Briel\generateAllArchivePages
 * @param array<UpdateInfo> $allUpdateInfos
 * @param array $paths A reference, modified if an empty array
 * @return array 1-indexed array of strings, each being a full HTML page
 */
function generateAllArchivePages(array $allUpdateInfos, array &$paths = []) {
    $infosOnPage = array_chunk($allUpdateInfos, RESULTSPERPAGE);
    $pageStrs = array_fill_keys(range(1, \count($infosOnPage) + 1), '');
    if ($paths == []) {
        $paths = array_map( fn($i) => ARCHIVEDIRPATH . "/archive_p{$i}.html", 
                            range(1, \count($infosOnPage) + 1)  );
    }

    ob_start(); // generateArchivePage will try to flush to output
    for ($pageIndex = 1; $pageIndex <= \count($infosOnPage); $pageIndex++) {
        $pageStrs[$pageIndex] = generateArchivePage($infosOnPage[$pageIndex - 1], 
                                                    $pageIndex,
                                                    \count($infosOnPage), 
                                                    $paths);
    }
    ob_clean(); // Don't need to output the result in any way

    return $pageStrs; 
}

?>