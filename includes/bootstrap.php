<?php
// Shared helpers used by every screen: session, CSRF, flash messages, audit trail, friendly DB errors.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

// Works even when the optional mbstring extension is disabled.
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return preg_match_all('/./us', (string)$s); }
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function self_name(): string { return basename($_SERVER['SCRIPT_NAME']); }
function url(array $q = [], ?string $page = null): string {
    $q = array_filter($q, function ($v) { return $v !== null && $v !== ''; });
    return ($page ?? self_name()) . ($q ? '?' . http_build_query($q) : '');
}
function redirect(string $to) { header('Location: ' . $to); exit; }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function post(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function money($n): string { return '₱' . number_format((float)$n, 2); }
function dt($s): string { return $s ? date('M j, Y · g:i A', strtotime((string)$s)) : '—'; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function current_user_id(): ?int { return isset($_SESSION['acting_user']) ? (int)$_SESSION['acting_user'] : null; }

// Writes one row to audit_logs for the user currently selected in the top bar.
function audit(string $action, string $details = ''): void {
    global $pdo;
    try {
        $pdo->prepare('INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)')
            ->execute([current_user_id(), $action, $details]);
    } catch (Throwable $e) { /* auditing must never break the screen */ }
}

// Turns raw database errors into messages a normal user understands.
function db_error(Throwable $e): string {
    if ($e instanceof PDOException) {
        $code = (int)($e->errorInfo[1] ?? 0);
        $msg  = (string)($e->errorInfo[2] ?? '');
        if ($code === 1644) return $msg;                                  // business rule from a trigger
        if ($code === 1062) return 'That value already exists (duplicate).';
        if ($code === 1451) return 'Cannot delete: other records still depend on this one.';
        if ($code === 1452) return 'Invalid selection: the related record does not exist.';
        if ($code === 3819 || $code === 4025) return 'A value is outside the allowed range.';
        if ($code === 1048) return 'A required field is empty.';
    }
    return 'Something went wrong while saving. Please try again.';
}

function badge(string $text, string $variant = 'gray'): string {
    return '<span class="badge badge-' . e($variant) . '">' . e($text) . '</span>';
}
function status_badge(?string $s): string {
    $map = ['active' => 'green', 'reserved' => 'amber', 'sold' => 'blue', 'archived' => 'gray',
            'pending' => 'amber', 'confirmed' => 'green', 'completed' => 'blue', 'cancelled' => 'red'];
    return $s === null ? '—' : badge(ucfirst($s), $map[$s] ?? 'gray');
}
function tier_badge(?string $t): string {
    $map = ['Gold' => 'gold', 'Silver' => 'silver', 'Bronze' => 'bronze'];
    return $t === null ? badge('No score', 'gray') : badge('★ ' . $t, $map[$t] ?? 'gray');
}
function sql_block(string $sql): string {
    return '<details class="sql"><summary>View the SQL behind this report</summary><pre>' . e(trim($sql)) . '</pre></details>';
}
function empty_row(int $cols, string $msg = 'Nothing here yet.'): string {
    return '<tr><td colspan="' . $cols . '" class="empty">' . e($msg) . '</td></tr>';
}
function errors_box(array $errors): string {
    if (!$errors) return '';
    return '<div class="flash flash-error"><ul><li>' . implode('</li><li>', array_map('e', $errors)) . '</li></ul></div>';
}
function delete_form(string $action, array $hidden, string $confirm): string {
    $h = '';
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return '<form method="post" class="inline" data-confirm="' . e($confirm) . '">' . csrf_field()
         . '<input type="hidden" name="action" value="' . e($action) . '">' . $h
         . '<button class="btn btn-sm btn-danger" type="submit">Delete</button></form>';
}

// "Acting as" switcher in the top bar (simple demo identity; used by audit()).
if (is_post() && isset($_POST['switch_user'])) {
    csrf_verify();
    $_SESSION['acting_user'] = (int)$_POST['switch_user'];
    redirect($_SERVER['REQUEST_URI']);
}
