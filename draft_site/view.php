<?php

use Briel\DatabaseStatements;
use function Briel\executeAndFetchScalar;

$setting = parse_ini_file("dynamic.ini");
require_once $setting['BACKENDSCRIPTDIR'] . 'siteOperations.php';

function searchForView(array $get, PDO $db) {
    [$results, $s, $e] = Briel\allSearchComics(   
            $get['search'], 
            $db, 
            !empty($get['desc']) AND $get['desc'] == Briel\CHECKBOXON, 
            !empty($get['exact']) AND $get['exact'] == Briel\CHECKBOXON 
    );

    $search = $get['search'];
    $viewTitle = $search;

    $orderedIDs = Briel\orderUnorderablePageIDs($db, array_column($results, 'pageid'));
    $stmt = new DatabaseStatements($db);
    $pageInfosOrdered = array_map(  fn($id) => Briel\getPageInfo($db, $id, $stmt), 
                                    $orderedIDs );
    
    return [$pageInfosOrdered, $search, $viewTitle];
}

// first, do maintenance on the GET request information
$get = $_GET; // copy $_GET
// $get['search'] = 'wizard';
if (getenv('DEBUG_VIEW')) { // an environment variable injected in my VSCode debug launch.json
    $get['updateid'] = 1;
}

$pageInfosOrdered = [];
$search = '';
$viewTitle = '';
$prevUpdateID = null;
$nextUpdateID = null;

$db = Briel\pdoConnect();
if ($db !== false) {
    if (empty($get['updateid']) AND empty($get['search'])) {
        $viewTitle = "Empty view...";
    } else if (empty($get['updateid'])) { // search is not empty
        [$pageInfosOrdered, $search, $viewTitle] = searchForView($get, $db);
    } else { // updateID is not empty
        $stmt = new DatabaseStatements($db);
        $updateExists = $db->prepare(
                "SELECT EXISTS(SELECT NULL FROM comicupdate WHERE updateid = ?);");
        if (Briel\executeAndFetchScalar($updateExists, $get['updateid']) == 1) {
            $update = Briel\getUpdateInfo($get['updateid'], $stmt, $db);
            $viewTitle = $update->updateRecord['title'];
            $pageInfosOrdered = array_map(  
                    fn($page) => Briel\getPageInfo($db, $page['pageid'], $stmt), 
                    $update->pageRecordsOrdered 
            );

            $prevUpdateID = executeAndFetchScalar(  $stmt->getPrevUpdateID, 
                                                    $get['updateid']    )   ?: null;
            $nextUpdateID = executeAndFetchScalar(  $stmt->getNextUpdateID, 
                                                    $get['updateid']    )   ?: null;

        } else if (!empty($get['search'])) {    // update doesn't exist... try search
            [$pageInfosOrdered, $search, $viewTitle] = searchForView($get, $db);
        }
    }
}

Briel\generateViewPage($pageInfosOrdered, $viewTitle, $search, $prevUpdateID, $nextUpdateID);