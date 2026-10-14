<?php

declare(strict_types=1);

namespace AlquilaPro\Tests\Support;

use PDO;

trait MaintenanceRequestFixture
{
    /** @return array{request_id:int,lease_id:int,actor_id:int,tenant_id:int,actor_email:string,tenant_email:string} */
    protected function createMaintenanceRequest(PDO $pdo): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actorEmail = 'admin-' . $suffix . '@example.test';
        $tenantEmail = 'tenant-' . $suffix . '@example.test';
        $actorId = $this->insertFixtureUser($pdo, 'admin', $suffix, $actorEmail);
        $tenantId = $this->insertFixtureUser($pdo, 'tenant', $suffix, $tenantEmail);
        $property = $pdo->prepare(
            'INSERT INTO properties (name, address, city) VALUES (:name, \'Calle 1\', \'Bogotá\')',
        );
        $property->execute(['name' => 'Property ' . $suffix]);
        $unit = $pdo->prepare(
            'INSERT INTO units (property_id, public_key, type, code, status) '
            . 'VALUES (:property, :key, \'apartment\', :code, \'occupied\')',
        );
        $unit->execute([
            'property' => (int) $pdo->lastInsertId(),
            'key' => bin2hex(random_bytes(12)),
            'code' => 'APT-' . substr($suffix, 0, 5),
        ]);
        $unitId = (int) $pdo->lastInsertId();
        $candidate = $pdo->prepare(
            'INSERT INTO candidates (unit_id, document_type, document_number, full_name, phone, email, status, '
            . 'continuation_token_hash, continuation_token_expires_at, consent_at) '
            . 'VALUES (:unit, \'cc\', :number, :name, \'3001234567\', :email, \'approved\', :token, '
            . '\'2027-01-01 00:00:00\', \'2026-10-13 10:00:00\')',
        );
        $candidate->execute([
            'unit' => $unitId,
            'number' => 'DOC-' . $suffix,
            'name' => 'Candidate ' . $suffix,
            'email' => 'candidate-' . $suffix . '@example.test',
            'token' => hash('sha256', $suffix),
        ]);
        $candidateId = (int) $pdo->lastInsertId();
        $template = $pdo->prepare(
            'INSERT INTO contract_templates (name, document_type) VALUES (:name, \'urban_housing\')',
        );
        $template->execute(['name' => 'Template ' . $suffix]);
        $version = $pdo->prepare(
            'INSERT INTO contract_template_versions (template_id, version_number, body_template, variables_json) '
            . 'VALUES (:template, 1, \'Contrato\', \'[]\')',
        );
        $version->execute(['template' => (int) $pdo->lastInsertId()]);
        $versionId = (int) $pdo->lastInsertId();
        $contract = $pdo->prepare(
            'INSERT INTO contracts (candidate_id, unit_id, template_version_id, document_type, status, monthly_rent, '
            . 'administration_fee, start_date, end_date, duration_text) '
            . 'VALUES (:candidate, :unit, :version, \'urban_housing\', \'finalized\', 1500000, 0, '
            . '\'2026-10-01\', \'2027-09-30\', \'Doce meses\')',
        );
        $contract->execute([
            'candidate' => $candidateId,
            'unit' => $unitId,
            'version' => $versionId,
        ]);
        $lease = $pdo->prepare(
            'INSERT INTO leases (contract_id, user_id, activated_by_user_id, activated_at) '
            . 'VALUES (:contract, :tenant, :actor, \'2026-10-01 12:00:00\')',
        );
        $lease->execute(['contract' => (int) $pdo->lastInsertId(), 'tenant' => $tenantId, 'actor' => $actorId]);
        $leaseId = (int) $pdo->lastInsertId();
        $request = $pdo->prepare(
            'INSERT INTO maintenance_requests (lease_id, category, priority, description, photos_json, created_by_user_id) '
            . 'VALUES (:lease, \'Plomería\', \'medium\', \'Fuga\', \'[]\', :actor)',
        );
        $request->execute(['lease' => $leaseId, 'actor' => $actorId]);
        return [
            'request_id' => (int) $pdo->lastInsertId(),
            'lease_id' => $leaseId,
            'actor_id' => $actorId,
            'tenant_id' => $tenantId,
            'actor_email' => $actorEmail,
            'tenant_email' => $tenantEmail,
        ];
    }

    private function insertFixtureUser(PDO $pdo, string $role, string $suffix, string $email): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, is_active) '
            . 'VALUES (:role, :name, :email, :hash, 1)',
        );
        $statement->execute([
            'role' => $role,
            'name' => ucfirst($role) . ' ' . $suffix,
            'email' => $email,
            'hash' => password_hash('TemporaryPass123', PASSWORD_DEFAULT),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
