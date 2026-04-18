<?php
$api = new ApiClient();

$cityId     = (int)($_GET['city_id']     ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$type       = $_GET['type']   ?? '';
$search     = trim($_GET['search'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$query = http_build_query(array_filter([
    'city_id'     => $cityId     ?: null,
    'category_id' => $categoryId ?: null,
    'type'        => $type       ?: null,
    'search'      => $search     ?: null,
    'page'        => $page,
    'limit'       => 40,
]));

$response   = $api->get('/announces?' . $query);
$announces  = $response['body']['data']  ?? [];
$total      = $response['body']['total'] ?? 0;
$totalPages = (int) ceil($total / 40);

$cities     = $api->get('/cities')['body']['data']     ?? [];
$categories = $api->get('/categories')['body']['data'] ?? [];

// Séparer offres / demandes quand pas de filtre de type
$offers   = [];
$requests = [];
if ($type === '') {
    foreach ($announces as $a) {
        if ($a['type'] === 'offer') $offers[] = $a;
        else $requests[] = $a;
    }
} else {
    $offers = $announces;
}

$categoryIcons = [
    'Informatique & High-Tech' => 'fa-laptop',
    'Mobilier & Décoration'    => 'fa-couch',
    'Vêtements & Accessoires'  => 'fa-shirt',
    'Véhicules'                => 'fa-car',
    'Immobilier'               => 'fa-house',
    'Sport & Loisirs'          => 'fa-futbol',
    'Jardin & Plantes'         => 'fa-seedling',
    'Livres & Médias'          => 'fa-book',
    'Électroménager'           => 'fa-blender',
    'Services'                 => 'fa-screwdriver-wrench',
];

// Helper pour rendre une card
function renderCard(array $a, array $categoryIcons): string
{
    $imgSrc = $a['main_image_thumb'] ?? $a['main_image'] ?? null;

    // Prix
    if ($a['type'] === 'request') {
        $priceHtml = '<span class="announce-card__price demand">Demande</span>';
    } elseif ($a['price'] > 0) {
        $formatted = number_format($a['price'], 0, ',', ' ') . ' €';
        $priceHtml = '<span class="announce-card__price">' . htmlspecialchars($formatted) . '</span>';
    } else {
        $priceHtml = '<span class="announce-card__price free">Gratuit</span>';
    }

    // Image
    $imgHtml = $imgSrc
        ? '<img src="' . htmlspecialchars($imgSrc) . '"
               alt="' . htmlspecialchars($a['title']) . '"
               loading="lazy"
               onerror="this.parentElement.innerHTML=\'<div class=\\\'announce-card__img-placeholder\\\'><i class=\\\'fa-regular fa-image\\\'></i></div>\'">'
        : '<div class="announce-card__img-placeholder"><i class="fa-regular fa-image"></i></div>';

    // Badge première catégorie + badge +N sur l'image
    $cats     = $a['categories'] ?? [];
    $catBadge = '';
    if (!empty($cats)) {
        $catBadge = '<div class="announce-card__cat-badges">';
        $catBadge .= '<span class="announce-card__cat-badge">'
            . htmlspecialchars($cats[0]['name'])
            . '</span>';
        if (count($cats) > 1) {
            // Liste des catégories extras encodée en data attribute
            $extraNames = implode(', ', array_map(
                fn($c) => htmlspecialchars($c['name']),
                array_slice($cats, 1)
            ));
            $catBadge .= '<span class="announce-card__cat-more"'
                . ' data-cats="' . $extraNames . '"'
                . ' onmouseenter="showCatTooltip(this)"'
                . ' onmouseleave="hideCatTooltip()">+'
                . (count($cats) - 1)
                . '</span>';
        }
        $catBadge .= '</div>';
    }

    // Description courte
    $desc = htmlspecialchars(mb_strimwidth(strip_tags($a['description'] ?? ''), 0, 90, '…'));
    $date = date('d/m/Y', strtotime($a['created_at']));

    return '
    <a href="/annonces/' . $a['id'] . '" class="announce-card">
        <div class="announce-card__img">
            ' . $imgHtml . '
            ' . $catBadge . '
        </div>
        <div class="announce-card__body">
            <h3 class="announce-card__title">' . htmlspecialchars($a['title']) . '</h3>
            ' . ($desc ? '<p class="announce-card__description">' . $desc . '</p>' : '') . '
            ' . $priceHtml . '
            <div class="announce-card__meta">
                <span class="announce-card__city">
                    <i class="fa-solid fa-location-dot"></i>
                    ' . htmlspecialchars($a['city']['name']) . '
                </span>
                <span class="announce-card__date">
                    <i class="fa-regular fa-clock"></i>
                    ' . $date . '
                </span>
            </div>
        </div>
    </a>';
}
?>

<!-- Barre de recherche -->
<section class="search-bar-section">
    <div class="container">
        <h1>Trouver une annonce</h1>
        <form method="GET" action="/" class="search-bar">
            <input type="text" name="search" class="form-control"
                placeholder="Que recherchez-vous ?"
                value="<?= htmlspecialchars($search) ?>">

            <select name="city_id" class="form-select">
                <option value="">Toutes les villes</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= $city['id'] ?>"
                        <?= $cityId === $city['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($city['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i> Rechercher
            </button>

            <?php if ($search || $cityId || $categoryId || $type): ?>
                <a href="/" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark"></i> Réinitialiser
                </a>
            <?php endif; ?>
        </form>
    </div>
</section>

<!-- Catégories -->
<section class="categories-section">
    <div class="container">
        <p class="categories-section__title">Catégories</p>
        <div class="categories-grid">
            <?php foreach ($categories as $cat):
                $icon     = $categoryIcons[$cat['name']] ?? 'fa-tag';
                $isActive = $categoryId === $cat['id'];
                $params   = http_build_query(array_merge($_GET, ['category_id' => $cat['id'], 'page' => 1]));
                $reset    = http_build_query(array_diff_key($_GET, ['category_id' => '', 'page' => '']));
            ?>
                <a href="/?<?= $isActive ? $reset : $params ?>"
                    class="category-chip <?= $isActive ? 'active' : '' ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Annonces -->
<section class="announces-section">
    <div class="container">

        <div class="announces-section__header">
            <span class="announces-section__heading">
                <?php if ($search || $cityId || $categoryId || $type): ?>
                    <?= $total ?> résultat<?= $total > 1 ? 's' : '' ?>
                <?php else: ?>
                    <?= $total ?> annonce<?= $total > 1 ? 's' : '' ?> en ligne
                <?php endif; ?>
            </span>

            <div class="announces-section__filters">
                <select class="form-select"
                    onchange="window.location='/?'+new URLSearchParams({...Object.fromEntries(new URLSearchParams(location.search)),...{type:this.value,page:1}}).toString()">
                    <option value="" <?= $type === '' ? 'selected' : '' ?>>Offres & Demandes</option>
                    <option value="offer" <?= $type === 'offer'   ? 'selected' : '' ?>>Offres uniquement</option>
                    <option value="request" <?= $type === 'request' ? 'selected' : '' ?>>Demandes uniquement</option>
                </select>
            </div>
        </div>

        <?php if (empty($announces)): ?>
            <div class="announces-empty">
                <i class="fa-regular fa-folder-open"></i>
                <p>Aucune annonce ne correspond à votre recherche.</p>
            </div>

        <?php elseif ($type !== ''): ?>
            <!-- Filtré sur un seul type : grille simple -->
            <div class="announces-grid">
                <?php foreach ($offers as $a): ?>
                    <?= renderCard($a, $categoryIcons) ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Mode mixte : offres puis demandes -->
            <div class="announces-grid">

                <?php if (!empty($offers)): ?>
                    <div class="announces-divider">
                        <span class="announces-divider__label">
                            <i class="fa-solid fa-tag"></i> Offres (<?= count($offers) ?>)
                        </span>
                        <span class="announces-divider__line"></span>
                    </div>
                    <?php foreach ($offers as $a): ?>
                        <?= renderCard($a, $categoryIcons) ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($requests)): ?>
                    <div class="announces-divider">
                        <span class="announces-divider__label">
                            <i class="fa-solid fa-hand-holding-heart"></i> Demandes (<?= count($requests) ?>)
                        </span>
                        <span class="announces-divider__line"></span>
                    </div>
                    <?php foreach ($requests as $a): ?>
                        <?= renderCard($a, $categoryIcons) ?>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-10 d-flex justify-content-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                        class="btn btn-outline-dark btn-sm">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                        class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-dark' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                        class="btn btn-outline-dark btn-sm">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </div>
</section>