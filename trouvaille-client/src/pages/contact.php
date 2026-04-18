<?php
$sent  = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'L\'adresse e-mail n\'est pas valide.';
    } elseif (strlen($message) < 10) {
        $error = 'Votre message est trop court (10 caractères minimum).';
    } else {
        // Formulaire validé — en production, envoyer un e-mail ici
        $_SESSION['flash_toasts'][] = [
            'msg'  => 'Message envoyé ! Nous vous répondrons dans les plus brefs délais.',
            'type' => 'success',
        ];
        header('Location: /contact');
        exit;
    }
}
?>

<section class="contact-section">
    <div class="container">

        <div class="contact-layout">

            <!-- ══════════════════════════════════════════
                 COLONNE PRINCIPALE — Formulaire
            ══════════════════════════════════════════ -->
            <div class="contact-main">

                <div class="contact-card">
                    <div class="contact-card__header">
                        <h1 class="contact-card__title">Nous contacter</h1>
                        <p class="contact-card__subtitle">
                            Une question, un signalement ou un problème technique ?
                            Remplissez ce formulaire et nous vous répondrons rapidement.
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/contact" id="contact-form">

                        <div class="contact-form-row">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nom complet</label>
                                <input type="text" id="name" name="name" class="form-control"
                                    placeholder="Jean Dupont"
                                    value="<?= htmlspecialchars($_POST['name'] ?? (Auth::isLoggedIn() ? (Auth::getUser()['display_name'] ?? Auth::getUser()['name']) : '')) ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse e-mail</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    placeholder="jean@exemple.fr"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <select id="subject" name="subject" class="form-select" required>
                                <option value="" disabled <?= empty($_POST['subject']) ? 'selected' : '' ?>>
                                    Choisissez un sujet…
                                </option>
                                <option value="question" <?= ($_POST['subject'] ?? '') === 'question' ? 'selected' : '' ?>>
                                    Question générale
                                </option>
                                <option value="signalement" <?= ($_POST['subject'] ?? '') === 'signalement' ? 'selected' : '' ?>>
                                    Signaler une annonce
                                </option>
                                <option value="compte" <?= ($_POST['subject'] ?? '') === 'compte' ? 'selected' : '' ?>>
                                    Problème de compte
                                </option>
                                <option value="technique" <?= ($_POST['subject'] ?? '') === 'technique' ? 'selected' : '' ?>>
                                    Bug ou problème technique
                                </option>
                                <option value="autre" <?= ($_POST['subject'] ?? '') === 'autre' ? 'selected' : '' ?>>
                                    Autre
                                </option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label">Message</label>
                            <textarea id="message" name="message" class="form-control"
                                rows="6"
                                placeholder="Décrivez votre demande en détail…"
                                required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <p class="form-text" id="msg-counter">0 / 2000 caractères</p>
                        </div>

                        <button type="submit" class="btn btn-primary" id="contact-submit">
                            <i class="fa-solid fa-paper-plane"></i>
                            Envoyer le message
                        </button>

                    </form>
                </div>

            </div>

            <!-- ══════════════════════════════════════════
                 SIDEBAR — Infos et FAQ
            ══════════════════════════════════════════ -->
            <aside class="contact-sidebar">

                <!-- Coordonnées -->
                <div class="sidebar-card">
                    <p class="sidebar-card__title">Informations</p>
                    <ul class="contact-info-list">
                        <li>
                            <i class="fa-regular fa-clock"></i>
                            <div>
                                <span class="contact-info-list__label">Délai de réponse</span>
                                <span class="contact-info-list__value">Sous 48h en jours ouvrés</span>
                            </div>
                        </li>
                        <li>
                            <i class="fa-solid fa-shield-halved"></i>
                            <div>
                                <span class="contact-info-list__label">Urgences</span>
                                <span class="contact-info-list__value">Signalements traités en priorité</span>
                            </div>
                        </li>
                        <li>
                            <i class="fa-regular fa-envelope"></i>
                            <div>
                                <span class="contact-info-list__label">E-mail</span>
                                <span class="contact-info-list__value">contact@trouvaille.fr</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- FAQ rapide -->
                <div class="sidebar-card">
                    <p class="sidebar-card__title">Questions fréquentes</p>
                    <div class="faq-list">

                        <div class="faq-item">
                            <button class="faq-item__question" type="button">
                                Comment supprimer mon compte ?
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <div class="faq-item__answer">
                                Contactez-nous via ce formulaire en choisissant "Problème de compte". Votre demande sera traitée sous 48h.
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-item__question" type="button">
                                Mon annonce a été supprimée, pourquoi ?
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <div class="faq-item__answer">
                                Les annonces ne respectant pas nos conditions d'utilisation (contenu illicite, doublon, spam) peuvent être supprimées par notre équipe de modération.
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-item__question" type="button">
                                Comment signaler une annonce suspecte ?
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <div class="faq-item__answer">
                                Utilisez ce formulaire en sélectionnant "Signaler une annonce" et indiquez l'identifiant ou le titre de l'annonce concernée.
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Liens utiles -->
                <?php if (!Auth::isLoggedIn()): ?>
                    <div class="sidebar-card">
                        <p class="sidebar-card__title">Accès rapide</p>
                        <div class="d-flex flex-column gap-2">
                            <a href="/connexion" class="btn btn-outline-dark w-100">
                                <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                            </a>
                            <a href="/inscription" class="btn btn-secondary w-100">
                                <i class="fa-solid fa-user-plus"></i> Créer un compte
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>

        </div>

    </div>
</section>

<script>
    // ── Compteur de caractères message ──
    (function() {
        const textarea = document.getElementById('message');
        const counter = document.getElementById('msg-counter');
        if (!textarea || !counter) return;

        function update() {
            const len = textarea.value.length;
            counter.textContent = len + ' / 2000 caractères';
            counter.style.color = len > 1800 ? 'var(--color-warning)' : '';
            textarea.maxLength = 2000;
        }

        textarea.addEventListener('input', update);
        update();
    })();

    // ── FAQ accordion ──
    document.querySelectorAll('.faq-item__question').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.faq-item');
            const isOpen = item.classList.contains('open');

            // Ferme tous les autres
            document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));

            if (!isOpen) item.classList.add('open');
        });
    });
</script>