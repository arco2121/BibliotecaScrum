<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log');


session_start();
require_once 'db_config.php';

$messaggio_db = "";

// Recupera codice utente se loggato
$codice = $_SESSION['codice_utente'] ?? null;

// ---------------- POPOLARI ----------------
$stmt = $pdo->query("
    SELECT l.isbn
    FROM libri l
    JOIN recensioni r ON r.isbn = l.isbn
    GROUP BY l.isbn
    ORDER BY AVG(r.voto) DESC
    LIMIT 10
");
$popolari = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- CATEGORIE POPOLARI ----------------
$catPopolari = [];
$stmt = $pdo->query("SELECT id_categoria, categoria FROM categorie LIMIT 5");
$catPopolari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoriePopolari = [];
foreach ($catPopolari as $cat) {
    $stmt = $pdo->prepare("
        SELECT l.isbn
        FROM libri l
        JOIN libro_categoria lc ON lc.isbn = l.isbn
        WHERE lc.id_categoria = ?
        LIMIT 10
    ");
    $stmt->execute([$cat['id_categoria']]);
    $categoriePopolari[$cat['categoria']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------- PRESTITI ATTIVI ----------------
$prestiti_attivi = [];
if ($codice) {
    $stmt = $pdo->prepare("
        SELECT c.isbn
        FROM prestiti p
        JOIN copie c ON p.id_copia = c.id_copia
        WHERE p.codice_alfanumerico = ? AND p.data_restituzione IS NULL
    ");
    $stmt->execute([$codice]);
    $prestiti_attivi = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------- HTML HEADER ----------------
require './src/includes/header.php';
require './src/includes/navbar.php';

function getCoverPath(string $isbn): string {
    $localPath = "public/bookCover/$isbn.png";
    $publicPath = "public/bookCover/$isbn.png";

    if (file_exists($localPath)) {
        return $publicPath;
    }

    return "public/assets/book_placeholder.jpg";
}

?>


<style>
.grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.card.cover-only {
    flex: 0 0 auto;
    width: 120px;
    height: 180px;
    overflow: hidden;
    display: block; /* Aggiunto per garantire il comportamento corretto del tag <a> */
    cursor: pointer;
}

.card.cover-only img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>


<div class="page_contents">
    <h1>Home</h1>

    <?php if ($messaggio_db): ?>
        <pre class="message"><?= $messaggio_db ?></pre>
    <?php endif; ?>

    <?php if ($prestiti_attivi): ?>
        <div class="section">
            <h2>I tuoi prestiti attivi</h2>
            <div class="grid">
                <?php foreach ($prestiti_attivi as $libro): ?>
                    <a href="./libro?isbn=<?= $libro['isbn'] ?>" class="card cover-only" data-isbn="<?= $libro['isbn'] ?>">
                        <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>Libri Popolari</h2>
        <div class="grid">
            <?php foreach ($popolari as $libro): ?>
                <a href="./libro?isbn=<?= $libro['isbn'] ?>" class="card cover-only" data-isbn="<?= $libro['isbn'] ?>">
                    <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ($categoriePopolari as $catName => $libriCat): ?>
        <div class="section">
            <h2><?= $catName?></h2>
            <div class="grid">
                <?php if ($libriCat): ?>
                    <?php foreach ($libriCat as $libro): ?>
                        <a href="./libro?isbn=<?= $libro['isbn'] ?>" class="card cover-only" data-isbn="<?= $libro['isbn'] ?>">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div>Nessun libro</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require './src/includes/footer.php'; ?>