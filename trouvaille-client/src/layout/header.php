<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trouvaille</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

    <div id="toast-container" aria-live="polite"></div>

    <nav class="navbar navbar-expand-lg custom-navbar sticky-top">
        <div class="container-fluid px-4">

            <a class="navbar-brand" href="/">
                <img src="/assets/img/favicon.png"
                    alt="Trouvaille"
                    class="navbar-brand__logo">
                Trouvaille
            </a>

            <button class="navbar-toggler ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php $currentUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); ?>

                    <li class="nav-item">
                        <a class="nav-link <?= $currentUri === '' || $currentUri === 'annonces' ? 'active' : '' ?>"
                            href="/">Annonces</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentUri === 'contact' ? 'active' : '' ?>"
                            href="/contact">Contact</a>
                    </li>

                    <?php if (Auth::isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentUri === 'annonces/creer' ? 'active' : '' ?>"
                                href="/annonces/creer">
                                <i class="fa-solid fa-plus"></i> Déposer
                            </a>
                        </li>

                        <!-- Dropdown Mon compte -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array($currentUri, ['profil', 'messages']) || str_starts_with($currentUri, 'messages') ? 'active' : '' ?>"
                                href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-regular fa-circle-user"></i> Mon compte
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item <?= str_starts_with($currentUri, 'messages') ? 'active' : '' ?>"
                                        href="/messages">
                                        <i class="fa-regular fa-comment-dots"></i> Messagerie
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $currentUri === 'profil' && ($_GET['tab'] ?? '') !== 'annonces' ? 'active' : '' ?>"
                                        href="/profil">
                                        <i class="fa-regular fa-user"></i> Mon profil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $currentUri === 'profil' && ($_GET['tab'] ?? '') === 'annonces' ? 'active' : '' ?>"
                                        href="/profil?tab=annonces">
                                        <i class="fa-solid fa-rectangle-list"></i> Mes annonces
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <?php if (Auth::isAdmin()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-warning <?= str_starts_with($currentUri, 'admin') ? 'active' : '' ?>"
                                    href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-shield-halved"></i> Admin
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="/admin?tab=users">
                                            <i class="fa-solid fa-users"></i> Gestion utilisateurs
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/admin?tab=announces">
                                            <i class="fa-solid fa-rectangle-list"></i> Gestion annonces
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="/deconnexion">
                                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentUri === 'connexion' ? 'active' : '' ?>"
                                href="/connexion">Connexion</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentUri === 'inscription' ? 'active' : '' ?>"
                                href="/inscription">Inscription</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </nav>