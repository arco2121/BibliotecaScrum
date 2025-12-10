<?php
session_start();
require_once "./src/includes/codiceFiscaleMethods.php";
require_once 'db_config.php';

// Redirect se già loggato
if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
    header("Location: /");
    exit();
}

if (!isset($pdo)) {
    $messaggio_db = "Connessione al Database non riuscita (controlla db_config.php).";
    $class_messaggio = "error";
    exit();
}
$registratiConCodice = isset($_GET['conCodiceFiscale']) && $_GET['conCodiceFiscale'] == "true";
$tipologia = $registratiConCodice ? " con Codice Fiscale" : "";
$status = '';

// LOGICA DI SIGNUP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $daCodiceFiscale = isset($_POST['daCodiceFiscale']) ? boolval($_POST['daCodiceFiscale']) : false;

    if (!$daCodiceFiscale) {
        $nome = $_POST['nome'] ?? '';
        $cognome = $_POST['cognome'] ?? '';
        $data_nascita = $_POST['data_nascita'] ?? '';
        $comune_nascita = $_POST['comune_nascita'] ?? '';
        $sesso = $_POST['sesso'] ?? '';
        if ($nome === '' || $cognome === '' || $data_nascita === '' || $comune_nascita === '') {
            $status = "Dati inseriti non validi";
        }
        $codice_fiscale = generateCodiceFiscale($nome, $cognome, $data_nascita, $comune_nascita, $sesso);
    } else {
        $cf = $_POST['codice_fiscale'] ?? '';
        $datiDaCodice = extractFromCodiceFiscale($cf);
        if (empty($datiDaCodice)) {
            $status = "Codice Fiscale non valido";
        } else {
            $nome = $datiDaCodice['nome'] ?? '';
            $cognome = $datiDaCodice['cognome'] ?? '';
            $data_nascita = $datiDaCodice['data_nascita'] ?? '';
            $comune_nascita = $datiDaCodice['comune_nascita'] ?? '';
            $sesso = $datiDaCodice['sesso'] ?? '';
            $codice_fiscale = $cf;
        }
    }
    // Inserimento nel DB
    if ($status === '') {
        $insert_string = "INSERT INTO users (nome, cognome, comune_nascita, data_nascita, sesso, codice_fiscale, email, password) VALUES (:nome, :cognome, :comune, :data, :sesso, :codice_fiscale, :email, :password)";
        $stmt = $pdo->prepare($insert_string);
        $stmt->bindParam(":nome", $nome);
        $stmt->bindParam(":cognome", $cognome);
        $stmt->bindParam(":data", $data_nascita);
        $stmt->bindParam(":comune", $comune_nascita);
        $stmt->bindParam(":sesso", $sesso);
        $stmt->bindParam(":codice_fiscale", $codice_fiscale);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $password);
        if ($stmt->execute()) {
            header("Location: /login");
            exit();
        } else {
            $status = "Errore nell'inserimento dell'utente";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione</title>
</head>
<body>

<?php include 'navbar.php'; ?>

<div style="padding: 20px;">

    <?php if ($status) echo "<p>$status</p>"; ?>

    <h2>Registrati<?php echo $tipologia ?></h2>
    <form method="post">

        <label for="nome">Nome:</label>
        <input required type="text" id="nome" name="nome">

        <label for="cognome">Cognome:</label>
        <input required type="text" id="cognome" name="cognome">

        <?php if ($registratiConCodice) { ?>
            <label for="codice_fiscale">Codice Fiscale:</label>
            <input required type="text" id="codice_fiscale" name="codice_fiscale">
        <?php } else { ?>

            <label for="comune_nascita">Comune di Nascita:</label>
            <input required type="text" id="comune_nascita" name="comune_nascita">

            <label for="data_nascita">Data di Nascita:</label>
            <input required type="date" id="data_nascita" name="data_nascita">

            <label for="sesso">Sesso:</label>
            <select required name="sesso" id="sesso">
                <option value="">--Seleziona--</option>
                <optgroup label="Preferenze">
                    <option value="M">Maschio</option>
                    <option value="F">Femmina</option>
                    <option value="PND">Preferisco non dirlo</option>
                </optgroup>
            </select>

        <?php } ?>

        <label for="email">Email:</label>
        <input required type="email" id="email" name="email">

        <label for="password">Password:</label>
        <input required type="password" id="password" name="password">

        <input type="submit" value="Registrami">
    </form>
    <?php if ($registratiConCodice) { ?>
        <a href='/signup/?conCodiceFiscale=false'>Non hai il codice fiscale?</a>
    <?php } else { ?>
        <a href='/signup/?conCodiceFiscale=true'>Hai il codice fiscale?</a>
    <?php } ?>

</div>

</body>
</html>