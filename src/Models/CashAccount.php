<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * AZARED - kas & bank accounts. Deliberately single-entry: balances are
 * DERIVED (never stored/mutated directly) from opening_balance plus every
 * cash-moving record already in the system (sale_payments, purchase_payments,
 * expenses, cash_other_entries), attributed to an account by payment method.
 * This keeps the accounting surface small while staying accurate, per the
 * "don't over-build accounting" instruction.
 */
final class CashAccount
{
    /** Payment methods considered physical cash vs. everything else (bank/e-channel). */
    public const CASH_METHODS = ['cash'];

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM cash_accounts';
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= ' ORDER BY type ASC, name ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cash_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function methodMatchesAccountType(string $method, string $accountType): bool
    {
        $isCashMethod = in_array($method, self::CASH_METHODS, true);
        return $accountType === 'cash' ? $isCashMethod : !$isCashMethod;
    }

    /**
     * Balance of a single account as of a given moment (exclusive upper
     * bound "before $asOf"), derived from opening_balance plus every
     * cash movement attributable to it by payment-method mapping.
     */
    public static function balanceAsOf(array $account, string $asOf): float
    {
        $db = Database::connection();
        $balance = (float) $account['opening_balance'];
        $isCash = $account['type'] === 'cash';
        $methodFilter = $isCash
            ? "IN ('" . implode("','", self::CASH_METHODS) . "')"
            : "NOT IN ('" . implode("','", self::CASH_METHODS) . "')";

        $stmt = $db->prepare("SELECT COALESCE(SUM(sp.amount),0) FROM sale_payments sp
            JOIN sales s ON s.id = sp.sale_id
            WHERE sp.method {$methodFilter} AND s.created_at < :asof");
        $stmt->execute(['asof' => $asOf]);
        $balance += (float) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(pp.amount),0) FROM purchase_payments pp
            JOIN purchases p ON p.id = pp.purchase_id
            WHERE pp.method {$methodFilter} AND p.created_at < :asof");
        $stmt->execute(['asof' => $asOf]);
        $balance -= (float) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses
            WHERE deleted_at IS NULL AND payment_method {$methodFilter} AND expense_date < :asof");
        $stmt->execute(['asof' => $asOf]);
        $balance -= (float) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END),0) AS cash_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END),0) AS cash_out
            FROM cash_other_entries WHERE account_id = :account_id AND entry_date < :asof");
        $stmt->execute(['account_id' => $account['id'], 'asof' => $asOf]);
        $row = $stmt->fetch();
        $balance += (float) ($row['cash_in'] ?? 0) - (float) ($row['cash_out'] ?? 0);

        return round($balance, 2);
    }

    /** Current balance (as of right now) for every active account. */
    public static function allWithCurrentBalance(): array
    {
        $accounts = self::all(true);
        $now = date('Y-m-d H:i:s', strtotime('+1 minute'));
        foreach ($accounts as &$acc) {
            $acc['balance'] = self::balanceAsOf($acc, $now);
        }
        return $accounts;
    }

    public static function totalCashBalance(): float
    {
        $total = 0.0;
        foreach (self::allWithCurrentBalance() as $acc) {
            if ($acc['type'] === 'cash') {
                $total += $acc['balance'];
            }
        }
        return $total;
    }

    public static function totalBankBalance(): float
    {
        $total = 0.0;
        foreach (self::allWithCurrentBalance() as $acc) {
            if ($acc['type'] === 'bank') {
                $total += $acc['balance'];
            }
        }
        return $total;
    }
}
