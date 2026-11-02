<?php 
# Folder paths should have a slash at the end
namespace Briel;

$loaded = parse_ini_file("breel.ini", scanner_mode: INI_SCANNER_TYPED);
$namespacePrefix = "Briel\\";

function defineList(array $defaults, array $loaded, string $prefix) {	
	if ($loaded) {
		foreach ($defaults as $key => $value) {
			define($prefix . $key, isset($loaded[$key]) ? $loaded[$key] : $value);
		}
	} else {
		foreach ($defaults as $key => $value) {
			define($prefix . $key, $value);
		}
	}
}

const SEARCHFIELDSPECS = [	"tag", 
							"cw", 
							"title", 
							"day", 
							"month", 
							"year",
							"description"	];

defineList(	[	"MASTERFOLDER" => "/srv/breel-site/", 
            	"SERVEDFOLDER" => "/"	], 
			$loaded, 
			$namespacePrefix	);

defineList(	[	"SITEROOT" => MASTERFOLDER . "www/",
				"BACKENDROOT" => MASTERFOLDER . "backend/"	],  
			$loaded, 
			$namespacePrefix	);

defineList(	[	"STYLEDIR" => SITEROOT . "style/", 
				"PAGEDIR"=> SITEROOT . "page/", 
				"SCRIPTDIR"=> SITEROOT . "script/", 
				"IMAGEDIR"=> SITEROOT . "img/"	],  
			$loaded, 
			$namespacePrefix	);

defineList( [	"SEARCHCACHEDIR" => PAGEDIR . "search/", 
				"ARCHIVEDIR" => PAGEDIR . "archive/",
				"READINGDIR" => PAGEDIR . "comic/",
				"DYNAMICPAGEDIR" => PAGEDIR . "dynamic/",
				"COLORSTYLEDIR"=> STYLEDIR . "color/", 
				"DECORATIONDIR"=> IMAGEDIR . "decoration/", 
				"COMICIMAGEDIR" => IMAGEDIR . "comic/",	],  
			$loaded, 
			$namespacePrefix	);

defineList( [ 	"LOCALSITE" => true, 

				"MASTERDBNAME" => "breel_comics", 
				"USERDBNAME" => "breel_user_submitted",
				"DBHOST" => "localhost", 
				"DBPORT" => 3306, 
				"DBMANIPUSER" => "manip", 
				"DBPAGEGENUSER" => "pagegen", 
				"DBMANIPUSERPW" => "",
				"DBPAGEGENUSERPW" => "",
				"SQLDATETIMEFORMAT" => "Y-m-d H:i:s",

				"WHITESPACES" => " \n\t\r",

				"HOMEPATH"=> PAGEDIR . "home.html",
				"SEARCHPATH" => DYNAMICPAGEDIR . "search.php",
				"COMMENTPATH"=> DYNAMICPAGEDIR . "comments.php", 
				"RANDOMPATH"=> DYNAMICPAGEDIR . "random.php", 
				"VIEWPATH"=> DYNAMICPAGEDIR . "view.php", 
				"BLOGARCHIVEPATH"=> PAGEDIR . "weblogs.html", 
				"ARCHIVESTARTPATH" => ARCHIVEDIR . "archive_p1.html",
				"BLANKSEARCHPATH"=> PAGEDIR . "search_blank.html", 
				
				"HOMESTYLEPATH"=> STYLEDIR . "home.css", 
				"READINGSTYLEPATH" => STYLEDIR . "reading.css",
				"LISTSTYLEPATH"  => STYLEDIR . "entry-list.css",
				"ARCHIVESTYLEPATH" => STYLEDIR . "archive.css", 
				"SEARCHSTYLEPATH" => STYLEDIR . "search.css", 
				"VIEWSTYLEPATH" => STYLEDIR . "view.css", 
				"FONTFACESPATH"=> STYLEDIR . "briel_font-faces.css", 
				"HEADERNAVSTYLEPATH"=> STYLEDIR . "header-nav.css", 
				"NAVBUTTONSTYLEPATH"=> STYLEDIR . "nav-buttons.css", 
				"COMMENTSTYLEPATH"=> STYLEDIR . "comment.css", 
		
				"READINGSCRIPTPATH"=> SCRIPTDIR . "reading.js",
				"SEARCHSCRIPTPATH"=> SCRIPTDIR . "search.js",
				"VIEWSCRIPTPATH"=> SCRIPTDIR . "view.js",
				"ARCHIVESCRIPTPATH"=> SCRIPTDIR . "archive.js",

				"SITEICONPATH" => DECORATIONDIR . "witch.ico",
				"COMMENTDOCPATH" => MASTERFOLDER . "comments.txt",	],  
			$loaded, 
			$namespacePrefix	);

// This is the regular expression that matches paths on the local filesystem
// define($namespacePrefix . "FILEFOLDERREGEXP", '(' . str_replace('/', '[/\\\]', SITEROOT) . ')');

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
// const DECORATIONDIR = IMAGESDIRPATH . 'decoration/'; # ren DECORATIONDIR
// # added COMICIMAGEDIR
// // const SITEICONNAME = 'smileicon.ico'; # un
// const SITEICONPATH = DECORATIONDIR . 'smileicon.ico'; #
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

function orderArrayByOrderedKeys(   array $orderedKeys, 
                                    array $toOrder  ) {
    return array_combine($orderedKeys, array_map(fn($key) => $toOrder[$key], $orderedKeys));
}

// var_export($loaded);
?>