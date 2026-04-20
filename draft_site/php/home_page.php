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
            <main> 
                <?php require 'comicDisplayElements.php'; ?>

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
                        <a href="<?= $prevUpd8Link ?>" class="nav-button prev-upd8-button">Skip back</a>
                    </p>
                    <p class="nav-line">
                        <a href="archive_page.html" class="nav-button archive-button">Archive</a>
                    </p>
                </nav>

                <section id="tag_section">
                    <h2>Tags</h2>
                    <p id="tags-para">
                    <?php
                        foreach ($tags as $tag) {
                            echo "<a href=$searchPageLocation?tag=$tag>$tag</a>\n";
                        }
                    ?>
                    </p>
                </section>

                <section id="cw_section">
                    <h2>Content Warnings</h2>
                    <p id="cws-para">
                    <?php
                        foreach ($contWarns as $cw) {
                            echo "<a href=$searchPageLocation?cw=$cw>$cw</a>\n";
                        }
                    ?>
                    </p>
                </section>

                <section id="desc_section">
                    <h2><a href="#text_description" 
                           class="text_desc_heading">Text Description</a></h2>
                    <p class="text-desc">
                        <?= $pageRecord["imagedesc"] ?>
                    </p>
                </section>
            </main>

            <footer>
                <p class="copyright">
                    ©Copyright 2025-<?= getdate()['year'] ?> by Briel Comics.
                    All rights reserved.
                </p>
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
