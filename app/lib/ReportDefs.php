<?php
/** Report catalogue. Each report: title, group, period(bool), run(from,to,params) => [headers, rows, align(r cols), totals(cols to sum)] */
class ReportDefs {
    static function all(): array {
        $c = fn($t) => $t;
        return [
            'sales_daily' => ['Sales by day', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT DATE(sale_date) d, COUNT(*) n, SUM(subtotal) sub, SUM(discount_total) disc, SUM(tax_total) tax, SUM(total) tot FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY d", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Date', 'Invoices', 'Subtotal', 'Discount', 'Tax', 'Total'], array_map(fn($x) => [$x['d'], $x['n'], $x['sub'], $x['disc'], $x['tax'], $x['tot']], $r), [1, 2, 3, 4, 5], [1, 2, 3, 4, 5]]; }],
            'sales_product' => ['Sales by product', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT si.name, p.sku, SUM(si.qty - si.returned_qty) q, SUM(si.line_total) amt, SUM((si.unit_price - si.cost_price) * (si.qty - si.returned_qty)) - SUM(si.discount) AS margin FROM sale_items si JOIN sales s ON s.id=si.sale_id LEFT JOIN products p ON p.id=si.product_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ? GROUP BY si.product_id, si.name, p.sku ORDER BY amt DESC", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Product', 'SKU', 'Net qty', 'Sales', 'Est. margin'], array_map(fn($x) => [$x['name'], $x['sku'], $x['q'], $x['amt'], $x['margin']], $r), [2, 3, 4], [2, 3, 4]]; }],
            'sales_cashier' => ['Sales by cashier', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT u.full_name, COUNT(*) n, SUM(s.total) tot, SUM(s.discount_total) d FROM sales s JOIN users u ON u.id=s.user_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ? GROUP BY u.id ORDER BY tot DESC", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Cashier', 'Invoices', 'Total', 'Discounts given'], array_map(fn($x) => [$x['full_name'], $x['n'], $x['tot'], $x['d']], $r), [1, 2, 3], [1, 2, 3]]; }],
            'sales_payment' => ['Sales by payment method', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT sp.method, COUNT(*) n, SUM(sp.amount) a FROM sale_payments sp JOIN sales s ON s.id=sp.sale_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ? GROUP BY sp.method", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Method', 'Payments', 'Amount'], array_map(fn($x) => [ucwords(str_replace('_', ' ', $x['method'])), $x['n'], $x['a']], $r), [1, 2], [1, 2]]; }],
            'returns' => ['Returns & refunds', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT r.return_no, DATE(r.return_date) d, s.invoice_no, r.refund_method, r.reason, r.total_refund FROM sales_returns r JOIN sales s ON s.id=r.sale_id WHERE r.return_date BETWEEN ? AND ? ORDER BY r.id", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Return', 'Date', 'Invoice', 'Method', 'Reason', 'Refund'], array_map(fn($x) => array_values($x), $r), [5], [5]]; }],
            'voids' => ['Voided transactions', 'Sales', true, function ($f, $t) {
                $r = DB::all("SELECT s.invoice_no, s.sale_date, u.full_name, v.full_name vb, s.total, s.void_reason FROM sales s JOIN users u ON u.id=s.user_id LEFT JOIN users v ON v.id=s.voided_by WHERE s.status='void' AND s.voided_at BETWEEN ? AND ? ORDER BY s.id", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Invoice', 'Sold', 'Cashier', 'Voided by', 'Total', 'Reason'], array_map(fn($x) => array_values($x), $r), [4], [4]]; }],
            'inventory_value' => ['Inventory valuation', 'Inventory', false, function () {
                $r = DB::all('SELECT p.sku, p.name, COALESCE(SUM(s.qty),0) q, p.cost_price, COALESCE(SUM(s.qty),0)*p.cost_price v, COALESCE(SUM(s.qty),0)*p.selling_price rv FROM products p LEFT JOIN stock s ON s.product_id=p.id WHERE p.is_archived=0 GROUP BY p.id ORDER BY v DESC');
                return [['SKU', 'Product', 'Qty', 'Cost', 'Cost value', 'Retail value'], array_map(fn($x) => array_values($x), $r), [2, 3, 4, 5], [2, 4, 5]]; }],
            'low_stock' => ['Low & out of stock', 'Inventory', false, function () {
                $a = Alerts::all(); $rows = [];
                foreach (['out' => 'Out of stock', 'low' => 'Low stock'] as $k => $l) foreach ($a[$k] as $x) $rows[] = [$l, $x['sku'], $x['name'], $x['qty'], $x['reorder_level']];
                return [['Status', 'SKU', 'Product', 'On hand', 'Reorder level'], $rows, [3, 4], []]; }],
            'purchases' => ['Purchases (invoices)', 'Purchasing', true, function ($f, $t) {
                $r = DB::all('SELECT i.inv_no, i.invoice_date, s.name, i.supplier_invoice_no, i.total, i.status FROM purchase_invoices i JOIN suppliers s ON s.id=i.supplier_id WHERE i.invoice_date BETWEEN ? AND ? ORDER BY i.invoice_date', [$f, $t]);
                return [['Invoice', 'Date', 'Supplier', 'Supplier inv', 'Total', 'Status'], array_map(fn($x) => array_values($x), $r), [4], [4]]; }],
            'supplier_outstanding' => ['Supplier outstanding', 'Suppliers', false, function () {
                $rows = []; foreach (DB::all('SELECT id, code, name, phone FROM suppliers ORDER BY name') as $s) { $b = Finance::supplierBalance((int)$s['id']); if (abs($b) > 0.004) $rows[] = [$s['code'], $s['name'], $s['phone'], $b]; }
                return [['Code', 'Supplier', 'Phone', 'Outstanding'], $rows, [3], [3]]; }],
            'customer_outstanding' => ['Customer outstanding', 'Customers', false, function () {
                $rows = []; foreach (DB::all('SELECT id, code, name, phone, credit_limit FROM customers ORDER BY name') as $c) { $b = Finance::customerBalance((int)$c['id']); if (abs($b) > 0.004) $rows[] = [$c['code'], $c['name'], $c['phone'], $c['credit_limit'], $b]; }
                return [['Code', 'Customer', 'Phone', 'Credit limit', 'Outstanding'], $rows, [3, 4], [4]]; }],
            'top_customers' => ['Top customers', 'Customers', true, function ($f, $t) {
                $r = DB::all("SELECT c.code, c.name, COUNT(*) n, SUM(s.total) tot FROM sales s JOIN customers c ON c.id=s.customer_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ? GROUP BY c.id ORDER BY tot DESC LIMIT 100", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Code', 'Customer', 'Invoices', 'Total'], array_map(fn($x) => array_values($x), $r), [2, 3], [2, 3]]; }],
            'expenses' => ['Expenses', 'Finance', true, function ($f, $t) {
                $r = DB::all("SELECT expense_date, category, description, method, amount FROM expenses WHERE status='approved' AND expense_date BETWEEN ? AND ? ORDER BY expense_date", [$f, $t]);
                return [['Date', 'Category', 'Description', 'Method', 'Amount'], array_map(fn($x) => array_values($x), $r), [4], [4]]; }],
            'profit_loss' => ['Profit & loss', 'Finance', true, function ($f, $t) { return self::pl($f, $t); }],
            'cash_variance' => ['Cash drawer variances', 'Finance', true, function ($f, $t) {
                $r = DB::all("SELECT s.session_no, u.full_name, s.opened_at, s.opening_cash, s.expected_cash, s.counted_cash, s.variance FROM pos_sessions s JOIN users u ON u.id=s.user_id WHERE s.status IN ('closed','reconciled') AND s.closed_at BETWEEN ? AND ? ORDER BY s.id", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Session', 'Cashier', 'Opened', 'Opening', 'Expected', 'Counted', 'Variance'], array_map(fn($x) => array_values($x), $r), [3, 4, 5, 6], [6]]; }],
            'attendance' => ['Employee attendance summary', 'Employees', true, function ($f, $t) {
                $r = DB::all("SELECT e.emp_no, e.full_name, SUM(a.status='present') pr, SUM(a.status='late') la, SUM(a.status='half_day') hd, SUM(a.status='leave') lv, SUM(a.status='absent') ab FROM employees e LEFT JOIN attendance a ON a.employee_id=e.id AND a.att_date BETWEEN ? AND ? WHERE e.status='active' GROUP BY e.id ORDER BY e.full_name", [$f, $t]);
                return [['Emp no', 'Employee', 'Present', 'Late', 'Half day', 'Leave', 'Absent'], array_map(fn($x) => array_map(fn($v) => $v ?? 0, array_values($x)), $r), [2, 3, 4, 5, 6], [2, 3, 4, 5, 6]]; }],
            'employee_sales' => ['Employee sales performance', 'Employees', true, function ($f, $t) {
                $r = DB::all("SELECT COALESCE(e.emp_no,'-') en, u.full_name, COUNT(*) n, SUM(s.total) tot FROM sales s JOIN users u ON u.id=s.user_id LEFT JOIN employees e ON e.id=u.employee_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ? GROUP BY u.id ORDER BY tot DESC", ["$f 00:00:00", "$t 23:59:59"]);
                return [['Emp no', 'User', 'Invoices', 'Sales'], array_map(fn($x) => array_values($x), $r), [2, 3], [2, 3]]; }],
            'price_changes' => ['Price change log', 'Audit', true, function ($f, $t) {
                $r = DB::all('SELECT h.changed_at, p.sku, p.name, h.field_name, h.old_value, h.new_value, u.username FROM product_price_history h JOIN products p ON p.id=h.product_id LEFT JOIN users u ON u.id=h.user_id WHERE h.changed_at BETWEEN ? AND ? ORDER BY h.id DESC', ["$f 00:00:00", "$t 23:59:59"]);
                return [['When', 'SKU', 'Product', 'Field', 'Old', 'New', 'By'], array_map(fn($x) => array_values($x), $r), [4, 5], []]; }],
        ];
    }
    /** Profit & loss: sales net of returns, COGS, expenses. Returns report tuple. */
    static function pl(string $f, string $t): array {
        $a = ["$f 00:00:00", "$t 23:59:59"];
        $gross = (float)DB::val("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ?", $a);
        $ret = (float)DB::val('SELECT COALESCE(SUM(total_refund),0) FROM sales_returns WHERE return_date BETWEEN ? AND ?', $a);
        $cogs = (float)DB::val("SELECT COALESCE(SUM(si.cost_price * (si.qty - si.returned_qty)),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.status='completed' AND s.sale_date BETWEEN ? AND ?", $a);
        $tax = (float)DB::val("SELECT COALESCE(SUM(tax_total),0) FROM sales WHERE status='completed' AND sale_date BETWEEN ? AND ?", $a);
        $exp = (float)DB::val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='approved' AND expense_date BETWEEN ? AND ?", [$f, $t]);
        $net = $gross - $ret; $gp = $net - $tax - $cogs;
        $rows = [['Gross sales (incl. tax)', $gross], ['Less: returns & refunds', -$ret], ['Net sales', $net], ['Less: tax collected', -$tax], ['Less: cost of goods sold', -$cogs], ['Gross profit', $gp], ['Less: operating expenses', -$exp], ['NET PROFIT', $gp - $exp]];
        return [['Item', 'Amount'], $rows, [1], []];
    }
}
