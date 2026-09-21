#!/usr/bin/env bash
#
# reproduce.sh - verify the AREA 4 finding is FIXED (post-fix evidence).
#
# CWE-20 (MEDIUM): a violation could be stored with a sanction whose tier did
# not match the violation's tier. After the fix, such a store is rejected and
# no row is written.
#
# Usage:  bash reproduce.sh   (needs the project DB reachable; run from repo root
#                             so `php reproduce_probe.php` resolves config.php)
# Exit 0 = pass.

set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../../../.." && pwd)"

echo "=================================================================="
echo " Reproduce AFTER fix - sanction tier validation (area4-pelanggaran)"
echo "=================================================================="

php -r '
chdir(getenv("REPRO_ROOT"));
require "config.php";
$c = $GLOBALS["connect"];

$tatib = $c->query("SELECT id_tata_tertib, tingkat FROM TATA_TERTIB ORDER BY id_tata_tertib LIMIT 1")->fetch();
$dosen = $c->query("SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1")->fetch();
$mhs   = $c->query("SELECT nim FROM MAHASISWA ORDER BY id_mhs LIMIT 1")->fetch();
if (!$tatib || !$dosen || !$mhs) { echo "  [SKIP] seed data missing\n"; exit(2); }

$mismatch = null;
foreach ($c->query("SELECT id_sanksi, tingkat FROM SANKSI")->fetchAll() as $r) {
    if (strcasecmp(trim((string) $r["tingkat"]), trim((string) $tatib["tingkat"])) !== 0) { $mismatch = (int) $r["id_sanksi"]; break; }
}
if ($mismatch === null) { echo "  [SKIP] no mismatched sanction tier available\n"; exit(2); }

require "models/Pelanggaran.php";
$before = (int) $c->query("SELECT COUNT(*) FROM DETAIL_PELANGGARAN")->fetchColumn();
$model = new Pelanggaran();
$res = $model->simpanDetailPelanggaran(
    (string) $dosen["nidn"], (int) $tatib["id_tata_tertib"], (string) $mhs["nim"],
    $mismatch, "ZZAREA4 reproduce mismatch", null, null, "Proses Bimbingan", "Belum Dikerjakan"
);
$after = (int) $c->query("SELECT COUNT(*) FROM DETAIL_PELANGGARAN")->fetchColumn();

$rejected = is_array($res) && ($res["success"] ?? true) === false;
$noRow    = ($before === $after);

printf("  [%s] mismatched sanction rejected          -> %s\n", $rejected ? "PASS" : "FAIL", $rejected ? "success=false" : "accepted");
printf("  [%s] no violation row written              -> before=%d after=%d\n", $noRow ? "PASS" : "FAIL", $before, $after);
echo "\n==================================================================\n";
echo ($rejected && $noRow) ? " RESULT: 2 passed, 0 failed\n" : " RESULT: FAILED\n";
echo "==================================================================\n";
exit(($rejected && $noRow) ? 0 : 1);
' 2>&1

