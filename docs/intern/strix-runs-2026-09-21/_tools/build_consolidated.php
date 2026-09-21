<?php
/**
 * build_consolidated.php — assemble a consolidated report + combined SARIF for
 * the DiscipLink 2026-09-21 Strix run.
 *
 * Reads the per-area artefacts (before/ = raw Strix output, after/ = verification)
 * and writes:
 *   docs/intern/strix-runs-2026-09-21/00-CONSOLIDATED-REPORT.md
 *   docs/intern/strix-runs-2026-09-21/COMBINED.sarif
 *
 * Nothing is invented: the narrative is the concatenated Strix reports, and the
 * SARIF runs are copied verbatim from each area's findings.sarif.
 */

$root = dirname(__DIR__); // docs/intern/strix-runs-2026-09-21 (this script lives in _tools/)
$areas = [
    'area1-login'        => 'LOGIN / AUTHENTICATION',
    'area2-session-csrf' => 'SESSION MANAGEMENT & CSRF',
    'area3-upload-idor'  => 'UPLOAD / DOWNLOAD / IDOR / ACCESS CONTROL',
    'area4-pelanggaran'  => 'VIOLATION WORKFLOW (BUSINESS LOGIC / INPUT)',
    'area5-news-xss'     => 'NEWS MODULE (XSS / SANITIZATION)',
];

// ---- COMBINED.sarif: merge every area's runs verbatim -------------------------
$combined = [
    'version' => '2.1.0',
    '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
    'runs'    => [],
];
$sarifCounts = [];
foreach ($areas as $dir => $label) {
    $p = $root . '/' . $dir . '/before/findings.sarif';
    if (!is_file($p)) {
        continue;
    }
    $j = json_decode((string) file_get_contents($p), true);
    if (!is_array($j) || empty($j['runs'])) {
        continue;
    }
    foreach ($j['runs'] as $run) {
        // annotate which area the run belongs to so the combined file is navigable
        $run['properties'] = ($run['properties'] ?? []) + ['discip_link_area' => $dir];
        $combined['runs'][] = $run;
        $sarifCounts[$dir] = count($run['results'] ?? []);
    }
}
file_put_contents(
    $root . '/COMBINED.sarif',
    json_encode($combined, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

// ---- 00-CONSOLIDATED-REPORT.md ------------------------------------------------
$rowsByArea = [];
foreach ($areas as $dir => $label) {
    $csv = $root . '/' . $dir . '/before/vulnerabilities.csv';
    $rows = [];
    if (is_file($csv)) {
        $lines = array_values(array_filter(array_map('trim', file($csv))));
        array_shift($lines); // header
        foreach ($lines as $line) {
            if ($line === '') { continue; }
            $cols = str_getcsv($line);
            $rows[] = $cols;
        }
    }
    $rowsByArea[$dir] = $rows;
}

$out = [];
$out[] = '# DiscipLink — Consolidated STRIX Pentest (2026-09-21)';
$out[] = '';
$out[] = 'Five focused areas were scanned with Strix (white-box: live target + source mounted),';
$out[] = 'one area per run, `reasoning=low`, RPM-safe. Each area keeps its raw Strix output under';
$out[] = '`<area>/before/` and the post-fix verification under `<area>/after/`.';
$out[] = '';
$out[] = '| Area | Label | Findings | SARIF results | After-fix |';
$out[] = '|---|---|---|---|---|';
$total = 0;
foreach ($areas as $dir => $label) {
    $n = count($rowsByArea[$dir]);
    $total += $n;
    $sarifN = $sarifCounts[$dir] ?? 0;
    $hasAfter = is_dir($root . '/' . $dir . '/after') ? 'yes' : 'n/a';
    $out[] = sprintf('| `%s` | %s | %d | %d | %s |', $dir, $label, $n, $sarifN, $hasAfter);
}
$out[] = sprintf('| | **TOTAL** | **%d** | **%d** | |', $total, array_sum($sarifCounts));
$out[] = '';
$out[] = '> `after/` exists only for areas whose findings are fixed and re-verified. `area3-upload-idor`';
$out[] = '> had zero findings, so its `after/` records held defenses + one fixed non-security defect.';
$out[] = '';
$out[] = '---';
$out[] = '';

foreach ($areas as $dir => $label) {
    $out[] = '## ' . $dir . ' — ' . $label;
    $out[] = '';
    $rep = $root . '/' . $dir . '/before/penetration_test_report.md';
    if (is_file($rep)) {
        $out[] = trim((string) file_get_contents($rep));
    } else {
        $out[] = '_(no narrative report; see vulnerabilities.csv)_';
    }
    $out[] = '';
    if (!empty($rowsByArea[$dir])) {
        $out[] = '### Findings index';
        $out[] = '';
        $out[] = '| id | title | severity |';
        $out[] = '|---|---|---|';
        foreach ($rowsByArea[$dir] as $r) {
            $id = $r[0] ?? '';
            $title = $r[1] ?? '';
            $sev = $r[2] ?? '';
            $out[] = sprintf('| `%s` | %s | %s |', $id, $title, $sev);
        }
        $out[] = '';
    }
    if (is_dir($root . '/' . $dir . '/after')) {
        $out[] = '### After-fix evidence';
        $out[] = '';
        $out[] = 'See [`./' . $dir . '/after/README.md`](./' . $dir . '/after/README.md) and';
        $out[] = '[`./' . $dir . '/after/reproduce-after.log`](./' . $dir . '/after/reproduce-after.log).';
        $out[] = '';
    }
    $out[] = '---';
    $out[] = '';
}

file_put_contents($root . '/00-CONSOLIDATED-REPORT.md', implode("\n", $out));

echo "wrote COMBINED.sarif runs=" . count($combined['runs']) . "\n";
echo "wrote 00-CONSOLIDATED-REPORT.md\n";
echo "total findings=" . $total . "\n";
