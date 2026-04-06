<?php
namespace Briel {

const SQLLOADNULL = '\N';
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
                                            WHERE ratio = :ratioStr");
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

    return [SQLLOADNULL, 
            $absPath, 
            $imgWidth, 
            $imgHeight, 
            $alttext, 
            $fileExt, 
            $fileSizeKB, 
            SQLLOADNULL, 
            $ratioID];
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
    $transactSuccess = NULL;
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

// Do I need this?
// const ACCEPTEDIMGTYPES = ["jpg",
//                           "jpeg",
//                           "png",
//                           "gif",
//                           "tif",
//                           "tiff",
//                           "ico",
//                           "bmp"];

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
                                         WHERE pageid = ?')
            AND $selectFile->execute([$pageid])) {

        return $selectFile->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        // Writes location values into an array indexed by width
    } else {
        return false;
    }
}

function is_html_path($str) {
    return preg_match('(.*/.+\.html)', $str);
}

function generatePageRecordInteractive($pdoConn) {

    echo "Generating new page record...";
    
    // Set title
    $allTitles = $pdoConn->query("SELECT title FROM page")
                          ->fetchAll(\PDO::FETCH_COLUMN, 0);
    $title = promptInput("Enter title: > ");
    $findFileFromTitle = $pdoConn->prepare("SELECT location FROM page 
                                            WHERE title = ?");
    $useAnyway = false;
    while (in_array($title, $allTitles) AND !$useAnyway) {
        echo "Warning: $title already exists in page at "
             . $findFileFromTitle->execute([$title])->fetch()[0];
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
    while (!is_html_path($location) 
           OR (file_exists($location) AND !$useAnyway)) {
        if (!is_html_path($location)) {
            echo "Error: $location is not a path for an HTML file.";
            $location = $promptHTMLPath();
        } else if (file_exists($location)) {
            if (promptInput("Warning: $location already exists.\n"
                            / "Use it? (y/n) > ") == "y") {
                $useAnyway = true;
            } else $location = $promptHTMLPath();
        }
    }

    $imageDesc = promptInput("Enter comic page description "
                                . "(or a path to it):\n> ");
    if (is_readable($imageDesc)) {
        $imageDesc = file_get_contents($imageDesc);
    }

    

    $fileExt = substr($filePath, strrpos($filePath, ".") + 1);

    $fileSizeKB = (int) (filesize($filePath) / 1000);

    $ratioStatement = $pdoConn->prepare("SELECT ratioid FROM aspectratio 
                                            WHERE ratio = :ratioStr");
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

    return [SQLLOADNULL, 
            $title, 
            SQLLOADNULL, // page shouldn't be posted at this point
            $location, 
            $imagedesc, 
            $spreadid, 
            $stylelocation];
}

}

?>