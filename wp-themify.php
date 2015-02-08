<?php

require dirname(__FILE__) . '/vendor/autoload.php';

// This is a quick'n'dirty tool for building WP theme files from a single
// HTML file.
//
// Features:
// - Automatically handles wp_head and wp_footer
// - Automatically prepends theme uri when neccessary

$input = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'index.html';

try {
    $themifier = new WPThemifier_Compiler($input);
    $themifier->compile($input);
} catch (Exception $e) {
    echo $e->getMessage(), "\n\n";
    exit(1);
}
