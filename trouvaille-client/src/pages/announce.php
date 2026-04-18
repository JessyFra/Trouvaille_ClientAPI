<?php
if (!isset($routeParam)) {
    header('Location: /');
    exit;
}

$api      = new ApiClient();
$response = $api->get('/announces/' . $routeParam);

if ($response['status'] === 404) {
    header('Location: /');
    exit;
}

$a = $response['body'];

$contactSuccess = false;
$contactError   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_message'])) {
    if (!Auth::isLoggedIn()) {
        header('Location: /connexion');
        exit;
    }

    $content = trim($_POST['contact_message'] ?? '');

    if (strlen($content) < 2) {
        $contactError = 'Votre message est trop court.';
    } elseif (Auth::getUser()['id'] === $a['author']['id']) {
        $contactError = 'Vous ne pouvez pas vous écrire à vous-même.';
    } else {
        $send = $api->post(
            '/messages/' . $a['author']['id'],
            ['content' => $content],
            Auth::getToken()
        );
        if ($send['status'] === 201) {
            $contactSuccess = true;
        } else {
            $contactError = $send['body']['error']['message'] ?? 'Erreur lors de l\'envoi.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_close'])) {
    if (!Auth::isLoggedIn()) {
        header('Location: /connexion');
        exit;
    }
    $api->patch('/announces/' . $a['id'] . '/close', [], Auth::getToken());
    $_SESSION['flash_toasts'][] = ['msg' => 'Annonce clôturée.', 'type' => 'success'];
    header('Location: /annonces/' . $a['id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reopen'])) {
    if (!Auth::isLoggedIn()) {
        header('Location: /connexion');
        exit;
    }
    $api->patch('/announces/' . $a['id'] . '/reopen', [], Auth::getToken());
    $_SESSION['flash_toasts'][] = ['msg' => 'Annonce réouverte.', 'type' => 'success'];
    header('Location: /annonces/' . $a['id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    if (!Auth::isLoggedIn()) {
        header('Location: /connexion');
        exit;
    }
    $del = $api->delete('/announces/' . $a['id'], Auth::getToken());
    if ($del['status'] === 204) {
        $_SESSION['flash_toasts'][] = ['msg' => 'Annonce supprimée.', 'type' => 'success'];
        header('Location: /');
        exit;
    }
}

$isActualOwner = Auth::isLoggedIn() && Auth::getUser()['id'] === $a['author']['id'];

$isAdminOnOther = Auth::isLoggedIn() && Auth::isAdmin() && !$isActualOwner;

$isOwner = $isActualOwner || Auth::isAdmin();

$images  = $a['images'] ?? [];
$mainImg = null;
foreach ($images as $img) {
    if ($img['is_main']) {
        $mainImg = $img;
        break;
    }
}
if (!$mainImg && !empty($images)) $mainImg = $images[0];

if ($a['type'] === 'request') {
    $priceHtml = '<span class="announce-content__price demand">Demande</span>';
} elseif ($a['price'] > 0) {
    $formatted = number_format($a['price'], 0, ',', ' ') . ' €';
    $priceHtml = '<span class="announce-content__price">' . htmlspecialchars($formatted) . '</span>';
} else {
    $priceHtml = '<span class="announce-content__price free">Gratuit</span>';
}

$authorInitial = strtoupper(substr($a['author']['name'], 0, 1));
$authorProfileUrl = '/profil/' . rawurlencode($a['author']['name']);
$allUrls       = array_map(fn($i) => $i['url'], $images);
?>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightboxOnBackdrop(event)">
    <button class="lightbox-close" onclick="closeLightbox()">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <button class="lightbox-prev" onclick="lightboxNav(-1)">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
    <img src="" id="lightboxImg" alt="image agrandie">
    <button class="lightbox-next" onclick="lightboxNav(1)">
        <i class="fa-solid fa-chevron-right"></i>
    </button>
    <span class="lightbox-counter" id="lightboxCounter"></span>
</div>

<section class="announce-section">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="/">Annonces</a>
            <i class="fa-solid fa-chevron-right"></i>
            <?php if (!empty($a['categories'])): ?>
                <a href="/?category_id=<?= $a['categories'][0]['id'] ?>">
                    <?= htmlspecialchars($a['categories'][0]['name']) ?>
                </a>
                <i class="fa-solid fa-chevron-right"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars(mb_strimwidth($a['title'], 0, 40, '…')) ?></span>
        </nav>

        <?php if ($a['status'] === 'closed'): ?>
            <div class="alert alert-warning mb-6">
                <i class="fa-solid fa-lock"></i>
                Cette annonce est clôturée et n'est plus disponible.
            </div>
        <?php endif; ?>

        <div class="announce-layout">

            <!-- Colonne gauche -->
            <div>
                <!-- Galerie -->
                <div class="announce-gallery">
                    <div class="announce-gallery__main"
                        id="mainImgWrapper"
                        <?= $mainImg ? 'onclick="openLightbox(0)"' : '' ?>>
                        <?php if ($mainImg): ?>
                            <img src="<?= htmlspecialchars($mainImg['url']) ?>"
                                alt="<?= htmlspecialchars($a['title']) ?>"
                                id="mainImgEl">
                            <span class="announce-gallery__zoom-hint">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </span>
                        <?php else: ?>
                            <div class="announce-gallery__main-placeholder">
                                <i class="fa-regular fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="announce-gallery__thumbs">
                            <?php foreach ($images as $idx => $img): ?>
                                <div class="announce-gallery__thumb <?= $img['is_main'] ? 'active' : '' ?>"
                                    onclick="switchImg(this, '<?= htmlspecialchars($img['url']) ?>', <?= $idx ?>)">
                                    <img src="<?= htmlspecialchars($img['url']) ?>" alt="miniature">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Header annonce -->
                <div class="announce-content__header">
                    <div class="announce-content__badges">
                        <span class="badge <?= $a['type'] === 'offer' ? 'badge-offer' : 'badge-request' ?>">
                            <?= $a['type'] === 'offer' ? 'Offre' : 'Demande' ?>
                        </span>
                        <?php if ($a['status'] === 'closed'): ?>
                            <span class="badge badge-closed">Clôturée</span>
                        <?php endif; ?>
                        <?php foreach ($a['categories'] as $cat): ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($cat['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <h1 class="announce-content__title">
                        <?= htmlspecialchars($a['title']) ?>
                    </h1>
                    <?= $priceHtml ?>
                </div>

                <!-- Meta -->
                <div class="announce-content__meta">
                    <div class="announce-content__meta-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><strong><?= htmlspecialchars($a['city']['name']) ?></strong></span>
                    </div>
                    <div class="announce-content__meta-item">
                        <i class="fa-regular fa-clock"></i>
                        <span>Publiée le <strong><?= date('d/m/Y à H\hi', strtotime($a['created_at'])) ?></strong></span>
                    </div>
                    <div class="announce-content__meta-item">
                        <i class="fa-regular fa-user"></i>
                        <span>Par <a href="/profil/<?= htmlspecialchars($a['author']['name']) ?>"
                                style="color:inherit;font-weight:var(--weight-medium);text-decoration:none;"
                                onmouseover="this.style.textDecoration='underline'"
                                onmouseout="this.style.textDecoration='none'">
                                <strong><?= htmlspecialchars($a['author']['display_name'] ?? $a['author']['name']) ?></strong>
                            </a></span>
                    </div>
                </div>

                <!-- Description -->
                <?php if ($a['description']): ?>
                    <div class="announce-content__description">
                        <h2>Description</h2>
                        <p><?= nl2br(htmlspecialchars($a['description'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar sticky -->
            <aside class="announce-sidebar">

                <!-- Vendeur -->
                <div class="sidebar-card">
                    <p class="sidebar-card__title">Vendeur</p>
                    <a href="/profil/<?= htmlspecialchars($a['author']['name']) ?>" class="sidebar-author__link">
                        <div class="sidebar-author__avatar"><?= $authorInitial ?></div>
                        <div>
                            <div class="sidebar-author__name">
                                <?= htmlspecialchars($a['author']['display_name'] ?? $a['author']['name']) ?>
                            </div>
                            <div class="sidebar-author__since">
                                Voir le profil
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Contact / Actions -->
                <div class="sidebar-card">

                    <?php if ($isActualOwner): ?>
                        <!-- Propriétaire -->
                        <p class="sidebar-card__title">Gérer l'annonce</p>
                        <div class="sidebar-owner-actions">
                            <p>C'est votre annonce.</p>
                            <a href="/annonces/<?= $a['id'] ?>/modifier" class="btn btn-outline-dark w-100">
                                <i class="fa-solid fa-pen"></i> Modifier
                            </a>
                            <?php if ($a['status'] === 'open'): ?>
                                <form id="form-close" method="POST" action="/annonces/<?= $a['id'] ?>">
                                    <input type="hidden" name="action_close" value="1">
                                    <button type="button" class="btn btn-warning w-100" id="btn-close-announce">
                                        <i class="fa-solid fa-lock"></i> Clôturer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form id="form-delete" method="POST" action="/annonces/<?= $a['id'] ?>">
                                <input type="hidden" name="action_delete" value="1">
                                <button type="button" class="btn btn-danger w-100" id="btn-delete-announce">
                                    <i class="fa-solid fa-trash"></i> Supprimer
                                </button>
                            </form>
                        </div>

                    <?php elseif ($isAdminOnOther): ?>
                        <!-- Admin sur l'annonce d'un autre -->
                        <p class="sidebar-card__title">Administration</p>
                        <div class="sidebar-owner-actions">
                            <?php if ($a['status'] === 'open'): ?>
                                <form id="form-close" method="POST" action="/annonces/<?= $a['id'] ?>">
                                    <input type="hidden" name="action_close" value="1">
                                    <button type="button" class="btn btn-warning w-100" id="btn-close-announce">
                                        <i class="fa-solid fa-lock"></i> Clôturer
                                    </button>
                                </form>
                            <?php else: ?>
                                <form id="form-reopen" method="POST" action="/annonces/<?= $a['id'] ?>">
                                    <input type="hidden" name="action_reopen" value="1">
                                    <button type="button" class="btn btn-success w-100" id="btn-reopen-announce">
                                        <i class="fa-solid fa-lock-open"></i> Réouvrir
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form id="form-delete" method="POST" action="/annonces/<?= $a['id'] ?>">
                                <input type="hidden" name="action_delete" value="1">
                                <button type="button" class="btn btn-danger w-100" id="btn-delete-announce">
                                    <i class="fa-solid fa-trash"></i> Supprimer
                                </button>
                            </form>
                        </div>

                    <?php elseif (!Auth::isLoggedIn()): ?>
                        <p class="sidebar-card__title">Contacter le vendeur</p>
                        <p class="text-sm text-muted mb-3">Connectez-vous pour envoyer un message.</p>
                        <a href="/connexion" class="btn btn-primary w-100">
                            <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                        </a>

                    <?php elseif ($a['status'] === 'closed'): ?>
                        <p class="sidebar-card__title">Contacter le vendeur</p>
                        <p class="text-sm text-muted">Cette annonce est clôturée.</p>

                    <?php elseif ($contactSuccess): ?>
                        <p class="sidebar-card__title">Contacter le vendeur</p>
                        <div class="alert alert-success">
                            <i class="fa-solid fa-circle-check"></i> Message envoyé !
                        </div>
                        <a href="/messages/<?= $a['author']['id'] ?>" class="btn btn-outline-dark w-100 mt-2">
                            <i class="fa-regular fa-envelope"></i> Voir la conversation
                        </a>

                    <?php else: ?>
                        <p class="sidebar-card__title">Contacter le vendeur</p>
                        <?php if ($contactError): ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($contactError) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="/annonces/<?= $a['id'] ?>" class="contact-form">
                            <textarea name="contact_message" class="form-control" rows="4"
                                placeholder="Bonjour, votre annonce m'intéresse…"
                                required><?= htmlspecialchars($_POST['contact_message'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-paper-plane"></i> Envoyer
                            </button>
                        </form>

                    <?php endif; ?>

                </div>

                <?php if ($isOwner): ?>
                    <!-- Modale de confirmation -->
                    <div id="announce-confirm-modal" class="admin-modal-overlay" aria-hidden="true">
                        <div class="admin-modal">
                            <p class="admin-modal__title">
                                <i id="announce-confirm-icon" class="fa-solid fa-triangle-exclamation"></i>
                                <span id="announce-confirm-title"></span>
                            </p>
                            <p class="admin-modal__body" id="announce-confirm-body"></p>
                            <div class="admin-modal__actions">
                                <button id="announce-confirm-cancel" class="btn btn-secondary">Annuler</button>
                                <button id="announce-confirm-ok" class="btn btn-danger">Confirmer</button>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function() {
                            const modal = document.getElementById('announce-confirm-modal');
                            const titleEl = document.getElementById('announce-confirm-title');
                            const iconEl = document.getElementById('announce-confirm-icon');
                            const bodyEl = document.getElementById('announce-confirm-body');
                            const cancelBtn = document.getElementById('announce-confirm-cancel');
                            const confirmBtn = document.getElementById('announce-confirm-ok');
                            let _resolve = null;

                            function openConfirm({
                                title,
                                message,
                                confirmLabel = 'Confirmer',
                                confirmClass = 'btn-danger',
                                icon = 'fa-triangle-exclamation'
                            }) {
                                titleEl.textContent = title;
                                bodyEl.textContent = message;
                                confirmBtn.textContent = confirmLabel;
                                confirmBtn.className = 'btn ' + confirmClass;
                                iconEl.className = 'fa-solid ' + icon;
                                modal.classList.add('open');
                                modal.setAttribute('aria-hidden', 'false');
                                return new Promise(resolve => {
                                    _resolve = resolve;
                                });
                            }

                            function closeConfirm(val) {
                                modal.classList.remove('open');
                                modal.setAttribute('aria-hidden', 'true');
                                if (_resolve) {
                                    _resolve(val);
                                    _resolve = null;
                                }
                            }

                            cancelBtn.addEventListener('click', () => closeConfirm(false));
                            confirmBtn.addEventListener('click', () => closeConfirm(true));
                            modal.addEventListener('click', e => {
                                if (e.target === modal) closeConfirm(false);
                            });
                            document.addEventListener('keydown', e => {
                                if (e.key === 'Escape') closeConfirm(false);
                            });

                            // ── Bouton Clôturer ──
                            const btnClose = document.getElementById('btn-close-announce');
                            const formClose = document.getElementById('form-close');
                            if (btnClose && formClose) {
                                btnClose.addEventListener('click', async () => {
                                    const ok = await openConfirm({
                                        title: 'Clôturer l\'annonce',
                                        message: 'Clôturer cette annonce ? Elle ne sera plus visible dans les recherches.',
                                        confirmLabel: 'Clôturer',
                                        confirmClass: 'btn-warning',
                                        icon: 'fa-lock',
                                    });
                                    if (ok) formClose.submit();
                                });
                            }

                            // ── Bouton Réouvrir ──
                            const btnReopen = document.getElementById('btn-reopen-announce');
                            const formReopen = document.getElementById('form-reopen');
                            if (btnReopen && formReopen) {
                                btnReopen.addEventListener('click', async () => {
                                    const ok = await openConfirm({
                                        title: 'Réouvrir l\'annonce',
                                        message: 'Réouvrir cette annonce ? Elle redeviendra visible dans les recherches.',
                                        confirmLabel: 'Réouvrir',
                                        confirmClass: 'btn-success',
                                        icon: 'fa-lock-open',
                                    });
                                    if (ok) formReopen.submit();
                                });
                            }

                            // ── Bouton Supprimer ──
                            const btnDelete = document.getElementById('btn-delete-announce');
                            const formDelete = document.getElementById('form-delete');
                            if (btnDelete && formDelete) {
                                btnDelete.addEventListener('click', async () => {
                                    const ok = await openConfirm({
                                        title: 'Supprimer l\'annonce',
                                        message: 'Supprimer définitivement cette annonce ? Cette action est irréversible.',
                                        confirmLabel: 'Supprimer',
                                        confirmClass: 'btn-danger',
                                        icon: 'fa-trash',
                                    });
                                    if (ok) formDelete.submit();
                                });
                            }
                        })();
                    </script>
                <?php endif; ?>

            </aside>
        </div>
    </div>
</section>

<script>
    // ── Galerie ──
    const allUrls = <?= json_encode($allUrls) ?>;
    let currentIndex = 0;

    function switchImg(thumb, url, idx) {
        currentIndex = idx;
        const el = document.getElementById('mainImgEl');
        if (el) el.src = url;
        document.querySelectorAll('.announce-gallery__thumb')
            .forEach(t => t.classList.remove('active'));
        thumb.classList.add('active');
    }

    // ── Lightbox ──
    function openLightbox(idx) {
        if (!allUrls.length) return;
        currentIndex = idx;
        updateLightbox();
        document.getElementById('lightbox').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.body.style.overflow = '';
    }

    function closeLightboxOnBackdrop(e) {
        if (e.target === document.getElementById('lightbox')) closeLightbox();
    }

    function lightboxNav(dir) {
        currentIndex = (currentIndex + dir + allUrls.length) % allUrls.length;
        updateLightbox();
    }

    function updateLightbox() {
        document.getElementById('lightboxImg').src = allUrls[currentIndex];
        document.getElementById('lightboxCounter').textContent =
            allUrls.length > 1 ? (currentIndex + 1) + ' / ' + allUrls.length : '';

        const prev = document.querySelector('.lightbox-prev');
        const next = document.querySelector('.lightbox-next');
        if (allUrls.length <= 1) {
            prev.style.display = 'none';
            next.style.display = 'none';
        }
    }

    document.addEventListener('keydown', e => {
        const lb = document.getElementById('lightbox');
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') lightboxNav(-1);
        if (e.key === 'ArrowRight') lightboxNav(1);
    });
</script>