<?php

declare(strict_types=1);

namespace AlquilaPro\Foundation;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseConnector
{
    public static function connect(DatabaseConfig $config): PDO
    {
        try {
            return new PDO(
                $config->dsn(),
                $config->username,
                $config->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException) {
            throw new RuntimeException('No fue posible conectar con la base de datos.');
        }
    }
}
