Hello World!

<p><em>User agent:</em> <?= $_SERVER['HTTP_USER_AGENT'] ?> </p>

<p><em>Name:</em> <?= $_GET['name'] ?> </p>

<p><em>Age:</em> <?= (int) $_GET['age'] ?> </p>

<?php

phpinfo();
// echo '<p><e>User agent:</e> ' . $_SERVER['HTTP_USER_AGENT'] . '</p>';

?>