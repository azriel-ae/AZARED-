<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class AuditLog
{
    public static function record(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip)'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values'  => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT al.*, u.full_name AS actor_name FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Filtered, paginated view for the /audit admin page.
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'al.action LIKE :action';
            $params['action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(al.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(al.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT al.*, u.full_name AS actor_name FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE {$whereSql}
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Distinct entity_type values seen so far, for the filter dropdown.
     */
    public static function entityTypes(): array
    {
        $stmt = Database::connection()->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type ASC');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
