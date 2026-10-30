<?php 
# Folder paths should have a slash at the end
namespace Briel;

$loaded = parse_ini_file("./breel.ini", scanner_mode: INI_SCANNER_TYPED);

$defaults = [
        "MASTERFOLDER" => "/srv/breel-site/", 
        "SERVEDFOLDER" => "/",
];

$defaults += [
        "SITEROOT" => $defaults["MASTERFOLDER"] . "www/",
        "BACKENDROOT" => $defaults["MASTERFOLDER"] . "backend/", 
];

$defaults += [
        "STYLEDIR" => $defaults["SITEROOT"] . "style/", 
        "PAGEDIR"=> $defaults["SITEROOT"] . "page/", 
        "SCRIPTDIR"=> $defaults["SITEROOT"] . "script/", 
        "IMAGEDIR"=> $defaults["SITEROOT"] . "img/", 
];

$defaults += [
        "SEARCHCACHEDIR" => $defaults["PAGEDIR"] . "search/", 
        "ARCHIVEDIR" => $defaults["PAGEDIR"] . "archive/",
        "READINGDIR" => $defaults["PAGEDIR"] . "comic/",
        "DYNAMICPAGEDIR" => $defaults["PAGEDIR"] . "dynamic/",
        "COLORSTYLEDIR"=> $defaults["STYLEDIR"] . "color/", 
        "DECORATIONDIR"=> $defaults["IMAGEDIR"] . "decoration/", 
        "COMICIMAGEDIR" => $defaults["IMAGEDIR"] . "comic/", 
];

$defaults += [
        "LOCALSITE" => true, 

        "MASTERDBNAME" => "breel_comics", 
        "USERDBNAME" => "breel_user_submitted",
        "SQLDATETIMEFORMAT" => "Y-m-d H:i:s",
        "SEARCHFIELDSPECS" => [ "tag", 
                                "cw", 
                                "title", 
                                "day", 
                                "month", 
                                "year",
                                "description"   ], 

        "WHITESPACES" => " \n\t\r",

        "HOMEPATH"=> $defaults["PAGEDIR"] . "home.html",
        "SEARCHPATH" => $defaults["DYNAMICPAGEDIR"] . "search.php",
        "COMMENTPATH"=> $defaults["DYNAMICPAGEDIR"] . "comments.php", 
        "RANDOMPATH"=> $defaults["DYNAMICPAGEDIR"] . "random.php", 
        "VIEWPATH"=> $defaults["DYNAMICPAGEDIR"] . "view.php", 
        "BLOGARCHIVEPATH"=> $defaults["PAGEDIR"] . "weblogs.html", 
        "ARCHIVESTARTPATH" => $defaults["ARCHIVEDIR"] . "archive_p1.html",
        "BLANKSEARCHPATH"=> $defaults["PAGEDIR"] . "search_blank.html", 
        
        "HOMESTYLEPATH"=> $defaults["STYLEDIR"] . "home.css", 
        "READINGSTYLEPATH" => $defaults["STYLEDIR"] . "reading.css",
        "LISTSTYLEPATH"  => $defaults["STYLEDIR"] . "entry-list.css",
        "ARCHIVESTYLEPATH" => $defaults["STYLEDIR"] . "archive.css", 
        "SEARCHSTYLEPATH" => $defaults["STYLEDIR"] . "search.css", 
        "VIEWSTYLEPATH" => $defaults["STYLEDIR"] . "view.css", 
        "FONTFACESPATH"=> $defaults["STYLEDIR"] . "briel_font-faces.css", 
        "HEADERNAVSTYLEPATH"=> $defaults["STYLEDIR"] . "header-nav.css", 
        "NAVBUTTONSTYLEPATH"=> $defaults["STYLEDIR"] . "nav-buttons.css", 
 
        "READINGSCRIPTPATH"=> $defaults["SCRIPTDIR"] . "reading.js",
        "SEARCHSCRIPTPATH"=> $defaults["SCRIPTDIR"] . "search.js",
        "VIEWSCRIPTPATH"=> $defaults["SCRIPTDIR"] . "view.js",

        "SITEICONPATH" => $defaults["DECORATIONDIR"] . "smileicon.ico",
        "COMMENTDOCPATH" => $defaults["MASTERFOLDER"] . "comments.txt",  
];

// If a default is present in the .ini file, define constants to be that.
// If not, use constant value from above defaults.
foreach ($defaults as $key => $value) {
    define($key, empty($loaded[$key]) ? $value : $loaded[$key]);
}

// This is the regular expression that matches paths on the local filesystem
define("FILEFOLDERREGEXP", '(' . str_replace('/', '[/\\\]', SITEROOT) . ')');

// // const MASTERFOLDER = '/srv/breel-site/'; #
// // const SERVEDFOLDER = '/'; #
// const SITEROOT = MASTERFOLDER . 'www/'; # ren 'siteroot'
// // if (LOCALSITE) define("SITEROOT", SITEROOT);
// // else define("SITEROOT", SERVEDFOLDER);

// const SEARCHFIELDSPECS = [  'tag', 
//                             'cw', 
//                             'title', 
//                             'day', 
//                             'month', 
//                             'year', 
//                             'description'   ]; #

// const WHITESPACES = " \n\t\r"; #

// const VIEWPATH = SITEROOT . 'view.php'; #
// const SEARCHPATH = SITEROOT . 'search.php'; #
// const HOMEPATH = SITEROOT . 'home.html'; #
// // const HOMEFILEPATH = SITEROOT . 'home.html'; # un
// const STYLEDIR = SITEROOT . 'style/'; #
// const ARCHIVEDIR = SITEROOT . 'page/archive/'; #
// const HOMESTYLEPATH = STYLEDIR . "home.css"; #
// const COMMENTPATH = SITEROOT . 'comments.php'; # 
// // const SEARCHCACHEDIR = SITEROOT . 'page/search/'; # un
// const SEARCHCACHEDIR = SITEROOT . 'page/search/'; # ren SEARCHCACHEDIR
// // const BLANKSEARCHFILENAME = 'blank_search.html'; # un
// const BLANKSEARCHPATH = SITEROOT . 'search_blank.html'; #
// // const ARCHIVEDIRNAME = "archive/"; # un
// const ARCHIVEDIR = SITEROOT . "page/archive/"; # ren ARCHIVEDIR
// const SCRIPTDIR = SITEROOT . 'script/'; # ren SCRIPTDIR
// const ARCHIVESCRIPTPATH = SCRIPTDIR . "archive.js";
// const ARCHIVESTARTPATH = ARCHIVEDIR . 'archive_p1.html'; #
// const FONTFACESPATH = STYLEDIR . 'briel_font-faces.css'; #
// const COLORSTYLEDIR = STYLEDIR . "color/"; # ren COLORSTYLEDIR
// const READINGSTYLEPATH = STYLEDIR . "reading.css"; #
// const READINGSCRIPTPATH = SCRIPTDIR . "reading.js"; #
// const LISTSTYLEPATH = STYLEDIR . "entry-list.css"; #
// const SEARCHSTYLEPATH = STYLEDIR . "search.css"; #
// const RANDOMPATH = SITEROOT . 'page/dynamic/random.php'; #
// const IMAGESDIRPATH = SITEROOT . 'images/'; # ren IMAGEDIR
// const DECORATIONDIRPATH = IMAGESDIRPATH . 'decoration/'; # ren DECORATIONDIR
// # added COMICIMAGEDIR
// // const SITEICONNAME = 'smileicon.ico'; # un
// const SITEICONPATH = DECORATIONDIRPATH . 'smileicon.ico'; #
// const VIEWSTYLENAME = 'view.css'; # un
// const VIEWSTYLEPATH = STYLEDIR . VIEWSTYLENAME; #
// const VIEWSCRIPTPATH = SCRIPTDIR . "view.js"; # 
// const SEARCHSCRIPTPATH = SCRIPTDIR . "search.js"; #
// // const FONTFACESCSSNAME = 'briel_font-faces.css'; # un
// const COMMENTDOCPATH = SITEROOT . 'comments.txt'; # ren COMMENTDOCTPATH
// // const PHPDIRFILEPATH = SITEROOT . 'php/'; # un
// // const COMMENTSTYLEPATH = STYLEDIR . 'comment.css'; # un (no comment page yet)
// const HEADERNAVSTYLEPATH = STYLEDIR . 'header-nav.css'; #
// const NAVBUTTONSTYLEPATH = STYLEDIR . 'nav-buttons.css'; #
// const ARCHIVESTYLEPATH = STYLEDIR . 'archive.css'; #
// const BLOGARCHIVEFILENAME = 'weblogs.html'; # un
// // const BLOGARCHIVEPATH = SITEROOT . BLOGARCHIVEFILENAME; # UN
// const BLOGARCHIVEPATH = SITEROOT . 'page/weblogs.html'; # 
// const MASTERDBNAME = "briel_comics_test"; #
// const USERDBNAME = "breel_user_submitted"; #

// const SQLDATETIMEFORMAT = 'Y-m-d H:i:s'; #

/**
 * Summary of Briel\createDateFromSQLDateTime
 * @param string $dateStr
 * @return bool|\DateTimeImmutable
 */
function createDateFromSQLDateTime(string $dateStr) {
    return \DateTimeImmutable::createFromFormat(SQLDATETIMEFORMAT, $dateStr);
}

?>