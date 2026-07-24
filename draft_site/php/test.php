<?php
namespace Briel;

require_once 'siteOperations.php';

$conn = pdoConnect();

$wizInfo1 = getPageInfo($conn, 29);

print_r($wizInfo1);