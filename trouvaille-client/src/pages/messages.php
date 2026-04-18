<?php
// ── Auth guard ──
if (!Auth::isLoggedIn()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Connectez-vous pour accéder à votre messagerie.', 'type' => 'warning'];
    header('Location: /connexion');
    exit;
}

$api   = new ApiClient();
$token = Auth::getToken();
$me    = Auth::getUser();

$resp          = $api->get('/messages/conversations', $token);
$conversations = [];

if ($resp['status'] === 200) {
    $conversations = $resp['body']['data'] ?? [];
}

// ── Helpers ──
function msgTimeLabel(string $datetime): string
{
    $ts   = strtotime($datetime);
    $now  = time();
    $diff = $now - $ts;

    if ($diff < 60)            return 'À l\'instant';
    if ($diff < 3600)          return floor($diff / 60) . ' min';
    if ($diff < 86400)         return date('H:i', $ts);
    if ($diff < 86400 * 7)    return ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][date('w', $ts)];
    return date('d/m/Y', $ts);
}

function avatarInitials(string $name): string
{
    $parts = preg_split('/[\s_\-]+/', trim($name));
    $init  = '';
    foreach ($parts as $p) {
        if ($p !== '') $init .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($init) >= 2) break;
    }
    return $init ?: '?';
}
?>

<section class="messages-section">
    <div class="container">

        <div class="messages-page">

            <!-- En-tête -->
            <div class="messages-page__header">
                <div>
                    <h1 class="messages-page__title">Messagerie</h1>
                    <p class="messages-page__subtitle">
                        <?php if (count($conversations) > 0): ?>
                            <?= count($conversations) ?> conversation<?= count($conversations) > 1 ? 's' : '' ?>
                        <?php else: ?>
                            Aucune conversation pour l'instant
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Liste ou état vide -->
            <?php if (empty($conversations)): ?>

                <div class="messages-empty">
                    <div class="messages-empty__icon">
                        <i class="fa-regular fa-comment-dots"></i>
                    </div>
                    <p class="messages-empty__title">Votre boîte est vide</p>
                    <p class="messages-empty__text">
                        Contactez un vendeur depuis la page d'une annonce pour démarrer une conversation.
                    </p>
                    <a href="/" class="btn btn-outline-dark">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Parcourir les annonces
                    </a>
                </div>

            <?php else: ?>

                <div class="conversations-list">
                    <?php
                    /*
 * PATCH : messages.php
 *
 * Rendre l'avatar + le nom de l'interlocuteur cliquables → profil public
 * tout en conservant la navigation vers la conversation.
 *
 * On transforme le <a class="conversation-item"> en <div>
 * et on ajoute un lien séparé sur l'avatar.
 *
 * REMPLACER dans la boucle foreach :
 */

                    /*
AVANT :
                    <?php foreach ($conversations as $conv):
                        $other   = $conv['interlocutor'];
                        $last    = $conv['last_message'];
                        $initials = avatarInitials($other['display_name'] ?? $other['name']);
                        $timeLabel = msgTimeLabel($last['created_at']);
                        $preview  = $last['is_mine'] ? 'Vous : ' . $last['content'] : $last['content'];
                    ?>
                        <a href="/messages/<?= (int)$other['id'] ?>" class="conversation-item">

                            <!-- Avatar -->
                            <div class="conversation-item__avatar">
                                <?= htmlspecialchars($initials) ?>
                            </div>

                            <!-- Contenu -->
                            <div class="conversation-item__body">
                                <div class="conversation-item__top">
                                    <span class="conversation-item__name">
                                        <?= htmlspecialchars($other['display_name'] ?? $other['name']) ?>
                                    </span>
                                    <span class="conversation-item__time"><?= htmlspecialchars($timeLabel) ?></span>
                                </div>
                                <p class="conversation-item__preview">
                                    <?= htmlspecialchars(mb_substr($preview, 0, 90)) ?><?= mb_strlen($preview) > 90 ? '…' : '' ?>
                                </p>
                            </div>

                            <!-- Chevron -->
                            <div class="conversation-item__arrow">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>

                        </a>
                    <?php endforeach; ?>
*/

                    // APRÈS :
                    ?>
                    <?php foreach ($conversations as $conv):
                        $other     = $conv['interlocutor'];
                        $last      = $conv['last_message'];
                        $initials  = avatarInitials($other['display_name'] ?? $other['name']);
                        $timeLabel = msgTimeLabel($last['created_at']);
                        $preview   = $last['is_mine'] ? 'Vous : ' . $last['content'] : $last['content'];
                        $convUrl   = '/messages/' . (int)$other['id'];
                        $profUrl   = '/profil/' . rawurlencode($other['name'] ?? '');
                    ?>
                        <div class="conversation-item" role="link"
                            onclick="window.location='<?= $convUrl ?>'"
                            style="cursor:pointer;">

                            <!-- Avatar → profil public -->
                            <a href="<?= $profUrl ?>"
                                class="conversation-item__avatar"
                                onclick="event.stopPropagation()"
                                title="Voir le profil de <?= htmlspecialchars($other['display_name'] ?? $other['name']) ?>">
                                <?= htmlspecialchars($initials) ?>
                            </a>

                            <!-- Contenu → conversation -->
                            <div class="conversation-item__body">
                                <div class="conversation-item__top">
                                    <a href="<?= $profUrl ?>"
                                        class="conversation-item__name"
                                        onclick="event.stopPropagation()"
                                        style="text-decoration:none;color:inherit;">
                                        <?= htmlspecialchars($other['display_name'] ?? $other['name']) ?>
                                    </a>
                                    <span class="conversation-item__time"><?= htmlspecialchars($timeLabel) ?></span>
                                </div>
                                <p class="conversation-item__preview">
                                    <?= htmlspecialchars(mb_substr($preview, 0, 90)) ?><?= mb_strlen($preview) > 90 ? '…' : '' ?>
                                </p>
                            </div>

                            <!-- Chevron -->
                            <div class="conversation-item__arrow">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div><!-- /.messages-page -->

    </div>
</section>