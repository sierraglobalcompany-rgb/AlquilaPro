<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

use InvalidArgumentException;

final readonly class DatabaseConfig
{
    private function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $host = trim((string) ($input['host'] ?? ''));
        $database = trim((string) ($input['database'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $port = filter_var(
            $input['port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]],
        );

        if ($host === '') {
            throw new InvalidArgumentException('El host de la base de datos es obligatorio.');
        }

        if ($port === false) {
            throw new InvalidArgumentException('El puerto de la base de datos no es válido.');
        }

        if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new InvalidArgumentException('El nombre de la base de datos no es válido.');
        }

        if ($username === '') {
            throw new InvalidArgumentException('El usuario de la base de datos es obligatorio.');
        }

        return new self($host, $port, $database, $username, $password);
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database,
        );
    }
}
