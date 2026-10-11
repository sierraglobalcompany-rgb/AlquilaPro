<?php

declare(strict_types=1);

use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\Installer;
use AlquilaPro\Foundation\RuntimeRequirements;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;

return static function (array $settings): App {
    $lockPath = (string) ($settings['lock_path'] ?? dirname(__DIR__) . '/storage/app/installed.lock');
    $databaseConfigPath = (string) ($settings['database_config_path'] ?? dirname(__DIR__) . '/config/database.php');
    $migrationDirectory = (string) ($settings['migration_directory'] ?? dirname(__DIR__) . '/database/migrations');
    $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');
    $basePath = '/' . trim((string) ($settings['base_path'] ?? ''), '/');
    $basePath = $basePath === '/' ? '' : $basePath;

    $lock = new InstallationLock($lockPath);
    $installer = new Installer($lock, $migrationDirectory, $databaseConfigPath);
    $app = AppFactory::create();

    if ($basePath !== '') {
        $app->setBasePath($basePath);
    }

    $storageDirectory = dirname($lockPath);
    $storageWritable = is_dir($storageDirectory)
        ? is_writable($storageDirectory)
        : is_writable(dirname($storageDirectory));

    $runtime = RuntimeRequirements::evaluate(PHP_VERSION, get_loaded_extensions(), $storageWritable);
    $installPath = $basePath . '/install';
    $homePath = $basePath . '/';
    $view = dirname(__DIR__) . '/resources/views/install.php';

    $renderInstaller = static function (?string $error = null) use ($runtime, $installPath, $view): string {
        $action = $installPath;
        ob_start();
        require $view;
        return (string) ob_get_clean();
    };

    $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $installPath): ResponseInterface {
        if (!$lock->isInstalled()) {
            return $response->withHeader('Location', $installPath)->withStatus(302);
        }

        $response->getBody()->write('Alquila Pro');
        return $response;
    });

    $app->get('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $renderInstaller): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $response->getBody()->write($renderInstaller());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    $app->post('/install', static function (ServerRequestInterface $request, ResponseInterface $response) use ($lock, $installer, $baseUrl, $homePath, $renderInstaller): ResponseInterface {
        if ($lock->isInstalled()) {
            return $response->withStatus(404);
        }

        $form = (array) ($request->getParsedBody() ?? []);

        try {
            $installer->install([
                'database' => [
                    'host' => $form['db_host'] ?? '',
                    'port' => $form['db_port'] ?? '3306',
                    'database' => $form['db_name'] ?? '',
                    'username' => $form['db_user'] ?? '',
                    'password' => $form['db_password'] ?? '',
                ],
                'app_name' => $form['app_name'] ?? 'Alquila Pro',
                'owner_name' => $form['owner_name'] ?? '',
                'owner_email' => $form['owner_email'] ?? '',
                'owner_password' => $form['owner_password'] ?? '',
                'base_url' => $baseUrl,
            ]);
        } catch (RuntimeException $exception) {
            $response->getBody()->write($renderInstaller($exception->getMessage()));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        } catch (Throwable) {
            $response->getBody()->write($renderInstaller('No fue posible completar la instalación.'));
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withStatus(422);
        }

        return $response->withHeader('Location', $homePath)->withStatus(303);
    });

    $app->get('/health', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $payload = json_encode(['status' => 'ok', 'version' => '0.1.0-f1'], JSON_THROW_ON_ERROR);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    return $app;
};
