<?php
// ── Auth guard ──
if (!Auth::isLoggedIn()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Connectez-vous pour accéder à votre messagerie.', 'type' => 'warning'];
    header('Location: /connexion');
    exit;
}

$otherUserId = (int)($routeParam ?? 0);

if (!$otherUserId) {
    header('Location: /messages');
    exit;
}

$api   = new ApiClient();
$token = Auth::getToken();
$me    = Auth::getUser();

$resp = $api->get('/messages/conversations/' . $otherUserId, $token);

if ($resp['status'] === 404 || $resp['status'] === 400) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Conversation introuvable.', 'type' => 'danger'];
    header('Location: /messages');
    exit;
}

if ($resp['status'] !== 200) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Impossible de charger la conversation.', 'type' => 'danger'];
    header('Location: /messages');
    exit;
}

$thread       = $resp['body'];
$messages     = $thread['data']         ?? [];
$interlocutor = $thread['interlocutor'] ?? [];

// ── Helpers ──
function threadTimeLabel(string $datetime): string
{
    $ts   = strtotime($datetime);
    $now  = time();
    $diff = $now - $ts;

    if ($diff < 60)         return 'À l\'instant';
    if ($diff < 3600)       return 'Il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400)      return 'Aujourd\'hui ' . date('H:i', $ts);
    if ($diff < 86400 * 2)  return 'Hier ' . date('H:i', $ts);
    return date('d/m/Y H:i', $ts);
}

function threadAvatarInitials(string $name): string
{
    $parts = preg_split('/[\s_\-]+/', trim($name));
    $init  = '';
    foreach ($parts as $p) {
        if ($p !== '') $init .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($init) >= 2) break;
    }
    return $init ?: '?';
}

// Date du dernier séparateur affiché côté PHP — passée en JS
// pour que appendMessage() reprenne depuis le bon état initial.
$lastDateSep = '';
if (!empty($messages)) {
    $lastDateSep = date('Y-m-d', strtotime(end($messages)['created_at']));
}
?>

<section class="thread-section">
    <div class="container">
        <div class="thread-layout">

            <!-- ── En-tête conversation ── -->
            <div class="thread-header">
                <a href="/messages" class="thread-header__back btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="d-none d-sm-inline">Retour</span>
                </a>
                <a href="/profil/<?= htmlspecialchars($interlocutor['name'] ?? '') ?>"
                    class="thread-header__profile-link">
                    <div class="thread-header__avatar">
                        <?= htmlspecialchars(threadAvatarInitials($interlocutor['display_name'] ?? $interlocutor['name'] ?? '?')) ?>
                    </div>
                    <div class="thread-header__info">
                        <span class="thread-header__name">
                            <?= htmlspecialchars($interlocutor['display_name'] ?? $interlocutor['name'] ?? 'Utilisateur') ?>
                        </span>
                        <span class="thread-header__handle">
                            @<?= htmlspecialchars($interlocutor['name'] ?? '') ?>
                        </span>
                    </div>
                </a>
            </div>

            <!-- ── Corps : messages ── -->
            <div class="thread-body" id="thread-body">

                <?php if (empty($messages)): ?>
                    <div class="thread-empty">
                        <i class="fa-regular fa-comment"></i>
                        <p>Commencez la conversation !</p>
                    </div>
                <?php else: ?>

                    <?php
                    $prevDate = null;
                    foreach ($messages as $msg):
                        $msgDate  = date('Y-m-d', strtotime($msg['created_at']));
                        $showDate = ($msgDate !== $prevDate);
                        $prevDate = $msgDate;
                    ?>

                        <?php if ($showDate): ?>
                            <div class="thread-date-sep" data-date="<?= $msgDate ?>">
                                <span><?= date('d/m/Y', strtotime($msg['created_at'])) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="thread-msg <?= $msg['is_mine'] ? 'thread-msg--mine' : 'thread-msg--theirs' ?>"
                            data-id="<?= (int)$msg['id'] ?>">
                            <div class="thread-msg__bubble"><?= htmlspecialchars($msg['content']) ?></div>
                            <span class="thread-msg__time">
                                <?= htmlspecialchars(threadTimeLabel($msg['created_at'])) ?>
                            </span>
                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div><!-- /#thread-body -->

            <!-- ── Pied : formulaire d'envoi ── -->
            <div class="thread-footer">
                <div class="thread-form" id="thread-form">
                    <textarea
                        id="msg-input"
                        class="thread-form__input form-control"
                        placeholder="Écrivez votre message…"
                        rows="1"
                        maxlength="5000"
                        autocomplete="off"></textarea>
                    <button type="button" id="msg-send-btn" class="btn btn-primary thread-form__send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <p class="thread-form__hint">Entrée pour envoyer · Maj+Entrée pour sauter une ligne</p>
            </div>

        </div><!-- /.thread-layout -->
    </div>
</section>

<script>
    (function() {
        const recipientId = <?= (int)$otherUserId ?>;
        const body = document.getElementById('thread-body');
        const input = document.getElementById('msg-input');
        const sendBtn = document.getElementById('msg-send-btn');
        let lastMsgId = <?= !empty($messages) ? (int)end($messages)['id'] : 0 ?>;
        let polling = null;

        // Initialisé depuis PHP pour que appendMessage() sache
        // quelle est la dernière date déjà affichée.
        let currentDateSep = <?= json_encode($lastDateSep) ?>;

        // ── Auto-scroll vers le bas ──
        function scrollToBottom(smooth = false) {
            body.scrollTo({
                top: body.scrollHeight,
                behavior: smooth ? 'smooth' : 'instant'
            });
        }
        scrollToBottom();

        // ── Auto-resize du textarea ──
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        });

        // ── Envoi Entrée / Maj+Entrée ──
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        sendBtn.addEventListener('click', sendMessage);

        // ── Format temps ──
        function fmtTime(datetimeStr) {
            const d = new Date(datetimeStr.replace(' ', 'T'));
            const now = new Date();
            const diff = Math.floor((now - d) / 1000);
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            const today = now.toDateString() === d.toDateString();
            const yesterday = new Date(now - 86400000).toDateString() === d.toDateString();

            if (diff < 60) return 'À l\'instant';
            if (diff < 3600) return 'Il y a ' + Math.floor(diff / 60) + ' min';
            if (today) return 'Aujourd\'hui ' + hh + ':' + mm;
            if (yesterday) return 'Hier ' + hh + ':' + mm;
            return d.toLocaleDateString('fr-FR') + ' ' + hh + ':' + mm;
        }

        // ── Injecter un message dans le DOM ──
        function appendMessage(msg) {
            // ── Séparateur de date ──
            // On compare avec currentDateSep (variable JS) plutôt que
            // querySelector(':last-of-type') qui ne fonctionne pas sur les classes.
            const msgDate = msg.created_at.substring(0, 10);
            if (msgDate !== currentDateSep) {
                currentDateSep = msgDate;
                const sep = document.createElement('div');
                sep.className = 'thread-date-sep';
                sep.dataset.date = msgDate;
                const d = new Date(msg.created_at.replace(' ', 'T'));
                sep.innerHTML = '<span>' + d.toLocaleDateString('fr-FR') + '</span>';
                body.appendChild(sep);
            }

            // Supprimer l'état vide éventuel
            body.querySelector('.thread-empty')?.remove();

            // ── Bulle ──
            // On utilise escHtml() sans .replace(/\n/g, '<br>') :
            // le CSS white-space:pre-wrap affiche les sauts de ligne nativement,
            // et nl2br+pre-wrap en PHP causait une ligne vide supplémentaire.
            const div = document.createElement('div');
            div.className = 'thread-msg ' + (msg.is_mine ? 'thread-msg--mine' : 'thread-msg--theirs');
            div.dataset.id = msg.id;
            div.innerHTML =
                '<div class="thread-msg__bubble">' + escHtml(msg.content) + '</div>' +
                '<span class="thread-msg__time">' + fmtTime(msg.created_at) + '</span>';
            body.appendChild(div);

            if (msg.id > lastMsgId) lastMsgId = msg.id;
        }

        function escHtml(str) {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // ── Envoi du message ──
        async function sendMessage() {
            const content = input.value.trim();
            if (!content) return;

            setSending(true);

            try {
                const res = await fetch('/action/send-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        recipient_id: recipientId,
                        content
                    }),
                });
                const data = await res.json();

                if (res.ok && data.id) {
                    input.value = '';
                    input.style.height = 'auto';
                    appendMessage({
                        ...data,
                        is_mine: true
                    });
                    scrollToBottom(true);
                } else {
                    showToast(data?.error?.message ?? 'Impossible d\'envoyer le message.', 'danger');
                }
            } catch {
                showToast('Erreur réseau, veuillez réessayer.', 'danger');
            } finally {
                setSending(false);
                input.focus();
            }
        }

        function setSending(on) {
            sendBtn.disabled = on;
            input.disabled = on;
            sendBtn.innerHTML = on ?
                '<i class="fa-solid fa-spinner fa-spin"></i>' :
                '<i class="fa-solid fa-paper-plane"></i>';
        }

        // ── Polling (nouveaux messages) ──
        async function pollNewMessages() {
            try {
                const res = await fetch('/action/get-thread?user_id=' + recipientId + '&after=' + lastMsgId);
                const data = await res.json();
                if (!res.ok || !Array.isArray(data.messages)) return;

                const atBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 60;

                data.messages.forEach(msg => {
                    if (!body.querySelector('[data-id="' + msg.id + '"]')) {
                        appendMessage(msg);
                    }
                });

                if (atBottom && data.messages.length > 0) scrollToBottom(true);
            } catch {
                /* silencieux */
            }
        }

        polling = setInterval(pollNewMessages, 5000);
        window.addEventListener('beforeunload', () => clearInterval(polling));
    })();
</script>