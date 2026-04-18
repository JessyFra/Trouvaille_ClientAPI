<?php

/**
 * Page : Profil public d'un utilisateur
 * Route : /profil/{username}
 * $routeParam = username (string)
 */

$api      = new ApiClient();
$username = $routeParam ?? '';

if (!$username) {
    header('Location: /');
    exit;
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;

$res = $api->get('/users/' . rawurlencode($username) . '?' . http_build_query([
    'page'  => $page,
    'limit' => $limit,
]));

if ($res['status'] === 404) {
    http_response_code(404);
    echo '<div class="container py-5 text-center"><h2>Utilisateur introuvable.</h2><a href="/" class="btn btn-outline-dark mt-3">Retour aux annonces</a></div>';
    return;
}

$userData   = $res['body']['user']              ?? [];
$announces  = $res['body']['announces']['data']  ?? [];
$total      = $res['body']['announces']['total'] ?? 0;
$totalPages = (int) ceil($total / $limit);

$displayName = $userData['display_name'] ?? $userData['name'] ?? '?';
$memberSince = date('d/m/Y', strtotime($userData['created_at'] ?? 'now'));

// Initiales avatar
$parts = preg_split('/[\s_\-]+/', trim($displayName));
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
    if (mb_strlen($initials) >= 2) break;
}
$initials = $initials ?: '?';

// Savoir si c'est son propre profil
$isSelf = Auth::isLoggedIn() && (Auth::getUser()['name'] ?? '') === ($userData['name'] ?? '');
if ($isSelf) {
    header('Location: /profil');
    exit;
}

// Helper card annonce (réutilise le même markup que home.php)
function renderPublicCard(array $a): string
{
    $imgSrc = $a['main_image_thumb'] ?? $a['main_image'] ?? null;

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

    $cats     = $a['categories'] ?? [];
    $catBadge = '';
    if (!empty($cats)) {
        $catBadge = '<div class="announce-card__cat-badges">';
        $catBadge .= '<span class="announce-card__cat-badge">' . htmlspecialchars($cats[0]['name']) . '</span>';
        if (count($cats) > 1) {
            $extraNames = implode(', ', array_map(fn($c) => htmlspecialchars($c['name']), array_slice($cats, 1)));
            $catBadge .= '<span class="announce-card__cat-more" data-cats="' . $extraNames . '"
                onmouseenter="showCatTooltip(this)" onmouseleave="hideCatTooltip()">+' . (count($cats) - 1) . '</span>';
        }
        $catBadge .= '</div>';
    }

    $desc = htmlspecialchars(mb_strimwidth(strip_tags($a['description'] ?? ''), 0, 90, '…'));
    $date = date('d/m/Y', strtotime($a['created_at']));

    return '
    <a href="/annonces/' . $a['id'] . '" class="announce-card">
        <div class="announce-card__img">' . $imgHtml . $catBadge . '</div>
        <div class="announce-card__body">
            <h3 class="announce-card__title">' . htmlspecialchars($a['title']) . '</h3>
            ' . ($desc ? '<p class="announce-card__description">' . $desc . '</p>' : '') . '
            ' . $priceHtml . '
            <div class="announce-card__meta">
                <span class="announce-card__city"><i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($a['city']['name']) . '</span>
                <span class="announce-card__date"><i class="fa-regular fa-clock"></i> ' . $date . '</span>
            </div>
        </div>
    </a>';
}
?>

<section class="profile-section">
    <div class="container">
        <div class="profile-layout">

            <!-- ══ COLONNE PRINCIPALE ══ -->
            <div class="profile-main">

                <div class="profile-card">
                    <div class="profile-card__header">
                        <h2 class="profile-card__title">
                            <i class="fa-solid fa-rectangle-list"></i>
                            Annonces de <?= htmlspecialchars($displayName) ?>
                        </h2>
                        <p class="profile-card__subtitle">
                            <?= $total ?> annonce<?= $total > 1 ? 's' : '' ?> publiée<?= $total > 1 ? 's' : '' ?>
                        </p>
                    </div>

                    <?php if (empty($announces)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-regular fa-folder-open fa-2x mb-3"></i>
                            <p>Cet utilisateur n'a publié aucune annonce.</p>
                        </div>
                    <?php else: ?>
                        <div class="announces-grid">
                            <?php foreach ($announces as $a): ?>
                                <?= renderPublicCard($a) ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="pagination-nav mt-4">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="/profil/<?= htmlspecialchars($username) ?>?page=<?= $p ?>"
                                        class="pagination-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ══ SIDEBAR ══ -->
            <aside class="profile-sidebar">

                <!-- Identité -->
                <div class="profile-identity">
                    <div class="profile-identity__avatar"><?= htmlspecialchars($initials) ?></div>
                    <h1 class="profile-identity__name"><?= htmlspecialchars($displayName) ?></h1>
                    <p class="profile-identity__handle">@<?= htmlspecialchars($userData['name'] ?? '') ?></p>

                    <?php if (!empty($userData['biography'])): ?>
                        <p class="profile-identity__bio"><?= htmlspecialchars($userData['biography']) ?></p>
                    <?php endif; ?>

                    <div class="profile-identity__meta">
                        <span>
                            <i class="fa-regular fa-calendar"></i>
                            Membre depuis le <?= $memberSince ?>
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <?php if (Auth::isLoggedIn()): ?>
                    <div class="sidebar-card">
                        <p class="sidebar-card__title">Actions</p>
                        <div class="profile-quick-links">
                            <a href="/messages" class="btn btn-outline-dark w-100"
                                onclick="sessionStorage.setItem('startConvWith', '<?= (int)($userData['id'] ?? 0) ?>')">
                                <i class="fa-regular fa-comment-dots"></i>
                                Envoyer un message
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>

        </div>
    </div>
</section>