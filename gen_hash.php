<?php
// Archivo temporal — eliminar después de usarlo
$hash = password_hash('Hotel1993', PASSWORD_BCRYPT);
echo '<pre>Hash para phpMyAdmin:<br><b>' . $hash . '</b></pre>';
echo '<p style="color:red">⚠ Elimina este archivo (gen_hash.php) después de copiar el hash.</p>';
