<?php

require_once 'security.php';
if (!checkAccess('amministratore')) header('Location: ./');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

$messaggio_db = "";
$class_messaggio = "";
$utenti = [];

if (!isset($pdo)) {
    die("Connessione DB non riuscita");
}

try {

    //ELIMINA
    if (isset($_POST['delete_id'])) {
        // Prima controlla se ci sono recensioni collegate
        $stmt = $pdo->prepare("SELECT COUNT(*) as num FROM recensioni WHERE codice_alfanumerico = :codice");
        $stmt->execute(['codice' => $_POST['delete_id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        try {
            // Prima elimina le recensioni
            if ($count['num'] > 0) {
                $stmt = $pdo->prepare("DELETE FROM recensioni WHERE codice_alfanumerico = :codice");
                $stmt->execute(['codice' => $_POST['delete_id']]);
            }

            // Elimina dai ruoli
            $stmt = $pdo->prepare("DELETE FROM ruoli WHERE codice_alfanumerico = :codice");
            $stmt->execute(['codice' => $_POST['delete_id']]);

            // Elimina l'utente
            $stmt = $pdo->prepare("DELETE FROM utenti WHERE codice_alfanumerico = :codice");
            $stmt->execute(['codice' => $_POST['delete_id']]);

            $pdo->commit();

            if ($count['num'] > 0) {
                $_SESSION['messaggio'] = "Utente eliminato insieme a " . $count['num'] . " recensione/i!";
            } else {
                $_SESSION['messaggio'] = "Utente eliminato con successo!";
            }
            $_SESSION['tipo_messaggio'] = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['messaggio'] = "ERRORE: " . $e->getMessage();
            $_SESSION['tipo_messaggio'] = "error";
        }

        header("Location: dashboard-utenti");
        exit;
    }

    //MODIFICA
    if (isset($_POST['edit_id'])) {

        // Password
        if ($_POST['password_hash'] !== '*****' && !empty($_POST['password_hash'])) {
            $password = password_hash($_POST['password_hash'], PASSWORD_DEFAULT);
        } else {
            $stmt_old = $pdo->prepare(
                    "SELECT password_hash FROM utenti WHERE codice_alfanumerico = :codice"
            );
            $stmt_old->execute(['codice' => $_POST['codice_alfanumerico']]);
            $password = $stmt_old->fetchColumn();
        }

        $account_bloccato = isset($_POST['account_bloccato']) ? 1 : 0;
        $affidabile = isset($_POST['affidabile']) ? 1 : 0;
        $email_confermata = isset($_POST['email_confermata']) ? 1 : 0;
        $login_bloccato_check = isset($_POST['login_bloccato_check']) ? 1 : 0;

        $ruolo0 = isset($_POST['ruolo0']) ? 1 : 0;
        $ruolo1 = isset($_POST['ruolo1']) ? 1 : 0;
        $ruolo2 = isset($_POST['ruolo2']) ? 1 : 0;
        $ruolo3 = isset($_POST['ruolo3']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE utenti SET
                username = :username,
                nome = :nome,
                cognome = :cognome,
                codice_fiscale = :codice_fiscale,
                email = :email,
                password_hash = :password_hash,
                login_bloccato = :login_bloccato,
                account_bloccato = :account_bloccato,
                affidabile = :affidabile,
                email_confermata = :email_confermata,
                livello_privato = :livello_privato,
                data_creazione = :data_creazione
            WHERE codice_alfanumerico = :codice
        ");

        $stmt->execute([
                'username' => $_POST['username'],
                'nome' => $_POST['nome'],
                'cognome' => $_POST['cognome'],
                'codice_fiscale' => $_POST['codice_fiscale'],
                'email' => $_POST['email'],
                'password_hash' => $password,
                'login_bloccato' => $login_bloccato_check,
                'account_bloccato' => $account_bloccato,
                'affidabile' => $affidabile,
                'email_confermata' => $email_confermata,
                'livello_privato' => $_POST['livello_privato'],
                'data_creazione' => $_POST['data_creazione'],
                'codice' => $_POST['codice_alfanumerico']
        ]);

        $stmt = $pdo->prepare("
            UPDATE ruoli SET
                studente = :studente,
                docente = :docente,
                bibliotecario = :bibliotecario,
                amministratore = :amministratore
            WHERE codice_alfanumerico = :codice
        ");
        $stmt->execute([
                "studente" => $ruolo0,
                "docente" => $ruolo1,
                "bibliotecario" => $ruolo2,
                "amministratore" => $ruolo3,
                "codice" => $_POST['codice_alfanumerico']
        ]);

        $_SESSION['messaggio'] = "Utente modificato con successo!";
        $_SESSION['tipo_messaggio'] = "success";

        header("Location: dashboard-utenti");
        exit;
    }

    //AGGIUNGI - USA STORED PROCEDURE
    if (isset($_POST['inserisci'])) {
        try {
            // Chiama la stored procedure
            $stmt = $pdo->prepare("CALL sp_crea_utente_alfanumerico(
                :username, 
                :nome, 
                :cognome, 
                :codice_fiscale, 
                :email, 
                :password_hash
            )");

            $password_hash = password_hash($_POST['password_hash'], PASSWORD_DEFAULT);

            $stmt->execute([
                    'username' => $_POST['username'],
                    'nome' => $_POST['nome'],
                    'cognome' => $_POST['cognome'],
                    'codice_fiscale' => $_POST['codice_fiscale'],
                    'email' => $_POST['email'],
                    'password_hash' => $password_hash
            ]);

            // Recupera il nuovo_id generato
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nuovo_id = $result['nuovo_id'];

            // Chiudi il cursore per permettere altre query
            $stmt->closeCursor();

            // Inserisci i ruoli
            $ruolo0 = isset($_POST['ruolo0']) ? 1 : 0;
            $ruolo1 = isset($_POST['ruolo1']) ? 1 : 0;
            $ruolo2 = isset($_POST['ruolo2']) ? 1 : 0;
            $ruolo3 = isset($_POST['ruolo3']) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO ruoli(
                    codice_alfanumerico, studente, docente,
                    bibliotecario, amministratore
                ) VALUES (
                    :codice_alfanumerico, :studente, :docente,
                    :bibliotecario, :amministratore                             
                )
            ");

            $stmt->execute([
                    'codice_alfanumerico' => $nuovo_id,
                    'studente' => $ruolo0,
                    'docente' => $ruolo1,
                    'bibliotecario' => $ruolo2,
                    'amministratore' => $ruolo3
            ]);

            $_SESSION['messaggio'] = "Utente creato con successo! Codice alfanumerico: " . $nuovo_id;
            $_SESSION['tipo_messaggio'] = "success";

        } catch (PDOException $e) {
            $_SESSION['messaggio'] = "ERRORE durante la creazione: " . $e->getMessage();
            $_SESSION['tipo_messaggio'] = "error";
        }

        header("Location: dashboard-utenti");
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM utenti 
                        JOIN ruoli ON utenti.codice_alfanumerico = ruoli.codice_alfanumerico
                        ORDER BY data_creazione DESC");
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recupera messaggi dalla sessione
    if (isset($_SESSION['messaggio'])) {
        $messaggio_db = $_SESSION['messaggio'];
        $class_messaggio = $_SESSION['tipo_messaggio'];
        unset($_SESSION['messaggio']);
        unset($_SESSION['tipo_messaggio']);
    }

} catch (PDOException $e) {
    die("Errore DB: " . $e->getMessage());
}

// ---------------- HTML HEADER ----------------
$title = "Dashboard Utenti";
$path = "../";
$page_css = "../public/css/style_dashboards.css";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

    <div class="dashboard_container_larger">

        <div class="page_header">
            <h2 class="page_title">Gestione Utenti</h2>
            <div class="header_actions">
                <button onclick="toggleAddForm()" class="btn_action btn_save">+ Nuovo Utente</button>
            </div>
        </div>

        <?php if (!empty($messaggio_db)): ?>
            <div class="alert_msg <?= $class_messaggio == 'error' ? 'alert_error' : 'alert_success' ?>">
                <?= htmlspecialchars($messaggio_db) ?>
            </div>
        <?php endif; ?>

        <div id="add_user_section" class="add_book_section">
            <form method="POST" class="add_form_wrapper form_spam_protect">
                <input type="hidden" name="inserisci" value="1">

                <div class="form_group">
                    <label class="form_label">Username</label>
                    <input type="text" name="username" class="edit_input" placeholder="Es: User123" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Nome</label>
                    <input type="text" name="nome" class="edit_input" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Cognome</label>
                    <input type="text" name="cognome" class="edit_input" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Codice Fiscale</label>
                    <input type="text" name="codice_fiscale" class="edit_input" maxlength="16" placeholder="16 caratteri" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Email</label>
                    <input type="email" name="email" class="edit_input" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Password</label>
                    <input type="password" name="password_hash" class="edit_input" required>
                </div>

                <div class="form_group" style="min-width: 300px;">
                    <label class="form_label">Assegna Ruoli</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; background: #faf9f6; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        <div>
                            <input type="checkbox" name="ruolo0" value="1" id="ins_ruolo0"> <label for="ins_ruolo0">Studente</label>
                        </div>
                        <div>
                            <input type="checkbox" name="ruolo1" value="1" id="ins_ruolo1"> <label for="ins_ruolo1">Docente</label>
                        </div>
                        <div>
                            <input type="checkbox" name="ruolo2" value="1" id="ins_ruolo2"> <label for="ins_ruolo2">Bibliotecario</label>
                        </div>
                        <div>
                            <input type="checkbox" name="ruolo3" value="1" id="ins_ruolo3"> <label for="ins_ruolo3">Admin</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn_action btn_save trigger_loader" style="margin-bottom: 5px;">Crea Utente</button>
            </form>
        </div>

        <div class="table_card">
            <div class="table_responsive">
                <table class="admin_table" style="min-width: 1800px;">
                    <thead>
                    <tr>
                        <th style="width: 80px;">Codice</th>
                        <th>Username</th>
                        <th>Nome</th>
                        <th>Cognome</th>
                        <th>CF</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th style="width: 60px; text-align:center;" title="Login Bloccato">Login Bloccato</th>
                        <th style="width: 60px; text-align:center;" title="Account Bloccato">Account Bloccato</th>
                        <th style="width: 50px;" title="Livello Privato">Lvl</th>
                        <th style="width: 140px;">Data Creazione</th>
                        <th style="width: 50px; text-align:center;" title="Affidabile">Aff</th>
                        <th style="width: 50px; text-align:center;" title="Email Confermata">Ver</th>
                        <th style="width: 150px;">Ruoli</th>
                        <th style="width: 160px; text-align: center;">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($utenti as $u): ?>
                    <tr>
                        <form method="POST" class="form_spam_protect_row">
                            <td style="color: #888;">
                                <?= htmlspecialchars($u['codice_alfanumerico'] ?? '') ?>
                                <input type="hidden" name="codice_alfanumerico" value="<?= htmlspecialchars($u['codice_alfanumerico'] ?? '') ?>">
                            </td>

                            <td><input type="text" name="username" class="edit_input" value="<?= htmlspecialchars($u['username'] ?? '') ?>" required></td>
                            <td><input type="text" name="nome" class="edit_input" value="<?= htmlspecialchars($u['nome'] ?? '') ?>" required></td>
                            <td><input type="text" name="cognome" class="edit_input" value="<?= htmlspecialchars($u['cognome'] ?? '') ?>" required></td>
                            <td><input type="text" name="codice_fiscale" class="edit_input" value="<?= htmlspecialchars($u['codice_fiscale'] ?? '') ?>" maxlength="16" required></td>
                            <td><input type="email" name="email" class="edit_input" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required></td>

                            <td><input type="password" name="password_hash" class="edit_input" placeholder="*****" value="*****"></td>

                            <td style="text-align:center;">
                                <input type="checkbox" name="login_bloccato_check" value="1" <?= !empty($u['login_bloccato']) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="account_bloccato" value="1" <?= !empty($u['account_bloccato']) ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <input type="number" name="livello_privato" class="edit_input" value="<?= htmlspecialchars($u['livello_privato'] ?? 0) ?>" min="0" max="10">
                            </td>
                            <td>
                                <input type="datetime-local" name="data_creazione" class="edit_input" style="font-size: 0.8rem;" value="<?= isset($u['data_creazione']) ? date('Y-m-d\TH:i', strtotime($u['data_creazione'])) : '' ?>" required>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="affidabile" value="1" <?= !empty($u['affidabile']) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="email_confermata" value="1" <?= !empty($u['email_confermata']) ? 'checked' : '' ?>>
                            </td>

                            <td style="font-size: 0.85rem;">
                                <div style="display:flex; flex-direction:column; gap:2px;">
                                    <label><input type="checkbox" name="ruolo0" value="1" <?= !empty($u['studente']) ? 'checked' : '' ?>> Stud</label>
                                    <label><input type="checkbox" name="ruolo1" value="1" <?= !empty($u['docente']) ? 'checked' : '' ?>> Doc</label>
                                    <label><input type="checkbox" name="ruolo2" value="1" <?= !empty($u['bibliotecario']) ? 'checked' : '' ?>> Biblio</label>
                                    <label><input type="checkbox" name="ruolo3" value="1" <?= !empty($u['amministratore']) ? 'checked' : '' ?>> Admin</label>
                                </div>
                            </td>

                            <td style="text-align: center;">
                                <div style="display: flex; gap: 5px; justify-content: center; flex-direction: column;">
                                    <input type="hidden" name="edit_id" value="<?= htmlspecialchars($u['codice_alfanumerico'] ?? '') ?>">
                                    <button type="submit" class="btn_action btn_save trigger_loader" style="font-size: 0.8rem; padding: 5px 10px;">Salva</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('ATTENZIONE: Verranno eliminate anche tutte le recensioni di questo utente.\n\nConfermi eliminazione?')">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($u['codice_alfanumerico'] ?? '') ?>">
                            <button type="submit" class="btn_action btn_delete trigger_loader" style="font-size: 0.8rem; padding: 5px 10px;">Elimina</button>
                        </form>
            </div>
            </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </div>
    </div>

    <script>
        function toggleAddForm() {
            var x = document.getElementById("add_user_section");
            x.style.display = (x.style.display === "block") ? "none" : "block";
        }

        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('loading_overlay');
            document.querySelectorAll('.trigger_loader').forEach(btn => {
                btn.addEventListener('click', () => overlay.style.display = 'flex');
            });
            document.querySelectorAll('.form_spam_protect').forEach(form => {
                form.addEventListener('submit', () => overlay.style.display = 'flex');
            });
            document.querySelectorAll('.form_spam_protect_row').forEach(form => {
                form.addEventListener('submit', () => overlay.style.display = 'flex');
            });
        });
    </script>

<?php require_once './src/includes/footer.php'; ?>