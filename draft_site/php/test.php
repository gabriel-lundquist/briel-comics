<?php
namespace Briel;

require_once('siteOperations.php');

$conn = pdoConnect();

generateViewPage(   [getPageInfo($conn, 31), getPageInfo($conn, 32), getPageInfo($conn, 30)], 
                    'Wizard and Hero'   );
