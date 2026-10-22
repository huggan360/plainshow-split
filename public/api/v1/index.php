<?php
// Split API entry point. Routes live in app/routes/api.php.
require dirname(__DIR__, 3) . '/app/bootstrap.php';

// Calls through plainshow.se/api come signed by core, naming the person.
if (PlainShow\Kit\Signed::present($_SERVER)) {
    $file = dirname(__DIR__, 3) . '/config/widgets.php';
    $multipart = str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
    try {
        $verified = PlainShow\Kit\Signed::verify($_SERVER, $multipart ? '' : (string) file_get_contents('php://input'), is_file($file) ? (string) (require $file)['secret'] : '');
    } catch (PlainShow\Kit\KitException $error) {
        Split\Api\Router::send(401, ['ok' => false, 'message' => $error->getMessage()]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort(); // nothing from a gateway call is kept in a session
    }
    gateway_identity(PlainShow\Kit\Signed::identity($verified));
}

$router = new Split\Api\Router();
require dirname(__DIR__, 3) . '/app/routes/api.php';

$path = preg_replace('#^/api/v1#', '', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?: '/';
if ($path === '/openapi.json' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/plainshow.json'), true);
    echo json_encode(PlainShow\Kit\OpenApi::build($router->routes(), require dirname(__DIR__, 3) . '/app/routes/openapi-docs.php', [
        'title' => ps_t('PlainShow Split'), 'version' => (string) ($manifest['version'] ?? '1'), 'description' => ps_t('Groups, shared expenses and settling up. Amounts are sent in kronor and returned in öre.'),
    ], 'https://plainshow.se/api/split/v1'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
