<?php
namespace Briel;

use DateTimeImmutable;

require_once 'dbManip.php';
require_once 'pageGen.php';

function getPageInfo(   \PDO $pdoConn, 
                        int $pageID, 
                        ?ComicPageStatements $stmt = null, 
                        ?\PDOStatement $getPageRecord = null, 
                        ?\PDOStatement $getPrevPageID = null, 
                        ?\PDOStatement $getNextPageID = null, 
                        ?\PDOStatement $getPrevUpdateID = null, 
                        ?\PDOStatement $getNextUpdateID = null, 
                        ?\PDOStatement $getPageFromUpdateID = null, 
                        ?\PDOStatement $getUpdateFromPageID = null, 
                        ?\PDOStatement $getImgRecords = null, 
                        ?\PDOStatement $getThumbnailRecords = null, 
                        ?\PDOStatement $getTags = null, 
                        ?\PDOStatement $getCWs = null, 
                        ?\PDOStatement $getSpreadType = null, 
                        ?\PDOStatement $getStylePath = null ) {
    if (!$stmt) $stmt = new ComicPageStatements(    $pdoConn, 
                                                    $getPageRecord, 
                                                    $getPrevPageID, 
                                                    $getNextPageID, 
                                                    $getPrevUpdateID, 
                                                    $getNextUpdateID, 
                                                    $getPageFromUpdateID, 
                                                    $getUpdateFromPageID, 
                                                    $getImgRecords, 
                                                    $getThumbnailRecords, 
                                                    $getTags, 
                                                    $getCWs, 
                                                    $getSpreadType, 
                                                    $getStylePath   );
    $stmt->executePageIDStmts($pageID);

    $record = $stmt->getPageRecord->fetch(\PDO::FETCH_ASSOC);

    $links = array_fill_keys(['prev', 'next', 'prevUpd8', 'nextUpd8', 'skipBack'], '');

    $links['prev'] = 
            (($prev = $stmt->getPrevPageID->fetch(\PDO::FETCH_NUM)) AND $prev[0] !== null) ?
                executeAndFetch($stmt->getPageRecord, [$prev[0]], \PDO::FETCH_ASSOC)['path']
                : DEFAULTPREVLINK;

    $links['next'] = 
            (($next = $stmt->getNextPageID->fetch(\PDO::FETCH_NUM)) AND $next[0] !== null) ? 
                executeAndFetch($stmt->getPageRecord, [$next[0]], \PDO::FETCH_ASSOC)['path']
                : DEFAULTNEXTLINK;

    $updateID = executeAndFetchScalar($stmt->getUpdateFromPageID, $record['pageid']);
    if ($updateID OR $updateID === 0) {   
        $prevUpd8ID = executeAndFetchScalar($stmt->getPrevUpdateID, $updateID);
        if ($prevUpd8ID) {
            $links['prevUpd8'] = getFirstPageRecordOfUpdateFromID(   
                    $pdoConn, 
                    $prevUpd8ID, 
                    $stmt->getPageFromUpdateID, 
                    $stmt->getPrevPageID, 
                    $stmt->getPageRecord    )['path'];
        }

        $page1 = getFirstPageRecordOfUpdateFromID(  $pdoConn, 
                                                    $updateID, 
                                                    $stmt->getPageFromUpdateID, 
                                                    $stmt->getPrevPageID, 
                                                    $stmt->getPageRecord    );
        $links['skipBack'] = ($page1['pageid'] == $record['pageid']) ? 
                $links['prevUpd8'] :
                $page1['path'];
        
        $nextUpd8ID = executeAndFetchScalar($stmt->getNextUpdateID, $updateID);
        if ($nextUpd8ID) {
            $links['nextUpd8'] = getFirstPageRecordOfUpdateFromID(   
                    $pdoConn, 
                    $nextUpd8ID, 
                    $stmt->getPageFromUpdateID, 
                    $stmt->getPrevPageID, 
                    $stmt->getPageRecord    )['path'];
        }
    }

    if (!$links['prevUpd8']) $links['prevUpd8'] = DEFAULTPREVLINK;
    if (!$links['skipBack']) $links['skipBack'] = DEFAULTPREVLINK;
    if (!$links['nextUpd8']) $links['nextUpd8'] = DEFAULTNEXTLINK;

    return new PageInfo(
            $record, 
            $links['prev'], 
            $links['next'], 
            $updateID, 
            $links['prevUpd8'], 
            $links['skipBack'], 
            $links['nextUpd8'], 
            $stmt->getImgRecords->fetchAll(\PDO::FETCH_ASSOC),
            $stmt->getThumbnailRecords->fetchAll(\PDO::FETCH_ASSOC), 
            $stmt->getTags->fetchAll(\PDO::FETCH_COLUMN),
            $stmt->getCWs->fetchAll(\PDO::FETCH_COLUMN),
            executeAndFetchScalar($stmt->getSpreadType, $record['spreadid']), 
            executeAndFetchScalar($stmt->getStylePath, $record['colorstyleid'])
    );
}

function getAllUpdateInfo(\PDO|bool|null $pdoConn = null) {
    if (!$pdoConn) if (!tryPDOConnect($pdoConn)) return false;
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
        $pageIDs = $getPageIDs->fetchAll(\PDO::FETCH_COLUMN);
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

        $getTags = getTagsFromMultiplePageIDsStmt($pageIDs, $pdoConn);
        $getTags->execute($pageIDs);
        $tags = $getTags->fetchAll(\PDO::FETCH_COLUMN);

        $getCWs = getCWsFromMultiplePageIDsStmt($pageIDs, $pdoConn);
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

function regenerateArchivePages(?\PDO $pdoConn = null, array $paths = []) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    $pageStrs = generateAllArchivePages(getAllUpdateInfo($pdoConn), $paths);
    // Remember: $pageStrs and $paths are 1-indexed for parity with the site
    // Also remember: `$paths` is modified by `generateAllArchivePages`

    foreach (array_map(null, $paths, $pageStrs) as [$path, $page]) {
        file_put_contents($path, $page);
    }

    return $pageStrs;
}

/**
 * Summary of Briel\regenerateHomepage
 * Updates the home page to feature the first page of the most recently posted update.
 * @param mixed $pdoConn
 * @param mixed $addlStylePath
 * @return bool|string
 */
function regenerateHomepage(?\PDO $pdoConn = null, ?string $addlStylePath = null) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Could not continue/create database connection. Aborting...";
        return false;
    };
    $mostRecentBlog = $pdoConn->query("SELECT * FROM blog ORDER BY postdate DESC LIMIT 1;")
                                ->fetch(\PDO::FETCH_ASSOC);
    $pageID = getFirstPageIDOfUpdateFromID($pdoConn, getMostRecentUpdate($pdoConn));
    ob_start();
    generateHomepage(   new BlogInfo(   $mostRecentBlog['blogtext'], 
                                        $mostRecentBlog['postdate'] ), 
                        getPageInfo($pdoConn, $pageID), 
                        addlStylePath: $addlStylePath   );
    file_put_contents(HOMEFILEPATH, ob_get_contents());
    return ob_get_clean();
}

// function regenerateCachedSearchPages(?\PDO $pdoConn = null) {
//     if (!$pdoConn) $pdoConn = pdoConnect();
//     $paths = [];

//     // get existing search records
//     $getSearches = $pdoConn->prepare(
//             "SELECT DISTINCT search, searchimgdesc, matchexactly FROM searchcache;");

//     // get result info for each existing search in cache

//     // generate page strings from searches

//     // save pages to files

//     // update database in case pages are added (or removed somehow)


//     foreach (array_map(null, $paths, $pageStrs) as [$path, $page]) {
//         file_put_contents($path, $page);
//     }

//     return $pageStrs;
// }

function generateInsertUpdateFileRecords(\PDO $pdoConn) {
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
}

function generateSaveUpdatePageFiles(\PDO $pdoConn, array $pageRecords, array $prevUpdatePageIDs) {
    echo "Getting page information for HTML files...\n";
    $comicPageStmts = new ComicPageStatements($pdoConn);

    $allPageInfo = array_fill(0, \count($pageRecords), null);
    for ($i = 0; $i < \count($allPageInfo); $i++) {
        $allPageInfo[$i] = getPageInfo($pdoConn, $pageRecords[$i]['pageid'], stmt: $comicPageStmts);
    }

    // get page info to be used to regenerate previous HTML files
    $allPrevPageInfo = array_fill(0, \count($prevUpdatePageIDs), null);
    for ($i = 0; $i < \count($allPrevPageInfo); $i++) {
        $allPrevPageInfo[$i] = getPageInfo($pdoConn, $prevUpdatePageIDs[$i], stmt: $comicPageStmts);
    }

    echo "Generating HTML files...\n";
    ob_start(); // generateComicpage will flush output
    // generate new HTML files using page info
    foreach ($allPageInfo as $page) {
        if (!file_put_contents($page->record['path'], generateComicpage($page)))
            echo "Failed to put file at {$page->record['path']}.\n";
    }
    
    // regenerate HTML files from previous update (update links)
    foreach ($allPrevPageInfo as $prevPage) {
        if (!file_put_contents($prevPage->record['path'], generateComicPage($prevPage)))
            echo "Failed to put file from previous update at {$page->record['path']}.\n";
    }
    ob_end_clean();
}

function redoCachedSearches(\PDO $pdoConn) {
    backupSearchData($pdoConn);
    
    $getSearch = $pdoConn->query(
            "SELECT DISTINCT search, searchimgdesc, matchexactly FROM searchcache;");
    $getSearchAtIndex = $pdoConn->prepare(<<<STMT
        SELECT * FROM searchcache 
        WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc
            AND resultpageindex = :pageindex;
        STMT);
    $deleteSearchNotLessThanIndex = $pdoConn->prepare(<<<STMT
        DELETE FROM searchcache WHERE 
            search = :search AND matchexactly = :exact AND searchimgdesc = :desc
            AND resultpageindex >= :minpageindex;
        STMT);
    $getTags = getTagsFromPageIDStmt($pdoConn);
    $getCWs = getCWsFromPageIDStmt($pdoConn);
    $getThumbnail = getThumbnailFromPageIDStmt($pdoConn);
    $getThumbnailContingency = getMinSizeFileFromPageIDStmt($pdoConn);
    
    $allsearchIsRegenerated = false;
    
    $insertParams = [];
    $insertStmt = '';
    for (   $n = 0, $search = $getSearch->fetch(\PDO::FETCH_ASSOC); 
            $search !== false; 
            $n++, $search = $getSearch->fetch(\PDO::FETCH_ASSOC)  ) {
                
        $desc = ($search['searchimgdesc'] == CHECKBOXON);
        $exact = ($search['matchexactly'] == CHECKBOXON);

        [$results, $s, $execList] = allSearchComics($search['search'], 
                                                    $pdoConn, 
                                                    $exact, 
                                                    $desc);
        
        if (\count($results) >= getNumServablePages($pdoConn)) { 
            // this search has returned all pages!
            if (!$allsearchIsRegenerated) {
                // we look to regenerate the all search
                // set stuff up so that the search section below searches ''
                $search['search'] = '';
                $search['searchimgdesc'] = '';
                $desc = false;
                $search['matchexactly'] = '';
                $exact = false;
                [$results, $s, $execList] = allSearchComics('', $pdoConn);
            } else { // all search has already been regenerated, go to next loop.
                continue;
            }
        }

        // get the info for each page (lists of tags, cws, thumbnails)
        $resultInfos = array_fill(0, count($results), null);
        for ($i = 0; $i < count($results); $i++) {
            $getThumbnail->execute([$results[$i]['pageid']]);
            $thumbnailRecord = null;
            if (($thumbnailRecord = $getThumbnail->fetch(\PDO::FETCH_ASSOC)) === false) {
                $getThumbnailContingency->execute([$results[$i]['pageid']]);
                // If this also returns false, there are no associated files.
                $thumbnailRecord = $getThumbnailContingency->fetch(\PDO::FETCH_ASSOC);
            } 
            
            $getTags->execute([$results[$i]['pageid']]);
            $getCWs->execute([$results[$i]['pageid']]);

            $resultInfos[$i] = new SearchResultInfo(
                    $results[$i], 
                    $execList, 
                    $getTags->fetchAll(\PDO::FETCH_COLUMN), 
                    $getCWs->fetchAll(\PDO::FETCH_COLUMN), 
                    $thumbnailRecord, 
                    $exact
            );
        }

        // generateAllSearchPages on the sorted string and the info array
        $fileNames = [];
        $pageStrs = generateAllSearchPages(   
                $search['search'], 
                $resultInfos, 
                [   'search' => $search['search'], 
                    'desc' => $search['searchimgdesc'], 
                    'exact' => $search['matchexactly'], 
                    SEARCHPAGEINDEXKEY => 0 ],   // this last value is just in case
                $fileNames
        );

        if ($pageStrs) {
            // save all pages and update cache records
            $i = 1;
            for ($i = 1; $i <= \count($pageStrs); $i++) {
                $filePath = FILEROOT . SEARCHCACHEDIR
                            . implode('_', [    'search', 
                                                $search['search'], 
                                                $search['searchimgdesc'], 
                                                $search['matchexactly'], 
                                                "p{$i}.html"    ]);
                // save the file
                $successfulSave = file_put_contents($filePath, $pageStrs[$i - 1]);
                if ($successfulSave === false) {
                    continue;
                    // do NOT update table if we can't write to file
                }

                $getSearchAtIndex->execute([':search' => $search['search'], 
                                            ':desc' => $search['searchimgdesc'], 
                                            ':exact' => $search['matchexactly'], 
                                            ':pageindex' => $i]);
                $searchAtIndex = $getSearchAtIndex->fetch(\PDO::FETCH_ASSOC);
                if (!$searchAtIndex) {
                    // assemble the statement...
                    $insertStmt .= 
                    " ROW(:search{$i}_$n, :desc{$i}_$n, :exact{$i}_$n, :index{$i}_$n, :path{$i}_$n)";
                    $insertParams = [   ...$insertParams, 
                                        ":search{$i}_$n" => $search['search'], 
                                        ":desc{$i}_$n" => $search['searchimgdesc'], 
                                        ":exact{$i}_$n" => $search['matchexactly'], 
                                        ":index{$i}_$n" => $i, 
                                        ":path{$i}_$n" => $filePath ];
                }
            }

            // Delete old records for indices that no longer exist
            // You better have backed up previous searches!
            $deleteSearchNotLessThanIndex->execute([':search' => $search['search'], 
                                                    ':desc' => $search['searchimgdesc'], 
                                                    ':exact' => $search['matchexactly'], 
                                                    ':minpageindex' => $i]);
            
        } 
    }
    
    // Insert new search indices into database if necessary
    if ($insertStmt != '') {
        tryBeginTransaction($pdoConn);
        // insert the records from assembled statement
        $insert = $pdoConn->prepare(<<<STMT
                INSERT INTO searchcache (   search, 
                                            searchimgdesc, 
                                            matchexactly, 
                                            resultpageindex, 
                                            path    )
                VALUES $insertStmt;
                STMT);
        $insert->execute($insertParams);
    }
}

function postUpdate(?\PDO $pdoConn = null) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    echo "Creating update...\n";
    tryBeginTransaction($pdoConn);

    // after file upload, generate file records
    generateInsertUpdateFileRecords($pdoConn);
    
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

    $prevUpdateID = getEndOfRecentUpdateOrderID($pdoConn);
    $getPrevUpdatePages = getPageIDsFromUpdateIDStmt($pdoConn);
    $getPrevUpdatePages->execute([$prevUpdateID]);
    $prevUpdatePageIDs = $getPrevUpdatePages->fetchAll(\PDO::FETCH_COLUMN);

    // generate page records, add them to pageorder, and associate them to files/tags/cws
    $pageRecords = appendGeneratePageRecords(
            $pdoConn, 
            getLastPageIDOfUpdateFromID($pdoConn, $prevUpdateID)
    );

    // associate pages with update
    associatePagesInteractive($updateRecord, $pdoConn);

    // generate and save new HTML files as well as new versions of old HTML files
    generateSaveUpdatePageFiles($pdoConn, $pageRecords, $prevUpdatePageIDs);

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
    backupSearchData($pdoConn);
    redoCachedSearches($pdoConn);
    if (promptInput("Would you like to delete the cached search page HTML files?\n(y/n) > ")
            == "y") {
        foreach (glob(SEARCHCACHEDIRPATH . "?*.{HTML,html}", GLOB_BRACE) as $path) {
            unlink($path);
        }
    }
    // `search.php` will rewrite anything not in the search cache, so it's not a big deal
}

function regenerateComicPages(  array|int $pageIDs, 
                                ?\PDO $pdoConn = null, 
                                ?ComicPageStatements $pageStmts = null   ) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    if (\is_int($pageIDs)) $pageIDs = [$pageIDs];

    echo "Regenerating comic page(s)...\n";
    $pageStmts ??= new ComicPageStatements($pdoConn);

    ob_start();
    foreach ($pageIDs as $id) {
        $info = getPageInfo($pdoConn, $id, $pageStmts);
        $success = file_put_contents($info->record['path'], generateComicpage($info));
        if (!$success) 
            echo "Failed to write page ID {$id} to path {$info->record['path']}\n";
    }
    ob_end_clean();

    return true;
}