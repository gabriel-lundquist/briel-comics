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
const STYLEDIRPATH = SITEROOT . 'styles/';
const HOMESTYLEPATH = STYLEDIRPATH . "home_page.css";
const COMMENTPATH = SITEROOT . 'comments.php';
const BLOGARCHIVEPATH = SITEROOT . 'weblog.html';
const SEARCHCACHEDIR = 'searchcache/';
const SEARCHCACHEDIRPATH = SITEROOT . SEARCHCACHEDIR;
const BLANKSEARCHFILENAME = 'blank_search.html';
const BLANKSEARCHPATH = SITEROOT . BLANKSEARCHFILENAME;
const ARCHIVEDIRNAME = "archive/";
const ARCHIVEDIRPATH = SITEROOT . ARCHIVEDIRNAME;
const JAVASCRIPTDIRPATH = SITEROOT . 'js/';
const ARCHIVESTARTPATH = ARCHIVEDIRPATH . 'archive_p1.html';
const FONTFACESPATH = STYLEDIRPATH . 'briel_font-faces.css';
const READINGCOLORDIRPATH = STYLEDIRPATH . "reading_colors/";
const READINGSTYLEPATH = STYLEDIRPATH . "reading_page.css";
const READINGSCRIPTPATH = JAVASCRIPTDIRPATH . "reading_page.js";
const LISTSTYLEPATH = STYLEDIRPATH . "entry_list_page.css";
const RANDOMPATH = SITEROOT . 'random.php';
const IMAGESDIRPATH = SITEROOT . 'images/';
const DECORATIONDIRPATH = IMAGESDIRPATH . 'decoration/';
const SITEICONNAME = 'smileicon.ico';
const SITEICONPATH = DECORATIONDIRPATH . SITEICONNAME;
const VIEWSTYLENAME = 'view_page.css';
const VIEWSTYLEPATH = STYLEDIRPATH . VIEWSTYLENAME;
const VIEWSCRIPTPATH = JAVASCRIPTDIRPATH . "view_page.js";
const SEARCHSCRIPTPATH = JAVASCRIPTDIRPATH . "search_page.js";
const FONTFACESCSSNAME = 'briel_font-faces.css';
const COMMENTDOCFILEPATH = FILEROOT . 'comments.txt';
const PHPDIRFILEPATH = FILEROOT . 'php/';
const COMMENTSTYLEPATH = STYLEDIRPATH . 'comment.css';
const HEADERNAVSTYLEPATH = STYLEDIRPATH . 'header-nav.css';
const NAVBUTTONSTYLEPATH = STYLEDIRPATH . 'nav-buttons.css';
const BLOGARCHIVEFILENAME = 'weblogs.html';
const BLOGARCHIVEPATH = SITEROOT . BLOGARCHIVEFILENAME;
const BLOGARCHIVEFILEPATH = FILEROOT . BLOGARCHIVEFILENAME;

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