<?php
if (!Auth::isLoggedIn()) {
    header('Location: /connexion');
    exit;
}

if (Auth::isBanned()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Votre compte est suspendu.', 'type' => 'danger'];
    header('Location: /');
    exit;
}

$api        = new ApiClient();
$cities     = $api->get('/cities')['body']['data']     ?? [];
$categories = $api->get('/categories')['body']['data'] ?? [];

$error  = null;
$posted = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'title'        => trim($_POST['title']       ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'price'        => $_POST['price']             ?? '',
        'type'         => $_POST['type']              ?? 'offer',
        'city_id'      => (int)($_POST['city_id']     ?? 0),
        'category_ids' => array_map('intval', $_POST['category_ids'] ?? []),
    ];

    // Validation basique
    if (strlen($posted['title']) < 3) {
        $error = 'Le titre doit faire au moins 3 caractères.';
    } elseif (!$posted['city_id']) {
        $error = 'Veuillez choisir une ville.';
    } elseif (empty($posted['category_ids'])) {
        $error = 'Veuillez choisir au moins une catégorie.';
    } else {
        $response = $api->post('/announces', [
            'title'        => $posted['title'],
            'description'  => $posted['description'] ?: null,
            'price'        => $posted['type'] === 'offer' ? max(0, (float) $posted['price']) : 0,
            'type'         => $posted['type'],
            'city_id'      => $posted['city_id'],
            'category_ids' => $posted['category_ids'],
        ], Auth::getToken());

        if ($response['status'] === 201) {
            $newId = $response['body']['id'];

            // Upload des images
            $files = $_FILES['images'] ?? [];
            if (!empty($files['tmp_name'])) {
                foreach ($files['tmp_name'] as $idx => $tmpName) {
                    if (!$tmpName || $files['error'][$idx] !== UPLOAD_ERR_OK) continue;

                    $isMain = ($idx === 0);
                    $api->uploadFile(
                        '/announces/' . $newId . '/images',
                        $tmpName,
                        $files['type'][$idx],
                        'image',
                        Auth::getToken(),
                        ['is_main' => $isMain ? 'true' : 'false']
                    );
                }
            }

            $_SESSION['flash_toasts'][] = [
                'msg'  => 'Annonce publiée avec succès !',
                'type' => 'success',
            ];
            header('Location: /annonces/' . $newId);
            exit;
        } else {
            $error = $response['body']['error']['message'] ?? 'Une erreur est survenue.';
        }
    }
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
?>

<section class="announce-form-section">
    <div class="container">

        <div class="page-header" style="padding-top:var(--space-8); margin-bottom:var(--space-8);">
            <h1 class="page-header__title">Déposer une annonce</h1>
            <p class="page-header__subtitle">Remplissez les informations ci-dessous pour publier votre annonce.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-6">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/annonces/creer"
            enctype="multipart/form-data" id="announceForm">
            <div class="announce-form-layout">

                <!-- Colonne principale -->
                <div>

                    <!-- Type -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Type d'annonce</p>
                        <div class="type-toggle">
                            <div>
                                <input type="radio" name="type" id="type_offer" value="offer"
                                    <?= ($posted['type'] ?? 'offer') === 'offer' ? 'checked' : '' ?>>
                                <label for="type_offer">
                                    <i class="fa-solid fa-tag"></i> Je vends / donne
                                </label>
                            </div>
                            <div>
                                <input type="radio" name="type" id="type_request" value="request"
                                    <?= ($posted['type'] ?? '') === 'request' ? 'checked' : '' ?>>
                                <label for="type_request">
                                    <i class="fa-solid fa-hand-holding-heart"></i> Je cherche
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Infos -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Informations</p>

                        <div class="mb-3">
                            <label for="title" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control"
                                placeholder="Ex: iPhone 13 128Go Bleu — Excellent état"
                                value="<?= htmlspecialchars($posted['title'] ?? '') ?>"
                                maxlength="64" required>
                            <span class="form-text">Maximum 64 caractères.</span>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control"
                                rows="6"
                                placeholder="Décrivez votre article : état, caractéristiques, conditions de vente…"><?= htmlspecialchars($posted['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3" id="priceField">
                            <label for="price" class="form-label">Prix (€)</label>
                            <input type="number" id="price" name="price" class="form-control"
                                placeholder="0 pour gratuit"
                                value="<?= htmlspecialchars($posted['price'] ?? '') ?>"
                                min="0" step="1">
                            <span class="form-text">Laissez 0 pour une annonce gratuite.</span>
                        </div>
                    </div>

                    <!-- Localisation -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Localisation</p>
                        <div class="mb-3">
                            <label for="city_id" class="form-label">Ville <span class="text-danger">*</span></label>
                            <select id="city_id" name="city_id" class="form-select" required>
                                <option value="">Choisissez une ville</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['id'] ?>"
                                        <?= ($posted['city_id'] ?? 0) === $city['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($city['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Catégories -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Catégorie(s) <span class="text-danger">*</span></p>
                        <div class="categories-checkboxes">
                            <?php foreach ($categories as $cat):
                                $icon    = $categoryIcons[$cat['name']] ?? 'fa-tag';
                                $checked = in_array($cat['id'], $posted['category_ids'] ?? []);
                            ?>
                                <div class="category-checkbox">
                                    <input type="checkbox"
                                        id="cat_<?= $cat['id'] ?>"
                                        name="category_ids[]"
                                        value="<?= $cat['id'] ?>"
                                        <?= $checked ? 'checked' : '' ?>>
                                    <label for="cat_<?= $cat['id'] ?>">
                                        <i class="fa-solid <?= $icon ?>"></i>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Photos</p>
                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('imagesInput').click()">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p>Cliquez ou glissez vos photos ici</p>
                            <span>JPG, PNG, WebP — 5 Mo max par photo</span>
                            <input type="file" id="imagesInput" name="images[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple>
                        </div>
                        <div class="image-previews" id="imagePreviews"></div>
                    </div>

                </div>

                <!-- Sidebar -->
                <aside class="announce-sidebar">
                    <div class="sidebar-card">
                        <p class="sidebar-card__title">Récapitulatif</p>
                        <p class="text-sm text-muted mb-4">
                            Vérifiez vos informations avant de publier.
                        </p>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-paper-plane"></i> Publier l'annonce
                        </button>
                        <a href="/" class="btn btn-secondary w-100 mt-2">
                            Annuler
                        </a>
                    </div>
                </aside>

            </div>
        </form>
    </div>
</section>

<script>
    // Masquer/afficher le champ prix selon le type
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('priceField').style.display =
                radio.value === 'request' ? 'none' : 'block';
        });
    });
    // Init
    if (document.querySelector('input[name="type"]:checked')?.value === 'request') {
        document.getElementById('priceField').style.display = 'none';
    }

    // Prévisualisation images
    const input = document.getElementById('imagesInput');
    const previews = document.getElementById('imagePreviews');
    const zone = document.getElementById('uploadZone');

    input.addEventListener('change', () => renderPreviews(Array.from(input.files)));

    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        renderPreviews(Array.from(input.files));
    });

    let selectedFiles = [];

    function renderPreviews(files) {
        selectedFiles = files;
        previews.innerHTML = '';
        files.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = e => {
                const wrap = document.createElement('div');
                wrap.className = 'image-preview-item';
                wrap.innerHTML =
                    `<img src="${e.target.result}" alt="preview">` +
                    `<button type="button" class="image-preview-item__remove"
                    onclick="removePreview(${idx})">
                    <i class="fa-solid fa-xmark"></i>
                </button>`;
                previews.appendChild(wrap);
            };
            reader.readAsDataURL(file);
        });
    }

    function removePreview(idx) {
        selectedFiles.splice(idx, 1);
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        input.files = dt.files;
        renderPreviews(selectedFiles);
    }
</script>