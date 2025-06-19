<?php
/**
 * scriptlet_aggregator.php
 *
 * Fetch multiple uBOL-home scriptlet JS files, extract their
 * argsList, hostnamesMap, and exceptionsMap, then aggregate &
 * group into two rule arrays: hostRules and exceptionRules.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/txt/prevent-setTimeout.txt';

// 1) Raw scriptlet URLs (uBOL-home)
$urls = [
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-cookies.prevent-setTimeout.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-others.prevent-setTimeout.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-overlays.prevent-setTimeout.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/ublock-filters.prevent-setTimeout.js',
    // …add more here…
];

$hostRules      = [];
$exceptionRules = [];

foreach ($urls as $url) {
    $js = @file_get_contents($url);
    if ($js === false) { continue; }

    // 2) Extract argsList (DOTALL)
    if (preg_match('/\bconst\s+argsList\s*=\s*(\[[\s\S]*?\]);/s', $js, $m)) {
        $argsList = json_decode($m[1], true);
    } else {
        $argsList = [];
    }

    // Helper: extract a Map([...]) to PHP array
    $extractMap = function(string $name) use ($js) {
        $re = '/\bconst\s+' . preg_quote($name, '/') 
            . '\s*=\s*new\s+Map\s*\(\s*(\[[\s\S]*?\])\s*\)/s';
        if (preg_match($re, $js, $mm)) {
            return json_decode($mm[1], true);
        }
        return [];
    };

    // 3a) hostnamesMap → hostRules
    $entries = $extractMap('hostnamesMap');
    foreach ($entries as list($pattern, $indices)) {
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
    $entries = $extractMap('exceptionsMap');
    foreach ($entries as list($pattern, $indices)) {
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

// 4) Deduplicate
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

$hostRules      = dedupe($hostRules);
$exceptionRules = dedupe($exceptionRules);

// 5) Group by pattern (merge args arrays)
function groupRules(array $rules): array {
    $grouped = [];
    foreach ($rules as $rule) {
        $pat = $rule['pattern'];
        $arg = $rule['args'][0];
        if (!isset($grouped[$pat])) {
            $grouped[$pat] = ['pattern'=>$pat,'args'=>[]];
        }
        if (!in_array($arg, $grouped[$pat]['args'], true)) {
            $grouped[$pat]['args'][] = $arg;
        }
    }
    return array_values($grouped);
}

$hostRules      = groupRules($hostRules);
$exceptionRules = groupRules($exceptionRules);

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
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ")";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Prevent setTimeout update complete";
flush();
