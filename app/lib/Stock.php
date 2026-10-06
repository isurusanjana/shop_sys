<?php
class Stock {
    /** Change on-hand qty (transactional caller recommended). Throws on negative result unless allowed. */
    static function change(int $wh, int $pid, int $delta, string $type, ?string $refType = null, ?int $refId = null, ?string $note = null, bool $allowNeg = false): void {
        DB::q('INSERT IGNORE INTO stock (warehouse_id, product_id, qty) VALUES (?,?,0)', [$wh, $pid]);
        $row = DB::one('SELECT qty FROM stock WHERE warehouse_id=? AND product_id=? FOR UPDATE', [$wh, $pid]);
        if (!$allowNeg && $row['qty'] + $delta < 0) {
            $n = DB::val('SELECT name FROM products WHERE id=?', [$pid]);
            throw new RuntimeException("Insufficient stock for \"$n\" (on hand {$row['qty']}, needed " . abs($delta) . ').');
        }
        DB::q('UPDATE stock SET qty = qty + ? WHERE warehouse_id=? AND product_id=?', [$delta, $wh, $pid]);
        DB::insert('stock_movements', ['product_id' => $pid, 'warehouse_id' => $wh, 'qty_change' => $delta, 'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId, 'note' => $note, 'user_id' => Auth::id()]);
    }
    static function available(int $wh, int $pid): int {
        return (int)DB::val('SELECT qty - reserved_qty FROM stock WHERE warehouse_id=? AND product_id=?', [$wh, $pid]);
    }
    static function defaultWarehouse(?int $branchId): ?int {
        $w = DB::val('SELECT id FROM warehouses WHERE branch_id=? AND is_active=1 ORDER BY is_default DESC, id LIMIT 1', [$branchId]);
        return $w ? (int)$w : null;
    }
}
