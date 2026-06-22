<?php

use function Briel\searchcacheKeyFromSearchString;

require 'dbManip.php';
require 'pageGen.php';

$getParams = $_GET;
if (!key_exists('search', $getParams)) {
    $getParams['search'] = '';
} 

if (!key_exists('p', $getParams)) {
    $getParams['p'] = 0;
}

// Check if `$_GET` fields `search` and `p` have an existing corresponding page
    // (use a database table that has one column for the search, a second for the page number, 
    // and a third for a file location)

$pdoConnection = Briel\pdoConnect($echoConnSuccess = false); // check for a cookie? session storage?
$prevSearch = $pdoConnection->prepare(
    "SELECT * FROM searchcache 
    WHERE search = :search AND resultpageindex = :pageindex");
$searchcacheKey = searchcacheKeyFromSearchString($getParams['search']);
$prevSearch->execute([  ':search' => $searchcacheKey, 
                        ':pageindex' => $getParams['p'] ]);

if ($prevExists = $prevSearch->fetch(PDO::FETCH_ASSOC)) {
    // load the HTML file from the result as a DOM object, 
    // switch out the 'value' fields in the search form inputs for $getParams[$search]
    // output the text of the DOM object
} else {
    // use searchComics to get the array of results
    // generateAllSearchPages on the original string and that array
    // 
}





// If so, serve it
// If not, generate *all search result pages*--they'll all reference the same `$_GET` search value!
    // Though I will need to add in a separate parameter for page number

    // ...does a PDO object automatically close when a script ends?
    // Or does it stay open if it's kept in session storage?


?>