<?php

namespace Briel;

require_once 'dbRead.php';

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
                            "colorstyleid", 
                            "note" ];


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
    return str_replace('\\', '/', promptInput(
            "Enter HTML file path (doesn't need to exist):\n> "));
}

function valueRows(array $arr) {
    return 'VALUES ' . \implode(', ', \array_map(fn($a) => "ROW($a)", $arr));
}

function getNoninteractiveFileRecordParts(  string $filePath, 
                                            \PDO $pdoConn, 
                                            array $incompleteRecord = []) {
    $absPath = str_replace('\\', '/', realpath($filePath));

    [$imgWidth, $imgHeight] = getimagesize($absPath);

    $fileExt = substr($filePath, strrpos($filePath, ".") + 1);

    // this will give you a different size than what Windows tells you, 
    // since Windows defines 1 kB = 1024 B, not 1 kB = 1000 B like usual.
    $fileSizeKB = (int) (filesize($filePath) / 1000);

    return [...$incompleteRecord, 
            'path' => $absPath, 
            'width' => $imgWidth, 
            'height' => $imgHeight, 
            'filetype' => $fileExt, 
            'filesize' => $fileSizeKB];
}

function getAspectRatioIDInteractive( \PDO $pdoConn, 
                                    ?\PDOStatement $ratioStatement = null, 
                                    string $ratioStr = "") {
    $ratioStatement ??= $pdoConn->prepare("SELECT ratioid FROM aspectratio WHERE ratio = ?;");
    $fetchedRow = null;
    if (!$ratioStr) $ratioStr = promptInput("Enter aspect ratio (format width:height)\n> ");
    // if execution is unsuccessful or there is no row in the selection
    while (    !($ratioStatement->execute([$ratioStr]))
            or !($fetchedRow = $ratioStatement->fetch(\PDO::FETCH_ASSOC))   ) {
        $ratioStr = promptInput("$ratioStr not a valid ratio. Valid ratios:\n"
                                . \implode( ', ', 
                                            $pdoConn->query("SELECT ratio FROM aspectratio;")
                                                    ->fetchAll(\PDO::FETCH_COLUMN)
                                            )
                                . "\nEnter aspect ratio (format width:height).\n> ");
    }

    return $fetchedRow['ratioid'];
}

function getPurposeIDInteractive(\PDO $pdoConn, ?\PDOStatement $purposeStatement = null) {
    $getPurposeID = $purposeStatement ?? $pdoConn->prepare(
            "SELECT purposeid FROM filepurpose WHERE purpose = ?;");
    if (promptInput(
                "Is this file a whole page, to be displayed for reading? (y/n) > "
            ) == "y") {
        $getPurposeID->execute(['page']);
    } elseif (promptInput("Okay, not a page image. Is it a thumbnail? (y/n) > ") == "y") {
        $getPurposeID->execute(['thumbnail']);
    } elseif (promptInput("Okay, not a thumbnail. Is it a preview? (y/n) > ") == "y") {
        $getPurposeID->execute(['preview']);
    } else {
        echo "Okay, for now it's classified as 'other'.\n";
        $getPurposeID->execute(['other']);
    }

    return $getPurposeID->fetch()[0];
}

/**
 * Summary of Briel\getInteractiveFileRecordParts
 * @param \PDO $pdoConn
 * @param array $incompleteRecord
 * @param string $ratioStr
 * @param \PDOStatement|null $ratioStatement
 * @return array{alttext: bool|string, purposeid: mixed, ratioid: mixed}
 */
function getInteractiveFileRecordParts( \PDO $pdoConn, 
                                        array $incompleteRecord = [],
                                        string $ratioStr = "", 
                                        ?\PDOStatement $ratioStatement = null   ) {
    $alttext = promptInput("Enter alt text (or a path to it):\n> ");
    if (is_readable($alttext)) {
        $alttext = file_get_contents($alttext) ?: $alttext;
    }

    $ratioID = getAspectRatioIDInteractive($pdoConn, $ratioStatement, $ratioStr);

    $purposeID = getPurposeIDInteractive($pdoConn);

    return [...$incompleteRecord, 
            'alttext' => $alttext, 
            'ratioid' => $ratioID, 
            'purposeid' => $purposeID];
}

function generateFileRecordsFromFolderWithMetadata( string $dirPath,
                                                    \PDO $pdoConn, 
                                                    ?\PDOStatement $ratioStatement = null, 
                                                    ?\PDOStatement $purposeStatement = null ) {
    $dirPath = endDirectoryPathWithSlash($dirPath);

    if (!is_dir($dirPath)) {
        echo "$dirPath is not an existing folder. Returning...\n";
        return false;
    } 
    
    if (!file_exists("$dirPath/metadata.json")) {
        echo "metadata.json does not exist in $dirPath. Returning...\n";
        return false;
    }

    $interactiveRecordParts = json_decode(  file_get_contents("$dirPath/metadata.json"), 
                                            associative: true   );
    
    $ratioStatement ??= $pdoConn->prepare("SELECT ratioid FROM aspectratio WHERE ratio = ?;");

    if (!$ratioStatement->execute([$interactiveRecordParts['ratio']])
            OR !($row = $ratioStatement->fetch(\PDO::FETCH_ASSOC))) {
        echo "Metadata provided aspect ratio {$interactiveRecordParts['ratio']} " 
            . "which doesn't match one that we can find.";
        $interactiveRecordParts['ratioid'] = getAspectRatioIDInteractive($pdoConn, $ratioStatement);
    } else {
        $interactiveRecordParts['ratioid'] = $row['ratioid'];
    }
    unset($interactiveRecordParts['ratio']);
    
    $purposeStatement ??= $pdoConn->prepare("SELECT purposeid FROM filepurpose WHERE purpose = ?;");
    if (!$purposeStatement->execute([$interactiveRecordParts['purpose']])
            OR !($row = $purposeStatement->fetch(\PDO::FETCH_ASSOC))) {
        echo "Metadata provided purpose {$interactiveRecordParts['purpose']} " 
            . "which doesn't match one that we can find.";
        $interactiveRecordParts['purposeid'] = getPurposeIDInteractive($pdoConn, $ratioStatement);
    } else {
        $interactiveRecordParts['purposeid'] = $row['purposeid'];
    }
    unset($interactiveRecordParts['purpose']);

    $records = [];
    foreach (scandir($dirPath) as $entry) {
        $path = $dirPath . $entry;
        if (!preg_match('(image/.*)', mime_content_type($path))) continue;
        $records[] = [  ...getNoninteractiveFileRecordParts($path, $pdoConn), 
                        ...$interactiveRecordParts ];
    }

    return $records;
}

function generateFileRecordsFromFolderInteractive(  string $dirPath, 
                                                    \PDO $pdoConn, 
                                                    ?\PDOStatement $ratioStatement = null   ) {
    echo "Generating records from `" . basename($dirPath) . "`...\n";
    if (!is_dir($dirPath)) {
        echo "$dirPath is not an existing folder. Aborting record generation...\n";
        return false;
    }

    $records = [];
    $dirPath = endDirectoryPathWithSlash($dirPath);
    $dirEntries = scandir($dirPath);
    $paths = array_filter(
            array_map(fn($entry) => $dirPath . $entry, $dirEntries), 
            fn($path) => preg_match('(image/.*)', mime_content_type($path))
    );
    echo "Image files found in `$dirPath`:\n" . fileListStr($paths);
    
    $keepGenerating = true;
    while ($paths AND $keepGenerating) {
        $id = promptInput(
                "Enter ID of file to generate a record for it, or `n` to exit.\n> ");
        while (!\in_array($id, array_keys($paths)) AND $id != 'n') {
            $id = promptInput(<<<PROMPT
                    $id not a valid ID.
                    Enter ID of file to generate a record for, or `n` to exit. > 
                    PROMPT);
        }
        if ($id != 'n') {
            $constantFields = getInteractiveFileRecordParts(
                    $pdoConn, ratioStatement: $ratioStatement);

            $idsToRecord = [$id];
            // unset($paths[$id]);

            $tryAgain = false;

            do {
                $idPrompt = promptInput(
                        fileListStr(array_diff($paths, [$paths[$id]]))
                        . "Would you like to associate any other files here with this same "
                        . "information?\n"
                        . "Enter the ID(s) or `n` to exit.\n> "
                );
                
                if ($idPrompt != 'n') { 
                    $ids = null;
                    preg_match_all('/[0-9]+/', $idPrompt, $ids);
                    $ids = array_intersect(array_diff($ids[0], $idsToRecord), array_keys($paths));
                    if ($ids) {
                        echo "Associating files with IDs " . \implode(', ', $ids) . "...\n";
                        $idsToRecord = [  
                            ...$idsToRecord, 
                            ...$ids
                        ];
                        $tryAgain = 
                            (promptInput("Would you like to associate more? (y/n) > ") == 'y');
                        // foreach ($ids as $id) unset($paths[$id]);
                    } else {
                        echo "No matched IDs that weren't already entered.\n";
                        $tryAgain = true;
                    }
                } else {
                    $tryAgain = false;
                }
            } while ($tryAgain);

            if (promptInput(fileListStr($paths) 
                            . "Okay to record all these IDs below as having the same alt text, "
                            . "aspect ratio, and purpose? (y/n)\n" 
                            . \implode(', ', $idsToRecord) . "\n> ") 
                    == "y") {
                foreach ($idsToRecord as $id) {
                    $records[] = [
                            ...getNoninteractiveFileRecordParts($paths[$id], $pdoConn), 
                            ...$constantFields
                    ];
                    unset($paths[$id]);
                }
            }
        }

        $keepGenerating = (promptInput(  "Remaining, unrecorded files:\n" 
                                        . fileListStr($paths)
                                        . "Continue matching? (y/n) > " ) == 'y');
    }

    echo "Either we're out of files to record or you've chosen to stop! Ciao.\n";

    return $records;
}

function generateFileRecordInteractive( string $filePath, 
                                        \PDO $pdoConn, 
                                        string $ratioStr = "", 
                                        ?\PDOStatement $ratioStatement = null   ) {
    echo "Generating record for `" . basename($filePath) . "`...\n";
    if (!is_file($filePath)) {
        echo "$filePath is not an existing file. Aborting record generation...\n";
        return false;
    }

    return [...getNoninteractiveFileRecordParts($filePath, $pdoConn), 
            ...getInteractiveFileRecordParts(   $pdoConn, 
                                                ratioStr: $ratioStr, 
                                                ratioStatement: $ratioStatement )];
}

function endDirectoryPathWithSlash( string $dirPath ) {
    if (!str_ends_with($dirPath, '/') AND !str_ends_with($dirPath, '\\')) $dirPath .= '/';
    return $dirPath;
}

/**
 * Summary of Briel\prepareInsertRecords
 * @param \PDO $pdoConn An existing PDO object.
 * @param string $tableName String containing name of the table in the database
 * @param array<string> $insertColumns Array of strings with titles of columns
 * @param array<array> $records 2D array, indexed by record then by column. 
 * Fuck! This messes up given that individual records are *also* arrays. 
 * Make sure to always put the records in an array, even if there's just one.
 */
function prepareInsertRecords(  \PDO $pdoConn, 
                                string $tableName, 
                                array $insertColumns, 
                                array $records    ) {
    $row = rowPlaceholder(\is_array($insertColumns) ? \count($insertColumns) : 1);
    $allPlaceholderStr = \is_array(array_first($records)) ? 
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

    echo "Records: ";
    var_export($records);

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
    // echo "Insert statement: ";
    // var_export($insertStatement);
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
    for ($i = 0; $i < \count($records); $i++) {
        $records[$i] = orderArrayByOrderedKeys(FILEINSERTCOLUMNS, $records[$i]);
    }

    $queryResult = queryInsertRecords(  $pdoConn, 
                                        "file", 
                                        FILEINSERTCOLUMNS, 
                                        $records, 
                                        $promptToCommit, 
                                        $promptCommitMessage );
    return $queryResult;
}

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

    for ($i = 0; $i < \count($records); $i++) {
        $records[$i] = orderArrayByOrderedKeys(PAGEINSERTCOLUMNS, $records[$i]);
    }

    $queryResult = queryInsertRecords(  $pdoConn, 
                                        'page',
                                        PAGEINSERTCOLUMNS, 
                                        $records, 
                                        promptToCommit: false);
    if ($promptToCommit) promptCommit(
                $pdoConn, "Query result is:\n" . print_r($queryResult, true) . "\n");
    return $queryResult;
}

function insertNewLeaves(   string $leafTableName, 
                            array $leaves, 
                            \PDO $pdoConn, 
                            bool $promptToCommit = true, 
                            string $promptCommitMessage = ''    ) {
    if (!tryBeginTransaction($pdoConn)) return false;
    $idName = '';
    switch ($leafTableName) {
        case 'tag':
            $idName = 'tagid';
            break;
        case 'contwarning':
            $idName = 'contwarningid';
            break;
        default: 
            echo "`$leafTableName` not a valid table for inserting these. Returning...\n";
            return false;
    }
    
    $leaves = array_unique($leaves);
    $matchExistingLeaves = 
            $pdoConn->prepare("SELECT * FROM $leafTableName WHERE name IN "
                                . rowPlaceholder(\count($leaves))
                                . ";");
    $matchExistingLeaves->execute($leaves);
    $existingLeaves = $matchExistingLeaves->fetchAll(\PDO::FETCH_ASSOC);
    if ($existingLeaves) {
        echo attributeTableStr( $existingLeaves, 
                                "These {$leafTableName}s are already present:", 
                                $idName, 
                                'name'  );
        if (promptInput("Add repeat {$leafTableName}s anyway? (y/n) > ") != 'y') {
            $leaves = array_diff($leaves, array_column($existingLeaves, 'name'));
        }
    }
    
    echo "Inserting new {$leafTableName}s:\n" . implode(', ', $leaves) . "\n";

    $queryResult = queryInsertRecords(  $pdoConn,
                                        $leafTableName, 
                                        ['name'], 
                                        array_map(fn($leaf) => [$leaf], $leaves), 
                                        $promptToCommit, 
                                        $promptCommitMessage
    );
    // queryInsertRecords() already checks for commit.
    return $queryResult;
}

function insertNewTags( array $tags, 
                        \PDO $pdoConn, 
                        bool $promptToCommit = true, 
                        string $promptCommitMessage = '') {
    return insertNewLeaves('tag', $tags, $pdoConn, $promptToCommit, $promptCommitMessage);
}

function insertNewCWs( array $cws, 
                        \PDO $pdoConn, 
                        bool $promptToCommit = true, 
                        string $promptCommitMessage = '') {
    return insertNewLeaves('contwarning', $cws, $pdoConn, $promptToCommit, $promptCommitMessage);
}

function getPageTitleInteractive(\PDO $pdoConn) {
    $title = promptInput("Enter title: > ");
    $findFileFromTitle = $pdoConn->prepare("SELECT path FROM page WHERE title = ?;");
    $existingTitle = $pdoConn->prepare(
            "SELECT EXISTS(SELECT NULL FROM page WHERE title = ?);");                                 
    $useAnyway = false;
    while (executeAndFetchScalar($existingTitle, $title) != 0 AND !$useAnyway) {
        echo "Warning: `$title` already exists in page at "
             . executeAndFetchScalar($findFileFromTitle, $title)
             . "\n";
        if (promptInput("Use this title anyway? (y/n) > ") == "y") {
            $useAnyway = true;
        } else $title = promptInput("Enter title: > ");
    }

    return $title;
}

function getPagePathInteractive() {
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

    if (!file_exists($path)) file_put_contents($path, '');
    return realpath($path);
}

function getImgDescInteractive() {
    $imageDesc = promptInput("Enter comic page description (or a path to it):\n> ");
    if (is_readable($imageDesc)) {
        $imageDesc = file_get_contents($imageDesc);
    }
    return $imageDesc;
}

function getSpreadIDInteractive(\PDO $pdoConn) {
    $getSpreadID = $pdoConn->prepare("SELECT spreadid FROM spread WHERE spreadtype = ?;");
    $spreadID = executeAndFetchScalar(
            $getSpreadID, 
            (promptInput("Double spread? (y/n) > ") == "y") ? "double" : "normal"
    );
    return $spreadID;
}

function getColorStyleInteractive(\PDO $pdoConn) {
    $colorStyleID = $pdoConn->query("SELECT MIN(colorstyleid) FROM colorstyle;")
                            ->fetch()[0];
    if (promptInput("Add non-default reading colors? (y/n) > ") == "y") {
        echo "Existing color styles:\n";
        $styles = $pdoConn->query("SELECT colorstyleid, colorstyledesc FROM colorstyle;")
                          ->fetchAll(\PDO::FETCH_ASSOC);
        echo attributeTableStr(
                $styles, 
                "colorstyle", 
                "colorstyleid", 
                "colorstyledesc"
        );
        $select = promptInput("Enter the ID of the style you want to select,"
                                . " or 'n' if you don't want any of these.\n> ");
        if (\in_array($select, array_column($styles, 'colorstyleid'))) {
            $colorStyleID = $select;
        } elseif ($select == 'n') {
            if (promptInput("Would you like to add a custom css file? (y/n) > ") 
                    == "y") {
                $path = promptInput("Enter path to CSS file.\n> ");
                while (!isFilePath($path, ".css") AND $path != 'n') {
                    $path = promptInput(
                            "Not a valid path to a CSS file. "
                            . "Enter path (or 'n' to cancel).\n> ");
                }

                if (isFilePath($path, ".css")) {
                    insertColorStyleRecord( $pdoConn, 
                                            promptInput("Enter a short description.\n> "), 
                                            $path   );
                    $colorStyleID = $pdoConn
                            ->query("SELECT MAX(colorstyleid) FROM colorstyle;")
                            ->fetch()[0];
                }
            }
        } else {
            echo "Okay, sticking with default style $colorStyleID. Moving on...\n";
        }
    }

    return $colorStyleID;
}

function getNoteInteractive() {
    if (($note = promptInput(
                "Enter text you'd like to add as a note on this page, or 'n' for none.\n> "))
            != 'n') {
        return $note;
    } else return null;
}

/**
 * Summary of Briel\generatePageRecordInteractive
 * @param \PDO $pdoConn
 * @return array
 */
function generatePageRecordInteractive(\PDO $pdoConn) {
    echo "Generating new page record...\n";
    
    // Set title
    $title = getPageTitleInteractive($pdoConn);
    
    // Set path
    $path = getPagePathInteractive();

    // Get image description
    $imageDesc = getImgDescInteractive();

    // Get spread ID
    $spreadID = getSpreadIDInteractive($pdoConn);

    // Get colors
    $colorStyleID = getColorStyleInteractive($pdoConn);

    // Get note
    $note = getNoteInteractive();

    // pageid is automatically generated upon inserting these values.
    return array_combine(PAGEINSERTCOLUMNS, [   $title, 
                                                $path, 
                                                $imageDesc, 
                                                $spreadID, 
                                                $colorStyleID, 
                                                $note   ]);
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

function nameNewLeavesInteractive(  string $leafTableName, 
                                    \PDO $pdoConn, 
                                    bool $promptToCommit = true, 
                                    string $promptCommitMessage = ''    ) {
    if (!\in_array($leafTableName, ['tag', 'contwarning'])) return false;

    $names = [];
    $tryAgain = false;
    do {
        $submittedNames = preg_split(
                '/[,\s]+/', 
                promptInput("Enter {$leafTableName}s separated by commas or spaces.\n> "), 
                flags: PREG_SPLIT_NO_EMPTY
        );
        $invalidNames = array_filter($submittedNames, fn($name) => !preg_match('/^\w+$/', $name));
        $validNames = array_unique(array_diff($submittedNames, $invalidNames));
        if ($invalidNames) {
            echo \implode(', ', $invalidNames) . " are not valid names because "
                . "{$leafTableName}s can only have letters, numbers, and underscores.\n";
        }
        if (promptInput("Okay to add these {$leafTableName}s?\n" . \implode(', ', $validNames) 
                            . "\n(y/n) > ") == 'y') {
            $names = [...$names, ...$validNames];
        }
        
        $tryAgain = (promptInput("Currently you're adding these {$leafTableName}s:\n"
                                    . \implode(', ', $names)
                                    . "\nWant to add any more? (y/n) > ") == 'y');
    } while ($tryAgain);

    return insertNewLeaves($leafTableName, $names, $pdoConn, $promptToCommit, $promptCommitMessage);
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
    $insertLeavesFn = null;
    switch ($leafTableType) {
        case 'tag': 
        case 'contwarning':
            $leafName = 'name';
            $leafTableAssocName = "{$leafTableType}page";
            $rootName = 'title';
            $rootIDName = 'pageid';
            $rootTableName = 'page';
            $insertLeavesFn = (fn() => nameNewLeavesInteractive($leafTableType, 
                                                                $pdoConn, 
                                                                $promptToCommit, 
                                                                $promptCommitMessage));
            break;
        case 'file':
            $leafName = 'path';
            $leafTableAssocName = 'file';
            $rootName = 'title';
            $rootIDName = 'pageid';
            $rootTableName = 'page';
            $insertLeavesFn = (fn() => generateInsertUpdateFileRecords($pdoConn));
            break;
        case 'page':
            $leafName = 'title';
            $leafTableAssocName = 'comicupdatepage';
            $rootName = 'title';
            $rootIDName = 'updateid';
            $rootTableName = 'comicupdate';
            break;
        default: 
            echo "`$leafTableType` isn't associatable data. Returning...\n";
            return false;
    }

    $recsToStrs = fn($recs) => array_map(  
            fn($rec) => str_pad($rec[$leafIDName], 8, '_') . $rec[$leafName], 
            $recs
    );
    $leaves = $pdoConn->query("SELECT $leafIDName, $leafName FROM $leafTableType;")
                      ->fetchAll(\PDO::FETCH_ASSOC);
    
    $tryAgain = false;
    do {
        $pdoConn->exec(<<<STMT
                DROP TEMPORARY TABLE IF EXISTS associd;
                CREATE TEMPORARY TABLE associd ($leafIDName int unsigned);
                STMT);

        $tryInputAgain = false;
        do {
            echo attributeTableStr( $leaves, 
                                    "$leafTableType list:", 
                                    $leafIDName, 
                                    $leafName   );
            
            if ($insertLeavesFn AND promptInput(
                    "Want to add {$leafTableType}s that aren't here? (y/n) > ") == 'y') {
                $insertLeavesFn();
                $leaves = $pdoConn->query("SELECT $leafIDName, $leafName FROM $leafTableType;")
                                ->fetchAll(\PDO::FETCH_ASSOC);
                echo attributeTableStr( $leaves, 
                                        "$leafTableType list:", 
                                        $leafIDName, 
                                        $leafName   );
            }

            $inputStr = promptInput("Enter $leafTableType IDs separated by commas, "
                                    . "or a ? followed by a regular expression for "
                                    . "$leafTableType names/titles.\n> ");
            if (preg_match("/^(\d+,\s*)*\d+$/", $inputStr)) {
                queryInsertRecords( $pdoConn, 
                                    "associd", 
                                    [$leafIDName], 
                                    array_map(fn($leaf) => [trim($leaf)], explode(",", $inputStr)), 
                                    $promptToCommit, 
                                    $promptCommitMessage);

                if (!empty($notRealIDs = $pdoConn->query(<<<STMT
                                                SELECT * FROM associd 
                                                    EXCEPT 
                                                SELECT $leafIDName FROM $leafTableType;
                                                STMT)
                                        ->fetchAll(\PDO::FETCH_COLUMN))) {
                    if (promptInput(
                                "Error: IDs [" . implode(', ', $notRealIDs)
                                . "] don't correspond to existing {$leafTableType}s.\n"
                                . "Try again? (y/n) > ")
                            == 'y') {
                        $tryInputAgain = true;
                    } else {
                        echo "Okay. Returning...";
                        return false;
                    }
                }

            } else if (\strlen($inputStr) > 0 AND $inputStr[0] == "?") {
                $regExp = $pdoConn->prepare(<<<STMT
                                INSERT INTO associd ($leafIDName)
                                    SELECT $leafIDName FROM $leafTableType
                                        WHERE REGEXP_LIKE($leafName, ?, 'c'); 
                                STMT); // 'c' enforces case-sensitivity
                                            
                $regExp->execute([substr($inputStr,1)]);
                $tryInputAgain = false;
            } else {
                $tryInputAgain = (promptInput("'$inputStr' invalid. Try again? (y/n) > ") == 'y');
            }
        } while ($tryInputAgain);

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
            $assoc = ($leafTableType == 'file' ? 
                    $pdoConn->prepare(<<<STMT
                        UPDATE $leafTableAssocName SET $rootIDName = ?
                            WHERE $leafIDName IN(SELECT * FROM associd);
                        STMT)
                    : 
                    $pdoConn->prepare(<<<STMT
                        INSERT INTO $leafTableAssocName 
                            ($rootIDName, $leafIDName)
                        SELECT ?, $leafIDName FROM associd;
                        STMT)
            );
            $assoc->execute([$record[$rootIDName]]);
            echo "{$leafTableType}s associated.\n";
            $leafIDs = $pdoConn ->query('SELECT * FROM associd;')
                                ->fetchAll(\PDO::FETCH_COLUMN);
            if (promptInput("Add any more? (y/n) > ") == 'y') {
                $tryAgain = true;
            } else {
                if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);
                return $leafIDs;
            }
        
        } else if (promptInput("Try again? (y/n) > ") == 'y') {
            $tryAgain = true;
        } else {
            echo "Okay. Returning without associating...\n";
            if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);
            return false;
        }

        $pdoConn->exec("DROP TEMPORARY TABLE associd;"); 
    } while ($tryAgain);
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
    $tryAgain = false;
    echo "Generating page records...\n";
    do {
        $record = orderArrayByOrderedKeys(  PAGEINSERTCOLUMNS, 
                                            generatePageRecordInteractive($pdoConn) );
        if (!queryInsertRecords(
                $pdoConn, 'page', PAGEINSERTCOLUMNS, [$record], promptToCommit: false   )) {
            promptRollback($pdoConn, 'Inserting page records failed.');
            return false;
        }
        // echo "Page Record:\n";
        // var_export($record);
        // echo "Made it past page insertion.\n";
        $getRecordBack = $pdoConn->prepare(<<<STMT
            SELECT * FROM page WHERE pageid = (
                SELECT MAX(pageid) FROM page 
                WHERE title = ? AND postdate IS NULL AND path = ? AND imagedesc = ?
            );
            STMT);  
        // echo "Prepared statement for getting ID.\n";
        $getRecordBack->execute([   $record['title'], 
                                    $record['path'], 
                                    $record['imagedesc']    ]);
        // echo "Executed statement.\n";
        $record = $getRecordBack->fetch(\PDO::FETCH_ASSOC);
        // echo "Fetched back record.\n";

        if (promptInput("Associate files? (y/n) > ") == 'y')
            associateFilesInteractive($record, $pdoConn, promptToCommit: false);

        if (promptInput("Associate tags? (y/n) > ") == 'y')
            associateTagsInteractive($record, $pdoConn, promptToCommit: false);

        if (promptInput("Associate content warnings? (y/n) > ") == 'y')
            associateCWsInteractive($record, $pdoConn, promptToCommit: false);

        $isInserted = insertPageAtEnd($record['pageid'], $pdoConn, promptToCommit: false);
        if ($isInserted) {
            $records[] = $record;
        } else echo "Appending page failed. No record added for it.\n";

    } while (promptInput("Generate another page? (y/n) > ") == 'y');

    if ($promptToCommit) promptCommit($pdoConn, $promptCommitMessage);

    return $records;
}

/**
 * Summary of Briel\generateFileRecordsTraverse
 * traverse a filetree of a folder recursively. 
 * basically, if a folder has no other folders within, that's a leaf. 
 * if *any* have images, even non-leaf nodes, run record generation on them.
 * if that node has images and a metadata file, use the generation with metadata. 
 * Otherwise, normal generation. 
 * return generated records up the call stack.
 * @param string $dirPath
 * @param \PDO $pdoConn
 * @param mixed $ratioStatement
 * @param mixed $purposeStatement
 * @return array<array|mixed>|bool
 */
function generateFileRecordsTraverse(   string $dirPath,
                                        \PDO $pdoConn, 
                                        ?\PDOStatement $ratioStatement = null, 
                                        ?\PDOStatement $purposeStatement = null   ) {
    $dirPath = endDirectoryPathWithSlash($dirPath);
    $dirEntries = array_filter(scandir($dirPath), fn($entry) => $entry != '.' AND $entry != '..');
    $subPaths = array_map(fn($entry) => $dirPath . $entry, $dirEntries);
    $subDirs = array_filter($subPaths, 'is_dir');
    // print_r($subDirs);
    $images = array_filter($subPaths, fn($path) => preg_match(  '(image/.*)', 
                                                                mime_content_type($path)    ));
    $records = [];
    if ($images) {
        $records = \in_array('metadata.json', $dirEntries) ? 
                generateFileRecordsFromFolderWithMetadata(  $dirPath, 
                                                            $pdoConn, 
                                                            ratioStatement: $ratioStatement, 
                                                            purposeStatement: $purposeStatement )
                : generateFileRecordsFromFolderInteractive( $dirPath, 
                                                            $pdoConn, 
                                                            ratioStatement: $ratioStatement);
    }
    
    foreach ($subDirs as $subDirPath) {
        $records = [
                ...$records, 
                ...generateFileRecordsTraverse( $subDirPath, 
                                                $pdoConn, 
                                                ratioStatement: $ratioStatement, 
                                                purposeStatement: $purposeStatement )
        ];
    }

    return $records;
}

/**
 * Summary of Briel\generateInsertUpdateFileRecords
 * Interactive generation and insertion of file records as part of posting an update.
 * @param \PDO $pdoConn
 * @param bool $promptToCommit
 * @param string $promptCommitMessage
 * @return void
 */
function generateInsertUpdateFileRecords(   \PDO $pdoConn, 
                                            bool $promptToCommit = true, 
                                            string $promptCommitMessage = ""    ) {
    $tryAgain = false;
    do {
        $fileRecords = [];
        $numFiles = 1;
        while (promptInput("Add many files from many sub-folders? (y/n) > ") == 'y') {
            $input = promptInput(
                    "Enter a path to the folder containing the subfolders or `n` to cancel.\n> ");
            if ($input == 'n') break;
            else if (is_dir($input)) {
                $newRecords = generateFileRecordsTraverse($input, $pdoConn);
                $fileRecords = [...$fileRecords, ...$newRecords];
                $numFiles += \count($newRecords);
            } else {
                echo "$input is not an existing folder.";
            }
        } 

        while (promptInput("Add lots of files from one folder? (y/n) > ") == 'y') {
            $dirPath = endDirectoryPathWithSlash(promptInput("Enter a path to the folder.\n> "));
            $newRecords = file_exists($dirPath . 'metadata.json') ? 
                    generateFileRecordsFromFolderWithMetadata($dirPath, $pdoConn)
                    : generateFileRecordsFromFolderInteractive($dirPath, $pdoConn);
            $fileRecords = [...$fileRecords, ...$newRecords];
            $numFiles += \count($newRecords);
        }

        for (; promptInput("Add a file? (y/n) > ") == 'y'; $numFiles++) {
            $fileRecords[] = generateFileRecordInteractive(
                    promptInput("Enter path for file $numFiles: > "), $pdoConn);
        }
        if ($numFiles > 1) {
            echo ($numFiles - 1) . " file records generated:\n";
            print_r($fileRecords);
            // insert file records
            if (promptinput("Insert these into the database? (y/n) > ") == "y") {
                insertFileRecords(  $fileRecords, 
                                    $pdoConn, 
                                    promptToCommit: $promptToCommit, 
                                    promptCommitMessage: $promptCommitMessage   );
                echo "...done!\n";
                $tryAgain = false;
            } elseif (promptInput(
                        "Okay. Wanna try again at adding files? (y/n) > ") == "y") {
                $tryAgain = true;
            } else {
                echo "Okay, moving on...\n";
                $tryAgain = false;
            }
        } else {
            $tryAgain = (promptInput("No file records generated. Try again? (y/n) > ") == 'y');
        }
    } while ($tryAgain);
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
        $pdoConn->exec("DROP TEMPORARY TABLE IF EXISTS tagsearch;");
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
        $pdoConn->exec("DROP TEMPORARY TABLE IF EXISTS cwsearch;");
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
        for (   $i = 0; 
                ($cwMatch = $possibleCWMatches->fetch(\PDO::FETCH_ASSOC)) !== false; 
                $i++    ) {
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
    // $originalDatabase = $pdoConn->query("SELECT DATABASE();")->fetch()[0];
    // useUserDatabase(USERDBNAME);
    if ($joinTokens OR $tagTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE tagsearch;'); 
    } 
    if ($joinTokens OR $cwTokens) {
        $pdoConn->exec('DROP TEMPORARY TABLE cwsearch;'); 
    }
    // useDatabase($originalDatabase)
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

function updatePrevSearchCountStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            UPDATE searchdata SET numtimes = numtimes + 1 
                WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc;
            STMT);
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

function insertColorStyleRecord(\PDO $pdoConn, 
                                string $description, 
                                string $path, 
                                bool $promptToCommit = true, 
                                string $promptCommitMessage = '') {

    return queryInsertRecords(  $pdoConn, 
                                'colorstyle', 
                                ['colorstyledesc', 'path'], 
                                [[$description, $path]], 
                                $promptToCommit, 
                                $promptCommitMessage    );
}

function recordUpdate(null|bool|\PDO $pdoConn = null) {
    if (!tryPDOConnectManip($pdoConn)) {
        echo "Couldn't establish/continue SQL server connection. Aborting...\n";
        return false;
    }
    echo "Recording update...\n";
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

    $blogText = null;
    if (promptInput("Would you like to make a new blog post? (y/n) > " == "y")) {
        $blogText = promptInput("Enter blog text (or a path to it):\n> ");
        if (is_readable($blogText)) $blogText = file_get_contents($blogText) ?: $blogText;
        $pdoConn->prepare("INSERT INTO blog(blogtext) VALUES (?);")
                ->execute([$blogText]);
    }
    
    echo "Final check for commitment. ";
    return promptCommit($pdoConn);
}

?>