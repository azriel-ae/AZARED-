<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;

final class AuditLogController
{
    private const PER_PAGE = 30;

    public static function index(): void
    {
        $filters = [
            'user_id'     => (int) ($_GET['user_id'] ?? 0),
            'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
            'action'      => trim((string) ($_GET['action'] ?? '')),
            'date_from'   => (string) ($_GET['date_from'] ?? ''),
            'date_to'     => (string) ($_GET['date_to'] ?? ''),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = AuditLog::paginate($filters, $page, self::PER_PAGE);
        $logs = $result['rows'];
        $total = $result['total'];
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));

        $users = User::simpleList();
        $entityTypes = AuditLog::entityTypes();

        require dirname(__DIR__, 2) . '/views/audit/index.php';
    }
}
