<?php

declare(strict_types=1);

use AlquilaPro\Auth\UserService;
use AlquilaPro\Candidates\CandidateHouseholdService;
use AlquilaPro\Documents\DocumentService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\InstallationLock;
use AlquilaPro\Foundation\MigrationRunner;
use AlquilaPro\Properties\PropertyService;
use Slim\Psr7\UploadedFile;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function g5Require(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function g5Write(string $path, string $bytes): void
{
    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
        throw new RuntimeException('No fue posible escribir una fixture sintética.');
    }
    @chmod($path, 0600);
}

function g5Upload(string $path, string $name, string $mime, string $bytes): UploadedFile
{
    g5Write($path, $bytes);
    return new UploadedFile($path, $name, $mime, strlen($bytes), UPLOAD_ERR_OK, false);
}

function g5Image(string $path, array $background, array $accent): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('GD no está disponible para generar fotos sintéticas.');
    }
    $image = imagecreatetruecolor(96, 96);
    if ($image === false) {
        throw new RuntimeException('No fue posible crear la foto sintética.');
    }
    $base = imagecolorallocate($image, $background[0], $background[1], $background[2]);
    $mark = imagecolorallocate($image, $accent[0], $accent[1], $accent[2]);
    imagefill($image, 0, 0, $base);
    imagefilledellipse($image, 48, 42, 40, 40, $mark);
    imagefilledrectangle($image, 17, 66, 79, 95, $mark);
    ob_start();
    $encoded = imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    if (!$encoded || !is_string($bytes) || $bytes === '') {
        throw new RuntimeException('No fue posible codificar la foto sintética.');
    }
    g5Write($path, $bytes);
    return $bytes;
}

function g5Admin(UserService $users, PDO $pdo, string $key, string $label, array $permissions): array
{
    $email = 'g5-' . $key . '@example.test';
    $password = bin2hex(random_bytes(18));
    $id = $users->create('G5 Demo ' . $label, $email, 'admin', $password, $permissions);
    $statement = $pdo->prepare('UPDATE users SET must_change_password = 0 WHERE id = :id');
    $statement->execute(['id' => $id]);
    return ['id' => $id, 'email' => $email, 'password' => $password];
}

function g5Property(PropertyService $properties, string $key): array
{
    $property = $properties->createProperty([
        'name' => 'Propiedad Demo G5 ' . $key,
        'address' => 'Calle Sintética 100',
        'city' => 'Bogotá',
        'sector' => 'QA',
        'administration_name' => '',
        'administration_contact' => '',
        'notes' => 'Fixture de prueba temporal.',
    ]);
    $unit = $properties->createUnit((int) $property['id'], [
        'type' => 'apartment',
        'code' => 'G5-' . strtoupper($key),
        'status' => 'available',
        'location_label' => 'Unidad sintética',
        'monthly_price' => '1000000',
        'description' => 'Unidad ficticia de Browser QA.',
        'requirements' => 'Datos sintéticos.',
        'private_notes' => '',
    ]);
    return ['property_id' => (int) $property['id'], 'unit_id' => (int) $unit['id']];
}

function g5Candidate(PDO $pdo, int $unitId, string $key, string $name, int $occupants, int $pets, ?string $answeredAt): int
{
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare(
        'INSERT INTO candidates '
        . '(unit_id, document_type, document_number, full_name, phone, email, occupants_count, pets_declared, '
        . 'pets_answered_at, occupants_notes, pets_notes, status, continuation_token_hash, '
        . 'continuation_token_expires_at, consent_at) '
        . 'VALUES (:unit_id, :document_type, :document_number, :full_name, :phone, :email, :occupants_count, '
        . ':pets_declared, :pets_answered_at, :occupants_notes, :pets_notes, :status, :token_hash, '
        . ':expires_at, :consent_at)',
    );
    $statement->execute([
        'unit_id' => $unitId,
        'document_type' => 'cc',
        'document_number' => 'G5-DEMO-' . strtoupper($key),
        'full_name' => $name,
        'phone' => '0000000000',
        'email' => 'candidate-' . strtolower($key) . '@example.test',
        'occupants_count' => $occupants,
        'pets_declared' => $pets,
        'pets_answered_at' => $answeredAt,
        'occupants_notes' => null,
        'pets_notes' => null,
        'status' => 'incomplete',
        'token_hash' => hash('sha256', $token),
        'expires_at' => '2099-01-01 00:00:00',
        'consent_at' => date('Y-m-d H:i:s'),
    ]);
    return (int) $pdo->lastInsertId();
}

try {
    g5Require(getenv('GITHUB_ACTIONS') === 'true' && getenv('CI') === 'true', 'Este fixture solo puede ejecutarse en GitHub Actions.');
    $runnerTemp = getenv('RUNNER_TEMP');
    $githubEnv = getenv('GITHUB_ENV');
    g5Require(is_string($runnerTemp) && $runnerTemp !== '' && is_string($githubEnv) && $githubEnv !== '', 'Faltan rutas efímeras del runner.');

    $db = [
        'host' => getenv('TEST_DB_HOST') ?: '',
        'port' => getenv('TEST_DB_PORT') ?: '',
        'database' => getenv('TEST_DB_NAME') ?: '',
        'username' => getenv('TEST_DB_USER') ?: '',
        'password' => getenv('TEST_DB_PASSWORD') ?: '',
    ];
    g5Require(
        $db['host'] === '127.0.0.1' && $db['port'] === '3306'
            && $db['database'] === 'alquila_test' && $db['username'] === 'root',
        'La configuración no corresponde a la MariaDB efímera esperada del CI.',
    );

    $repo = dirname(__DIR__, 2);
    $databaseConfigPath = $repo . '/config/database.php';
    $lockPath = $repo . '/storage/app/installed.lock';
    g5Require(!file_exists($databaseConfigPath) && !file_exists($lockPath), 'Ya existe una instalación en el checkout del runner.');

    $pdo = DatabaseConnector::connect(DatabaseConfig::fromArray($db));
    $identity = $pdo->query('SELECT DATABASE() AS database_name, @@port AS server_port')->fetch(PDO::FETCH_ASSOC);
    g5Require(
        is_array($identity) && $identity['database_name'] === 'alquila_test' && (int) $identity['server_port'] === 3306,
        'La conexión no apunta a la base desechable esperada.',
    );
    MigrationRunner::migrate($pdo, $repo . '/database/migrations');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if ((string) $table === 'schema_migrations') {
            continue;
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        g5Require($count === 0, 'La base de CI no quedó vacía después de la suite PHPUnit.');
    }

    $propertyDocumentDirectory = $runnerTemp . '/cand03-g5-property-documents';
    if (!is_dir($propertyDocumentDirectory) && !mkdir($propertyDocumentDirectory, 0700, true) && !is_dir($propertyDocumentDirectory)) {
        throw new RuntimeException('No fue posible crear el almacenamiento privado sintético.');
    }
    $workspaceParent = dirname($repo);
    $householdDirectory = $workspaceParent . '/alquilapro-private/' . basename($repo) . '/candidate-household';
    if (!is_dir($householdDirectory) && !mkdir($householdDirectory, 0700, true) && !is_dir($householdDirectory)) {
        throw new RuntimeException('No fue posible crear el almacenamiento privado de fotos sintéticas.');
    }

    $userService = new UserService($pdo);
    $allPermissions = ['candidates.view', 'candidates.manage', 'properties.view', 'documents.view'];
    $credentials = [
        'manager' => g5Admin($userService, $pdo, 'manager', 'Manager', $allPermissions),
        'viewer' => g5Admin($userService, $pdo, 'viewer', 'Viewer', ['candidates.view']),
        'viewer_property' => g5Admin($userService, $pdo, 'viewer-property', 'Property Viewer', ['candidates.view', 'properties.view']),
        'viewer_documents' => g5Admin($userService, $pdo, 'viewer-documents', 'Documents Viewer', ['candidates.view', 'documents.view']),
        'viewer_all' => g5Admin($userService, $pdo, 'viewer-all', 'Full Viewer', ['candidates.view', 'properties.view', 'documents.view']),
        'no_access' => g5Admin($userService, $pdo, 'no-access', 'No Access', []),
        'property_documents_only' => g5Admin($userService, $pdo, 'property-documents-only', 'Property Documents Only', ['properties.view', 'documents.view']),
    ];
    $tenant = $userService->createTenantIdentity('Inquilino Demo G5', 'g5-tenant@example.test', 'G5TENANT1026');
    $credentials['tenant'] = ['id' => $tenant['id'], 'email' => 'g5-tenant@example.test', 'password' => $tenant['temporary_password']];

    $propertyService = new PropertyService($pdo);
    $household = new CandidateHouseholdService($pdo, $householdDirectory);
    $mainProperty = g5Property($propertyService, 'main');
    $largeProperty = g5Property($propertyService, 'long');
    $emptyProperty = g5Property($propertyService, 'empty');
    $foreignProperty = g5Property($propertyService, 'foreign');
    $mainId = g5Candidate($pdo, $mainProperty['unit_id'], '001', 'Camilo Demo G5', 2, 1, date('Y-m-d H:i:s'));
    $largeId = g5Candidate($pdo, $largeProperty['unit_id'], '002', 'Lucía Demo G5', 4, 1, date('Y-m-d H:i:s'));
    $emptyId = g5Candidate($pdo, $emptyProperty['unit_id'], '003', 'Alex Demo G5', 1, 0, null);
    $foreignId = g5Candidate($pdo, $foreignProperty['unit_id'], '004', 'Candidato Ajeno G5', 1, 0, null);

    $mainOccupant = $household->saveRecord('occupant', $mainId, null, [
        'full_name' => 'María Demo G5', 'relationship' => 'Familiar', 'age_years' => '32',
        'occupation_or_activity' => 'Actividad sintética', 'notes' => 'Dato ficticio de prueba.',
    ], null, time());
    $longText = str_repeat('Observación ficticia para revisar el crecimiento del contenido y la lectura en pantallas estrechas. ', 10);
    for ($index = 1; $index <= 3; ++$index) {
        $household->saveRecord('occupant', $largeId, null, [
            'full_name' => 'Ocupante Demo G5 ' . $index,
            'relationship' => $index === 1 ? 'Familiar' : 'Acompañante',
            'age_years' => $index === 1 ? '8' : (string) (20 + $index),
            'occupation_or_activity' => $index === 1 ? 'Estudiante' : 'Actividad sintética',
            'notes' => $longText,
        ], null, time());
    }
    $imageOriginalPath = $runnerTemp . '/cand03-g5-photo-original.png';
    $imageReplacementPath = $runnerTemp . '/cand03-g5-photo-replacement.png';
    $imageAddedPath = $runnerTemp . '/cand03-g5-photo-added.png';
    $originalBytes = g5Image($imageOriginalPath, [211, 235, 226], [30, 106, 90]);
    $replacementBytes = g5Image($imageReplacementPath, [222, 231, 244], [57, 92, 154]);
    g5Image($imageAddedPath, [245, 228, 203], [166, 106, 22]);
    $mainPet = $household->saveRecord('pet', $mainId, null, [
        'name' => 'Max Demo G5', 'species' => 'Perro', 'breed' => 'Raza sintética',
        'size_category' => 'medium', 'age_years' => '3', 'vaccination_declaration' => 'declared_vaccinated',
        'notes' => 'Declaración sintética, no verificada.', 'photo_consent' => '1',
    ], g5Upload($runnerTemp . '/cand03-g5-photo-seed.png', 'synthetic-pet.png', 'image/png', $originalBytes), time());
    for ($index = 1; $index <= 3; ++$index) {
        $household->saveRecord('pet', $largeId, null, [
            'name' => 'Mascota Demo G5 ' . $index,
            'species' => $index === 1 ? 'Gato' : 'Perro',
            'breed' => '', 'size_category' => '', 'age_years' => '',
            'vaccination_declaration' => '', 'notes' => $longText,
        ], null, time());
    }
    $largeNotes = $pdo->prepare('UPDATE candidates SET occupants_notes = :occupants, pets_notes = :pets WHERE id = :id');
    $largeNotes->execute([
        'occupants' => $longText . '<script>window.__g5_injected = true</script>',
        'pets' => $longText,
        'id' => $largeId,
    ]);

    $documents = new DocumentService($pdo, $propertyDocumentDirectory);
    $documents->createContentVersion(
        $mainProperty['property_id'], null, 'manual_convivencia', 'Manual sintético G5 JSON',
        ['summary' => 'Contenido de convivencia sintético.', 'items' => ['Regla ficticia de QA.']],
        null, (int) $credentials['manager']['id'], time(),
    );
    $pdfBytes = file_get_contents($repo . '/tests/Fixtures/valid-conventional.pdf');
    if (!is_string($pdfBytes) || $pdfBytes === '') {
        throw new RuntimeException('No está disponible la fixture PDF válida del repositorio.');
    }
    $manualPdf = $documents->createFileVersion(
        $largeProperty['property_id'], null, 'manual_convivencia', 'Manual sintético G5 PDF',
        g5Upload($runnerTemp . '/cand03-g5-manual.pdf', 'manual-synthetic.pdf', 'application/pdf', $pdfBytes),
        null, (int) $credentials['manager']['id'], time(),
    );

    $configDir = dirname($databaseConfigPath);
    if (!is_dir($configDir)) {
        mkdir($configDir, 0700, true);
    }
    $config = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($db, true) . ";\n";
    g5Write($databaseConfigPath, $config);
    (new InstallationLock($lockPath))->write(['schema_version' => '1', 'installed_at' => date(DATE_ATOM)]);
    $fixture = [
        'credentials' => $credentials,
        'candidates' => [
            'main' => ['id' => $mainId, 'property_id' => $mainProperty['property_id'], 'occupant_id' => (int) $mainOccupant['id'], 'pet_id' => (int) $mainPet['id']],
            'long' => ['id' => $largeId, 'property_id' => $largeProperty['property_id']],
            'empty' => ['id' => $emptyId, 'property_id' => $emptyProperty['property_id']],
            'foreign' => ['id' => $foreignId, 'property_id' => $foreignProperty['property_id']],
        ],
        'manual_pdf_id' => (int) $manualPdf['id'],
        'uploads' => [
            'original_photo' => $imageOriginalPath,
            'replacement_photo' => $imageReplacementPath,
            'added_photo' => $imageAddedPath,
        ],
    ];
    $fixturePath = $runnerTemp . '/cand03-g5-fixture.json';
    g5Write($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    if (file_put_contents($githubEnv, 'ALQUILAPRO_DOCUMENT_DIRECTORY=' . $propertyDocumentDirectory . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('No fue posible exportar el directorio privado temporal al job.');
    }
    fwrite(STDOUT, "CAND03 G5 synthetic fixture prepared.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'CAND03 G5 fixture setup failed (' . $exception::class . '): ' . $exception->getMessage() . "\n");
    exit(1);
}
