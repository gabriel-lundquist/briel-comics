<?php
namespace Briel;

require_once 'siteOperations.php';

$conn = pdoConnect(echoConnSuccess: LOCALSITE);

foreach ([29, 30, 31, 32] as $id) {
    $info[$id] = getPageInfo($conn, $id);
    // have to be careful to always include the full filesystem path when writing to files
    print_r(file_put_contents(  (LOCALSITE ? '' : FILEFOLDER) . $info[$id]->record['path'], 
                                generateComicpage($info[$id])       ));
}