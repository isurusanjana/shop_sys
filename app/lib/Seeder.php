<?php
class Seeder {
    static function permissions(): void {
        $defs = require APP_PATH . '/permissions.php';
        foreach ($defs as $mod => $m) foreach ($m['features'] as $feat => [$flabel, $actions]) foreach ($actions as $act => $alabel) {
            DB::q('INSERT INTO permissions (code, module, feature, action, label) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)',
                ["$mod.$feat.$act", $mod, $feat, $act, "$flabel: $alabel"]);
        }
    }
    static function roles(): void {
        $all = array_column(DB::all('SELECT id, code FROM permissions'), 'id', 'code');
        $match = function (array $include, array $exclude = []) use ($all) {
            $ids = [];
            foreach ($all as $code => $id) {
                $ok = false; foreach ($include as $p) if (fnmatch($p, $code)) { $ok = true; break; }
                if ($ok) foreach ($exclude as $p) if (fnmatch($p, $code)) { $ok = false; break; }
                if ($ok) $ids[] = $id;
            }
            return $ids;
        };
        $roles = [
            ['Administrator', 'Full system access', 1, []],
            ['Manager', 'Store manager: approvals, operations and reporting', 0, $match(['*'], ['admin.users.*', 'admin.roles.*', 'admin.settings.edit'])],
            ['Cashier', 'POS cashier', 0, $match(['pos.session.open', 'pos.sale.create', 'pos.discount.apply', 'pos.returns.create', 'pos.history.view', 'pos.history.reprint', 'admin.customers.view', 'admin.customers.create', 'inventory.stock.view'])],
            ['Inventory Clerk', 'Stock and purchasing staff', 0, $match(['inventory.*', 'admin.suppliers.view'], ['inventory.*.approve', 'inventory.purchasing.invoice'])],
            ['Accountant', 'Finance and reports', 0, $match(['admin.finance.*', 'admin.reports.*', 'admin.suppliers.view', 'admin.suppliers.payment', 'admin.customers.view', 'admin.customers.payment', 'inventory.purchasing.view', 'inventory.purchasing.invoice'])],
        ];
        foreach ($roles as [$name, $desc, $super, $perms]) {
            if (DB::val('SELECT 1 FROM roles WHERE name=?', [$name])) continue;
            $rid = DB::insert('roles', ['name' => $name, 'description' => $desc, 'is_super' => $super]);
            foreach ($perms as $pid) DB::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
        }
    }
    static function defaults(string $businessName): void {
        foreach (['business_name' => $businessName, 'business_address' => '', 'business_phone' => '', 'currency' => 'Rs.', 'tax_rate' => '0', 'return_days' => '14',
            'max_cashier_discount' => '10', 'loyalty_per_amount' => '100', 'allow_negative_stock' => '0', 'receipt_footer' => 'Thank you for shopping with us!'] as $k => $v)
            DB::q('INSERT IGNORE INTO settings (skey, svalue) VALUES (?,?)', [$k, $v]);
        if (!DB::val('SELECT COUNT(*) FROM branches')) {
            $b = DB::insert('branches', ['code' => 'MAIN', 'name' => 'Main Branch']);
            DB::insert('warehouses', ['branch_id' => $b, 'name' => 'Main Warehouse', 'is_default' => 1]);
            DB::insert('counters', ['branch_id' => $b, 'name' => 'Counter 1']);
        }
        foreach (['Piece' => 'pc', 'Pack' => 'pk', 'Box' => 'box', 'Ream' => 'rm', 'Set' => 'set', 'Dozen' => 'dz'] as $n => $s) DB::q('INSERT IGNORE INTO units (name, short_name) VALUES (?,?)', [$n, $s]);
        foreach ([['Retail', 'retail', 0], ['Wholesale', 'wholesale', 0], ['Member', 'member', 0]] as [$n, $t, $d]) DB::q('INSERT IGNORE INTO customer_groups (name, price_type, discount_percent) VALUES (?,?,?)', [$n, $t, $d]);
        if (!DB::val('SELECT COUNT(*) FROM categories')) {
            $tree = ['Books' => ['Fiction' => [], 'Non-Fiction' => [], 'Educational' => ['Mathematics', 'Science', 'Languages'], "Children's Books" => []],
                'Stationery' => ['Writing' => ['Pens', 'Pencils', 'Markers'], 'Paper Products' => [], 'School Supplies' => [], 'Office Supplies' => [], 'Art Supplies' => []]];
            foreach ($tree as $top => $subs) {
                $t = DB::insert('categories', ['name' => $top]);
                foreach ($subs as $s => $leafs) { $sid = DB::insert('categories', ['name' => $s, 'parent_id' => $t]); foreach ($leafs as $l) DB::insert('categories', ['name' => $l, 'parent_id' => $sid]); }
            }
        }
    }
    static function admin(string $username, string $fullName, string $password): int {
        $uid = DB::insert('users', ['username' => $username, 'full_name' => $fullName, 'password_hash' => Auth::hashPassword($password), 'branch_id' => DB::val('SELECT id FROM branches ORDER BY id LIMIT 1')]);
        DB::insert('user_roles', ['user_id' => $uid, 'role_id' => DB::val("SELECT id FROM roles WHERE name='Administrator'")]);
        return $uid;
    }
    static function demo(): void {
        $cat = fn($n) => DB::val('SELECT id FROM categories WHERE name=? LIMIT 1', [$n]);
        $unit = DB::val("SELECT id FROM units WHERE name='Piece'"); $wh = (int)DB::val('SELECT id FROM warehouses ORDER BY id LIMIT 1');
        $branch = (int)DB::val('SELECT id FROM branches ORDER BY id LIMIT 1');
        foreach (['Martin Wickramasinghe', 'Ediriweera Sarachchandra', 'Robert C. Martin', 'Jane Austen'] as $a) DB::q('INSERT IGNORE INTO authors (name) VALUES (?)', [$a]);
        foreach (['Gunasena', 'Godage', 'Pearson', 'Penguin'] as $p) DB::q('INSERT IGNORE INTO publishers (name) VALUES (?)', [$p]);
        foreach (['Atlas', 'Pilot', 'Faber-Castell', 'Staedtler'] as $b) DB::q('INSERT IGNORE INTO brands (name) VALUES (?)', [$b]);
        $aid = fn($n) => DB::val('SELECT id FROM authors WHERE name=?', [$n]); $pid = fn($n) => DB::val('SELECT id FROM publishers WHERE name=?', [$n]); $bid = fn($n) => DB::val('SELECT id FROM brands WHERE name=?', [$n]);
        $books = [['Gamperaliya', 'Martin Wickramasinghe', 'Godage', 'Fiction', 'Sinhala', 950, 1100], ['Clean Code', 'Robert C. Martin', 'Pearson', 'Educational', 'English', 6200, 7500],
            ['Pride and Prejudice', 'Jane Austen', 'Penguin', 'Fiction', 'English', 1800, 2400], ['Maname', 'Ediriweera Sarachchandra', 'Gunasena', 'Non-Fiction', 'Sinhala', 400, 550]];
        $seed = 978955100000;
        foreach ($books as $i => [$name, $au, $pu, $c, $lang, $cost, $price]) {
            $isbn = Barcode::ean13Check((string)($seed + $i * 7 + 3));
            $id = DB::insert('products', ['product_type' => 'book', 'sku' => next_number('sku', 'BK-', 5), 'barcode' => $isbn, 'isbn' => $isbn, 'name' => $name, 'category_id' => $cat($c), 'unit_id' => $unit,
                'author_id' => $aid($au), 'publisher_id' => $pid($pu), 'language' => $lang, 'edition' => '1st', 'pub_year' => 2020, 'cost_price' => $cost, 'selling_price' => $price, 'reorder_level' => 5, 'min_stock' => 2]);
            Stock::change($wh, $id, 25, 'opening', null, null, 'Opening stock');
        }
        $st = [['Blue Ball Pen', 'Pens', 'Atlas', 'Blue', 12, 20], ['Black Ball Pen', 'Pens', 'Atlas', 'Black', 12, 20], ['HB Pencil', 'Pencils', 'Staedtler', 'Grey', 18, 30], ['A4 Copy Paper (Ream)', 'Paper Products', 'Pilot', 'White', 1150, 1350],
            ['Exercise Book 80pg', 'School Supplies', 'Atlas', 'Mixed', 95, 130], ['Permanent Marker', 'Markers', 'Faber-Castell', 'Black', 70, 110], ['Stapler', 'Office Supplies', 'Pilot', 'Black', 450, 650], ['Water Colour Set', 'Art Supplies', 'Faber-Castell', 'Mixed', 780, 990]];
        foreach ($st as [$name, $c, $b, $col, $cost, $price]) {
            $id = DB::insert('products', ['product_type' => 'stationery', 'sku' => next_number('sku', 'ST-', 5), 'barcode' => Barcode::generate(), 'name' => $name, 'category_id' => $cat($c), 'brand_id' => $bid($b), 'unit_id' => $unit,
                'color' => $col, 'pack_qty' => 1, 'cost_price' => $cost, 'selling_price' => $price, 'wholesale_price' => round($price * 0.9, 2), 'member_price' => round($price * 0.95, 2), 'reorder_level' => 20, 'min_stock' => 5]);
            Stock::change($wh, $id, 100, 'opening', null, null, 'Opening stock');
        }
        DB::insert('suppliers', ['code' => next_number('supplier', 'SUP-', 4), 'name' => 'Lanka Book Distributors', 'contact_person' => 'Nuwan', 'phone' => '0112345678', 'payment_terms' => '30 days']);
        DB::insert('customers', ['code' => next_number('customer', 'CUS-', 5), 'name' => 'Sample Customer', 'phone' => '0771234567', 'group_id' => DB::val("SELECT id FROM customer_groups WHERE name='Member'"), 'credit_limit' => 20000]);
        foreach ([['manager', 'Store Manager', 'Manager'], ['cashier', 'Demo Cashier', 'Cashier'], ['clerk', 'Inventory Clerk', 'Inventory Clerk']] as [$u, $n, $role]) {
            $uid = DB::insert('users', ['username' => $u, 'full_name' => $n, 'password_hash' => Auth::hashPassword('Demo@1234'), 'branch_id' => $branch]);
            DB::insert('user_roles', ['user_id' => $uid, 'role_id' => DB::val('SELECT id FROM roles WHERE name=?', [$role])]);
        }
    }
}
