<?php
ob_start();
session_start();

$env = parse_ini_file(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/Auth.php';

// Routeur
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    ''                  => __DIR__ . '/../src/pages/home.php',
    'annonces'          => __DIR__ . '/../src/pages/home.php',
    'annonces/creer'    => __DIR__ . '/../src/pages/announce_create.php',
    'connexion'         => __DIR__ . '/../src/pages/login.php',
    'inscription'       => __DIR__ . '/../src/pages/register.php',
    'deconnexion'       => null,
    'profil'            => __DIR__ . '/../src/pages/profile.php',
    'messages'          => __DIR__ . '/../src/pages/messages.php',
    'admin'             => __DIR__ . '/../src/pages/admin.php',
    'contact'           => __DIR__ . '/../src/pages/contact.php',
];

// Routes dynamiques avec paramètre
$template   = null;
$routeParam = null;

if (preg_match('#^annonces/(\d+)/modifier$#', $uri, $m)) {
    $template   = __DIR__ . '/../src/pages/announce_edit.php';
    $routeParam = (int) $m[1];
} elseif (preg_match('#^annonces/(\d+)$#', $uri, $m)) {
    $template   = __DIR__ . '/../src/pages/announce.php';
    $routeParam = (int) $m[1];
} elseif (preg_match('#^messages/(\d+)$#', $uri, $m)) {
    $template   = __DIR__ . '/../src/pages/messages_thread.php';
    $routeParam = (int) $m[1];
} elseif (preg_match('#^profil/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
    $template   = __DIR__ . '/../src/pages/public_profile.php';
    $routeParam = $m[1];
} elseif (isset($routes[$uri])) {
    $template = $routes[$uri];
} else {
    $template = __DIR__ . '/../src/pages/home.php';
}

// Déconnexion
if ($uri === 'deconnexion') {
    Auth::logout();
    header('Location: /');
    exit;
}

// ── Proxy actions AJAX ──
if ($uri === 'action/delete-image' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }

    $body       = json_decode(file_get_contents('php://input'), true);
    $announceId = (int)($body['announce_id'] ?? 0);
    $imageId    = (int)($body['image_id']    ?? 0);

    if (!$announceId || !$imageId) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants']);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->delete(
        '/announces/' . $announceId . '/images/' . $imageId,
        Auth::getToken()
    );

    http_response_code($result['status']);
    echo json_encode($result['body']);
    exit;
}

// ── Proxy : envoyer un message ──────────────────────────────────
if ($uri === 'action/send-message' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Non authentifié']]);
        exit;
    }

    $body        = json_decode(file_get_contents('php://input'), true);
    $recipientId = (int)($body['recipient_id'] ?? 0);
    $content     = trim($body['content'] ?? '');

    if (!$recipientId || $content === '') {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Paramètres manquants']]);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->post(
        '/messages/' . $recipientId,
        ['content' => $content],
        Auth::getToken()
    );

    http_response_code($result['status']);
    echo json_encode($result['body']);
    exit;
}

// ── Proxy : récupérer les nouveaux messages (polling) ──────────
if ($uri === 'action/get-thread' && $method === 'GET') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }

    $otherUserId = (int)($_GET['user_id'] ?? 0);
    $afterId     = (int)($_GET['after']   ?? 0);

    if (!$otherUserId) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètre manquant']);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->get(
        '/messages/conversations/' . $otherUserId,
        Auth::getToken()
    );

    if ($result['status'] !== 200) {
        http_response_code($result['status']);
        echo json_encode(['error' => 'Erreur API']);
        exit;
    }

    // Filtre uniquement les messages plus récents que afterId
    $allMessages = $result['body']['data'] ?? [];
    $newMessages = array_values(array_filter(
        $allMessages,
        fn($m) => (int)$m['id'] > $afterId
    ));

    echo json_encode(['messages' => $newMessages]);
    exit;
}

// ── Proxy : ban / unban utilisateur ────────────────────────────
if ($uri === 'action/admin-user-toggle' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'Accès refusé']]);
        exit;
    }

    $body   = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($body['user_id'] ?? 0);
    $action = $body['action'] ?? ''; // 'ban' ou 'unban'

    if (!$userId || !in_array($action, ['ban', 'unban'])) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Paramètres invalides']]);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->patch(
        '/admin/users/' . $userId . '/' . $action,
        [],
        Auth::getToken()
    );

    http_response_code($result['status']);
    echo json_encode($result['body']);
    exit;
}

// ── Proxy : clôturer une annonce ────────────────────────────────
if ($uri === 'action/admin-close-announce' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'Accès refusé']]);
        exit;
    }

    $body       = json_decode(file_get_contents('php://input'), true);
    $announceId = (int)($body['announce_id'] ?? 0);

    if (!$announceId) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Paramètre manquant']]);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->patch(
        '/admin/announces/' . $announceId . '/close',
        [],
        Auth::getToken()
    );

    http_response_code($result['status']);
    echo json_encode($result['body']);
    exit;
}

// ── Proxy : réouvrir une annonce (admin) ────────────────────────
if ($uri === 'action/admin-reopen-announce' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'Accès refusé']]);
        exit;
    }

    $body       = json_decode(file_get_contents('php://input'), true);
    $announceId = (int)($body['announce_id'] ?? 0);

    if (!$announceId) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Paramètre manquant']]);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->patch(
        '/announces/' . $announceId . '/reopen',
        [],
        Auth::getToken()
    );

    http_response_code($result['status']);
    echo json_encode($result['body']);
    exit;
}

// ── Proxy : supprimer une annonce ───────────────────────────────
if ($uri === 'action/admin-delete-announce' && $method === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => ['message' => 'Accès refusé']]);
        exit;
    }

    $body       = json_decode(file_get_contents('php://input'), true);
    $announceId = (int)($body['announce_id'] ?? 0);

    if (!$announceId) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Paramètre manquant']]);
        exit;
    }

    $api    = new ApiClient();
    $result = $api->delete(
        '/admin/announces/' . $announceId,
        Auth::getToken()
    );

    // DELETE retourne 204 sans body
    http_response_code($result['status']);
    if ($result['status'] !== 204) {
        echo json_encode($result['body']);
    }
    exit;
}

include __DIR__ . '/../src/layout/header.php';
include $template;
include __DIR__ . '/../src/layout/footer.php';

ob_end_flush();
