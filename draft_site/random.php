<?php
require_once './php/dbManip.php';

$db = Briel\pdoConnect(echoConnSuccess: false); // check for session storage
if ($db === false) {
    readfile(Briel\FILEROOT . Briel\BLANKSEARCHFILENAME);
    exit("Ah shit, database connection failed.");
}

$randUpdateID = $db->query("SELECT updateid FROM comicupdate ORDER BY RAND( ) LIMIT 1;")
                    ->fetch(PDO::FETCH_ASSOC)['updateid'];
                    
readfile(Briel\getFirstPageRecordOfUpdateFromID($db, $randUpdateID)['path']);
