<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * Runs SQL migrations from database/migrations/*.php.
 * Each file returns ['up' => list<string>, 'down' => list<string>].
 * MySQL DDL auto-commits, so every statement is applied one by one and the
 * migration is recorded only after all of its statements succeed.
 */
final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $path)
    {
    }

    public function ensureRepository(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> migration names (file names without .php), sorted */
    public function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return array_map(static fn (string $f): string => basename($f, '.php'), $files);
    }

    /** @return array<string, int> migration => batch */
    public function ran(): array
    {
        $this->ensureRepository();
        $rows = $this->pdo->query('SELECT migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('intval', $rows);
    }

    /**
     * @param callable(string): void $output
     * @return int number of applied migrations
     */
    public function migrate(callable $output): int
    {
        $ran = $this->ran();
        $pending = array_values(array_diff($this->files(), array_keys($ran)));
        if ($pending === []) {
            $output('Nothing to migrate.');

            return 0;
        }

        $batch = ($ran === [] ? 0 : max($ran)) + 1;
        foreach ($pending as $name) {
            $output("Migrating: {$name}");
            foreach ($this->load($name)['up'] as $sql) {
                $this->pdo->exec($sql);
            }
            $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)')->execute([$name, $batch]);
            $output("Migrated:  {$name}");
        }

        return count($pending);
    }

    /** @param callable(string): void $output */
    public function rollback(callable $output): int
    {
        $ran = $this->ran();
        if ($ran === []) {
            $output('Nothing to rollback.');

            return 0;
        }

        $lastBatch = max($ran);
        $names = array_reverse(array_keys(array_filter($ran, static fn (int $b): bool => $b === $lastBatch)));
        foreach ($names as $name) {
            $output("Rolling back: {$name}");
            foreach ($this->load($name)['down'] as $sql) {
                $this->pdo->exec($sql);
            }
            $this->pdo->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$name]);
        }

        return count($names);
    }

    /** Drop every table in the current database (used by migrate:fresh). */
    public function dropAllTables(): void
    {
        $tables = $this->pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->pdo->exec('DROP TABLE ' . Database::identifier((string) $table));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array{up: list<string>, down: list<string>} */
    private function load(string $name): array
    {
        $file = $this->path . '/' . $name . '.php';
        $migration = require $file;
        if (!is_array($migration) || !isset($migration['up'], $migration['down'])) {
            throw new RuntimeException("Migration [{$name}] must return ['up' => [...], 'down' => [...]].");
        }

        return $migration;
    }
}
