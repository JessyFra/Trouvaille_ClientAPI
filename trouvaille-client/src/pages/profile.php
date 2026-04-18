<?php
// ── Auth guard ──
if (!Auth::isLoggedIn()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Connectez-vous pour accéder à votre profil.', 'type' => 'warning'];
    header('Location: /connexion');
    exit;
}

$api   = new ApiClient();
$token = Auth::getToken();
$me    = Auth::getUser();

// Rechargement frais depuis l'API
$fresh = $api->get('/auth/me', $token);
if ($fresh['status'] === 200) {
    $me = $fresh['body'];
    Auth::setUser($me);
}

$infoError   = null;
$infoSuccess = false;
$pwdError    = null;
$pwdSuccess  = false;

// ── Traitement formulaire infos ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_info'])) {
    $displayName = trim($_POST['display_name'] ?? '');
    $biography   = trim($_POST['biography']    ?? '');

    $payload = [
        'display_name' => $displayName !== '' ? $displayName : null,
        'biography'    => $biography   !== '' ? $biography   : null,
    ];

    $res = $api->patch('/auth/profile', $payload, $token);

    if ($res['status'] === 200) {
        Auth::setUser($res['body']);
        $me = $res['body'];
        $infoSuccess = true;
        $_SESSION['flash_toasts'][] = ['msg' => 'Profil mis à jour.', 'type' => 'success'];
        header('Location: /profil');
        exit;
    } else {
        $infoError = $res['body']['error']['message'] ?? 'Erreur lors de la mise à jour.';
    }
}

// ── Traitement formulaire mot de passe ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_password'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password']     ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if (!$currentPwd || !$newPwd || !$confirmPwd) {
        $pwdError = 'Veuillez remplir tous les champs.';
    } elseif ($newPwd !== $confirmPwd) {
        $pwdError = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($newPwd) < 6) {
        $pwdError = 'Le nouveau mot de passe doit faire au moins 6 caractères.';
    } else {
        $res = $api->patch('/auth/profile', [
            'current_password' => $currentPwd,
            'new_password'     => $newPwd,
        ], $token);

        if ($res['status'] === 200) {
            $_SESSION['flash_toasts'][] = ['msg' => 'Mot de passe modifié avec succès.', 'type' => 'success'];
            header('Location: /profil');
            exit;
        } else {
            $pwdError = $res['body']['error']['message'] ?? 'Erreur lors du changement de mot de passe.';
        }
    }
}

// ── Onglet actif ──
$activeTab = $_GET['tab'] ?? 'infos'; // 'infos' | 'annonces'

// ── Mes annonces (chargé uniquement sur l'onglet dédié) ──
$myAnnounces  = [];
$myTotal      = 0;
$myPage       = max(1, (int)($_GET['page'] ?? 1));
$myLimit      = 12;
$myTotalPages = 1;

if ($activeTab === 'annonces') {
    $myQuery = http_build_query([
        'author' => $me['name'],
        'page'   => $myPage,
        'limit'  => $myLimit,
    ]);
    $myRes        = $api->get('/announces?' . $myQuery);
    $myAnnounces  = $myRes['body']['data']  ?? [];
    $myTotal      = $myRes['body']['total'] ?? 0;
    $myTotalPages = (int) ceil($myTotal / $myLimit);
}

// ── Helpers ──
$displayName = $me['display_name'] ?? '';
$biography   = $me['biography']    ?? '';
$memberSince = date('d/m/Y', strtotime($me['created_at']));

function profileInitials(array $user): string
{
    $src   = $user['display_name'] ?? $user['name'] ?? '?';
    $parts = preg_split('/[\s_\-]+/', trim($src));
    $init  = '';
    foreach ($parts as $p) {
        if ($p !== '') $init .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($init) >= 2) break;
    }
    return $init ?: '?';
}
?>

<section class="profile-section">
    <div class="container">

        <div class="profile-layout">

            <!-- ══════════════════════════════════════════
                 COLONNE PRINCIPALE
            ══════════════════════════════════════════ -->
            <div class="profile-main">

                <!-- ── Onglets ── -->
                <div class="profile-tabs">
                    <a href="/profil?tab=infos"
                        class="profile-tab <?= $activeTab === 'infos' ? 'active' : '' ?>">
                        <i class="fa-regular fa-user"></i> Mon profil
                    </a>
                    <a href="/profil?tab=annonces"
                        class="profile-tab <?= $activeTab === 'annonces' ? 'active' : '' ?>">
                        <i class="fa-solid fa-rectangle-list"></i> Mes annonces
                        <?php if ($activeTab === 'annonces' && $myTotal > 0): ?>
                            <span class="profile-tab__count"><?= $myTotal ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- ════════════════════════════════════════
                     ONGLET : MES ANNONCES
                ════════════════════════════════════════ -->
                <?php if ($activeTab === 'annonces'): ?>

                    <div class="profile-card">
                        <div class="profile-card__header">
                            <h2 class="profile-card__title">
                                <i class="fa-solid fa-rectangle-list"></i>
                                Mes annonces
                            </h2>
                            <p class="profile-card__subtitle">
                                <?= $myTotal ?> annonce<?= $myTotal > 1 ? 's' : '' ?> publiée<?= $myTotal > 1 ? 's' : '' ?>
                            </p>
                        </div>

                        <?php if (empty($myAnnounces)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-regular fa-folder-open fa-2x mb-3 d-block"></i>
                                <p>Vous n'avez pas encore publié d'annonce.</p>
                                <a href="/annonces/creer" class="btn btn-primary mt-2">
                                    <i class="fa-solid fa-plus"></i> Déposer une annonce
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="announces-grid">
                                <?php foreach ($myAnnounces as $a):
                                    $imgSrc   = $a['main_image'] ?? null;
                                    $isClosed = $a['status'] === 'closed';

                                    if ($a['type'] === 'request') {
                                        $priceHtml = '<span class="announce-card__price demand">Demande</span>';
                                    } elseif ($a['price'] > 0) {
                                        $formatted = number_format($a['price'], 0, ',', ' ') . ' €';
                                        $priceHtml = '<span class="announce-card__price">' . htmlspecialchars($formatted) . '</span>';
                                    } else {
                                        $priceHtml = '<span class="announce-card__price free">Gratuit</span>';
                                    }

                                    $imgHtml = $imgSrc
                                        ? '<img src="' . htmlspecialchars($imgSrc) . '" alt="' . htmlspecialchars($a['title']) . '" loading="lazy"
                                               onerror="this.parentElement.innerHTML=\'<div class=\\\'announce-card__img-placeholder\\\'><i class=\\\'fa-regular fa-image\\\'></i></div>\'">'
                                        : '<div class="announce-card__img-placeholder"><i class="fa-regular fa-image"></i></div>';
                                ?>
                                    <a href="/annonces/<?= $a['id'] ?>"
                                        class="announce-card <?= $isClosed ? 'announce-card--closed' : '' ?>">
                                        <div class="announce-card__img">
                                            <?= $imgHtml ?>
                                            <?php if ($isClosed): ?>
                                                <span class="announce-card__closed-badge">Clôturée</span>
                                            <?php endif; ?>
                                            <?php
                                            $cats = $a['categories'] ?? [];
                                            if (!empty($cats)):
                                            ?>
                                                <div class="announce-card__cat-badges">
                                                    <span class="announce-card__cat-badge">
                                                        <?= htmlspecialchars($cats[0]['name']) ?>
                                                    </span>
                                                    <?php if (count($cats) > 1):
                                                        $extraNames = implode(', ', array_map(fn($c) => htmlspecialchars($c['name']), array_slice($cats, 1)));
                                                    ?>
                                                        <span class="announce-card__cat-more"
                                                            data-cats="<?= $extraNames ?>"
                                                            onmouseenter="showCatTooltip(this)"
                                                            onmouseleave="hideCatTooltip()">
                                                            +<?= count($cats) - 1 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="announce-card__body">
                                            <h3 class="announce-card__title">
                                                <?= htmlspecialchars($a['title']) ?>
                                            </h3>
                                            <?php if (!empty($a['description'])): ?>
                                                <p class="announce-card__description">
                                                    <?= htmlspecialchars(mb_strimwidth(strip_tags($a['description']), 0, 90, '…')) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?= $priceHtml ?>
                                            <div class="announce-card__meta">
                                                <span class="announce-card__city">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                    <?= htmlspecialchars($a['city']['name']) ?>
                                                </span>
                                                <span class="announce-card__date">
                                                    <i class="fa-regular fa-clock"></i>
                                                    <?= date('d/m/Y', strtotime($a['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($myTotalPages > 1): ?>
                                <nav class="pagination-nav mt-4">
                                    <?php for ($p = 1; $p <= $myTotalPages; $p++): ?>
                                        <a href="/profil?tab=annonces&page=<?= $p ?>"
                                            class="pagination-btn <?= $p === $myPage ? 'active' : '' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                <?php else: /* ════ ONGLET : MON PROFIL ════ */ ?>

                    <!-- ── Section : Informations publiques ── -->
                    <div class="profile-card">
                        <div class="profile-card__header">
                            <h2 class="profile-card__title">
                                <i class="fa-regular fa-user"></i>
                                Informations publiques
                            </h2>
                            <p class="profile-card__subtitle">
                                Ces informations sont visibles par les autres utilisateurs.
                            </p>
                        </div>

                        <?php if ($infoError): ?>
                            <div class="alert alert-danger">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($infoError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/profil">
                            <input type="hidden" name="action_info" value="1">

                            <!-- Nom d'utilisateur (lecture seule) -->
                            <div class="mb-3">
                                <label class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($me['name']) ?>"
                                    disabled>
                                <p class="form-text">Le nom d'utilisateur ne peut pas être modifié.</p>
                            </div>

                            <!-- Nom affiché -->
                            <div class="mb-3">
                                <label for="display_name" class="form-label">Nom affiché</label>
                                <input
                                    type="text"
                                    id="display_name"
                                    name="display_name"
                                    class="form-control"
                                    placeholder="Ex : Marie D."
                                    maxlength="16"
                                    value="<?= htmlspecialchars($displayName) ?>"
                                    autocomplete="off">
                                <p class="form-text">Optionnel · 16 caractères max · Affiché à la place de votre identifiant.</p>
                            </div>

                            <!-- Biographie -->
                            <div class="mb-4">
                                <label for="biography" class="form-label">Biographie</label>
                                <textarea
                                    id="biography"
                                    name="biography"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Dites quelques mots sur vous…"><?= htmlspecialchars($biography) ?></textarea>
                                <p class="form-text">Optionnel · 1000 caractères max.</p>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Enregistrer
                            </button>
                        </form>
                    </div>

                    <!-- ── Section : Changer le mot de passe ── -->
                    <div class="profile-card">
                        <div class="profile-card__header">
                            <h2 class="profile-card__title">
                                <i class="fa-solid fa-lock"></i>
                                Mot de passe
                            </h2>
                            <p class="profile-card__subtitle">
                                Choisissez un mot de passe solide d'au moins 6 caractères.
                            </p>
                        </div>

                        <?php if ($pwdError): ?>
                            <div class="alert alert-danger">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($pwdError) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/profil" autocomplete="off">
                            <input type="hidden" name="action_password" value="1">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <div class="pwd-field">
                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        class="form-control"
                                        placeholder="••••••••"
                                        autocomplete="current-password">
                                    <button type="button" class="pwd-toggle" data-target="current_password" tabindex="-1">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <div class="pwd-field">
                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        class="form-control"
                                        placeholder="••••••••"
                                        autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" data-target="new_password" tabindex="-1">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                <div class="pwd-field">
                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="form-control"
                                        placeholder="••••••••"
                                        autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" data-target="confirm_password" tabindex="-1">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-key"></i>
                                Changer le mot de passe
                            </button>
                        </form>
                    </div>

                <?php endif; /* fin onglets */ ?>

            </div><!-- /.profile-main -->

            <!-- ══════════════════════════════════════════
                 SIDEBAR
            ══════════════════════════════════════════ -->
            <aside class="profile-sidebar">

                <!-- Carte identité -->
                <div class="sidebar-card profile-identity-card">
                    <div class="profile-identity__avatar">
                        <?= htmlspecialchars(profileInitials($me)) ?>
                    </div>
                    <p class="profile-identity__name">
                        <?= htmlspecialchars($me['display_name'] ?? $me['name']) ?>
                    </p>
                    <p class="profile-identity__handle">@<?= htmlspecialchars($me['name']) ?></p>

                    <?php if (!empty($me['biography'])): ?>
                        <p class="profile-identity__bio">
                            <?= htmlspecialchars($me['biography']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="profile-identity__meta">
                        <span>
                            <i class="fa-regular fa-calendar"></i>
                            Membre depuis le <?= $memberSince ?>
                        </span>
                        <?php if ($me['role'] === 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div class="sidebar-card">
                    <p class="sidebar-card__title">Actions rapides</p>
                    <div class="profile-quick-links">
                        <a href="/annonces/creer" class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus"></i>
                            Déposer une annonce
                        </a>
                        <a href="/messages" class="btn btn-outline-dark w-100">
                            <i class="fa-regular fa-comment-dots"></i>
                            Messagerie
                        </a>
                        <a href="/deconnexion" class="btn btn-secondary w-100">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Se déconnecter
                        </a>
                    </div>
                </div>

            </aside>

        </div><!-- /.profile-layout -->

    </div>
</section>

<style>
    /* ── Onglets profil ── */
    .profile-tabs {
        display: flex;
        gap: var(--space-2);
        margin-bottom: var(--space-6);
        border-bottom: 2px solid var(--color-border);
    }

    .profile-tab {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3) var(--space-4);
        font-size: var(--text-sm);
        font-weight: 500;
        color: var(--color-text-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: color 0.15s, border-color 0.15s;
    }

    .profile-tab:hover {
        color: var(--color-text);
    }

    .profile-tab.active {
        color: var(--color-text);
        border-bottom-color: var(--color-text);
    }

    .profile-tab__count {
        background: var(--color-surface-2, #e5e7eb);
        border-radius: 999px;
        font-size: var(--text-xs);
        padding: 1px 7px;
        font-weight: 600;
    }

    /* ── Card annonce clôturée ── */
    .announce-card--closed {
        opacity: 0.65;
    }

    .announce-card__closed-badge {
        position: absolute;
        bottom: var(--space-2);
        left: var(--space-2);
        background: rgba(0, 0, 0, .6);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
    }
</style>

<script>
    // ── Toggle visibilité mots de passe ──
    document.querySelectorAll('.pwd-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ── Compteur biographie ──
    (function() {
        const bio = document.getElementById('biography');
        const hint = bio?.closest('.mb-4')?.querySelector('.form-text');
        if (!bio || !hint) return;

        function updateCount() {
            hint.textContent = `Optionnel · ${bio.value.length}/1000 caractères.`;
            hint.style.color = (1000 - bio.value.length) < 50 ? 'var(--color-warning)' : '';
        }

        bio.addEventListener('input', updateCount);
        updateCount();
    })();

    // ── Compteur display_name ──
    (function() {
        const dn = document.getElementById('display_name');
        const hint = dn?.closest('.mb-3')?.querySelector('.form-text');
        if (!dn || !hint) return;

        function updateCount() {
            hint.textContent = `Optionnel · ${dn.value.length}/16 caractères · Affiché à la place de votre identifiant.`;
            hint.style.color = dn.value.length >= 16 ? 'var(--color-warning)' : '';
        }

        dn.addEventListener('input', updateCount);
        updateCount();
    })();
</script>