<?php
require_once 'security.php';

// Controllo accesso: solo amministratori e bibliotecari
if (!checkAccess('amministratore') && !checkAccess('bibliotecario')) {
    header('Location: ./');
    exit;
}

require_once 'db_config.php';

$messaggio = "";
$richieste = [];

if (isset($pdo)) {
    try {
        // QUERY: Recupera le richieste (INVARIATA)
        $stmt = $pdo->prepare("
            SELECT 
                rb.id_richiesta,
                rb.id_prestito,
                rb.tipo_richiesta,
                rb.data_richiesta,
                rb.data_scadenza_richiesta, 
                rb.stato,
                p.data_scadenza as scadenza_attuale_prestito,
                p.codice_alfanumerico,
                p.id_copia,
                u.nome,
                u.cognome,
                l.titolo,
                (SELECT COUNT(*) FROM multe m JOIN prestiti p2 ON m.id_prestito = p2.id_prestito WHERE p2.codice_alfanumerico = p.codice_alfanumerico AND m.pagata = 0) as numero_multe,
                (SELECT COALESCE(SUM(m.importo), 0) FROM multe m JOIN prestiti p2 ON m.id_prestito = p2.id_prestito WHERE p2.codice_alfanumerico = p.codice_alfanumerico AND m.pagata = 0) as totale_multe
            FROM richieste_bibliotecario rb
            JOIN prestiti p ON rb.id_prestito = p.id_prestito
            JOIN utenti u ON p.codice_alfanumerico = u.codice_alfanumerico
            JOIN copie c ON p.id_copia = c.id_copia
            JOIN libri l ON c.isbn = l.isbn
            ORDER BY CASE WHEN rb.stato = 'in_attesa' THEN 0 ELSE 1 END, rb.data_richiesta DESC
        ");
        $stmt->execute();
        $richieste = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $messaggio = "Errore caricamento dati: " . $e->getMessage();
    }
}

// GESTIONE AZIONI (POST) - (LOGICA INVARIATA)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id_richiesta'])) {
        $action = $_POST['action'];
        $id_richiesta = filter_input(INPUT_POST, 'id_richiesta', FILTER_VALIDATE_INT);

        if ($id_richiesta) {
            try {
                $pdo->beginTransaction();
                $stmt_info = $pdo->prepare("SELECT id_prestito FROM richieste_bibliotecario WHERE id_richiesta = ?");
                $stmt_info->execute([$id_richiesta]);
                $req_data = $stmt_info->fetch(PDO::FETCH_ASSOC);

                if (!$req_data) throw new Exception("Richiesta non trovata.");
                $id_prestito_target = $req_data['id_prestito'];

                switch ($action) {
                    case 'approva':
                        $stmt = $pdo->prepare("UPDATE richieste_bibliotecario SET stato = 'approvata' WHERE id_richiesta = ?");
                        $stmt->execute([$id_richiesta]);
                        // Logica rinnovo standard (+7 alla scadenza attuale) come da tua richiesta precedente
                        $stmt_upd = $pdo->prepare("UPDATE prestiti SET data_scadenza = DATE_ADD(data_scadenza, INTERVAL 7 DAY), num_rinnovi = num_rinnovi + 1 WHERE id_prestito = ?");
                        $stmt_upd->execute([$id_prestito_target]);
                        break;
                    case 'rifiuta':
                        $stmt = $pdo->prepare("UPDATE richieste_bibliotecario SET stato = 'rifiutata' WHERE id_richiesta = ?");
                        $stmt->execute([$id_richiesta]);
                        break;
                }
                $pdo->commit();
                header("Location: dashboard-richieste");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $messaggio = "Errore durante l'operazione: " . $e->getMessage();
            }
        }
    }
}
?>

<?php
$title = "Gestione Richieste";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

<style>
    .dashboard-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: none;
        overflow: hidden; /* Per i bordi arrotondati della tabella */
    }
    .page-header {
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
    }
    .table-custom th {
        background-color: #f8f9fa;
        color: #6c757d;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef;
        padding: 15px;
    }
    .table-custom td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f2f5;
    }
    .table-custom tr:last-child td {
        border-bottom: none;
    }
    .user-avatar-placeholder {
        width: 35px;
        height: 35px;
        background-color: #e9ecef;
        color: #495057;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 10px;
    }
    .book-title {
        color: #2c3e50;
        font-weight: 600;
        display: block;
    }
    .action-btn-group {
        display: flex;
        gap: 5px;
        justify-content: flex-end;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-approved { background-color: #d4edda; color: #155724; }
    .status-rejected { background-color: #f8d7da; color: #721c24; }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #adb5bd;
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block;
    }
</style>

<div class="page_contents" style="background-color: #fcfcfc; min-height: 90vh; padding-top: 30px;">
    <div class="container-fluid" style="max-width: 1400px;">
        
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-inbox-fill text-primary me-2"></i>Gestione Richieste</h2>
                <p class="text-muted mb-0">Visualizza e gestisci le richieste di rinnovo degli utenti.</p>
            </div>
            </div>
        
        <?php if ($messaggio): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3">
                <i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($messaggio) ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 5%;">ID</th>
                            <th scope="col" style="width: 20%;">Utente</th>
                            <th scope="col" style="width: 25%;">Libro & Prestito</th>
                            <th scope="col" style="width: 15%;">Tempistiche</th>
                            <th scope="col" style="width: 10%;">Stato</th>
                            <th scope="col" style="width: 15%;">Affidabilità</th>
                            <th scope="col" style="width: 10%; text-align: right;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($richieste)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-check"></i>
                                        <h5>Tutto tranquillo!</h5>
                                        <p>Non ci sono richieste in sospeso al momento.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($richieste as $req): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border">#<?= htmlspecialchars($req['id_richiesta']) ?></span></td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-placeholder">
                                                <?= strtoupper(substr($req['nome'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($req['cognome'] . ' ' . $req['nome']) ?></div>
                                                <div class="text-muted small" style="font-family: monospace;"><?= htmlspecialchars($req['codice_alfanumerico']) ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="book-title"><?= htmlspecialchars($req['titolo']) ?></span>
                                        <span class="text-muted small">
                                            <i class="bi bi-upc-scan me-1"></i>Prestito #<?= htmlspecialchars($req['id_prestito']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="small">
                                            <div class="mb-1" title="Data richiesta">
                                                <i class="bi bi-calendar-event me-1 text-secondary"></i> Richiesto: <?= date('d/m/y', strtotime($req['data_richiesta'])) ?>
                                            </div>
                                            <div class="text-danger fw-bold" title="Scadenza attuale del prestito">
                                                <i class="bi bi-hourglass-split me-1"></i> Scade: <?= date('d/m/y', strtotime($req['scadenza_attuale_prestito'])) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php 
                                        if($req['stato'] == 'approvata') {
                                            echo '<span class="status-badge status-approved"><i class="bi bi-check-circle-fill"></i> Approvata</span>';
                                        } elseif($req['stato'] == 'rifiutata') {
                                            echo '<span class="status-badge status-rejected"><i class="bi bi-x-circle-fill"></i> Rifiutata</span>';
                                        } else {
                                            echo '<span class="status-badge status-pending"><i class="bi bi-clock-fill"></i> In Attesa</span>';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <?php if ($req['numero_multe'] > 0): ?>
                                            <div class="d-flex align-items-center text-danger">
                                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                                <div style="line-height: 1.2;">
                                                    <div class="fw-bold"><?= $req['numero_multe'] ?> Multe</div>
                                                    <small>Tot: €<?= number_format($req['totale_multe'], 2, ',', '.') ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-success small fw-bold">
                                                <i class="bi bi-shield-check me-1 fs-5" style="vertical-align: middle;"></i> Regolare
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="action-btn-group">
                                            <?php if ($req['stato'] === 'in_attesa'): ?>
                                                <form method="post" class="d-flex gap-1">
                                                    <input type="hidden" name="id_richiesta" value="<?= htmlspecialchars($req['id_richiesta']) ?>">
                                                    
                                                    <button type="submit" name="action" value="approva" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="tooltip" title="Approva (+7gg)">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    
                                                    <button type="submit" name="action" value="rifiuta" class="btn btn-outline-danger btn-sm shadow-sm" data-bs-toggle="tooltip" title="Rifiuta" onclick="return confirm('Sei sicuro di voler rifiutare questa richiesta?');">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Nessuna azione</span>
                                            <?php endif; ?>
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
</div>

<script>
    // Abilita i tooltip di Bootstrap (se inclusi nel tuo header/footer)
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once './src/includes/footer.php'; ?>