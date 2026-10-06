<?php
class Lines {
    /** Parse parallel arrays product_id[], qty[], price[] from POST into validated lines. Returns [lines, error|null]. */
    static function parse(bool $needPrice = true, string $qtyKey = 'qty'): array {
        $ids = (array)($_POST['product_id'] ?? []); $qty = (array)($_POST[$qtyKey] ?? []); $price = (array)($_POST['price'] ?? []); $lines = []; $seen = [];
        foreach ($ids as $i => $pid) {
            $pid = (int)$pid; if (!$pid) continue;
            $q = $qty[$i] ?? ''; if (!preg_match('/^\d+$/', (string)$q) || (int)$q < 1 || (int)$q > 1000000) return [[], 'Quantities must be whole numbers greater than zero.'];
            $p = 0.0;
            if ($needPrice) { $pr = $price[$i] ?? ''; if (!is_numeric($pr) || $pr < 0 || $pr > 999999999) return [[], 'Prices must be valid non-negative amounts.']; $p = round((float)$pr, 2); }
            if (isset($seen[$pid])) return [[], 'The same product appears twice; combine the quantities.'];
            if (!DB::val('SELECT 1 FROM products WHERE id=? AND is_archived=0', [$pid])) return [[], 'A selected product no longer exists.'];
            $seen[$pid] = 1; $lines[] = ['product_id' => $pid, 'qty' => (int)$q, 'price' => $p];
        }
        return $lines ? [$lines, null] : [[], 'Add at least one product line.'];
    }
    static function products(): array { return DB::all('SELECT id, sku, name, cost_price FROM products WHERE is_archived=0 AND is_active=1 ORDER BY name LIMIT 3000'); }
}
