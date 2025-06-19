<?php
/**
 * specificCss.php
 *
 * Fetches multiple specificCss filter lists (AdGuard Base + EasyList + more…),
 * extracts only the domain-scoped specificCss rules (`domain##selector`),
 * and returns a JSON map:
 *     domain ⇒ [ selector, … ]
 *
 * Usage:
 *   https://your-server/specificCss.php
 *
 * Output:
 *   {
 *     "forbes.com": [".ad-slot", ".ad-banner-top"],
 *     "cnn.com":    [".ad-container > .banner"],
 *     …
 *   }
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/txt/specific_css.txt';

// 1) List of filter sources to fetch
$sources = [
    'https://raw.githubusercontent.com/AdguardTeam/FiltersRegistry/master/filters/filter_2_Base/filter.txt',
    'https://easylist-downloads.adblockplus.org/easylist.txt',
    // 'https://secure.fanboy.co.nz/fanboy-annoyance_ubo.txt'
    // add more URLs here as needed
];

// 2) Initialize empty map
$map = [];

// 3) Fetch & parse each source
foreach ($sources as $filterUrl) {
    $raw = @file_get_contents($filterUrl);
    if ($raw === false) {
        // Skip this source if unreachable
        continue;
    }

    // Split into lines
    $lines = preg_split('/\R/', $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty or comment lines (EasyList & AdGuard use '!' for comments)
        if ($line === '' || strpos($line, '!') === 0) {
            continue;
        }
        // Must contain a '##' to be a specificCss rule
        if (strpos($line, '##') === false) {
            continue;
        }
        // Split into "domains" and "selector"
        list($domains, $selector) = explode('##', $line, 2);
        $selector = trim($selector);
        if ($selector === '') {
            continue;
        }
        // For each comma-separated domain
        foreach (explode(',', $domains) as $domain) {
            $d = trim($domain);
            // Skip domainless rules (i.e. blank $d)
            if ($d === '') {
                continue;
            }
            $map[$d][] = $selector;
        }
    }
}

// 4) Deduplicate selectors for each domain
foreach ($map as &$selectors) {
    $selectors = array_values(array_unique($selectors));
}
unset($selectors);

// 5) If the $map length is equal to 0, then do not commit and exit.
if (count($map) <= 0) {
    echo "final map array length 0, GitHub not updated.\n";
    flush();
    exit;
}

// 6) Write data to activeFile
file_put_contents($activeFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// 7) Commit changes
exec("git add "
    . escapeshellarg($activeFile)
);
exec("git config user.name 'github-actions[bot]'");
exec("git config user.email 'github-actions[bot]@users.noreply.github.com'");
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ") - specific-css";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Specific CSS update complete";
flush();
