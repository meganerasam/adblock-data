<?php
/**
 * cosmetic-css.php
 *
 * Fetch multiple cosmetic filter lists (e.g. EasyList, AdGuard Base),
 * extract only the domain-less CSS selectors (lines beginning with "##"),
 * and return them as a JSON array.
 *
 * Your JS content-script will consume this array to hide matching elements.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/csometic_css.txt';

// 1) Define all filter URLs you want to pull from.
//    Add or remove entries here as your sources change.
$sources = [
    'https://easylist-downloads.adblockplus.org/easylist.txt',
    'https://raw.githubusercontent.com/AdguardTeam/FiltersRegistry/master/filters/filter_2_Base/filter.txt',
    // 'https://secure.fanboy.co.nz/fanboy-annoyance_ubo.txt'
    // e.g. 'https://another-source.com/my-filters.txt',
];

// Will hold every selector we find (`##selector` → `selector`)
$selectors = [
    ".adsbygoogle",
    "div[data-content=\"Advertisement\"]",
];

foreach ($sources as $url) {
    // Attempt to fetch the remote filter list
    $content = @file_get_contents($url);
    if ($content === false) {
        // If it fails (network issue, 404, etc.), skip and continue
        continue;
    }

    // Split the file into lines, keeping cross-platform line breaks
    foreach (preg_split('/\R/', $content) as $line) {
        $line = trim($line);
        // Only domain-less cosmetic rules start with "##"
        if (strpos($line, '##') === 0) {
            // Remove the leading "##"
            $sel = substr($line, 2);
            // Ignore empty selectors
            if ($sel !== '') {
                $selectors[] = $sel;
            }
        }
    }
}

// 2) Deduplicate selectors to avoid redundant CSS rules
$selectors = array_values(array_unique($selectors));

// 3) Output the final array as pretty-printed JSON
echo json_encode($selectors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
