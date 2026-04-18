SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS trouvaille;
CREATE DATABASE trouvaille CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trouvaille;

-- ----------------------------------------------------------
-- Création des tables
-- ----------------------------------------------------------

CREATE TABLE user (
    id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    name VARCHAR(16) NOT NULL UNIQUE,
    hashed_password VARCHAR(255) NOT NULL,
    display_name VARCHAR(16),
    biography TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    banned TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT NOW()
) ENGINE = InnoDB;

CREATE TABLE city (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE announce (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(64) NOT NULL,
    description TEXT,
    price DOUBLE DEFAULT 0,
    status ENUM('closed', 'open') DEFAULT 'open',
    type ENUM('offer', 'request') DEFAULT 'offer',
    city_id INT NOT NULL,
    author_id INT NOT NULL,
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE announce_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announce_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255) DEFAULT NULL,
    is_main TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (announce_id) REFERENCES announce (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE announce_category (
    announce_id INT NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (announce_id) REFERENCES announce (id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE message (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content LONGTEXT NOT NULL,
    author_id INT NOT NULL,
    recipient_id INT NOT NULL,
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipient_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ----------------------------------------------------------
-- Utilisateurs
-- Mot de passe : Test1234! (bcrypt cost 10)
-- ----------------------------------------------------------
INSERT INTO user (id, name, hashed_password, display_name, biography, role, banned, created_at) VALUES
(1, 'admin',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin',         'Administrateur de la plateforme Trouvaille.',                              'admin', 0, '2024-01-01 08:00:00'),
(2, 'marie_dupont',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie',         'Passionnée de déco et de mode. Je vide régulièrement mes placards !',      'user',  0, '2024-02-10 09:15:00'),
(3, 'thomas_martin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Thomas',        'Geek assumé, je renouvelle souvent mon matos. Sérieux et ponctuel.',        'user',  0, '2024-02-15 14:30:00'),
(4, 'sophie_bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sophie',        'Jardinière du dimanche et chineuse du samedi. Toujours partante pour un bon plan.', 'user', 0, '2024-03-01 11:00:00'),
(5, 'lucas_petit',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lucas',         'Étudiant en informatique. Vends du matos tech en bon état.',                'user',  0, '2024-03-20 16:45:00'),
(6, 'emma_rousseau', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emma',          'Amoureuse des livres et du sport. Je cherche autant que je vends.',        'user',  0, '2024-04-05 10:20:00'),
(7, 'julien_blanc',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Julien',        NULL,                                                                        'user',  1, '2024-04-10 08:00:00');

-- ----------------------------------------------------------
-- Villes (20 villes françaises)
-- ----------------------------------------------------------
INSERT INTO city (id, name) VALUES
(1,  'Paris'),
(2,  'Lyon'),
(3,  'Marseille'),
(4,  'Toulouse'),
(5,  'Bordeaux'),
(6,  'Nantes'),
(7,  'Strasbourg'),
(8,  'Lille'),
(9,  'Nice'),
(10, 'Rennes'),
(11, 'Grenoble'),
(12, 'Montpellier'),
(13, 'Tours'),
(14, 'Clermont-Ferrand'),
(15, 'Dijon'),
(16, 'Angers'),
(17, 'Metz'),
(18, 'Saint-Étienne'),
(19, 'Le Mans'),
(20, 'Caen');

-- ----------------------------------------------------------
-- Catégories (10)
-- ----------------------------------------------------------
INSERT INTO category (id, name) VALUES
(1,  'Informatique & High-Tech'),
(2,  'Mobilier & Décoration'),
(3,  'Vêtements & Accessoires'),
(4,  'Véhicules'),
(5,  'Immobilier'),
(6,  'Sport & Loisirs'),
(7,  'Jardin & Plantes'),
(8,  'Livres & Médias'),
(9,  'Électroménager'),
(10, 'Services');

-- ----------------------------------------------------------
-- Annonces (30)
-- ----------------------------------------------------------
INSERT INTO announce (id, title, description, price, status, type, city_id, author_id, created_at) VALUES

-- OFFRES - Informatique
(1,  'PC Gaming RTX 3070 — i7 12700K',
     'Config assemblée par mes soins il y a 18 mois. RTX 3070 8Go, i7 12700K, 32Go RAM DDR5, SSD 1To NVMe. Vendu avec Windows 11 Pro. Tourne tout en ultra. Remplace par une config encore plus puissante.',
     1200, 'open', 'offer', 2, 5, '2024-06-01 10:00:00'),

(2,  'iPhone 13 128Go Bleu — Excellent état',
     'Utilisé 1 an, aucune rayure, avec sa boîte d''origine, chargeur et coque de protection. Batterie à 94%. Déverrouillé tous opérateurs.',
     450, 'open', 'offer', 1, 2, '2024-06-03 14:30:00'),

(3,  'MacBook Pro M1 13" — 2021',
     'Peu utilisé, en parfait état. 8 Go RAM, 256 Go SSD. Autonomie incroyable (10h+). Vendu avec la boîte, chargeur MagSafe. Idéal pour études ou télétravail.',
     1100, 'open', 'offer', 1, 3, '2024-06-05 09:00:00'),

(4,  'Appareil photo Canon EOS M50',
     'Hybride Canon EOS M50 avec objectif 15-45mm. Environ 4000 déclenchements. Vidéo 4K. Vendu avec 2 batteries, chargeur et carte SD 64Go. Superbe pour débuter en photo.',
     280, 'open', 'offer', 10, 5, '2024-06-18 11:00:00'),

(5,  'Drone DJI Mini 2 — Comme neuf',
     'Utilisé 3 fois seulement. Vendu avec radiocommande, 2 batteries, chargeur et sacoche de transport. Moins de 250g donc aucune réglementation contraignante.',
     180, 'closed', 'offer', 9, 3, '2024-05-20 15:00:00'),

-- OFFRES - Mobilier
(6,  'Canapé 3 places tissu gris — IKEA',
     'Canapé IKEA Kivik, 3 places, housse gris clair lavable en machine. Très bon état général, quelques légères traces d''usure sur les accoudoirs. Démontable. À venir chercher sur place.',
     180, 'open', 'offer', 5, 3, '2024-06-07 16:00:00'),

(7,  'Table basse bois massif',
     'Table basse en chêne massif, 110x60cm, style scandinave. Quelques micro-rayures en surface. Solide et intemporelle. Non démontable, prévoir un véhicule adapté.',
     45, 'open', 'offer', 2, 4, '2024-06-10 10:30:00'),

(8,  'Bibliothèque 5 étagères blanche',
     'Bibliothèque type IKEA Billy, 5 étagères, très bon état. Hauteur 200cm, largeur 80cm. Idéale pour un salon ou bureau. À démonter et venir chercher à Paris 15ème.',
     35, 'open', 'offer', 1, 4, '2024-06-20 09:00:00'),

(9,  'Armoire 2 portes coulissantes',
     'Armoire 2 portes miroir coulissantes. 160x200cm. Intérieur bien organisé : 2 penderies + étagères. Très bon état. À démonter sur place, vous aurez besoin d''aide.',
     75, 'open', 'offer', 17, 4, '2024-06-22 14:00:00'),

-- OFFRES - Vêtements
(10, 'Veste en cuir noir — Taille M',
     'Veste en cuir véritable, coupe droite, taille M. Portée une saison. Parfait état. Marque Schott. Donne un style rock/casual parfait.',
     80, 'open', 'offer', 3, 2, '2024-06-08 11:00:00'),

(11, 'Robe de soirée bordeaux — Taille 38',
     'Robe longue de soirée, couleur bordeaux, taille 38. Portée une seule fois pour un mariage. Tissu fluide, coupe empire. Avec son étiquette d''origine.',
     55, 'open', 'offer', 2, 6, '2024-06-15 17:00:00'),

(12, 'Pull en laine douce — Taille S',
     'Pull vert sauge, 100% laine mérinos, très doux. Taille S. Lavé à la main, impeccable. Idéal pour l''automne. Marque Uniqlo.',
     15, 'open', 'offer', 20, 2, '2024-06-25 10:00:00'),

-- OFFRES - Sport & Loisirs
(13, 'VTT semi-rigide 29" — Rockrider',
     'VTT Btwin Rockrider 520, cadre aluminium, fourche suspendue 100mm, dérailleur Shimano 21 vitesses. Taille L. Entretenu régulièrement. Quelques traces d''utilisation normales.',
     320, 'open', 'offer', 11, 5, '2024-06-12 08:00:00'),

(14, 'Lot de jeux PS4 — 8 jeux',
     'Lot de 8 jeux PS4 : FIFA 23, GTA V, The Last of Us, Uncharted 4, Horizon Zero Dawn, Spider-Man, Red Dead Redemption 2, God of War. Tous en bon état avec boîte.',
     60, 'open', 'offer', 7, 5, '2024-06-17 19:00:00'),

(15, 'Nintendo Switch OLED + 5 jeux',
     'Nintendo Switch OLED blanche, acheté il y a 8 mois. Avec Joy-Con, dock, câbles. Jeux inclus : Zelda BotW, Mario Kart 8, Animal Crossing, Splatoon 3, Pokemon Violet.',
     220, 'open', 'offer', 1, 6, '2024-06-21 12:00:00'),

(16, 'Tapis de yoga antidérapant — Neuf',
     'Tapis de yoga 183x61cm, 6mm d''épaisseur, matière TPE écologique. Jamais utilisé, encore dans son emballage. Coloris violet.',
     20, 'open', 'offer', 5, 2, '2024-06-26 10:00:00'),

-- OFFRES - Jardin
(17, 'Tondeuse thermique Husqvarna',
     'Tondeuse à gazon thermique Husqvarna, coupe 46cm. Moteur Honda 140cc. Traction avant. Bac de ramassage. Révisée au printemps. Idéale pour terrain jusqu''à 800m².',
     90, 'open', 'offer', 4, 4, '2024-06-09 07:30:00'),

(18, 'Salon de jardin aluminium 6 places',
     'Table + 6 fauteuils aluminium/textilène taupe. Très bon état, utilisé 2 étés. Housse de protection fournie. Table extensible de 160 à 220cm.',
     120, 'open', 'offer', 9, 4, '2024-06-14 16:00:00'),

-- OFFRES - Livres
(19, 'Lot de 12 livres de science-fiction',
     'Lot de 12 romans SF : Asimov, Philip K. Dick, Frank Herbert (Dune), Isaac Asimov, Arthur C. Clarke... Tous en très bon état, aucun défaut majeur. Prix pour le lot.',
     25, 'open', 'offer', 10, 6, '2024-06-11 13:00:00'),

-- OFFRES - Électroménager
(20, 'Réfrigérateur Bosch 350L — A+',
     'Réfrigérateur congélateur Bosch KGN36VLED, 350L (253+97), classe A+. Acheté en 2021, fonctionne parfaitement. Vendu car déménagement en appartement plus petit.',
     200, 'open', 'offer', 2, 2, '2024-06-13 15:00:00'),

(21, 'Machine à laver Samsung 8kg — A+++',
     'Lave-linge Samsung AddWash 8kg, classe A+++. 2 ans d''utilisation. Fonctionne très bien. Avec tous les programmes. Moteur Digital Inverter garanti 10 ans.',
     250, 'open', 'offer', 8, 3, '2024-06-16 11:30:00'),

-- OFFRES - Véhicules
(22, 'Scooter 50cc Yamaha Jog — 2020',
     'Yamaha Jog 50cc de 2020, 3800km, carnet d''entretien suivi. Vidange faite il y a 500km. Pneus neufs. Permis AM requis. Vendu avec antivol et top case.',
     850, 'open', 'offer', 4, 3, '2024-06-19 10:00:00'),

-- OFFRES - Services
(23, 'Cours de guitare — Débutant & Intermédiaire',
     'Professeur de guitare diplômé donne cours particuliers. Guitare acoustique, électrique et folk. Tous styles (rock, pop, blues, classique). Cours à domicile ou en ligne. 1h = 30€.',
     30, 'open', 'offer', 5, 6, '2024-06-23 09:00:00'),

-- DEMANDES
(24, 'Recherche vélo de ville — Bon état',
     'Cherche vélo de ville taille M ou L pour femme. Budget 80-120€. Préfère avec panier et garde-boue. Peut venir chercher sur Nantes et environs.',
     0, 'open', 'request', 6, 6, '2024-06-04 10:00:00'),

(25, 'Recherche machine à laver — Petit budget',
     'Étudiant cherche machine à laver fonctionnelle, 5 ou 6 kg. Budget max 80€. Peux venir la chercher avec un véhicule utilitaire loué. Secteur Lille.',
     0, 'open', 'request', 8, 5, '2024-06-06 18:00:00'),

(26, 'Recherche colocation Paris — Septembre',
     'Ingénieur en CDI cherche colocation ou chambre meublée à Paris, idéalement Paris 11/12/13. Budget 700€ charges comprises. Non-fumeur, calme, propre. Dispo à partir du 1er septembre.',
     0, 'open', 'request', 1, 3, '2024-06-09 12:00:00'),

(27, 'Recherche vélo électrique — Budget 400€',
     'Cherche vélo électrique en bon état. Budget 350-400€. Poids maximum 22kg. Autonomie min 40km. Secteur Nantes et Loire-Atlantique. Peux me déplacer dans un rayon de 50km.',
     0, 'open', 'request', 6, 3, '2024-06-15 09:00:00'),

(28, 'Recherche baby-foot pour local asso',
     'Association cherche baby-foot d''occasion en bon état pour salle commune. Budget 150€ max. Quartier Paris 18/19/20. Contact par message, pas d''appel.',
     0, 'open', 'request', 1, 2, '2024-06-20 16:00:00'),

(29, 'Recherche cours de cuisine — Lyon',
     'Cherche cours particuliers de cuisine française ou italienne. 1 fois par semaine, de préférence le week-end. Budget 25€/h. Secteur Lyon 3/6/7.',
     0, 'open', 'request', 2, 5, '2024-06-22 11:00:00'),

(30, 'Recherche appartement T2 — Toulouse',
     'Jeune couple cherche T2 à louer, idéalement meublé, secteur Toulouse Capitole ou Saint-Cyprien. Budget 750€ charges comprises. Disponible dès maintenant. Dossier solide fourni.',
     0, 'open', 'request', 4, 3, '2024-06-24 14:00:00');

-- ----------------------------------------------------------
-- Images des annonces
-- Dossier : trouvaille-api/public/uploads/announces/
-- ----------------------------------------------------------
INSERT INTO announce_image (id, announce_id, image_path, is_main) VALUES
-- PC Gaming
(1,  1, 'uploads/announces/pc-gaming-1.jpg', 1),
(2,  1, 'uploads/announces/pc-gaming-2.jpg', 0),
-- iPhone 13
(3,  2, 'uploads/announces/iphone13-1.jpg', 1),
(4,  2, 'uploads/announces/iphone13-2.jpg', 0),
-- MacBook Pro
(5,  3, 'uploads/announces/macbook-pro-1.jpg', 1),
-- Canon EOS M50
(6,  4, 'uploads/announces/canon-eos-1.jpg', 1),
(7,  4, 'uploads/announces/canon-eos-2.jpg', 0),
-- Drone DJI (annonce clôturée)
(8,  5, 'uploads/announces/dji-mini-1.jpg', 1),
-- Canapé
(9,  6, 'uploads/announces/canape-gris-1.jpg', 1),
(10, 6, 'uploads/announces/canape-gris-2.jpg', 0),
-- Table basse
(11, 7, 'uploads/announces/table-basse-1.jpg', 1),
-- Bibliothèque
(12, 8, 'uploads/announces/bibliotheque-1.jpg', 1),
-- Armoire
(13, 9, 'uploads/announces/armoire-1.jpg', 1),
(14, 9, 'uploads/announces/armoire-2.jpg', 0),
-- Veste cuir
(15, 10, 'uploads/announces/veste-cuir-1.jpg', 1),
-- Robe soirée
(16, 11, 'uploads/announces/robe-soiree-1.jpg', 1),
(17, 11, 'uploads/announces/robe-soiree-2.jpg', 0),
-- Pull laine
(18, 12, 'uploads/announces/pull-laine-1.jpg', 1),
-- VTT
(19, 13, 'uploads/announces/vtt-1.jpg', 1),
(20, 13, 'uploads/announces/vtt-2.jpg', 0),
-- Jeux PS4
(21, 14, 'uploads/announces/jeux-ps4-1.jpg', 1),
-- Nintendo Switch
(22, 15, 'uploads/announces/switch-1.jpg', 1),
(23, 15, 'uploads/announces/switch-2.jpg', 0),
-- Tapis yoga
(24, 16, 'uploads/announces/tapis-yoga-1.jpg', 1),
-- Tondeuse
(25, 17, 'uploads/announces/tondeuse-1.jpg', 1),
-- Salon jardin
(26, 18, 'uploads/announces/salon-jardin-1.jpg', 1),
(27, 18, 'uploads/announces/salon-jardin-2.jpg', 0),
-- Livres SF
(28, 19, 'uploads/announces/livres-sf-1.jpg', 1),
-- Frigo
(29, 20, 'uploads/announces/frigo-1.jpg', 1),
-- Lave-linge
(30, 21, 'uploads/announces/lave-linge-1.jpg', 1),
-- Scooter
(31, 22, 'uploads/announces/scooter-1.jpg', 1),
(32, 22, 'uploads/announces/scooter-2.jpg', 0),
-- Cours guitare
(33, 23, 'uploads/announces/cours-guitare-1.jpg', 1);

-- ----------------------------------------------------------
-- Catégories des annonces
-- ----------------------------------------------------------
INSERT INTO announce_category (announce_id, category_id) VALUES
(1,  1),  -- PC Gaming → Informatique
(2,  1),  -- iPhone → Informatique
(3,  1),  -- MacBook → Informatique
(4,  1),  -- Canon → Informatique
(5,  1),  -- Drone → Informatique
(6,  2),  -- Canapé → Mobilier
(7,  2),  -- Table basse → Mobilier
(8,  2),  -- Bibliothèque → Mobilier
(9,  2),  -- Armoire → Mobilier
(10, 3),  -- Veste → Vêtements
(11, 3),  -- Robe → Vêtements
(12, 3),  -- Pull → Vêtements
(13, 6),  -- VTT → Sport
(14, 6),  -- Jeux PS4 → Sport & Loisirs
(15, 6),  -- Switch → Sport & Loisirs
(16, 6),  -- Tapis yoga → Sport
(17, 7),  -- Tondeuse → Jardin
(18, 7),  -- Salon jardin → Jardin
(18, 2),  -- Salon jardin → aussi Mobilier
(19, 8),  -- Livres SF → Livres
(20, 9),  -- Frigo → Électroménager
(21, 9),  -- Lave-linge → Électroménager
(22, 4),  -- Scooter → Véhicules
(23, 10), -- Cours guitare → Services
(24, 6),  -- Recherche vélo → Sport
(25, 9),  -- Recherche machine → Électroménager
(26, 5),  -- Recherche coloc → Immobilier
(27, 4),  -- Recherche vélo élec → Véhicules
(28, 6),  -- Recherche baby-foot → Sport & Loisirs
(29, 10), -- Recherche cours → Services
(30, 5);  -- Recherche appart → Immobilier

-- ----------------------------------------------------------
-- Conversations (messages entre utilisateurs)
-- ----------------------------------------------------------

-- Conversation 1 : Emma s'intéresse au MacBook de Thomas (annonce #3)
INSERT INTO message (id, content, author_id, recipient_id, created_at) VALUES
(1,  'Bonjour Thomas, votre MacBook Pro m''intéresse vraiment. Est-ce qu''il y a des défauts que vous n''auriez pas mentionnés dans l''annonce ? Et il est possible de le tester avant achat ?',
     6, 3, '2024-06-06 10:15:00'),
(2,  'Bonjour Emma ! Non vraiment rien à signaler, je l''ai utilisé principalement pour du web et quelques projets perso. Bien sûr qu''on peut le tester, je suis disponible ce weekend sur Paris si ça vous va.',
     3, 6, '2024-06-06 11:30:00'),
(3,  'Super ! Samedi après-midi ça m''irait parfaitement. Vous seriez disponible vers 15h ? On pourrait se retrouver dans un café si vous préférez.',
     6, 3, '2024-06-06 12:00:00'),
(4,  'Samedi 15h c''est parfait. Je vous propose le café Le Procope rue de l''Ancienne Comédie, c''est facile d''accès. On se retrouve là-bas ?',
     3, 6, '2024-06-06 13:45:00'),
(5,  'Parfait, à samedi ! Je viendrai avec mon chargeur pour vérifier la batterie. Merci pour votre réactivité.',
     6, 3, '2024-06-06 14:00:00');

-- Conversation 2 : Lucas demande des infos sur le canapé de Thomas (annonce #6)
INSERT INTO message (id, content, author_id, recipient_id, created_at) VALUES
(6,  'Salut Thomas, le canapé m''intéresse. C''est quelle année exactement ? Et les traces sur les accoudoirs c''est vraiment visible ou c''est des petites choses ?',
     5, 3, '2024-06-08 09:00:00'),
(7,  'Salut Lucas ! Acheté en 2021 donc 3 ans. Les traces sur les accoudoirs c''est très léger, ça se voit seulement de près vraiment. Je peux t''envoyer des photos en gros plan si tu veux.',
     3, 5, '2024-06-08 09:45:00'),
(8,  'Oui carrément envoie les photos. Et tu es à Bordeaux centre ? J''ai pas de voiture, je pourrais venir en tram.',
     5, 3, '2024-06-08 10:00:00'),
(9,  'Je t''envoie les photos par ici. Je suis quartier Saint-Michel, à 10min à pied de la gare tram Saint-Michel - Victoire. Très accessible. On peut se mettre d''accord sur un créneau cette semaine si les photos te conviennent.',
     3, 5, '2024-06-08 10:30:00'),
(10, 'Les photos sont nickel, les traces sont vraiment minimes. Je suis dispo mercredi soir ou jeudi en journée. Tu as une préférence ?',
     5, 3, '2024-06-08 11:00:00'),
(11, 'Jeudi 14h ça m''arrange bien. On se dit ça alors !',
     3, 5, '2024-06-08 11:15:00');

-- Conversation 3 : Marie répond à la demande de vélo d'Emma (annonce #24)
INSERT INTO message (id, content, author_id, recipient_id, created_at) VALUES
(12, 'Bonjour Emma, j''ai vu votre annonce pour un vélo de ville. J''en ai justement un à vendre que je n''ai pas encore publié : une Btwin Elops 120, taille M, avec panier avant et garde-boue intégrés. 3 ans, très bon état. 95€. Ça pourrait vous intéresser ?',
     2, 6, '2024-06-05 15:00:00'),
(13, 'Oh super ça correspond exactement à ce que je cherche ! Il y a combien de vitesses ? Et vous êtes sur Nantes ou vous pouvez livrer ?',
     6, 2, '2024-06-05 15:30:00'),
(14, 'C''est un 7 vitesses Shimano, très agréable en ville. Je suis sur Nantes Ile de Nantes. Je peux pas livrer mais vous pouvez venir le chercher facilement, y''a du parking vélo partout ici. Vous voulez une photo ?',
     2, 6, '2024-06-05 16:00:00'),
(15, 'Oui une photo serait super ! Et je peux venir ce week-end si ça vous convient.',
     6, 2, '2024-06-05 16:20:00'),
(16, 'Je vous envoie les photos. Samedi matin vous convient ? Vers 10h ?',
     2, 6, '2024-06-05 17:00:00'),
(17, 'Samedi 10h c''est parfait ! À ce weekend alors, merci beaucoup.',
     6, 2, '2024-06-05 17:15:00');

-- Conversation 4 : Sophie cherche quelqu'un pour ses cours de guitare (annonce #23)
INSERT INTO message (id, content, author_id, recipient_id, created_at) VALUES
(18, 'Bonjour Emma, j''ai vu votre annonce pour les cours de guitare. Je suis débutante complète, vous prenez vraiment des gens qui n''ont jamais touché une guitare ? Et vous prêtez une guitare ou je dois en avoir une ?',
     4, 6, '2024-06-24 11:00:00'),
(19, 'Bonjour Sophie ! Oui bien sûr, j''ai plusieurs élèves débutants complets et c''est même souvent les plus motivants à enseigner. Pour la guitare, j''en ai une de prêt les 2-3 premiers cours, le temps que vous voyiez si ça vous plaît vraiment.',
     6, 4, '2024-06-24 11:45:00'),
(20, 'Super, c''est vraiment sympa le prêt. Je suis sur Lyon 6ème, vous vous déplacez à domicile ou c''est chez vous ?',
     4, 6, '2024-06-24 12:00:00'),
(21, 'Je me déplace à domicile oui, c''est inclus dans le tarif. Lyon 6ème c''est parfait pour moi. Quand aimeriez-vous commencer ?',
     6, 4, '2024-06-24 12:30:00'),
(22, 'Dès que possible ! La semaine prochaine vous avez un créneau en soirée, vers 19h ou 20h ?',
     4, 6, '2024-06-24 13:00:00'),
(23, 'Merdi soir 19h je suis libre. On peut commencer comme ça ! Je vous envoie mon adresse en message privé.',
     6, 4, '2024-06-24 13:30:00');

-- ----------------------------------------------------------
-- Threads de conversations (private_messages)
-- ----------------------------------------------------------
INSERT INTO private_messages (user_id, recipient_id) VALUES
(6, 3),  -- Emma <-> Thomas (MacBook)
(3, 6),
(5, 3),  -- Lucas <-> Thomas (Canapé)
(3, 5),
(2, 6),  -- Marie <-> Emma (Vélo)
(6, 2),
(4, 6),  -- Sophie <-> Emma (Guitare)
(6, 4);

SET FOREIGN_KEY_CHECKS = 1;
