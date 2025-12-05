<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOGICA MESSAGGI CENTRALIZZATA ---
$display_status = null;

if (isset($_SESSION['status'])) {
    $display_status = $_SESSION['status'];
    unset($_SESSION['status']);
}

if (isset($status) && !empty($status)) {
    $display_status = $status;
}

$nome_visualizzato = 'Utente';  // username da database

if (isset($_SESSION['nome_utente'])) {
    $nome_visualizzato = $_SESSION['nome_utente'];
}
?>

<nav class="navbar">
    <div class="navbar_left"></div>
    <div class="navbar_rigth">
        <a href="#" class="navbar_link instrument-sans-semibold">Dashboard</a>
        <a href="#" class="navbar_link_img instrument-sans-semibold">
            <img src="/BibliotecaScrum/public/assets/icon_notification.png" alt="notifica" class="navbar_icon">
        </a>
    </div>
</nav>