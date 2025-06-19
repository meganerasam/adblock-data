<?php
/**
 * adjust-setInterval.php
 *
 * Aggregates uBO-Lite adjust-setInterval scriptlets into JSON:
 *   { hostRules: [...], exceptionRules: [...] }
 */
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/txt/adjust-setInterval.txt';

$urls = [
    // Overlay & pol-0 scriptlets for setInterval
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-overlays.adjust-setInterval.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/ublock-filters.adjust-setInterval.js',
];

$hostRules      = [];
$exceptionRules = [];

foreach ($urls as $url) {
    $js = @file_get_contents($url);
    if ($js === false) continue;

    // Extract argsList (needle, delay, boost)
    if (preg_match('/\bconst\s+argsList\s*=\s*(\[[\s\S]*?\]);/s', $js, $m)) {
        $argsList = json_decode($m[1], true);
    } else {
        $argsList = [];
    }

    // Helper: get Map([...]) entries
    $extractMap = function($name) use ($js) {
        $re = '/\bconst\s+' . preg_quote($name, '/') 
            . '\s*=\s*new\s+Map\s*\(\s*(\[[\s\S]*?\])\s*\)/s';
        return preg_match($re, $js, $mm)
            ? json_decode($mm[1], true)
            : [];
    };

    // hostnamesMap → hostRules
    foreach ($extractMap('hostnamesMap') as list($pattern, $idx)) {
        $idxs = is_array($idx) ? $idx : [$idx];
        foreach ($idxs as $i) {
            if (isset($argsList[$i])) {
                $hostRules[] = [
                    'pattern' => $pattern,
                    'args'    => [ $argsList[$i] ]
                ];
            }
        }
    }
    // exceptionsMap → exceptionRules
    foreach ($extractMap('exceptionsMap') as list($pattern, $idx)) {
        $idxs = is_array($idx) ? $idx : [$idx];
        foreach ($idxs as $i) {
            if (isset($argsList[$i])) {
                $exceptionRules[] = [
                    'pattern' => $pattern,
                    'args'    => [ $argsList[$i] ]
                ];
            }
        }
    }
}

// Deduplicate and group by pattern
function dedupe(array $rules): array {
    $seen = []; $out = [];
    foreach ($rules as $r) {
        $key = json_encode($r);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $r;
        }
    }
    return array_values($out);
}
function groupRules(array $rules): array {
    $grp = [];
    foreach ($rules as $r) {
        $p = $r['pattern'];
        $arg = $r['args'][0];
        if (!isset($grp[$p])) {
            $grp[$p] = ['pattern'=>$p,'args'=>[]];
        }
        if (!in_array($arg, $grp[$p]['args'], true)) {
            $grp[$p]['args'][] = $arg;
        }
    }
    return array_values($grp);
}

$hostRules      = groupRules(dedupe($hostRules));
$exceptionRules = groupRules(dedupe($exceptionRules));

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
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ") - adjust-setInterval";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Adjust setInterval update complete";
flush();
