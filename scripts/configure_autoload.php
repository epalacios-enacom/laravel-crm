<?php

if ($argc < 2) {
    echo "[ERROR] Missing root path argument.\n";
    exit(1);
}
$krayinRoot = $argv[1];
$composerFile = $krayinRoot . '/composer.json';

echo "  -> Processing: $composerFile\n";

if (!file_exists($composerFile)) {
    echo "  [ERROR] composer.json not found.\n";
    exit(1);
}

$content = file_get_contents($composerFile);
$json = json_decode($content, true);

if (!$json) {
    echo "  [ERROR] Could not decode composer.json.\n";
    exit(1);
}

$namespace = 'Webkul\\EnacomLeadOrg\\';
$path = 'packages/Webkul/EnacomLeadOrg/src';

if (isset($json['autoload']['psr-4'][$namespace]) && $json['autoload']['psr-4'][$namespace] === $path) {
    echo "  -> Autoload PSR-4 already configured correctly.\n";
    exit(0);
}

echo "  -> Adding/updating PSR-4 entry.\n";
$json['autoload']['psr-4'][$namespace] = $path;

$newContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($composerFile, $newContent);

echo "  -> composer.json updated successfully.\n";
