<?php
// One-time migration: hash any plaintext password rows still in the DB.
// Models/User.php now rejects non-bcrypt hashes at login, so run this after
// importing a legacy .sql dump directly (artisan db:seed already hashes automatically).

declare(strict_types=1);

require __DIR__ . '/../../config.php';

$tables = [
    'MAHASISWA' => 'nim',
    'DOSEN' => 'nidn',
    'ADMIN' => 'NIP',
];

$converted = 0;
foreach ($tables as $table => $identityColumn) {
    // Table/column names are fixed literals above, never input.
    $stmt = $GLOBALS['connect']->prepare("SELECT {$identityColumn} AS identitas, password FROM {$table} WHERE password IS NOT NULL AND password NOT LIKE ?");
    $stmt->execute(['$2%']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hash = password_hash((string) $row['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $update = $GLOBALS['connect']->prepare("UPDATE {$table} SET password = ? WHERE {$identityColumn} = ?");
        $update->execute([$hash, $row['identitas']]);
        $converted++;
    }
}

echo "Hashed {$converted} plaintext password row(s).\n";
