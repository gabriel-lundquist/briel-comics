<?php
namespace Briel;

require_once('siteOperations.php');

$db = pdoConnect();

regenerateComicPages([29,30,31,32,74,75], $db);
regenerateHomepage($db);
// redoCachedSearches($db);
// regenerateArchivePages($db);
