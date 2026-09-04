<?php
require_once './php/dbManip.php';

ob_start();
$db = Briel\pdoConnect(); // check for session storage
ob_end_clean();
if ($db === false) {
    readfile(Briel\FILEROOT . Briel\BLANKSEARCHFILENAME);
    exit("Ah shit, database connection failed.");
}

$randUpdateID = $db->query("SELECT updateid FROM comicupdate ORDER BY RAND( ) LIMIT 1;")
                    ->fetch(PDO::FETCH_ASSOC)['updateid'];
                    
readfile(Briel\getFirstPageRecordOfUpdateFromID($db, $randUpdateID)['path']);
