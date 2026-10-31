<?php
namespace Briel;

$setting = parse_ini_file("dynamic.ini");
require_once $setting['BACKENDSCRIPTDIR'] . 'dbRead.php';
require_once $setting['BACKENDSCRIPTDIR'] . 'pageGen.php';

$db = pdoConnect(); // check for session storage?
if ($db === false) {
    readfile(BLANKSEARCHPATH);
    exit("Ah shit, database connection failed.");
}

$randUpdateID = $db->query("SELECT updateid FROM comicupdate ORDER BY RAND( ) LIMIT 1;")
                    ->fetch(\PDO::FETCH_ASSOC)['updateid'];
ob_start(); ?>
<!doctype html>
<html lang="en-US">
    <script>
        window.location.href = "<?= 
                getFirstPageRecordOfUpdateFromID($db, $randUpdateID)['path'] 
        ?>";
    </script>
</html>
<?php 
echo ensureServerPaths(ob_get_clean());