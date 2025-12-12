<?php
session_start();
require_once 'db_config.php';

// Nome visitatore
$nome_visitatore = isset($_SESSION['username']) ? $_SESSION['username'] . ' (Logged)' : 'Utente Web';

// Registriamo il visitatore
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO visitatori (nome) VALUES (:nome)");
        $stmt->execute(['nome' => $nome_visitatore]);
        $messaggio_db = "Nuovo accesso registrato nel DB!";
        $class_messaggio = "success";
    } catch (PDOException $e) {
        $messaggio_db = "Errore Scrittura: " . $e->getMessage();
        $class_messaggio = "error";
    }
} else {
    $messaggio_db = "Connessione al Database non riuscita (controlla db_config.php).";
    $class_messaggio = "error";
}

// Recuperiamo i libri consigliati in base all'utente loggato
if (isset($pdo)) {
    try {
        if (isset($_SESSION['username'])) {
            // Se l'utente è loggato, puoi filtrare i libri consigliati
            $username = $_SESSION['username'];
            $stmt = $pdo->prepare("SELECT * FROM libri WHERE consigliato_per = :username");
            $stmt->execute(['username' => $username]);
        } else {
            // Se non è loggato, mostra tutti i libri
            $stmt = $pdo->prepare("SELECT * FROM libri");
            $stmt->execute();
        }
        $libri = $stmt->fetchAll();
    } catch (PDOException $e) {
        $libri = [];
        $messaggio_db = "Errore caricamento libri: " . $e->getMessage();
        $class_messaggio = "error";
    }
} else {
    $libri = [];
}

?>

<?php require_once './src/includes/header.php'; ?>
<?php require_once './src/includes/navbar.php'; ?>

<div class="page_contents">
    <?php if (!empty($messaggio_db)): ?>
        <div class="<?= $class_messaggio ?>"><?= htmlspecialchars($messaggio_db) ?></div>
    <?php endif; ?>

    <?php if (!empty($libri)): ?>
        <?php foreach ($libri as $libro): ?>
            <div class="libro">
                <h3><?= htmlspecialchars($libro['titolo']) ?></h3>
                <p><?= htmlspecialchars($libro['descrizione']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Nessun libro disponibile al momento.</p>
    <?php endif; ?>
</div>

<?php require_once './src/includes/footer.php'; ?>
