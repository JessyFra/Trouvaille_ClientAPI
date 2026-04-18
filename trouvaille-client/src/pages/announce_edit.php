<?php
if (!Auth::isLoggedIn()) {
    header('Location: /connexion');
    exit;
}

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

// Vérif propriétaire ou admin
if (Auth::getUser()['id'] !== $a['author']['id'] && !Auth::isAdmin()) {
    $_SESSION['flash_toasts'][] = ['msg' => 'Accès refusé.', 'type' => 'danger'];
    header('Location: /annonces/' . $routeParam);
    exit;
}

$cities     = $api->get('/cities')['body']['data']     ?? [];
$categories = $api->get('/categories')['body']['data'] ?? [];
$error      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    $data = array_filter([
        'title'       => trim($_POST['title']       ?? '') ?: null,
        'description' => trim($_POST['description'] ?? '') ?: null,
        'price'       => isset($_POST['price']) ? max(0, (float)$_POST['price']) : null,
        'city_id'     => (int)($_POST['city_id'] ?? 0) ?: null,
    ]);

    $res = $api->patch('/announces/' . $a['id'], $data, Auth::getToken());

    if ($res['status'] === 200) {
        // Nouvelles images
        $files = $_FILES['images'] ?? [];
        if (!empty($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $idx => $tmpName) {
                if (!$tmpName || $files['error'][$idx] !== UPLOAD_ERR_OK) continue;
                $api->uploadFile(
                    '/announces/' . $a['id'] . '/images',
                    $tmpName,
                    $files['type'][$idx],
                    'image',
                    Auth::getToken(),
                    ['is_main' => 'false']
                );
            }
        }

        $_SESSION['flash_toasts'][] = ['msg' => 'Annonce modifiée.', 'type' => 'success'];
        header('Location: /annonces/' . $a['id']);
        exit;
    } else {
        $error = $res['body']['error']['message'] ?? 'Erreur lors de la modification.';
    }
}

$existingCatIds = array_column($a['categories'], 'id');

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
            <h1 class="page-header__title">Modifier l'annonce</h1>
            <p class="page-header__subtitle">
                <a href="/annonces/<?= $a['id'] ?>" class="text-muted">
                    ← Retour à l'annonce
                </a>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-6">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/annonces/<?= $a['id'] ?>/modifier"
            enctype="multipart/form-data">
            <input type="hidden" name="action_update" value="1">

            <div class="announce-form-layout">

                <!-- Colonne principale -->
                <div>

                    <!-- Infos -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Informations</p>

                        <div class="mb-3">
                            <label for="title" class="form-label">Titre</label>
                            <input type="text" id="title" name="title" class="form-control"
                                value="<?= htmlspecialchars($_POST['title'] ?? $a['title']) ?>"
                                maxlength="64">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description"
                                class="form-control" rows="6"><?= htmlspecialchars($_POST['description'] ?? $a['description'] ?? '') ?></textarea>
                        </div>

                        <?php if ($a['type'] === 'offer'): ?>
                            <div class="mb-3">
                                <label for="price" class="form-label">Prix (€)</label>
                                <input type="number" id="price" name="price" class="form-control"
                                    value="<?= htmlspecialchars($_POST['price'] ?? $a['price']) ?>"
                                    min="0" step="1">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Localisation -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Localisation</p>
                        <div class="mb-3">
                            <label for="city_id" class="form-label">Ville</label>
                            <select id="city_id" name="city_id" class="form-select">
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['id'] ?>"
                                        <?= $city['id'] === $a['city']['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($city['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Photos existantes -->
                    <?php if (!empty($a['images'])): ?>
                        <div class="announce-form__section">
                            <p class="announce-form__section-title">Photos actuelles</p>
                            <div class="image-previews" id="existingPreviews">
                                <?php foreach ($a['images'] as $img): ?>
                                    <div class="image-preview-item" id="img-<?= $img['id'] ?>">
                                        <img src="<?= htmlspecialchars($img['url']) ?>" alt="">
                                        <button type="button"
                                            class="image-preview-item__remove"
                                            onclick="deleteExistingImage(<?= $img['id'] ?>, <?= $a['id'] ?>)">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Ajouter des photos -->
                    <div class="announce-form__section">
                        <p class="announce-form__section-title">Ajouter des photos</p>
                        <div class="upload-zone" id="uploadZone"
                            onclick="document.getElementById('imagesInput').click()">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p>Cliquez ou glissez vos photos ici</p>
                            <span>JPG, PNG, WebP — 5 Mo max</span>
                            <input type="file" id="imagesInput" name="images[]"
                                accept="image/jpeg,image/png,image/webp" multiple>
                        </div>
                        <div class="image-previews" id="imagePreviews"></div>
                    </div>

                </div>

                <!-- Sidebar -->
                <aside class="announce-sidebar">
                    <div class="sidebar-card">
                        <p class="sidebar-card__title">Sauvegarder</p>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                        </button>
                        <a href="/annonces/<?= $a['id'] ?>"
                            class="btn btn-secondary w-100 mt-2">
                            Annuler
                        </a>
                    </div>
                </aside>

            </div>
        </form>
    </div>
</section>

<script>
    const input = document.getElementById('imagesInput');
    const previews = document.getElementById('imagePreviews');
    const zone = document.getElementById('uploadZone');
    let selectedFiles = [];

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

    function renderPreviews(files) {
        selectedFiles = files;
        previews.innerHTML = '';
        files.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = e => {
                const wrap = document.createElement('div');
                wrap.className = 'image-preview-item';
                wrap.innerHTML =
                    `<img src="${e.target.result}" alt="">` +
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

    async function deleteExistingImage(imageId, announceId) {
        const el = document.getElementById('img-' + imageId);
        if (!el) return;

        el.style.opacity = '0.4';
        el.style.pointerEvents = 'none';

        try {
            const res = await fetch('/action/delete-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    announce_id: announceId,
                    image_id: imageId
                })
            });

            if (res.ok || res.status === 204) {
                el.style.transition = 'all 0.2s ease';
                el.style.transform = 'scale(0)';
                setTimeout(() => el.remove(), 200);
            } else {
                const json = await res.json().catch(() => ({}));
                el.style.opacity = '1';
                el.style.pointerEvents = 'all';
                alert('Erreur : ' + (json?.error?.message ?? json?.error ?? 'Suppression échouée'));
            }
        } catch (err) {
            console.error(err);
            el.style.opacity = '1';
            el.style.pointerEvents = 'all';
            alert('Erreur réseau inattendue.');
        }
    }
</script>