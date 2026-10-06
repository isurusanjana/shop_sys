<?php
class ProductsController extends Controller {
    protected ?string $module = 'inventory';
    private function product(int $id): array { $p = DB::one('SELECT * FROM products WHERE id=?', [$id]); if (!$p) $this->abort(404, 'Product not found.'); return $p; }

    /** AJAX: returns a fresh unique EAN-13 for the product form's "Generate" button */
    function action_generate(): void {
        $this->need('inventory.products.view');
        do { $c = Barcode::generate(); } while (DB::val('SELECT 1 FROM products WHERE barcode=?', [$c]));
        json_out(['ok' => true, 'barcode' => $c]);
    }
    /** Assign barcodes to products that have none (all, or the selected ids). */
    function action_assign(): void {
        $this->postOnly(); $this->need('inventory.products.edit');
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $sql = "SELECT id FROM products WHERE (barcode IS NULL OR barcode='') AND is_archived=0";
        if ($ids) $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $n = 0;
        DB::tx(function () use ($sql, $ids, &$n) {
            foreach (DB::all($sql, $ids) as $r) {
                do { $c = Barcode::generate(); } while (DB::val('SELECT 1 FROM products WHERE barcode=?', [$c]));
                DB::update('products', ['barcode' => $c], 'id=?', [$r['id']]); $n++;
            }
        });
        audit('barcode_generate', 'products', null, ['count' => $n]);
        flash($n ? 'success' : 'info', $n ? "$n barcode(s) generated." : 'All selected products already have barcodes.');
        redirect('products/labels', array_filter(['q' => input('q'), 'type' => input('type')]));
    }
    /** Replace a product's barcode with a new generated one (explicit action; old code is logged). */
    function action_regenerate(): void {
        $this->postOnly(); $this->need('inventory.products.edit'); $p = $this->product((int)input('id'));
        if ($p['product_type'] === 'book' && $p['isbn']) { flash('warning', 'Books use their ISBN as the barcode; edit the ISBN to change it.'); redirect('products/label', ['id' => $p['id']]); }
        do { $c = Barcode::generate(); } while (DB::val('SELECT 1 FROM products WHERE barcode=?', [$c]));
        DB::update('products', ['barcode' => $c], 'id=?', [$p['id']]);
        audit('barcode_regenerate', 'products', $p['id'], ['old' => $p['barcode'], 'new' => $c]); flash('success', 'New barcode generated.'); redirect('products/label', ['id' => $p['id']]);
    }
    function action_label(): void {
        $this->need('inventory.products.view'); $p = $this->product((int)input('id'));
        $this->render('products/label', ['title' => 'Barcode: ' . $p['name'], 'p' => $p]);
    }
    /** Bulk label sheet builder + print view. */
    function action_labels(): void {
        $this->need('inventory.products.view');
        $q = (string)input('q', ''); $type = (string)input('type', ''); $w = ['p.is_archived=0']; $par = [];
        if ($q !== '') { $w[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.isbn LIKE ?)'; $l = '%' . addcslashes($q, '%_\\') . '%'; array_push($par, $l, $l, $l, $l); }
        if (in_array($type, ['book', 'stationery', 'other'], true)) { $w[] = 'p.product_type=?'; $par[] = $type; }
        if (input('nobarcode')) $w[] = "(p.barcode IS NULL OR p.barcode='')";
        $products = DB::all('SELECT p.id, p.sku, p.name, p.barcode, p.selling_price, p.product_type FROM products p WHERE ' . implode(' AND ', $w) . ' ORDER BY p.name LIMIT 300', $par);
        $missing = (int)DB::val("SELECT COUNT(*) FROM products WHERE (barcode IS NULL OR barcode='') AND is_archived=0");
        $this->render('products/labels', ['title' => 'Barcode Labels', 'products' => $products, 'q' => $q, 'type' => $type, 'missing' => $missing]);
    }
    function action_print(): void {
        $this->postOnly(); $this->need('inventory.products.view');
        $qty = (array)($_POST['qty'] ?? []); $items = []; $total = 0;
        foreach ($qty as $id => $n) {
            $n = max(0, min(200, (int)$n)); if (!$n) continue;
            $p = DB::one("SELECT id, sku, name, barcode, selling_price FROM products WHERE id=? AND barcode IS NOT NULL AND barcode<>''", [(int)$id]);
            if ($p) { $items[] = [$p, $n]; $total += $n; }
        }
        if (!$items) { flash('warning', 'Choose at least one product with a barcode and a quantity.'); redirect('products/labels'); }
        if ($total > 1000) { flash('danger', 'Too many labels at once (max 1000).'); redirect('products/labels'); }
        $size = in_array(input('size'), ['small', 'medium', 'large'], true) ? input('size') : 'medium';
        audit('barcode_print', 'products', null, ['labels' => $total]);
        $this->render('products/sheet', ['title' => 'Print labels', 'items' => $items, 'size' => $size, 'showPrice' => (bool)input('price'), 'showName' => (bool)input('name', 1)], 'none');
    }
    function action_history(): void {
        $this->need('inventory.products.view'); $p = $this->product((int)input('id'));
        $rows = DB::all('SELECT h.*, u.username FROM product_price_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.product_id=? ORDER BY h.id DESC LIMIT 200', [$p['id']]);
        $this->render('products/history', ['title' => 'Price history', 'p' => $p, 'rows' => $rows]);
    }
}
