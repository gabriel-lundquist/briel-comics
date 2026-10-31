<?php
namespace Briel;

$setting = parse_ini_file("dynamic.ini");
require_once $setting['BACKENDSCRIPTDIR'] . 'dbRead.php';
require_once $setting['BACKENDSCRIPTDIR'] . 'pageGen.php';

$db = pdoConnect();
$hasCommented = $_POST AND !empty($_POST['comment']);
ob_start();
?>
<!doctype html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width"/>
        <meta name="author" content="Breel">
        <meta name="description" content="Breel comics search page.">
        
        <title>Comment | <?= randomPageTitle() ?></title>
        <link rel="icon" href="<?= SITEICONPATH ?>" type="image/x-icon" />

        <link href="<?= FONTFACESPATH ?>" rel="stylesheet" />
        <link href="<?= LISTSTYLEPATH ?>" rel="stylesheet" />
        <link href="<?= HEADERNAVSTYLEPATH ?>" rel="stylesheet" />
        <link href="<?= NAVBUTTONSTYLEPATH ?>" rel="stylesheet" />
    </head>

    <body>
        <header>
            <div class="bar">
                <?php 
    generateHomeButton(labelID: 'header-home-label', textFirst: true);
                ?>
                <h1>Comment</h1>
                <?php 
    generateArchiveButton(labelID: 'header-archive-label', leftGraphic: false);
                ?>
            </div>
        </header>

        <main>
            <p>Thank you for reading! 
                There is no comment section (yet), just an anonymous feedback box. 
                I love reading fanmail :)
            </p>

            <?php 
    if ($hasCommented) {
        // we want the current date and time and the text of the post
        $nowTime = date('h:i:sa');
        $nowDate = date('l, F jS, Y');
        file_put_contents(
                COMMENTDOCPATH, 
                "\t***\n\t***\n\nAt $nowTime on $nowDate, someone sent you the following:\n"
                        . "\t---\n{$_POST['comment']}\n\n", 
                FILE_APPEND
        );

        echo "<article class=\"prev-comment\">\n<h3>You just commented:</h3>\n<p>" 
                . htmlspecialchars($_POST['comment']) . "</p>\n</article>\n";
    }       ?>

            <form action="<?= COMMENTPATH ?>" method="post">
                <label for="comment">You say:</label>
                <textarea 
                        id="comment" 
                        name="comment" 
                        rows="5" 
                        cols="34" 
                        autofocus="true"
                        minlength="2"
                        maxlength="10000"
                        title=""
                        required
                ></textarea>
                <button type="submit">Send</button>
            </form>

        </main>

        <footer>
            <nav class="nav-line">
                <?php 
                // TODO: put this in session storage? I mean I guess it might update...
    generatePrevButton(executeAndFetch( getPageRecordFromIDStmt($db), 
                                        [getEndofRecentPageOrderID($db)]    )['path']);
    echo "\n";
    generateRandomButton();
                ?>
            </nav>
            <?php 
    include DYNAMICPAGEDIR . 'copyrightElement.php'; 
            ?>
        </footer>

    </body>
</html>

<?php 
echo ensureServerPaths(ob_get_clean());
?>