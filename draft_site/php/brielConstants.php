<?php 
namespace Briel;

const LOCALSITE = false;

const FILEFOLDER = 'C:/Users/gabri/Code/Briel comics website/briel-comics';
define("FILEFOLDERREGEXP", '(' . str_replace('/', '[/\\\]', FILEFOLDER) . ')');
const SITEFOLDER = '/draft_site';
const FILEROOT = FILEFOLDER . SITEFOLDER;
if (LOCALSITE) define("SITEROOT", FILEROOT);
else define("SITEROOT", SITEFOLDER);

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
const FONTFACESPATH = SITEROOT . '/styles/briel_font-faces.css';
const SITEICONPATH = SITEROOT . '/images/smileicon.ico';

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