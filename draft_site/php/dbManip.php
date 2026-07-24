<?php
namespace Briel;

require_once 'brielConstants.php';

const SQLLOADFILENULL = '\N';
const SQLSPACEREGEX = '[[:space:]]';

const FILECOLUMNS = [   "fileid",
                        "path",
                        "width",
                        "height",
                        "alttext",
                        "uploaddate", 
                        "modifydate", 
                        "filetype",
                        "filesize",
                        "pageid",
                        "ratioid", 
                        "purposeid" ];

const FILEINSERTCOLUMNS = [ FILECOLUMNS[1], //path
                            FILECOLUMNS[2], //width
                            FILECOLUMNS[3], //height
                            FILECOLUMNS[4], //alttext
                            FILECOLUMNS[7], //filetype
                            FILECOLUMNS[8], //filesize
                            FILECOLUMNS[10], //ratioid
                            FILECOLUMNS[11] ]; //purposeid

const PAGEINSERTCOLUMNS = [ "title",
                            "path",
                            "imagedesc",
                            "spreadid",
                            "stylepath" ];

const TEXTMATCHTHRESHOLD = 0.1;

/**
 * Summary of Briel\promptInput
 * @param string $prompt
 * @return string
 */
function promptInput(string $prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

function promptPathHTML() {
    return promptInput("Enter HTML file path (doesn't need to exist):\n> ");
}

/**
 * Summary of Briel\pdoConnect
 * WILL NEED TO EDIT IN AN ENVIRONMENT VARIABLE FOR THE PASSWORD
 * Returns the PDO connection if successful, `false` if not.
 * Default values come from my laptop server settings.
 * @param string $dbhost
 * @param string $dbuser
 * @param string $dbname
 * @param int $dbport
 * @param bool $enterPassword
 * @param bool $echoConnSuccess
 * @return bool|\PDO
 */
function pdoConnect(string $dbhost = 'localhost', 
                    string $dbuser = 'root', 
                    string $dbname = 'briel_comics_test', 
                    int $dbport = 3307, 
                    bool $enterPassword = false,
                    bool $echoConnSuccess = false   ) {
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

function dumpQuery(string $query, \PDO $pdoConn) {
    var_dump($pdoConn->query($query)->fetchAll(\PDO::FETCH_ASSOC));
}

function tryBeginTransaction(\PDO $pdoConn) {
    if ($pdoConn->inTransaction()) {
        try {
            $pdoConn->beginTransaction();
        } catch (\PDOException $e) {
            echo $e->getMessage() 
                . "\n---\nCan't begin transaction.\n";
            return false;
        }
    }

    return true;
}

function promptCommit(\PDO $pdoConn, string $message = '') {
    if ($pdoConn->inTransaction()) {
        echo "$message\nIf you don't commit now, this change may be rolled back.\n";
        if (promptInput("Commit transaction? (y/n) > ") == "y") {
            $pdoConn->commit();
            return true;
        }
    }

    return false;
}

function promptRollback(\PDO $pdoConn, string $message = '') {
    if ($pdoConn->inTransaction()) {
        if ($message) echo "$message\n";
        if (promptInput("Roll back this transaction? (y/n) > ") == "y") {
            $pdoConn->rollback();
            return true;
        }
    }

    return false;
}

function executeAndFetch(   \PDOStatement $statement, 
                            ?array $executeArr = null, 
                            $mode = \PDO::FETCH_BOTH    ) {
    if ($statement->execute($executeArr)) {
        return $statement->fetch($mode);
    } else return false;
}

function queryAndFetchScalar(string $statementText, \PDO $pdoConn) {
    return $pdoConn->query($statementText)->fetch(\PDO::FETCH_NUM)[0];
}

function executeAndFetchScalar( \PDOStatement $statement, 
                                $execScalar = null ) {
    return $statement->execute([$execScalar]) ? 
            $statement->fetch(\PDO::FETCH_NUM)[0] 
            : false;
}

function rowPlaceholder(int $n) {
    return '(' . implode(',', array_fill(0, $n, '?')) . ')';
}

function searchcacheKeyFromSearchString(string $searchStr) {
    $termArr = preg_split('/\s+/', $searchStr, flags: PREG_SPLIT_NO_EMPTY);
    sort($termArr, SORT_STRING);
    return implode(' ', $termArr);  // delimit with spaces so we can pass this to searchComics
}

function recordsToAttrRowStrs(  array $records, 
                                string $attrIDKey, 
                                string $attrNameKey, 
                                int $padLen = 8 ) {
    return array_map(fn($record) => str_pad($record[$attrIDKey], 
                                            $padLen, 
                                            '_')
                                    . $record[$attrNameKey], 
                        $records);
}

function valueRows(array $arr) {
    return 'VALUES ' . \implode(', ', \array_map(fn($a) => "ROW($a)", $arr));
}

function attributeTableStr( array $records, 
                            string $tableTitle, 
                            string $attrIDKey, 
                            string $attrNameKey, 
                            int $rowPadLen = 8, 
                            int $tableSpacerLen = 42    ) {

    return "\n" . str_repeat('-', $tableSpacerLen) 
            . "\n" . $tableTitle 
            . "\n" . str_pad('ID', $rowPadLen) . 'Name/Title/Path'
            . "\n" . implode("\n", recordsToAttrRowStrs($records, 
                                                        $attrIDKey, 
                                                        $attrNameKey, 
                                                        $rowPadLen))
            . "\n" . str_repeat('_', $tableSpacerLen) . "\n";
}

function generateFileRecordInteractive( string $filePath, 
                                        \PDO $pdoConn, 
                                        ?string $ratioStr = null, 
                                        ?\PDOStatement $ratioStatement = null   ) {
    echo "Generating record for `" . basename($filePath) . '`...';
    if (!is_file($filePath)) {
        echo "`$filePath` is not an existing file. Aborting record generation...\n";
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

    if (!$ratioStatement) $ratioStatement = $pdoConn->prepare(
            "SELECT ratioid FROM aspectratio WHERE ratio = ?;");
    $fetchedRow = null;
    if (!$ratioStr) {
        $ratioStr = promptInput("Enter aspect ratio (format width:height)\n> ");
    }
    // if execution is unsuccessful or there is no row in the selection
    while (    !($ratioStatement->execute([$ratioStr]))
            or !($fetchedRow = $ratioStatement->fetch(\PDO::FETCH_ASSOC))   ) {
        $ratioStr = promptInput(<<<STR
                                $ratioStr not a valid ratio.
                                Enter aspect ratio (format width:height).
                                > 
                                STR);
    }
    $ratioID = $fetchedRow['ratioid'];

    $getPurposeID = $pdoConn->prepare("SELECT purposeid FROM filepurpose WHERE purpose = ?;");
    if (promptInput("Is this file a whole page, to be displayed for reading? (y/n) > ") == "y") {
        $getPurposeID->execute(['page']);
    } elseif (promptInput("Okay, not a page image. Is it a thumbnail? (y/n) > ") == "y") {
        $getPurposeID->execute(['thumbnail']);
    } else {
        echo "Okay, for now it's classified as 'other'.\n";
        $getPurposeID->execute(['other']);
    }
    $purposeID = $getPurposeID->fetch()[0];

    return array_combine(FILEINSERTCOLUMNS, [   $absPath, 
                                                $imgWidth, 
                                                $imgHeight, 
                                                $alttext, 
                                                $fileExt, 
                                                $fileSizeKB, 
                                                $ratioID, 
                                                $purposeID  ]);
}

/**
 * Summary of Briel\prepareInsertRecords
 * @param \PDO $pdoConn An existing PDO object.
 * @param string $tableName String containing name of the table in the database
 * @param array<string> $insertColumns Array of strings with titles of columns
 * @param array<array> $records 2D array, indexed by record then by column
 * Fuck! This messes up given that individual records are *also* arrays. 
 * Make sure to always put the records in an array, even if there's just one.
 */
function prepareInsertRecords(  \PDO $pdoConn, 
                                string $tableName, 
                                array $insertColumns, 
                                array $records    ) {
    $row = rowPlaceholder(\is_array($insertColumns) ? \count($insertColumns) : 1);
    $allPlaceholderStr = \is_array($records) ? 
            \implode(",\n", array_fill(0, \count($records), $row))
            : $row;
    
    return $pdoConn->prepare("INSERT INTO $tableName ("
                             . (\is_array($insertColumns) ? 
                                        implode(", ", $insertColumns)
                                        : $insertColumns) 
                             . ") VALUES $allPlaceholderStr;");
}

/**
 * Summary of Briel\executeInsertRecords
 * @param \PDO $pdoConn
 * @param \PDOStatement $insertStatement Assumes this is prepared by `prepareInsertRecords`
 * @param array<array> $records Fuck! This messes up given that records are *also* arrays. 
 * Make sure to always put the records in an array, even if there's just one.
 * @param bool $promptToCommit
 * @return bool
 */
function executeInsertRecords(  \PDO $pdoConn, 
                                \PDOStatement $insertStatement, 
                                array $records, 
                                bool $promptToCommit = true, 
                                string $promptCommitMessage = ''   ) {
                                    
    if (!tryBeginTransaction($pdoConn)) return false;

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
    if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);

    return $execStatus;
}

/**
 * Summary of Briel\queryInsertRecords
 * @param \PDO $pdoConn An existing PDO object.
 * @param string $tableName String containing name of the table in the database
 * @param array $insertColumns Array of strings with titles of columns
 * @param array $records 2D array of strings, indexed by record then by column
 * @param bool $promptToCommit Whether you want to output a prompt for committing 
 * the transaction
 * @param string $promptCommitMessage The message output with `$promptToCommit`.
 */
function queryInsertRecords(\PDO $pdoConn, 
                            string $tableName, 
                            array $insertColumns, 
                            array $records, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = '') {

    $insertStatement = prepareInsertRecords($pdoConn, 
                                            $tableName, 
                                            $insertColumns, 
                                            $records);
    if (executeInsertRecords(   $pdoConn, 
                                $insertStatement, 
                                $records, 
                                promptToCommit: $promptToCommit, 
                                promptCommitMessage: $promptCommitMessage   )) {
        return $insertStatement;
    } else {
        return false;
    }
}

/**
 * Summary of Briel\insertFileRecords
 * @param array $records
 * @param \PDO $pdoConn
 * @param bool $promptToCommit
 * @param string $promptCommitMessage
 * @return bool|\PDOStatement
 */
function insertFileRecords( array $records, 
                            \PDO $pdoConn, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = '' ) {

    $queryResult = queryInsertRecords(  $pdoConn, 
                                        "file", 
                                        FILEINSERTCOLUMNS, 
                                        $records, 
                                        $promptToCommit, 
                                        $promptCommitMessage );
    return $queryResult;
}

// function insertFileRecordsFolder(   string $dirPath, 
//                                     \PDO $pdoConn, 
//                                     bool $promptToCommit = true, 
//                                     string $promptCommitMessage = '' ) {

//     $fileInfo = \finfo_open(\FILEINFO_MIME_TYPE);

//     $filePaths = array_map(fn($fileName) => realpath("$dirPath/$fileName"), 
//                            \scandir($dirPath));
    
//     $records = [];
//     foreach ($filePaths as $filePath) {
//         $fileInfoStr = \finfo_file($fileInfo, $filePath);
//         // if MIME type is image
//         if (substr($fileInfoStr, 
//                    0,
//                    strrpos($fileInfoStr, '/')) == 'image') {
//             $records[] = generateFileRecordInteractive($filePath, $pdoConn);
//         }
//     }

//     return $records;
// }

// /**
//  * Summary of Briel\loadFileRecords
//  * @param mixed $dirPath
//  * @param mixed $dbhost
//  * @param mixed $dbuser
//  * @param mixed $dbname
//  * @param mixed $dbport
//  */
// function loadFileRecords(   $dirPath, 
//                             $dbhost = 'localhost', 
//                             $dbuser = 'root', 
//                             $dbname = 'briel_comics_test', 
//                             $dbport = 3307  ) {
    
//     $pdoConn = pdoConnect($dbhost, 
//                           $dbuser, 
//                           $dbname, 
//                           $dbport);
//     return insertFileRecords(insertFileRecordsFolder($dirPath, $pdoConn), 
//                              $pdoConn);
// }

;

/**
 * Summary of Briel\insertPageRecords
 * @param array $records
 * @param \PDO $pdoConn
 * @param bool $promptToCommit
 * @param string $promptCommitMessage
 * @return bool|\PDOStatement
 */
function insertPageRecords( array $records, 
                            \PDO $pdoConn, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;

    $queryResult = queryInsertRecords(  $pdoConn, 
                                        'page',
                                        PAGEINSERTCOLUMNS, 
                                        $records, 
                                        promptToCommit: false);
    if ($promptToCommit) promptCommit(
                $pdoConn, "Query result is:\n" . print_r($queryResult, true) . "\n");
    return $queryResult;
}

/**
 * Summary of Briel\insertNewTags
 * @param array $tags
 * @param \PDO $pdoConn
 * @param bool $promptToCommit
 * @param string $promptCommitMessage
 * @return bool|\PDOStatement
 */
function insertNewTags( array $tags, 
                        \PDO $pdoConn, 
                        bool $promptToCommit = true, 
                        string $promptCommitMessage = '') {
    if (!tryBeginTransaction($pdoConn)) return false;
    
    $tags = array_unique($tags);
    $matchExistingTags = 
            $pdoConn->prepare("SELECT * FROM tag
                                WHERE name IN "
                                . rowPlaceholder(\count($tags))
                                . ";");
    $matchExistingTags->execute($tags);
    $existingTags = $matchExistingTags->fetchAll(\PDO::FETCH_ASSOC);
    echo attributeTableStr( $existingTags, 
                            "These tags are already present:", 
                            'tagid', 
                            'name'  );
    if (!(promptInput("Add repeat tags anyway? (y/n) > ") == 'y')) {
        $tags = array_diff( $tags, 
                            array_column($existingTags, 'name') );
    }

    echo "Inserting new tags:\n" . implode(', ', $tags) . "\n";

    $queryResult = queryInsertRecords(  $pdoConn,
                                        'tag', 
                                        ['name'], 
                                        array_map(  fn($tag) => [$tag], 
                                                    $tags   ), 
                                        $promptToCommit, 
                                        $promptCommitMessage
    );
    // queryInsertRecords() already checks for commit.
    return $queryResult;
}

/**
 * Summary of Briel\getFileWidthPaths
 * @param int $pageid
 * @param \PDO $pdoConn
 */
function getFileWidthPaths(int $pageid, \PDO $pdoConn) {
    
    if (    $selectFile = $pdoConn->prepare(<<<STMT
                                        SELECT width, path FROM file 
                                            WHERE pageid = ?;
                                        STMT)
            AND $selectFile->execute([$pageid]) ) {

        return $selectFile->fetchAll(\PDO::FETCH_KEY_PAIR);
        // FETCH_KEY_PAIR writes `path` values into an array indexed by `width`
    } else {
        return false;
    }
}

/**
 * Summary of Briel\isFilePath
 * @param string $str
 * @param string $fileExt
 * @return bool|int Returns 1 on a find, 0 on no find, or false on some other failure
 */
function isFilePath(string $str, string $fileExt = ".+") {
    return preg_match('(.*[\\\/].+\.' . "$fileExt)", $str);
}

/**
 * Summary of Briel\generatePageRecordInteractive
 * @param \PDO $pdoConn
 * @return array
 */
function generatePageRecordInteractive(\PDO $pdoConn) {
    echo "Generating new page record...\n";
    
    // Set title
    $title = promptInput("Enter title: > ");
    $findFileFromTitle = $pdoConn->prepare("SELECT path FROM page WHERE title = ?;");
    $existingTitle = $pdoConn->prepare(
            "SELECT EXISTS(SELECT title FROM page WHERE title = ?);");                                 
    $useAnyway = false;
    while (executeAndFetchScalar($existingTitle, $title) != 0 AND !$useAnyway) {
        echo "Warning: `$title` already exists in page at "
             . executeAndFetchScalar($findFileFromTitle, $title)
             . "\n";
        if (promptInput("Use this title anyway? (y/n) > ") == "y") {
            $useAnyway = true;
        } else $title = promptInput("Enter title: > ");
    }
    
    // Set path
    $useAnyway = false;
    $path = promptPathHTML();
    while (!isFilePath($path, "html") 
           OR (file_exists($path) AND !$useAnyway)) {
        if (!isFilePath($path, "html")) {
            echo "Error: `$path` is not a path for an HTML file.\n";
            $path = promptPathHTML();
        } else if (file_exists($path)) {
            if (promptInput("Warning: `$path` already exists.\n"
                            . 'Use it anyway? (y/n) > ') == "y") {
                $useAnyway = true;
            } else $path = promptPathHTML();
        }
    }

    // Get image description
    $imageDesc = promptInput("Enter comic page description (or a path to it):\n> ");
    if (is_readable($imageDesc)) {
        $imageDesc = file_get_contents($imageDesc);
    }

    // Get spread ID
    $getSpreadID = $pdoConn->prepare("SELECT spreadid FROM spread WHERE spreadtype = ?;");
    $spreadID = executeAndFetchScalar(
            $getSpreadID, 
            (promptInput("Double spread? (y/n) > ") == "y") ? "double" : "normal"
    );

    // Get path of a special style (if present)
    $stylePath = promptInput("Enter path to special style (or 'n' if none):\n> ");
    while (!isFilePath($stylePath, "css") AND $stylePath != "n") {
        echo "Error: $stylePath not a css file.\n";
        $stylePath = promptInput("Enter path to special style (or 'n' if none):\n> ");
    }
    if ($stylePath == "n") $stylePath = null;

    // pageid is automatically generated upon inserting these values.
    return array_combine(PAGEINSERTCOLUMNS, [   $title, 
                                                $path, 
                                                $imageDesc, 
                                                $spreadID, 
                                                $stylePath  ]);
}

function orderContinues(array $record, string $key) {
    return $record !== false AND $record[$key] !== null;
}

function nextOrderContinues(array $targetRecord) {
    return $targetRecord !== false AND $targetRecord['targetid'] !== null;
}

function prevOrderContinues(array $sourceRecord) {
    return $sourceRecord !== false AND $sourceRecord['sourceid'] !== null;
}

/**
 * Summary of Briel\orderPageIDs
 * @param array $pageIDs
 * @param \PDO $pdoConn
 * @param null|\PDOStatement $getSource
 * @param null|\PDOStatement $getTarget
 * @throws \Exception
 * @return array A zero-indexed array of page IDs
 */
function orderPageIDs(  array $pageIDs, 
                        \PDO $pdoConn, 
                        ?\PDOStatement $getSource = null, 
                        ?\PDOStatement $getTarget = null    ) {
    if (!$getSource) $getSource = getPrevPageFromIDStmt($pdoConn);
    if (!$getTarget) $getTarget = getNextPageFromIDStmt($pdoConn);
    $pageIDsOrdered = [];
    $initKey = array_key_first($pageIDs);
    $page = $pageIDs[$initKey];
    $remainingPageIDs = $pageIDs;

    $getSource->execute([$page]);
    for (   $source = $getSource->fetch(\PDO::FETCH_ASSOC); 
            !empty($remainingPageIDs) AND prevOrderContinues($source); 
            $getSource->execute([$source['sourceid']]), 
                    $source = $getSource->fetch(\PDO::FETCH_ASSOC)  ) {

        if (($key = array_search($source['sourceid'], $remainingPageIDs)) !== false) {
            $pageIDsOrdered = [$source['sourceid'], ...$pageIDsOrdered];
            unset($remainingPageIDs[$key]);
        }
    }

    $pageIDsOrdered = [...$pageIDsOrdered, $page];
    unset($remainingPageIDs[$initKey]);

    $getTarget->execute([$page]);
    for (   $target = $getTarget->fetch(\PDO::FETCH_ASSOC); 
            !empty($remainingPageIDs) AND nextOrderContinues($target); 
            $getTarget->execute([$target['targetid']]), 
                    $target = $getTarget->fetch(\PDO::FETCH_ASSOC)) {

        if (($key = array_search($target['targetid'], $remainingPageIDs)) !== false) {
            $pageIDsOrdered = [...$pageIDsOrdered, $target['targetid']];
            unset($remainingPageIDs[$key]);
        }
    }

    if ($remainingPageIDs != []) {
        throw new \Exception(   "Malformed ID list, some pages here are not orderable:\n"
                                . var_export($remainingPageIDs, true)   );
    }

    return $pageIDsOrdered;
}

function getFirstPageID(\PDO $pdoConn, 
                        array $pageIDs, 
                        ?\PDOStatement $getSource = null) {
    if (!$getSource) $getSource = getPrevPageFromIDStmt($pdoConn);
    
    $firstID = array_first($pageIDs);
    $getSource->execute([$firstID]);
    for (   $source = $getSource->fetch(\PDO::FETCH_ASSOC); 
            !empty($pageIDs) AND prevOrderContinues($source); 
            $getSource->execute([$source]), 
                    $source = $getSource->fetch(\PDO::FETCH_ASSOC)) {

        if (($index = array_search($source['sourceid'], $pageIDs)) !== false) {
            $firstID = $source['sourceid'];
            unset($pageIDs[$index]);
        }
    }

    return $firstID;
}

function getLastPageID( \PDO $pdoConn, 
                        array $pageIDs, 
                        ?\PDOStatement $getTarget = null    ) {
    if (!$getTarget) $getTarget = getNextPageFromIDStmt($pdoConn);
    
    $lastID = array_last($pageIDs);
    $getTarget->execute([$lastID]);
    for (   $target = $getTarget->fetch(\PDO::FETCH_ASSOC); 
            !empty($pageIDs) AND nextOrderContinues($target); 
            $getTarget->execute([$target]), 
                    $target = $getTarget->fetch(\PDO::FETCH_ASSOC)) {

        if (($index = array_search($target['targetid'], $pageIDs)) !== false) {
            $lastID = $target['targetid'];
            unset($pageIDs[$index]);
        }
    }

    return $lastID;
}

/**
 * Summary of Briel\orderPageRecords
 * Not a fast function! Use sparingly.
 * @param \PDO $pdoConn
 * @param array $pageRecords
 * @param ?\PDOStatement $getSource
 * @param ?\PDOStatement $getTarget
 * @throws \Exception if some of the pages in `$pageRecords` are not orderable.
 * @return array 0-indexed array of page records in order defined by `pageorder` table.
 */
function orderPageRecords(  \PDO $pdoConn, 
                            array $pageRecords, 
                            ?\PDOStatement $getSource = null, 
                            ?\PDOStatement $getTarget = null    ) {
    if (!$getSource) $getSource = getPrevPageFromIDStmt($pdoConn);
    if (!$getTarget) $getTarget = getNextPageFromIDStmt($pdoConn);
    // re-index pageRecords by page ID (check `array_column` documentation)
    $pageRecords = array_column($pageRecords, null, 'pageid');
    $pageRecordsOrdered = [];

    $initID = array_key_first($pageRecords);
    $initRecord = $pageRecords[$initID];
    $getSource->execute([$initRecord['pageid']]);
    for (   $source = $getSource->fetch(\PDO::FETCH_ASSOC); 
            !empty($pageRecords) AND prevOrderContinues($source); 
            $getSource->execute([$source]), $source = $getSource->fetch(\PDO::FETCH_ASSOC)  ) {

        if (\array_key_exists($source['sourceid'], $pageRecords)) {
            $pageRecordsOrdered = [$pageRecords[$source['sourceid']], ...$pageRecordsOrdered];
            unset($pageRecords[$source['sourceid']]);
        }
    }

    $pageRecordsOrdered = [...$pageRecordsOrdered, $initRecord];
    unset($pageRecords[$initID]);

    $getTarget->execute([$initID]);
    for (   $target = $getTarget->fetch(\PDO::FETCH_ASSOC); 
            !empty($pageRecords) AND nextOrderContinues($target); 
            $getTarget->execute([$target]), 
                    $target = $getTarget->fetch(\PDO::FETCH_ASSOC)  ) {

        if (\array_key_exists($target['targetid'], $pageRecords)) {
            $pageRecordsOrdered = [...$pageRecordsOrdered, $pageRecords[$target['targetid']]];
            unset($pageRecords[$target['targetid']]);
        }
    }
    
    if ($pageRecords != []) {
        throw new \Exception(   "Malformed record list, some pages here are not orderable.\n"
                                . var_export($pageRecords, true)   );
    }

    return $pageRecordsOrdered;
}

function upList($pageID,
                $getSource, 
                $delRowByTarget) {
    $getSource->execute([$pageID]);
    if (prevOrderContinues($sourceRec = $getSource->fetch(\PDO::FETCH_ASSOC))) {
        $delRowByTarget->execute([$pageID]);
        $listAbove = upList($sourceRec["sourceid"], 
                            $getSource, 
                            $delRowByTarget);
        $listAbove[] = $pageID; // append
        return $listAbove; 
    } else {    // No entries where sourceid = $pageID and targetid != null
        return [$pageID];
    }
}

function downList($pageID, $getTarget, $delRowBySource) {
    $getTarget->execute([$pageID]);
    if (nextOrderContinues($targetRec = $getTarget->fetch(\PDO::FETCH_ASSOC))) {
        $delRowBySource->execute([$pageID]);
        $listBelow = downList(  $targetRec["targetid"], 
                                $getTarget, 
                                $delRowBySource );
        array_unshift($listBelow, $pageID); // prepend
        return $listBelow;
    } else {    // No entries where targetid = $pageID and sourceid != null
        return [$pageID];
    }
}

/**
 * Summary of Briel\getPageOrderLists
 * @param \PDO $pdoConn
 * @return array[]
 */
function getPageOrderLists(\PDO $pdoConn) {
    $pdoConn->exec(<<<STMT
                CREATE TEMPORARY TABLE temppageorder 
                    AS SELECT * FROM pageorder;
                STMT);

    $rowExists = $pdoConn->prepare(
            "SELECT sourceid, targetid FROM temppageorder LIMIT 1;");

    $getTarget = getNextIDFromIDStmt($pdoConn, 'temppageorder');
    $getSource = getPrevIDFromIDStmt($pdoConn, 'temppageorder');

    $delRowByTarget = $pdoConn->prepare(
            "DELETE FROM temppageorder WHERE targetid = ?;");

    $delRowBySource = $pdoConn->prepare(
            "DELETE FROM temppageorder WHERE sourceid = ?;");
    
    // May have multiple separate path graphs (chains of pages)
    // so we have lists rather than a single list
    $orderLists = [];
    $rowExists->execute();
    for (   $record = $rowExists->fetch(\PDO::FETCH_ASSOC);
            $record !== false; 
            $rowExists->execute(), $record = $rowExists->fetch(\PDO::FETCH_ASSOC)  ) {
        $orderLists[] = [   ...upList(  $record['sourceid'],
                                        $getSource,
                                        $delRowByTarget ), 
                            ...downList($record['targetid'],
                                        $getTarget, 
                                        $delRowBySource)
                        ];
        $delRowBySource->execute([$record['sourceid']]);
    }

    $pdoConn->exec("DROP TEMPORARY TABLE temppageorder;");

    return $orderLists;
}

function getEndOfOrderID(   string $orderTableName, 
                            int $id, 
                            \PDO $pdoConn, 
                            ?\PDOStatement $getTarget = null    ) {
    if (!$getTarget) $getTarget = getNextIDFromIDStmt($pdoConn, $orderTableName);
    $getTarget->execute([$id]);
    $lastTargetID = $id;
    while (nextOrderContinues($lastTarget = $getTarget->fetch(\PDO::FETCH_ASSOC))) {
        $lastTargetID = $lastTarget['targetid'];
        $getTarget->execute([$lastTargetID]);
    }

    return $lastTargetID;
}

function getEndOfPageOrderID(int $pageID, \PDO $pdoConn, ?\PDOStatement $getTarget = null) {
    return getEndOfOrderID('pageorder', $pageID, $pdoConn, $getTarget);
}

function getEndOfUpdateOrderID(int $updateID, \PDO $pdoConn, ?\PDOStatement $getTarget = null) {
    return getEndOfOrderID('comicupdateorder', $updateID, $pdoConn, $getTarget);
}

function insertAfter(   string $orderTableName, 
                        int $prevID, 
                        int $newID, 
                        \PDO $pdoConn, 
                        ?\PDOStatement $getTarget = null, 
                        bool $promptToCommit = false, 
                        string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;

    if (!$getTarget) $getTarget = getNextIDFromIDStmt($pdoConn, $orderTableName);
    $nextID = executeAndFetchScalar($getTarget, $prevID);
    if ($nextID === false) $nextID = null;
    
    $insert = $pdoConn->prepare(<<<STMT
                            UPDATE $orderTableName SET targetid = :newID 
                                WHERE sourceid = :prevID;
                            INSERT INTO $orderTableName 
                                VALUE (:newID, :nextID);
                            STMT);
    try {
        $success = $insert->execute([   ":newID"  => $newID, 
                                    ":prevID" => $prevID, 
                                    ":nextID" => $nextID    ]);
        if ($success) {
            if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);
        } else {
            promptRollback($pdoConn, 'Insertion in order failed.');
        }
        return $success;
    } catch (\PDOException $e) {
        promptRollback($pdoConn, $e->getMessage());
        return false;
    }
}


function insertPageAfter(   int $prevPageID, 
                            int $newPageID, 
                            \PDO $pdoConn, 
                            ?\PDOStatement $getTarget = null, 
                            bool $promptToCommit = false,
                            string $promptCommitMessage = ''    ) {
    return insertAfter( 'pageorder', 
                        $prevPageID, 
                        $newPageID, 
                        $pdoConn, 
                        $getTarget, 
                        $promptToCommit, 
                        $promptCommitMessage    );
}

function insertUpdateAfter( int $prevUpdateID, 
                            int $newUpdateID, 
                            \PDO $pdoConn, 
                            ?\PDOStatement $getTarget = null, 
                            bool $promptToCommit = false,
                            string $promptCommitMessage = ''    ) {
    return insertAfter( 'comicupdateorder', 
                        $prevUpdateID, 
                        $newUpdateID, 
                        $pdoConn, 
                        $getTarget, 
                        $promptToCommit, 
                        $promptCommitMessage    );
}

function getMostRecent($tableName, $idName, \PDO $pdoConn) {
    return queryAndFetchScalar(
            "SELECT $idName FROM $tableName ORDER BY postdate DESC LIMIT 1;", 
            $pdoConn
    );
}

function getMostRecentPage(\PDO $pdoConn) {
    return getMostRecent('page', 'pageid', $pdoConn);
}

function getMostRecentUpdate(\PDO $pdoConn) {
    return getMostRecent('comicupdate', 'updateid', $pdoConn);
}

function getEndofRecentPageOrderID(\PDO $pdoConn) {
    return getEndOfPageOrderID(getMostRecentPage($pdoConn), $pdoConn);
}

function getEndofRecentUpdateOrderID(\PDO $pdoConn) {
    return getEndOfUpdateOrderID(getMostRecentUpdate($pdoConn), $pdoConn);
}

/**
 * Summary of Briel\insertAtEnd
 * Attempts to insert the ID into the order table after the furthest forward entry.
 * @param string $orderTableName
 * @param string $tableName
 * @param string $idName
 * @param int $newID
 * @param \PDO $pdoConn
 * @param mixed $getTarget
 * @param bool $promptToCommit
 * @param string $promptCommitMessage
 * @return bool
 */
function insertAtEnd(   string $orderTableName, 
                        string $tableName, 
                        string $idName, 
                        int $newID, 
                        \PDO $pdoConn, 
                        ?\PDOStatement $getTarget = null, 
                        bool $promptToCommit = true, 
                        string $promptCommitMessage = ''    ) {
    return insertAfter(
            $orderTableName, 
            getEndOfOrderID($orderTableName, 
                            getMostRecent($tableName, $idName, $pdoConn), 
                            $pdoConn, 
                            $getTarget),
            $newID, 
            $pdoConn, 
            $getTarget, 
            $promptToCommit, 
            $promptCommitMessage
    );
}

function insertUpdateAtEnd( int $newID, 
                            \PDO $pdoConn, 
                            ?\PDOStatement $getTarget = null, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = ''    ) {
    return insertAtEnd( 'comicupdateorder', 
                        'comicupdate', 
                        'updateid', 
                        $newID, 
                        $pdoConn, 
                        $getTarget,
                        $promptToCommit, 
                        $promptCommitMessage    );
}

function insertPageAtEnd(   int $newID, 
                            \PDO $pdoConn, 
                            ?\PDOStatement $getTarget = null, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = ''    ) {
    return insertAtEnd( 'pageorder', 
                        'page', 
                        'pageid', 
                        $newID, 
                        $pdoConn, 
                        $getTarget,
                        $promptToCommit, 
                        $promptCommitMessage    );
}

function insertPageBefore($nextPageID, $newPageID, \PDO $pdoConn) {
    $getPrev = $pdoConn->prepare(<<<STMT
                            SELECT sourceid FROM pageorder
                                WHERE targetid = ?;
                            STMT);
    $prevPageID = executeAndFetchScalar($getPrev, $nextPageID);

    $insert = $pdoConn->prepare(<<<STMT
                            UPDATE pageorder SET sourceid = :newPageID 
                                WHERE targetid = :nextPageID;
                            INSERT INTO pageorder 
                                VALUE (:prevPageID, :newPageID);
                            STMT);
    return $insert->execute([":newPageID"  => $newPageID, 
                             ":prevPageID" => $prevPageID, 
                             ":nextPageID" => $nextPageID]);
}

function deletePageAfter(int $pageID, \PDO $pdoConn, ?\PDOStatement $getTarget) {
    if (!$getTarget) $getTarget = getNextPageFromIDStmt($pdoConn);
    $getTarget->execute([$pageID]);
    $target = $getTarget->fetch(\PDO::FETCH_ASSOC);
    if ($target !== false AND ($prevPageID = $target['targetid']) !== null) {
    $delPage = $pdoConn->prepare(<<<STMT
                SET @nextID = (
                    SELECT targetid FROM pageorder
                        WHERE sourceid = :pageID AND targetid IS NOT NULL
                        LIMIT 1
                );
                DELETE FROM pageorder WHERE sourceid = :pageID AND targetid IS NOT NULL;
                UPDATE pageorder SET targetID = @nextID
                    WHERE sourceID = :prevID;
                STMT);
    
        return $delPage->execute([  ":pageID" => $pageID, 
                                    ":prevID" => $prevPageID    ]);
    } else {
        return true; // Vacuously I guess
    }
}

function appendPages(   array $pageIDs, 
                        \PDO $pdoConn, 
                        ?array $lastPageID = null, 
                        bool $promptToCommit = true, 
                        string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;

    if ($lastPageID === null) $lastPageID = getEndofRecentPageOrderID($pdoConn);

    $records = [];
    $records[] = [$lastPageID, $pageIDs[0]];
    for ($i = 1; $i < \count($pageIDs); $i++) {
        $records[] = [$pageIDs[$i-1], $pageIDs[$i]];
    }
    return queryInsertRecords(  $pdoConn, 
                                "pageorder", 
                                ["sourceid", "targetid"],
                                $records, 
                                $promptToCommit, 
                                $promptCommitMessage    );
}

/**
 * Summary of Briel\associateInteractive
 * 
 * Associates tags with pages, contwarnings with pages, files with pages, 
 * or pages with updates
 * @param array $record A page or update record.
 * @param string $leafTableType Can be 'tag', 'contwarning', 'file', or 'page'
 * @param \PDO $pdoConn
 */
function associateInteractive(  array $record,
                                string $leafTableType, 
                                \PDO $pdoConn, 
                                bool $promptToCommit = true, 
                                string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;

    echo "Associating {$leafTableType}s with `{$record['title']}`...\n";

    $leafName = null;
    $leafIDName = "{$leafTableType}id";
    $leafTableAssocName = null;
    $rootName = null;
    $rootIDName = null;
    $rootTableName = null;
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
            $leafName = 'path';
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
            array_map(  fn($rec) => str_pad($rec[$leafIDName], 8, '_') . $rec[$leafName], 
                        $recs   )
    ;
    $leaves = $pdoConn->query("SELECT $leafIDName, $leafName FROM $leafTableType;")
                      ->fetchAll(\PDO::FETCH_ASSOC);

    echo attributeTableStr( $leaves, 
                            "$leafTableType list:", 
                            $leafIDName, 
                            $leafName   );
    
    $pdoConn->exec(
        "CREATE TEMPORARY TABLE associd ($leafIDName int unsigned);"
    );
    $inputStr = promptInput("Enter $leafTableType IDs separated by commas, "
                            . "or a ? followed by a regular expression for "
                            . "$leafTableType names/titles.\n> ");
    if (preg_match("/^(\d+,\s*)*\d+$/", $inputStr)) {
        queryInsertRecords($pdoConn, 
                           "associd", 
                           [$leafIDName], 
                           array_map('trim', explode(",", $inputStr)));

        if (!empty($notRealIDs = $pdoConn->query(<<<STMT
                                        TABLE associd 
                                            EXCEPT 
                                        SELECT $leafIDName FROM $leafTableType;
                                        STMT)
                                ->fetchALL(\PDO::FETCH_COLUMN))) {
            promptRollback( $pdoConn, 
                            "Error: page IDs [" . implode(', ', $notRealIDs)
                                    . "] don't correspond to existing {$leafTableType}s.");
            return false;
        }

    } else if (\strlen($inputStr) > 0 AND $inputStr[0] == "?") {
        $regExp = $pdoConn->prepare(<<<STMT
                        INSERT INTO associd ($leafIDName)
                            SELECT $leafIDName FROM $leafTableType
                                WHERE REGEXP_LIKE($leafName, ?, 'c'); 
                        STMT); // 'c' enforces case-sensitivity
                                    
        $regExp->execute([substr($inputStr,1)]);

    } else {
        promptRollback($pdoConn, "'$inputStr' invalid.");
        echo "Returning...\n";
        return false;
    }
    
    $assocSelect = $pdoConn->prepare(<<<STMT
            SELECT $leafIDName, $leafName 
            FROM (
                SELECT $leafIDName FROM 
                    associd INNER JOIN $leafTableType USING ($leafIDName)
                EXCEPT 
                SELECT $leafIDName FROM $leafTableAssocName
                    WHERE $rootIDName = ?
            ) AS t INNER JOIN $leafTableType USING ($leafIDName);
            STMT);
    $assocSelect->execute([$record[$rootIDName]]);

    $alreadyAssocSelect = $pdoConn->prepare(<<<STMT
            SELECT $leafIDName, $leafName 
            FROM (
                SELECT $leafIDName FROM 
                    associd INNER JOIN $leafTableType USING ($leafIDName)
                INTERSECT 
                SELECT $leafIDName FROM $leafTableAssocName
                    WHERE $rootIDName = ?
            ) AS t INNER JOIN $leafTableType USING ($leafIDName);
            STMT);
    $alreadyAssocSelect->execute([$record[$rootIDName]]);

    echo "\n-----------------------------------------\n"
         . "{$leafTableType}s to associate with `{$record[$rootName]}`:\n" 
         . "ID      Name/Title/Path\n"
         . implode("\n", 
                    $recsToStrs($assocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "\n-----------------------------------------\n"
         . "{$leafTableType}s already associated with `{$record[$rootName]}`:\n" 
         . "ID      Name/Title/Path\n"
         . implode("\n", 
                    $recsToStrs($alreadyAssocSelect->fetchAll(\PDO::FETCH_ASSOC)))
         . "\n_________________________________________\n";

    $trimExistingLinks = $pdoConn->prepare(<<<STMT
            DELETE FROM associd 
                WHERE $leafIDName IN( 
                    SELECT $leafIDName FROM $leafTableAssocName 
                        WHERE $rootIDName = ?
                )
            ;
            STMT);
    $trimExistingLinks->execute([$record[$rootIDName]]);

    if (promptInput("Okay to associate? (y/n) > ") == "y") {
        if ($leafTableType == 'file') {
            $assoc = $pdoConn->prepare(<<<STMT
                    UPDATE $leafTableAssocName SET $rootIDName = ?
                        WHERE $leafIDName IN(TABLE associd);
                    STMT);
            
        } else {
            $assoc = $pdoConn->prepare(<<<STMT
                    INSERT INTO $leafTableAssocName 
                        ($rootIDName, $leafIDName)
                    SELECT ?, $leafIDName FROM associd;
                    STMT);
        }
        $assoc->execute([$record[$rootIDName]]);
        echo "Files associated.\n";
        $leafIDs = $pdoConn ->query('TABLE associd;')
                            ->fetchAll(\PDO::FETCH_COLUMN);
        $pdoConn->exec("DROP TEMPORARY TABLE associd;"); 
        if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);
        return $leafIDs;
    
    } else {
        echo "Okay. Returning without associating...\n";
        if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);
        return false;
    }
}

function associateTagsInteractive(  array $pageRecord, 
                                    \PDO $pdoConn, 
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage  = ''   ) {
    return associateInteractive($pageRecord, 'tag', $pdoConn, $promptToCommit, $promptCommitMessage);
}

function associateCWsInteractive(   array $pageRecord, 
                                    \PDO $pdoConn, 
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage = ''    ) {
    return associateInteractive($pageRecord, 
                                'contwarning', 
                                $pdoConn, 
                                $promptToCommit, 
                                $promptCommitMessage);
}

function associatePagesInteractive( array $updateRecord, 
                                    \PDO $pdoConn, 
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage = '') {
    return associateInteractive($updateRecord, 
                                'page', 
                                $pdoConn, 
                                $promptToCommit, 
                                $promptCommitMessage);
}

function associateFilesInteractive( array $pageRecord, 
                                    \PDO $pdoConn, 
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage = ''    ) {
    return associateInteractive($pageRecord, 
                                'file', 
                                $pdoConn, 
                                $promptToCommit, 
                                $promptCommitMessage);
}

function appendGeneratePageRecords( \PDO $pdoConn, 
                                    ?int $lastPageID = null,
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;

    $records = [];
    $getRecordBack = $pdoConn->prepare(<<<STMT
        SELECT * FROM page WHERE pageid = (
            SELECT MAX(pageid) FROM page 
            WHERE title = ? AND updatedesc = ? AND postdate IS NULL
        );
        STMT);
    echo "Generating page records...\n";
    do {
        $record = generatePageRecordInteractive($pdoConn);
        if (!queryInsertRecords($pdoConn, 'page', PAGEINSERTCOLUMNS, [$record])) {
            promptRollback($pdoConn, 'Inserting page records failed.');
            return false;
        }
        
        $getRecordBack->execute([$record['title']]);
        $record = $getRecordBack->fetch(\PDO::FETCH_ASSOC);

        if (promptInput("Associate files? (y/n) > " == 'y'))
            associateFilesInteractive($record, $pdoConn, promptToCommit: false);

        if (promptInput("Associate tags? (y/n) > " == 'y'))
            associateTagsInteractive($record, $pdoConn, promptToCommit: false);

        if (promptInput("Associate content warnings? (y/n) > " == 'y'))
            associateCWsInteractive($record, $pdoConn, promptToCommit: false);

        if (!appendPages([$record], $pdoConn, $lastPageID, promptToCommit: false)) {
            promptRollback($pdoConn, 'Ording pages failed.');
            return false;
        }

        $records[] = $record;
    } while (promptInput("Generate another page? (y/n) > ") == 'y');

    if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);

    return $records;
}

function generateUpdateRecordInteractive(\PDO $pdoConn) {
    echo "Generating new update record...\n";

    // Set title
    $title = promptInput("Enter title: > ");
    $existingTitle = $pdoConn->prepare(
            "SELECT EXISTS(SELECT title FROM comicupdate WHERE title = ?);");
    $findDateFromTitle = $pdoConn->prepare(
            "SELECT postdate FROM comicupdate WHERE title = ?;");
    $useAnyway = false;
    while (executeAndFetchScalar($existingTitle, $title) != 0 AND !$useAnyway) {
        echo "Warning: `$title` already exists in page from "
             . executeAndFetchScalar($findDateFromTitle, $title)
             . "\n";
        if (promptInput("Use this title anyway? (y/n) > ") == "y") {
            $useAnyway = true;
        } else $title = promptInput("Enter title: > ");
    }

    // Get image description
    $updateDesc = promptInput("Enter update description:\n> ");

    return ['title' => $title, 'updatedesc' => $updateDesc];
}

function defineSearchMatches($matchExactly, $matchOp) {
    $coalesceNulls = fn($field, $str) => "COALESCE($str, $field IS NOT NULL)";
    $tagMatch = fn($key) => <<<CLAUSE
                    $key IN(
                        SELECT DISTINCT token FROM 
                            tagsearch INNER JOIN tagpage USING (tagid)
                                WHERE tagpage.pageid = page.pageid
                    )
                    CLAUSE; 
    $cwMatch = fn($key) => <<<CLAUSE
                    $key IN(
                        SELECT DISTINCT token FROM 
                            cwsearch INNER JOIN contwarningpage 
                                USING (contwarningid)
                                WHERE contwarningpage.pageid = page.pageid
                    )
                    CLAUSE; 
    // SQL requires \\s to output \s, PHP requires \\\s to output \\s.
    $titleExactMatch = fn($key) => 
            $coalesceNulls( 'title', 
                            <<<CLAUSE
                                title REGEXP CONCAT('(^|[-\"\\'*(\\\[\\\s])', 
                                                    $key, 
                                                    '([-\"\\'*)\\\]\\\s!:;,.?]|$)')
                                CLAUSE
                        );
    $titleMatch = $matchExactly ? $titleExactMatch
                                : fn($key) => $coalesceNulls(   'title', 
                                                                "title $matchOp $key"   );
    $dayNameMatch = fn($key) => $coalesceNulls( 'postdate', 
                                                "DAYNAME(postdate) = $key"  );
    $dayOfMonthMatch = fn($key) => $coalesceNulls(  'postdate', 
                                                    "DAYOFMONTH(postdate) = $key"   );
    $monthMatch = fn($key) => $coalesceNulls(   'postdate', 
                                                "MONTHNAME(postdate) = $key"    );
    $yearMatch = fn($key) => $coalesceNulls('postdate', "YEAR(postdate) = $key");
    $descExactMatch = fn($key) => 
            $coalesceNulls( 'imagedesc', 
                            <<<CLAUSE
                                imagedesc REGEXP CONCAT(
                                    '(^|[-\"\\'*(\\\[\\\s])', 
                                    $key, 
                                    '([-\"\\'*)\\\]\\\s!:;,.?]|$)'
                                )
                                CLAUSE
                            );
    $descMatch = $matchExactly 
                    ? $descExactMatch
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

    $allTokens = [];
    for (   $keyIdx = 0, $token = strtok($searchStr, WHITESPACES); 
            $token !== false; 
            $token = strtok(WHITESPACES)    ) {
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
                        array_push( $tokenClauses[$origToken], 
                                    $dayOfMonthMatch($key), 
                                    $dayNameMatch($key) );
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
                        if (!\array_key_exists($token, $tokenExcludeClauses)) 
                            $tokenExcludeClauses[$token] = [];
                        array_push( $tokenExcludeClauses[$token], 
                                    $dayOfMonthMatch($key), 
                                    $dayNameMatch($key) );
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
            if ($include) {
                // 1- or 2-digit number
                if (preg_match('/^\d{1,2}$/', $token)) { 
                    $execList[$key] = $token;
                    $tokenDataSelects['title'][] = $titleExactMatch($key);
                    $tokenDataSelects['dayOfMonth'][] = $dayOfMonthMatch($key);

                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    array_push( $tokenClauses[$origToken], 
                                $titleExactMatch($key), 
                                $dayOfMonthMatch($key)  );

                    if ($searchImgDesc) {
                        if ($matchExactly) {
                            $tokenDataSelects["desc$token"] = $descMatch($key);
                            $tokenClauses[$origToken][] = $descMatch($key);
                        } else $imgDescMatchPhrase[] = $token;
                    }

                // year
                } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
                    $execList[$key] = $token;
                    $tokenDataSelects['title'][] = $titleExactMatch($key);
                    $tokenDataSelects['year'][] = $yearMatch($key);

                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    array_push( $tokenClauses[$origToken], 
                                $titleExactMatch($key), 
                                $yearMatch($key)    );

                    if ($searchImgDesc) {
                        if ($matchExactly){
                            $tokenDataSelects["desc$token"] = $descMatch($key);
                            $tokenClauses[$origToken][] = $descMatch($key);
                        } else $imgDescMatchPhrase[] = $token;                        
                    }

                // string of length over 2
                } else if (\strlen($token) > 2) { 
                    $execList[$key] = $token;
                    $tokenDataSelects['title'][] = $titleMatch($key);
                    $tokenDataSelects['month'][] = $monthMatch($key);
                    $tokenDataSelects['dayName'][] = $dayNameMatch($key);

                    if (!\array_key_exists($token, $tokenClauses)) 
                        $tokenClauses[$origToken] = [];
                    array_push( $tokenClauses[$origToken], 
                                $tagMatch($key), 
                                $cwMatch($key),
                                $titleMatch($key), 
                                $monthMatch($key), 
                                $dayNameMatch($key) );

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
                // 1- or 2-digit number
                if (preg_match('/^\d{1,2}$/', $token)) { 
                    $execList[$key] = $token;
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    array_push( $tokenExcludeClauses[$token], 
                                $titleExactMatch($key), 
                                $dayOfMonthMatch($key)  );

                    if ($searchImgDesc) 
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);

                // year
                } else if (preg_match('/^(19|20|21)\d{2}$/', $token)) {
                    $execList[$key] = $token;
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    array_push( $tokenExcludeClauses[$token], 
                                $titleExactMatch($key), 
                                $yearMatch($key)    );

                    if ($searchImgDesc) 
                        $tokenExcludeClauses[$token][] = $descExactMatch($key);

                // string of length over 2
                } else if (\strlen($token) > 2) { 
                    $execList[$key] = $token;
                    if (!\array_key_exists($token, $tokenExcludeClauses)) 
                        $tokenExcludeClauses[$token] = [];
                    array_push( $tokenExcludeClauses[$token], 
                                $titleMatch($key), 
                                $monthMatch($key), 
                                $dayNameMatch($key));

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

function createTempSearchTables($joinTokens, 
                                $tagTokens,
                                $cwTokens, 
                                $matchOp, 
                                \PDO $pdoConn) {
    $tagSearchDefinition = '';
    $cwSearchDefinition = '';
    $possibleTagMatches = null;
    $possibleCWMatches = null;
    if ($tagTokens OR $joinTokens) {
        $tagSearchDefinition = 
                'WITH joinsearch AS (SELECT * FROM ('
                . valueRows([...\array_keys($joinTokens), ...\array_keys($tagTokens)])
                . <<<CLAUSE
                    ) AS joinsearch (token))
                    SELECT token, tagid, name FROM joinsearch INNER JOIN tag
                        ON name $matchOp token
                CLAUSE;
        $tagSearch = $pdoConn->prepare(
                        "CREATE TEMPORARY TABLE tagsearch AS ($tagSearchDefinition);");
        $tagSearch->execute([...$joinTokens, ...$tagTokens]);
        
        $possibleTagMatches = $pdoConn->query("SELECT name, tagid FROM tagsearch;");
    }

    if ($cwTokens OR $joinTokens) {
        $cwSearchDefinition = 
                'WITH joinsearch AS (SELECT * FROM ('
                . valueRows([...\array_keys($joinTokens), ...\array_keys($cwTokens)])
                . ') AS joinsearch (token)) '
                . "SELECT token, contwarningid, name FROM "
                . "joinsearch INNER JOIN contwarning ON name $matchOp token";
        $cwSearch = $pdoConn->prepare(
                "CREATE TEMPORARY TABLE cwsearch AS ($cwSearchDefinition);");
        $cwSearch->execute([...$joinTokens, ...$cwTokens]);

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
            $execList[":descalias$descSelectIdx"] = $key; 
            $descSelectIdx++;
        }
    }
    $descSelectClause = implode(', ', $descSelectClause);

    $tagSelectClause = [];
    if ($possibleTagMatches) {
        for (   $i = 0; 
                ($tagMatch = $possibleTagMatches->fetch(\PDO::FETCH_ASSOC)) !== false; 
                $i++    ) {
            $tagSelectClause[] = <<<CLAUSE
                    EXISTS( SELECT tagpage.tagid FROM tagpage 
                            WHERE tagpage.pageid = page.pageid 
                                AND tagpage.tagid = :tagid$i
                    ) AS :tagalias$i
                    CLAUSE; 
            $execList[":tagid$i"] = $tagMatch['tagid'];
            $execList[":tagalias$i"] = 'tag' . $tagMatch['name'];
        }
    }
    $tagSelectClause = implode(', ', $tagSelectClause);

    $cwSelectClause = [];
    if ($possibleCWMatches) {
        for (   $i = 0, $cwMatch = $possibleCWMatches->fetch(\PDO::FETCH_ASSOC); 
                $cwMatch !== false; 
                $i++, $cwMatch = $possibleCWMatches->fetch(\PDO::FETCH_ASSOC)   ) {
            $cwSelectClause[] = <<<CLAUSE
                    EXISTS( SELECT contwarningpage.contwarningid 
                            FROM contwarningpage 
                            WHERE contwarningpage.pageid = page.pageid 
                                AND contwarningpage.contwarningid = :cwid$i
                    ) AS :cwalias$i
                    CLAUSE; 
            $execList[":cwid$i"] = $cwMatch['contwarningid'];
            $execList[":cwalias$i"] = 'cw' . $cwMatch['name'];
        }
    }
    $cwSelectClause = implode(', ', $cwSelectClause);

    $tagExcludeWithClause = ($joinExcludeTokens OR $tagExcludeTokens) ? 
            'tagsearchexclude (token, tagid) AS ('
                    . "SELECT token, tagid FROM (\n"
                    . valueRows([...\array_keys($joinExcludeTokens),  
                                    ...\array_keys($tagExcludeTokens)])
                    . "\n) AS excluded (token) INNER JOIN tag " 
                    . "ON tag.name $matchOp excluded.token)"
            : '';
    
    $cwExcludeWithClause = ($joinExcludeTokens OR $cwExcludeTokens) ? 
            "cwsearchexclude (token, contwarningid) AS ("
                    . "SELECT token, contwarningid FROM (\n"
                    . valueRows([...\array_keys($joinExcludeTokens), 
                                    ...\array_keys($cwExcludeTokens)])
                    . "\n) AS excluded (token) INNER JOIN contwarning "  
                    . "ON contwarning.name $matchOp excluded.token)"
            : '';
    
    $singleMatchClause = fn($field, $alias) =>
                            ($tokenDataSelects[$field] ? 
                                '(' . \implode(' OR ', $tokenDataSelects[$field]) 
                                        . ") AS $alias"
                                : "0 AS $alias");
    $selectMatchClause = \implode(
            ', ', 
            \array_filter(  [   $singleMatchClause('title', 'titlematch'), 
                                $singleMatchClause('dayOfMonth', 'dayofmonthmatch'), 
                                $singleMatchClause('year', 'yearmatch'), 
                                $singleMatchClause('month', 'monthmatch'), 
                                $singleMatchClause('dayName', 'daynamematch'), 
                                $tagSelectClause,
                                $cwSelectClause, 
                                $descSelectClause   ], 
                            fn($el) => $el !== '')
    );

    $withClause = [];
    if ($tagSearchDefinition) { 
        $withClause[] = 
                "tagsearch (token, tagid, name) AS ($tagSearchDefinition)";
    }
    if ($cwSearchDefinition) {
        $withClause[] = 
                "cwsearch (token, contwarningid, name) AS ($cwSearchDefinition)";
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
                ARRAY_FILTER_USE_KEY
        );
        $nonspecTokenClauses = \array_filter(  
                $tokenClauses, 
                fn($k) => !preg_match('/^(' . \implode('|', SEARCHFIELDSPECS) . '):/', $k)
                            AND !str_starts_with($k, 'alldesc'), 
                ARRAY_FILTER_USE_KEY
        );
        $whereSelectClause = \implode(
                ' AND ', 
                array_map(  fn($tarr) => '(' . \implode(' OR ', $tarr) . ')', 
                            $specTokenClauses   )
        );
        if ($specTokenClauses AND $nonspecTokenClauses) {
            $whereSelectClause .= ' AND ';
        }
        if ($nonspecTokenClauses) {
            $alldescClause = array_find(   
                    $tokenClauses, 
                    fn($val, $key) => str_starts_with($key, 'alldesc') 
            );
            $whereSelectClause .= 
                '(('
                . \implode(
                        ' AND ', 
                        array_map(  fn($tarr) => '(' . \implode(' OR ', $tarr) . ')', 
                                    $nonspecTokenClauses)
                    )
                . ') OR '
                . (isset($alldescClause) ? $alldescClause[0] : '')
                . ')';
        }
    } else {
        $whereSelectClause = \implode(
                ' AND ', 
                array_map(  fn($tarr) => '(' . \implode(' OR ', $tarr) . ')', 
                            $tokenClauses)
                    );
    }
    
    $whereNotSelectClause = [];
    if ($tokenExcludeClauses) {
        $whereNotSelectClause[] = \implode( 
                ' OR ', 
                array_map(  fn($tarr) => \implode(' OR ', $tarr), 
                            $tokenExcludeClauses    )
        );
    }
    if ($joinExcludeTokens) {
        $whereNotSelectClause[] = <<<CLAUSE
                EXISTS(
                    SELECT tagid 
                    FROM tagsearchexclude INNER JOIN tagpage USING (tagid)
                        WHERE tagpage.pageid = page.pageid
                ) OR EXISTS(
                    SELECT contwarningid 
                    FROM cwsearchexclude INNER JOIN contwarningpage 
                            USING (contwarningid)
                        WHERE contwarningpage.pageid = page.pageid    
                )
                CLAUSE;
    } else {
        if ($tagExcludeTokens) {
            $whereNotSelectClause[] = <<<CLAUSE
                    EXISTS(
                        SELECT tagid 
                        FROM tagsearchexclude INNER JOIN tagpage USING (tagid)
                            WHERE tagpage.pageid = page.pageid
                    )
                    CLAUSE;
        }
        if ($cwExcludeTokens) {
            $whereNotSelectClause[] = <<<CLAUSE
                    EXISTS(
                        SELECT contwarningid 
                        FROM cwsearchexclude INNER JOIN contwarningpage 
                                USING (contwarningid)
                            WHERE contwarningpage.pageid = page.pageid
                    )
                    CLAUSE;
        }
    }
    $whereNotSelectClause = \implode(' OR ', $whereNotSelectClause);

    $whereClause = $whereSelectClause;
    if ($whereSelectClause AND $whereNotSelectClause) $whereClause .= ' AND ';
    if ($whereNotSelectClause) $whereClause .= "NOT ($whereNotSelectClause)";
    
    return [    $execList, 
                $withClause, 
                $selectMatchClause, 
                $whereClause    ];
}

function dropTempTables($joinTokens, $tagTokens, $cwTokens, \PDO $pdoConn) {
    if ($joinTokens OR $tagTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE tagsearch;'); 
    } 
    if ($joinTokens OR $cwTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE cwsearch;'); 
    }
}

function execSearchComicsStmt(  $searchStr, 
                                \PDO $pdoConn, 
                                $matchExactly = false, 
                                $searchImgDesc = false  ) {
    
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

    [   $tagSearchDefinition, 
        $cwSearchDefinition, 
        $possibleTagMatches, 
        $possibleCWMatches  ] = createTempSearchTables( $joinTokens,
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
                                                    $tagSearchDefinition, 
                                                    $cwSearchDefinition, 
                                                    $matchExactly, 
                                                    $searchImgDesc, 
                                                    $matchOp            );

    $getResults = $pdoConn->prepare( 
        ($withClause ? "WITH $withClause " : '')
        . "SELECT page.pageid AS pageid, page.title AS title, "
            . "page.postdate AS postdate, page.path AS path, "
            . "page.imagedesc AS imagedesc"
        . ($selectMatchClause ? 
                ", $selectMatchClause" 
                : '')
        . ' FROM page' 
        . ' WHERE postdate IS NOT NULL AND path IS NOT NULL' 
        . ($whereClause ? 
                " AND $whereClause"
                : '') 
        . " ORDER BY page.postdate DESC;"
        );
    
    $getResults->execute($execList);

    dropTempTables($joinTokens, $tagTokens, $cwTokens, $pdoConn);

    return [$getResults, $execList];
}

/**
 * Summary of Briel\searchComics
 * 
 * Does return all pages with blank search, as you'd expect
 * 
 * @param string $searchStr
 * @param \PDO $pdoConn
 * @param bool $matchExactly
 * @param bool $searchImgDesc
 * @return array<mixed|string>
 */
function searchComics(  string $searchStr, 
                        \PDO $pdoConn, 
                        bool $matchExactly = false, 
                        bool $searchImgDesc = false  ) {
    [$getResults, $execList] = execSearchComicsStmt($searchStr, 
                                                    $pdoConn, 
                                                    $matchExactly, 
                                                    $searchImgDesc);

    for (   $result = $getResults->fetch(\PDO::FETCH_ASSOC); 
            $result !== false; 
            $result = $getResults->fetch(\PDO::FETCH_ASSOC) ) {
        yield $result;
    }
}

function allSearchComics(   string $searchStr, 
                            \PDO $pdoConn, 
                            bool $matchExactly = false, 
                            bool $searchImgDesc = false  ) {

    [$getResults, $execList] = execSearchComicsStmt($searchStr, 
                                                    $pdoConn, 
                                                    $matchExactly, 
                                                    $searchImgDesc);

    $results = $getResults->fetchAll(\PDO::FETCH_ASSOC);

    return [$results, 
            $getResults, 
            $execList];
}

function getTagsFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT name FROM tagpage INNER JOIN tag USING (tagid) 
                WHERE pageid = ?;
            STMT);
}

function getTagsFromMultiplePageIDsStmt(array $numIDs, \PDO $pdoConn) {
    return $pdoConn->prepare(
            "SELECT name FROM tagpage INNER JOIN tag USING (tagid) WHERE pageid IN"
                . rowPlaceholder(\count($numIDs))
                . ";"
    );
}

function getCWsFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT name 
                FROM contwarningpage INNER JOIN contwarning USING (contwarningid)
                WHERE pageid = ?;
            STMT);
}

function getCWsFromMultiplePageIDsStmt(array $numIDs, \PDO $pdoConn) {
    $stmtPrefix = <<<STMT
            SELECT name FROM contwarningpage INNER JOIN contwarning USING (contwarningid) 
                WHERE pageid IN
            STMT;
    return $pdoConn->prepare($stmtPrefix . rowPlaceholder(\count($numIDs)) . ";");
}

function getThumbnailFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT * FROM file LEFT JOIN filepurpose USING (purposeid)
                WHERE pageid = ? AND purpose = 'thumbnail' ORDER BY filesize;
            STMT);
}

function getMinSizeFileFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(
            "SELECT * FROM file WHERE pageid = ? ORDER BY filesize LIMIT 1;");
}

function getNumServablePages(\PDO $pdoConn) {
    return $pdoConn->query(
            'SELECT COUNT(*) FROM page 
                WHERE postdate IS NOT NULL AND path IS NOT NULL;'
            )->fetch(\PDO::FETCH_NUM)[0];
}

function getPageRecordFromIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare("SELECT * FROM page WHERE pageid = ?;");
}

function getUpdateRecordFromIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare("SELECT * FROM comicupdate WHERE updateid = ?;");
}

function getPageIDsFromUpdateIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare("SELECT pageid FROM comicupdatepage WHERE updateid = ?;");
}

function getFirstPageIDOfUpdateFromID(  \PDO $pdoConn, 
                                        int $updateID, 
                                        ?\PDOStatement $getPageFromUpdateID = null, 
                                        ?\PDOStatement $getPrevPageFromID = null    ) {
    if (!$getPageFromUpdateID) $getPageFromUpdateID = getPageIDsFromUpdateIDStmt($pdoConn);
    $getPageFromUpdateID->execute([$updateID]);

    return getFirstPageID( 
            $pdoConn, 
            $getPageFromUpdateID->fetchAll(\PDO::FETCH_COLUMN), 
            $getPrevPageFromID  
    );
}

function getFirstPageRecordOfUpdateFromID(  \PDO $pdoConn, 
                                            int $updateID, 
                                            ?\PDOStatement $getPageFromUpdateID = null, 
                                            ?\PDOStatement $getPrevPageFromID = null, 
                                            ?\PDOStatement $getPageRecordFromID = null    ) {
    if (!$getPageRecordFromID) $getPageRecordFromID = getPageRecordFromIDStmt($pdoConn);
    return executeAndFetch(
            $getPageRecordFromID, 
            [getFirstPageIDOfUpdateFromID(  $pdoConn, 
                                            $updateID, 
                                            $getPageFromUpdateID, 
                                            $getPrevPageFromID  )]
    );
}

function getLastPageIDOfUpdateFromID(   \PDO $pdoConn, 
                                        int $updateID, 
                                        ?\PDOStatement $getPageFromUpdateID = null, 
                                        ?\PDOStatement $getNextPageFromID = null    ) {
    if (!$getPageFromUpdateID) $getPageFromUpdateID = getPageIDsFromUpdateIDStmt($pdoConn);
    $getPageFromUpdateID->execute([$updateID]);

    return getLastPageID( 
            $pdoConn, 
            $getPageFromUpdateID->fetchAll(\PDO::FETCH_COLUMN), 
            $getNextPageFromID  
    );
}

function getLastPageRecordOfUpdateFromID(   \PDO $pdoConn, 
                                            int $updateID, 
                                            ?\PDOStatement $getPageFromUpdateID = null, 
                                            ?\PDOStatement $getNextPageFromID = null, 
                                            ?\PDOStatement $getPageRecordFromID = null    ) {
    if (!$getPageRecordFromID) $getPageRecordFromID = getPageRecordFromIDStmt($pdoConn);
    return executeAndFetch(
            $getPageRecordFromID, 
            [getLastPageIDOfUpdateFromID(   $pdoConn, 
                                            $updateID, 
                                            $getPageFromUpdateID, 
                                            $getNextPageFromID  )]
    );
}

/**
 * Summary of Briel\getPrevIDFromIDStmt
 * @param \PDO $pdoConn
 * @param string $tableName
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a target, `null` if the record exists but
 * the thing is the first in an order, or the ID if the thing does have a preceding 
 * thing. 
 */
function getPrevIDFromIDStmt(\PDO $pdoConn, string $tableName) {
    return $pdoConn->prepare("SELECT sourceid FROM $tableName WHERE targetid = ?;");
}

function getPrevRecordFromIDStmt(   \PDO $pdoConn, 
                                    string $recordTableName, 
                                    string $idName, 
                                    string $orderTableName) {
    return $pdoConn->prepare(<<<STMT
            SELECT * FROM $recordTableName INNER JOIN (
                SELECT * FROM $orderTableName WHERE targetid = ?
            ) ON ($idName = sourceid);
            STMT);
}

function getPrevPageRecordFromIDStmt(   \PDO $pdoConn   ) {
    return getPrevRecordFromIDStmt($pdoConn, 'page', 'pageid', 'pageorder');
}

/**
 * Summary of Briel\getNextIDFromIDStmt
 * @param \PDO $pdoConn
 * @param string $tableName
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a source, `null` if the record exists but
 * the thing is the last in an order, or the ID if the thing does have a subsequent 
 * thing. 
 */
function getNextIDFromIDStmt(\PDO $pdoConn, string $tableName) {
    return $pdoConn->prepare("SELECT targetid FROM $tableName WHERE sourceid = ?;");
}

/**
 * Summary of Briel\getPrevPageFromIDStmt
 * @param \PDO $pdoConn
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a target, `null` if the record exists but
 * the page is the first in an order, or the ID if the page does have a preceding 
 * page. 
 */
function getPrevPageFromIDStmt(\PDO $pdoConn) {
    return getPrevIDFromIDStmt($pdoConn, "pageorder");
}

/**
 * Summary of Briel\getNextPageFromIDStmt
 * @param \PDO $pdoConn
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a source, `null` if the record exists but
 * the page is the last in an order, or the ID if the page does have a subsequent 
 * page. 
 */
function getNextPageFromIDStmt(\PDO $pdoConn) {
    return getNextIDFromIDStmt($pdoConn, "pageorder");
}

/**
 * Summary of Briel\getPrevUpdateFromIDStmt
 * @param \PDO $pdoConn
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a target, `null` if the record exists but
 * the update is the first in an order, or the ID if the update does have a preceding 
 * update. 
 */
function getPrevUpdateFromIDStmt(\PDO $pdoConn) {
    return getPrevIDFromIDStmt($pdoConn, "comicupdateorder");
}

/**
 * Summary of Briel\getNextUpdateFromIDStmt
 * @param \PDO $pdoConn
 * @return bool|\PDOStatement when executed, this statement will return `false` if 
 * there is no record with the given ID as a source, `null` if the record exists but
 * the update is the last in an order, or the ID if the update does have a subsequent 
 * update. 
 */
function getNextUpdateFromIDStmt(\PDO $pdoConn) {
    return getNextIDFromIDStmt($pdoConn, "comicupdateorder");
}

function getPageImgRecordsFromIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT * FROM file LEFT JOIN filepurpose USING (purposeid) 
            WHERE pageid = ? AND purposeid = 1;
            STMT); // `purposeid` of 1 corresponds to a standard image representing a page
}

function getThumbnailRecordsFromIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT * FROM file LEFT JOIN filepurpose USING (purposeid) 
            WHERE pageid = ? AND purposeid = 2;
            STMT); // `purposeid` of 2 corresponds to page thumbnails
}

/**
 * Summary of Briel\backupSearchData
 * @param \PDO $pdoConn [optional]
 * @param mixed $search [optional] Default null. If null, backs up entire search cache.
 * @param mixed $searchDesc [optional]
 * @param mixed $matchExactly [optional]
 * @return void
 */
function backupSearchData(  \PDO $pdoConn, 
                            $search = null, 
                            $searchDesc = false, 
                            $matchExactly = false   ) {
    if ($search === null) {
        $pdoConn->query(<<<STMT
                INSERT INTO searchcachehistory (
                    search, searchimgdesc, matchexactly, numtimes, firstsearched, lastsearched
                ) SELECT search, searchimgdesc, matchexactly, numtimes, firstsearched, lastsearched
                    FROM searchcache;
                STMT);
    } else {
        $insertFromCache = $pdoConn->prepare(<<<STMT
                INSERT INTO searchcachehistory (
                    search, searchimgdesc, matchexactly, numtimes, firstsearched, lastsearched
                ) SELECT search, searchimgdesc, matchexactly, numtimes, firstsearched, lastsearched
                    FROM searchcache 
                    WHERE search = ? AND searchimgdesc = ? AND matchexactly = ?;
                STMT);
        $insertFromCache->execute([ searchcacheKeyFromSearchString($search), 
                                    $searchDesc ? CHECKBOXON : '', 
                                    $matchExactly ? CHECKBOXON : '' ]);
    }
}
?>