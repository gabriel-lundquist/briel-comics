<?php

namespace Briel;

require_once 'brielConstants.php';

const TEXTMATCHTHRESHOLD = 0.1;

class DatabaseStatements {
    public function __construct(    public \PDO $pdoConn, 
                                    public ?\PDOStatement $getPageRecord = null, 
                                    public ?\PDOStatement $getPrevPageID = null,
                                    public ?\PDOStatement $getNextPageID = null,
                                    public ?\PDOStatement $getPrevUpdateID = null,
                                    public ?\PDOStatement $getNextUpdateID = null,
                                    public ?\PDOStatement $getPageFromUpdateID = null,
                                    public ?\PDOStatement $getUpdateFromPageID = null, 
                                    public ?\PDOStatement $getImgRecords = null,
                                    public ?\PDOStatement $getThumbnailRecords = null, 
                                    public ?\PDOStatement $getTags = null,
                                    public ?\PDOStatement $getCWs = null,
                                    public ?\PDOStatement $getSpreadType = null, 
                                    public ?\PDOStatement $getStylePath = null,    
                                    public ?\PDOStatement $getMinSizeFile = null,   
                                    public ?\PDOStatement $getUpdate = null    ) {
        $this->getPageRecord ??= getPageRecordFromIDStmt($pdoConn);
        $this->getPrevPageID ??= getPrevPageFromIDStmt($pdoConn);
        $this->getNextPageID ??= getNextPageFromIDStmt($pdoConn);
        $this->getPrevUpdateID ??= getPrevUpdateFromIDStmt($pdoConn);
        $this->getNextUpdateID ??= getNextUpdateFromIDStmt($pdoConn);
        $this->getPageFromUpdateID ??= getPageIDsFromUpdateIDStmt($pdoConn);
        $this->getUpdateFromPageID ??= getUpdateIDFromPageIDStmt($pdoConn);
        $this->getImgRecords ??= getPageImgRecordsFromIDStmt($pdoConn);
        $this->getThumbnailRecords ??= getThumbnailFromPageIDStmt($pdoConn);
        $this->getTags ??= getTagsFromPageIDStmt($pdoConn);
        $this->getCWs ??= getCWsFromPageIDStmt($pdoConn);
        $this->getSpreadType ??= getSpreadTypeFromSpreadIDStmt($pdoConn);
        $this->getStylePath ??= getStylePathFromColorstyleIDStmt($pdoConn);
        $this->getMinSizeFile ??= getMinSizeFileFromPageIDStmt($pdoConn);
        $this->getUpdate ??= getUpdateRecordFromIDStmt($pdoConn);
    }

    public function executePageIDStmts(int $pageID) {
        foreach ([  $this->getPageRecord, 
                    $this->getPrevPageID, 
                    $this->getNextPageID, 
                    $this->getImgRecords, 
                    $this->getThumbnailRecords, 
                    $this->getTags, 
                    $this->getCWs, 
                    $this->getMinSizeFile   ] as $getStatement) {
            // this works the way you'd hope it would!
            $getStatement->execute([$pageID]);
        }
    }
}

function isColorHexcode(string $input) {
    return preg_match('/^#?[0-9A-Fa-f]{6}$/', $input);
}

/**
 * Summary of Briel\pdoConnect
 * Returns the PDO connection if successful, `false` if not.
 * @param string $dbhost
 * @param string $dbuser
 * @param string $dbname
 * @param int $dbport
 * @param bool $enterPassword
 * @param bool $echoConnSuccess
 * @param string $password
 * @return bool|\PDO
 */
function pdoConnect(string $dbhost = DBHOST, 
                    string $dbuser = DBPAGEGENUSER, 
                    string $dbname = MASTERDBNAME, 
                    int $dbport = DBPORT, 
                    bool $enterPassword = false,
                    bool $echoConnSuccess = false,
                    string $password = DBPAGEGENUSERPW) {
    try {
        $conn = new \PDO(   "mysql:host=$dbhost; dbname=$dbname; port=$dbport", 
                            $dbuser, 
                            $enterPassword ? 
                                    promptInput("Enter password. > ") 
                                    : getenv($password)
                        );
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
 * Summary of Briel\tryPDOConnect
 * Attempts to establish that `$pdoConn` is a working database connection.
 * Returns `false` if it is falsy and `pdoConnect()` also returns a falsy value.
 * Otherwise returns `true`, i.e. `$pdoConn` (which is a reference) is a working 
 * connection.
 * @param ?\PDO $pdoConn
 * @return bool
 */
function tryPDOConnect( ?\PDO &$pdoConn, 
                        string $dbhost = DBHOST, 
                        string $dbuser = DBPAGEGENUSER, 
                        string $dbname = MASTERDBNAME, 
                        int $dbport = DBPORT, 
                        bool $enterPassword = false, 
                        bool $echoConnSuccess = false, 
                        string $passwordEnvVar = DBPAGEGENUSERPW) {
    if (!$pdoConn) {
        $pdoConn = pdoConnect(  $dbhost, 
                                $dbuser, 
                                $dbname, 
                                $dbport, 
                                $enterPassword, 
                                $echoConnSuccess, 
                                $passwordEnvVar );
        if (!$pdoConn) return false;
        else return true;
    } else return true;
}

/**
 * Summary of Briel\tryBeginTransaction
 * Attempts to ensure that the connection is in a transaction by the time it returns.
 * @param \PDO|bool $pdoConn
 * @return bool `true` when in a transaction by the end of execution, `false` otherwise.
 */
function tryBeginTransaction(\PDO|bool $pdoConn) {
    if (!$pdoConn) {
        echo "Connection to SQL server doesn't exist.";
        return false;
    }
    if (!$pdoConn->inTransaction()) {
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
    return implode(' ', $termArr);  
    // delimit with spaces so we can pass this to searchComics
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

function fileListStr(   array $paths    ) {
    return "ID\tPath\n" 
            . implode("\n", array_map(  fn($key, $val) => "$key\t" . basename($val), 
                                        array_keys($paths), 
                                        array_values($paths)    ))
            . "\n";
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
            $getSource->execute([$source['sourceid']]), 
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
            $getTarget->execute([$target['targetid']]), 
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
            $getSource->execute([$source['sourceid']]), 
                    $source = $getSource->fetch(\PDO::FETCH_ASSOC)  ) {

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
            $getTarget->execute([$target['targetid']]), 
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


function useDatabase(string $dbName, \PDO $pdoConn) {
    $useDB = $pdoConn->prepare("USE ?;");
    return $useDB->execute([$dbName]);
}

function useUserDatabase(\PDO $pdoConn) {
    return useDatabase(USERDBNAME, $pdoConn);
}

function useMasterDatabase(\PDO $pdoConn) {
    return useDatabase(MASTERDBNAME, $pdoConn);
}

function getTagsFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT name FROM tagpage INNER JOIN tag USING (tagid) 
                WHERE pageid = ?;
            STMT);
}

function getTagsFromMultiplePageIDsStmt(array $numIDs, \PDO $pdoConn) {
    return $pdoConn->prepare(
            "SELECT DISTINCT name FROM tagpage INNER JOIN tag USING (tagid) WHERE pageid IN"
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
            SELECT DISTINCT name FROM contwarningpage INNER JOIN contwarning USING (contwarningid) 
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

function getUpdateIDFromPageIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare("SELECT updateid FROM comicupdatepage WHERE pageid = ?;");
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
 * Gets the previous update ID in the order, given an update ID.
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

function getSpreadTypeFromSpreadIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(
            "SELECT spreadtype FROM spread WHERE spreadid = ?;");
}

function getStylePathFromColorstyleIDStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(
            "SELECT path FROM colorstyle WHERE colorstyleid = ?");
}

function getPrevSearchStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT path FROM searchcache 
            WHERE search = :search AND matchexactly = :exact AND searchimgdesc = :desc
                AND resultpageindex = :pageindex;
            STMT);
}

function existsPrevSearchAnyIndexStmt(\PDO $pdoConn) {
    return $pdoConn->prepare(<<<STMT
            SELECT EXISTS(
                SELECT NULL FROM searchdata 
                WHERE search = :search 
                    AND matchexactly = :exact 
                    AND searchimgdesc = :desc
            );
            STMT);
}

function orderUnorderablePageIDs(   \PDO $pdoConn, 
                                    array $pageIDs, 
                                    ?\PDOStatement $getSource = null, 
                                    ?\PDOStatement $getTarget = null, 
                                    ?DatabaseStatements $stmt = null   ) {
    $stmt ??= new DatabaseStatements($pdoConn);
    $getSource ??= getPrevPageFromIDStmt($pdoConn);
    $getTarget ??= getNextPageFromIDStmt($pdoConn);

    $pageOrders = [];
    $remainingPageIDs = $pageIDs;

    while (!empty($remainingPageIDs)) {
        $pageOrders[] = [];
        $i = array_key_last($pageOrders);
        $initKey = array_key_first($remainingPageIDs);
        $page = $pageIDs[$initKey];

        $getSource->execute([$page]);
        for (   $source = $getSource->fetch(\PDO::FETCH_ASSOC); 
                !empty($remainingPageIDs) AND prevOrderContinues($source); 
                $getSource->execute([$source['sourceid']]), 
                        $source = $getSource->fetch(\PDO::FETCH_ASSOC)  ) {

            if (($key = array_search($source['sourceid'], $remainingPageIDs)) !== false) {
                $pageOrders[$i] = [$source['sourceid'], ...$pageOrders[$i]];
                unset($remainingPageIDs[$key]);
            }
        }

        $pageOrders[$i] = [...$pageOrders[$i], $page];
        unset($remainingPageIDs[$initKey]);

        $getTarget->execute([$page]);
        for (   $target = $getTarget->fetch(\PDO::FETCH_ASSOC); 
                !empty($remainingPageIDs) AND nextOrderContinues($target); 
                $getTarget->execute([$target['targetid']]), 
                        $target = $getTarget->fetch(\PDO::FETCH_ASSOC)) {

            if (($key = array_search($target['targetid'], $remainingPageIDs)) !== false) {
                $pageOrders[$i] = [...$pageOrders[$i], $target['targetid']];
                unset($remainingPageIDs[$key]);
            }
        }
    }
    
    // order by date of last page in order
    $pageOrdersOrdered = [];
    foreach ($pageOrders as $order) {
        $stmt->getPageRecord->execute([array_last($order)]);
        $lastRecord = $stmt->getPageRecord->fetch(\PDO::FETCH_ASSOC);
        $pageOrdersOrdered[
                createDateFromSQLDateTime($lastRecord['postdate'])->getTimestamp()
        ] = $order;
    }
    ksort($pageOrdersOrdered);
    return array_merge(...array_values($pageOrdersOrdered));
}
