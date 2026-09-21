#!/usr/bin/env bash
#
# reproduce.sh - verify the AREA 5 finding is FIXED (post-fix evidence).
#
# CWE-79 (MEDIUM): a news payload could inject a live event handler by closing a
# preceding attribute with a quote (<div title="x"onmouseover=...>), which the
# old sanitizer regex missed. After the fix the handler is stripped.
#
# Usage:  REPRO_ROOT=<repo> bash reproduce.sh
# Exit 0 = pass.

set -u

echo "=================================================================="
echo " Reproduce AFTER fix - news sanitizer (area5-news-xss)"
echo "=================================================================="

php -r '
chdir(getenv("REPRO_ROOT"));
require "config.php";
require "controllers/NewsController.php";
$m = new ReflectionMethod(NewsController::class, "sanitizeNewsContent");
$m->setAccessible(true);
$c = new NewsController($GLOBALS["connect"] ?? null);
$clean = function (string $in) use ($m, $c): string { return (string) $m->invoke($c, $in); };

$pass = 0; $fail = 0;
$check = function (string $label, bool $ok, string $detail) use (&$pass, &$fail) {
    printf("  [%s] %-52s -> %s\n", $ok ? "PASS" : "FAIL", $label, $detail);
    $ok ? $pass++ : $fail++;
};

$q = $clean("<div title=\"x\"onmouseover=\"alert(1)\">t</div>");
$check("quote-boundary handler stripped (CWE-79)",
       stripos($q, "onmouseover") === false, "onmouseover removed");

$f = $clean("<p title=\"a\"onfocus=\"alert(2)\" autofocus>t</p>");
$check("autofocus/onfocus handler stripped",
       stripos($f, "onfocus") === false, "onfocus removed");

$j = $clean("<a href=\"javascript:alert(1)\">x</a>");
$check("javascript: URI neutralized",
       stripos($j, "javascript:") === false, $j);

$s = $clean("<p>before</p><script>alert(1)</script>after");
$check("script block removed",
       stripos($s, "<script") === false && stripos($s, "alert(1)") === false, $s);

$b = $clean("<p>Halo <strong>dunia</strong></p>");
$check("benign markup preserved",
       stripos($b, "<strong>dunia</strong>") !== false, $b);

echo "\n==================================================================\n";
printf(" RESULT: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
' 2>&1

