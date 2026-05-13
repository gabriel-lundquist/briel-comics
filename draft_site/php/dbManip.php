<?php
namespace Briel;

const SQLLOADFILENULL = '\N';
const SQLNULL = 'NULL';
const SQLSPACEREGEX = '[[:space:]]';

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
 * Summary of Briel\createDateFromSQLDateTime
 * @param mixed $dateStr
 * @return bool|\DateTimeImmutable
 */
function createDateFromSQLDateTime($dateStr) {
    return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr);
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
                            promptInput("Enter password: ") : getenv('MySQLBreelPassword'));
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
}

function searchComics($searchStr, 
                      $pdoConn, 
                      $matchExactly = false, 
                      $searchImgDesc = false) {
    $matchOp = $matchExactly ? '=' : 'REGEXP';

    // Split search string into unique tokens separated by spaces
    $tokens = array_unique(array_filter(explode(' ', trim($searchStr)), 
                                        fn($str) => (\count($str) > 0)));
    $excludeTokens = array_filter($tokens, 
                                    fn($str) => preg_match('/^-/', $str));
    $includeTokens = array_diff($tokens, $excludeTokens);

    // trim the leading `-`
    $excludeTokens = array_map(fn($str) => ltrim($str, '-'), 
                                $excludeTokens);

    $fieldSpec = fn($toks, $fieldName) => 
        array_map(fn($str) => substr($str, \strlen("$fieldName:")), 
                    array_filter($toks, fn($str) => str_starts_with($str, "$fieldName:")));
    
    $includeTokensByField = [];
    $excludeTokensByField = [];
    foreach (['tag', 'cw', 'title', 'day', 'month', 'year', 'description']
                as $field) {
        $includeTokensByField[$field] = $fieldSpec($includeTokens, $field);
        $excludeTokensByField[$field] = $fieldSpec($excludeTokens, $field);
    }
    $includeTokensNoField = array_diff($includeTokens, ...$includeTokensByField);
    $excludeTokensNoField = array_diff($excludeTokens, ...$excludeTokensByField);

    // Common table expressions defined below
    $tagMatch = fn($key) => "($key IN(SELECT token FROM tokentagpage))";
    $tagGeneralMatch = fn($key) => "($key IN(SELECT token FROM tagsearch))"; 
    $cwMatch = fn($key) => "($key IN(SELECT token FROM tokencwpage))";
    $cwGeneralMatch = fn($key) => "($key IN (SELECT token FROM cwsearch))";
    $titleExactMatch = fn($key) => 
        "title REGEXP '(^|\\\s)$key(\\\s|$)'";
    // SQL requires \\s to output \s, PHP requires \\\s to output \\s.
    $titleMatch = fn($key) => $matchExactly ? $titleExactMatch($key)
                                            : "title $matchOp $key";
    $dayNameMatch = fn($key) => "DAYNAME(postdate) = $key";
    $dayOfMonthMatch = fn($key) => "DAYOFMONTH(postdate) = $key";
    $monthMatch = fn($key) => "DAYOFMONTH(postdate) = $key";
    $yearMatch = fn($key) => "YEAR(postdate) = $key";
    $descMatch = fn($key) => "MATCH (imagedesc) AGAINST ($key)";

    $tokenClauses = [];
    $tokenDataSelects = ['title' => [], 
                         'dayOfMonth' => [], 
                         'year' => [], 
                         'month' => [], 
                         'dayName' => []];
    $joinTokens = [];

    $tokenExcludeClauses = [];
    $joinExcludeTokens = [];

    $keyIdx = 0;
    $tokenExecList = [];

    foreach ($includeTokensByField['tag'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        $tokenClauses[$token] = [$tagMatch($key)];
        $tokenDataSelects["tag$token"] = 
                $tagGeneralMatch($key) . "AS 'tag" . trim($key, ':') . "'";

        $joinTokens[$key] = $token;
    }

    foreach ($includeTokensByField['cw'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        $tokenClauses[$token] = [$cwMatch($key)];
        $tokenDataSelects["cw$token"] = 
                $cwGeneralMatch($key) . "AS 'cw" . trim($key, ':') . "'";

        $joinTokens[$key] = $token;
    }

    foreach ($includeTokensByField['title'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenClauses[$token] = [$titleMatch($key)];
        $tokenDataSelects['title'][] = $titleMatch($key);
    }

    foreach ($includeTokensByField['day'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenClauses[$token] = [$dayOfMonthMatch($key), 
                                    $dayNameMatch($key)];
        $tokenDataSelects['dayOfMonth'][] = $dayOfMonthMatch($key);
        $tokenDataSelects['dayName'][] = $dayNameMatch($key);
    }

    foreach ($includeTokensByField['month'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenClauses[$token] = [$monthMatch($key)];
        $tokenDataSelects['month'][] = $monthMatch($key);
    }

    foreach ($includeTokensByField['year'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenClauses[$token] = [$yearMatch($key)];
        $tokenDataSelects['year'][] = $yearMatch($key);
    }

    foreach ($includeTokensByField['description'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenClauses[$token] = [$descMatch($key)];
        $tokenDataSelects["desc$token"][] = $descMatch($key);
    }

    foreach ($includeTokensNoField as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        if (preg_match('/^\d{1,2}$/', $token)) { 
            $tokenClauses[$token] = [$titleExactMatch($key), 
                                     $dayOfMonthMatch($key)];

            $tokenDataSelects['title'] += $titleExactMatch($key);
            $tokenDataSelects['dayOfMonth'] += $dayOfMonthMatch($key);

            if ($searchImgDesc) {
                $tokenClauses[$token][] = $descMatch($key);
                $tokenDataSelects["desc$token"] = $descMatch($key);
            }

        } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
            $tokenClauses[$token] = [$titleExactMatch($key), 
                                     $yearMatch($key)];

            $tokenDataSelects['title'] += $titleExactMatch($key);
            $tokenDataSelects['year'] += $yearMatch($key);
            
            if ($searchImgDesc) {
                $tokenClauses[$token][] = $descMatch($key);
                $tokenDataSelects["desc$token"] = $descMatch($key);
            }

        } else if (\strlen($token) > 2) { 
            $tokenClauses[$token] = [$tagMatch($key), 
                                     $cwMatch($key),
                                     $titleMatch($key), 
                                     $monthMatch($key), 
                                     $dayNameMatch($key)];

            $tokenDataSelects["tag$token"] = 
                    $tagGeneralMatch($key) . "AS 'tag" . trim($key, ':') . "'";
            $tokenDataSelects["cw$token"] = 
                    $cwGeneralMatch($key) . "AS 'cw" . trim($key, ':') . "'";
            $tokenDataSelects['title'][] = $titleMatch($key);
            $tokenDataSelects['month'][] = $monthMatch($key);
            $tokenDataSelects['dayName'][] = $dayNameMatch($key);
            
            if ($searchImgDesc) {
                $tokenClauses[$token][] = $descMatch($key);
                $tokenDataSelects["desc$token"] = $descMatch($key);
            }

            $joinTokens[$key] = $token;
        }
        // If a token isn't numeric and is of length 2 or less, ignore it
    }

    foreach ($excludeTokensByField['tag'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        $joinExcludeTokens[$key] = $token;
    }

    foreach ($excludeTokensByField['cw'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        $joinExcludeTokens[$key] = $token;
    }

    foreach ($excludeTokensByField['title'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenExcludeClauses[$token] = [$titleMatch($key)];
    }

    foreach ($excludeTokensByField['day'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenExcludeClauses[$token] = [$dayOfMonthMatch($key), 
                                        $dayNameMatch($key)];
    }

    foreach ($excludeTokensByField['month'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenExcludeClauses[$token] = [$monthMatch($key)];
    }

    foreach ($excludeTokensByField['year'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenExcludeClauses[$token] = [$yearMatch($key)];
    }

    foreach ($excludeTokensByField['description'] as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;
        
        $tokenExcludeClauses[$token] = [$descMatch($key)];
    }

    foreach ($excludeTokensNoField as $token) {
        $tokenExecList[$key = ':t' . $keyIdx++] = $token;

        if (preg_match('/^\d{1,2}$/', $token)) { 
            $tokenExcludeClauses[$token] = [$titleExactMatch($key), 
                                            $dayOfMonthMatch($key)];
            if ($searchImgDesc) {
                $tokenExcludeClauses[$token][] = $descMatch($key);
            }

        } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
            $tokenExcludeClauses[$token] = [$titleExactMatch($key), 
                                            $yearMatch($key)];
            if ($searchImgDesc) {
                $tokenExcludeClauses[$token][] = $descMatch($key);
            }

        } else if (\strlen($token) > 2) { 
            $tokenExcludeClauses[$token] = [$titleMatch($key), 
                                            $monthMatch($key), 
                                            $dayNameMatch($key)];
            if ($searchImgDesc) {
                $tokenExcludeClauses[$token][] = $descMatch($key);
            }

            $joinExcludeTokens[$key] = $token;
        }
        // If a token isn't numeric and is of length 2 or less, ignore it
    }

    $joinSearch = $pdoConn->exec("CREATE TEMPORARY TABLE joinsearch;
                                    INSERT INTO joinsearch VALUES "
                                    . \implode(', ', 
                                                \array_map(fn($k) => "('$k')", 
                                                            )))

    $joinWithClause = 'joinsearch (token) AS (VALUES '
                        . \implode(', ', 
                                    \array_map(fn($k) => "ROW($k)", 
                                                \array_keys($joinTokens))
                                    )
                        . ')';

    $tagWithClause = 'tagsearch (token, tagid) AS (
                        SELECT token, tagid FROM 
                        joinsearch INNER JOIN tag '  
                        . "ON tag.name $matchOp joinsearch.token)";
    
    $cwWithClause = 'cwsearch (token) AS (
                        SELECT token, contwarningid FROM 
                        joinsearch INNER JOIN contwarning '  
                        . "ON contwarning.name $matchOp joinsearch.token)";

    // Must place in a WITH statement *after* a page has been selected
    $tagWithPageClause = 'tokentagpage (token) AS (
                                    SELECT DISTINCT token FROM tagsearch
                                INNER JOIN 
                                    SELECT tagid FROM tagpage 
                                        WHERE tagpage.pageid = page.pageid
                                USING (tagid)
                            )';
    
    // Must place in a WITH statement *after* a page has been selected
    $cwWithPageClause = 'tokencwpage (token) AS (
                                    SELECT DISTINCT token FROM cwsearch
                                INNER JOIN
                                    SELECT contwarningid FROM contwarningpage 
                                        WHERE contwarningpage.pageid = page.pageid
                                USING (contwarningid)
                            )';

    $joinExcludeWithClause = 'joinsearchexclude (token) AS (VALUES '
                            . \implode(', ', 
                                        \array_map(fn($k) => "ROW($k)", 
                                                    \array_keys($joinExcludeTokens))
                                        )
                            . ')';
    
    $tagExcludeWithClause = 'tagsearchexclude (token, tagid) AS (
                                SELECT token, tagid FROM 
                                joinsearchexclude INNER JOIN tag '  
                                . "ON tag.name $matchOp joinsearchexclude.token)";
    
    $cwExcludeWithClause = 'cwsearchexclude (token) AS (
                            SELECT token, contwarningid FROM 
                            joinsearchexclude INNER JOIN contwarning '  
                            . "ON contwarning.name $matchOp joinsearchexclude.token)";
    
    $whereSelectClause = \implode(' AND ', 
                                    array_map(fn($tarr) => 
                                                    '(' 
                                                    . \implode(' OR ', 
                                                                $tarr)
                                                    . ')', 
                                                $tokenClauses)
                                    );

    $whereNotSelectClause = \implode(' OR ', 
                                        array_map(fn($tarr) => 
                                                        \implode(' OR ', $tarr), 
                                                    $tokenExcludeClauses)
                                    )
                            . ' OR EXISTS(
                                        SELECT tagid FROM tagsearchexclude
                                    INNER JOIN
                                        SELECT tagid FROM tagpage
                                            WHERE tagpage.pageid = page.pageid
                                    USING (tagid)
                                ) OR EXISTS(
                                        SELECT contwarningid FROM cwsearchexclude
                                    INNER JOIN
                                        SELECT contwarningid FROM contwarningpage
                                            WHERE contwarningpage.pageid = page.pageid
                                    USING (contwarningid)
                                )';

    $selectMatchClause = '(' . \implode(' OR ', $tokenDataSelects['title']) 
                            . ') AS titlematch,' 
                        . '(' . \implode(' OR ', $tokenDataSelects['dayOfMonth']) 
                            . ') AS dayofmonthmatch, '
                        . '(' . \implode(' OR ', $tokenDataSelects['year'])
                            . ') AS yearmatch, '
                        . '(' . \implode(' OR ', $tokenDataSelects['month'])
                            . ') AS monthmatch, '
                        . '(' . \implode(' OR ', $tokenDataSelects['dayName'])
                            . ') AS daynamematch, '
                        . \implode(', ', 
                                    \array_filter($tokenDataSelects, 
                                                    fn($key) => str_starts_with($key, 'tag')
                                                                or str_starts_with($key, 'cw')
                                                                or str_starts_with($key, 'desc'), 
                                                    ARRAY_FILTER_USE_KEY)
                                    )
                        ;

    $pages = $pdoConn->prepare("WITH $joinWithClause, 
                                     $tagWithClause, 
                                     $cwWithClause, 
                                     $joinExcludeWithClause, 
                                     $tagExcludeWithClause, 
                                     $cwExcludeWithClause 
                                SELECT pageid, $selectMatchClause FROM page
                                WHERE (
                                    WITH $tagWithPageClause, 
                                        $cwWithPageClause
                                    SELECT (NOT ($whereNotSelectClause))
                                        AND $whereSelectClause
                                );"
                                );

    $pages->execute($tokenExecList);

    $results = $pages->fetchAll(\PDO::FETCH_ASSOC);

    return [$results, 
            $tokenExecList, 
            $includeTokensNoField, 
            $includeTokensByField];
}
?>