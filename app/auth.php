<?php
/*
 * PlainShow sign-in for Split: the session, and the kit's OIDC client.
 *   splitAuth()->requireUser()   sign in if needed
 *   current_identity()           the signed-in person, or null
 */
use PlainShow\Kit\PhpSessionAuth;

function splitSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.save_path', dirname(__DIR__) . '/storage/sessions');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_name('split_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function splitAuth(): PhpSessionAuth
{
    static $auth = null;
    splitSession();
    return $auth ??= new PhpSessionAuth(splitConfig('plainshow'));
}
