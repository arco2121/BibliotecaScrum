<?php
// --- CONFIGURAZIONE PERCORSI ---
$baseDir = dirname(__DIR__, 2);
$path = '../'; 

require_once $baseDir . '/security.php';
require_once $baseDir . '/db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controllo Accessi
if (!checkAccess('amministratore') && !checkAccess('bibliotecario')) {
    header('Location: ../login');
    exit;
}

$isAdmin = checkAccess('amministratore');
$isBibliotecario = checkAccess('bibliotecario') && !$isAdmin; 
$messaggio = "";

// --- 1. GESTIONE SELEZIONE BIBLIOTECA (SOLO BIBLIOTECARI) ---
if (!$isAdmin && !isset($_SESSION['id_biblioteca_operativa'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_biblioteca'])) {
        $_SESSION['id_biblioteca_operativa'] = $_POST['id_biblioteca'];
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
$id_biblio_operativa = $_SESSION['id_biblioteca_operativa'] ?? null;
$showDashboard = $isAdmin || $id_biblio_operativa;

// --- 2. GESTIONE AZIONI POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. RESTITUZIONE PRESTITO (Standard)
    if (isset($_POST['restituisci_id'])) {
        try {
            $data_scelta = !empty($_POST['data_fine']) ? $_POST['data_fine'] : date('Y-m-d');
            $stmt = $pdo->prepare("UPDATE prestiti SET data_restituzione = :data WHERE id_prestito = :id");
            $stmt->execute([
                'data' => $data_scelta,
                'id' => $_POST['restituisci_id']
            ]);
            header("Location: dashboard-gestioneprestiti?success=restituzione");
            exit;
        } catch (PDOException $e) {
            $messaggio = "Errore restituzione: " . $e->getMessage();
        }
    }

    // B. MULTA DANNI + CHIUSURA PRESTITO (SOLO BIBLIOTECARIO)
    if (isset($_POST['azione']) && $_POST['azione'] === 'multa_danni' && $isBibliotecario) {
        $id_prestito_multa = $_POST['id_prestito'];
        $id_copia_multa = $_POST['id_copia'];
        $importo_danni = 15.00; // Importo fisso

        try {
            $pdo->beginTransaction();

            // 1. Inserisci Multa
            $stmtM = $pdo->prepare("INSERT INTO multe (id_prestito, importo, causale, data_creata, pagata) VALUES (?, ?, 'Danni al materiale (Copertina/Pagine)', CURDATE(), 0)");
            $stmtM->execute([$id_prestito_multa, $importo_danni]);

            // 2. Decrementa Condizione Copia
            $stmtCheck = $pdo->prepare("SELECT condizione FROM copie WHERE id_copia = ?");
            $stmtCheck->execute([$id_copia_multa]);
            $condAttuale = $stmtCheck->fetchColumn();

            if ($condAttuale > 0) {
                $stmtUpdate = $pdo->prepare("UPDATE copie SET condizione = condizione - 1 WHERE id_copia = ?");
                $stmtUpdate->execute([$id_copia_multa]);
            }

            // 3. CHIUDI IL PRESTITO (Restituzione automatica a oggi)
            $stmtClose = $pdo->prepare("UPDATE prestiti SET data_restituzione = CURDATE() WHERE id_prestito = ?");
            $stmtClose->execute([$id_prestito_multa]);

            $pdo->commit();
            header("Location: dashboard-gestioneprestiti?success=multa_e_chiuso");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $messaggio = "Errore applicazione danni: " . $e->getMessage();
        }
    }
}

// --- 3. QUERY PRESTITI ATTIVI ---
$prestitiAttivi = [];
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

// Carichiamo biblioteche per la select iniziale
$biblioteche = [];
if (!$showDashboard) {
    $biblioteche = $pdo->query("SELECT id, nome FROM biblioteche ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
}

if ($showDashboard && isset($pdo)) {
    try {
        $query = "SELECT 
                    p.id_prestito, 
                    u.nome, 
                    u.cognome, 
                    u.codice_alfanumerico,
                    u.codice_fiscale,
                    l.titolo, 
                    p.id_copia, 
                    c.condizione, 
                    p.data_prestito, 
                    p.data_scadenza, 
                    p.num_rinnovi,
                    b.nome as nome_biblioteca,
                    DATEDIFF(p.data_scadenza, CURDATE()) as giorni_rimanenti,
                    (SELECT COUNT(*) FROM multe m WHERE m.id_prestito = p.id_prestito AND m.pagata = 0) as multe_pendenti
                  FROM prestiti p
                  JOIN utenti u ON p.codice_alfanumerico = u.codice_alfanumerico
                  JOIN copie c ON p.id_copia = c.id_copia
                  JOIN libri l ON c.isbn = l.isbn
                  JOIN biblioteche b ON c.id_biblioteca = b.id
                  WHERE p.data_restituzione IS NULL";

        $params = [];

        if (!$isAdmin) {
            $query .= " AND c.id_biblioteca = :id_biblio";
            $params['id_biblio'] = $id_biblio_operativa;
        }

        if (!empty($searchTerm)) {
            $query .= " AND (u.nome LIKE :q OR u.cognome LIKE :q OR u.codice_alfanumerico LIKE :q OR u.codice_fiscale LIKE :q OR l.titolo LIKE :q)";
            $params['q'] = "%$searchTerm%";
        }

        $query .= " ORDER BY p.data_scadenza ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $prestitiAttivi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $messaggio = "Errore caricamento dati: " . $e->getMessage();
    }
}

// Inclusione Layout
$title = "Gestione Prestiti";
require_once $baseDir . '/src/includes/header.php';
require_once $baseDir . '/src/includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent-primary: #3f5135; --bg-main: #faf7f0; }
        body { background-color: var(--bg-main); font-family: 'Inter', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-custom th { background-color: var(--accent-primary); color: white; font-weight: 500; }
        .badge-status { font-size: 0.8rem; padding: 6px 10px; border-radius: 8px; }
        .bg-warning-soft { background-color: #fff3cd; color: #856404; }
        .bg-danger-soft { background-color: #f8d7da; color: #721c24; }
        .bg-success-soft { background-color: #d4edda; color: #155724; }
        .config-box { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .btn-main { background-color: var(--accent-primary); color: white; border: none; }
        .btn-main:hover { background-color: #2e3c27; color: white; }
        
        .cond-bar-wrapper { display: flex; gap: 2px; align-items: center; background: #eee; padding: 2px; border-radius: 3px; width: fit-content; margin-bottom: 5px; }
        .cond-segment { width: 10px; height: 10px; border-radius: 1px; background-color: #ddd; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <?php if (!$showDashboard): ?>
        <div class="config-box">
            <h3 style="color: var(--accent-primary);">Configurazione Postazione</h3>
            <p class="text-muted">Seleziona la biblioteca operativa per questa sessione.</p>
            <form method="POST">
                <select name="id_biblioteca" class="form-select form-select-lg mb-3">
                    <?php foreach ($biblioteche as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="set_biblioteca" class="btn btn-main w-100 py-2">Imposta Postazione</button>
            </form>
        </div>
    <?php else: ?>
    
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: #2c2c2c;">Gestione Prestiti Attivi</h2>
                <p class="text-muted mb-0">Gestisci restituzioni, danni e sanzioni.</p>
                <?php if ($isAdmin): ?>
                    <span class="badge bg-secondary">Admin Global View</span>
                <?php endif; ?>
            </div>
            
            <div class="d-flex gap-2">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cerca utente (CF), libro..." value="<?= htmlspecialchars($searchTerm) ?>">
                        <button class="btn btn-outline-secondary" type="submit">Cerca</button>
                    </div>
                    <?php if(!empty($searchTerm)): ?>
                        <a href="dashboard-gestioneprestiti" class="btn btn-light ms-2" title="Reset"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>

                <a href="dashboard-aggiuntaprestiti" class="btn btn-primary shadow-sm" style="background-color: var(--accent-primary); border:none;">
                    <i class="bi bi-plus-lg me-2"></i>Nuovo Prestito
                </a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Operazione completata!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($messaggio): ?>
            <div class="alert alert-danger shadow-sm border-0" role="alert">
                <?= htmlspecialchars($messaggio) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th class="ps-4">Libro / Copia</th>
                                <th>Utente</th>
                                <th>Dettagli</th>
                                <th>Stato</th>
                                <th class="text-end pe-4">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($prestitiAttivi)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                    Nessun prestito attivo trovato.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prestitiAttivi as $p): ?>
                                <?php 
                                    $giorni = $p['giorni_rimanenti'];
                                    $statusClass = 'bg-success-soft';
                                    $statusText = 'Regolare';
                                    if ($giorni < 0) { $statusClass = 'bg-danger-soft'; $statusText = 'Scaduto'; } 
                                    elseif ($giorni <= 3) { $statusClass = 'bg-warning-soft'; $statusText = 'In scadenza'; }
                                    
                                    // Logica Colore Barra Condizioni
                                    $cond = (int)$p['condizione'];
                                    $condColor = "#e0e0e0";
                                    if ($cond === 1) $condColor = "#f1c40f";
                                    else if ($cond === 2) $condColor = "#2ecc71";
                                    else if ($cond === 3) $condColor = "#27ae60";
                                    else if ($cond === 0) $condColor = "#c0392b";
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['titolo']) ?></div>
                                        <div class="small text-muted">
                                            <i class="bi bi-upc-scan"></i> ID: <?= $p['id_copia'] ?>
                                            <?php if ($isAdmin): ?>
                                                <br><span class="badge bg-light text-dark border">📍 <?= htmlspecialchars($p['nome_biblioteca']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($p['cognome'] . ' ' . $p['nome']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($p['codice_alfanumerico']) ?></div>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($p['codice_fiscale']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-muted">Dal: <?= date('d/m/Y', strtotime($p['data_prestito'])) ?></div>
                                        <div class="fw-bold text-dark">Al: <?= date('d/m/Y', strtotime($p['data_scadenza'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $statusClass ?> d-inline-block mb-1"><?= $statusText ?></span>
                                        <?php if ($p['multe_pendenti'] > 0): ?>
                                            <br><span class="badge bg-danger text-white rounded-pill" style="font-size:0.7rem;"><i class="bi bi-exclamation-circle"></i> Multe attive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2 align-items-center">
                                            
                                            <?php if ($isAdmin): ?>
                                                <a href="/bibliotecario/gestione-multe?q=<?= $p['codice_alfanumerico'] ?>" class="btn btn-sm btn-outline-warning text-dark fw-bold" title="Vedi Multe Utente">
                                                    <i class="bi bi-cash-coin me-1"></i> Multe
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($isBibliotecario): ?>
                                                <div style="display:flex; flex-direction:column; align-items:center; margin-right:5px;">
                                                    <div class="cond-bar-wrapper" title="Condizione: <?= $cond ?>/3">
                                                        <?php for($i=0; $i<3; $i++): ?>
                                                            <div class="cond-segment" style="background-color: <?= ($i < $cond) ? $condColor : '#ddd' ?>;"></div>
                                                        <?php endfor; ?>
                                                    </div>
                                                    
                                                    <form method="POST">
                                                        <input type="hidden" name="azione" value="multa_danni">
                                                        <input type="hidden" name="id_prestito" value="<?= $p['id_prestito'] ?>">
                                                        <input type="hidden" name="id_copia" value="<?= $p['id_copia'] ?>">
                                                        
                                                        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold" 
                                                                title="Segnala Danni e Chiudi Prestito" 
                                                                onclick="return confirm('ATTENZIONE: Verrà emessa una multa di 15€, ridotta la condizione e CHIUSO il prestito. Confermi?')"
                                                                <?= ($cond <= 0) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                                                            <i class="bi bi-hammer"></i> Danni
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>

                                            <form method="POST" class="d-flex align-items-center bg-light rounded p-1 border">
                                                <input type="date" name="data_fine" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm border-0 bg-transparent" style="width: 110px;" title="Data restituzione">
                                                <input type="hidden" name="restituisci_id" value="<?= $p['id_prestito'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success text-white fw-bold ms-1" onclick="return confirm('Confermi la restituzione corretta?')">
                                                    <i class="bi bi-box-arrow-in-down-left"></i> Restituisci
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once $baseDir . '/src/includes/footer.php'; ?>
</body>
</html>