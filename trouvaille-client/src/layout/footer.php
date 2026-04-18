</main>

<footer class="custom-footer">
    <div class="container">
        <div class="footer__inner">
            <span class="footer__brand">
                <img src="/assets/img/favicon.png"
                    alt="Trouvaille"
                    class="footer__brand-logo">
                Trouvaille
            </span>
            <span class="footer__copy">
                © <?= date('Y') ?> — Petites annonces entre particuliers
            </span>
            <nav class="footer__links">
                <a href="/">Annonces</a>
                <a href="/contact">Contact</a>
            </nav>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // ── Tooltip catégories (+N badge) ─────────────────────────────
    // Crée le div #cat-tooltip une seule fois et l'attache au <body>
    // (position: fixed → échappe à tous les overflow:hidden des parents)
    (function() {
        const tip = document.createElement('div');
        tip.id = 'cat-tooltip';
        document.body.appendChild(tip);

        window.showCatTooltip = function(el) {
            const cats = el.dataset.cats;
            if (!cats) return;

            tip.textContent = cats;
            tip.classList.add('visible');

            // Positionnement : centré au-dessus du badge
            // On remet à 0 d'abord pour que offsetWidth soit juste
            tip.style.left = '0';
            tip.style.top = '0';

            requestAnimationFrame(function() {
                const rect = el.getBoundingClientRect();
                const tw = tip.offsetWidth;
                const th = tip.offsetHeight;

                let left = rect.left + rect.width / 2 - tw / 2;
                let top = rect.top - th - 6; // 6 px de marge au-dessus

                // Reste dans le viewport horizontalement
                left = Math.max(8, Math.min(left, window.innerWidth - tw - 8));

                tip.style.left = left + 'px';
                tip.style.top = top + 'px';
            });
        };

        window.hideCatTooltip = function() {
            tip.classList.remove('visible');
        };
    })();
</script>
</body>

</html>