<?php
/**
 * prevent-setInterval.php
 *
 * Fetches uBOL-home scriptlets for setInterval,
 * extracts hostnamesMap + exceptionsMap + argsList,
 * then builds & groups hostRules/exceptionRules into JSON.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/txt/prevent-setInterval.txt';

/** 1) List your raw scriptlet URLs here **/
$urls = [
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-others.prevent-setInterval.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-overlays.prevent-setInterval.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/ublock-filters.prevent-setInterval.js',
    // …add more here…
];

$hostRules      = [];
$exceptionRules = [];

foreach ($urls as $url) {
    $js = @file_get_contents($url);
    if ($js === false) { continue; }

    // 2) Extract argsList (multiline)
    if (preg_match('/\bconst\s+argsList\s*=\s*(\[[\s\S]*?\]);/s', $js, $m)) {
        $argsList = json_decode($m[1], true);
    } else {
        $argsList = [];
    }

    // Helper: get Map([...]) contents
    $extractMap = function(string $name) use ($js) {
        $re = '/\bconst\s+' . preg_quote($name, '/') 
            . '\s*=\s*new\s+Map\s*\(\s*(\[[\s\S]*?\])\s*\)/s';
        if (preg_match($re, $js, $mm)) {
            return json_decode($mm[1], true);
        }
        return [];
    };

    // 3a) hostnamesMap → hostRules
    foreach ($extractMap('hostnamesMap') as list($pattern, $indices)) {
        $indices = is_array($indices) ? $indices : [$indices];
        foreach ($indices as $i) {
            if (isset($argsList[$i])) {
                $hostRules[] = [
                    'pattern' => $pattern,
                    'args'    => [ $argsList[$i] ]
                ];
            }
        }
    }
    // 3b) exceptionsMap → exceptionRules
    foreach ($extractMap('exceptionsMap') as list($pattern, $indices)) {
        $indices = is_array($indices) ? $indices : [$indices];
        foreach ($indices as $i) {
            if (isset($argsList[$i])) {
                $exceptionRules[] = [
                    'pattern' => $pattern,
                    'args'    => [ $argsList[$i] ]
                ];
            }
        }
    }
}

// Dedupe helper
function dedupe(array $rules): array {
    $seen = [];
    $out  = [];
    foreach ($rules as $r) {
        $key = json_encode($r);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $r;
        }
    }
    return array_values($out);
}

// Group by pattern → one object per hostname, merging all args
function groupRules(array $rules): array {
    $grouped = [];
    foreach ($rules as $rule) {
        $pat = $rule['pattern'];
        $arg = $rule['args'][0];
        if (!isset($grouped[$pat])) {
            $grouped[$pat] = [ 'pattern' => $pat, 'args' => [] ];
        }
        if (!in_array($arg, $grouped[$pat]['args'], true)) {
            $grouped[$pat]['args'][] = $arg;
        }
    }
    return array_values($grouped);
}

// 6) Write data to activeFile
file_put_contents($activeFile, json_encode([
    'hostRules'      => $hostRules,
    'exceptionRules' => $exceptionRules
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// 7) Commit changes
exec("git add "
    . escapeshellarg($activeFile)
);
exec("git config user.name 'github-actions[bot]'");
exec("git config user.email 'github-actions[bot]@users.noreply.github.com'");
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ") - prevent-setInterval";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Prevent setInterval update complete";
flush();
