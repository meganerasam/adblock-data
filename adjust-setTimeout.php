<?php
/**
 * adjust-setTimeout.php
 * Aggregates uBO-Lite adjust-setTimeout scriptlets into JSON.
 */
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// File paths
$activeFile           = __DIR__ . '/txt/adjust-setTimeout.txt';

$urls = [
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/annoyances-overlays.adjust-setTimeout.js',
    'https://raw.githubusercontent.com/uBlockOrigin/uBOL-home/main/chromium/rulesets/scripting/scriptlet/ublock-filters.adjust-setTimeout.js',
];

$hostRules=[]; 
$exceptionRules=[];

// Same extraction logic as above, but for adjust-setTimeout
foreach ($urls as $url) {
    $js = @file_get_contents($url);
    if (!$js) continue;
    if (preg_match('/\bconst\s+argsList\s*=\s*(\[[\s\S]*?\]);/s', $js, $m)) {
        $argsList = json_decode($m[1], true);
    } else {
        $argsList = [];
    }
    $extractMap = function($name) use($js) {
        $re = '/\bconst\s+' . preg_quote($name, '/') . '\s*=\s*new\s+Map\s*\(\s*(\[[\s\S]*?\])\s*\)/s';
        return preg_match($re, $js, $mm) ? json_decode($mm[1], true) : [];
    };
    foreach ($extractMap('hostnamesMap') as list($pattern, $i)) {
        foreach ((array)$i as $idx) {
            if (isset($argsList[$idx])) {
                $hostRules[]   = ['pattern'=>$pattern,'args'=>[$argsList[$idx]]];
            }
        }
    }
    foreach ($extractMap('exceptionsMap') as list($pattern, $i)) {
        foreach ((array)$i as $idx) {
            if (isset($argsList[$idx])) {
                $exceptionRules[] = ['pattern'=>$pattern,'args'=>[$argsList[$idx]]];
            }
        }
    }
}

// Dedupe & group (same as above)
function dedupe($a){ $s=[];$o=[]; foreach($a as$r){ $k=json_encode($r); if(!@$s[$k]){$s[$k]=1;$o[]=$r;} } return$o; }
function group($a){ $g=[]; foreach($a as$r){ $p=$r['pattern']; $arg=$r['args'][0]; if(!isset($g[$p]))$g[$p]=['pattern'=>$p,'args'=>[]]; if(!in_array($arg,$g[$p]['args'],true))$g[$p]['args'][]=$arg;} return array_values($g); }

$hostRules      = group(dedupe($hostRules));
$exceptionRules = group(dedupe($exceptionRules));

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
$msg = "Updated on " . " (" . date('Y-m-d H:i') . ") - adjust-setTimeout";
exec("git commit -m " . escapeshellarg($msg));
exec("git push");

// Output the final array as pretty-printed JSON
echo "Adjust setTimeout update complete";
flush();