<?php
require_once 'security.php';

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

if(isset($_POST["logout"])){
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}
?>

<style>
    /* FIX IMMAGINE PROFILO NAVBAR */



</style>

<nav class="navbar">
    <div class="navbar_left">
        <a href="<?= $path ?>" class="navbar_link_img instrument-sans-semibold" id="navbar_logo">
            <img src="<?= $path ?>public/assets/logo_ligth.png" class="navbar_logo" alt="Biblioteca Scrum">
        </a>
        <div class="search_container">
            <form class="search_container" action="<?= $path ?>search" method="GET">
                <button type="submit" class="search_icon_button">
                    <img src="<?= $path ?>public/assets/icon_search_dark.png" alt="Cerca" class="navbar_search_icon">
                </button>
                <input type="text" placeholder="Carca..." name="search"
                    class="navbar_search_input instrument-sans-semibold"
                    value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
            </form>
        </div>
    </div>
    
    <div class="navbar_rigth">
        <div class="navbar_rigth_left">
            <a href="#" class="navbar_link_img instrument-sans-semibold">
                <img src="<?= $path ?>public/assets/icon_notification.png" alt="notifica" class="navbar_icon">
            </a>

            <?php
            if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
                $pfpPath = $path . 'public/pfp/' . $_SESSION['codice_utente'] . '.png';
                if (!file_exists($pfpPath)) {
                    $pfpPath = $path . 'public/assets/base_pfp.png';
                } else {
                    $pfpPath .= '?v=' . time();
                }
                ?>
                
                <div class="dropdown">
                    <div id="navbar_pfp" onclick="toggleDropdown()">
                        <img src="<?= $pfpPath ?>" alt="pfp" class="navbar_pfp">
                    </div>

                    <div id="navbarDropdown" class="dropdown_content">
                        <a href="<?= $path ?>profilo">Profilo</a>
                        
                        <?php if (checkAccess('amministratore') || checkAccess('bibliotecario')) { ?>
                            <a href="<?= $path ?>dashboard">Dashboard</a>
                        <?php } ?>

                        <form action="" method="post">
                            <input type="hidden" name="logout" value="1">
                            <button type="submit">Logout</button>
                        </form>
                    </div>
                </div>

            <?php } else { ?>
                <a href="<?= $path ?>login" class="navbar_link instrument-sans-semibold text_underline">Accedi</a>
            <?php } ?>

        </div>
    </div>
</nav>

<script>
    function toggleDropdown() {
        document.getElementById("navbarDropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.navbar_pfp')) {
            var dropdowns = document.getElementsByClassName("dropdown_content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
</script>