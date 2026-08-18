<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;

final class DashboardController
{
    public static function index(): void
    {
        $db = Database::connection();

        // Guard every query: this dashboard must still render cleanly on a
        // fresh Phase-1-only database where these tables don't exist yet.
        $hasPosTables = (bool) $db->query("SHOW TABLES LIKE 'sales'")->fetchColumn();
        $hasSettingsTable = (bool) $db->query("SHOW TABLES LIKE 'app_settings'")->fetchColumn();

        $stats = [
            'sales_today_total'     => 0.0,
            'sales_today_count'     => 0,
            'sales_month_total'     => 0.0,
            'sales_month_count'     => 0,
            'purchases_today_total' => 0.0,
            'low_stock_count'       => 0,
            'customers_total'       => 0,
            'gross_profit_today'    => 0.0,
        ];
        $bestSellers = [];
        $lowStockList = [];
        $paymentBreakdown = [];

        if ($hasPosTables) {
            try {
                $today = Sale::todayStats();
                $stats['sales_today_total'] = (float) $today['total'];
                $stats['sales_today_count'] = (int) $today['cnt'];

                $month = Sale::monthStats();
                $stats['sales_month_total'] = (float) $month['total'];
                $stats['sales_month_count'] = (int) $month['cnt'];

                $stats['purchases_today_total'] = Purchase::todayTotal();
                $stats['low_stock_count'] = Product::lowStockCount();
                $stats['customers_total'] = Customer::count();
                $stats['gross_profit_today'] = Sale::grossProfitTodayEstimate();

                $bestSellers = Product::bestSellers(date('Y-m-01'), 5);
                $lowStockList = Product::lowStockList(8);
                $paymentBreakdown = Sale::paymentMethodBreakdownToday();
            } catch (\Throwable $e) {
                // Keep zeroed defaults if any Phase 2 table is still mid-migration.
            }
        }

        $lowStockAlertNote = $hasSettingsTable ? AppSetting::get('low_stock_alert_note', '') : '';

        require dirname(__DIR__, 2) . '/views/dashboard/index.php';
    }
}
