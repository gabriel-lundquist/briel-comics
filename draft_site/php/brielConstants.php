<?php 
namespace Briel;

const DEBUG = true;

const FILEROOT = 'C:/Users/gabri/Code/Briel comics website/briel-comics/draft_site';
if (DEBUG) define("SITEROOT", '/draft_site');
else define("SITEROOT", FILEROOT);

const SEARCHFIELDSPECS = [  'tag', 
                            'cw', 
                            'title', 
                            'day', 
                            'month', 
                            'year', 
                            'description'   ];

const WHITESPACES = " \n\t\r";
const PUNCTUATION = '~`!@#$%^&*()_-+=[]{}\\|:;"\'<>,.?/¡¿';

const SEARCHPATH = SITEROOT . '/search.php';
const HOMEPATH = SITEROOT . '/home.html';
const COMMENTPATH = SITEROOT . '/comments.php';
const BLOGARCHIVEPATH = SITEROOT . '/weblog.html';
const SEARCHCACHEDIRPATH = SITEROOT . '/searchcache';
const BLANKSEARCHPATH = SITEROOT . '/blank_search.html';
const ARCHIVEDIRPATH = SITEROOT . "/archive";
const ARCHIVESTARTPATH = ARCHIVEDIRPATH . '/archive_p1.html';

const FONTFACESCSSNAME = 'briel_font-faces.css';

const SITEICONNAME = 'smileicon.ico';

const SQLDATETIMEFORMAT = 'Y-m-d H:i:s';

?>