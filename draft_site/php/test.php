<?php
namespace Briel;

require_once('siteOperations.php');

$db = pdoConnect();
var_export($db->query("SHOW TABLES;")->fetchAll(\PDO::FETCH_ASSOC));

// regenerateComicPages([29,30,31,32,74,75], $db);
// regenerateHomepage($db);
// redoCachedSearches($db);
// regenerateArchivePages($db);5
