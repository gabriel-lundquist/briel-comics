<?php
if (!defined('UNSETDEFAULT')) define('UNSETDEFAULT', '');

if (!isset($updateInfos)) $updateInfos = [];
if (!isset($archivePagePos)) $archivePagePos = 1;
if (!isset($archivePageCount)) $archivePageCount = 1;
if (!isset($recentestArchiveLink)) $recentestArchiveLink = UNSETDEFAULT;
if (!isset($recentArchiveLink5)) $recentArchiveLink5 = UNSETDEFAULT;
if (!isset($recentArchiveLink2)) $recentArchiveLink2 = UNSETDEFAULT;
if (!isset($recentArchiveLink1)) $recentArchiveLink1 = UNSETDEFAULT;
if (!isset($earlierArchiveLink1)) $earlierArchiveLink1 = UNSETDEFAULT;
if (!isset($earlierArchiveLink2)) $earlierArchiveLink2 = UNSETDEFAULT;
if (!isset($earlierArchiveLink5)) $earlierArchiveLink5 = UNSETDEFAULT;
if (!isset($earliestArchiveLink)) $earliestArchiveLink = UNSETDEFAULT;
?>

<!doctype html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta name="viewport" content="width=device-width"/>
        <meta name="author" content="Breel">
        <meta name="description" content="Breel comics update archive.">
        
        <title>Archive | Breel Comix</title>
        <link rel="icon" href="images/smileicon.ico" type="image/x-icon" />

        <link href="styles/defaults.css" rel="stylesheet" />
        <link href="styles/briel_font-faces.css" rel="stylesheet" />
        <link href="styles/update_list_style.css" rel="stylesheet" />
        <link href="styles/archive_page_style.css" rel="stylesheet" />
        
        <script type="module" src="js/archive_page_script.js"></script>
    </head>

    <body>
        <header>
            <h1>Archive</h1>

            <form role="search" action="search_page.html" method="get">
                <p>
                    <input 
                        type="search" 
                        name="search" 
                        id="search"
                        placeholder="Search comic pages..."
                        minlength="2"
                        maxlength="256"
                        size="34"
                        aria-label="Search comic pages"/> 
                    <button>Search</button>
                </p>
            </form>
            
        </header>

        <main>
            <button class="toggle-nails">Hide thumbnails</button>
<?php
foreach ($updateInfos as $update) {
?>
            <article class="archive-entry">
                <div class="detail-div">
                    <!-- The update page should just lead to the first page? -->
                    <h3><a href="<?= $update->pageRecordsOrdered[0]['location'] ?>" 
                        class="page-link"><?= $update->updateRecord['title'] ?></a></h3>
                    <p><?= $update->updateRecord['desc'] ?></p>
<?php
    if (\count($update->pageRecordsOrdered) > 1) {
?>
                    <p class="pages-list"><h4>Pages:</h4> 
<?php   for ($i = 0; $i < \count($update->pageRecordsOrdered); $i++) { ?>      
                        <a href="<?= $update->pageRecordsOrdered[$i]['location'] ?>" 
                           class="page-link"><?= $i + 1 ?></a> 
<?php
        }
?>
                    </p>
<?php
    }
?>
                    <p><h4>Date:</h4>
                        <time date="<?= $update->$date->format('Y-m-d') ?>"><?= 
                            $update->$date->format('Y, M. js, l')?></time>
                    </p>
<?php
    if (\count($update->tags) != 0) {
?>
                    <p><h4>Tags:</h4>
<?php
        foreach ($update->tags as $tag) {
?>
                        <a href="search_page.php?tag=<?= $tag ?>"><?= $tag ?></a>
<?php
        }
?>                  </p>
<?php
    }

    if (\count($update->contWarns) != 0) {
?>
                    <p><h4>Content Warnings:</h4>
<?php
        foreach ($update->contWarns as $contWarn) {
?>
                        <a href="search_page.php?tag=<?= $contWarn ?>"><?= $contWarn ?></a>
<?php
        }
?>                  </p>
<?php
    }
?>
                </div>
                <div class="thumbnails-div">
<?php 
    foreach (array_map(null, $update->pageRecordsOrdered, $update->thumbnailRecordsOrdered) as [$page, $nail]) {
?>
                    <a href="<?= $page['location'] ?>" class="page-link">
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

if ($archivePageCount > 1) {
?>
            <nav class='archive-pos'>
                <h3>Archive navigation</h3>
<?php
    // $archiveMoreRecentLink = function($pos, $link) use ($archivePagePos) {
    //     if ($archivePagePos > $pos) {
    //         echo '<a href="' . $link . '>' . ($archivePagePos - $pos) . '</a> ';
    //         return true;
    //     } else return false;
    // };

    // $archiveEarlierLink = function($pos, $link) use ($archivePageCount, $archivePagePos) {
    //     if ($archivePageCount - $archivePagePos >= $pos) {
    //         echo ' <a href="' . $link . '>' . ($archivePagePos + $pos) . '</a>';
    //         return true;
    //     } else return false;
    // };

    if ($archivePagePos > 3) {
        echo '<a href="' . $recentestArchiveLink . '">Latest</a> ... ';
    }

    if ($archivePagePos > 5) {
        echo '<a href="' . $recentArchiveLink5 . '" class="later5">' 
                . ($archivePagePos - 5) . '</a> ... ';
    }

    if ($archivePagePos > 2) {
        echo '<a href="' . $recentArchiveLink2 . '">' . ($archivePagePos - 2) . '</a> ';
    }

    if ($archivePagePos > 1) {
        echo '<a href="' . $recentArchiveLink1 . '" class="later1>' 
            . ($archivePagePos - 1) . '</a> ';
    }

    echo $archivePagePos;

    if ($archivePageCount - $archivePagePos >= 1) {
        echo ' <a href="' . $recentArchiveLink1 . '" class="earlier1">' 
            . ($archivePagePos + 1) . '</a>';
    }

    if ($archivePageCount - $archivePagePos >= 2) {
        echo ' <a href="' . $recentArchiveLink2 . '">' . ($archivePagePos + 2) . '</a>';
    }

    if ($archivePageCount - $archivePagePos >= 5) {
        echo ' ... <a href="' . $recentArchiveLink5 . '" class="earlier5">' 
            . ($archivePagePos + 5) . '</a>';
    }

    if ($archivePageCount - $archivePagePos >= 3) {
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