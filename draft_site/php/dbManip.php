<?php
namespace Briel;

const DEBUG = true;

const SQLLOADFILENULL = '\N';
const SQLNULL = 'NULL';
const SQLSPACEREGEX = '[[:space:]]';

const FILECOLUMNS = [   "fileid",
                        "location",
                        "width",
                        "height",
                        "alttext",
                        "uploaddate", 
                        "modifydate", 
                        "filetype",
                        "filesize",
                        "pageid",
                        "ratioid"   ];

const FILEINSERTCOLUMNS = [ FILECOLUMNS[1], //location
                            FILECOLUMNS[2], //width
                            FILECOLUMNS[3], //height
                            FILECOLUMNS[4], //alttext
                            FILECOLUMNS[7], //filetype
                            FILECOLUMNS[8], //filesize
                            FILECOLUMNS[9], //pageid
                            FILECOLUMNS[10] ]; //ratioid

const PAGEINSERTCOLUMNS = [ "title",
                            "location",
                            "imagedesc",
                            "spreadid",
                            "stylelocation" ];

const SEARCHFIELDSPECS = [  'tag', 
                            'cw', 
                            'title', 
                            'day', 
                            'month', 
                            'year', 
                            'description'   ];

const TEXTMATCHTHRESHOLD = 0.1;

/**
 * Summary of Briel\promptInput
 * @param mixed $prompt
 * @return string
 */
function promptInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

function promptPathHTML() {
    return promptInput("Enter HTML file path (doesn't need to exist):\n> ");
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
function pdoConnect($dbhost = 'localhost', 
                    $dbuser = 'root', 
                    $dbname = 'briel_comics_test', 
                    $dbport = 3307, 
                    $enterPassword = false,
                    $echoConnSuccess = DEBUG ) {
    try {
        $conn = new \PDO("mysql:host=$dbhost;
                          dbname=$dbname;
                          port=$dbport", 
                         $dbuser, 
                         $enterPassword ? 
                            promptInput("Enter password: ") : getenv('COMIC_DB_PASSWORD'));
        $conn->setAttribute(\PDO::ATTR_ERRMODE, 
                            \PDO::ERRMODE_EXCEPTION);
        if ( $echoConnSuccess ) echo "Connected successfully.\n";
        return $conn;
    } catch (\PDOException $e) {
        if ( $echoConnSuccess ) echo "Connection failed.\n" . $e->getMessage();
        return false;
    }
}

function dumpQuery($query, $pdoConn) {
    var_dump($pdoConn->query($query)->fetchAll(PDO::FETCH_ASSOC));
}

function tryBeginTransaction($pdoConn) {
    try {
        $pdoConn->beginTransaction();
    } catch (\PDOException $e) {
        if (!$pdoConn->inTransaction()) {
            echo $e->getMessage() 
                . "\n---\nCan't begin transaction. Aborting assocation.\n";
            return false;
        }
        // otherwise, we're already in a transaction
    }

    return true;
}

function checkCommit($pdoConn) {
    if ($pdoConn->inTransaction()) {
        echo "\nIf you don't commit now, this change may be rolled back.\n";
        if (promptInput("Commit transaction? (y/n) > ") == "y") {
            $pdoConn->commit();
            return true;
        }

    }

    return false;
}

/**
 * Summary of Briel\execAndFetch
 * @param mixed $statement
 * @param mixed $executeArr
 * @param mixed $mode
 */
function execAndFetch($statement, 
                        $executeArr = NULL, 
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
                            $execVar = NULL) {
    if ($execVar !== NULL) $execVar = [$execVar];
    return $statement->execute($execVar) 
            ? $statement->fetch(\PDO::FETCH_NUM)[0] : false;
}

function rowPlaceholder($n) {
    return '(' . implode(',', array_fill(0, $n, '?')) . ')';
}

function recordsToAttrRowStrs($records, 
                                $attrIDKey, 
                                $attrNameKey, 
                                $padLen = 8) {
    return array_map(fn($record) => str_pad($record[$attrIDKey], 
                                            $padLen, 
                                            '_')
                                    . $record[$attrNameKey], 
                        $records);
}

function valueRows($arr) {
    return 'VALUES ' . \implode(', ', \array_map(fn($a) => "ROW($a)", $arr));
}

function attributeTableStr($records, 
                            $tableTitle, 
                            $attrIDKey, 
                            $attrNameKey, 
                            $rowPadLen = 8, 
                            $tableSpacerLen = 42) {

    return "\n" . str_repeat('-', $tableSpacerLen) 
            . "\n" . $tableTitle 
            . "\n" . str_pad('ID', $rowPadLen) . 'Name/Title/Location'
            . "\n" . implode("\n", recordsToAttrRowStrs($records, 
                                                        $attrIDKey, 
                                                        $attrNameKey, 
                                                        $rowPadLen))
            . "\n" . str_repeat('_', $tableSpacerLen) . "\n";
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
    echo "Generating record for `" . basename($filePath) . '`...';
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

    // this will give you a different size than what Windows tells you, 
    // since Windows defines 1 KB = 1024 B, not 1 KB = 1000 B like usual.
    $fileSizeKB = (int) (filesize($filePath) / 1000);

    $ratioStatement = $pdoConn->prepare("SELECT ratioid FROM aspectratio 
                                            WHERE ratio = ?;");
    $fetchedRow = NULL;
    if (!$ratioStr) {
        $ratioStr = promptInput("Enter aspect ratio (format width:height)\n"
                                . "> ");
    }
    // if execution is unsuccessful or there is no row in the selection
    if (!($ratioStatement->execute([$ratioStr]))
            or !($fetchedRow = $ratioStatement->fetch())) {
        do {
            $ratioStr = promptInput("$ratioStr not a valid ratio.\n"
                                    . "Enter aspect ratio (format width:height)\n"
                                    . "> ");
        } while (!$ratioStatement->execute([$ratioStr])
                 or !($fetchedRow = $ratioStatement->fetch()));
    }
    $ratioID = $fetchedRow[0];

    return array_combine(FILEINSERTCOLUMNS, [$absPath, 
                                                $imgWidth, 
                                                $imgHeight, 
                                                $alttext, 
                                                $fileExt, 
                                                $fileSizeKB, 
                                                $ratioID]);
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
    
    $allPlaceholderStr = \is_array($records) ? 
            \implode(",\n", 
                     array_fill(0, 
                                \count($records), 
                                rowPlaceholder(\is_array($insertColumns) ? 
                                                    \count($insertColumns)
                                                    : 1)))
            : '?';
    
    return $pdoConn->prepare("INSERT INTO $tableName ("
                             . (\is_array($insertColumns) ? 
                                        implode(", ", $insertColumns)
                                        : $insertColumns) 
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
                              $debugTransaction = DEBUG) {
    try {
        $pdoConn->beginTransaction();
    } catch (\PDOException $e) {
        if ($debugTransaction) echo $e->getMessage() . "\n";
        if (!$pdoConn->inTransaction()) return false;
    }

    if (\is_array($records)) {
        for ($placeIdx = 1, $i = 0; $i < \count($records); $i++) {
            if (\is_array($records[$i])) {
                foreach ($records[$i] as $field) {
                    $insertStatement->bindValue($placeIdx, $field);
                    $placeIdx++;
                }
            }
            else {
                $insertStatement->bindValue($placeIdx, $records[$i]);
                $placeIdx++;
            }
        }
    } else $insertStatement->bindValue(1, $records);
    
    $execStatus = $insertStatement->execute();
    if ($debugTransaction) {
        echo "Statement affected {$insertStatement->rowCount()} rows.\n"
                . "If you don't commit now, statement may be rolled back.\n";
        if (promptInput("Commit? (y/n) > ") == "y") $pdoConn->commit();
    } else $pdoConn->commit();

    return $execStatus;
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
    tryBeginTransaction($pdoConn);

    $queryResult = queryInsertRecords($pdoConn, 
                                      'page',
                                      PAGEINSERTCOLUMNS, 
                                      $records);
    echo "Query result is:\n";
    var_dump($queryResult);
    checkCommit($pdoConn);
    return $queryResult;
}

function insertNewTags($tags, $pdoConn) {
    tryBeginTransaction($pdoConn);
    
    $tags = array_unique($tags);
    $matchExistingTags = 
            $pdoConn->prepare("SELECT * FROM tag
                                WHERE name IN "
                                . rowPlaceholder(\count($tags))
                                . ";");
    $matchExistingTags->execute($tags);
    $existingTags = $matchExistingTags->fetchAll(\PDO::FETCH_ASSOC);
    echo attributeTableStr($existingTags, 
                            "These tags are already present:", 
                            'tagid', 
                            'name');
    if (!(promptInput("Add repeat tags anyway? (y/n) > ") == 'y')) {
        $tags = array_diff($tags, 
                            array_column($existingTags, 'name'));
    }

    echo "Inserting new tags:\n" . implode(', ', $tags) . "\n";

    $queryResult = queryInsertRecords($pdoConn,
                                        'tag', 
                                        'name', 
                                        array_map(fn($tag) => [$tag], 
                                                    $tags));
    // queryResult() already checks for commit.
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
        // FETCH_KEY_PAIR writes location values into an array indexed by width
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
    return preg_match('(.*[\\\/].+\.' . "$fileExt)", $str);
}

/**
 * Summary of Briel\generatePageRecordInteractive
 * @param mixed $pdoConn
 * @return array{imagedesc: bool|string, location: string, 
 *               pageid: string, spreadid: mixed, 
 *               stylelocation: string, title: string}
 */
function generatePageRecordInteractive($pdoConn) {
    echo "Generating new page record...\n";
    
    // Set title
    $title = promptInput("Enter title: > ");
    $findFileFromTitle = $pdoConn->prepare("SELECT location FROM page 
                                            WHERE title = ?;");
    $existingTitle = $pdoConn->prepare("SELECT EXISTS(
                                            SELECT title FROM page
                                            WHERE title = ?);");                                 
    $useAnyway = false;
    while (execAndFetchScalar($existingTitle, $title) AND !$useAnyway) {
        echo "Warning: `$title` already exists in page at "
             . execAndFetchScalar($findFileFromTitle, $title)
             . "\n";
        if (promptInput("Use this title anyway? (y/n) > ") == "y") {
            $useAnyway = true;
        } else $title = promptInput("Enter title: > ");
    }
    
    // Set location
    $useAnyway = false;
    $location = promptPathHTML();
    while (!is_file_path($location, "html") 
           OR (file_exists($location) AND !$useAnyway)) {
        if (!is_file_path($location, "html")) {
            echo "Error: `$location` is not a path for an HTML file.\n";
            $location = promptPathHTML();
        } else if (file_exists($location)) {
            if (promptInput("Warning: `$location` already exists.\n"
                            . "Use it anyway? (y/n) > ") == "y") {
                $useAnyway = true;
            } else $location = promptPathHTML();
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

    // pageid is automatically generated upon inserting these values.
    return array_combine(PAGEINSERTCOLUMNS, [$title, 
                                             $location, 
                                             $imageDesc, 
                                             $spreadID, 
                                             $styleLocation]);
}

function loadPageRecordsInteractive($pdoConn) {
    tryBeginTransaction($pdoConn);

    echo "Add a page.\n";
    $records = [];
    do {
        $record = generatePageRecordInteractive($pdoConn);
        $records[] = $record;
        insertPageRecords([$record], $pdoConn);
    } while (promptInput("Add another page? (y/n) > ") == 'y');

    if ($pdoConn->inTransaction()) {
        echo "\nFinished adding pages. If you don't commit now, "
            . "this change may be rolled back.\n";
        if (promptInput("Commit transaction? (y/n) > ") == "y") 
            $pdoConn->commit();
    }

    return $records;
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
    } elseif ($table != 'tag' AND $table != 'contwarning') {
        echo "'$table' not a valid table.";
        return false;
    }

    $existing = $pdoConn->query("SELECT name FROM $table;")
                            ->fetchAll(\PDO::FETCH_COLUMN);
    $insertNew = $pdoConn->prepare("INSERT INTO $table (name) VALUE (?);");
    $getIDFromName = $pdoConn->prepare("SELECT {$table}id FROM $table WHERE name = ?;");

    $listStr = promptInput("Adding $table associations with {$pageRecord['title']}...\n"
                                . "Type in comma-separated {$table}s. Existing {$table}s:\n"
                                . implode(",\t", $existing)
                                . "\n> ");
    
    $exists = $pdoConn->prepare("SELECT EXISTS(
                                    SELECT name FROM $table 
                                    WHERE name = ?
                                 );");
    $assocRecords = [];
    $finalList = [];
    $tieList = array_map('trim', explode(",", $listStr));
    foreach ($tieList as $tie) {
        if (!preg_match('/^\w[-\w]*$/', $tie)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the $table. \nSkipping $tie.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!execAndFetchScalar($exists, $tie)) {
            if (promptInput("$tie not an existing $table. Add it? (y/n) > ") == "y") {
                $insertNew->execute([$tie]);
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
        if (promptInput("{$table}s now associated with this page:\n\t"
                        . implode(", ", $finalList)
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
}

function upList($pageID,
                $getSource, 
                $delRowByTarget) {
    $getSource->execute([$pageID]);
    if ($sourceRec = $getSource->fetch(\PDO::FETCH_ASSOC)) {
        $delRowByTarget->execute([$pageID]);
        $listAbove = upList($sourceRec["sourceid"], 
                            $getSource, 
                            $delRowByTarget);
        $listAbove[] = $pageID; // append
        return $listAbove; 
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
        array_unshift($listBelow, $pageID); // prepend
        return $listBelow;
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
    
    // May have multiple separate path graphs (chains of pages)
    // so we have lists rather than a single list
    $orderLists = [];
    $rowExists->execute();
    while ($record = $rowExists->fetch(\PDO::FETCH_ASSOC)) {
        $orderLists[] = [...upList($record['sourceid'],
                                    $getSource,
                                    $delRowByTarget), 
                            ...downList($record['targetid'],
                                        $getTarget, 
                                        $delRowBySource)];
        $delRowBySource->execute([$record['sourceid']]);
        $rowExists->execute();
    }

    $pdoConn->exec("DROP TEMPORARY TABLE temppageorder;");

    return $orderLists;
}

function getEndOfPageOrderID($pageID, $pdoConn) {
    $getTarget = $pdoConn->prepare('SELECT targetid FROM pageorder
                                    WHERE sourceid = ?;');
    $getTarget->execute($pageID);
    $lastTargetID = $pageID;
    while (($lastTarget = $getTarget->fetch()) !== false) {
        $lastTargetID = $lastTarget['targetid'];
        $getTarget->execute($lastTargetID);
    }

    return $lastTargetID;
}

/**
 * Summary of Briel\insertPageAfter
 * @param mixed $prevPageID
 * @param mixed $newPageID
 * @param mixed $pdoConn
 */
function insertPageAfter($prevPageID, $newPageID, $pdoConn) {
    $getNext = $pdoConn->prepare("SELECT targetid FROM pageorder
                                    WHERE sourceid = ?;");
    $nextPageID = execAndFetchScalar($getNext, $prevPageID);
    
    $insert = $pdoConn->prepare("UPDATE pageorder SET targetid = :newPageID 
                                    WHERE sourceid = :prevPageID;
                                 INSERT INTO pageorder 
                                    VALUE (:pageID, :nextPageID);");
    return $insert->execute([":newPageID"  => $newPageID, 
                             ":prevPageID" => $prevPageID, 
                             ":nextPageID" => $nextPageID]);
}

/**
 * Summary of Briel\insertPageBefore
 * @param mixed $nextPageID
 * @param mixed $newPageID
 * @param mixed $pdoConn
 */
function insertPageBefore($nextPageID, $newPageID, $pdoConn) {
    $getPrev = $pdoConn->prepare("SELECT sourceid FROM pageorder
                                    WHERE targetid = ?;");
    $prevPageID = execAndFetchScalar($getPrev, $nextPageID);

    $insert = $pdoConn->prepare("UPDATE pageorder SET sourceid = :newpageID 
                                    WHERE targetid = :nextPageID;
                                 INSERT INTO pageorder 
                                    VALUE (:prevPageID, :newPageID);");
    return $insert->execute([":newpageID"  => $newPageID, 
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

/**
 * Summary of Briel\associateWithInteractive
 * 
 * Associates tags with pages, contwarnings with pages, files with pages, 
 * or pages with updates
 * @param mixed $record A page or update record.
 * @param mixed $leafTableType Can be 'tag', 'contwarning', 'file', or 'page'
 * @param mixed $pdoConn
 */
function associateInteractive($record,
                                $leafTableType, 
                                $pdoConn) {
    if (!tryBeginTransaction($pdoConn)) return false;

    echo "Associating {$leafTableType}s with `{$record['title']}`...\n";

    $leafName = NULL;
    $leafIDName = "{$leafTableType}id";
    $leafTableAssocName = NULL;
    $rootName = NULL;
    $rootIDName = NULL;
    $rootTableName = NULL;
    switch ($leafTableType) {
        case 'tag': 
        case 'contwarning':
            $leafName = 'name';
            $leafTableAssocName = "{$leafTableType}page";
            $rootName = 'title';
            $rootIDName = 'pageid';
            $rootTableName = 'page';
            break;
        case 'file':
            $leafName = 'location';
            $leafTableAssocName = 'file';
            $rootName = 'title';
            $rootIDName = 'pageid';
            $rootTableName = 'page';
            break;
        case 'page':
            $leafName = 'title';
            $leafTableAssocName = 'comicupdatepage';
            $rootName = 'title';
            $rootIDName = 'updateid';
            $rootTableName = 'comicupdate';
            break;
        default: 
            echo "`$leafTableType` isn't associatable data. 
                    Aborting association.\n";
            return false;
    }

    $recsToStrs = fn($recs) => 
                        array_map(fn($rec) => str_pad($rec[$leafIDName], 
                                                        8, 
                                                        '_')
                                                . $rec[$leafName], 
                                    $recs);
    $leaves = $pdoConn->query("SELECT $leafIDName, $leafName 
                                FROM $leafTableType;")
                        ->fetchAll(\PDO::FETCH_ASSOC);

    echo attributeTableStr($leaves, 
                            "$leafTableType list:", 
                            $leafIDName, 
                            $leafName);
    
    $pdoConn->exec("CREATE TEMPORARY TABLE associd 
                    ($leafIDName int unsigned);");
    $inputStr = promptInput("Enter {$leafTableType} IDs separated by commas, "
                            . "or a ? followed by a regular expression for "
                            . "$leafTableType names/titles.\n> ");
    if (preg_match("/^(\d+,\s*)*\d+$/", $inputStr)) {
        queryInsertRecords($pdoConn, 
                           "associd", 
                           $leafIDName, 
                           array_map('trim', explode(",", $inputStr)));

        if (!empty($notRealIDs = 
                        $pdoConn->query("TABLE associd 
                                            EXCEPT
                                            SELECT $leafIDName
                                                FROM $leafTableType;")
                                ->fetchALL(\PDO::FETCH_COLUMN))) {
            echo "Error: page IDs [" 
                 . implode(', ', $notRealIDs)
                 . "] don't correspond to existing {$leafTableType}s.\n"
                 . "Rolling back and returning...\n";
            $pdoConn->rollback();
            return false;
        }

    } else if (\strlen($inputStr) > 0 AND $inputStr[0] == "?") {
        $regExp = $pdoConn->prepare("INSERT INTO associd ($leafIDName)
                                        SELECT $leafIDName 
                                        FROM $leafTableType
                                        WHERE REGEXP_LIKE($leafName, ?, 'c')
                                        ;");
                                    // 'c' enforces case-sensitivity
        $regExp->execute([substr($inputStr,1)]);

    } else {
        echo "'$inputStr' invalid.\n";
        if (promptInput("Rollback changes? (y/n) > ") == 'y') {
            echo "Rolling back...\n";
            $pdoConn->rollback();
        }
        echo "Returning...\n";
        return false;
    }
    
    $assocSelect = 
            $pdoConn->prepare("SELECT $leafIDName, $leafName 
                                FROM (
                                    SELECT $leafIDName
                                    FROM associd INNER JOIN $leafTableType
                                        USING ($leafIDName)
                                    EXCEPT 
                                    SELECT $leafIDName
                                    FROM $leafTableAssocName
                                        WHERE $rootIDName = ?
                                ) AS t INNER JOIN $leafTableType
                                    USING ($leafIDName);");
    $assocSelect->execute([$record[$rootIDName]]);

    $alreadyAssocSelect = 
            $pdoConn->prepare("SELECT $leafIDName, $leafName 
                                FROM (
                                    SELECT $leafIDName
                                    FROM associd INNER JOIN $leafTableType
                                        USING ($leafIDName)
                                    INTERSECT 
                                    SELECT $leafIDName
                                    FROM $leafTableAssocName
                                        WHERE $rootIDName = ?
                                ) AS t INNER JOIN $leafTableType
                                    USING ($leafIDName);");
    $alreadyAssocSelect->execute([$record[$rootIDName]]);

    echo "\n-----------------------------------------\n"
         . "{$leafTableType}s to associate with `{$record[$rootName]}`:\n" 
         . "ID      Name/Title/Location\n"
         . implode("\n", 
                    $recsToStrs($assocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "\n-----------------------------------------\n"
         . "{$leafTableType}s already associated with `{$record[$rootName]}`:\n" 
         . "ID      Name/Title/Location\n"
         . implode("\n", 
                    $recsToStrs($alreadyAssocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "\n_________________________________________\n";

    $trimExistingLinks = $pdoConn->prepare("DELETE FROM associd 
                                            WHERE $leafIDName IN( 
                                                SELECT $leafIDName
                                                FROM $leafTableAssocName
                                                WHERE $rootIDName = ?
                                            );");
    $trimExistingLinks->execute([$record[$rootIDName]]);

    if (promptInput("Okay to associate? (y/n) > ") == "y") {
        if ($leafTableType == 'file') {
            $assoc = $pdoConn->prepare("UPDATE $leafTableAssocName
                                        SET $rootIDName = ?
                                        WHERE $leafIDName IN(
                                            TABLE associd
                                        );");
            
        } else {
            $assoc = $pdoConn->prepare("INSERT INTO $leafTableAssocName 
                                            ($rootIDName, $leafIDName)
                                        SELECT ?, $leafIDName
                                            FROM associd;");
        }
        $assoc->execute([$record[$rootIDName]]);
        echo "Files associated.\n";
        $leafIDs = $pdoConn->query('TABLE associd;')
                            ->fetchAll(\PDO::FETCH_COLUMN);
        $pdoConn->exec("DROP TEMPORARY TABLE associd;"); 
        if (promptInput("Commit changes? (y/n) > ") == "y") {
            echo "Sick. Committing...\n";   
            $pdoConn->commit();
        } else {
            echo "Okay. Returning...\n";
        }
        return $leafIDs;
    
    } else {
        echo "Okay. Returning without associating...\n";
        return false;
    }
}

/**
 * Summary of Briel\associateTagsInteractive
 * Associates tags with the given page
 * @param mixed $pageRecord
 * @param mixed $pdoConn
 */
function associateTagsInteractive($pageRecord, $pdoConn) {
    return associateInteractive($pageRecord, 'tag', $pdoConn);
}

/**
 * Summary of Briel\associateCWsInteractive
 * Associates content warnings with the given page
 * @param mixed $pageRecord
 * @param mixed $pdoConn
 */
function associateCWsInteractive($pageRecord, $pdoConn) {
    return associateInteractive($pageRecord, 'contwarning', $pdoConn);
}

/**
 * Summary of Briel\associatePagesInteractive
 * Associates pages with the given update
 * @param mixed $updateRecord
 * @param mixed $pdoConn
 */
function associatePagesInteractive($updateRecord, $pdoConn) {
    return associateInteractive($updateRecord, 'page', $pdoConn);
}

/**
 * Summary of Briel\associateFilesInteractive
 * Associates files with the given page
 * @param mixed $pageRecord
 * @param mixed $pdoConn
 */
function associateFilesInteractive($pageRecord, $pdoConn) {
    return associateInteractive($pageRecord, 'file', $pdoConn);
}

/**
 * Summary of Briel\appendGeneratePages
 * @param mixed $pdoConn
 * @param mixed $existingPageID
 * @return array
 */
function appendGeneratePages($pdoConn, 
                                $existingPageID = NULL) {
    if ($existingPageID === NULL) {
        $existingPageID = execAndFetchScalar('SELECT targetid 
                                                FROM pageorder 
                                                LIMIT 1;');
    }

    $lastPageID = getEndOfPageOrderID($existingPageID, $pdoConn);

    tryBeginTransaction(($pdoConn));

    $records = [];
    echo "Generating pages...\n";
    do {
        $record = generatePageRecordInteractive($pdoConn);
        queryInsertRecords($pdoConn, 'page', PAGEINSERTCOLUMNS, [$record]);
        $record = execAndFetch('SELECT * FROM page WHERE pageid = (
                                    SELECT MAX(pageid) FROM page 
                                    WHERE title = ?
                                );', 
                                [$record['title']]);

        if (promptInput("Associate files? (y/n) > " == 'y'))
            associateFilesInteractive($record, $pdoConn);

        if (promptInput("Associate tags? (y/n) > " == 'y'))
            associateTagsInteractive($record, $pdoConn);

        if (promptInput("Associate content warnings? (y/n) > " == 'y'))
            associateCWsInteractive($record, $pdoConn);

        appendPages([$record], $lastPageID, $pdoConn);
        $lastPageID = $record['pageid'];

        $records[] = $record;
    } while (promptInput("Generate another page? (y/n) > " == 'y'));

    checkCommit($pdoConn);

    return $records;
}

function defineSearchMatches($matchExactly, $matchOp) {
    $coalesceNulls = fn($field, $str) => "COALESCE($str, $field IS NOT NULL)";
    $tagMatch = fn($key) => "$key IN(SELECT DISTINCT token FROM 
                                        tagsearch INNER JOIN tagpage USING (tagid)
                                        WHERE tagpage.pageid = page.pageid)"; 
    $cwMatch = fn($key) => "$key IN(SELECT DISTINCT token FROM 
                                        cwsearch INNER JOIN contwarningpage 
                                            USING (contwarningid)
                                        WHERE contwarningpage.pageid = page.pageid)"; 
    // SQL requires \\s to output \s, PHP requires \\\s to output \\s.
    $titleExactMatch = fn($key) => $coalesceNulls('title', 
                                    "title REGEXP CONCAT('(^|[-\"\\'*(\\\[\\\s])', 
                                                        $key, 
                                                        '([-\"\\'*)\\\]\\\s!:;,.?]|$)')");
    $titleMatch = $matchExactly ? $titleExactMatch
                                : fn($key) => $coalesceNulls('title', "title $matchOp $key");
    $dayNameMatch = fn($key) => $coalesceNulls('postdate', "DAYNAME(postdate) = $key");
    $dayOfMonthMatch = fn($key) => $coalesceNulls('postdate', "DAYOFMONTH(postdate) = $key");
    $monthMatch = fn($key) => $coalesceNulls('postdate', "MONTHNAME(postdate) = $key");
    $yearMatch = fn($key) => $coalesceNulls('postdate', "YEAR(postdate) = $key");
    $descExactMatch = fn($key) => $coalesceNulls('imagedesc', 
                        "imagedesc REGEXP CONCAT('(^|[-\"\\'*(\\\[\\\s])', 
                                                $key, 
                                                '([-\"\\'*)\\\]\\\s!:;,.?]|$)')");
    $descMatch = $matchExactly ? $descExactMatch
                                : fn($key) => 
                                    $coalesceNulls( 'imagedesc', 
                                                    "MATCH (imagedesc) AGAINST ($key) > " 
                                                        . TEXTMATCHTHRESHOLD);
    
    return [$tagMatch, 
            $cwMatch, 
            $titleExactMatch, 
            $titleMatch, 
            $dayNameMatch, 
            $dayOfMonthMatch, 
            $monthMatch, 
            $yearMatch, 
            $descExactMatch, 
            $descMatch];
}

function generateSearchClauses( $searchStr, 
                                $matchExactly, 
                                $searchImgDesc,
                                $tagMatch, 
                                $cwMatch, 
                                $titleExactMatch, 
                                $titleMatch, 
                                $dayNameMatch, 
                                $dayOfMonthMatch, 
                                $monthMatch, 
                                $yearMatch, 
                                $descExactMatch, 
                                $descMatch  ) {
    $tokenClauses = [];
    $tokenDataSelects = array_fill_keys([   'title', 
                                            'dayOfMonth', 
                                            'year', 
                                            'month', 
                                            'dayName'   ], 
                                        []);
    $joinTokens = [];
    $tagTokens = [];
    $cwTokens = [];

    $joinExcludeTokens = [];
    $tagExcludeTokens = [];
    $cwExcludeTokens = [];
    $tokenExcludeClauses = [];

    $execList = [];

    $imgDescMatchPhrase = [];

    $whitespaces = " \n\t\r";
    $token = strtok($searchStr, $whitespaces);
    $allTokens = [];
    for ($keyIdx = 0; $token !== false; $token = strtok($whitespaces)) {
        if (\in_array($token, $allTokens)) continue;
        $allTokens[] = $origToken = $token;
        $key = ':t' . $keyIdx++;

        $include = true;
        if (str_starts_with($token, '-')) {
            $include = false;
            $token = substr($token, 1);
        }

        if (preg_match('/^('. \implode('|', SEARCHFIELDSPECS) . '):.+/', $token)) {
            $colonPos = strpos($token, ':');
            $prefix = substr($token, 0, $colonPos);
            $token = substr($token, $colonPos + 1);
            $execList[$key] = $token;

            if ($include) {
                switch ($prefix) {
                    case 'tag':
                        // Don't need match selects since those are by tag, not token
                        $tokenClauses[$origToken][] = $tagMatch($key);
                        // We do need a joined table to match tokens to tags
                        $tagTokens[$key] = $token;
                        break;
                    case 'cw':
                        // Don't need match selects since those are by cw, not token
                        $tokenClauses[$origToken][] = $cwMatch($key);
                        // We do need a joined table to match tokens to cws
                        $cwTokens[$key] = $token;
                        break;
                    case 'title':
                        $tokenDataSelects['title'][] = $titleMatch($key);
                        $tokenClauses[$origToken][] = $titleMatch($key);
                        break;
                    case 'day':
                        $tokenDataSelects['dayOfMonth'][] = $dayOfMonthMatch($key);
                        $tokenDataSelects['dayName'][] = $dayNameMatch($key);
                        if (!\array_key_exists($token, $tokenClauses)) 
                            $tokenClauses[$origToken] = [];
                        $tokenClauses[$origToken] += [$dayOfMonthMatch($key), 
                                                        $dayNameMatch($key)];
                        break;
                    case 'month':
                        $tokenDataSelects['month'][] = $monthMatch($key);
                        $tokenClauses[$origToken][] = $monthMatch($key);
                        break;
                    case 'year':
                        $tokenDataSelects['year'][] = $yearMatch($key);
                        $tokenClauses[$origToken][] = $yearMatch($key);
                        break;
                    case 'description':
                        $tokenDataSelects["desc$token"] = $descMatch($key);
                        $tokenClauses[$origToken][] = $descMatch($key);
                        break;
                    // no default behavior
                }
            } else {
                switch ($prefix) {
                    case 'tag': 
                        $tagExcludeTokens[$key] = $token;
                        break;
                    case 'cw': 
                        $cwExcludeTokens[$key] = $token; 
                        break;
                    case 'title': 
                        $tokenExcludeClauses[$token][] = $titleMatch($key);
                        break;
                    case 'day':
                        if (\array_key_exists($token, $tokenExcludeClauses)) 
                            $tokenExcludeClauses[$token] = [];
                        $tokenExcludeClauses[$token] += [$dayOfMonthMatch($key), 
                                                            $dayNameMatch($key)];
                        break;
                    case 'month':
                        $tokenExcludeClauses[$token][] = $monthMatch($key);
                        break;
                    case 'year':
                        $tokenExcludeClauses[$token][] = $yearMatch($key);
                        break;
                    case 'description':
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);
                        break;
                    // no default behavior
                }
            }
            
        } else {
            $execList[$key] = $token;
            if ($include) {
                if (preg_match('/^\d{1,2}$/', $token)) { 
                    $tokenDataSelects['title'][] = $titleExactMatch($key);
                    $tokenDataSelects['dayOfMonth'][] = $dayOfMonthMatch($key);
                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    $tokenClauses[$origToken] += [  $titleExactMatch($key), 
                                                    $dayOfMonthMatch($key)  ];
                    if ($searchImgDesc) {
                        if ($matchExactly) {
                            $tokenDataSelects["desc$token"] = $descMatch($key);
                            $tokenClauses[$origToken][] = $descMatch($key);
                        } else $imgDescMatchPhrase[] = $token;
                    }

                } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
                    $tokenDataSelects['title'][] = $titleExactMatch($key);
                    $tokenDataSelects['year'][] = $yearMatch($key);
                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    $tokenClauses[$origToken] += [  $titleExactMatch($key), 
                                                    $yearMatch($key)        ]; 
                    if ($searchImgDesc) {
                        if ($matchExactly){
                            $tokenDataSelects["desc$token"] = $descMatch($key);
                            $tokenClauses[$origToken][] = $descMatch($key);
                        } else $imgDescMatchPhrase[] = $token;                        
                    }

                } else if (\strlen($token) > 2) { 
                    $tokenDataSelects['title'][] = $titleMatch($key);
                    $tokenDataSelects['month'][] = $monthMatch($key);
                    $tokenDataSelects['dayName'][] = $dayNameMatch($key);
                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    $tokenClauses[$origToken] += [  $tagMatch($key), 
                                                    $cwMatch($key),
                                                    $titleMatch($key), 
                                                    $monthMatch($key), 
                                                    $dayNameMatch($key) ];                
                    if ($searchImgDesc) {
                        if ($matchExactly) {
                            $tokenDataSelects["desc$token"] = $descMatch($key);
                            $tokenClauses[$origToken][] = $descMatch($key);
                        } else $imgDescMatchPhrase[] = $token;
                    }
                    $joinTokens[$key] = $token;
                }
                //non-numeric tokens of length 2 or less are discarded

            } else {
                if (preg_match('/^\d{1,2}$/', $token)) { 
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    $tokenExcludeClauses[$token] += [$titleExactMatch($key), 
                                                    $dayOfMonthMatch($key)];
                    if ($searchImgDesc) 
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);

                } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    $tokenExcludeClauses[$token] += [$titleExactMatch($key), 
                                                    $yearMatch($key)];
                    if ($searchImgDesc) 
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);

                } else if (\strlen($token) > 2) { 
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    $tokenExcludeClauses[$token] += [$titleMatch($key), 
                                                    $monthMatch($key), 
                                                    $dayNameMatch($key)];
                    if ($searchImgDesc) 
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);
                    $joinExcludeTokens[$key] = $token;

                } // non-numeric tokens of length 2 or less are discarded
            }
        }
    }

    $imgDescMatchPhrase = \implode(' ', $imgDescMatchPhrase);
    if ($imgDescMatchPhrase) {
        $key = ':t' . $keyIdx++;
        $execList[$key] = $imgDescMatchPhrase;
        $tokenDataSelects["desc$imgDescMatchPhrase"] = $descMatch($key);
        $tokenClauses["alldesc$imgDescMatchPhrase"][] = $descMatch($key);
    }

    return [    $tokenClauses, 
                $tokenDataSelects, 
                $joinTokens, 
                $tagTokens, 
                $cwTokens,
                $joinExcludeTokens, 
                $tagExcludeTokens,
                $cwExcludeTokens, 
                $tokenExcludeClauses,  
                $execList               ];
}

function createTempTables(  $joinTokens, 
                            $tagTokens,
                            $cwTokens, 
                            $matchOp, 
                            $pdoConn    ) {
    $tagSearchDefinition = '';
    $cwSearchDefinition = '';
    $possibleTagMatches = NULL;
    $possibleCWMatches = NULL;
    if ($tagTokens OR $joinTokens) {
        $tagSearchDefinition = 
                'WITH joinsearch AS (SELECT * FROM ('
                . valueRows(\array_keys($joinTokens) + \array_keys($tagTokens))
                . ') AS joinsearch (token))'
                . "SELECT token, tagid, name 
                    FROM joinsearch INNER JOIN tag
                        ON name $matchOp token";
        $tagSearch = $pdoConn->prepare("CREATE TEMPORARY TABLE tagsearch
                                        AS ($tagSearchDefinition);");
        $tagSearch->execute($joinTokens + $tagTokens);
        
        $possibleTagMatches = $pdoConn->query("SELECT name, tagid FROM tagsearch;");
    }

    if ($cwTokens OR $joinTokens) {
        $cwSearchDefinition = 
                'WITH joinsearch AS (SELECT * FROM ('
                . valueRows(\array_keys($joinTokens) + \array_keys($cwTokens))
                . ') AS joinsearch (token))'
                . "SELECT token, contwarningid, name 
                    FROM joinsearch INNER JOIN contwarning 
                        ON name $matchOp token";
        $cwSearch = $pdoConn->prepare("CREATE TEMPORARY TABLE cwsearch
                                        AS ($cwSearchDefinition);");
        $cwSearch->execute($joinTokens + $cwTokens);

        $possibleCWMatches = $pdoConn->query("SELECT name, contwarningid 
                                                FROM cwsearch;");
    }

    return [$tagSearchDefinition, 
            $cwSearchDefinition, 
            $possibleTagMatches, 
            $possibleCWMatches];
}

function assembleSearchClauses( &$execList, 
                                $tokenDataSelects, 
                                $possibleTagMatches, 
                                $possibleCWMatches, 
                                $joinExcludeTokens, 
                                $tagExcludeTokens, 
                                $cwExcludeTokens, 
                                $tokenClauses, 
                                $tokenExcludeClauses, 
                                $tagSearchDefinition, 
                                $cwSearchDefinition, 
                                $matchExactly, 
                                $searchImgDesc,
                                $matchOp            ) {
    $descSelectClause = [];
    $descSelectIdx = 0;
    foreach ($tokenDataSelects as $key => $select) {
        if (str_starts_with($key, 'desc')) {
            $descSelectClause[] = "($select) AS :descalias$descSelectIdx";
            $execList[":descalias$descSelectIdx"] = substr($key, \strlen('desc'));
            $descSelectIdx++;
        }
    }
    $descSelectClause = implode(', ', $descSelectClause);

    $tagSelectClause = [];
    if ($possibleTagMatches) {
        for (   $i = 0; 
                ($tagMatch = $possibleTagMatches->fetch(\PDO::FETCH_ASSOC)) !== false; 
                $i++    ) {
            $tagSelectClause[] = "EXISTS(SELECT tagpage.tagid FROM tagpage 
                                        WHERE tagpage.pageid = page.pageid 
                                            AND tagpage.tagid = :tagid$i
                                    ) AS :tagalias$i"; 
            $execList[":tagid$i"] = $tagMatch['tagid'];
            $execList[":tagalias$i"] = 'tag' . $tagMatch['name'];
        }
    }
    $tagSelectClause = implode(', ', $tagSelectClause);

    $cwSelectClause = [];
    if ($possibleCWMatches) {
        for (   $i = 0; 
                ($cwMatch = $possibleCWMatches->fetch(\PDO::FETCH_ASSOC)) !== false; 
                $i++    ) {
            $cwSelectClause[] = "EXISTS(SELECT contwarningpage.contwarningid 
                                        FROM contwarningpage 
                                        WHERE contwarningpage.pageid = page.pageid 
                                            AND contwarningpage.contwarningid = :cwid$i
                                    ) AS :cwalias$i"; 
            $execList[":cwid$i"] = $cwMatch['contwarningid'];
            $execList[":cwalias$i"] = 'cw' . $cwMatch['name'];
        }
    }
    $cwSelectClause = implode(', ', $cwSelectClause);

    $tagExcludeWithClause = ($joinExcludeTokens OR $tagExcludeTokens) ? 
                                "tagsearchexclude (token, tagid) AS (
                                    SELECT token, tagid FROM ("
                                    . valueRows(\array_keys($joinExcludeTokens) 
                                                + \array_keys($tagExcludeTokens))
                                    . ") AS excluded (token) INNER JOIN tag  
                                        ON tag.name $matchOp excluded.token)"
                                : '';
    
    $cwExcludeWithClause = ($joinExcludeTokens OR $cwExcludeTokens) ? 
                                "cwsearchexclude (token, contwarningid) AS (
                                    SELECT token, contwarningid FROM ("
                                    . valueRows(\array_keys($joinExcludeTokens) 
                                                + \array_keys($cwExcludeTokens))
                                    . ") AS excluded (token) INNER JOIN contwarning   
                                        ON contwarning.name $matchOp excluded.token)"
                                : '';
    
    $singleMatchClause = fn($field, $alias) =>
                            ($tokenDataSelects[$field] ? 
                                    '(' . \implode(' OR ', $tokenDataSelects[$field]) 
                                            . ") AS $alias"
                                    : '');
    $selectMatchClause = 
        \implode(', ', 
                \array_filter(  [$singleMatchClause('title', 'titlematch'), 
                                    $singleMatchClause('dayOfMonth', 'dayofmonthmatch'), 
                                    $singleMatchClause('year', 'yearmatch'), 
                                    $singleMatchClause('month', 'monthmatch'), 
                                    $singleMatchClause('dayName', 'daynamematch'), 
                                    $tagSelectClause,
                                    $cwSelectClause, 
                                    $descSelectClause], 
                                fn($el) => $el !== '')
                );

    $withClause = [];
    if ($tagSearchDefinition) { 
        $withClause[] = "tagsearch (token, tagid, name) 
                            AS ($tagSearchDefinition)";
    }
    if ($cwSearchDefinition) {
        $withClause[] = "cwsearch (token, contwarningid, name) 
                            AS ($cwSearchDefinition)";
    }
    if ($tagExcludeWithClause) {
        $withClause[] = $tagExcludeWithClause;
    }
    if ($cwExcludeWithClause) {
        $withClause[] = $cwExcludeWithClause;
    }
    $withClause = \implode(', ', $withClause);

    $whereSelectClause = '';
    if (!$matchExactly AND $searchImgDesc) {
        $specTokenClauses = \array_filter(  
                $tokenClauses, 
                fn($k) => preg_match('/^(' . \implode('|', SEARCHFIELDSPECS) . '):/', $k), 
                ARRAY_FILTER_USE_KEY);
        $nonspecTokenClauses = \array_filter(  
                $tokenClauses, 
                fn($k) => !preg_match('/^(' . \implode('|', SEARCHFIELDSPECS) . '):/', $k)
                            AND !str_starts_with($k, 'alldesc'), 
                ARRAY_FILTER_USE_KEY);
        $whereSelectClause = 
            \implode(' AND ', array_map(fn($tarr) => 
                                            '(' . \implode(' OR ', $tarr) . ')', 
                                        $specTokenClauses)
                    );
        if ($specTokenClauses AND $nonspecTokenClauses) {
            $whereSelectClause .= ' AND ';
        }
        if ($nonspecTokenClauses) {
            $whereSelectClause .= '(('
                    . \implode(' AND ', array_map(fn($tarr) => 
                                                '(' . \implode(' OR ', $tarr) . ')', 
                                            $nonspecTokenClauses)
                                )
                    . ') OR '
                    . array_find(   $tokenClauses, 
                                    fn($val, $key) => str_starts_with($key, 'alldesc')  )[0]
                    . ')';
        }
    } else {
        $whereSelectClause = 
            \implode(' AND ', array_map(fn($tarr) => 
                                            '(' . \implode(' OR ', $tarr) . ')', 
                                        $tokenClauses)
                    );
    }
    
    $whereNotSelectClause = [];
    if ($tokenExcludeClauses) {
        $whereNotSelectClause[] = \implode( ' OR ', 
                                            array_map(  fn($tarr) => \implode(' OR ', $tarr), 
                                                        $tokenExcludeClauses)
                                            );
    }
    if ($joinExcludeTokens) {
        $whereNotSelectClause[] = 'EXISTS(SELECT tagid FROM 
                                    tagsearchexclude INNER JOIN tagpage USING (tagid)
                                        WHERE tagpage.pageid = page.pageid
                                ) OR EXISTS(SELECT contwarningid FROM 
                                    cwsearchexclude INNER JOIN contwarningpage 
                                            USING (contwarningid)
                                        WHERE contwarningpage.pageid = page.pageid    
                                )';
    } else {
        if ($tagExcludeTokens) {
            $whereNotSelectClause[] = 'EXISTS(SELECT tagid FROM 
                                        tagsearchexclude INNER JOIN tagpage USING (tagid)
                                            WHERE tagpage.pageid = page.pageid)';
        }
        if ($cwExcludeTokens) {
            $whereNotSelectClause[] = 'EXISTS(SELECT contwarningid FROM 
                                        cwsearchexclude INNER JOIN contwarningpage 
                                                USING (contwarningid)
                                            WHERE contwarningpage.pageid = page.pageid)';
        }
    }
    $whereNotSelectClause = \implode(' OR ', $whereNotSelectClause);

    $whereClause = (string) $whereSelectClause;
    if ($whereSelectClause AND $whereNotSelectClause) $whereClause .= ' AND ';
    if ($whereNotSelectClause) $whereClause .= "NOT ($whereNotSelectClause)";
    
    return [    $execList, 
                $withClause, 
                $selectMatchClause, 
                $whereClause    ];
}

function dropTempTables($joinTokens, $tagTokens, $cwTokens, $pdoConn) {
    if ($joinTokens OR $tagTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE tagsearch;'); 
    } 
    if ($joinTokens OR $cwTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE cwsearch;'); 
    }
}

function searchComics($searchStr, 
                      $pdoConn, 
                      $matchExactly = false, 
                      $searchImgDesc = false) {
    $matchOp = $matchExactly ? '=' : 'REGEXP';

    [   $tagMatch, 
        $cwMatch, 
        $titleExactMatch, 
        $titleMatch, 
        $dayNameMatch, 
        $dayOfMonthMatch, 
        $monthMatch, 
        $yearMatch, 
        $descExactMatch, 
        $descMatch      ] = defineSearchMatches($matchExactly, $matchOp);

    [   $tokenClauses, 
        $tokenDataSelects, 
        $joinTokens, 
        $tagTokens, 
        $cwTokens,
        $joinExcludeTokens, 
        $tagExcludeTokens,
        $cwExcludeTokens, 
        $tokenExcludeClauses,  
        $execList           ] = generateSearchClauses(  $searchStr, 
                                                        $matchExactly, 
                                                        $searchImgDesc,
                                                        $tagMatch, 
                                                        $cwMatch, 
                                                        $titleExactMatch, 
                                                        $titleMatch, 
                                                        $dayNameMatch, 
                                                        $dayOfMonthMatch, 
                                                        $monthMatch, 
                                                        $yearMatch, 
                                                        $descExactMatch, 
                                                        $descMatch  );

    [   //$joinSearchDefinition, 
        $tagSearchDefinition, 
        $cwSearchDefinition, 
        $possibleTagMatches, 
        $possibleCWMatches  ] = createTempTables(   $joinTokens,
                                                    $tagTokens, 
                                                    $cwTokens, 
                                                    $matchOp, 
                                                    $pdoConn    );

    [   $execList, 
        $withClause, 
        $selectMatchClause, 
        $whereClause    ] = assembleSearchClauses(  $execList, 
                                                    $tokenDataSelects, 
                                                    $possibleTagMatches, 
                                                    $possibleCWMatches, 
                                                    $joinExcludeTokens, 
                                                    $tagExcludeTokens, 
                                                    $cwExcludeTokens, 
                                                    $tokenClauses, 
                                                    $tokenExcludeClauses, 
                                                    //$joinSearchDefinition, 
                                                    $tagSearchDefinition, 
                                                    $cwSearchDefinition, 
                                                    $matchExactly, 
                                                    $searchImgDesc, 
                                                    $matchOp            );

    $pages = $pdoConn->prepare( ($withClause ? "WITH $withClause " : '')
                                . 'SELECT page.pageid AS pageid, 
                                        page.title AS title, 
                                        page.postdate AS postdate, 
                                        page.location AS location, 
                                        page.imagedesc AS imagedesc'
                                . ($selectMatchClause ? 
                                        ", $selectMatchClause" 
                                        : '')
                                . ' FROM page' 
                                . ($whereClause ? 
                                        " WHERE $whereClause"
                                        : '') 
                                . ";"
                                );

    $pages->execute($execList);

    $results = $pages->fetchAll(\PDO::FETCH_ASSOC);

    dropTempTables($joinTokens, $tagTokens, $cwTokens, $pdoConn);

    return [$results, 
            $pages, 
            $execList];
}

?>