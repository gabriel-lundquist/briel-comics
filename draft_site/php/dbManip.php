<?php
namespace Briel {

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

/**
 * 
 */
function promptInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

/**
 * Returns the PDO connction if successful, 
 * returns false if not.
 * 
 * Default values are from my laptop SQL server.
 */
function pdoConnect( $dbhost = 'localhost', 
                     $dbuser = 'root', 
                     $dbname = 'briel_comics_test', 
                     $dbport = 3307, 
                     $echoConnSuccess = false ) {
    try {
        $conn = new \PDO("mysql:host=$dbhost;
                          dbname=$dbname;
                          port=$dbport", 
                         $dbuser, 
                         promptInput("Enter password: "));
        $conn->setAttribute(\PDO::ATTR_ERRMODE, 
                            \PDO::ERRMODE_EXCEPTION);
        if ( $echoConnSuccess ) echo "Connected successfully.\n";
        return $conn;
    } catch (\PDOException $e) {
        if ( $echoConnSuccess ) echo "Connection failed: " . $e->getMessage();
        return false;
    }
}

/**
 * 
 */
function executeAndFetch($statement, 
                         $executeArr, 
                         $mode = \PDO::FETCH_BOTH) {
    if ($statement->execute($executeArr)) {
        return $statement->fetch($mode);
    } else return false;
}

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
        $alttext = file_get_contents($alttext);
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

function prepareInsertRecords($pdoConn, 
                              $tableName, 
                              $insertColumns, 
                              $records) {
    $rowPlaceholderStr = "(?" . str_repeat(", ?", \count($insertColumns)-1) . ")";
    $allPlaceholderStr = $rowPlaceholderStr . str_repeat(", \n$rowPlaceholderStr", 
                                                         \count($records)-1);

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

function getFileWidthLocations($pageid, $pdoConn) {
    
    if ($selectFile = $pdoConn->prepare('SELECT width, location FROM file 
                                         WHERE pageid = ?;')
            AND $selectFile->execute([$pageid])) {

        return $selectFile->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        // Writes location values into an array indexed by width
    } else {
        return false;
    }
}

// function is_html_path($str) {
//     return is_file_path($str) AND preg_match('\.html)', $str);
// }

function is_file_path($str, $fileExt = ".+") {
    return preg_match('(.*/.+)', $str) AND preg_match("(.*\.$fileExt)", $str);
}

function generatePageRecordInteractive($pdoConn) {
    echo "Generating new page record...";
    
    // Set title
    $allTitles = $pdoConn->query("SELECT title FROM page;")
                          ->fetchAll(\PDO::FETCH_COLUMN, 0);
    $title = promptInput("Enter title: > ");
    $findPathFromTitle = $pdoConn->prepare("SELECT location FROM page 
                                            WHERE title = ?;");
    $useAnyway = false;
    while (in_array($title, $allTitles) AND !$useAnyway) {
        echo "Warning: $title already exists in page at "
             . $findPathFromTitle->execute([$title])->fetch()[0] 
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
                            . "Use it? (y/n) > ") == "y") {
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
    $getSpreadID->execute([(promptInput("Double spread? (y/n) > ") == "y")
                            ? "double" : "normal"]);
    $spreadID = $getSpreadID->fetch()[0];

    // Get location of a special style (if present)
    $styleLocation = promptInput("Enter path to special style (or 'n' if none):\n> ");
    while (!is_file_path($styleLocation, "css") AND $styleLocation != "n") {
        echo "Error: $styleLocation not a css file.\n";
        $styleLocation = promptInput("Enter path to special style (or 'n' if none):\n> ");
    }
    if ($styleLocation == "n") $styleLocation = SQLNULL;

    return ["pageid"        => SQLNULL, 
            "title"         => $title, 
            "location"      => $location, 
            "imagedesc"     => $imageDesc, 
            "spreadid"      => $spreadID, 
            "stylelocation" => $styleLocation];
}

/**
 * Assumes $pageRecord is filled out, with an assigned `pageid`.
 */
function insertTagAssociationsInteractive($pageRecord, $pdoConn) {
    if (!$pdoConn->beginTransaction()) {
        echo "Error: can't begin transaction. Aborting tag assocation.";
        return false;
    }

    $existingTags = $pdoConn->query("SELECT name FROM tag;")
                            ->fetchALL(\PDO::FETCH_COLUMN);
    $insertNewTag = $pdoConn->prepare("INSERT INTO tag (tagid, name) VALUE (?,?);");
    $getTagIDFromName = $pdoConn->prepare("SELECT tagid FROM tag WHERE name = ?;");

    $tagListStr = promptInput("Adding tag associations with {$pageRecord['title']}...\n"
                                . "Type in comma-separated tags. Available tags:\n"
                                . implode("\t", $existingTags)
                                . "\n> ");
    $tagList = array_map(fn($s) => trim($s), explode(",", $tagListStr));

    $tagAssocRecords = [];
    $finalTagList = [];
    foreach ($tagList as $tag) {

        if (!preg_match('([\w]+[-\w]*)', $tag)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the tag. \nSkipping $tag.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!in_array($tag, $existingTags)) {
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
        echo "Error: could not insert records. Rolling back and aborting...\n";
        $pdoConn->rollback();
        return false;
    }
}

/**
 * Assumes $pageRecord is filled out, with an assigned `pageid`.
 */
function insertCWAssociationsInteractive($pageRecord, $pdoConn) {
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

    $cwAssocRecords = [];
    $finalCWList = [];
    foreach ($cwList as $contWarn) {

        if (!preg_match('([\w]+[-\w]*)', $contWarn)) {
            echo "Warning: only permitted special characters are - and _\n"
                . "and - can't start the content warning. \nSkipping $contWarn.\n";
            continue;
        } 
        
        // Only get below here if the name matches allowed characters.
        if (!in_array($contWarn, $existingCWs)) {
            if (promptInput("$contWarn not an existing content warning."
                            . "Add it? (y/n) > ") == "y") {
                $insertNewCW->execute([SQLNULL, $contWarn]);
            } else {
                echo "Okay, skipping $contWarn...\n";
                continue;
            }
        }

        $cwAssocRecords[] = [$getCWIDFromName->execute([$contWarn])->fetch()[0], 
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
        echo "Error: could not insert records. Rolling back and aborting...\n";
        $pdoConn->rollback();
        return false;
    }
}

}

?>