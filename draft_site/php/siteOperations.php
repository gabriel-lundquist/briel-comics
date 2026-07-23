<?php
namespace Briel;

use DateTimeImmutable;

require_once 'dbManip.php';
require_once 'pageGen.php';

function getPageInfo(   \PDO $pdoConn, 
                        int $pageID, 
                        ?\PDOStatement $getPageRecord = NULL, 
                        ?\PDOStatement $getPrevPageID = NULL, 
                        ?\PDOStatement $getNextPageID = NULL, 
                        ?\PDOStatement $getPrevUpdateID = NULL, 
                        ?\PDOStatement $getNextUpdateID = NULL, 
                        ?\PDOStatement $getPageFromUpdateID = NULL, 
                        ?\PDOStatement $getImgRecords = NULL, 
                        ?\PDOStatement $getThumbnailRecords = NULL, 
                        ?\PDOStatement $getTags = NULL, 
                        ?\PDOStatement $getCWs = NULL, 
                        ?\PDOStatement $getSpreadType = NULL    ) {
    if (!$getPageRecord) $getPageRecord = getPageRecordFromIDStmt($pdoConn);
    if (!$getPrevPageID) $getPrevPageID = getPrevPageFromIDStmt($pdoConn);
    if (!$getNextPageID) $getNextPageID = getNextPageFromIDStmt($pdoConn);
    if (!$getPrevUpdateID) $getPrevUpdateID = getPrevUpdateFromIDStmt($pdoConn);
    if (!$getNextUpdateID) $getNextUpdateID = getNextUpdateFromIDStmt($pdoConn);
    if (!$getPageFromUpdateID) $getPageFromUpdateID = getPageIDsFromUpdateIDStmt($pdoConn);
    if (!$getImgRecords) $getImgRecords = getPageImgRecordsFromIDStmt($pdoConn);
    if (!$getThumbnailRecords) $getThumbnailRecords = getThumbnailRecordsFromIDStmt($pdoConn);
    if (!$getTags) $getTags = getTagsFromPageIDStmt($pdoConn);
    if (!$getCWs) $getCWs = getCWsFromPageIDStmt($pdoConn);
    if (!$getSpreadType) $getSpreadType = $pdoConn->prepare(
            "SELECT spreadtype FROM spread WHERE spreadid = ?;");

    foreach ([  $getPageRecord, 
                $getPrevPageID, 
                $getNextPageID, 
                $getPrevUpdateID, 
                $getNextUpdateID, 
                $getImgRecords, 
                $getThumbnailRecords, 
                $getTags, 
                $getCWs     ] as $getStatement) {
        // this works the way you'd hope it would!
        $getStatement->execute([$pageID]);
    }

    $record = $getPageRecord->fetch(\PDO::FETCH_ASSOC);

    return new PageInfo(
            $record, 
            ($prev = $getPrevPageID->fetch(\PDO::FETCH_ASSOC)) ?
                    executeAndFetch($getPageRecord, [$prev['pageid']], \PDO::FETCH_ASSOC)['path']
                    : DEFAULTPREVLINK, 
            ($next = $getNextPageID->fetch(\PDO::FETCH_ASSOC)) ? 
                    executeAndFetch($getPageRecord, [$next['pageid']], \PDO::FETCH_ASSOC)['path']
                    : DEFAULTNEXTLINK, 
            ($prevUpdate = $getPrevUpdateID->fetch(\PDO::FETCH_ASSOC)) ? 
                    getFirstPageRecordOfUpdateFromID(   $pdoConn, 
                                                        $prevUpdate['updateid'], 
                                                        $getPageFromUpdateID, 
                                                        $getPrevPageID, 
                                                        $getPageRecord      )['path']
                    : DEFAULTPREVUPDATELINK, 
            ($nextUpdate = $getNextUpdateID->fetch(\PDO::FETCH_ASSOC)) ? 
                    getFirstPageRecordOfUpdateFromID(   $pdoConn, 
                                                        $nextUpdate['updateid'], 
                                                        $getPageFromUpdateID, 
                                                        $getPrevPageID, 
                                                        $getPageRecord      )['path']
                    : DEFAULTNEXTUPDATELINK, 
            $getImgRecords->fetchAll(\PDO::FETCH_ASSOC), 
            $getThumbnailRecords->fetchAll(\PDO::FETCH_ASSOC), 
            $getTags->fetchAll(\PDO::FETCH_COLUMN),
            $getCWs->fetchAll(\PDO::FETCH_COLUMN),
            executeAndFetchScalar($getSpreadType, [$record['spreadid']])
    );
}

function getAllUpdateInfo(?\PDO $pdoConn = NULL) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    $getUpdates = $pdoConn->query(
            "SELECT * FROM comicupdate WHERE postdate IS NOT NULL ORDER BY postdate;");
    $allUpdateInfo = [];
    for (   $update = $getUpdates->fetch(\PDO::FETCH_ASSOC);
            $update !== false; 
            $update = $getUpdates->fetch(\PDO::FETCH_ASSOC)  ) {
        $getPageIDs = $pdoConn->prepare(<<<STMT
                SELECT pageid FROM page INNER JOIN comicupdatepage USING (pageid)
                    WHERE updateid = ?;
                STMT);
        $getPageIDs->execute([$update['updateid']]);
        $pageIDs = $getPageIDs->fetchAll(\PDO::FETCH_ASSOC);
        $pageIDsOrdered = orderPageIDs($pageIDs, $pdoConn);

        $getPage = $pdoConn->prepare("SELECT * FROM page WHERE pageid = ?;");
        $getThumbnail = getThumbnailFromPageIDStmt($pdoConn);
        $getThumbnailContingency = getMinSizeFileFromPageIDStmt($pdoConn);

        $pagesOrdered = array_fill(0, \count($pageIDs), []);
        $thumbnailsOrdered = array_fill(0, \count($pageIDs), []);
        for ($i = 0; $i < \count($pageIDs); $i++) {
            $id = $pageIDsOrdered[$i];
            $getPage->execute([$id]);
            $pagesOrdered[$i] = $getPage->fetch(\PDO::FETCH_ASSOC);

            $getThumbnail->execute([$id]);
            $thumbnailsOrdered[$i] = $getThumbnail->fetch(\PDO::FETCH_ASSOC);
            if ($thumbnailsOrdered[$i] === false) {
                $getThumbnailContingency->execute([$id]);
                $thumbnailsOrdered[$i] = $getThumbnailContingency->fetch(\PDO::FETCH_ASSOC);
            }
        }

        $getTags = getTagsFromMultiplePageIDsStmt(\count($pageIDs), $pdoConn);
        $getTags->execute($pageIDs);
        $tags = $getTags->fetchAll(\PDO::FETCH_COLUMN);

        $getCWs = getCWsFromMultiplePageIDsStmt(\count($pageIDs), $pdoConn);
        $getCWs->execute($pageIDs);
        $cws = $getCWs->fetchAll(\PDO::FETCH_COLUMN);

        $allUpdateInfo[] = new UpdateInfo(  $update, 
                                            $pagesOrdered, 
                                            $thumbnailsOrdered, 
                                            $tags, 
                                            $cws    );
    }

    return $allUpdateInfo;
}

function regenerateArchivePages(?\PDO $pdoConn = NULL, array $paths = []) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    $pageStrs = generateAllArchivePages(getAllUpdateInfo($pdoConn), $paths);
    // Remember: $pageStrs and $paths are 1-indexed for parity with the site
    // Also remember: `$paths` is modified by `generateAllArchivePages`

    foreach (array_map(NULL, $paths, $pageStrs) as [$path, $page]) {
        file_put_contents($path, $page);
    }

    return $pageStrs;
}

/**
 * Summary of Briel\regenerateHomepage
 * Updates the home page to feature the first page of the most recently posted update.
 * @param mixed $pdoConn
 * @param mixed $stylePath
 * @return bool|string
 */
function regenerateHomepage(?\PDO $pdoConn = NULL, ?string $stylePath = NULL) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    $mostRecentBlog = $pdoConn->query("SELECT * FROM blog ORDER BY postdate DESC LIMIT 1;")
                                ->fetch(\PDO::FETCH_ASSOC);
    $pageID = getFirstPageIDOfUpdateFromID($pdoConn, getMostRecentUpdate($pdoConn));
    ob_start();
    generateHomepage(   new BlogInfo(   $mostRecentBlog['blogtext'], 
                                        $mostRecentBlog['postdate'] ), 
                        getPageInfo($pdoConn, $pageID), 
                        stylePath: $stylePath   );
    file_put_contents(HOMEPATH, ob_get_contents());
    return ob_get_clean();
}

function postUpdate(?\PDO $pdoConn = NULL) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    echo "Creating update...\n";
    tryBeginTransaction($pdoConn);

    // after file upload, generate file records
    $tryAgain = false;
    do {
        $fileRecords = [];
        for ($numFiles = 1; promptInput("Add a file? (y/n) > ") == 'y'; $numFiles++) {
            $fileRecords[] = generateFileRecordInteractive(
                    promptInput("Enter path for file $numFiles: > "), $pdoConn);
        }
        if ($numFiles > 1) {
            echo ($numFiles - 1) . " file records generated:\n";
            print_r($fileRecords);
            // insert file records
            if (promptinput("Insert these into the database? (y/n) > ") == "y") {
                insertFileRecords($fileRecords, $pdoConn, promptCommitMessage: "Okay...");
                echo "...done!\n";
                $tryAgain = false;
            } elseif (promptInput(
                        "Okay. Wanna try again at adding files? (y/n) > ") == "y") {
                $tryAgain = true;
            } else {
                echo "Okay, moving on...\n";
                $tryAgain = false;
            }
        }
    } while ($tryAgain);
    
    // generate update record
    $updateRecord = generateUpdateRecordInteractive($pdoConn);

    // insert update record
    queryInsertRecords($pdoConn, 'comicupdate', ['title', 'updatedesc'], [$updateRecord]);

    // only problem is the update's ID is generated when it's inserted.
    // so here we get updateid generated during insert
    $getUpdateID = $pdoConn->prepare(<<<STMT
            SELECT updateid FROM comicupdate 
                WHERE title = ? AND updatedesc = ? AND postdate IS NULL LIMIT 1;
            STMT);
    $getUpdateID->execute([$updateRecord['title'], $updateRecord['updatedesc']]);
    $updateRecord['updateid'] = $getUpdateID->fetch(\PDO::FETCH_ASSOC)['updateid'];

    // add update ID to update order
    insertUpdateAtEnd($updateRecord['updateid'], $pdoConn);

    // generate page records, add them to pageorder, and associate them to files/tags/cws
    $pageRecords = appendGeneratePageRecords(
            $pdoConn, 
            getLastPageIDOfUpdateFromID($pdoConn, getEndOfRecentUpdateOrderID($pdoConn))
    );

    // associate pages with update
    associatePagesInteractive($updateRecord, $pdoConn);

    echo "Getting page information for HTML files...\n";
    // generate page info to be used in files
    $allPageInfo = array_fill(0, \count($pageRecords), NULL);
    for ($i = 0; $i < \count($allPageInfo); $i++) {
        $allPageInfo[$i] = getPageInfo($pdoConn, $pageRecords[$i]['pageid']);
    }

    echo "Generating HTML files...\n";
    // generate HTML files using page info
    ob_start(); // generateComicpage will flush output
    foreach ($allPageInfo as $page) {
        file_put_contents($page->record['path'], generateComicpage($page));
    }
    ob_end_clean();

    // add post date to pages and update
    $addDateToPage = $pdoConn->prepare("UPDATE page SET postdate = NOW() WHERE pageid = ?;");
    foreach (array_column($pageRecords, 'pageid') as $pageID) {
        $addDateToPage->execute([$pageID]);
    }
    $addDateToUpdate = $pdoConn->prepare(
            "UPDATE comicupdate SET postdate = NOW() WHERE updateid = ?;");
    $addDateToUpdate->execute([$updateRecord['updateid']]);

    echo "Regenerating homepage HTML file...\n";
    // regenerate home page
    regenerateHomepage($pdoConn);

    echo "Regenerating archive HTML files...\n";
    // regenerate archive
    $archivePagePaths = [];
    regenerateArchivePages($pdoConn, $archivePagePaths);

    // backup and delete/regenerate affected searches
    // Eh....... for now, don't parse through affected searches, just delete them all
    backupSearchData($pdoConn);
    $pdoConn->exec("DELETE FROM searchcache;");
    if (promptInput("Would you like to delete the cached search page HTML files?\n(y/n) > ")
            == "y") {
        foreach (glob(SEARCHCACHEDIRPATH . "?*.{HTML,html}", GLOB_BRACE) as $path) {
            unlink($path);
        }
    }
    // `search.php` will rewrite anything not in the search cache, so it's not a big deal
}
