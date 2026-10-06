<?php
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url(): string { $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); return $d; }
function url(string $route = '', array $params = []): string {
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    return base_url() . '/index.php' . ($q ? '?' . http_build_query($q) : '');
}
function asset(string $p): string { return base_url() . '/assets/' . ltrim($p, '/'); }
function redirect(string $route, array $params = []): never { header('Location: ' . url($route, $params)); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function input(string $k, $d = null) { $v = $_POST[$k] ?? $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
function old(string $k, $d = '') { return $_SESSION['_old'][$k] ?? $d; }
function money($v): string { return setting('currency', 'Rs.') . ' ' . number_format((float)$v, 2); }
function fmt_date($v, string $f = 'Y-m-d'): string { return $v ? date($f, strtotime((string)$v)) : ''; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function json_out($d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json'); echo json_encode($d); exit; }
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }

function setting(string $k, $d = null) {
    static $all = null;
    if ($all === null) { try { $all = array_column(DB::all('SELECT skey, svalue FROM settings'), 'svalue', 'skey'); } catch (Throwable $e) { $all = []; } }
    return $all[$k] ?? $d;
}
function set_setting(string $k, string $v): void { DB::q('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', [$k, $v]); }

function next_number(string $seq, string $prefix, int $pad = 5): string {
    DB::q('INSERT INTO sequences (name, val) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE val = LAST_INSERT_ID(val + 1)', [$seq]);
    return $prefix . str_pad((string)DB::pdo()->lastInsertId(), $pad, '0', STR_PAD_LEFT);
}
function audit(string $action, ?string $entity = null, $id = null, $details = null): void {
    $u = Auth::user();
    try {
        DB::insert('audit_logs', ['user_id' => $u['id'] ?? null, 'username' => $u['username'] ?? null, 'action' => $action, 'entity' => $entity,
            'entity_id' => $id === null ? null : (string)$id, 'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details, 'ip' => client_ip()]);
    } catch (Throwable $e) { /* never break a request because logging failed */ }
}
function fin_txn(string $type, string $dir, string $method, float $amt, ?int $branch, ?int $session, ?string $refType, ?int $refId, ?string $note = null): void {
    DB::insert('fin_transactions', ['txn_date' => date('Y-m-d H:i:s'), 'type' => $type, 'direction' => $dir, 'method' => $method, 'amount' => round($amt, 2),
        'branch_id' => $branch, 'session_id' => $session, 'ref_type' => $refType, 'ref_id' => $refId, 'note' => $note, 'user_id' => Auth::id()]);
}
function paginate(int $total, int $per, int $page): array { $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, $page), $pages); return [$page, $pages, ($page - 1) * $per]; }
function payment_methods(): array { return ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'digital' => 'Digital Payment']; }
function badge(string $s): string {
    $m = ['active' => 'success', 'approved' => 'success', 'completed' => 'success', 'received' => 'success', 'paid' => 'success', 'open' => 'success', 'present' => 'success', 'reconciled' => 'success',
        'pending' => 'warning', 'draft' => 'secondary', 'partial' => 'info', 'submitted' => 'info', 'dispatched' => 'info', 'counting' => 'info', 'pending_approval' => 'warning', 'suspended' => 'warning', 'unpaid' => 'danger',
        'void' => 'danger', 'cancelled' => 'danger', 'rejected' => 'danger', 'inactive' => 'secondary', 'closed' => 'secondary', 'absent' => 'danger', 'late' => 'warning', 'leave' => 'info', 'half_day' => 'warning', 'ordered' => 'info'];
    return '<span class="badge text-bg-' . ($m[$s] ?? 'secondary') . '">' . e(ucwords(str_replace('_', ' ', $s))) . '</span>';
}
