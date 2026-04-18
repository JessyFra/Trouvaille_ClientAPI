<?php
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

$error  = null;
$posted = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'name'     => trim($_POST['name']     ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'confirm'  => trim($_POST['confirm']  ?? ''),
    ];

    if (!$posted['name'] || !$posted['password'] || !$posted['confirm']) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif ($posted['password'] !== $posted['confirm']) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($posted['password']) < 6) {
        $error = 'Le mot de passe doit faire au moins 6 caractères.';
    } else {
        $api      = new ApiClient();
        $response = $api->post('/auth/register', [
            'name'     => $posted['name'],
            'password' => $posted['password'],
        ]);

        if ($response['status'] === 201) {
            // Connexion automatique
            $login = $api->post('/auth/login', [
                'name'     => $posted['name'],
                'password' => $posted['password'],
            ]);

            if ($login['status'] === 200 && isset($login['body']['token'])) {
                Auth::setToken($login['body']['token']);
                $me = $api->get('/auth/me', $login['body']['token']);
                if ($me['status'] === 200) {
                    Auth::setUser($me['body']);
                }
            }

            $_SESSION['flash_toasts'][] = [
                'msg'  => 'Compte créé avec succès ! Bienvenue sur Trouvaille.',
                'type' => 'success',
            ];
            header('Location: /');
            exit;
        } elseif ($response['status'] === 409) {
            $error = 'Ce nom d\'utilisateur est déjà pris.';
        } elseif ($response['status'] === 422) {
            $apiError = $response['body']['error']['message'] ?? null;
            $error    = $apiError ?: 'Données invalides, vérifiez le formulaire.';
        } else {
            $error = 'Une erreur est survenue, veuillez réessayer.';
        }
    }
}
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-card">
            <div class="auth-card__header">
                <h1 class="auth-card__title">Créer un compte</h1>
                <p class="auth-card__subtitle">Rejoignez Trouvaille et déposez vos annonces gratuitement.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/inscription">
                <div class="mb-3">
                    <label for="name" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="name" name="name" class="form-control"
                        placeholder="ex: jean_dupont"
                        value="<?= htmlspecialchars($posted['name'] ?? '') ?>"
                        autocomplete="username"
                        maxlength="16"
                        required>
                    <span class="form-text">3 à 16 caractères, lettres, chiffres et underscores uniquement.</span>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Minimum 6 caractères"
                        autocomplete="new-password"
                        required>
                </div>

                <div class="mb-4">
                    <label for="confirm" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="confirm" name="confirm" class="form-control"
                        placeholder="Répétez votre mot de passe"
                        autocomplete="new-password"
                        required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-user-plus"></i> Créer mon compte
                </button>
            </form>

            <div class="auth-card__footer">
                Déjà un compte ?
                <a href="/connexion">Se connecter</a>
            </div>
        </div>
    </div>
</section>