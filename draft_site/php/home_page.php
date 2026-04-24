<?php 
if (!defined('UNSETDEFAULT')) define('UNSETDEFAULT', '');

if (!isset($prevLink)) $prevLink = UNSETDEFAULT;
if (!isset($prevUpd8Link)) $prevUpd8Link = UNSETDEFAULT;
?>
<!doctype html>
<html lang="en-US">
    <head>
        <!-- Good to include charset just to prevent weird errors later on. -->
        <meta charset="utf-8">
        
        <!-- Prevents mobile browsers from screwing with you. -->
        <meta name="viewport" content="width=device-width">

        <meta name="author" content="Breel">
        <meta name="description" content="A home page for a comics website.">
        
        <title>Breel Comix</title>
        <link rel="icon" href="./images/smileicon.ico" type="image/x-icon">

        <link href="./styles/briel_font-faces.css" rel="stylesheet">
        <link href="./styles/reading_page_style.css" 
              rel="stylesheet" 
              id="reading_stylesheet"> 

        <script type="module" src="./js/reading_page_script.js"></script>
    </head>

    <body>
        <a href="<?= $prevLink ?>" class="nav-button prev-button" title="Previous"></a>

        <div class="page-display">         
            <header>
                <?php
    $bannerFileStem = '';
    $bannerAlt = 'Blank banner';
    switch(rand(0,1)) {
        case 1: 
            $bannerFileStem = '2026-04-21BrielComicsBanner';
            $bannerAlt = 'Briel Comics';
            break;
        default: 
            $bannerFileStem = '2026-04-20BreelComixBanner';
            $bannerAlt = 'Breel Comics';
    }
                ?>
                <img
                    srcset="./images/<?= $bannerFileStem ?>_800w.png 800w, 
                            ./images/<?= $bannerFileStem ?>_1400w.png 1400w"
                    src="./images/<?= $bannerFileStem ?>_800w.png"
                    sizes="(max-width: 800px) 100vw, 
                           (max-width: 1500px) 800px, 
                           1400px"
                    alt="<?= $bannerAlt ?>";
                >
            </header>

            <main> 
                <?php 
    require 'comicDisplayElements.php'; 
                ?>
                <nav>
                    <p class="nav-line">
                        <a href="<?= $prevLink ?>" 
                           class="nav-button prev-button">Previous</a>
                        <?php
    if (isset($nextLink)) {
                        ?>
                        <a href="<?= $nextLink ?>" 
                           class="nav-button next-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="<?= $prevUpd8Link ?>" 
                           class="nav-button prev-upd8-button">Skip back</a>
                        <?php
    if (isset($nextUpd8Link)) {
                        ?>
                        <a href="<?= $nextUpd8Link ?>" 
                           class="nav-button next-upd8-button">Next</a>
                        <?php 
    }
                        ?>
                    </p>
                    <p class="nav-line">
                        <a href="archive_page.html" 
                           class="nav-button archive-button">Archive</a>
                    </p>
                </nav>

                <section id="blog_section">
                    <h2>Web log</h2>
                    <br>
                    <?= $blogText ?>
                    <br>
                    <?= $blogPostDate ?>
                </section>

                <?php 
    require 'comicTagsCWsDescElements.php'; 
                ?>
            </main>

            <footer>
                <?php
    require 'copyrightElement.php';
                ?>
            </footer>

        </div>  
        
        <?php 
if (isset($nextLink)) { 
        ?>
        <a href="<?= $nextLink ?>" class="nav-button next-button" title="Next"></a>
        <?php
}
        ?>
    </body>
</html>
