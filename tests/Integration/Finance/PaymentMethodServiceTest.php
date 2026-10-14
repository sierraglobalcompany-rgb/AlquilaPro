<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Integration\Finance;

use AlquilaPro\Finance\PaymentMethodService;
use AlquilaPro\Foundation\DatabaseConfig;
use AlquilaPro\Foundation\DatabaseConnector;
use AlquilaPro\Foundation\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentMethodServiceTest extends TestCase
{
    private PDO $pdo;
    private string $qrDirectory;

    protected function setUp(): void
    {
        $this->pdo = DatabaseConnector::connect(DatabaseConfig::fromArray([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: 'alquila_test',
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'root',
        ]));

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $safeTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $safeTable));
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        MigrationRunner::migrate($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
        $this->qrDirectory = sys_get_temp_dir() . '/alquilapro-payment-methods-' . bin2hex(random_bytes(8));
    }

    public function testCreatesEachSupportedNonQrTypeAndClearsIrrelevantFields(): void
    {
        $cases = [
            [
                'type' => 'bank_account',
                'overrides' => ['breb_key_value' => 'stale-key', 'wallet_identifier' => 'stale-wallet'],
                'relevant' => ['recipient_name', 'institution_name', 'account_type', 'account_number'],
            ],
            [
                'type' => 'breb_key',
                'overrides' => ['account_number' => 'stale-account', 'wallet_identifier' => 'stale-wallet'],
                'relevant' => ['breb_key_type', 'breb_key_value'],
            ],
            [
                'type' => 'wallet',
                'overrides' => ['account_number' => 'stale-account', 'breb_key_value' => 'stale-key'],
                'relevant' => ['institution_name', 'wallet_identifier'],
            ],
            [
                'type' => 'manual',
                'overrides' => [
                    'recipient_name' => 'stale-recipient',
                    'institution_name' => 'stale-institution',
                    'account_number' => 'stale-account',
                    'breb_key_value' => 'stale-key',
                    'wallet_identifier' => 'stale-wallet',
                ],
                'relevant' => ['instructions'],
            ],
        ];

        foreach ($cases as $case) {
            $method = $this->service()->create(
                $this->payload($case['type'], $case['overrides']),
                null,
            );

            self::assertIsInt($method['id']);
            self::assertSame($case['type'], $method['type']);
            foreach ($case['relevant'] as $field) {
                self::assertNotNull($method[$field], $case['type'] . ' must retain ' . $field);
            }
            foreach (['qr_stored_name', 'qr_mime_type', 'qr_sha256', 'qr_size_bytes'] as $field) {
                self::assertNull($method[$field], $case['type'] . ' cannot contain QR metadata before Task 3');
            }
        }

        self::assertFalse(method_exists(PaymentMethodService::class, 'delete'));
    }

    public function testRejectsUnsupportedTypesAndMissingRequiredValues(): void
    {
        foreach (['pse', 'credit_card', 'cash', 'paypal', 'stripe', 'wompi', 'mercadopago', 'unknown'] as $type) {
            $this->assertRejected(fn () => $this->service()->create($this->payload($type), null));
        }

        $required = [
            'bank_account' => ['label', 'recipient_name', 'institution_name', 'account_type', 'account_number'],
            'breb_key' => ['label', 'breb_key_type', 'breb_key_value'],
            'wallet' => ['label', 'institution_name', 'wallet_identifier'],
            'manual' => ['label', 'instructions'],
        ];
        foreach ($required as $type => $fields) {
            foreach ($fields as $field) {
                $input = $this->payload($type, [$field => '  ']);
                $this->assertRejected(fn () => $this->service()->create($input, null));
            }
        }

        $this->assertRejected(fn () => $this->service()->create($this->payload('breb_qr'), null));
        self::assertSame(0, $this->methodCount());
    }

    public function testTrimsVisibleTextAndEnforcesColumnLengthLimits(): void
    {
        $method = $this->service()->create($this->payload('manual', [
            'label' => '  Transferencia OR 1=1  ',
            'instructions' => '  Use la referencia del contrato.  ',
        ]), null);
        self::assertSame('Transferencia OR 1=1', $method['label']);
        self::assertSame('Use la referencia del contrato.', $method['instructions']);

        $tooLong = [
            ['manual', 'label', 121],
            ['bank_account', 'recipient_name', 191],
            ['bank_account', 'institution_name', 191],
            ['bank_account', 'account_type', 41],
            ['bank_account', 'account_number', 121],
            ['wallet', 'wallet_identifier', 191],
            ['manual', 'instructions', 1001],
        ];
        foreach ($tooLong as [$type, $field, $length]) {
            $input = $this->payload($type, [$field => str_repeat('x', $length)]);
            $this->assertRejected(fn () => $this->service()->create($input, null));
        }
    }

    public function testNormalizesGlobalAndPropertyScopeAndRejectsInvalidProperties(): void
    {
        $propertyId = $this->createProperty('Property one');
        $global = $this->service()->create($this->payload('manual', ['property_id' => '']), null);
        self::assertNull($global['property_id']);

        $scoped = $this->service()->create($this->payload('manual', ['property_id' => (string) $propertyId]), null);
        self::assertSame($propertyId, $scoped['property_id']);

        foreach (['0', '-1', '1.5', '999999'] as $invalidPropertyId) {
            $this->assertRejected(fn () => $this->service()->create(
                $this->payload('manual', ['property_id' => $invalidPropertyId]),
                null,
            ));
        }
    }

    public function testNormalizesSortAndActiveStateAndRejectsInvalidValues(): void
    {
        $inactive = $this->service()->create($this->payload('manual', [
            'sort_order' => '3',
            'is_active' => '0',
        ]), null);
        self::assertSame(3, $inactive['sort_order']);
        self::assertSame(0, $inactive['is_active']);

        $default = $this->service()->create($this->payload('manual', [
            'sort_order' => 1,
            'is_active' => true,
        ]), null);
        self::assertSame(1, $default['sort_order']);
        self::assertSame(1, $default['is_active']);

        foreach ([0, '-1', '1.5', '4294967296', 'not-a-number'] as $invalidOrder) {
            $this->assertRejected(fn () => $this->service()->create(
                $this->payload('manual', ['sort_order' => $invalidOrder]),
                null,
            ));
        }
        foreach (['2', 'yes', 'active'] as $invalidActive) {
            $this->assertRejected(fn () => $this->service()->create(
                $this->payload('manual', ['is_active' => $invalidActive]),
                null,
            ));
        }
    }

    public function testValidatesBreBKeyTypesAndCurrentRoutingShapes(): void
    {
        $validKeys = [
            'identity_document' => 'AB123456789',
            'mobile' => '3001234567',
            'email' => 'inquilino@example.com',
            'alphanumeric' => '@casa123',
            'merchant_code' => '0012345678',
        ];
        foreach ($validKeys as $keyType => $keyValue) {
            $method = $this->service()->create($this->payload('breb_key', [
                'breb_key_type' => $keyType,
                'breb_key_value' => $keyValue,
            ]), null);
            self::assertSame($keyType, $method['breb_key_type']);
            self::assertSame($keyValue, $method['breb_key_value']);
        }

        $invalidKeys = [
            ['unknown', 'ABC123'],
            ['identity_document', 'AB-123'],
            ['identity_document', '1234567890123456789'],
            ['mobile', '+573001234567'],
            ['mobile', '31012345'],
            ['email', 'not-an-email'],
            ['email', str_repeat('a', 31) . '@example.com'],
            ['alphanumeric', 'casa123'],
            ['alphanumeric', '@abc1'],
            ['alphanumeric', '@casa-con-espacios'],
            ['merchant_code', '1234567890'],
            ['merchant_code', '00123456789'],
        ];
        foreach ($invalidKeys as [$keyType, $keyValue]) {
            $this->assertRejected(fn () => $this->service()->create($this->payload('breb_key', [
                'breb_key_type' => $keyType,
                'breb_key_value' => $keyValue,
            ]), null));
        }
    }

    public function testUpdatingTypeClearsStaleFinancialFields(): void
    {
        $brebKey = $this->service()->create($this->payload('breb_key', [
            'recipient_name' => 'Titular',
            'institution_name' => 'Banco',
        ]), null);
        $wallet = $this->service()->update((int) $brebKey['id'], $this->payload('wallet', [
            'recipient_name' => 'Titular',
        ]), null, false);

        foreach (['account_type', 'account_number', 'breb_key_type', 'breb_key_value', 'qr_stored_name', 'qr_mime_type', 'qr_sha256', 'qr_size_bytes'] as $field) {
            self::assertNull($wallet[$field], $field . ' must be cleared when changing to wallet');
        }
        self::assertSame('wallet', $wallet['type']);

        $manual = $this->service()->update((int) $wallet['id'], $this->payload('manual'), null, false);
        foreach ([
            'recipient_name',
            'institution_name',
            'account_type',
            'account_number',
            'breb_key_type',
            'breb_key_value',
            'wallet_identifier',
            'qr_stored_name',
            'qr_mime_type',
            'qr_sha256',
            'qr_size_bytes',
        ] as $field) {
            self::assertNull($manual[$field], $field . ' must be cleared when changing to manual');
        }
    }

    public function testListsApplicableActiveMethodsInStableOrderAndKeepsInactiveForStaff(): void
    {
        $propertyOne = $this->createProperty('Property one');
        $propertyTwo = $this->createProperty('Property two');
        $globalLater = $this->service()->create($this->payload('manual', [
            'label' => 'Global later', 'sort_order' => 5,
        ]), null);
        $matchingFirst = $this->service()->create($this->payload('manual', [
            'label' => 'Matching first', 'property_id' => (string) $propertyOne, 'sort_order' => 3,
        ]), null);
        $foreign = $this->service()->create($this->payload('manual', [
            'label' => 'Foreign', 'property_id' => (string) $propertyTwo, 'sort_order' => 1,
        ]), null);
        $inactiveGlobal = $this->service()->create($this->payload('manual', [
            'label' => 'Inactive global', 'sort_order' => 2, 'is_active' => 0,
        ]), null);
        $matchingSecond = $this->service()->create($this->payload('manual', [
            'label' => 'Matching second', 'property_id' => (string) $propertyOne, 'sort_order' => 3,
        ]), null);

        $applicable = $this->service()->listForProperty($propertyOne);
        self::assertSame([
            $matchingFirst['id'],
            $matchingSecond['id'],
            $globalLater['id'],
        ], array_column($applicable, 'id'));
        self::assertSame([], $this->service()->listForProperty(0));

        $all = $this->service()->listAll();
        self::assertCount(5, $all);
        self::assertContains($inactiveGlobal['id'], array_column($all, 'id'));
        self::assertContains($foreign['id'], array_column($all, 'id'));
        self::assertSame($matchingFirst, $this->service()->find((int) $matchingFirst['id']));
        self::assertNull($this->service()->find(999999));
    }

    private function service(): PaymentMethodService
    {
        return new PaymentMethodService($this->pdo, $this->qrDirectory);
    }

    private function payload(string $type, array $overrides = []): array
    {
        $fields = [
            'bank_account' => [
                'recipient_name' => 'Titular de cuenta',
                'institution_name' => 'Banco de Colombia',
                'account_type' => 'savings',
                'account_number' => '0123456789',
            ],
            'breb_key' => [
                'breb_key_type' => 'mobile',
                'breb_key_value' => '3001234567',
            ],
            'breb_qr' => [],
            'wallet' => [
                'institution_name' => 'Nequi',
                'wallet_identifier' => '3001234567',
            ],
            'manual' => ['instructions' => 'Use la referencia del contrato.'],
        ];

        return array_replace([
            'property_id' => null,
            'type' => $type,
            'label' => 'Medio de pago',
            'recipient_name' => null,
            'institution_name' => null,
            'account_type' => null,
            'account_number' => null,
            'breb_key_type' => null,
            'breb_key_value' => null,
            'wallet_identifier' => null,
            'instructions' => null,
            'sort_order' => 1,
            'is_active' => 1,
        ], $fields[$type] ?? [], $overrides);
    }

    private function createProperty(string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO properties (name, address, city) VALUES (:name, :address, :city)',
        );
        $statement->execute([
            'name' => $name,
            'address' => 'Calle 1',
            'city' => 'Bogotá',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function methodCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            self::assertSame('No fue posible guardar el medio de pago.', $exception->getMessage());

            return;
        }

        self::fail('Invalid payment method input must be rejected.');
    }
}
