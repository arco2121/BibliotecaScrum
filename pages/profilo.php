<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', '/var/www/html/php_errors.log');

session_start();
require_once 'db_config.php';

$messaggio_db = "";

/* ---- Controllo login ---- */
$codice = $_SESSION['codice_utente'] ?? null;
if (!$codice) {
    die('Utente non autenticato');
}

if (!isset($pdo)) {
    die('Could not connect to the database.');
}

/* ---- Info Utente ---- */
$stm = $pdo->prepare("SELECT * FROM utenti WHERE codice_alfanumerico = ?");
$stm->execute([$codice]);
$utente = $stm->fetch(PDO::FETCH_ASSOC);

/* ---- Prestiti attivi ---- */
$stm = $pdo->prepare("
 SELECT c.isbn FROM prestiti p
        JOIN copie c ON p.id_copia = c.id_copia
        WHERE p.codice_alfanumerico = ? AND p.data_restituzione IS NULL
");
$stm->execute([$codice]);
$prestiti_attivi = $stm->fetchAll(PDO::FETCH_ASSOC);

/* ---- Prenotazioni ---- */
$stm = $pdo->prepare("
    SELECT p.isbn
    FROM prenotazioni p
    WHERE p.codice_alfanumerico = ?
");
$stm->execute([$codice]);
$prenotazioni = $stm->fetchAll(PDO::FETCH_ASSOC);

/* ---- Libri letti (Da rivedere la query) ---- */
$stm = $pdo->prepare("
 SELECT c.isbn as isbn FROM prestiti p JOIN copie c ON p.id_copia = c.id_copia WHERE p.codice_alfanumerico = ? AND p.data_restituzione IS NOT NULL
");
$stm->execute([$codice]);
$libri_letti = $stm->fetchAll(PDO::FETCH_ASSOC);;

/* ---- Badge (Da rivedere la query) ---- */
$stm = $pdo->prepare("
 SELECT u.id_badge as id_badge, b.icona as icona FROM utente_badge u JOIN badge b ON u.id_badge = b.id_badge WHERE u.id_ub = ?
");
$stm->execute([$codice]);
$badges = $stm->fetchAll(PDO::FETCH_ASSOC);

/* ---- Header / Navbar ---- */
require './src/includes/header.php';
require './src/includes/navbar.php';

function getCoverPath(string $isbn): string {
    $localPath = "public/bookCover/$isbn.png";
    if (file_exists($localPath)) {
        return $localPath;
    }
    return "public/assets/book_placeholder.jpg";
}
?>

<style>
    .grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .card.cover-only {
        width: 120px;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: #333;
    }
    .card.cover-only img {
        width: 120px;
        height: 180px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    /*Info class style */
    .info_cover
    {
        background-color: #819e71;
        gap: 15px;
        padding: 10px 5px;
        border-radius: 15px;
        border: solid 5px #3f5135;
        display: flex;
        flex-direction: column;
    }
    .info_cover input {
        border-radius: 5px;
        border: solid 2px #3f5135;
        padding: 5px;
    }
    .info_column
    {
        display: flex;
        flex-direction: column;
        width: auto;
        height: 100%;
        justify-content: flex-start;
        align-items: center;
        gap: 15px;
        padding: 0px 10px;
        margin: 5px 10px;
    }
    .info_line
    {
        display: flex;
        flex-direction: row;
        width: 100%;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }
    .info_pfp
    {
        border-radius: 100%;
        width: 240px;
        height: 240px;
        padding: 5px;
        border: solid 5px #3f5135;
    }
    .extend_all
    {
        width: 100%;
        flex: 1;
        gap: 20px;
    }
    .section
    {
        width: 100%;
        height: auto;
        display: flex;
        flex-direction: column;

        .grid {
            width: 100%;
            padding: 5px;
        }
    }
    .info_line:first-child
    {
        height: 300px;
    }
</style>

<?php if ($messaggio_db): ?>
    <pre class="message"><?= htmlspecialchars($messaggio_db) ?></pre>
<?php endif; ?>

<div class="info_line">

    <div class="info_column">

        <img class="info_pfp" alt="Pfp" src="<?= htmlspecialchars($utente['icona'] ?? 'public/assets/base_pfp.png') ?>">
        <div class="info_cover">
            <input type="text" disabled value="<?= htmlspecialchars($utente['username'] ?? '') ?>">
            <input type="text" disabled value="<?= htmlspecialchars($utente['nome'] ?? '') ?>">
            <input type="text" disabled value="<?= htmlspecialchars($utente['cognome'] ?? '') ?>">
            <input type="text" disabled value="<?= htmlspecialchars($utente['codice_fiscale'] ?? '') ?>">
            <input type="text" disabled value="<?= htmlspecialchars($utente['email'] ?? '') ?>">
        </div>

    </div>

    <div class="info_column extend_all">

        <div class="section">
            <h2>Badge</h2>
            <div class="grid">
                <?php if ($badges): ?>
                    <?php foreach ($badges as $badge): ?>
                        <a href="./prestiti?id=<?= $badge['id_badge'] ?>" class="badge cover-only">
                            <img src="<?= $badge['icona'] ?? 'public/assets/icon-png' ?>" alt="Badge">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <h4>Nessun badge acquisito</h4>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>Prestiti</h2>
            <div class="grid">
                <?php if ($prestiti_attivi): ?>
                    <?php foreach ($prestiti_attivi as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <h4>Nessun prestito trovato</h4>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>Prenotazioni</h2>
            <div class="grid">
                <?php if ($prenotazioni): ?>
                    <?php foreach ($prenotazioni as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <h4>Nessuna prenotazione trovata</h4>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>Libri Letti</h2>
            <div class="grid">
                <?php if ($libri_letti): ?>
                    <?php foreach ($libri_letti as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <h4>Nessun libro ancora letto</h4>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require './src/includes/footer.php'; ?>
