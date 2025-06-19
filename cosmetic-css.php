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
$activeFile           = __DIR__ . '/txt/cosmetic_css.txt';

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

// 3a) If the $selectors length is equal to 2, then do not commit and exit.
if (count($selectors) <= 2) {
    echo "No new selectors found, GitHub not updated.\n";
    flush();
    exit;
}

// 3b) Read activeFile and put it inside an array
$activeSelectors = [];
if (file_exists($activeFile)) {
    $activeFileContent = file_get_contents($activeFile);
    if ($activeFileContent !== false) {
        // Split the content into lines, keeping cross-platform line breaks
        $activeSelectors = preg_split('/\R/', $activeFileContent);
        // Remove empty lines
        $activeSelectors = array_filter($activeSelectors, 'trim');
    } else {
        echo "TXT file empty.\n";
        flush();
        exit;
    }
} else {
    echo "Error fetching the active.txt file.\n";
    flush();
    exit;
}

// 3c) Create a new array with selectors that are not in the active file
$newSelectors = array_diff($selectors, $activeSelectors);

// 3d) If the new selectors array is empty, exit
if (empty($newSelectors)) {
    echo "NO NEW selectors found, GitHub not updated.\n";
    flush();
    exit;
}

// 3e) Add the $newSelectors to active file content. Do not rewrite all, add only newSelctors in order to have a certain added date order, at the end of existing file.
$selectors = array_merge($activeSelectors, $newSelectors);

// 3f) Update the active file with the new selectors
file_put_contents($activeFile,   implode("\n", $selectors)   . "\n");

// 3g) Commit changes
exec("git add "
    . escapeshellarg($activeFile)
);
exec("git config user.name 'github-actions[bot]'");
exec("git config user.email 'github-actions[bot]@users.noreply.github.com'");
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ")";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Cosmetic CSS update complete";
flush();
?>