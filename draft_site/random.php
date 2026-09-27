<?php
require_once './php/dbManip.php';
require_once './php/pageGen.php';

$db = Briel\pdoConnect(echoConnSuccess: false); // check for session storage
if ($db === false) {
    readfile(Briel\FILEROOT . Briel\BLANKSEARCHFILENAME);
    exit("Ah shit, database connection failed.");
}

$randUpdateID = $db->query("SELECT updateid FROM comicupdate ORDER BY RAND( ) LIMIT 1;")
                    ->fetch(PDO::FETCH_ASSOC)['updateid'];
ob_start(); ?>
<!doctype html>
<html lang="en-US">
    <script>
        window.location.href = "<?= 
                Briel\getFirstPageRecordOfUpdateFromID($db, $randUpdateID)['path'] 
        ?>";
    </script>
</html>
<?php 
if (Briel\LOCALSITE) {    
    ob_end_flush();
} else {    // temporary, until I get NGINX hooked up
    $pageStr = ob_get_clean();
    $pageStr = Briel\replacePathsForServerSite($pageStr);
    echo $pageStr;
    return $pageStr;
}