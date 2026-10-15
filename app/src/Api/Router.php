<?php

namespace Split\Api;

use Throwable;

/**
 * /api/v1 router. Routes declare who may call them; writes need the CSRF
 * token. Handlers return arrays, sent as {"ok": true, ...}.
 */
final class Router
{
    /** @var list<array{string, string, callable, string|null}> */
    private array $routes = [];

    /** @param string|null $capability null: public; 'user': any signed-in person; otherwise a capability */
    public function add(string $method, string $pattern, callable $handler, ?string $capability = 'user'): void
    {
        $this->routes[] = [$method, $pattern, $handler, $capability];
    }

    public function dispatch(string $method, string $path): never
    {
        $path = '/' . trim($path, '/');
        $allowed = [];
        foreach ($this->routes as [$routeMethod, $pattern, $handler, $capability]) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($routeMethod !== $method) {
                $allowed[] = $routeMethod;
                continue;
            }
            try {
                $this->authorize($method, $capability);
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $result = $handler(new Request($method, $params));
                self::send(200, ['ok' => true] + ($result ?? []));
            } catch (ApiException $error) {
                self::send($error->status, ['ok' => false, 'message' => $error->getMessage()]);
            } catch (Throwable $error) {
                error_log('Split API ' . $method . ' ' . $path . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine());
                self::send(500, ['ok' => false, 'message' => 'Något gick fel på servern. Försök igen.']);
            }
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            self::send(405, ['ok' => false, 'message' => 'Metoden stöds inte.']);
        }
        self::send(404, ['ok' => false, 'message' => 'Hittades inte.']);
    }

    private function authorize(string $method, ?string $capability): void
    {
        if ($method !== 'GET' && !csrf_valid()) {
            throw new ApiException('Sidan har varit öppen länge. Ladda om och försök igen.', 419);
        }
        if ($capability === null) {
            return;
        }
        $identity = current_identity();
        if ($identity === null) {
            throw new ApiException('Logga in för att fortsätta.', 401);
        }
        if (!$identity->hasService('split')) {
            throw new ApiException('Du har inte tillgång till Split.', 403);
        }
        if ($capability !== 'user' && !$identity->can($capability)) {
            throw new ApiException('Du har inte behörighet att göra det.', 403);
        }
    }

    public static function send(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
