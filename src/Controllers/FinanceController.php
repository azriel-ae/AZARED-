<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\CashAccount;
use App\Models\Finance;
use App\Models\Store;

final class FinanceController
{
    public static function dashboard(): void
    {
        $snapshot = Finance::dashboardSnapshot();
        $accounts = CashAccount::allWithCurrentBalance();
        require dirname(__DIR__, 2) . '/views/finance/dashboard.php';
    }

    private static function periodFilters(): array
    {
        $period = (string) ($_GET['period'] ?? 'month');
        if (!in_array($period, ['day', 'week', 'month', 'year', 'custom'], true)) {
            $period = 'month';
        }
        $from = (string) ($_GET['date_from'] ?? '');
        $to = (string) ($_GET['date_to'] ?? '');
        $storeId = (int) ($_GET['store_id'] ?? 0) ?: null;

        return [$period, $from, $to, $storeId];
    }

    public static function profitLoss(): void
    {
        [$period, $from, $to, $storeId] = self::periodFilters();
        $range = Finance::resolveRange($period, $from ?: null, $to ?: null);
        $report = Finance::profitLoss($range['start'], $range['end'], $storeId);
        $stores = Store::all();

        require dirname(__DIR__, 2) . '/views/finance/profit_loss.php';
    }

    public static function cashFlow(): void
    {
        [$period, $from, $to, $storeId] = self::periodFilters();
        $range = Finance::resolveRange($period, $from ?: null, $to ?: null);
        $report = Finance::cashFlow($range['start'], $range['end'], $storeId);
        $accounts = CashAccount::allWithCurrentBalance();
        $stores = Store::all();

        require dirname(__DIR__, 2) . '/views/finance/cash_flow.php';
    }
}
