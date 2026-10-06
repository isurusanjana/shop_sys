<?php
// Per-entity business validation and side effects. Method naming: validate_<slug>, before_<slug>, after_<slug>.
class EntityHooks {
    static function call(string $phase, string $slug, ...$args) { $m = $phase . '_' . $slug; return method_exists(__CLASS__, $m) ? self::$m(...$args) : ($phase === 'validate' ? [] : null); }
    static function before(string $slug, array &$d, ?int $id): void { $m = 'before_' . $slug; if (method_exists(__CLASS__, $m)) self::$m($d, $id); }

    static function validate_promotions(array $d, ?int $id): array {
        $e = [];
        if (($d['scope'] ?? '') === 'category' && empty($d['category_id'])) $e['category_id'] = 'Choose a category for a category promotion.';
        if (($d['scope'] ?? '') === 'product' && empty($d['product_id'])) $e['product_id'] = 'Choose a product for a product promotion.';
        if (($d['type'] ?? '') === 'percent' && (float)($d['value'] ?? 0) > 100) $e['value'] = 'Percentage cannot exceed 100.';
        return $e + self::dateRange($d);
    }
    static function validate_price_lists(array $d, ?int $id): array { return self::dateRange($d); }
    private static function dateRange(array $d): array {
        return (!empty($d['start_date']) && !empty($d['end_date']) && $d['end_date'] < $d['start_date']) ? ['end_date' => 'End date must not be before the start date.'] : [];
    }
    static function after_warehouses(int $id, ?array $old, array $d): void {
        if (!empty($d['is_default'])) DB::q('UPDATE warehouses SET is_default=0 WHERE branch_id=? AND id<>?', [$d['branch_id'], $id]);
        elseif (!DB::val('SELECT 1 FROM warehouses WHERE branch_id=? AND is_default=1', [$d['branch_id']])) DB::q('UPDATE warehouses SET is_default=1 WHERE id=?', [$id]);
    }
    static function validate_categories(array $d, ?int $id): array {
        if ($id && !empty($d['parent_id'])) {
            $p = (int)$d['parent_id'];
            for ($i = 0; $p && $i < 20; $i++) { if ($p === $id) return ['parent_id' => 'A category cannot be placed under itself or its own sub-category.']; $p = (int)DB::val('SELECT parent_id FROM categories WHERE id=?', [$p]); }
        }
        return [];
    }
    // ---- products / books / stationery
    static function validate_products(array $d, ?int $id): array {
        $e = [];
        if (isset($d['max_stock']) && $d['max_stock'] !== null && (int)$d['max_stock'] > 0 && (int)$d['max_stock'] < (int)($d['reorder_level'] ?? 0)) $e['max_stock'] = 'Maximum stock must be at least the reorder level.';
        if (!empty($d['barcode']) && preg_match('/^\d{13}$/', $d['barcode']) && !Barcode::valid13($d['barcode'])) $e['barcode'] = 'EAN-13 barcode has an invalid check digit.';
        if ($id && !empty($d['parent_id']) && (int)$d['parent_id'] === $id) $e['parent_id'] = 'A product cannot be its own parent.';
        return $e;
    }
    static function validate_books(array $d, ?int $id): array { return self::validate_products($d, $id); }
    static function validate_stationery(array $d, ?int $id): array { return self::validate_products($d, $id); }
    static function before_products(array &$d, ?int $id): void {
        $type = $d['product_type'] ?? 'other';
        if (empty($d['sku'])) $d['sku'] = next_number('sku', ['book' => 'BK-', 'stationery' => 'ST-'][$type] ?? 'PR-', 5);
        if (!empty($d['isbn'])) { $d['isbn'] = str_replace(['-', ' '], '', strtoupper($d['isbn'])); if (empty($d['barcode']) && strlen($d['isbn']) === 13) $d['barcode'] = $d['isbn']; }
        if (empty($d['barcode'])) { do { $d['barcode'] = Barcode::generate(); } while (DB::val('SELECT 1 FROM products WHERE barcode=?', [$d['barcode']])); }
    }
    static function before_books(array &$d, ?int $id): void { self::before_products($d, $id); }
    static function before_stationery(array &$d, ?int $id): void { self::before_products($d, $id); }
    static function after_products(int $id, ?array $old, array $d): void {
        foreach (['cost_price', 'selling_price', 'wholesale_price', 'member_price'] as $f) {
            $o = $old[$f] ?? null; $n = $d[$f] ?? null;
            if ($old === null) { if ($n === null) continue; } elseif ((string)(float)$o === (string)(float)$n && ($o === null) === ($n === null)) continue;
            DB::insert('product_price_history', ['product_id' => $id, 'field_name' => $f, 'old_value' => $o, 'new_value' => $n, 'user_id' => Auth::id()]);
            if ($old !== null) audit('price_change', 'products', $id, [$f => [$o, $n]]);
        }
    }
    static function after_books(int $id, ?array $old, array $d): void { self::after_products($id, $old, $d); }
    static function after_stationery(int $id, ?array $old, array $d): void { self::after_products($id, $old, $d); }
}
