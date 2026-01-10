<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

// CONFIGURAZIONE LIMITE CARATTERI
$MAX_CHARS = 1000;

$messaggio_db = "";
$server_message = ""; // Messaggio da passare al JS per il banner
$libro = null;
$autori = [];
$categorie = [];
$recensioni_altri = [];
$mia_recensione = null;
$mediaVoto = 0;
$totaleRecensioni = 0;

$isbn = $_GET['isbn'] ?? null;
$uid = $_SESSION['codice_utente'] ?? null;

if (!$isbn) {
    die("<h1>Errore</h1><p>ISBN non specificato.</p>");
}

// --- 1. GESTIONE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$uid) {
        // In caso di errore login, redirect con status error
         header("Location: ./libro?isbn=" . $isbn . "&status=login_needed");
         exit;
    } else {
        $voto = filter_input(INPUT_POST, 'voto', FILTER_VALIDATE_INT);
        $raw_commento = $_POST['commento'] ?? '';
        $commento = trim(filter_var($raw_commento, FILTER_SANITIZE_STRING));
        $mode = $_POST['mode'] ?? 'insert';

        if (strlen($commento) > $MAX_CHARS) {
             header("Location: ./libro?isbn=" . $isbn . "&status=toolong");
             exit;
        } elseif ($voto < 1 || $voto > 5 || empty($commento)) {
             header("Location: ./libro?isbn=" . $isbn . "&status=invalid");
             exit;
        } else {
            try {
                if ($mode === 'update') {
                    $stmt = $pdo->prepare("UPDATE recensioni SET voto = ?, commento = ?, data_commento = NOW() WHERE isbn = ? AND codice_alfanumerico = ?");
                    $stmt->execute([$voto, $commento, $isbn, $uid]);
                    $msg_type = "updated";
                } else {
                    $chk = $pdo->prepare("SELECT 1 FROM recensioni WHERE isbn = ? AND codice_alfanumerico = ?");
                    $chk->execute([$isbn, $uid]);
                    if (!$chk->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO recensioni (isbn, codice_alfanumerico, voto, commento, data_commento) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$isbn, $uid, $voto, $commento]);
                        $msg_type = "created";
                    } else {
                        $msg_type = "exists";
                    }
                }
                header("Location: ./libro?isbn=" . $isbn . "&status=" . $msg_type);
                exit;
            } catch (PDOException $e) {
                // In produzione logga l'errore, qui rimandiamo un errore generico
                header("Location: ./libro?isbn=" . $isbn . "&status=error");
                exit;
            }
        }
    }
}

// GESTIONE MESSAGGI STATUS (PER BANNER JS)
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'created': $server_message = "Recensione pubblicata con successo!"; break;
        case 'updated': $server_message = "Recensione aggiornata!"; break;
        case 'exists': $server_message = "Hai già recensito questo libro."; break;
        case 'login_needed': $server_message = "Devi accedere per recensire."; break;
        case 'toolong': $server_message = "Commento troppo lungo."; break;
        case 'invalid': $server_message = "Compila tutti i campi."; break;
        case 'error': $server_message = "Errore di sistema."; break;
    }
}

try {
    // 2. RECUPERA INFO LIBRO
    $stmt = $pdo->prepare("
        SELECT l.*,
            (SELECT editore FROM copie c WHERE c.isbn = l.isbn LIMIT 1) as editore_temp,
            (SELECT COUNT(*) FROM copie c WHERE c.isbn = l.isbn AND c.disponibile = 1) as numero_copie_disponibili
        FROM libri l WHERE l.isbn = ?
    ");
    $stmt->execute([$isbn]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($libro) {
        $libro['editore'] = $libro['editore_temp'] ?? 'N/D';

        $stmt = $pdo->prepare("SELECT a.nome, a.cognome FROM autori a JOIN autore_libro al ON al.id_autore = a.id_autore WHERE al.isbn = ?");
        $stmt->execute([$isbn]);
        $autori = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT categoria FROM categorie c JOIN libro_categoria lc ON lc.id_categoria = c.id_categoria WHERE lc.isbn = ?");
        $stmt->execute([$isbn]);
        $categorie = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT CAST(AVG(voto) AS DECIMAL(3,1)) as media, COUNT(*) as totale FROM recensioni WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $mediaVoto = $stats['media'] ? number_format((float)$stats['media'], 1) : 0;
        $totaleRecensioni = $stats['totale'];

        if ($uid) {
            $stmt = $pdo->prepare("
                SELECT r.*, u.username, u.codice_alfanumerico as id_recensore 
                FROM recensioni r 
                JOIN utenti u ON r.codice_alfanumerico = u.codice_alfanumerico 
                WHERE r.isbn = ? AND r.codice_alfanumerico = ?
            ");
            $stmt->execute([$isbn, $uid]);
            $mia_recensione = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $sqlAltri = "
            SELECT r.*, u.username, u.codice_alfanumerico as id_recensore
            FROM recensioni r
            JOIN utenti u ON r.codice_alfanumerico = u.codice_alfanumerico
            WHERE r.isbn = ? 
        ";
        if ($uid) {
            $sqlAltri .= " AND r.codice_alfanumerico != ? ";
        }
        $sqlAltri .= " ORDER BY r.data_commento DESC";
        
        $stmt = $pdo->prepare($sqlAltri);
        if ($uid) $stmt->execute([$isbn, $uid]);
        else $stmt->execute([$isbn]);
        
        $recensioni_altri = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $messaggio_db = "Libro non trovato.";
    }

} catch (PDOException $e) {
    $messaggio_db = "Errore DB: " . $e->getMessage();
}

function getCoverPath($isbn) {
    $localPath = "public/bookCover/$isbn.png";
    return file_exists($localPath) ? $localPath : "public/assets/book_placeholder.jpg";
}

function getPfpPath($userId) {
    $path = "public/pfp/$userId.png";
    return file_exists($path) ? $path . '?v=' . time() : "public/assets/base_pfp.png";
}
?>

<style>
</style>

<?php
$title = $libro['titolo'];
    $page_css = "./public/css/style_index.css";
    require './src/includes/header.php';
    require './src/includes/navbar.php';
?>

    <div class="page_contents">

        <?php if ($messaggio_db || !$libro): ?>
            <div class="alert_box danger" style="margin-top:40px;">
                <h1>Ops!</h1>
                <p><?= htmlspecialchars($messaggio_db ?: "Libro non trovato.") ?></p>
                <a href="./" class="btn_send" style="text-decoration:none;">Torna alla Home</a>
            </div>
        <?php else: ?>

            <div class="book_details_wrapper">
                <div class="book_hero_card">
                    <div class="book_hero_left">
                        <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Cover" class="book_hero_cover">
                    </div>
                    <div class="book_hero_right">
                        <h1 class="book_main_title"><?= htmlspecialchars($libro['titolo']) ?></h1>
                        <div class="book_authors">
                            di <?= htmlspecialchars(implode(', ', array_map(fn($a) => $a['nome'] . ' ' . $a['cognome'], $autori))) ?>
                        </div>
                        <div class="meta_info_grid">
                            <span><strong>Editore:</strong> <?= htmlspecialchars($libro['editore']) ?></span>
                            <span><strong>Anno:</strong> <?= htmlspecialchars($libro['anno_pubblicazione'] ?? 'N/D') ?></span>
                        </div>
                        <?php if ($categorie): ?>
                            <div class="book_tags">
                                <?php foreach($categorie as $cat): ?>
                                    <span class="tag_pill"><?= htmlspecialchars($cat) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-bottom: 20px;">
                            <?php if ($libro['numero_copie_disponibili'] > 0): ?>
                                <span class="badge_avail badge_ok">Disponibile (<?= $libro['numero_copie_disponibili'] ?>)</span>
                            <?php else: ?>
                                <span class="badge_avail badge_ko">Non disponibile</span>
                            <?php endif; ?>
                        </div>
                        <div class="book_desc_box">
                            <h3 class="book_desc_title">Trama</h3>
                            <div class="book_desc_text"><?= nl2br(htmlspecialchars($libro['descrizione'])) ?></div>
                        </div>
                    </div>
                </div>

                <div class="reviews_section">
                    <h2 class="reviews_title">Recensioni (<?= $totaleRecensioni ?>)</h2>

                    <?php if ($uid): ?>
                        
                        <?php if ($mia_recensione): ?>
                            
                            <div class="review_card my-review">
                                <div class="review_avatar_col">
                                    <img src="<?= getPfpPath($mia_recensione['id_recensore']) ?>" alt="Io" class="review_pfp">
                                </div>
                                <div class="review_content_col">
                                    
                                    <div id="my_review_view">
                                        <div class="review_header_row">
                                            <div class="review_user"><?= htmlspecialchars($mia_recensione['username']) ?></div>
                                            <span class="review_date"><?= date('d/m/Y', strtotime($mia_recensione['data_commento'])) ?></span>
                                        </div>
                                        <div class="review_stars_text">
                                            <?php
                                            for ($i = 0; $i < $mia_recensione['voto']; $i++) echo "<span class='star_yellow'>★</span>";
                                            for ($i = $mia_recensione['voto']; $i < 5; $i++) echo "<span class='star_grey'>★</span>";
                                            ?>
                                        </div>
                                        <div class="review_body">
                                            <?= nl2br(htmlspecialchars($mia_recensione['commento'])) ?>
                                        </div>
                                        
                                        <button type="button" class="btn_edit_circular" onclick="toggleEditMode()" title="Modifica">
                                            <svg viewBox="0 0 24 24" class="icon_pencil">
                                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div id="my_review_edit" class="hidden">
                                        <h3 class="young-serif-regular" style="margin-top:0; color:#f39c12; margin-bottom: 20px;">Modifica</h3>
                                        
                                        <form method="POST" action="./libro?isbn=<?= $isbn ?>">
                                            <input type="hidden" name="mode" value="update">
                                            
                                            <input type="hidden" name="voto" id="voto_edit_input" value="<?= $mia_recensione['voto'] ?>">
                                            
                                            <div class="interactive-rating" id="rating_edit">
                                                <span class="star-input" data-value="1">★</span>
                                                <span class="star-input" data-value="2">★</span>
                                                <span class="star-input" data-value="3">★</span>
                                                <span class="star-input" data-value="4">★</span>
                                                <span class="star-input" data-value="5">★</span>
                                            </div>

                                            <div style="margin-bottom:10px;">
                                                <label class="form_label">Commento</label>
                                                <textarea name="commento" id="commento_edit" rows="4" required class="form_field" maxlength="<?= $MAX_CHARS ?>" oninput="updateCharCount(this)"><?= htmlspecialchars($mia_recensione['commento']) ?></textarea>
                                                <div class="char_counter">0 / <?= $MAX_CHARS ?></div>
                                            </div>
                                            <div style="display:flex; gap:10px; justify-content: flex-end;">
                                                <button type="button" onclick="toggleEditMode()" class="btn_send" style="background:#ccc;">Annulla</button>
                                                <button type="submit" name="submit_review" class="btn_send">Salva</button>
                                            </div>
                                        </form>
                                    </div>

                                </div>
                            </div>

                        <?php else: ?>
                            <div class="review_form_box">
                                <h3 class="young-serif-regular" style="margin-top:0; margin-bottom: 20px;">Lascia un pensiero</h3>
                                
                                <form method="POST" action="./libro?isbn=<?= $isbn ?>">
                                    <input type="hidden" name="mode" value="insert">
                                    
                                    <input type="hidden" name="voto" id="voto_new_input" value="0">
                                    
                                    <div class="interactive-rating" id="rating_new">
                                        <span class="star-input" data-value="1">★</span>
                                        <span class="star-input" data-value="2">★</span>
                                        <span class="star-input" data-value="3">★</span>
                                        <span class="star-input" data-value="4">★</span>
                                        <span class="star-input" data-value="5">★</span>
                                    </div>

                                    <div style="margin-bottom:15px;">
                                        <label for="commento" class="form_label">Commento</label>
                                        <textarea name="commento" id="commento_new" rows="5" required class="form_field" maxlength="<?= $MAX_CHARS ?>" oninput="updateCharCount(this)" placeholder="Scrivi qui la tua recensione dettagliata..."></textarea>
                                        <div class="char_counter">0 / <?= $MAX_CHARS ?></div>
                                    </div>
                                    <button type="submit" name="submit_review" class="btn_send">Pubblica Recensione</button>
                                </form>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert_box login">
                            <p>Vuoi lasciare una recensione? <a href="./login" style="color:#333; font-weight:bold;">Accedi</a> per dirci la tua!</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($recensioni_altri): ?>
                        <div class="reviews_list">
                            <?php foreach ($recensioni_altri as $r): ?>
                                <div class="review_card">
                                    <div class="review_avatar_col">
                                        <img src="<?= getPfpPath($r['id_recensore']) ?>" alt="Utente" class="review_pfp">
                                    </div>
                                    <div class="review_content_col">
                                        <div class="review_header_row">
                                            <div class="review_user"><?= htmlspecialchars($r['username']) ?></div>
                                            <span class="review_date"><?= date('d/m/Y', strtotime($r['data_commento'])) ?></span>
                                        </div>
                                        <div class="review_stars_text">
                                            <?php
                                            for ($i = 0; $i < $r['voto']; $i++) echo "<span class='star_yellow'>★</span>";
                                            for ($i = $r['voto']; $i < 5; $i++) echo "<span class='star_grey'>★</span>";
                                            ?>
                                        </div>
                                        <div class="review_body">
                                            <?= nl2br(htmlspecialchars($r['commento'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php if (!$mia_recensione): ?>
                            <p style="text-align:center; color:#888; margin-top:30px; font-size:1.1em;">Ancora nessuna recensione. Sii il primo a scriverne una!</p>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="notification-banner">
        <span id="banner-msg" class="notification-text">Notifica</span>
        <button class="close-btn-banner" onclick="hideNotification()">&times;</button>
    </div>

<script>
    // --- GESTIONE BANNER NOTIFICA ---
    let timeoutId;
    function showNotification(message) {
        const banner = document.getElementById('notification-banner');
        const msgSpan = document.getElementById('banner-msg');
        msgSpan.innerText = message;
        banner.classList.add('show');
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => { hideNotification(); }, 5000);
    }
    function hideNotification() {
        document.getElementById('notification-banner').classList.remove('show');
    }

    // Se c'è un messaggio dal server, mostralo
    const serverMessage = "<?= addslashes($server_message) ?>";
    if (serverMessage.length > 0) {
        setTimeout(() => { showNotification(serverMessage); }, 500);
    }

    // --- GESTIONE STELLE INTERATTIVE ---
    function initStarRating(containerId, inputId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        if (!container || !input) return;

        const stars = container.querySelectorAll('.star-input');
        
        // Funzione per colorare le stelle
        const paintStars = (value, className) => {
            stars.forEach(star => {
                const sVal = parseInt(star.getAttribute('data-value'));
                if (sVal <= value) star.classList.add(className);
                else star.classList.remove(className);
            });
        };

        // Inizializza stato corrente
        paintStars(parseInt(input.value), 'active');

        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                paintStars(parseInt(this.getAttribute('data-value')), 'hover');
            });

            star.addEventListener('mouseout', function() {
                // Rimuovi hover e ripristina active
                stars.forEach(s => s.classList.remove('hover'));
                paintStars(parseInt(input.value), 'active');
            });

            star.addEventListener('click', function() {
                const val = parseInt(this.getAttribute('data-value'));
                input.value = val;
                paintStars(val, 'active');
            });
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        initStarRating('rating_new', 'voto_new_input');
        initStarRating('rating_edit', 'voto_edit_input');
        
        // Update char count al caricamento (per edit)
        const editTxt = document.getElementById('commento_edit');
        if(editTxt) updateCharCount(editTxt);
    });


    // --- TOGGLE EDIT MODE ---
    function toggleEditMode() {
        const viewDiv = document.getElementById('my_review_view');
        const editDiv = document.getElementById('my_review_edit');
        
        if (viewDiv.classList.contains('hidden')) {
            viewDiv.classList.remove('hidden');
            editDiv.classList.add('hidden');
        } else {
            viewDiv.classList.add('hidden');
            editDiv.classList.remove('hidden');
            updateCharCount(document.getElementById('commento_edit'));
        }
    }

    // --- CHAR COUNT ---
    function updateCharCount(textarea) {
        const max = <?= $MAX_CHARS ?>;
        const current = textarea.value.length;
        const counterDiv = textarea.nextElementSibling; 
        counterDiv.innerText = current + " / " + max;
        if (current >= max) {
            counterDiv.classList.add('limit-reached');
            counterDiv.classList.remove('limit-near');
        } else if (current >= max * 0.9) {
            counterDiv.classList.add('limit-near');
            counterDiv.classList.remove('limit-reached');
        } else {
            counterDiv.classList.remove('limit-reached', 'limit-near');
        }
    }
</script>

<?php require './src/includes/footer.php'; ?>