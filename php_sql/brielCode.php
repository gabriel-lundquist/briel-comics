<?php
namespace brielCode {

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
                     $dbport = 3307 ) {
    try {
        $conn = new \PDO("mysql:host=$dbhost;
                          dbname=$dbname;
                          port=$dbport", 
                         $dbuser, 
                         promptInput("Enter password: "));
        $conn->setAttribute(\PDO::ATTR_ERRMODE, 
                            \PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully.\n";
        return $conn;
    } catch (\PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        return false;
    }
}

// function readAspectRatios($pdoConn) {
//     if (!$pdoConn) {
//         throw new \Exception("Not connected to server.");
//     }

//     return $pdoConn->query("SELECT * FROM aspectratio;")
//                    ->fetchAll(\PDO::FETCH_ASSOC);
// }

function executeAndFetch($statement, 
                         $executeArr, 
                         $mode = \PDO::FETCH_BOTH) {
    if ($statement->execute([$executeArr])) {
        return $statement->fetch($mode);
    } else return false;
}

// function queryForID($pdoConn, 
//                     $idAttrName, 
//                     $tableName, 
//                     $searchAttr) {
    
// }

const SQLNULL = '\N';

function getFileRecordInteractive($filePath, 
                                  $pdoConn, 
                                  $ratioStr = NULL) {
    echo "Getting record for " . basename($filePath);
    if (!is_file($filePath)) {
        return false;
    }
    $sqlNull = '\N';

    $absPath = realpath($filePath);

    // I *think* this should work
    [$imgWidth, $imgHeight] = getimagesize($absPath);

    $alttext = promptInput("Enter alt text (or a path to it):\n> ");
    if (is_readable($alttext)) {
        $alttext = file_get_contents($alttext);
    }

    $fileExt = substr($filePath, strrpos($filePath, ".") + 1);

    $fileSizeKB = (int) (filesize($filePath) / 1000);

    $ratioStatement = $pdoConn->prepare("SELECT ratioid FROM aspectratio WHERE ratio = :ratioStr");
    $fetchedRow = NULL;
    if (!$ratioStr) {
        $ratioStr = promptInput("Enter aspect ratio (format: #:#): > ");
    }
    // if execution is unsuccessful or there is no row in the selection
    if (!($ratioStatement->execute([":ratioStr" => $ratioStr]))
            or !($fetchedRow = $ratioStatement->fetch())) {
        do {
            $ratioStr = promptInput("$ratioStr not a valid ratio."
                                    ."\nEnter aspect ratio (format: #:#): > ");
        } while (!$ratioStatement->execute([":ratioStr" => $ratioStr])
                 or !($fetchedRow = $ratioStatement->fetch()));
    }
    $ratioID = $fetchedRow[0];

    return [SQLNULL, 
            $absPath, 
            $imgWidth, 
            $imgHeight, 
            $alttext, 
            $fileExt, 
            $fileSizeKB, 
            SQLNULL, 
            $ratioID];
}

function prepareInsertRecords($pdoConn, 
                              $tableName, 
                              $insertColumns, 
                              $records) {
    $rowPlaceholderStr = "(?" . str_repeat(", ?", \count($insertColumns) - 1) . ")";
    $allPlaceholderStr = $rowPlaceholderStr . str_repeat(", $rowPlaceholderStr", 
                                                         \count($records) - 1);

    return $pdoConn->prepare("INSERT INTO $tableName ("
                             . implode($insertColumns) 
                             . ") VALUES $allPlaceholderStr;");
}

/**
 * Summary of brielCode\executeInsertRecords
 * @param mixed $insertStatement Assumes this is prepared by `prepareInsertRecords`
 * @param mixed $records
 */
function executeInsertRecords($insertStatement, $records) {
    for ($placeIdx = 1, $i = 0; $i < \count($records); $i++) {
        for ($j = 0; $j < \count($records[$i]); $j++, $placeIdx++) {
            $insertStatement->bindValue($placeIdx, $records[$i][$j]);
        }
    }
    return $insertStatement->execute();
}

function queryInsertRecords($pdoConn, 
                            $tableName, 
                            $insertColumns, 
                            $records) {
    $insertStatement = prepareInsertRecords($pdoConn, 
                                            $tableName, 
                                            $insertColumns, 
                                            $records);
    if (executeInsertRecords($insertStatement, $records)) {
        return $insertStatement;
    } else {
        return false;
    }
}

const FILEINSERTCOLUMNS = ["fileid",
                          "location",
                          "width",
                          "height",
                          "alttext",
                          "filetype",
                          "filesize",
                          "pageid",
                          "ratio"];

function insertFileRecords($pdoConn, 
                           $records) {

    return queryInsertRecords($pdoConn, 
                              "file", 
                              FILEINSERTCOLUMNS, 
                              $records);
}

// Do I need this?
const ACCEPTEDIMGTYPES = ["jpg",
                          "jpeg",
                          "png",
                          "gif",
                          "tif",
                          "tiff",
                          "ico",
                          "bmp"];

function getFileRecordsFolder($pdoConn, $dirPath) {

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
            $records[] = getFileRecordInteractive($filePath, $pdoConn);
        }
    }

    return $records;
}

function loadFileRecords($dirPath) {
    $pdoConn = pdoConnect();
    return insertFileRecords($pdoConn, 
                             getFileRecordsFolder($pdoConn, $dirPath));
}

}

?>