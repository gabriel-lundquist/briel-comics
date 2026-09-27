<aside>
<h3 id="search_options">Search options</h3>
<ul>
    <li>Enter <kbd>-blegh</kbd> to exclude any page with "blegh"</li>
    <li>Enter <kbd>title:tuesday</kbd> to get pages with "tuesday" in their 
        titles, as opposed to just searching <kbd>tuesday</kbd> which gets you 
        any page with a "tuesday" tag or posted on a tuesday or whatever. 
        Valid prefixes are:
        <ul>
            <?php 
require_once 'brielConstants.php';
foreach (Briel\SEARCHFIELDSPECS as $field) {
    echo "\t\t\t<li><kbd>$field:</kbd>";
    switch ($field) {
        case 'cw': echo ' (as in Content Warning)'; break;
        case 'day': echo ' (searches both day of the month and day of the week)'; break;
    }
    echo "</li>\n";
}               ?>              
            </ul>
        </li>
        <li>You can use both at once, like <kbd>-cw:gore</kbd>
            if you're okay with the word "gore" but you don't want to see 
            any comics with guts.</li>
        <li>Check "Search image descriptions too" and <strong>uncheck</strong>
            "Match terms exactly" if you're looking for a specific comic but 
            you can't remember exact dialogue. The machine will attempt to 
            find image descriptions that have similar words (no AI, just 
            MySQL's fulltext search algorithm).</li>
    </ul>
</aside>