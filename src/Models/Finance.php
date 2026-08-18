<?php

declare(strict_types=1);

namespace App\Models;

/**
 * AZARED - finance/report aggregation. This is a thin orchestration layer
 * over Sale/Purchase/Expense/CashAccount - it does not touch the database
 * directly so the authoritative queries stay in one place per domain.
 */
final class Finance
{
    /**
     * Resolves a period filter into a half-open [start, end) datetime
     * range. $period one of: day, week, month, year, custom.
     * For 'custom', $from/$to are inclusive calendar dates (Y-m-d).
     */
    public static function resolveRange(string $period, ?string $from = null, ?string $to = null): array
    {
        $today = new \DateTimeImmutable('today');

        switch ($period) {
            case 'week':
                $start = $today->modify('monday this week');
                $end = $start->modify('+7 days');
                $label = 'Minggu Ini';
                break;
            case 'month':
                $start = $today->modify('first day of this month');
                $end = $start->modify('+1 month');
                $label = 'Bulan Ini';
                break;
            case 'year':
                $start = $today->modify('first day of january this year');
                $end = $start->modify('+1 year');
                $label = 'Tahun Ini';
                break;
            case 'custom':
                $start = $from ? new \DateTimeImmutable($from) : $today;
                $end = ($to ? new \DateTimeImmutable($to) : $today)->modify('+1 day');
                $label = 'Rentang Kustom';
                break;
            case 'day':
            default:
                $start = $today;
                $end = $start->modify('+1 day');
                $label = 'Hari Ini';
                break;
        }

        return [
            'start'      => $start->format('Y-m-d H:i:s'),
            'end'        => $end->format('Y-m-d H:i:s'),
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->modify('-1 day')->format('Y-m-d'),
            'label'      => $label,
        ];
    }

    /**
     * Laba Rugi (Profit & Loss) for the given range.
     * Pendapatan (Penjualan, Retur Penjualan, Diskon) - HPP = Laba Kotor
     * Laba Kotor - Biaya Operasional = Laba Bersih
     */
    public static function profitLoss(string $start, string $end, ?int $storeId = null): array
    {
        $revenue = Sale::revenueBetween($start, $end, $storeId);
        $returns = Sale::returnsBetween($start, $end, $storeId);
        $hpp = Sale::hppBetween($start, $end, $storeId);
        $expenseTotal = Expense::totalBetween($start, $end, $storeId);
        $expenseBreakdown = Expense::categoryBreakdownBetween($start, $end, $storeId);

        $netSales = (float) $revenue['net_sales'];       // gross sales net of item-level discounts, excl. tax
        $discount = (float) $revenue['discount'];        // transaction-level discount
        $pendapatanBersih = $netSales - $returns - $discount;
        $labaKotor = $pendapatanBersih - $hpp;
        $labaBersih = $labaKotor - $expenseTotal;

        return [
            'gross_sales'       => (float) $revenue['gross_sales'],
            'sales_returns'     => $returns,
            'discount'          => $discount,
            'net_revenue'       => $pendapatanBersih,
            'hpp'               => $hpp,
            'gross_profit'      => $labaKotor,
            'gross_margin_pct'  => $pendapatanBersih > 0 ? round($labaKotor / $pendapatanBersih * 100, 2) : 0.0,
            'expense_total'     => $expenseTotal,
            'expense_breakdown' => $expenseBreakdown,
            'net_profit'        => $labaBersih,
            'net_margin_pct'    => $pendapatanBersih > 0 ? round($labaBersih / $pendapatanBersih * 100, 2) : 0.0,
            'transaction_count' => (int) $revenue['cnt'],
        ];
    }

    /**
     * Cash Flow for the given range: opening balance (sum of all
     * accounts' balance right before $start) + cash in/out breakdown +
     * closing balance (opening + in - out).
     */
    public static function cashFlow(string $start, string $end, ?int $storeId = null): array
    {
        $accounts = CashAccount::all(true);
        $openingTotal = 0.0;
        foreach ($accounts as $acc) {
            $openingTotal += CashAccount::balanceAsOf($acc, $start);
        }

        $salesIn = Sale::revenueBetween($start, $end, $storeId)['grand_total'] ?? 0;
        $salesIn = (float) $salesIn;
        // Use actual payments received (not invoiced total) for accuracy on partial/split payments.
        $salesPaymentTotal = array_sum(array_column(Sale::paymentMethodBreakdownBetween($start, $end, $storeId), 'total'));
        $purchaseReturnIn = PurchaseReturn::totalBetween($start, $end);

        $cashInOther = 0.0;
        $cashOutOther = 0.0;
        foreach (CashAccount::all(true) as $acc) {
            $rows = \App\Database::connection()->prepare(
                "SELECT direction, COALESCE(SUM(amount),0) AS total FROM cash_other_entries
                 WHERE account_id = :account_id AND entry_date >= :start AND entry_date < :end GROUP BY direction"
            );
            $rows->execute(['account_id' => $acc['id'], 'start' => $start, 'end' => $end]);
            foreach ($rows->fetchAll() as $r) {
                if ($r['direction'] === 'in') { $cashInOther += (float) $r['total']; } else { $cashOutOther += (float) $r['total']; }
            }
        }

        $purchaseOut = array_sum(array_column(Purchase::paymentMethodBreakdownBetween($start, $end, $storeId), 'total'));
        $expenseOut = Expense::totalBetween($start, $end, $storeId);
        $salesReturnOut = Sale::returnsBetween($start, $end, $storeId);

        $totalIn = $salesPaymentTotal + $purchaseReturnIn + $cashInOther;
        $totalOut = $purchaseOut + $expenseOut + $salesReturnOut + $cashOutOther;
        $closing = $openingTotal + $totalIn - $totalOut;

        return [
            'opening_balance' => round($openingTotal, 2),
            'cash_in' => [
                'sales'            => $salesPaymentTotal,
                'purchase_returns' => $purchaseReturnIn,
                'other'            => $cashInOther,
                'total'            => round($totalIn, 2),
            ],
            'cash_out' => [
                'purchases'    => $purchaseOut,
                'expenses'     => $expenseOut,
                'sales_returns' => $salesReturnOut,
                'other'        => $cashOutOther,
                'total'        => round($totalOut, 2),
            ],
            'closing_balance' => round($closing, 2),
        ];
    }

    /**
     * Snapshot used by the /finance dashboard: today/week/month/year
     * revenue, purchases, HPP, gross/net profit, expenses, cash position.
     */
    public static function dashboardSnapshot(): array
    {
        $day = self::resolveRange('day');
        $week = self::resolveRange('week');
        $month = self::resolveRange('month');
        $year = self::resolveRange('year');

        $todayPl = self::profitLoss($day['start'], $day['end']);
        $monthPl = self::profitLoss($month['start'], $month['end']);
        $todayCf = self::cashFlow($day['start'], $day['end']);

        return [
            'omzet_today' => $todayPl['net_revenue'],
            'omzet_week'  => Sale::revenueBetween($week['start'], $week['end'])['net_sales'] ?? 0,
            'omzet_month' => $monthPl['net_revenue'],
            'omzet_year'  => Sale::revenueBetween($year['start'], $year['end'])['net_sales'] ?? 0,
            'purchases_month' => Purchase::totalBetween($month['start'], $month['end']),
            'hpp_month'        => $monthPl['hpp'],
            'gross_profit_month' => $monthPl['gross_profit'],
            'net_profit_month'   => $monthPl['net_profit'],
            'expenses_month'     => $monthPl['expense_total'],
            'cash_in_today'   => $todayCf['cash_in']['total'],
            'cash_out_today'  => $todayCf['cash_out']['total'],
            'saldo_kas'   => CashAccount::totalCashBalance(),
            'saldo_bank'  => CashAccount::totalBankBalance(),
            'low_stock_count' => Product::lowStockCount(),
            'inventory_value' => Product::totalInventoryValue(),
        ];
    }
}
