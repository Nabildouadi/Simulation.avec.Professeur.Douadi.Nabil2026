<?php
/**
 * ==========================================================================
 *  PLATEFORME PÉDAGOGIQUE — INSFP EL HIDHAB 2026
 *  Professeur Douadi Nabil
 * --------------------------------------------------------------------------
 *  FICHIER PHP UNIQUE : configuration + connexion MySQL + installation
 *  automatique de la base + API JSON utilisée par index.html.
 *
 *  Les 3 fichiers du projet :
 *    1. index.html    → toute l'interface (HTML + CSS + JavaScript)
 *    2. api.php       → ce fichier (serveur : connexion + API + installation)
 *    3. database.sql  → script SQL de la base (facultatif : api.php la crée)
 *
 *  Utilisation :
 *    - Ouvrir http://127.0.0.1:8080/api.php    → page d'état / installation
 *    - Ouvrir http://127.0.0.1:8080/index.html → l'application
 *
 *  Compte professeur : créé automatiquement lors de l'installation
 *  (empreintes SHA-256 salées, aucun mot de passe en clair dans ce fichier).
 * ==========================================================================
 */

declare(strict_types=1);

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

/* ==========================================================================
   1. CONFIGURATION DE LA CONNEXION
   --------------------------------------------------------------------------
   Ordre de priorité :
     1. Variables d'environnement INSFP_DB_HOST / PORT / USER / PASS / NAME
     2. Fichier serveur/db-config.json (écrit par le lanceur portable)
     3. Valeurs par défaut XAMPP / WAMP / Laragon : 127.0.0.1:3306, root, ""
   Si le port demandé ne répond pas, on essaie automatiquement 3306 puis 3307.
   ========================================================================== */

if (!function_exists('insfp_db_config')) {
    function insfp_db_config(): array
    {
        $cfg = [
            'host' => '127.0.0.1',
            'port' => 0,
            'user' => 'root',
            'pass' => '',
            'db'   => 'insfp_projets',
        ];

        $envMap = [
            'INSFP_DB_HOST' => 'host',
            'INSFP_DB_USER' => 'user',
            'INSFP_DB_PASS' => 'pass',
            'INSFP_DB_NAME' => 'db',
        ];
        foreach ($envMap as $env => $key) {
            $value = getenv($env);
            if ($value !== false && $value !== '') {
                $cfg[$key] = $value;
            }
        }
        $envPort = getenv('INSFP_DB_PORT');
        if ($envPort !== false && $envPort !== '') {
            $cfg['port'] = (int) $envPort;
        }

        $file = __DIR__ . DIRECTORY_SEPARATOR . 'serveur' . DIRECTORY_SEPARATOR . 'db-config.json';
        if (is_file($file)) {
            $json = json_decode((string) @file_get_contents($file), true);
            if (is_array($json)) {
                foreach (['host', 'port', 'user', 'pass', 'db'] as $key) {
                    if (isset($json[$key]) && $json[$key] !== '') {
                        $cfg[$key] = $json[$key];
                    }
                }
            }
        }

        $cfg['port'] = (int) $cfg['port'];
        return $cfg;
    }
}

if (!function_exists('insfp_db_ports')) {
    function insfp_db_ports(array $cfg): array
    {
        $ports = [];
        if (($cfg['port'] ?? 0) > 0) {
            $ports[] = (int) $cfg['port'];
        }
        foreach ([3306, 3307] as $fallback) {
            if (!in_array($fallback, $ports, true)) {
                $ports[] = $fallback;
            }
        }
        return $ports;
    }
}

if (!function_exists('insfp_db_connect')) {
    function insfp_db_connect(array $cfg, ?string $dbname = null): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $lastError = null;
        foreach (insfp_db_ports($cfg) as $port) {
            $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $port . ';charset=utf8mb4';
            if ($dbname !== null && $dbname !== '') {
                $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8mb4';
            }
            try {
                return new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        if ($lastError === null) {
            $lastError = new PDOException('Aucun serveur MySQL joignable.');
        }
        throw $lastError;
    }
}

/* ==========================================================================
   2. STRUCTURE DE LA BASE (identique à database.sql)
   ========================================================================== */

if (!function_exists('insfp_schema_sql')) {
    function insfp_schema_sql(): string
    {
        return <<<'SQL'
CREATE DATABASE IF NOT EXISTS `insfp_projets` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `insfp_projets`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(128) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `prof_name` VARCHAR(100) DEFAULT 'Douadi Nabil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `specialites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stagiaires` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NOT NULL UNIQUE,
    `password` VARCHAR(4) NOT NULL,
    `specialite_id` INT NULL,
    `semestre` ENUM('S1','S2','S3','S4','S5') DEFAULT 'S1',
    `photo` LONGTEXT NULL,
    FOREIGN KEY (`specialite_id`) REFERENCES `specialites`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(255) NOT NULL,
    `methode` TEXT NULL,
    `data_json` LONGTEXT NULL,
    `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date_modification` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_membres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `stagiaire_id` INT NULL,
    `nom_libre` VARCHAR(100) NULL,
    `prenom_libre` VARCHAR(100) NULL,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaires`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_objectifs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_etapes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_materiel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `position` INT NOT NULL,
    `image_data` LONGTEXT NOT NULL,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projet_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `critere_id` VARCHAR(50) NOT NULL,
    `critere_texte` VARCHAR(255) NULL,
    `points_100` FLOAT DEFAULT 0,
    `points_20` FLOAT DEFAULT 0,
    `note_obtenue` FLOAT DEFAULT 0,
    FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    }
}

/* ==========================================================================
   3. INSTALLATION / MISE À JOUR AUTOMATIQUE
   ========================================================================== */

if (!function_exists('insfp_install')) {
    /**
     * Crée la base, les tables et les données de départ.
     * @return array{ok:bool,message:string,etapes:array}
     */
    function insfp_install(array $config): array
    {
        $etapes = [];

        $pdo = insfp_db_connect($config, null);

        $sql = insfp_schema_sql();
        $fichier = __DIR__ . DIRECTORY_SEPARATOR . 'database.sql';
        if (is_file($fichier)) {
            $contenu = (string) @file_get_contents($fichier);
            if (trim($contenu) !== '') {
                $sql = $contenu;
            }
        }
        $pdo->exec($sql);
        $etapes[] = 'Base « ' . $config['db'] . ' » et tables vérifiées.';

        // Compatibilité avec une base déjà installée (ancienne version)
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `projets` LIKE 'data_json'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `projets` ADD COLUMN `data_json` LONGTEXT NULL");
                $etapes[] = 'Colonne projets.data_json ajoutée.';
            }
        } catch (PDOException $e) {
            // Table absente : le schéma vient de la créer normalement.
        }

        // Anciennes installations : colonne username trop courte pour un SHA-256
        try {
            $pdo->exec("ALTER TABLE `users` MODIFY `username` VARCHAR(128) NOT NULL");
        } catch (PDOException $e) {
            // Ignorer.
        }

        $pdo->exec("USE `" . $config['db'] . "`");

        // Empreintes SHA-256 du compte professeur initial (aucun mot de passe en clair)
        $adminUserHash = '7a6c15bcdcf959c71d1dd7e4bf64bddb982fb01a32365d831dd7dacac12c20ed';
        $adminPassHash = '0300ef4631a500641e7ec2af25987f053712342796746e2ca4b7899514546f53';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$adminUserHash]);
        if ((int) $stmt->fetchColumn() === 0) {
            $insert = $pdo->prepare("INSERT INTO users (username, password, prof_name) VALUES (?, ?, ?)");
            $insert->execute([$adminUserHash, $adminPassHash, 'Douadi Nabil']);
            $etapes[] = 'Compte professeur initial créé.';
        }

        // Spécialités par défaut
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM specialites");
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            $specs = [
                'Chimie Industrielle',
                'Analyse et Contrôle Qualité',
                'Pharmacie',
                'Biologie',
                'Industrie Agroalimentaire',
                "Protection de l'Environnement",
                'Autre',
            ];
            $insertSpec = $pdo->prepare("INSERT IGNORE INTO specialites (nom) VALUES (?)");
            foreach ($specs as $s) {
                $insertSpec->execute([$s]);
            }
            $etapes[] = count($specs) . ' spécialités ajoutées.';
        }

        return ['ok' => true, 'message' => 'Installation terminée.', 'etapes' => $etapes];
    }
}

if (!function_exists('insfp_boot')) {
    /**
     * Connexion à la base ; si elle n'existe pas encore, elle est créée.
     */
    function insfp_boot(array $config): PDO
    {
        try {
            return insfp_db_connect($config, $config['db']);
        } catch (PDOException $e) {
            $code = (int) $e->getCode();
            $msg = $e->getMessage();
            if ($code === 1049 || stripos($msg, 'Unknown database') !== false || $code === 1045) {
                insfp_install($config);
                return insfp_db_connect($config, $config['db']);
            }
            throw $e;
        }
    }
}

/* ==========================================================================
   4. OUTILS DE RÉPONSE
   ========================================================================== */

if (!function_exists('insfp_json')) {
    function insfp_json($data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('insfp_erreur')) {
    function insfp_erreur(string $message, int $http = 200): void
    {
        if ($http >= 400) {
            http_response_code($http);
        }
        insfp_json(['status' => 'error', 'message' => $message]);
    }
}

if (!function_exists('insfp_h')) {
    function insfp_h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

/* ==========================================================================
   5. LECTURE DE LA REQUÊTE
   ========================================================================== */

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$inputJSON = null;

$rawBody = file_get_contents('php://input');
if (is_string($rawBody) && $rawBody !== '') {
    $decode = json_decode($rawBody, true);
    if (is_array($decode)) {
        $inputJSON = $decode;
        if (isset($decode['action'])) {
            $action = $decode['action'];
        }
    }
}
$action = is_string($action) ? trim($action) : '';

$config = insfp_db_config();

/* ==========================================================================
   6. PAGE D'ÉTAT (aucune action demandée)
   ========================================================================== */

if ($action === '') {
    $pdo      = null;
    $erreur   = null;
    $tables   = [];
    $portOk   = null;

    try {
        $pdo = insfp_db_connect($config, $config['db']);
        $portOk = true;
    } catch (PDOException $e) {
        $erreur = $e->getMessage();
    }

    if ($pdo !== null) {
        try {
            $noms = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($noms as $t) {
                $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`')->fetchColumn();
                $tables[$t] = $n;
            }
        } catch (PDOException $e) {
            $erreur = $e->getMessage();
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    $phpOk  = version_compare(PHP_VERSION, '7.4', '>=');
    $pdoOk  = extension_loaded('pdo_mysql');
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Serveur — Plateforme INSFP EL HIDHAB 2026</title>
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Tahoma,sans-serif;background:linear-gradient(160deg,#07131f,#0b2233 55%,#071720);color:#eaf3fb;min-height:100vh;display:flex;justify-content:center;padding:36px 18px}
    .card{width:100%;max-width:840px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.15);border-radius:18px;padding:28px;box-shadow:0 20px 50px rgba(0,0,0,.4);align-self:flex-start}
    h1{font-size:1.5rem;margin-bottom:4px}
    .sub{color:#9fb6c9;font-size:.9rem;margin-bottom:22px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:22px}
    .box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:14px}
    .box .k{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#8fb3c9;font-weight:800}
    .box .v{font-size:.95rem;margin-top:6px;word-break:break-word}
    .ok{color:#34d399;font-weight:700}
    .ko{color:#fb7185;font-weight:700}
    .msg{border-radius:12px;padding:14px 16px;font-size:.9rem;line-height:1.6;margin-bottom:18px}
    .msg.err{background:rgba(251,113,133,.12);border:1px solid rgba(251,113,133,.45);color:#ffd7dd}
    .msg.good{background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.45);color:#ccfbf1}
    table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:.88rem}
    th,td{text-align:left;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.1)}
    th{color:#9fd9ff;font-size:.76rem;text-transform:uppercase;letter-spacing:.08em}
    .actions{display:flex;gap:10px;flex-wrap:wrap}
    button,a.btn{border:0;cursor:pointer;font:inherit;font-weight:800;font-size:.9rem;padding:12px 18px;border-radius:11px;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
    #btnInstall{background:linear-gradient(135deg,#7dd3fc,#34d399);color:#04121c}
    #btnPing{background:rgba(255,255,255,.12);color:#eaf3fb;border:1px solid rgba(255,255,255,.22)}
    a.btn{background:linear-gradient(135deg,#fbbf24,#f97316);color:#241000}
    button[disabled]{opacity:.55;cursor:default}
    #retour{font-size:.85rem;color:#bfe3f7;margin-top:16px;min-height:20px}
    code{background:rgba(255,255,255,.1);padding:1px 6px;border-radius:5px;font-size:.85em}
</style>
</head>
<body>
<div class="card">
    <h1>Serveur de la plateforme</h1>
    <div class="sub">INSFP El Hidhab — Les Projets des Stagiaires · Professeur Douadi Nabil · 2026</div>

    <?php if ($erreur !== null): ?>
        <div class="msg err">
            <strong>Base de données non joignable.</strong><br>
            <?php echo insfp_h($erreur); ?><br><br>
            Démarrez MySQL (XAMPP / WAMP / Laragon) puis cliquez sur <strong>Installer / réparer la base</strong>.
            Ports essayés : <?php echo insfp_h(implode(', ', insfp_db_ports($config))); ?>.
        </div>
    <?php else: ?>
        <div class="msg good"><strong>Connexion MySQL réussie.</strong> La base <code><?php echo insfp_h($config['db']); ?></code> est accessible.</div>
    <?php endif; ?>

    <div class="grid">
        <div class="box"><div class="k">Serveur MySQL</div><div class="v"><?php echo insfp_h($config['host']); ?> — ports <?php echo insfp_h(implode(' / ', insfp_db_ports($config))); ?></div></div>
        <div class="box"><div class="k">Base</div><div class="v"><?php echo insfp_h($config['db']); ?></div></div>
        <div class="box"><div class="k">Utilisateur MySQL</div><div class="v"><?php echo insfp_h($config['user']); ?></div></div>
        <div class="box"><div class="k">PHP</div><div class="v"><?php echo insfp_h(PHP_VERSION); ?> <?php echo $phpOk ? '<span class="ok">OK</span>' : '<span class="ko">trop ancien</span>'; ?></div></div>
        <div class="box"><div class="k">Extension PDO MySQL</div><div class="v"><?php echo $pdoOk ? '<span class="ok">activée</span>' : '<span class="ko">manquante</span>'; ?></div></div>
    </div>

    <?php if (!empty($tables)): ?>
        <table>
            <thead><tr><th>Table</th><th>Enregistrements</th></tr></thead>
            <tbody>
            <?php foreach ($tables as $t => $n): ?>
                <tr><td><?php echo insfp_h($t); ?></td><td><?php echo (int) $n; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="actions">
        <button id="btnInstall">Installer / réparer la base</button>
        <button id="btnPing">Tester l'API</button>
        <a class="btn" href="index.html">Ouvrir l'application</a>
    </div>
    <div id="retour"></div>
</div>
<script>
    const retour = document.getElementById('retour');
    document.getElementById('btnInstall').addEventListener('click', async function () {
        const b = this; b.disabled = true; retour.textContent = 'Installation en cours...';
        try {
            const r = await fetch('api.php?action=install', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
            const j = await r.json();
            if (j.status === 'success') {
                retour.innerHTML = '✔ ' + (j.message || 'Installation terminée.') + ' Rechargement...';
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                retour.textContent = '✖ ' + (j.message || j.error || 'Erreur inconnue');
            }
        } catch (e) {
            retour.textContent = '✖ ' + e.message;
        }
        b.disabled = false;
    });
    document.getElementById('btnPing').addEventListener('click', async function () {
        const b = this; b.disabled = true; retour.textContent = 'Test en cours...';
        try {
            const r = await fetch('api.php?action=ping', { cache: 'no-store' });
            const j = await r.json();
            retour.textContent = '✔ API disponible : ' + JSON.stringify(j);
        } catch (e) {
            retour.textContent = '✖ ' + e.message;
        }
        b.disabled = false;
    });
</script>
</body>
</html>
    <?php
    exit;
}

/* ==========================================================================
   7. API JSON
   ========================================================================== */

try {
    if ($action === 'install') {
        $resultat = insfp_install($config);
        insfp_json([
            'status'  => 'success',
            'message' => $resultat['message'],
            'etapes'  => $resultat['etapes'],
            'mode'    => 'normal',
        ]);
        exit;
    }

    if ($action === 'ping') {
        insfp_json(['status' => 'success', 'mode' => 'normal', 'php' => PHP_VERSION]);
        exit;
    }

    $pdo = insfp_boot($config);
} catch (PDOException $e) {
    insfp_json([
        'status' => 'error',
        'error'  => 'Erreur de connexion : ' . $e->getMessage(),
        'hint'   => "Démarrez le serveur MySQL puis ouvrez api.php pour installer la base. Sur XAMPP/WAMP : port 3306. Version portable : lancez le serveur inclus.",
    ]);
    exit;
}

switch ($action) {

    /* ---------------------------------------------------------------
       AUTHENTIFICATION
       --------------------------------------------------------------- */
    case 'login_prof':
        $userHash = $inputJSON['userHash'] ?? $_POST['userHash'] ?? '';
        $passHash = $inputJSON['passHash'] ?? $_POST['passHash'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$userHash, $passHash]);
        $user = $stmt->fetch();

        if ($user) {
            insfp_json(['status' => 'success', 'role' => 'admin', 'prof_name' => $user['prof_name']]);
        } else {
            insfp_erreur('Identifiant ou mot de passe incorrect.');
        }
        break;

    case 'change_password':
        $userHash    = $inputJSON['userHash'] ?? $_POST['userHash'] ?? '';
        $currentHash = $inputJSON['currentHash'] ?? $_POST['currentHash'] ?? '';
        $newHash     = $inputJSON['newHash'] ?? $_POST['newHash'] ?? '';

        if ($userHash === '' || $currentHash === '' || $newHash === '') {
            insfp_erreur('Données manquantes.');
            break;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$userHash, $currentHash]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            insfp_erreur('Mot de passe actuel incorrect.');
            break;
        }

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        insfp_json(['status' => 'success']);
        break;

    case 'login_stu':
        $phone = $inputJSON['phone'] ?? $_POST['phone'] ?? '';
        $pass  = $inputJSON['pass'] ?? $_POST['pass'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM stagiaires WHERE phone = ? AND password = ?");
        $stmt->execute([$phone, $pass]);
        $stu = $stmt->fetch();

        if ($stu) {
            insfp_json(['status' => 'success', 'role' => 'stagiaire', 'data' => $stu]);
        } else {
            insfp_erreur('Numéro ou mot de passe incorrect.');
        }
        break;

    /* ---------------------------------------------------------------
       PROFILS STAGIAIRES
       --------------------------------------------------------------- */
    case 'get_profiles':
        $stmt = $pdo->query("SELECT s.*, sp.nom AS specialite_nom
                             FROM stagiaires s
                             LEFT JOIN specialites sp ON s.specialite_id = sp.id
                             ORDER BY s.id DESC");
        $profiles = $stmt->fetchAll();

        $formatted = array_map(function ($p) {
            return [
                'id'         => (string) $p['id'],
                'id_db'      => (string) $p['id'],
                'nom'        => $p['nom'],
                'prenom'     => $p['prenom'],
                'phone'      => $p['phone'],
                'pass'       => $p['password'],
                'specialite' => $p['specialite_nom'] ?? '',
                'semestre'   => $p['semestre'],
                'photo'      => $p['photo'] ?? '',
            ];
        }, $profiles);

        $stmtSpec = $pdo->query("SELECT nom FROM specialites ORDER BY nom ASC");
        $specialites = $stmtSpec->fetchAll(PDO::FETCH_COLUMN);

        insfp_json(['status' => 'success', 'profils' => $formatted, 'specialites' => $specialites]);
        break;

    case 'save_profile':
        $data = $inputJSON['profil'] ?? null;
        if (!$data) {
            insfp_erreur('Données invalides.');
            break;
        }

        $specId = null;
        if (!empty($data['specialite'])) {
            $stmtSpec = $pdo->prepare("SELECT id FROM specialites WHERE nom = ?");
            $stmtSpec->execute([$data['specialite']]);
            $specId = $stmtSpec->fetchColumn() ?: null;
        }

        $idDb   = $data['id_db'] ?? $data['id'] ?? null;
        $existe = false;
        if (!empty($idDb) && is_numeric($idDb)) {
            $check = $pdo->prepare("SELECT id FROM stagiaires WHERE id = ?");
            $check->execute([$idDb]);
            $existe = (bool) $check->fetchColumn();
        }

        if ($existe) {
            $stmt = $pdo->prepare("UPDATE stagiaires
                                   SET nom = ?, prenom = ?, phone = ?, password = ?, specialite_id = ?, semestre = ?, photo = ?
                                   WHERE id = ?");
            $stmt->execute([
                $data['nom'], $data['prenom'] ?? '', $data['phone'], $data['pass'] ?? '',
                $specId, $data['semestre'] ?? 'S1', $data['photo'] ?? '', $idDb,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO stagiaires (nom, prenom, phone, password, specialite_id, semestre, photo)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['nom'], $data['prenom'] ?? '', $data['phone'], $data['pass'] ?? '',
                $specId, $data['semestre'] ?? 'S1', $data['photo'] ?? '',
            ]);
        }
        insfp_json(['status' => 'success']);
        break;

    case 'delete_profile':
        $phone = $inputJSON['phone'] ?? $_POST['phone'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM stagiaires WHERE phone = ?");
        $stmt->execute([$phone]);
        insfp_json(['status' => 'success']);
        break;

    /* ---------------------------------------------------------------
       SPÉCIALITÉS
       --------------------------------------------------------------- */
    case 'save_specialites':
        $specialites = $inputJSON['specialites'] ?? [];
        $nouvelles = [];
        foreach ((array) $specialites as $s) {
            $s = trim((string) $s);
            if ($s !== '' && !in_array($s, $nouvelles, true)) {
                $nouvelles[] = $s;
            }
        }

        $pdo->beginTransaction();
        if (count($nouvelles)) {
            $placeholders = implode(',', array_fill(0, count($nouvelles), '?'));
            $stmt = $pdo->prepare("DELETE FROM specialites WHERE nom NOT IN ($placeholders)");
            $stmt->execute($nouvelles);
        } else {
            $pdo->exec("DELETE FROM specialites");
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO specialites (nom) VALUES (?)");
        foreach ($nouvelles as $s) {
            $stmt->execute([$s]);
        }
        $pdo->commit();
        insfp_json(['status' => 'success']);
        break;

    /* ---------------------------------------------------------------
       PROJETS / FICHES PROJET
       --------------------------------------------------------------- */
    case 'get_projets':
        $stmt = $pdo->query("SELECT * FROM projets ORDER BY date_creation DESC");
        $projets = $stmt->fetchAll();

        $result = [];
        foreach ($projets as $p) {
            $pId = $p['id'];

            $stmtObj = $pdo->prepare("SELECT id, texte FROM projet_objectifs WHERE projet_id = ?");
            $stmtObj->execute([$pId]);
            $objectifs = $stmtObj->fetchAll();

            $stmtEt = $pdo->prepare("SELECT id, texte FROM projet_etapes WHERE projet_id = ?");
            $stmtEt->execute([$pId]);
            $etapes = $stmtEt->fetchAll();

            $stmtMat = $pdo->prepare("SELECT id, texte FROM projet_materiel WHERE projet_id = ?");
            $stmtMat->execute([$pId]);
            $materiel = $stmtMat->fetchAll();

            $stmtMem = $pdo->prepare("SELECT pm.*, s.nom, s.prenom, s.phone
                                      FROM projet_membres pm
                                      LEFT JOIN stagiaires s ON pm.stagiaire_id = s.id
                                      WHERE pm.projet_id = ?");
            $stmtMem->execute([$pId]);
            $membres = array_map(function ($m) {
                return [
                    'nom'    => $m['nom'] ?? $m['nom_libre'] ?? '',
                    'prenom' => $m['prenom'] ?? $m['prenom_libre'] ?? '',
                    'phone'  => $m['phone'] ?? '',
                ];
            }, $stmtMem->fetchAll());

            $stmtPho = $pdo->prepare("SELECT position, image_data FROM projet_photos WHERE projet_id = ? ORDER BY position ASC");
            $stmtPho->execute([$pId]);
            $photosData = $stmtPho->fetchAll();
            $photos = array_fill(0, 10, '');
            foreach ($photosData as $ph) {
                if ((int) $ph['position'] >= 0 && (int) $ph['position'] < 10) {
                    $photos[(int) $ph['position']] = $ph['image_data'];
                }
            }

            $stmtNot = $pdo->prepare("SELECT critere_id, note_obtenue FROM projet_notes WHERE projet_id = ?");
            $stmtNot->execute([$pId]);
            $notesArr = $stmtNot->fetchAll();
            $notes = [];
            foreach ($notesArr as $n) {
                $notes[$n['critere_id']] = (float) $n['note_obtenue'];
            }

            $item = [
                'id'        => (string) $p['id'],
                'nom'       => $p['nom'],
                'methode'   => $p['methode'],
                'cree'      => $p['date_creation'],
                'modif'     => strtotime((string) $p['date_modification']) * 1000,
                'membres'   => $membres,
                'objectifs' => $objectifs,
                'etapes'    => $etapes,
                'materiel'  => $materiel,
                'photos'    => $photos,
                'notes'     => $notes,
            ];

            if (!empty($p['data_json'])) {
                $saved = json_decode((string) $p['data_json'], true);
                if (is_array($saved)) {
                    $item = array_merge($item, $saved);
                }
            }
            $item['id']      = (string) $p['id'];
            $item['nom']     = $p['nom'];
            $item['methode'] = $p['methode'] ?? ($item['methode'] ?? '');
            if (empty($item['cree'])) {
                $item['cree'] = $p['date_creation'];
            }
            if (empty($item['modif'])) {
                $item['modif'] = strtotime((string) $p['date_modification']) * 1000;
            }
            $photosItem = is_array($item['photos'] ?? null) ? array_values($item['photos']) : [];
            $item['photos'] = array_slice(array_pad($photosItem, 10, ''), 0, 10);
            if (!isset($item['notes']) || !is_array($item['notes'])) {
                $item['notes'] = [];
            }

            $result[] = $item;
        }

        insfp_json(['status' => 'success', 'projets' => $result]);
        break;

    case 'save_projet':
        $p = $inputJSON['projet'] ?? null;
        if (!$p) {
            insfp_erreur('Projet manquant.');
            break;
        }

        $pdo->beginTransaction();

        $pId = null;
        if (!empty($p['id']) && is_numeric($p['id'])) {
            $check = $pdo->prepare("SELECT id FROM projets WHERE id = ?");
            $check->execute([$p['id']]);
            if ($check->fetchColumn()) {
                $pId = $p['id'];
            }
        }

        if ($pId !== null) {
            $stmt = $pdo->prepare("UPDATE projets SET nom = ?, methode = ? WHERE id = ?");
            $stmt->execute([$p['nom'], $p['methode'] ?? '', $pId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO projets (nom, methode) VALUES (?, ?)");
            $stmt->execute([$p['nom'], $p['methode'] ?? '']);
            $pId = $pdo->lastInsertId();
        }

        $p['id'] = (string) $pId;
        if (empty($p['cree'])) {
            $p['cree'] = date('c');
        }
        $p['modif'] = round(microtime(true) * 1000);
        $jsonData = json_encode($p, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            $jsonData = json_encode($p);
        }
        $stmtJson = $pdo->prepare("UPDATE projets SET data_json = ? WHERE id = ?");
        $stmtJson->execute([$jsonData, $pId]);

        $pdo->prepare("DELETE FROM projet_objectifs WHERE projet_id = ?")->execute([$pId]);
        $pdo->prepare("DELETE FROM projet_etapes WHERE projet_id = ?")->execute([$pId]);
        $pdo->prepare("DELETE FROM projet_materiel WHERE projet_id = ?")->execute([$pId]);
        $pdo->prepare("DELETE FROM projet_membres WHERE projet_id = ?")->execute([$pId]);
        $pdo->prepare("DELETE FROM projet_photos WHERE projet_id = ?")->execute([$pId]);
        $pdo->prepare("DELETE FROM projet_notes WHERE projet_id = ?")->execute([$pId]);

        if (!empty($p['objectifs'])) {
            $stmtObj = $pdo->prepare("INSERT INTO projet_objectifs (projet_id, texte) VALUES (?, ?)");
            foreach ($p['objectifs'] as $o) {
                if (!empty($o['texte'])) {
                    $stmtObj->execute([$pId, $o['texte']]);
                }
            }
        }

        if (!empty($p['etapes'])) {
            $stmtEt = $pdo->prepare("INSERT INTO projet_etapes (projet_id, texte) VALUES (?, ?)");
            foreach ($p['etapes'] as $e) {
                if (!empty($e['texte'])) {
                    $stmtEt->execute([$pId, $e['texte']]);
                }
            }
        }

        if (!empty($p['materiel'])) {
            $stmtMat = $pdo->prepare("INSERT INTO projet_materiel (projet_id, texte) VALUES (?, ?)");
            foreach ($p['materiel'] as $m) {
                if (!empty($m['texte'])) {
                    $stmtMat->execute([$pId, $m['texte']]);
                }
            }
        }

        if (!empty($p['membres'])) {
            $stmtMem = $pdo->prepare("INSERT INTO projet_membres (projet_id, nom_libre, prenom_libre) VALUES (?, ?, ?)");
            foreach ($p['membres'] as $mem) {
                if (!empty($mem['nom'])) {
                    $stmtMem->execute([$pId, $mem['nom'], $mem['prenom'] ?? '']);
                }
            }
        }

        if (!empty($p['photos'])) {
            $stmtPho = $pdo->prepare("INSERT INTO projet_photos (projet_id, position, image_data) VALUES (?, ?, ?)");
            foreach ($p['photos'] as $pos => $img) {
                if (!empty($img)) {
                    $stmtPho->execute([$pId, $pos, $img]);
                }
            }
        }

        if (!empty($p['notes'])) {
            $stmtNot = $pdo->prepare("INSERT INTO projet_notes (projet_id, critere_id, note_obtenue) VALUES (?, ?, ?)");
            foreach ($p['notes'] as $critId => $val) {
                $stmtNot->execute([$pId, $critId, (float) $val]);
            }
        }

        $pdo->commit();
        insfp_json(['status' => 'success', 'projet_id' => $pId]);
        break;

    case 'delete_projet':
        $id = $inputJSON['id'] ?? $_POST['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM projets WHERE id = ?");
            $stmt->execute([$id]);
        }
        insfp_json(['status' => 'success']);
        break;

    /* ---------------------------------------------------------------
       PARAMÈTRES / THÈME / GALERIE
       --------------------------------------------------------------- */
    case 'get_settings':
        $stmt = $pdo->query("SELECT * FROM settings");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
        insfp_json(['status' => 'success', 'settings' => $settings]);
        break;

    case 'save_settings':
        $key   = $inputJSON['key'] ?? $_POST['key'] ?? '';
        $value = $inputJSON['value'] ?? $_POST['value'] ?? '';

        if (!empty($key)) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        insfp_json(['status' => 'success']);
        break;

    /* ---------------------------------------------------------------
       STATISTIQUES (page api.php)
       --------------------------------------------------------------- */
    case 'stats':
        $stats = [];
        foreach (['users', 'specialites', 'stagiaires', 'projets', 'settings'] as $t) {
            try {
                $stats[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            } catch (PDOException $e) {
                $stats[$t] = null;
            }
        }
        insfp_json(['status' => 'success', 'stats' => $stats]);
        break;

    default:
        insfp_erreur('Action inconnue : ' . $action);
        break;
}
