<?php

// A note: this will not return a page in a search until it has a path in the database!
// DONE!!! A thing to consider: session storage for pdoConnection, limiting searches

require_once './php/dbManip.php';
require_once './php/pageGen.php';

// first, do maintenance on the GET request information
$getParams = $_GET; // copy $_GET
if (getenv('DEBUG_SEARCH')) { // an environment variable injected in my VSCode debug launch.json
}

if (!key_exists('search', $getParams)) $getParams['search'] = '';

foreach (['desc', 'exact'] as $field) {
    if (!key_exists($field, $getParams) OR $getParams[$field] != Briel\CHECKBOXON) 
        $getParams[$field] = '';
}

if (!key_exists(Briel\SEARCHPAGEINDEXKEY, $getParams)) {
    $getParams[Briel\SEARCHPAGEINDEXKEY] = 1;
}

ob_start();
$pdoConnection = Briel\pdoConnect(); // check for a cookie? session storage?
ob_end_clean();
if ($pdoConnection === false) {
    readfile(Briel\BLANKSEARCHPATH);
    exit("Oh fuck! MySQL connection failed...");
}

// check if the connection was already in a transaction
$prevInTransaction = $pdoConnection->inTransaction();

// first, check if this exact search (with the terms potentially shuffled)
// has been done before
$prevSearch = $pdoConnection->prepare(<<<STMT
    SELECT path FROM searchcache 
    WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc
        AND resultpageindex = :pageindex
    STMT);
$searchcacheKey = Briel\searchcacheKeyFromSearchString($getParams['search']);
$prevSearch->execute([  
        ':search' => $searchcacheKey, 
        ':exact' => $getParams['exact'], 
        ':desc' => $getParams['desc'], 
        ':pageindex' => $getParams[Briel\SEARCHPAGEINDEXKEY]    
]);

$isOutputYet = false;

if (($prevExists = $prevSearch->fetch(PDO::FETCH_ASSOC)) !== false) {
    // this search *has* been done before.
    readfile($prevExists['path']);
    $isOutputYet = true;

    // update the database for logging purposes
    $update = $pdoConnection->prepare(<<<STMT
        UPDATE searchcache SET numtimes = numtimes + 1 
        WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc;
        STMT);
    $update->execute([  
            ':search' => $searchcacheKey, 
            ':exact' => $getParams['exact'], 
            ':desc' => $getParams['desc']
    ]);

} else {
    // the exact index value may not exist, but maybe the search does?
    $prevSearchOtherIndex = $pdoConnection->prepare(<<<STMT
            SELECT EXISTS(
                SELECT NULL FROM searchcache 
                WHERE search = :search 
                    AND matchexactly = :exact 
                    AND searchimgdesc = :desc
            );
            STMT);
    $prevSearchOtherIndex->execute([  
            ':search' => $searchcacheKey, 
            ':exact' => $getParams['exact'], 
            ':desc' => $getParams['desc']  
    ]);

    if ($prevSearchOtherIndex->fetch()[0] != 0) {
        // the search does exist! just not this page. output blank search page
        readfile(Briel\BLANKSEARCHPATH);
        $isOutputYet = true;

        // and update that ofc
        $update = $pdoConnection->prepare(<<<STMT
            UPDATE searchcache SET numtimes = numtimes + 1 
            WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc;
            STMT);
        $update->execute([  
                ':search' => $searchcacheKey, 
                ':exact' => $getParams['exact'], 
                ':desc' => $getParams['desc']
        ]);

    } else {
        // search is nowhere in the cache. do the actual search algorithm
        [$results, $s, $execList] = Briel\allSearchComics(
                $getParams['search'], 
                $pdoConnection, 
                $matchExactly = ($getParams['exact'] == Briel\CHECKBOXON), 
                $searchDesc = ($getParams['desc'] == Briel\CHECKBOXON)
        );

        if (count($results) >= Briel\getNumServablePages($pdoConnection)) { 
            // this search has returned all pages!
            $prevSearchOtherIndex->execute(
                    [':search' => '', ':exact' => '', ':desc' => '']);

            if ($prevSearchOtherIndex->fetch()[0] == 0) {
                // this means that there *is* no blank search
                // we need to generate its results
                // set stuff up so that the search section below searches ''
                // (luckily the actual $_GET parameters are unchanged)
                foreach (['search', 'desc', 'exact'] as $field) 
                    $getParams[$field] = '';
                $searchcacheKey = '';
                [$results, $s, $execList] = Briel\allSearchComics('', $pdoConnection);

            } else {
                // the search that returns all pages has already been done
                $prevSearch->execute([  
                        ':search' => '', 
                        ':exact' => '', 
                        ':desc' => '',
                        ':pageindex' => $getParams[Briel\SEARCHPAGEINDEXKEY]
                ]);

                // obligatory check for whether the index doesn't exist
                if (($allSearch = $prevSearch->fetch(PDO::FETCH_ASSOC)) === false) {
                    readfile(Briel\BLANKSEARCHPATH);
                    $isOutputYet = true;

                // but if it does, output the appropriate blank search
                } else {
                    readfile($allSearch['path']);
                    $isOutputYet = true;

                    $pdoConnection->exec(
                            "UPDATE searchcache SET numtimes = numtimes + 1 WHERE search = '';"
                    );
                }
            }
        }

        if (!$isOutputYet) {
            // search has never been done. we make the pages here

            // first, prep the info we'll need
            $getTags = Briel\getTagsFromPageIDStmt($pdoConnection);
            $getCWs = Briel\getCWsFromPageIDStmt($pdoConnection);
            $getThumbnail = Briel\getThumbnailFromPageIDStmt($pdoConnection);
            $getThumbnailContingency = Briel\getMinSizeFileFromPageIDStmt($pdoConnection);
            
            // next, get the info for each page (lists of tags, cws, thumbnails)
            $resultInfos = array_fill(0, count($results), null);
            for ($i = 0; $i < count($results); $i++) {
                $getThumbnail->execute([$results[$i]['pageid']]);
                $thumbnailRecord = null;
                if (($thumbnailRecord = $getThumbnail->fetch(PDO::FETCH_ASSOC)) === false) {
                    $getThumbnailContingency->execute([$results[$i]['pageid']]);
                    // If this also returns false, there are no associated files.
                    $thumbnailRecord = $getThumbnailContingency->fetch(PDO::FETCH_ASSOC);
                } 
                
                $getTags->execute([$results[$i]['pageid']]);
                $getCWs->execute([$results[$i]['pageid']]);

                $resultInfos[$i] = new Briel\SearchResultInfo(
                        $results[$i], 
                        $execList, 
                        $getTags->fetchAll(PDO::FETCH_COLUMN), 
                        $getCWs->fetchAll(PDO::FETCH_COLUMN), 
                        $thumbnailRecord, 
                        $getParams['exact'] == Briel\CHECKBOXON
                );
            }

            // generateAllSearchPages on the sorted string and the info array
            $pageStrs = Briel\generateAllSearchPages(   
                    $searchcacheKey, 
                    $resultInfos, 
                    $getParams
            );

            if ($pageStrs) {
                // output the HTML for the first page
                echo $pageStrs[0];
                $isOutputYet = true;

                // save all pages and insert cache records
                $execParams = [];
                $insertStmt = '';
                for ($i = 1; $i <= count($pageStrs); $i++) {
                    $filePath = Briel\SEARCHCACHEDIRPATH 
                                . implode('_', ['/search', 
                                                urlencode($searchcacheKey), 
                                                $getParams['desc'], 
                                                $getParams['exact'], 
                                                "p{$i}.html"]);
                    // save the file
                    if (file_put_contents($filePath, $pageStrs[$i - 1]) === false) {
                        continue;
                        // do NOT add record if we can't write to file
                    }

                    // assemble the statement...
                    $insertStmt .= 
                            " ROW(:search$i, :desc$i, :exact$i, :index$i, :path$i)";
                    $execParams = [ ...$execParams, 
                                    ":search$i" => $searchcacheKey, 
                                    ":desc$i" => $getParams['desc'], 
                                    ":exact$i" => $getParams['exact'], 
                                    ":index$i" => $i, 
                                    ":path$i" => $filePath  ];
                }

                if ($insertStmt != '') {
                    if (!$prevInTransaction) $pdoConnection->beginTransaction();
                    // insert the records from assembled statement
                    $insert = $pdoConnection->prepare(<<<STMT
                            INSERT INTO searchcache (   search, 
                                                        searchimgdesc, 
                                                        matchexactly, 
                                                        resultpageindex, 
                                                        path    )
                            VALUES $insertStmt;
                            STMT);
                    $insert->execute($execParams);
                }
            } else { // ...unless we have generated no pages, then output blank
                readfile(Briel\BLANKSEARCHPATH);
                $isOutputYet = true;
            }
        }
    }
}

if (!$prevInTransaction AND $pdoConnection->inTransaction()) $pdoConnection->commit();

// generate *all search result pages*--they'll all reference the same `$_GET` search value!
    // Though I will need to add in a separate parameter for page number

    // ...does a PDO object automatically close the connection when a script ends?
    // Or does it stay open if it's kept in session storage?

?>