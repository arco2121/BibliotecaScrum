<?php

require_once 'security.php';
if (!checkAccess('amministratore')) header('Location: ./');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includiamo la configurazione
require_once 'db_config.php';

// Inizializziamo il messaggio per evitare errori "Undefined variable"
$messaggio_db = "";

// --- 1. TEST SCRITTURA (INSERT) ---
// Eseguiamo l'INSERT solo se la connessione ($pdo) esiste
if (isset($pdo)) {
    try {
        // Se l'utente è loggato, usiamo il suo nome nel DB, altrimenti "Utente Web"
        $nome_visitatore = isset($_SESSION['username']) ? $_SESSION['username'] . ' (Logged)' : 'Utente Web';

        // ELIMINA (con CASCADE - elimina anche le recensioni)
        if (isset($_POST['delete_id'])) {
            // Prima conta le recensioni che verranno eliminate
            $stmt = $pdo->prepare("SELECT COUNT(*) as num FROM recensioni WHERE isbn = :isbn");
            $stmt->execute(['isbn' => $_POST['delete_id']]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);

            // Inizia una transazione per sicurezza
            $pdo->beginTransaction();

            try {
                // Prima elimina tutte le recensioni collegate
                if ($count['num'] > 0) {
                    $stmt = $pdo->prepare("DELETE FROM recensioni WHERE isbn = :isbn");
                    $stmt->execute(['isbn' => $_POST['delete_id']]);
                }

                // Poi elimina il libro
                $stmt = $pdo->prepare("DELETE FROM libri WHERE isbn = :isbn");
                $stmt->execute(['isbn' => $_POST['delete_id']]);

                // Conferma la transazione
                $pdo->commit();

                if ($count['num'] > 0) {
                    $_SESSION['messaggio'] = "Libro eliminato con successo insieme a " . $count['num'] . " recensione/i collegata/e!";
                } else {
                    $_SESSION['messaggio'] = "Libro eliminato con successo!";
                }
                $_SESSION['tipo_messaggio'] = "success";

            } catch (PDOException $e) {
                // Se c'è un errore, annulla tutto
                $pdo->rollBack();
                $_SESSION['messaggio'] = "ERRORE durante l'eliminazione: " . $e->getMessage();
                $_SESSION['tipo_messaggio'] = "error";
            }

            header("Location: dashboard-libri");
            exit;
        }

        // SALVA MODIFICA
        if (isset($_POST['edit_id'])) {
            $stmt = $pdo->prepare("
                UPDATE libri 
                SET titolo = :titolo, descrizione = :descrizione, anno_pubblicazione = :anno_pubblicazione
                WHERE isbn = :isbn
            ");
            $stmt->execute([
                    'titolo' => $_POST['titolo'],
                    'descrizione' => $_POST['descrizione'],
                    'anno_pubblicazione' => $_POST['anno_pubblicazione'],
                    'isbn' => $_POST['edit_id']
            ]);
            header("Location: dashboard-libri");
            exit;
        }

        //AGGIUNGI
        if (isset($_POST['inserisci'])) {
            $stmt = $pdo->prepare("
                INSERT INTO libri(isbn,titolo,descrizione,anno_pubblicazione)
                VALUES (:isbn,:titolo,:descrizione,:anno_pubblicazione)
            ");
            $stmt->execute([
                    'titolo' => $_POST['titolo'],
                    'descrizione' => $_POST['descrizione'],
                    'anno_pubblicazione' => $_POST['anno_pubblicazione'],
                    'isbn' => $_POST['isbn']
            ]);
            header("Location: dashboard-libri");
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO visitatori (nome) VALUES (:nome)");
            $stmt->execute(['nome' => $nome_visitatore]);
        } catch (PDOException $e) {
            // Tabella visitatori non esiste, ignora
        }

        $messaggio_db = isset($_SESSION['messaggio']) ? $_SESSION['messaggio'] : "";
        $class_messaggio = isset($_SESSION['tipo_messaggio']) ? $_SESSION['tipo_messaggio'] : "success";
        unset($_SESSION['messaggio']);
        unset($_SESSION['tipo_messaggio']);

    } catch (PDOException $e) {
        $messaggio_db = "Errore Scrittura: " . $e->getMessage();
        $class_messaggio = "error";
    }
} else {
    $messaggio_db = "Connessione al Database non riuscita (controlla db_config.php).";
    $class_messaggio = "error";
}

$stmt = $pdo->prepare("SELECT * FROM libri");
$stmt->execute();
$libri = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$title = "Dashboard Catalogo Libri";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

    <!-- INIZIO DEL BODY -->

    <div class="page_contents">

        <?php if (!empty($messaggio_db)): ?>
            <div style="padding: 10px; background: <?= $class_messaggio == 'error' ? '#f8d7da' : '#d4edda' ?>; border: 1px solid <?= $class_messaggio == 'error' ? '#f5c6cb' : '#c3e6cb' ?>; margin: 10px 0; color: <?= $class_messaggio == 'error' ? '#721c24' : '#155724' ?>;">
                <?= htmlspecialchars($messaggio_db) ?>
            </div>
        <?php endif; ?>

        <h2>Inserisci nuovo libro</h2>

        <table style="margin-bottom: 40px">
            <tr>
                <th>Isbn</th>
                <th>Titolo</th>
                <th>Descrizione</th>
                <th>Anno pubblicazione</th>
                <th>Azioni</th>
            </tr>
            <tr>
                <form method="post">
                    <td><input type="text" placeholder="isbn" name="isbn" required></td>
                    <td><input type="text" placeholder="titolo" name="titolo" required></td>
                    <td><input type="text" placeholder="descrizione" name="descrizione" required></td>
                    <td><input type="text" placeholder="anno_pubblicazione" name="anno_pubblicazione" required></td>

                    <input type="hidden" name="inserisci" value="1">
                    <td><input type="submit" value="inserisci"></td>
                </form>
            </tr>
        </table>

        <table>
            <tr>
                <th>Isbn</th>
                <th>Titolo</th>
                <th>Descrizione</th>
                <th>Anno pubblicazione</th>
                <th>Azioni</th>
            </tr>

            <?php foreach ($libri as $b): ?>
                <tr>
                    <form method="POST">
                        <td>
                            <?= htmlspecialchars($b['isbn']) ?>
                        </td>
                        <td>
                            <input type="text" name="titolo"
                                   value="<?= htmlspecialchars($b['titolo']) ?>">
                        </td>
                        <td>
                            <input type="text" name="descrizione"
                                   value="<?= htmlspecialchars($b['descrizione']) ?>">
                        </td>
                        <td>
                            <input type="text" name="anno_pubblicazione"
                                   value="<?= htmlspecialchars($b['anno_pubblicazione']) ?>">
                        </td>
                        <td>
                            <!-- SALVA -->
                            <input type="hidden" name="edit_id" value="<?= htmlspecialchars($b['isbn']) ?>">
                            <button type="submit">Salva</button>
                    </form>

                    <!-- ELIMINA - Form separato -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('ATTENZIONE: Eliminando questo libro verranno eliminate anche TUTTE le recensioni collegate.\n\nSei sicuro di voler eliminare il libro: <?= htmlspecialchars($b['titolo']) ?>?')">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($b['isbn']) ?>">
                        <button type="submit">Elimina</button>
                    </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

<?php require_once './src/includes/footer.php'; ?>
<style>
    th, td {
        padding: 15px;
        border: solid 1px black;
    }
</style>3
