<?php 
# Folder paths should have a slash at the end
namespace Briel;

const LOCALSITE = false;

const FILEFOLDER = 'C:/Users/gabri/Code/Briel comics website/briel-comics/';
const SITEFOLDER = '/draft_site/';
const FILEROOT = FILEFOLDER . 'draft_site/';
if (LOCALSITE) define("SITEROOT", FILEROOT);
else define("SITEROOT", SITEFOLDER);

// This is the regular expression that matches paths on the local filesystem
// Used temporarily, as a stopgap until I get NGINX hooked up and filtering filesystem
// paths into site paths
define("FILEFOLDERREGEXP", '(' . str_replace('/', '[/\\\]', FILEFOLDER) . ')');

const SEARCHFIELDSPECS = [  'tag', 
                            'cw', 
                            'title', 
                            'day', 
                            'month', 
                            'year', 
                            'description'   ];

const WHITESPACES = " \n\t\r";
const PUNCTUATION = '~`!@#$%^&*()_-+=[]{}\\|:;"\'<>,.?/¡¿';

const SEARCHPATH = SITEROOT . 'search.php';
const HOMEPATH = SITEROOT . 'home.html';
const HOMEFILEPATH = FILEROOT . 'home.html';
const STYLEDIR = 'styles/';
const HOMESTYLEPATH = SITEROOT . STYLEDIR . "home_page.css";
const COMMENTPATH = SITEROOT . 'comments.php';
const BLOGARCHIVEPATH = SITEROOT . 'weblog.html';
const SEARCHCACHEDIR = 'searchcache/';
const SEARCHCACHEDIRPATH = SITEROOT . SEARCHCACHEDIR;
const BLANKSEARCHFILENAME = 'blank_search.html';
const BLANKSEARCHPATH = SITEROOT . BLANKSEARCHFILENAME;
const ARCHIVEDIRNAME = "archive/";
const ARCHIVEDIRPATH = SITEROOT . ARCHIVEDIRNAME;
const ARCHIVESTARTPATH = ARCHIVEDIRPATH . 'archive_p1.html';
const FONTFACESPATH = SITEROOT . 'styles/briel_font-faces.css';
const SITEICONPATH = SITEROOT . 'images/decorations/';
const READINGCOLORDIRPATH = SITEROOT . "styles/reading_colors/";
const READINGSTYLEPATH = SITEROOT . "styles/reading_page.css";
const READINGSCRIPTPATH = SITEROOT . "js/reading_page.js";
const RANDOMPATH = SITEROOT . 'random.php';
const IMAGESDIRPATH = SITEROOT . 'images/';
const DECORATIONDIRPATH = IMAGESDIRPATH . 'decoration/';

const FONTFACESCSSNAME = 'briel_font-faces.css';

const SITEICONNAME = 'smileicon.ico';

const SQLDATETIMEFORMAT = 'Y-m-d H:i:s';

/**
 * Summary of Briel\createDateFromSQLDateTime
 * @param string $dateStr
 * @return bool|\DateTimeImmutable
 */
function createDateFromSQLDateTime(string $dateStr) {
    return \DateTimeImmutable::createFromFormat(SQLDATETIMEFORMAT, $dateStr);
}

?>