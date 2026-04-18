<?php
// ── Auth guard admin ──
if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Accès réservé aux administrateurs.', 'type' => 'danger'];
    header('Location: /');
    exit;
}

$api   = new ApiClient();
$token = Auth::getToken();

$tab   = in_array($_GET['tab'] ?? '', ['announces']) ? 'announces' : 'users';
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

$users     = [];
$announces = [];
$total     = 0;

if ($tab === 'users') {
    $res   = $api->get("/admin/users?page={$page}&limit={$limit}", $token);
    $users = $res['body']['data']  ?? [];
    $total = $res['body']['total'] ?? 0;
} else {
    $res       = $api->get("/admin/announces?page={$page}&limit={$limit}", $token);
    $announces = $res['body']['data']  ?? [];
    $total     = $res['body']['total'] ?? 0;
}

$totalPages = max(1, (int)ceil($total / $limit));

function adminPaginationUrl(string $tab, int $page): string
{
    return '/admin?tab=' . urlencode($tab) . '&page=' . $page;
}
function adminDate(string $dt): string
{
    return date('d/m/Y', strtotime($dt));
}
?>

<section class="admin-section">
    <div class="container">

        <div class="admin-header">
            <h1 class="admin-header__title">Administration</h1>
            <p class="admin-header__subtitle">Gestion des utilisateurs et des annonces</p>
        </div>

        <!-- Onglets -->
        <div class="admin-tabs">
            <a href="/admin?tab=users" class="admin-tab <?= $tab === 'users' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> Utilisateurs
                <?php if ($tab === 'users'): ?><span class="admin-tab__count"><?= number_format($total) ?></span><?php endif; ?>
            </a>
            <a href="/admin?tab=announces" class="admin-tab <?= $tab === 'announces' ? 'active' : '' ?>">
                <i class="fa-solid fa-rectangle-list"></i> Annonces
                <?php if ($tab === 'announces'): ?><span class="admin-tab__count"><?= number_format($total) ?></span><?php endif; ?>
            </a>
        </div>

        <!-- ══ UTILISATEURS ══ -->
        <?php if ($tab === 'users'): ?>
            <div class="admin-table-wrap">
                <?php if (empty($users)): ?>
                    <div class="admin-empty"><i class="fa-regular fa-face-meh"></i>
                        <p>Aucun utilisateur trouvé.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="col-id">#</th>
                                <th>Utilisateur</th>
                                <th class="col-role">Rôle</th>
                                <th class="col-status">Statut</th>
                                <th class="col-date">Inscription</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u):
                                $isSelf  = (int)$u['id'] === (int)(Auth::getUser()['id'] ?? 0);
                                $isAdmin = $u['role'] === 'admin';
                                $banned  = (bool)$u['banned'];
                            ?>
                                <tr id="user-row-<?= (int)$u['id'] ?>" class="<?= $banned ? 'row-banned' : '' ?> <?= $isSelf ? 'row-self' : '' ?>">
                                    <td class="col-id text-muted"><?= (int)$u['id'] ?></td>
                                    <td>
                                        <div class="admin-user-cell">
                                            <span class="admin-user-cell__name"><?= htmlspecialchars($u['display_name'] ?? $u['name']) ?></span>
                                            <span class="admin-user-cell__handle">@<?= htmlspecialchars($u['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="col-role">
                                        <span class="badge <?= $isAdmin ? 'bg-danger' : 'bg-secondary' ?>"><?= $isAdmin ? 'Admin' : 'Membre' ?></span>
                                    </td>
                                    <td class="col-status">
                                        <span class="user-status-badge <?= $banned ? 'banned' : 'active' ?>"><?= $banned ? 'Banni' : 'Actif' ?></span>
                                    </td>
                                    <td class="col-date"><?= adminDate($u['created_at']) ?></td>
                                    <td class="col-actions">
                                        <?php if ($isSelf || $isAdmin): ?>
                                            <span class="text-muted" style="font-size:var(--text-xs)">—</span>
                                        <?php else: ?>
                                            <button class="btn btn-sm <?= $banned ? 'btn-success' : 'btn-warning' ?> btn-ban-toggle"
                                                data-id="<?= (int)$u['id'] ?>"
                                                data-banned="<?= $banned ? '1' : '0' ?>"
                                                data-name="<?= htmlspecialchars($u['name']) ?>">
                                                <i class="fa-solid <?= $banned ? 'fa-unlock' : 'fa-ban' ?>"></i>
                                                <?= $banned ? 'Débannir' : 'Bannir' ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ══ ANNONCES ══ -->
        <?php if ($tab === 'announces'): ?>
            <div class="admin-table-wrap">
                <?php if (empty($announces)): ?>
                    <div class="admin-empty"><i class="fa-regular fa-face-meh"></i>
                        <p>Aucune annonce trouvée.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="col-id">#</th>
                                <th>Titre</th>
                                <th class="col-role">Type</th>
                                <th class="col-status">Statut</th>
                                <th class="col-author">Auteur</th>
                                <th class="col-date">Date</th>
                                <th class="col-actions-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($announces as $a):
                                $closed = $a['status'] === 'closed';
                            ?>
                                <tr id="announce-row-<?= (int)$a['id'] ?>" class="<?= $closed ? 'row-closed' : '' ?>">
                                    <td class="col-id text-muted"><?= (int)$a['id'] ?></td>
                                    <td>
                                        <a href="/annonces/<?= (int)$a['id'] ?>" class="admin-announce-title" target="_blank" rel="noopener">
                                            <?= htmlspecialchars(mb_substr($a['title'], 0, 52)) ?><?= mb_strlen($a['title']) > 52 ? '…' : '' ?>
                                        </a>
                                    </td>
                                    <td class="col-role">
                                        <span class="badge <?= $a['type'] === 'offer' ? 'badge-offer' : 'badge-request' ?>">
                                            <?= $a['type'] === 'offer' ? 'Offre' : 'Demande' ?>
                                        </span>
                                    </td>
                                    <td class="col-status">
                                        <span class="announce-status-badge <?= $closed ? 'closed' : 'open' ?>"><?= $closed ? 'Clôturée' : 'Active' ?></span>
                                    </td>
                                    <td class="col-author"><span class="text-muted">@<?= htmlspecialchars($a['author']['name']) ?></span></td>
                                    <td class="col-date"><?= adminDate($a['created_at']) ?></td>
                                    <td class="col-actions-wide">
                                        <div class="admin-announce-actions">
                                            <?php if (!$closed): ?>
                                                <button class="btn btn-sm btn-warning btn-toggle-announce"
                                                    data-id="<?= (int)$a['id'] ?>" data-closed="0">
                                                    <i class="fa-solid fa-lock"></i> Clôturer
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success btn-toggle-announce"
                                                    data-id="<?= (int)$a['id'] ?>" data-closed="1">
                                                    <i class="fa-solid fa-lock-open"></i> Réouvrir
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger btn-delete-announce"
                                                data-id="<?= (int)$a['id'] ?>"
                                                data-title="<?= htmlspecialchars($a['title']) ?>">
                                                <i class="fa-solid fa-trash"></i> Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= adminPaginationUrl($tab, $page - 1) ?>" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-chevron-left"></i> Préc.
                    </a>
                <?php endif; ?>
                <span class="admin-pagination__info">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= adminPaginationUrl($tab, $page + 1) ?>" class="btn btn-secondary btn-sm">
                        Suiv. <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- Modale de confirmation -->
<div id="confirm-modal" class="admin-modal-overlay" aria-hidden="true">
    <div class="admin-modal">
        <p class="admin-modal__title">
            <i id="confirm-icon" class="fa-solid fa-triangle-exclamation"></i>
            <span id="confirm-title">Confirmer l'action</span>
        </p>
        <p class="admin-modal__body" id="confirm-body"></p>
        <div class="admin-modal__actions">
            <button id="confirm-cancel" class="btn btn-secondary">Annuler</button>
            <button id="confirm-ok" class="btn btn-danger">Confirmer</button>
        </div>
    </div>
</div>

<script>
    (function() {

        // ── Modale générique ──
        const modal = document.getElementById('confirm-modal');
        const titleEl = document.getElementById('confirm-title');
        const iconEl = document.getElementById('confirm-icon');
        const bodyEl = document.getElementById('confirm-body');
        const cancelBtn = document.getElementById('confirm-cancel');
        const confirmBtn = document.getElementById('confirm-ok');
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

        // ── BAN / UNBAN ──
        document.querySelectorAll('.btn-ban-toggle').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const banned = btn.dataset.banned === '1';
                const name = btn.dataset.name;
                const action = banned ? 'unban' : 'ban';

                const ok = await openConfirm({
                    title: banned ? 'Débannir l\'utilisateur' : 'Bannir l\'utilisateur',
                    message: `Voulez-vous vraiment ${banned ? 'débannir' : 'bannir'} @${name} ?`,
                    confirmLabel: banned ? 'Débannir' : 'Bannir',
                    confirmClass: banned ? 'btn-success' : 'btn-warning',
                    icon: banned ? 'fa-unlock' : 'fa-ban',
                });
                if (!ok) return;

                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                try {
                    const res = await fetch('/action/admin-user-toggle', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: id,
                            action
                        }),
                    });
                    const data = await res.json();

                    if (res.ok) {
                        const newBanned = action === 'ban';
                        const row = document.getElementById('user-row-' + id);
                        row.querySelector('.user-status-badge').textContent = newBanned ? 'Banni' : 'Actif';
                        row.querySelector('.user-status-badge').className = 'user-status-badge ' + (newBanned ? 'banned' : 'active');
                        row.classList.toggle('row-banned', newBanned);
                        btn.dataset.banned = newBanned ? '1' : '0';
                        btn.className = 'btn btn-sm ' + (newBanned ? 'btn-success' : 'btn-warning') + ' btn-ban-toggle';
                        btn.innerHTML = `<i class="fa-solid ${newBanned ? 'fa-unlock' : 'fa-ban'}"></i> ${newBanned ? 'Débannir' : 'Bannir'}`;
                        btn.disabled = false;
                        showToast(data.message ?? 'Action effectuée.', 'success');
                    } else {
                        showToast(data.error?.message ?? 'Erreur.', 'danger');
                        btn.disabled = false;
                        btn.innerHTML = `<i class="fa-solid ${banned ? 'fa-unlock' : 'fa-ban'}"></i> ${banned ? 'Débannir' : 'Bannir'}`;
                    }
                } catch {
                    showToast('Erreur réseau.', 'danger');
                    btn.disabled = false;
                }
            });
        });

        // ── CLÔTURER / RÉOUVRIR ANNONCE (bouton unique qui bascule) ──
        document.querySelectorAll('.btn-toggle-announce').forEach(btn => attachToggle(btn));

        function attachToggle(btn) {
            btn.addEventListener('click', async function handler() {
                const id = btn.dataset.id;
                const closed = btn.dataset.closed === '1';

                const ok = await openConfirm(closed ? {
                    title: 'Réouvrir l\'annonce',
                    message: 'Réouvrir cette annonce ? Elle redeviendra visible dans les recherches.',
                    confirmLabel: 'Réouvrir',
                    confirmClass: 'btn-success',
                    icon: 'fa-lock-open',
                } : {
                    title: 'Clôturer l\'annonce',
                    message: 'Clôturer cette annonce ? Elle ne sera plus visible dans les recherches. Vous pourrez la réouvrir ensuite.',
                    confirmLabel: 'Clôturer',
                    confirmClass: 'btn-warning',
                    icon: 'fa-lock',
                });
                if (!ok) return;

                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                const endpoint = closed ? '/action/admin-reopen-announce' : '/action/admin-close-announce';

                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            announce_id: id
                        }),
                    });
                    const data = await res.json();

                    if (res.ok) {
                        const nowClosed = !closed;
                        const row = document.getElementById('announce-row-' + id);
                        row.classList.toggle('row-closed', nowClosed);
                        row.querySelector('.announce-status-badge').textContent = nowClosed ? 'Clôturée' : 'Active';
                        row.querySelector('.announce-status-badge').className = 'announce-status-badge ' + (nowClosed ? 'closed' : 'open');

                        btn.dataset.closed = nowClosed ? '1' : '0';
                        btn.className = 'btn btn-sm ' + (nowClosed ? 'btn-success' : 'btn-warning') + ' btn-toggle-announce';
                        btn.innerHTML = nowClosed ?
                            '<i class="fa-solid fa-lock-open"></i> Réouvrir' :
                            '<i class="fa-solid fa-lock"></i> Clôturer';
                        btn.disabled = false;

                        // Réattache le handler (once ne suffit pas ici, on re-bind)
                        btn.removeEventListener('click', handler);
                        attachToggle(btn);

                        showToast(nowClosed ? 'Annonce clôturée.' : 'Annonce réouverte.', 'success');
                    } else {
                        showToast(data.error?.message ?? 'Erreur.', 'danger');
                        btn.disabled = false;
                        btn.innerHTML = closed ?
                            '<i class="fa-solid fa-lock-open"></i> Réouvrir' :
                            '<i class="fa-solid fa-lock"></i> Clôturer';
                    }
                } catch {
                    showToast('Erreur réseau.', 'danger');
                    btn.disabled = false;
                }
            }, {
                once: true
            });
        }

        // ── SUPPRIMER ANNONCE ──
        document.querySelectorAll('.btn-delete-announce').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const title = btn.dataset.title;
                const ok = await openConfirm({
                    title: 'Supprimer l\'annonce',
                    message: `Supprimer définitivement « ${title} » ? Cette action est irréversible.`,
                    confirmLabel: 'Supprimer',
                    confirmClass: 'btn-danger',
                    icon: 'fa-trash',
                });
                if (!ok) return;

                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                try {
                    const res = await fetch('/action/admin-delete-announce', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            announce_id: id
                        }),
                    });

                    if (res.status === 204 || res.ok) {
                        const row = document.getElementById('announce-row-' + id);
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(8px)';
                        setTimeout(() => row.remove(), 300);
                        showToast('Annonce supprimée.', 'success');
                    } else {
                        const data = await res.json().catch(() => ({}));
                        showToast(data.error?.message ?? 'Erreur.', 'danger');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Supprimer';
                    }
                } catch {
                    showToast('Erreur réseau.', 'danger');
                    btn.disabled = false;
                }
            });
        });

    })();
</script>