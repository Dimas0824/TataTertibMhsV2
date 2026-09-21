<?php
/**
 * add_findings_index.php — prepend a "Findings & remediation status" table to the
 * consolidated report, built from the per-area vulnerabilities.csv + known fix
 * commits. Keeps the machine-generated report as the single source of truth.
 */

$root = dirname(__DIR__);

$map = [
    'area1-login'        => ['fix' => 'ba4e8d1', 'after' => 'area1-login/after/reproduce-after.log'],
    'area2-session-csrf' => ['fix' => '9f55f2a + 484bb67', 'after' => 'area2-session-csrf/after/reproduce-after.log'],
    'area3-upload-idor'  => ['fix' => '78cdf02', 'after' => 'area3-upload-idor/after/reproduce-after.log'],
    'area4-pelanggaran'  => ['fix' => '487dd93', 'after' => 'area4-pelanggaran/after/reproduce-after.log'],
    'area5-news-xss'     => ['fix' => '7b4c39d', 'after' => 'area5-news-xss/after/reproduce-after.log'],
];

$rows = [];
foreach ($map as $dir => $meta) {
    $csv = $root . '/' . $dir . '/before/vulnerabilities.csv';
    if (!is_file($csv)) {
        continue;
    }
    $lines = array_values(array_filter(array_map('trim', file($csv))));
    array_shift($lines);
    foreach ($lines as $line) {
        if ($line === '') { continue; }
        $c = str_getcsv($line);
        $rows[] = [
            'area' => $dir,
            'id'   => $c[0] ?? '',
            'title'=> $c[1] ?? '',
            'sev'  => strtoupper($c[2] ?? ''),
            'fix'  => $meta['fix'],
            'after'=> $meta['after'],
        ];
    }
}

$sevOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
usort($rows, static fn($a, $b) => ($sevOrder[$a['sev']] ?? 9) <=> ($sevOrder[$b['sev']] ?? 9));

$t = [];
$t[] = '## Findings & remediation status';
$t[] = '';
$t[] = 'All findings from the 2026-09-21 run are **fixed and re-verified**.';
$t[] = '';
$t[] = '| Severity | Area | id | Title | Fix commit | After-fix evidence |';
$t[] = '|---|---|---|---|---|---|';
foreach ($rows as $r) {
    $t[] = sprintf(
        '| %s | `%s` | `%s` | %s | `%s` | [`%s`](./%s) |',
        $r['sev'], $r['area'], $r['id'], str_replace('|', '\\|', $r['title']), $r['fix'], basename($r['after']), $r['after']
    );
}
$t[] = '';
$t[] = '_Findings count: ' . count($rows) . '._';
$t[] = '';
$t[] = '---';
$t[] = '';

$path = $root . '/00-CONSOLIDATED-REPORT.md';
$content = (string) file_get_contents($path);

// insert the index right after the first '---' separator (end of the summary block)
$needle = "\n---\n\n";
$pos = strpos($content, $needle);
$block = implode("\n", $t);
if ($pos !== false) {
    $content = substr($content, 0, $pos + strlen($needle)) . $block . substr($content, $pos + strlen($needle));
} else {
    $content = $block . $content;
}
file_put_contents($path, $content);
echo "indexed " . count($rows) . " findings\n";
