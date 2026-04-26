<?php
namespace Briel;

const SQLLOADFILENULL = '\N';
const SQLNULL = 'NULL';

const FILECOLUMNS = ["fileid",
                     "location",
                     "width",
                     "height",
                     "alttext",
                     "uploaddate", 
                     "modifydate", 
                     "filetype",
                     "filesize",
                     "pageid",
                     "ratio"];

const FILEINSERTCOLUMNS = ["fileid",
                          "location",
                          "width",
                          "height",
                          "alttext",
                          "filetype",
                          "filesize",
                          "pageid",
                          "ratio"];

const PAGEINSERTCOLUMNS = ["pageid", 
                           "title",
                           "location",
                           "imagedesc",
                           "spreadid",
                           "stylelocation"];

/**
 * Summary of Briel\promptInput
 * @param mixed $prompt
 * @return string
 */
function promptInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

/**
 * Summary of Briel\pdoConnect
 * WILL NEED TO EDIT IN AN ENVIRONMENT VARIABLE FOR THE PASSWORD
 * Returns the PDO connection if successful, `false` if not.
 * Default values come from my laptop server settings.
 * @param mixed $dbhost
 * @param mixed $dbuser
 * @param mixed $dbname
 * @param mixed $dbport
 * @param mixed $echoConnSuccess
 * @return bool|\PDO
 */
function pdoConnect( $dbhost = 'localhost', 
                     $dbuser = 'root', 
                     $dbname = 'briel_comics_test', 
                     $dbport = 3307, 
                     $enterPassword = false,
                     $echoConnSuccess = false ) {
    try {
        $conn = new \PDO("mysql:host=$dbhost;
                          dbname=$dbname;
                          port=$dbport", 
                         $dbuser, 
                         $enterPassword ? 
                            $promptInput("Enter password: ") : getenv('MySQLBreelPassword'));
        $conn->setAttribute(\PDO::ATTR_ERRMODE, 
                            \PDO::ERRMODE_EXCEPTION);
        if ( $echoConnSuccess ) echo "Connected successfully.\n";
        return $conn;
    } catch (\PDOException $e) {
        if ( $echoConnSuccess ) echo "Connection failed.\n" . $e->getMessage();
        return false;
    }
}

/**
 * Summary of Briel\execAndFetch
 * @param mixed $statement
 * @param mixed $executeArr
 * @param mixed $mode
 */
function execAndFetch($statement, 
                        $executeArr, 
                        $mode = \PDO::FETCH_BOTH) {
    if ($statement->execute($executeArr)) {
        return $statement->fetch($mode);
    } else return false;
}

/**
 * Summary of Briel\execAndFetchScalar
 * @param mixed $statement
 * @param mixed $execVar
 */
function execAndFetchScalar($statement, 
                            $execVar) {
    return $statement->execute([$execVar]) 
            ? $statement->fetch(\PDO::FETCH_NUM)[0] : false;
}

/**
 * Summary of Briel\generateFileRecordInteractive
 * @param mixed $filePath
 * @param mixed $pdoConn
 * @param mixed $ratioStr
 * @return bool|array{alttext: string, fileid: string, filesize: int, 
 *                    filetype: string, height: mixed, location: bool|string, 
 *                    pageid: string, ratio: mixed, width: mixed|bool}
 */
function generateFileRecordInteractive($filePath, 
                                       $pdoConn, 
                                       $ratioStr = NULL) {
    echo "Generating record for " . basename($filePath);
    if (!is_file($filePath)) {
        return false;
    }

    $absPath = realpath($filePath);

    // I *think* this should work
    [$imgWidth, $imgHeight] = getimagesize($absPath);

    $alttext = promptInput("Enter alt text (or a path to it):\n> ");
    if (is_readable($alttext)) {
        if (!($alttext = file_get_contents($alttext))) $alttext = "";
    }

    $fileExt = substr($filePath, strrpos($filePath, ".") + 1);

    $fileSizeKB = (int) (filesize($filePath) / 1000);

    $ratioStatement = $pdoConn->prepare("SELECT ratioid FROM aspectratio 
                                            WHERE ratio = :ratioStr;");
    $fetchedRow = NULL;
    if (!$ratioStr) {
        $ratioStr = promptInput("Enter aspect ratio (format #:#): > ");
    }
    // if execution is unsuccessful or there is no row in the selection
    if (!($ratioStatement->execute([":ratioStr" => $ratioStr]))
            or !($fetchedRow = $ratioStatement->fetch())) {
        do {
            $ratioStr = promptInput("$ratioStr not a valid ratio.\n"
                                    . "Enter aspect ratio (format #:#): > ");
        } while (!$ratioStatement->execute([":ratioStr" => $ratioStr])
                 or !($fetchedRow = $ratioStatement->fetch()));
    }
    $ratioID = $fetchedRow[0];

    return ["fileid"    => SQLNULL, 
            "location"  => $absPath, 
            "width"     => $imgWidth, 
            "height"    => $imgHeight, 
            "alttext"   => $alttext, 
            "filetype"  => $fileExt, 
            "filesize"  => $fileSizeKB, 
            "pageid"    => SQLNULL, 
            "ratio"     => $ratioID];
}

function rowPlaceholder($n) {
    return '(?' . str_repeat(', ?', $n) . ')';
}

/**
 * Summary of Briel\prepareInsertRecords
 * @param mixed $pdoConn An existing PDO object.
 * @param mixed $tableName String containing name of the table in the database
 * @param mixed $insertColumns Array of strings with titles of columns
 * @param mixed $records 2D array of strings, indexed by record then by column
 */
function prepareInsertRecords($pdoConn, 
                              $tableName, 
                              $insertColumns, 
                              $records) {
    $allPlaceholderStr = rowPlaceholder(\count($insertColumns)) 
                         . str_repeat(", \n" . rowPlaceholder(\count($insertColumns)), 
                                      \count($records) - 1);

    return $pdoConn->prepare("INSERT INTO $tableName ("
                             . implode(",", $insertColumns) 
                             . ") VALUES $allPlaceholderStr;");
}

/**
 * Summary of brielCode\executeInsertRecords
 * @param mixed $insertStatement Assumes this is prepared by `prepareInsertRecords`
 * @param mixed $records
 */
function executeInsertRecords($pdoConn, 
                              $insertStatement, 
                              $records, 
                              $debugTransaction = true) {
    $transactSuccess = false;
    try {
        $transactSuccess = $pdoConn->beginTransaction();
    } catch (\PDOException $e) {
        if ($debugTransaction) echo "{$e->getMessage()}\n";
        return false;
    }

    if (!$transactSuccess) {
        if ($debugTransaction) var_dump($pdoConn->errorInfo());
        return false;
    } else {
        for ($placeIdx = 1, $i = 0; $i < \count($records); $i++) {
            for ($j = 0; $j < \count($records[$i]); $j++, $placeIdx++) {
                $insertStatement->bindValue($placeIdx, $records[$i][$j]);
            }
        }
        $execStatus = $insertStatement->execute();
        if ($debugTransaction) {
            echo "Statement affected {$insertStatement->rowCount} rows.\n"
                 . "If you don't commit now, statement may be rolled back.";
            if (promptInput("Commit? (y/n) > ") == "y") $pdoConn->commit();
        } else $pdoConn->commit();

        return $execStatus;
    }
}

/**
 * Summary of Briel\queryInsertRecords
 * @param mixed $pdoConn An existing PDO object.
 * @param mixed $tableName String containing name of the table in the database
 * @param mixed $insertColumns Array of strings with titles of columns
 * @param mixed $records 2D array of strings, indexed by record then by column
 */
function queryInsertRecords($pdoConn, 
                            $tableName, 
                            $insertColumns, 
                            $records) {
    $insertStatement = prepareInsertRecords($pdoConn, 
                                            $tableName, 
                                            $insertColumns, 
                                            $records);
    if (executeInsertRecords($pdoConn, $insertStatement, $records)) {
        return $insertStatement;
    } else {
        return false;
    }
}

/**
 * Summary of Briel\insertFileRecords
 * @param mixed $records
 * @param mixed $pdoConn
 */
function insertFileRecords($records, 
                           $pdoConn) {

    $queryResult = queryInsertRecords($pdoConn, 
                                        "file", 
                                        FILEINSERTCOLUMNS, 
                                        $records);
    if ($pdoConn->inTransaction()) {
        echo "Query result is: ";
        var_dump($queryResult);
        echo "If you don't commit now, this change may be rolled back.";
        if (promptInput("Commit transaction? (y/n) > ") == "y") $pdoConn->commit();
    }
    return $queryResult;
}

/**
 * Summary of Briel\insertFileRecordsFolder
 * @param mixed $dirPath
 * @param mixed $pdoConn
 * @return array<array{alttext: bool|string, fileid: string, filesize: int, 
 *                              filetype: string, height: mixed, location: bool|string, 
 *                              pageid: string, ratio: mixed, width: mixed|bool>}
 */
function insertFileRecordsFolder($dirPath, $pdoConn) {

    $fileInfo = \finfo_open(\FILEINFO_MIME_TYPE);

    $filePaths = array_map(fn($fileName) => realpath("$dirPath/$fileName"), 
                           \scandir($dirPath));
    
    $records = [];
    foreach ($filePaths as $filePath) {
        $fileInfoStr = \finfo_file($fileInfo, $filePath);
        // if MIME type is image
        if (substr($fileInfoStr, 
                   0,
                   strrpos($fileInfoStr, '/')) == 'image') {
            $records[] = generateFileRecordInteractive($filePath, $pdoConn);
        }
    }

    return $records;
}

/**
 * Summary of Briel\loadFileRecords
 * @param mixed $dirPath
 * @param mixed $dbhost
 * @param mixed $dbuser
 * @param mixed $dbname
 * @param mixed $dbport
 */
function loadFileRecords($dirPath, 
                         $dbhost = 'localhost', 
                         $dbuser = 'root', 
                         $dbname = 'briel_comics_test', 
                         $dbport = 3307) {
    
    $pdoConn = pdoConnect($dbhost, 
                          $dbuser, 
                          $dbname, 
                          $dbport);
    return insertFileRecords(insertFileRecordsFolder($dirPath, $pdoConn), 
                             $pdoConn);
}

function insertPageRecords($records, $pdoConn) {
    $queryResult = queryInsertRecords($pdoConn, 
                                      'page',
                                      PAGEINSERTCOLUMNS, 
                                      $records);
    if ($pdoConn->inTransaction()) {
        echo "Query result is: ";
        var_dump($queryResult);
        echo "If you don't commit now, this change may be rolled back.";
        if (promptInput("Commit transaction? (y/n) > ") == "y") $pdoConn->commit();
    }
    return $queryResult;
}

/**
 * Summary of Briel\getFileWidthLocations
 * @param mixed $pageid
 * @param mixed $pdoConn
 */
function getFileWidthLocations($pageid, $pdoConn) {
    
    if ($selectFile = $pdoConn->prepare('SELECT width, location FROM file 
                                         WHERE pageid = ?;')
            AND $selectFile->execute([$pageid])) {

        return $selectFile->fetchAll(\PDO::FETCH_KEY_PAIR);
        // Writes location values into an array indexed by width
    } else {
        return false;
    }
}

/**
 * Summary of Briel\is_file_path
 * @param mixed $str
 * @param mixed $fileExt
 * @return bool|int
 */
function is_file_path($str, $fileExt = ".+") {
    return preg_match("(.*/.+\.$fileExt)", $str);
}

/**
 * Summary of Briel\generatePageRecordInteractive
 * @param mixed $pdoConn
 * @return array{imagedesc: bool|string, location: string, 
 *               pageid: string, spreadid: mixed, 
 *               stylelocation: string, title: string}
 */
function generatePageRecordInteractive($pdoConn) {
    echo "Generating new page record...";
    
    // Set title
    $title = promptInput("Enter title: > ");
    $findFileFromTitle = $pdoConn->prepare("SELECT location FROM page 
                                            WHERE title = ?;");
    $existingTitle = $pdoConn->prepare("SELECT EXISTS(
                                            SELECT title FROM page
                                            WHERE title = ?);");                                 
    $useAnyway = false;
    while (execAndFetchScalar($existingTitle, $title) AND !$useAnyway) {
        echo "Warning: $title already exists in page at "
             . execAndFetchScalar($findFileFromTitle, $title)
             . "\n";
        if (promptInput("Use this title anyway? (y/n) > ") == "y") {
            $useAnyway = true;
        } else $title = promptInput("Enter title: > ");
    }
    
    // Set location
    $useAnyway = false;
    $promptHTMLPath = fn() => promptInput("Enter HTML file path "
                                          . "(doesn't need to exist):"
                                          . "\n> ");
    $location = $promptHTMLPath();
    while (!is_file_path($location, "html") 
           OR (file_exists($location) AND !$useAnyway)) {
        if (!is_file_path($location, "html")) {
            echo "Error: $location is not a path for an HTML file.\n";
            $location = $promptHTMLPath();
        } else if (file_exists($location)) {
            if (promptInput("Warning: $location already exists.\n"
                            . "Use it anyway? (y/n) > ") == "y") {
                $useAnyway = true;
            } else $location = $promptHTMLPath();
        }
    }

    // Get image description
    $imageDesc = promptInput("Enter comic page description "
                                . "(or a path to it):\n> ");
    if (is_readable($imageDesc)) {
        $imageDesc = file_get_contents($imageDesc);
    }

    // Get spread ID
    $getSpreadID = $pdoConn->prepare("SELECT spreadid FROM spread
                                        WHERE spreadtype = ?;");
    $spreadID = execAndFetchScalar($getSpreadID, 
                                   (promptInput("Double spread? (y/n) > ") == "y")
                                    ? "double" : "normal");

    // Get location of a special style (if present)
    $styleLocation = promptInput("Enter path to special style (or 'n' if none):\n> ");
    while (!is_file_path($styleLocation, "css") AND $styleLocation != "n") {
        echo "Error: $styleLocation not a css file.\n";
        $styleLocation = promptInput("Enter path to special style (or 'n' if none):\n> ");
    }
    if ($styleLocation == "n") $styleLocation = SQLNULL;

    return array_combine(PAGEINSERTCOLUMNS, [SQLNULL, 
                                             $title, 
                                             $location, 
                                             $imageDesc, 
                                             $spreadID, 
                                             $styleLocation]);
}

/**
 * Summary of Briel\insertAssociationsInteractive
 * @param mixed $pageRecord
 * @param mixed $table
 * @param mixed $pdoConn
 * @return bool|string[]
 */
function insertAssociationsInteractive($pageRecord, $table, $pdoConn) {
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting $table assocation.";
        return false;
    }

    $existing = $pdoConn->query("SELECT name FROM $table;")
                            ->fetchAll(\PDO::FETCH_COLUMN);
    $insertNew = $pdoConn->prepare("INSERT INTO $table ({$table}id, name) VALUE (?,?);");
    $getIDFromName = $pdoConn->prepare("SELECT {$table}id FROM $table WHERE name = ?;");

    $listStr = promptInput("Adding $table associations with {$pageRecord['title']}...\n"
                                . "Type in comma-separated {$table}s. Existing {$table}s:\n"
                                . implode("\t", $existing)
                                . "\n> ");
    $tieList = array_map('trim', explode(",", $listStr));

    
    $exists = $pdoConn->prepare("SELECT EXISTS(
                                    SELECT name FROM $table 
                                    WHERE name = ?
                                 );");
    $assocRecords = [];
    $finalList = [];
    foreach ($tieList as $tie) {

        if (!preg_match('([\w]+[-\w]*)', $tie)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the $table. \nSkipping $tie.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!execAndFetchScalar($exists, $tie)) {
            if (promptInput("$tie not an existing $table. Add it? (y/n) > ") == "y") {
                $insertNew->execute([SQLNULL, $tie]);
            } else {
                echo "Okay, skipping $tie...\n";
                continue;
            }
        }

        $assocRecords[] = [execAndFetchScalar($getIDFromName, $tie), 
                              $pageRecord["pageid"]];
        $finalList[] = $tie;
    }

    if (queryInsertRecords($pdoConn, 
                           "{$table}page", 
                           ["{$table}id", "pageid"], 
                           $assocRecords)) {
        if (promptInput("{$table}s now associated with this page:\n"
                        . implode("\n", $finalList)
                        . "\nAcceptable? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";
            $pdoConn->commit();
            return $finalList;
        } else {
            echo "Okay. Rolling back changes...\n";
            $pdoConn->rollback();
            return false;
        }
    } else {
        echo "Error: could not insert records. Rolling back and returning...\n";
        $pdoConn->rollback();
        return false;
    }

}

/**
 * Summary of Briel\insertTagAssociationsInteractive
 * Assumes `$pageRecord` is filled out and given a page ID
 * @param mixed $pageRecord
 * @param mixed $pdoConn
 * @return bool|string[]
 */
function insertTagAssociationsInteractive($pageRecord, $pdoConn) {
    return insertAssociationsInteractive($pageRecord, "tag", $pdoConn);
    /*
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting tag assocation.";
        return false;
    }

    $existingTags = $pdoConn->query("SELECT name FROM tag;")
                            ->fetchAll(\PDO::FETCH_COLUMN);
    $insertNewTag = $pdoConn->prepare("INSERT INTO tag (tagid, name) VALUE (?,?);");
    $getTagIDFromName = $pdoConn->prepare("SELECT tagid FROM tag WHERE name = ?;");

    $tagListStr = promptInput("Adding tag associations with {$pageRecord['title']}...\n"
                                . "Type in comma-separated tags. Existing tags:\n"
                                . implode("\t", $existingTags)
                                . "\n> ");
    $tagList = array_map(fn($s) => trim($s), explode(",", $tagListStr));

    $tagExists = $pdoConn->prepare("SELECT EXISTS(
                                        SELECT name FROM tag 
                                        WHERE name = ?
                                   );");
    $tagAssocRecords = [];
    $finalTagList = [];
    foreach ($tagList as $tag) {

        if (!preg_match('([\w]+[-\w]*)', $tag)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the tag. \nSkipping $tag.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!execAndFetchScalar($tagExists, $tag)) {
            if (promptInput("$tag not an existing tag. Add it? (y/n) > ") == "y") {
                $insertNewTag->execute([SQLNULL, $tag]);
            } else {
                echo "Okay, skipping $tag...\n";
                continue;
            }
        }

        $tagAssocRecords[] = [$getTagIDFromName->execute([$tag])->fetch()[0], 
                              $pageRecord["pageid"]];
        $finalTagList[] = $tag;
    }

    if (queryInsertRecords($pdoConn, 
                           "tagpage", 
                           ["tagid", "pageid"], 
                           $tagAssocRecords)) {
        if (promptInput("Tags now associated with this page:\n"
                        . implode("\n", $finalTagList)
                        . "\nAcceptable? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";
            $pdoConn->commit();
            return $finalTagList;
        } else {
            echo "Okay. Rolling back changes...\n";
            $pdoConn->rollback();
            return false;
        }
    } else {
        echo "Error: could not insert records. Rolling back and returning...\n";
        $pdoConn->rollback();
        return false;
    }
    */
}

/**
 * Summary of Briel\insertCWAssociationsInteractive
 * Assumes `$pageRecord` is filled out and given a page ID
 * @param mixed $pageRecord
 * @param mixed $pdoConn
 * @return bool|string[]
 */
function insertCWAssociationsInteractive($pageRecord, $pdoConn) {
    return insertAssociationsInteractive($pageRecord, "contwarning", $pdoConn);
    /*
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting content warning assocation.";
        return false;
    }

    $existingCWs = $pdoConn->query("SELECT name FROM contwarning;")
                           ->fetchALL(\PDO::FETCH_COLUMN);
    $insertNewCW = $pdoConn->prepare("INSERT INTO contwarning (contwarningid, name) 
                                        VALUE (?,?);");
    $getCWIDFromName = $pdoConn->prepare("SELECT contwarningid FROM contwarning 
                                            WHERE name = ?;");

    $cwListStr = promptInput("Adding content warning associations with "
                                . "{$pageRecord['title']}...\n"
                                . "Type in comma-separated content warnings. Existing warnings:\n"
                                . implode("\t", $existingCWs)
                                . "\n> ");
    $cwList = array_map(fn($s) => trim($s), explode(",", $cwListStr));

    $cwExists = $pdoConn->prepare("SELECT EXISTS(
                                        SELECT name FROM contwarning 
                                        WHERE name = ?
                                   );");
    $cwAssocRecords = [];
    $finalCWList = [];
    foreach ($cwList as $contWarn) {

        if (!preg_match('([\w]+[-\w]*)', $contWarn)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the content warning. \nSkipping '$contWarn'.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!execAndFetchScalar($cwExists, $contWarn)) {
            if (promptInput("'$contWarn' not an existing content warning."
                            . "Add it? (y/n) > ") == "y") {
                $insertNewCW->execute([SQLNULL, $contWarn]);
            } else {
                echo "Okay, skipping '$contWarn'...\n";
                continue;
            }
        }
                            
        $cwAssocRecords[] = [execAndFetchScalar($getCWIDFromName, $contWarn), 
                             $pageRecord["pageid"]];
        $finalCWList[] = $contWarn;
    }

    if (queryInsertRecords($pdoConn, 
                           "contwarningpage", 
                           ["contwarningid", "pageid"], 
                           $cwAssocRecords)) {
        if (promptInput("Content warnings now associated with this page:\n"
                        . implode("\n", $finalCWList)
                        . "\nAcceptable? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";
            $pdoConn->commit();
            return $finalCWList;
        } else {
            echo "Okay. Rolling back changes...\n";
            $pdoConn->rollback();
            return false;
        }
    } else {
        echo "Error: could not insert records. Rolling back and returning...\n";
        $pdoConn->rollback();
        return false;
    }
    */
}

function upList($pageID,
                $getSource, 
                $delRowByTarget) {
    $getSource->execute([$pageID]);
    if ($sourceRec = $getSource->fetch(\PDO::FETCH_ASSOC)) {
        $delRowByTarget->execute([$pageID]);
        $aboveList = upList($sourceRec["sourceid"], 
                            $getSource, 
                            $delRowByTarget);
        $aboveList[] = $pageID;
        return $aboveList; // append
    } else {    // No entries where sourceid = $pageID
        return [$pageID];
    }
}

function downList($pageID, $getTarget, $delRowBySource) {
    $getTarget->execute([$pageID]);
    if ($targetRec = $getTarget->fetch(\PDO::FETCH_ASSOC)) {
        $delRowBySource->execute([$pageID]);
        $listBelow = downList($targetRec["targetid"], 
                                $getTarget, 
                                $delRowBySource);
        return array_unshift($listBelow, $pageID); // prepend
    } else {    // No entries where targetid = $pageID
        return [$pageID];
    }
}

/**
 * Summary of Briel\getPageOrderLists
 * @param mixed $pdoConn
 * @return array[]
 */
function getPageOrderLists($pdoConn) {
    $pdoConn->exec("CREATE TEMPORARY TABLE temppageorder 
                    AS SELECT * FROM pageorder;");

    $rowExists = $pdoConn->prepare("SELECT sourceid, targetid FROM temppageorder 
                                    LIMIT 1;");
    $getTarget = $pdoConn->prepare("SELECT targetid FROM temppageorder 
                                    WHERE sourceid = ?;");
    $getSource = $pdoConn->prepare("SELECT sourceid FROM temppageorder
                                    WHERE targetid = ?;");
    $delRowByTarget = $pdoConn->prepare("DELETE FROM temppageorder
                                            WHERE targetid = ?;");
    $delRowBySource = $pdoConn->prepare("DELETE FROM temppageorder
                                            WHERE sourceid = ?;");
    
    $orderLists = [];
    $rowExists->execute();
    while ($record = $rowExists->fetch(\PDO::FETCH_ASSOC)) {
        $orderLists[] = array_merge(upList($record["sourceid"],
                                           $getSource,
                                           $delRowByTarget), 
                                    downList($record["targetid"],
                                             $getTarget, 
                                             $delRowBySource)
                                    );
        $rowExists->execute();
    }

    $pdoConn->exec("DROP TEMPORARY TABLE temppageorder;");

    return $orderLists;
}

/**
 * Summary of Briel\insertPageAfter
 * @param mixed $prevPageID
 * @param mixed $pageID
 * @param mixed $pdoConn
 */
function insertPageAfter($prevPageID, $pageID, $pdoConn) {
    $getNext = $pdoConn->prepare("SELECT targetid FROM pageorder
                                    WHERE sourceid = ?;");
    $insert = $pdoConn->prepare("UPDATE pageorder SET targetid = :pageID 
                                    WHERE sourceid = :prevPageID;
                                 INSERT INTO pageorder 
                                    VALUE (:pageID, :nextPageID);");

    $nextPageID = execAndFetchScalar($getNext, $prevPageID);
    return $insert->execute([":pageID"     => $pageID, 
                             ":prevPageID" => $prevPageID, 
                             ":nextPageID" => $nextPageID]);
}

/**
 * Summary of Briel\deletePageAfter
 * @param mixed $pdoConn
 * @param mixed $pageID
 * @param mixed $prevPageID
 */
function deletePageAfter($pdoConn, $pageID, $prevPageID) {
    $getNext = $pdoConn->prepare("SELECT targetid FROM pageorder
                                    WHERE sourceid = ?;");

    $delPage = $pdoConn->prepare("SET @nextID = (
                                    SELECT targetid FROM pageorder
                                        WHERE sourceid = :pageID
                                  );   
                                  DELETE FROM pageorder 
                                    WHERE sourceID = :pageID;
                                  UPDATE pageorder SET targetID = @nextID
                                    WHERE sourceID = :prevID;
                                  ;");
    
    return $delPage->execute([":pageID" => $pageID, 
                              ":prevID" => $prevPageID]);
}

/**
 * Summary of Briel\appendPages
 * @param mixed $pageIDs
 * @param mixed $lastPageID
 * @param mixed $pdoConn
 */
function appendPages($pageIDs, $lastPageID, $pdoConn) {
    $records = [];
    $records[] = [$lastPageID, $pageIDs[0]];
    for ($i = 1; $i < \count($pageIDs); $i++) {
        $records[] = [$pageIDs[$i-1], $pageIDs[$i]];
    }
    return queryInsertRecords($pdoConn, 
                              "pageorder", 
                              ["sourceid", "targetid"],
                              $records);
}

function associateWithInteractive($record, 
                                  $table, 
                                  $tableID, 
                                  $srcTable, 
                                  $pdoConn) {
    echo "Associating {$table}s with {$record['title']}...\n";
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting assocation.\n";
        return false;
    }

    $recsToStrs = fn($recs) => array_map(fn($rec) => $rec['pageid'] 
                                                     . '----' 
                                                     . $rec['title'], 
                                         $recs);
    $sources = $pdoConn->query("SELECT {$srcTable}id, title FROM $srcTable;")
                     ->fetchAll(\PDO::FETCH_ASSOC);

    echo "\n---------------------------------\n$srcTable list:\nID-----Title/Location" 
         . implode("\n", $recsToStrs($sources)) 
         . "\n---------------------------------\n";
    
    $pdoConn->exec("CREATE TEMPORARY TABLE associd 
                    ({$srcTable}id int unsigned);");
    $inputStr = promptInput("Enter $srcTable IDs separated by commas, or \n"
                            . "a ? followed by a regular expression for titles."
                            . "\n> ");
    if (preg_match("((\d+,\s*)*\d+)", $inputStr)) {
        queryInsertRecords($pdoConn, 
                           "associd", 
                           "{$srcTable}id", 
                           array_map('trim', 
                                     explode(",", $inputStr)));

        if (!empty($notrealIDs = 
                    $pdoConn->query('SELECT * FROM associd 
                                     WHERE {$srcTable}id NOT IN (
                                        SELECT {$srcTable}id FROM file
                                     );')->fetchALL(\PDO::FETCH_COLUMN))) {
            echo "Error: page IDs [" 
                 . implode(', ', $notrealIDs)
                 . "] don't correspond to existing {$srcTable}s.\n"
                 . "Rolling back and returning...\n";
            $pdoConn->rollback();
            return false;
        }

    } else if ($inputStr[0] == "?") {
        $regExp = $pdoConn->prepare("INSERT INTO associd ({$srcTable}id)
                                        SELECT {$srcTable}id FROM file 
                                        WHERE REGEXP_LIKE(location, 
                                                          ?, 
                                                          'c');");
        $regExp->execute([substr($inputStr,1)]);

    } else {
        echo "'$inputStr' invalid. Rolling back and returning...";
        $pdoConn->rollback();
        return false;
    }

    $assocSelect = $pdoConn->query("SELECT {$srcTable}id, location FROM $srcTable
                                    WHERE {$srcTable}id IN (
                                        SELECT * FROM associd
                                    );");
    echo "{$srcTable}s to associate with {$record['title']}:\n" 
         . "ID-----Title/Location\n"
         . implode("\n", $recsToStrs($assocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "---------------------------------\n";

    if (promptInput("Okay to associate? (y/n) > ") == "y") {
        $updateAssoc = $pdoConn->prepare("UPDATE $srcTable SET $tableID = ?
                                          WHERE {$srcTable}id IN (
                                            SELECT * FROM associd
                                          );");
        $updateAssoc->execute([$record[$tableID]]);
        $srcIDs = $pdoConn->query('SELECT * FROM associd')
                           ->fetchAll(\PDO::FETCH_COLUMN);
        echo "Files associated.\n";
        if (promptInput("Roll back? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";
            $pdoConn->exec("DROP TEMPORARY TABLE associd;");    
            $pdoConn->commit();
            return $srcIDs;
        } else {
            echo "Okay. Rolling back changes...";
            $pdoConn->rollback();
            return false;
        }
        
    } else {
        echo "Okay. Rolling back and returning without associating...\n";
        $pdoConn->rollback();
        return false;
    }
}

function associatePagesWithUpdateInteractive($updateRecord, $pdoConn) {
    return associateWithInteractive($updateRecord, 
                                    "comicupdate", 
                                    "updateid", 
                                    "page", 
                                    $pdoConn);
}

/**
 * Summary of Briel\associateFilesWithPageInteractive
 * @param mixed $pdoConn
 * @param mixed $pageRecord
 */
function associateFilesWithPageInteractive($pageRecord, 
                                           $pdoConn) {
    return associateWithInteractive($pageRecord, 
                                    "page", 
                                    "pageid", 
                                    "file", 
                                    $pdoConn);
    /*
    echo "Associating files with {$pageRecord['title']}...\n";
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting page-file assocation.\n";
        return false;
    }

    $filesToStrs = fn($recs) => array_map(fn($rec) => $rec['fileid'] 
                                                      . '----' 
                                                      . $rec['location'], 
                                          $recs);
    $files = $pdoConn->query("SELECT fileid, location FROM file;")
                     ->fetchAll(\PDO::FETCH_ASSOC);

    echo "\n---------------------------------\nFile list:\nID-----Path" 
         . implode("\n", $filesToStrs($files)) 
         . "\n---------------------------------\n";
    
    $pdoConn->exec('CREATE TEMPORARY TABLE inputfileid 
                    (fileid int unsigned);');
    $inputStr = promptInput("Enter file IDs separated by commas, or \n"
                            . "a ? followed by a regular expression for paths."
                            . "\n> ");
    if (preg_match("((\d+,\s*)*\d+)", $inputStr)) {
        queryInsertRecords($pdoConn, 
                           "inputfileid", 
                           "fileid", 
                           array_map(fn($s) => trim($s), 
                                     explode(",", $inputStr)));
        
        if (!empty($notrealIDs = 
                    $pdoConn->query('SELECT * FROM inputfileid 
                                     WHERE fileid NOT IN (
                                        SELECT fileid FROM file
                                     );')->fetchALL(\PDO::FETCH_COLUMN))) {
            echo "Error: file IDs [" 
                 . implode(', ', $notrealIDs)
                 . "] don't correspond to existing files.\n"
                 . "Rolling back and returning...\n";
            $pdoConn->rollback();
            return false;
        }

    } else if ($inputStr[0] == "?") {
        $filesRegExp = $pdoConn->prepare("INSERT INTO inputfileid (fileid)
                                          SELECT fileid FROM file 
                                            WHERE REGEXP_LIKE(location, 
                                                              ?, 
                                                              'c');");
        $filesRegExp->execute([substr($inputStr,1)]);

    } else {
        echo "'$inputStr' invalid. Rolling back and returning...";
        $pdoConn->rollback();
        return false;
    }

    $assocSelect = $pdoConn->query('SELECT fileid, location FROM file
                                    WHERE fileid IN (
                                        SELECT * FROM inputfileid
                                    );');
    echo "Files to associate with {$pageRecord['title']}:\n" 
         . "ID-----Path\n"
         . implode("\n", $filesToStrs($assocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "---------------------------------\n";

    if (promptInput("Okay to associate? (y/n) > ") == "y") {
        $updateAssoc = $pdoConn->prepare('UPDATE file SET pageid = ?
                                          WHERE fileid IN (
                                            SELECT * FROM inputfileid
                                          );');
        $updateAssoc->execute([$pageRecord['pageid']]);
        $fileIDs = $pdoConn->query('SELECT * FROM inputfileid')
                           ->fetchAll(\PDO::FETCH_COLUMN);
        echo "Files associated.\n";
        if (promptInput("Roll back? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";
            $pdoConn->exec("DROP TEMPORARY TABLE inputfileid;");    
            $pdoConn->commit();
            return $fileIDs;
        } else {
            echo "Okay. Rolling back changes...";
            $pdoConn->rollback();
            return false;
        }
        
    } else {
        echo "Okay. Rolling back and returning without associating...\n";
        $pdoConn->rollback();
        return false;
    }
        */
}

function searchComics($searchStr, $pdoConn) {
    $tokens = explode(' ', $searchStr);
    $pdoConn->query();
}
?>