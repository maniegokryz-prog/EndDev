<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache reset.";
} else {
    echo "OPCache not enabled or restricted.";
}
?>
