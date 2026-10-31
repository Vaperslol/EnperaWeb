<?php
// A nyilvános /admin.php belépési pont le van tiltva.
// Bármilyen hozzáférési kísérlet esetén azonnali átirányítás a főoldalra.
header('Location: index.php');
exit;
