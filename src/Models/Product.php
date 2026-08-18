<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

final class Product
{
    /**
     * @return array{rows: array, total: int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['stock_filter'])) {
            if ($filters['stock_filter'] === 'low') {
                $where[] = 'p.stock <= p.min_stock';
            } elseif ($filters['stock_filter'] === 'empty') {
                $where[] = 'p.stock <= 0';
            } elseif ($filters['stock_filter'] === 'available') {
                $where[] = 'p.stock > 0';
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM products p WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = Database::connection()->prepare(
            "SELECT p.*, c.name AS category_name, u.symbol AS unit_symbol
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE {$whereSql}
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, c.name AS category_name, u.symbol AS unit_symbol, u.name AS unit_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByBarcode(string $barcode): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM products WHERE barcode = :barcode AND deleted_at IS NULL AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['barcode' => $barcode]);
        return $stmt->fetch() ?: null;
    }

    /**
     * POS-facing quick search: name, SKU, or barcode. Only active products.
     */
    public static function searchForPos(string $term, ?int $categoryId, int $limit = 30): array
    {
        $where = ["p.status = 'active'", 'p.deleted_at IS NULL'];
        $params = [];

        if ($term !== '') {
            $where[] = '(p.name LIKE :term OR p.sku LIKE :term OR p.barcode = :exact)';
            $params['term'] = '%' . $term . '%';
            $params['exact'] = $term;
        }
        if ($categoryId) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $whereSql = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.sku, p.barcode, p.name, p.sell_price, p.wholesale_price, p.wholesale_min_qty,
                    p.stock, p.tax_percent, p.tax_inclusive, p.image_path, u.symbol AS unit_symbol
             FROM products p
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE {$whereSql}
             ORDER BY p.name ASC
             LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function skuExists(string $sku, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE sku = :sku';
        $params = ['sku' => $sku];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        if ($barcode === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM products WHERE barcode = :barcode';
        $params = ['barcode' => $barcode];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function generateSku(): string
    {
        $db = Database::connection();
        do {
            $sku = 'PRD-' . strtoupper(bin2hex(random_bytes(4)));
        } while (self::skuExists($sku));
        return $sku;
    }

    public static function create(array $data, int $createdBy): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO products
                (sku, barcode, name, category_id, unit_id, cost_price, sell_price, wholesale_price,
                 wholesale_min_qty, stock, min_stock, tax_percent, tax_id, tax_inclusive, status, image_path,
                 description, allow_negative_stock, created_by)
             VALUES
                (:sku, :barcode, :name, :category_id, :unit_id, :cost_price, :sell_price, :wholesale_price,
                 :wholesale_min_qty, :stock, :min_stock, :tax_percent, :tax_id, :tax_inclusive, :status, :image_path,
                 :description, :allow_negative_stock, :created_by)'
        );
        $stmt->execute([
            'sku'                  => $data['sku'],
            'barcode'              => $data['barcode'] !== '' ? $data['barcode'] : null,
            'name'                 => $data['name'],
            'category_id'          => $data['category_id'] ?: null,
            'unit_id'              => $data['unit_id'] ?: null,
            'cost_price'           => $data['cost_price'],
            'sell_price'           => $data['sell_price'],
            'wholesale_price'      => $data['wholesale_price'] !== '' ? $data['wholesale_price'] : null,
            'wholesale_min_qty'    => $data['wholesale_min_qty'] !== '' ? $data['wholesale_min_qty'] : null,
            'stock'                => $data['stock'] ?? 0,
            'min_stock'            => $data['min_stock'] ?? 0,
            'tax_percent'          => $data['tax_percent'] ?? 0,
            'tax_inclusive'        => !empty($data['tax_inclusive']) ? 1 : 0,
            'status'               => $data['status'],
            'image_path'           => $data['image_path'] ?? null,
            'description'          => $data['description'] !== '' ? $data['description'] : null,
            'allow_negative_stock' => !empty($data['allow_negative_stock']) ? 1 : 0,
            'created_by'           => $createdBy,
        ]);

        $productId = (int) $db->lastInsertId();
        $initialStock = (float) ($data['stock'] ?? 0);

        if ($initialStock != 0) {
            StockMovement::apply(
                $db,
                $productId,
                'initial',
                $initialStock,
                0.0,
                'product',
                $productId,
                $createdBy,
                'Stok awal saat produk dibuat',
                null,
                true
            );
        }

        return $productId;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE products SET
                sku = :sku, barcode = :barcode, name = :name, category_id = :category_id, unit_id = :unit_id,
                cost_price = :cost_price, sell_price = :sell_price, wholesale_price = :wholesale_price,
                wholesale_min_qty = :wholesale_min_qty, min_stock = :min_stock, tax_percent = :tax_percent, tax_id = :tax_id,
                tax_inclusive = :tax_inclusive, status = :status, image_path = :image_path,
                description = :description, allow_negative_stock = :allow_negative_stock
             WHERE id = :id'
        );
        $stmt->execute([
            'sku'                  => $data['sku'],
            'barcode'              => $data['barcode'] !== '' ? $data['barcode'] : null,
            'name'                 => $data['name'],
            'category_id'          => $data['category_id'] ?: null,
            'unit_id'              => $data['unit_id'] ?: null,
            'cost_price'           => $data['cost_price'],
            'sell_price'           => $data['sell_price'],
            'wholesale_price'      => $data['wholesale_price'] !== '' ? $data['wholesale_price'] : null,
            'wholesale_min_qty'    => $data['wholesale_min_qty'] !== '' ? $data['wholesale_min_qty'] : null,
            'min_stock'            => $data['min_stock'] ?? 0,
            'tax_percent'          => $data['tax_percent'] ?? 0,
            'tax_id'               => !empty($data['tax_id']) ? (int) $data['tax_id'] : null,
            'tax_inclusive'        => !empty($data['tax_inclusive']) ? 1 : 0,
            'status'               => $data['status'],
            'image_path'           => $data['image_path'] ?? null,
            'description'          => $data['description'] !== '' ? $data['description'] : null,
            'allow_negative_stock' => !empty($data['allow_negative_stock']) ? 1 : 0,
            'id'                   => $id,
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE products SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE products SET deleted_at = NOW(), status = "inactive" WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Lock a product row for update inside an already-open transaction.
     * MUST be used before any stock-changing operation (sale, purchase,
     * return, adjustment) so concurrent requests on the same product are
     * serialized instead of racing and corrupting the stock figure.
     */
    public static function lockForUpdate(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function lowStockCount(): int
    {
        $stmt = Database::connection()->query(
            "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'active' AND stock <= min_stock"
        );
        return (int) $stmt->fetchColumn();
    }

    public static function lowStockList(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, u.symbol AS unit_symbol FROM products p
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.deleted_at IS NULL AND p.status = 'active' AND p.stock <= p.min_stock
             ORDER BY (p.stock - p.min_stock) ASC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function bestSellers(string $sinceDate, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.name, p.sku, SUM(si.qty) AS total_qty, SUM(si.subtotal) AS total_revenue
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id AND s.status IN ('completed','partially_returned')
             JOIN products p ON p.id = si.product_id
             WHERE s.created_at >= :since
             GROUP BY p.id, p.name, p.sku
             ORDER BY total_qty DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':since', $sinceDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * /reports/inventory: current stock + valuation (stock x avg_cost) +
     * stock in/out totals for the given period, per product.
     */
    public static function inventoryReport(string $startDate, string $endDate, array $filters = []): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = ['start' => $startDate, 'end' => $endDate, 'start2' => $startDate, 'end2' => $endDate];

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.sku LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT p.id, p.sku, p.name, p.stock, p.min_stock, p.avg_cost, u.symbol AS unit_symbol,
                    p.stock * p.avg_cost AS inventory_value,
                    COALESCE((
                        SELECT SUM(sm.quantity) FROM stock_movements sm
                        WHERE sm.product_id = p.id AND sm.quantity > 0
                          AND sm.created_at >= :start AND sm.created_at < :end
                    ), 0) AS stock_in,
                    COALESCE((
                        SELECT SUM(-sm.quantity) FROM stock_movements sm
                        WHERE sm.product_id = p.id AND sm.quantity < 0
                          AND sm.created_at >= :start2 AND sm.created_at < :end2
                    ), 0) AS stock_out
                FROM products p
                LEFT JOIN units u ON u.id = p.unit_id
                WHERE {$whereSql}
                ORDER BY p.name ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalInventoryValue(): float
    {
        $stmt = Database::connection()->query(
            "SELECT COALESCE(SUM(stock * avg_cost),0) FROM products WHERE deleted_at IS NULL"
        );
        return (float) $stmt->fetchColumn();
    }

    public static function allActive(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, sku, name, cost_price, sell_price, stock FROM products WHERE deleted_at IS NULL ORDER BY name ASC"
        );
        return $stmt->fetchAll();
    }
}
