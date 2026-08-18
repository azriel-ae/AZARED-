<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

final class Supplier
{
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR code LIKE :search OR phone LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare("SELECT COUNT(*) FROM suppliers WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT * FROM suppliers WHERE {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function all(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, code, name, phone FROM suppliers WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM suppliers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function history(int $id): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_purchases, COALESCE(SUM(total),0) AS total_spend,
                    MAX(created_at) AS last_purchase_at
             FROM purchases WHERE supplier_id = :id AND status = 'received'"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: ['total_purchases' => 0, 'total_spend' => 0, 'last_purchase_at' => null];
    }

    public static function generateCode(): string
    {
        $db = Database::connection();
        do {
            $code = 'SUPP-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $db->prepare('SELECT COUNT(*) FROM suppliers WHERE code = :code');
            $stmt->execute(['code' => $code]);
        } while (((int) $stmt->fetchColumn()) > 0);
        return $code;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO suppliers (code, name, legal_name, contact_person, phone, email, address, npwp, nik, tax_status, tax_address, status)
             VALUES (:code, :name, :legal_name, :contact_person, :phone, :email, :address, :npwp, :nik, :tax_status, :tax_address, :status)'
        );
        $stmt->execute([
            'code'           => $data['code'],
            'name'           => $data['name'],
            'legal_name'     => $data['legal_name'] !== '' ? $data['legal_name'] : null,
            'contact_person' => $data['contact_person'] !== '' ? $data['contact_person'] : null,
            'phone'          => $data['phone'] !== '' ? $data['phone'] : null,
            'email'          => $data['email'] !== '' ? $data['email'] : null,
            'address'        => $data['address'] !== '' ? $data['address'] : null,
            'npwp'           => $data['npwp'] !== '' ? $data['npwp'] : null,
            'nik'            => $data['nik'] !== '' ? $data['nik'] : null,
            'tax_status'     => $data['tax_status'] !== '' ? $data['tax_status'] : null,
            'tax_address'    => $data['tax_address'] !== '' ? $data['tax_address'] : null,
            'status'         => $data['status'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE suppliers SET name = :name, legal_name = :legal_name, contact_person = :contact_person, phone = :phone, email = :email,
                address = :address, npwp = :npwp, nik = :nik, tax_status = :tax_status, tax_address = :tax_address, status = :status WHERE id = :id'
        );
        $stmt->execute([
            'name'           => $data['name'],
            'legal_name'     => $data['legal_name'] !== '' ? $data['legal_name'] : null,
            'contact_person' => $data['contact_person'] !== '' ? $data['contact_person'] : null,
            'phone'          => $data['phone'] !== '' ? $data['phone'] : null,
            'email'          => $data['email'] !== '' ? $data['email'] : null,
            'address'        => $data['address'] !== '' ? $data['address'] : null,
            'npwp'           => $data['npwp'] !== '' ? $data['npwp'] : null,
            'nik'            => $data['nik'] !== '' ? $data['nik'] : null,
            'tax_status'     => $data['tax_status'] !== '' ? $data['tax_status'] : null,
            'tax_address'    => $data['tax_address'] !== '' ? $data['tax_address'] : null,
            'status'         => $data['status'],
            'id'             => $id,
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE suppliers SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
