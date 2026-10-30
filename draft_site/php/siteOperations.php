<?php
namespace Briel;

use DateTimeImmutable;

require_once 'dbManip.php';
require_once 'pageGen.php';

function getAllBlogInfo(\PDO $db) {
    $getRecord = $db->query('SELECT blogtext, postdate FROM blog ORDER BY postdate DESC;');
    $record = $getRecord->fetch(\PDO::FETCH_ASSOC);
    $infos = [];
    while ($record) {
        $infos[] = new BlogInfo($record['blogtext'], $record['postdate']);
        $record = $getRecord->fetch(\PDO::FETCH_ASSOC);
    }
    return $infos;
}

/**
 * Summary of Briel\getPageInfo
 * Uses database statements to get all the information needed to generate the html file
 * for single comic reading pages.
 * @param \PDO $pdoConn
 * @param int $pageID
 * @param null|DatabaseStatements $stmt Optional database statements object
 * @param null|\PDOStatement $getPageRecord Optional database statement
 * @param null|\PDOStatement $getPrevPageID Optional database statement
 * @param null|\PDOStatement $getNextPageID Optional database statement
 * @param null|\PDOStatement $getPrevUpdateID Optional database statement
 * @param null|\PDOStatement $getNextUpdateID Optional database statement
 * @param null|\PDOStatement $getPageFromUpdateID Optional database statement
 * @param null|\PDOStatement $getUpdateFromPageID Optional database statement
 * @param null|\PDOStatement $getImgRecords Optional database statement
 * @param null|\PDOStatement $getThumbnailRecords Optional database statement
 * @param null|\PDOStatement $getTags Optional database statement
 * @param null|\PDOStatement $getCWs Optional database statement
 * @param null|\PDOStatement $getSpreadType Optional database statement
 * @param null|\PDOStatement $getStylePath Optional database statement
 * @return PageInfo 
 */
function getPageInfo(   \PDO $pdoConn, 
                        int $pageID, 
                        ?DatabaseStatements $stmt = null, 
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
    if (!$stmt) $stmt = new DatabaseStatements( $pdoConn, 
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
                : "";

    $links['next'] = 
            (($next = $stmt->getNextPageID->fetch(\PDO::FETCH_NUM)) AND $next[0] !== null) ? 
                executeAndFetch($stmt->getPageRecord, [$next[0]], \PDO::FETCH_ASSOC)['path']
                : "";

    $page1Path = $record['path'];
    $updateTitle = $record['title'];
    $isFirstOfUpdate = true;
    $updateID = executeAndFetchScalar($stmt->getUpdateFromPageID, $record['pageid']);
    if ($updateID OR $updateID === 0) {   
        $updateTitle = executeAndFetch($stmt->getUpdate, [$updateID])['title'];

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
        $page1Path = $page1['path'];
        if ($page1['pageid'] == $record['pageid']) {
            $links['skipBack'] = $links['prevUpd8'];
            $isFirstOfUpdate = true;
        } else {
            $links['skipBack'] = $page1['path'];
            $isFirstOfUpdate = false;
        }
        
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

    if (!$links['prevUpd8']) $links['prevUpd8'] = DEFAULTPREVUPDATELINK;
    if (!$links['skipBack']) $links['skipBack'] = DEFAULTPREVUPDATELINK;
    if (!$links['nextUpd8']) $links['nextUpd8'] = DEFAULTNEXTUPDATELINK;

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
            executeAndFetchScalar($stmt->getStylePath, $record['colorstyleid']), 
            $isFirstOfUpdate, 
            $page1Path,
            $updateTitle
    );
}

/**
 * Summary of Briel\getUpdateInfo
 * Uses database statements to get all the data to generate update pages or an archive entry
 * @param int $updateID
 * @param DatabaseStatements|null $stmt
 * @param \PDO|bool|null $pdoConn Optional database connection (will make own connection)
 * @return bool|UpdateInfo
 */
function getUpdateInfo( int $updateID, 
                        ?DatabaseStatements $stmt = null, 
                        \PDO|bool|null $pdoConn = null) {
    if (!$pdoConn) if (!tryPDOConnect($pdoConn)) return false;
    if (!$stmt) $stmt = new DatabaseStatements($pdoConn);

    $stmt->getPageFromUpdateID->execute([$updateID]);
    $pageIDs = $stmt->getPageFromUpdateID->fetchAll(\PDO::FETCH_COLUMN);
    $pageIDsOrdered = orderPageIDs($pageIDs, $pdoConn);

    $pagesOrdered = array_fill(0, \count($pageIDs), []);
    $thumbnailsOrdered = array_fill(0, \count($pageIDs), [[]]);
    for ($i = 0; $i < \count($pageIDs); $i++) {
        $id = $pageIDsOrdered[$i];
        $stmt->getPageRecord->execute([$id]);
        $pagesOrdered[$i] = $stmt->getPageRecord->fetch(\PDO::FETCH_ASSOC);

        $stmt->getThumbnailRecords->execute([$id]);
        $thumbnailsOrdered[$i] = $stmt->getThumbnailRecords->fetchAll(\PDO::FETCH_ASSOC);
        if ($thumbnailsOrdered[$i] === false) {
            $stmt->getMinSizeFile->execute([$id]);
            $thumbnailsOrdered[$i] = [$stmt->getMinSizeFile->fetch(\PDO::FETCH_ASSOC)];
        }
    }

    $getTags = getTagsFromMultiplePageIDsStmt($pageIDs, $pdoConn);
    $getTags->execute($pageIDs);
    $tags = $getTags->fetchAll(\PDO::FETCH_COLUMN);

    $getCWs = getCWsFromMultiplePageIDsStmt($pageIDs, $pdoConn);
    $getCWs->execute($pageIDs);
    $cws = $getCWs->fetchAll(\PDO::FETCH_COLUMN);

    $stmt->getUpdate->execute([$updateID]);
    $update = $stmt->getUpdate->fetch();
    
    return new UpdateInfo(  $update, 
                            $pagesOrdered, 
                            $thumbnailsOrdered, 
                            $tags, 
                            $cws    );
}

/**
 * Summary of Briel\getAllUpdateInfo
 * Gets the information for every update in the database (see `getUpdateInfo`). 
 * @param null|DatabaseStatements $stmt
 * @param \PDO|bool|null $pdoConn
 * @return array<bool|UpdateInfo>|bool
 */
function getAllUpdateInfo(  ?DatabaseStatements $stmt = null, 
                            \PDO|bool|null $pdoConn = null  ) {
    if (!$pdoConn) if (!tryPDOConnect($pdoConn)) return false;
    if (!$stmt) $stmt = new DatabaseStatements($pdoConn);

    $getUpdateIDs = $pdoConn->query(
            "SELECT updateid FROM comicupdate WHERE postdate IS NOT NULL ORDER BY postdate;");
    $allUpdateInfo = [];
    while (($updateID = $getUpdateIDs->fetch(\PDO::FETCH_ASSOC)) !== false) {
        $allUpdateInfo[] = getUpdateInfo($updateID['updateid'], $stmt, $pdoConn);
    }

    return $allUpdateInfo;
}

/**
 * Summary of Briel\regenerateArchivePages
 * Regenerates all archive pages and saves them. Old files are overwritten!
 * @param null|\PDO $pdoConn
 * @param array $paths An array of *1-indexed* paths to write to. 
 * Passed to `generateAllArchivePages` where you'll find more information.
 * @return array
 */
function regenerateArchivePages(?\PDO $pdoConn = null, array $paths = []) {
    if (!$pdoConn) $pdoConn = pdoConnect();
    $pageStrs = generateAllArchivePages(getAllUpdateInfo(pdoConn: $pdoConn), $paths);
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
 * Starts an output buffer and returns its contents (or false on failure) but doesn't
 * flush to output.
 * @param \PDO|null $pdoConn
 * @param string $addlStylePath Option path to an extra styling document to use in 
 * addition to `home.css`.
 * @return bool|string The return value of `ob_get_clean()`;
 */
function regenerateHomepage(?\PDO $pdoConn = null, string $addlStylePath = "") {
    if (!tryPDOConnect($pdoConn)) {
        echo "Could not continue/create database connection. Aborting...";
        return false;
    };
    $mostRecentBlog = $pdoConn->query("SELECT * FROM blog ORDER BY postdate DESC LIMIT 1;")
                                ->fetch(\PDO::FETCH_ASSOC);
    $updateID = getMostRecentUpdate($pdoConn);
    $getUpdateTitle = $pdoConn->prepare("SELECT title FROM comicupdate WHERE updateid = ?;");
    $getUpdateTitle->execute([$updateID]);
    $pageID = getFirstPageIDOfUpdateFromID($pdoConn, $updateID);
    ob_start();
    generateHomepage(   new BlogInfo(   $mostRecentBlog['blogtext'], 
                                        $mostRecentBlog['postdate'] ), 
                        getPageInfo($pdoConn, $pageID), 
                        $getUpdateTitle->fetch(\PDO::FETCH_NUM)[0], 
                        addlStylePath: $addlStylePath   );
    file_put_contents(HOMEPATH, ob_get_contents());
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


/**
 * Summary of Briel\generateSaveUpdatePageFiles
 * Generates and saves the HTML files for the given pages as part of update posting.
 * @param \PDO $pdoConn
 * @param array $pageRecords
 * @param array $prevUpdatePageIDs
 * @return void
 */
function generateSaveUpdatePageFiles(   \PDO $pdoConn, 
                                        array $pageRecords, 
                                        array $prevUpdatePageIDs    ) {
    echo "Getting page information for HTML files...\n";
    $comicPageStmts = new DatabaseStatements($pdoConn);

    $allPageInfo = [];
    for ($i = 0; $i < \count($allPageInfo); $i++) {
        $allPageInfo[$i] = getPageInfo( $pdoConn, 
                                        $pageRecords[$i]['pageid'], 
                                        stmt: $comicPageStmts   );
    }

    // get page info to be used to regenerate previous HTML files
    $allPrevPageInfo = [];
    for ($i = 0; $i < \count($allPrevPageInfo); $i++) {
        $allPrevPageInfo[$i] = getPageInfo( $pdoConn, 
                                            $prevUpdatePageIDs[$i], 
                                            stmt: $comicPageStmts   );
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

/**
 * Summary of Briel\redoCachedSearches
 * Backs up and then regenerates HTML files for all cached searches, saving them 
 * according to default search HTML naming format.
 * @param \PDO $pdoConn
 * @return void
 */
function redoCachedSearches(\PDO $pdoConn) {
    backupSearchData($pdoConn);
    $pdoConn->exec("USE user_submitted;");
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
    $pdoConn->exec("USE " . MASTERDBNAME . ";");
    $getTags = getTagsFromPageIDStmt($pdoConn);
    $getCWs = getCWsFromPageIDStmt($pdoConn);
    $getThumbnails = getThumbnailFromPageIDStmt($pdoConn);
    $getThumbnailContingency = getMinSizeFileFromPageIDStmt($pdoConn);
    
    $allsearchIsRegenerated = false;
    $insertParams = [];
    $insertStmt = '';
    for (   $n = 0, $search = $getSearch->fetch(\PDO::FETCH_ASSOC); 
            $search !== false; 
            $n++, $search = $getSearch->fetch(\PDO::FETCH_ASSOC)  ) {
        $desc = ($search['searchimgdesc'] == CHECKBOXON);
        $exact = ($search['matchexactly'] == CHECKBOXON);

        // get the search results
        [$results, $s, $execList] = allSearchComics($search['search'], 
                                                    $pdoConn, 
                                                    $exact, 
                                                    $desc);
        
        // If this search has returned all pages and the all search hasn't been 
        // regenerated, do that.
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
        for ($i = 0; $i < \count($results); $i++) {
            $getThumbnails->execute([$results[$i]['pageid']]);
            $thumbnailRecords = [];
            if (($thumbnailRecords = $getThumbnails->fetchAll(\PDO::FETCH_ASSOC)) == []) {
                $getThumbnailContingency->execute([$results[$i]['pageid']]);
                // If this also returns false, there are no associated files.
                $thumbnailRecords = [$getThumbnailContingency->fetch(\PDO::FETCH_ASSOC)];
            } 
            $getTags->execute([$results[$i]['pageid']]);
            $getCWs->execute([$results[$i]['pageid']]);
            $resultInfos[$i] = new SearchResultInfo(
                    $results[$i], 
                    $execList, 
                    $getTags->fetchAll(\PDO::FETCH_COLUMN), 
                    $getCWs->fetchAll(\PDO::FETCH_COLUMN), 
                    $thumbnailRecords, 
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
            $pdoConn->exec("USE user_submitted;");
            // save all pages and update cache records
            $i = 1;
            for ($i = 1; $i <= \count($pageStrs); $i++) {
                $filePath = SEARCHCACHEDIR
                            . implode('_', ['search', 
                                            urlencode($search['search']), 
                                            $search['searchimgdesc'], 
                                            $search['matchexactly'], 
                                            "p{$i}.html"]);
                // save the file
                $successfulSave = file_put_contents($filePath, $pageStrs[$i - 1]);
                if ($successfulSave === false) {
                    continue;
                    // do NOT update table if we can't write to file
                }

                // If there isn't currently an existing record in searchcache for 
                // this page index of the search results, adds to `$insertStmt` so 
                // that it can be inserted.
                $getSearchAtIndex->execute([':search' => $search['search'], 
                                            ':desc' => $search['searchimgdesc'], 
                                            ':exact' => $search['matchexactly'], 
                                            ':pageindex' => $i]);
                $searchAtIndex = $getSearchAtIndex->fetch(\PDO::FETCH_ASSOC);
                if (!$searchAtIndex) {
                    // assemble the statement...
                    $insertStmt .= " ROW(:search{$i}_$n, :desc{$i}_$n, :exact{$i}_$n, "
                            . ":index{$i}_$n, :path{$i}_$n)";
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
        $pdoConn->exec("USE user_submitted;");
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

    $pdoConn->exec("USE " . MASTERDBNAME . ";");
}

function generateSaveBlogPage(\PDO $pdoConn) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Returning false...\n";
        return false;
    }

    ob_start();
    $success = file_put_contents(   BLOGARCHIVEPATH, 
                                    generateBlogPage(getAllBlogInfo($pdoConn))  );
    ob_end_clean();
    if (!$success) {
        echo "Failed to write to blog archive page at " . BLOGARCHIVEPATH . "\n";
        return false;
    } else return true;
}

function checkToDeleteSearchCacheFiles() {
    if (promptInput("Would you like to delete the cached search page HTML files?\n(y/n) > ")
            == "y") {
        foreach (glob(SEARCHCACHEDIR . "?*.{HTML,html}", GLOB_BRACE) as $path) {
            unlink($path);
        }
    } 
}

/**
 * Summary of Briel\postUpdate
 * @param null|\PDO $pdoConn
 * @return bool
 */
function postUpdate(?\PDO $pdoConn = null) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    echo "Creating update...\n";
    tryBeginTransaction($pdoConn);

    // after file upload, generate and insert file records
    generateInsertUpdateFileRecords($pdoConn);
    
    // generate update record
    $updateRecord = generateUpdateRecordInteractive($pdoConn);

    // insert update record
    queryInsertRecords( $pdoConn, 
                        'comicupdate', 
                        ['title', 'updatedesc'], 
                        [$updateRecord], 
                        promptToCommit: false   );

    // only problem is the update's ID is generated when it's inserted.
    // so here we get the updateid generated during insert
    $getUpdateID = $pdoConn->prepare(<<<STMT
            SELECT updateid FROM comicupdate 
                WHERE title = ? AND updatedesc = ? AND postdate IS NULL LIMIT 1;
            STMT);
    $getUpdateID->execute([$updateRecord['title'], $updateRecord['updatedesc']]);
    $updateRecord['updateid'] = $getUpdateID->fetch(\PDO::FETCH_ASSOC)['updateid'];

    // add update ID to update order
    insertUpdateAtEnd($updateRecord['updateid'], $pdoConn, promptToCommit: false);

    $prevUpdateID = getEndOfRecentUpdateOrderID($pdoConn);
    $getPrevUpdatePages = getPageIDsFromUpdateIDStmt($pdoConn);
    $getPrevUpdatePages->execute([$prevUpdateID]);
    $prevUpdatePageIDs = $getPrevUpdatePages->fetchAll(\PDO::FETCH_COLUMN);

    // generate page records, add them to pageorder, and associate them to files/tags/cws
    $pageRecords = appendGeneratePageRecords(
            $pdoConn, 
            getLastPageIDOfUpdateFromID($pdoConn, $prevUpdateID), 
            promptToCommit: false
    );

    // associate pages with update
    associatePagesInteractive($updateRecord, $pdoConn);

    // generate and save new HTML files as well as new versions of old HTML files
    generateSaveUpdatePageFiles($pdoConn, $pageRecords, $prevUpdatePageIDs);

    $blogText = null;
    if (promptInput("Would you like to make a new blog post? (y/n) > " == "y")) {
        $blogText = promptInput("Enter blog text (or a path to it):\n> ");
        if (is_readable($blogText)) $blogText = file_get_contents($blogText) ?: $blogText;
    }

    backupSearchData($pdoConn);
    checkToDeleteSearchCacheFiles();

    // add post date to pages and update
    $addDateToPage = $pdoConn->prepare("UPDATE page SET postdate = NOW() WHERE pageid = ?;");
    foreach (array_column($pageRecords, 'pageid') as $pageID) {
        $addDateToPage->execute([$pageID]);
    }
    $addDateToUpdate = $pdoConn->prepare(
            "UPDATE comicupdate SET postdate = NOW() WHERE updateid = ?;");
    $addDateToUpdate->execute([$updateRecord['updateid']]);

    if ($blogText) {
        $pdoConn->prepare("INSERT INTO blog(blogtext, postdate) VALUES (?, NOW());")
                ->execute([$blogText]);
    }
    generateSaveBlogPage($pdoConn);

    echo "Regenerating homepage HTML file...\n";
    // regenerate home page
    regenerateHomepage($pdoConn);

    echo "Regenerating archive HTML files...\n";
    // regenerate archive
    $archivePagePaths = [];
    regenerateArchivePages($pdoConn, $archivePagePaths);

    // regenerate affected searches
    redoCachedSearches($pdoConn);

    echo "Final check for commitment. ";
    promptCommit($pdoConn);
}

function generateSiteUpdate(?\PDO $pdoConn = null) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    echo "Generating update files...\n";
    tryBeginTransaction($pdoConn);

    $unpostedUpdateRecords = $pdoConn
            ->query("SELECT updateid, title FROM comicupdate WHERE postdate IS NULL;")
            ->fetchAll(\PDO::FETCH_ASSOC);
    $tryAgain = false;
    $updateID = null;
    do {
        echo attributeTableStr( $unpostedUpdateRecords, 
                                "Unposted updates:", 
                                'updateid', 
                                'title' );
        $updateID = promptInput("Enter the ID of the update you want to post,"
                                        . " or 'n' if you want to cancel posting.\n> ");
        if ($updateID == 'n') return false;
        elseif (!\in_array($updateID, array_column($unpostedUpdateRecords, 'updateid'))) {
            echo "$updateID not in the list of IDs.\n";
            $tryAgain = true;
        } else {
            $tryAgain = false;
        }
    } while ($tryAgain);

    $updateRecord = executeAndFetch(
            getUpdateRecordFromIDStmt($pdoConn), 
            [$updateID], 
            mode: \PDO::FETCH_ASSOC
    );

    $pageRecords = [];
    $getPageIDs = getPageIDsFromUpdateIDStmt($pdoConn);
    $getPageIDs->execute([$updateID]);
    $getPageRecord = getPageRecordFromIDStmt($pdoConn);
    while (($page = $getPageIDs->fetch(\PDO::FETCH_ASSOC)) !== false) {
        $pageRecords[] = executeAndFetch(   $getPageRecord, 
                                            [$page['pageid']], 
                                            \PDO::FETCH_ASSOC   );
    }

    $prevUpdateID = executeAndFetchScalar(getPrevUpdateFromIDStmt($pdoConn), $updateID);
    $getPageIDs->execute([$prevUpdateID]);
    $prevUpdatePageIDs = $getPageIDs->fetchAll(\PDO::FETCH_COLUMN);

    // generate and save new HTML files as well as new versions of old HTML files
    generateSaveUpdatePageFiles($pdoConn, $pageRecords, $prevUpdatePageIDs);

    // $blogText = null;
    // if (promptInput("Would you like to make a new blog post? (y/n) > " == "y")) {
    //     $blogText = promptInput("Enter blog text (or a path to it):\n> ");
    //     if (is_readable($blogText)) $blogText = file_get_contents($blogText) ?: $blogText;
    // }

    backupSearchData($pdoConn);
    checkToDeleteSearchCacheFiles();

    // add post date to pages, update, and blog
    $addDateToPage = $pdoConn->prepare("UPDATE page SET postdate = NOW() WHERE pageid = ?;");
    foreach (array_column($pageRecords, 'pageid') as $pageID) {
        $addDateToPage->execute([$pageID]);
    }
    $addDateToUpdate = $pdoConn->prepare(
            "UPDATE comicupdate SET postdate = NOW() WHERE updateid = ?;");
    $addDateToUpdate->execute([$updateRecord['updateid']]);
    $pdoConn->exec(<<<STMT
            UPDATE blog SET postdate = NOW() WHERE blogid = (
                SELECT MAX(blogid) FROM blog WHERE postdate IS NULL
            );
            STMT);

    generateSaveBlogPage($pdoConn);

    echo "Regenerating homepage HTML file...\n";
    // regenerate home page
    regenerateHomepage($pdoConn);

    echo "Regenerating archive HTML files...\n";
    // regenerate archive
    $archivePagePaths = [];
    regenerateArchivePages($pdoConn, $archivePagePaths);

    echo "Regenerating cached search HTML files...\n";
    // regenerate affected searches
    redoCachedSearches($pdoConn);

    echo "Final check for commitment. ";
    promptCommit($pdoConn);
}

function generateSite(?\PDO $pdoConn = null) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }    

    $stmt = new DatabaseStatements($pdoConn);

    checkToDeleteSearchCacheFiles();

    $updateID = false;
    $blogID = false;
    if (promptInput("Would you like to add a post date to an undated blog or update? (y/n) > ")
             == "y") {
        $unpostedUpdateRecords = $pdoConn
                ->query("SELECT updateid, title FROM comicupdate WHERE postdate IS NULL;")
                ->fetchAll(\PDO::FETCH_ASSOC);
        $tryAgain = false;
        do {
            echo attributeTableStr( $unpostedUpdateRecords, 
                                    "Unposted updates:", 
                                    'updateid', 
                                    'title' );
            $updateID = promptInput("Enter the ID of the update you want to date,"
                                    . " or 'n' if you want to skip dating updates.\n> ");
            if ($updateID == 'n') $tryAgain = $updateID = false;
            elseif (!\in_array($updateID, array_column($unpostedUpdateRecords, 'updateid'))) {
                echo "$updateID not in the list of IDs.\n";
                $tryAgain = true;
            } else $tryAgain = false;
        } while ($tryAgain);

        $unpostedBlogRecords = $pdoConn->query(
                "SELECT blogid, CONCAT(SUBSTRING(blogtext FROM 0 FOR 29), '...') AS trunctext 
                            FROM blog WHERE postdate IS NULL;"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $tryAgain = false;
        do {
            echo attributeTableStr( $unpostedBlogRecords, 
                                    "Unposted blogs:", 
                                    'blogid', 
                                    'trunctext' );
            $blogID = promptInput("Enter the ID of the blog you want to date,"
                                    . " or 'n' if you want to skip dating blogs.\n> ");
            if ($blogID == 'n') $tryAgain = $blogID = false;
            elseif (!\in_array($blogID, array_column($unpostedBlogRecords, 'blogid'))) {
                echo "$blogID not in the list of IDs.\n";
                $tryAgain = true;
            } else $tryAgain = false;
        } while ($tryAgain);
    }

    if ($updateID !== false) {
        $addDateToUpdate = $pdoConn->prepare(
                "UPDATE comicupdate SET postdate = NOW() WHERE updateid = ?;");
        $addDateToUpdate->execute([$updateID]);
        $addDateToPages = $pdoConn->prepare(
                "UPDATE page SET postdate = NOW() WHERE pageid = ?;");
        $stmt->getPageFromUpdateID->execute([$updateID]);
        while (($page = $stmt->getPageFromUpdateID->fetch()) !== false) {
            $addDateToPages->execute([$page['pageid']]);
        }
    }

    if ($blogID !== false) {
        $addDateToBlog = $pdoConn->prepare(
                "UPDATE blog SET postdate = NOW() WHERE blogid = ?;");
        $addDateToBlog->execute([$blogID]);
    }

    $allPageIDs = $pdoConn
            ->query("SELECT pageid FROM page WHERE postdate IS NOT NULL;")
            ->fetchAll(\PDO::FETCH_COLUMN);

    regenerateComicPages($allPageIDs, $pdoConn, $stmt);

    regenerateArchivePages($pdoConn);

    redoCachedSearches($pdoConn);

    generateSaveBlogPage($pdoConn);

    regenerateHomepage($pdoConn);

    return true;
}

function regenerateComicPages(  array|int $pageIDs, 
                                ?\PDO $pdoConn = null, 
                                ?DatabaseStatements $pageStmts = null   ) {
    if (!tryPDOConnect($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    if (\is_int($pageIDs)) $pageIDs = [$pageIDs];

    echo "Regenerating comic page(s)...\n";
    $pageStmts ??= new DatabaseStatements($pdoConn);

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