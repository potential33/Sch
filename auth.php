<?php
// /app/auth.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AppException.php';

/* =========================================================
 * SESSION
 * ========================================================= */

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable when you move to HTTPS
    ]);
    session_start();

    // CSRF token bootstrap
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/** Current user row (joined with teacher) or null. */
function auth_user(): ?array
{
    static $cache = null;
    static $loaded = false;
    if ($loaded) return $cache;
    $loaded = true;

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return null;

    $row = fetch_one(
    "SELECT u.id, u.username, u.role, u.teacher_id, u.student_id, u.active,
            t.full_name AS teacher_name, t.email AS teacher_email
     FROM users u
     LEFT JOIN teachers t ON t.id = u.teacher_id
     WHERE u.id = ? AND u.active = 1",
    [$uid]
);
    if (!$row) {
        // user deleted or deactivated mid-session
        auth_logout();
        return null;
    }
    return $cache = $row;
}

function auth_check(): bool { return auth_user() !== null; }

function auth_role_is(string ...$roles): bool
{
    $u = auth_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function auth_teacher_id(): ?int
{
    $u = auth_user();
    return $u && $u['role'] === 'teacher' ? (int) $u['teacher_id'] : null;
}

/* =========================================================
 * LOGIN / LOGOUT
 * ========================================================= */

/**
 * Attempt a login. Returns ['ok' => bool, 'error' => ?string].
 * Rate-limited per session: 5 failures in 15 minutes locks for 15 minutes.
 */
function auth_attempt(string $username, string $password): array
{
    auth_start();

    // Rate limiting
    $now = time();
    $rl  = $_SESSION['login_rl'] ?? ['count' => 0, 'first' => $now, 'locked_until' => 0];

    if ($rl['locked_until'] > $now) {
        $mins = (int) ceil(($rl['locked_until'] - $now) / 60);
        return ['ok' => false, 'error' => "Too many attempts. Try again in {$mins} minute(s)."];
    }
    if ($now - $rl['first'] > 900) {
        // window expired
        $rl = ['count' => 0, 'first' => $now, 'locked_until' => 0];
    }

    $username = trim($username);
    $user = fetch_one(
        "SELECT * FROM users WHERE username = ? AND active = 1",
        [strtolower($username)]
    );

    $ok = $user && password_verify($password, $user['password_hash']);

    if (!$ok) {
        $rl['count']++;
        if ($rl['count'] >= 5) {
            $rl['locked_until'] = $now + 900;
        }
        $_SESSION['login_rl'] = $rl;
        return ['ok' => false, 'error' => 'Invalid username or password.'];
    }

    // Success — clear rate limit, rotate session id, set user
    unset($_SESSION['login_rl']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    return ['ok' => true, 'error' => null];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* =========================================================
 * GATES
 * ========================================================= */

/** Redirect to /login if not logged in. Also refreshes CSRF token check on POST. */
function auth_require(): void
{
    if (!auth_check()) {
        $back = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('/?' . http_build_query(['r' => 'login', 'next' => $back]));
    }
}

/** 403 unless user has one of the roles. */
function auth_require_role(string ...$roles): void
{
    auth_require();
    if (!auth_role_is(...$roles)) {
        http_response_code(403);
        throw new AppException('You do not have permission to access this page.');
    }
}

/* =========================================================
 * CSRF
 * ========================================================= */

function csrf_token(): string
{
    auth_start();
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'
         . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token on any non-GET request. Call once, near the top of
 * index.php, after auth_start() and before dispatching to the page.
 * Throws AppException on failure.
 */
function csrf_verify(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') return;

    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $have = $_SESSION['csrf_token'] ?? '';

    if (!$sent || !$have || !hash_equals($have, $sent)) {
        throw new AppException('CSRF token missing or invalid. Please refresh the page and try again.');
    }
}

/** Rotate the token (call on logout or privilege change). */
function csrf_rotate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
/* =========================================================
 * STUDENT-SPECIFIC HELPERS
 * ========================================================= */

function auth_student_id(): ?int
{
    $u = auth_user();
    return $u && $u['role'] === 'student' ? (int) $u['student_id'] : null;
}

function auth_must_change_password(): bool
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return false;
    $flag = fetch_val("SELECT must_change_password FROM users WHERE id = ?", [$uid]);
    return (int) $flag === 1;
}

function auth_clear_must_change_password(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return;
    q("UPDATE users SET must_change_password = 0 WHERE id = ?", [$uid]);
}

/**
 * If the user hasn't changed their initial password, force them to.
 * Called from index.php on every route EXCEPT logout and /password/force.
 */
function auth_enforce_password_change(string $currentRoute): void
{
    if (!auth_check()) return;
    if ($currentRoute === 'logout' || $currentRoute === 'password/force') return;
    if (auth_must_change_password()) {
        flash_set('error', 'You must change your initial password before continuing.');
        redirect(route_link('password/force'));
    }
}