<?php
// Déjà connecté → accueil
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$password) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $api      = new ApiClient();
        $response = $api->post('/auth/login', [
            'name'     => $name,
            'password' => $password,
        ]);

        if ($response['status'] === 200 && isset($response['body']['token'])) {
            $token = $response['body']['token'];
            Auth::setToken($token);

            // Récupère le profil
            $me = $api->get('/auth/me', $token);
            if ($me['status'] === 200) {
                Auth::setUser($me['body']);

                // Vérifie si banni
                if ($me['body']['banned']) {
                    Auth::logout();
                    $error = 'Votre compte a été suspendu. Contactez l\'administration.';
                } else {
                    $_SESSION['flash_toasts'][] = [
                        'msg'  => 'Bienvenue, ' . htmlspecialchars($me['body']['display_name'] ?? $me['body']['name']) . ' !',
                        'type' => 'success',
                    ];
                    header('Location: /');
                    exit;
                }
            }
        } else {
            $error = 'Identifiants incorrects.';
        }
    }
}
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-card">
            <div class="auth-card__header">
                <h1 class="auth-card__title">Connexion</h1>
                <p class="auth-card__subtitle">Accédez à votre espace Trouvaille.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/connexion">
                <div class="mb-3">
                    <label for="name" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="name" name="name" class="form-control"
                        placeholder="ex: marie_dupont"
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        autocomplete="username"
                        required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Votre mot de passe"
                        autocomplete="current-password"
                        required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                </button>
            </form>

            <div class="auth-card__footer">
                Pas encore de compte ?
                <a href="/inscription">Créer un compte</a>
            </div>
        </div>
    </div>
</section>