<?php

class SqlRunner
{
    public function executeFile(PDO $pdo, $filePath, $transactional = true)
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('File SQL tidak ditemukan: ' . $filePath);
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException('Gagal membaca file SQL: ' . $filePath);
        }

        $statements = $this->splitSqlBatches($sql);
        if (empty($statements)) {
            return 0;
        }

        $startedTransaction = false;

        try {
            if ($transactional && !$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return count($statements);
    }

    private function splitSqlBatches($sql)
    {
        $lines = preg_split('/\r\n|\n|\r/', $sql);
        if ($lines === false) {
            return [];
        }

        $batches = [];
        $current = '';

        foreach ($lines as $line) {
            if (preg_match('/^\s*GO(?:\s+(\d+))?\s*$/i', $line, $matches)) {
                $this->flushBatch($batches, $current, isset($matches[1]) ? (int) $matches[1] : 1);
                $current = '';
                continue;
            }

            if (
                preg_match('/^\s*(CREATE|ALTER)\s+(PROCEDURE|VIEW|FUNCTION|TRIGGER)\b/i', $line) &&
                trim($current) !== ''
            ) {
                $this->flushBatch($batches, $current, 1);
                $current = '';
            }

            $current .= $line . PHP_EOL;
        }

        $this->flushBatch($batches, $current, 1);

        return $batches;
    }

    private function flushBatch(array &$batches, $batch, $times)
    {
        $statement = trim($batch);
        if ($statement === '') {
            return;
        }

        $repeat = $times > 0 ? $times : 1;
        for ($i = 0; $i < $repeat; $i++) {
            $batches[] = $statement;
        }
    }
}
