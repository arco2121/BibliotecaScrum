<?php
require_once 'security.php';
if (!checkAccess('amministratore') ) {
    header('Location: ../index.php');
    exit;
}
require_once 'db_config.php';

// Inizializzazione KPI e variabili
$kpi = ['totale_titoli' => 0, 'copie_fisiche' => 0, 'prestiti_attivi' => 0, 'prestiti_scaduti' => 0, 'scadenza_oggi' => 0, 'multe_totali' => 0, 'utenti_totali' => 0];
$trendPrestiti = []; $topLibri = []; $distribuzioneCat = []; $trendUtenti = []; $ruoliLabels = []; $ruoliValori = []; $statoCopie = [];

// Variabili per le Tabelle
$ultimiPrestiti = []; $scadenzeProssime = []; $topUtenti = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. KPI
        $stmtKpi = $pdo->query("SELECT (SELECT COUNT(*) FROM libri) as totale_titoli, (SELECT COUNT(*) FROM copie) as copie_fisiche, (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL) as prestiti_attivi, (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL AND data_scadenza < CURDATE()) as prestiti_scaduti, (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL AND data_scadenza = CURDATE()) as scadenza_oggi, (SELECT COUNT(*) FROM multe WHERE pagata = 0) as multe_totali, (SELECT COUNT(*) FROM utenti) as utenti_totali");
        $kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC);

        // 2. Analisi Categorie (Prestiti attivi)
        $distribuzioneCat = $pdo->query("SELECT c.categoria, COUNT(p.id_prestito) as conteggio FROM categorie c JOIN libro_categoria lc ON c.id_categoria = lc.id_categoria JOIN copie cp ON lc.isbn = cp.isbn JOIN prestiti p ON cp.id_copia = p.id_copia WHERE p.data_restituzione IS NULL GROUP BY c.id_categoria")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Distribuzione Ruoli
        $distRuoli = $pdo->query("SELECT SUM(studente) as Studenti, SUM(docente) as Docenti, SUM(bibliotecario) as Bibliotecari, SUM(amministratore) as Admin FROM ruoli")->fetch(PDO::FETCH_ASSOC);
        $ruoliLabels = array_keys($distRuoli); $ruoliValori = array_values($distRuoli);

        // 4. TABELLE OPERATIVE (Novità)
        // Ultimi 10 prestiti
        $ultimiPrestiti = $pdo->query("SELECT p.data_prestito, l.titolo, u.nome, u.cognome FROM prestiti p JOIN copie c ON p.id_copia = c.id_copia JOIN libri l ON c.isbn = l.isbn JOIN utenti u ON p.codice_alfanumerico = u.codice_alfanumerico ORDER BY p.data_prestito DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

        // Scadenze Oggi o Domani
        $scadenzeProssime = $pdo->query("SELECT p.data_scadenza, l.titolo, u.email FROM prestiti p JOIN copie c ON p.id_copia = c.id_copia JOIN libri l ON c.isbn = l.isbn JOIN utenti u ON p.codice_alfanumerico = u.codice_alfanumerico WHERE p.data_restituzione IS NULL AND (p.data_scadenza = CURDATE() OR p.data_scadenza = DATE_ADD(CURDATE(), INTERVAL 1 DAY)) ORDER BY p.data_scadenza ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Top 10 Utenti più attivi
        $topUtenti = $pdo->query("SELECT u.nome, u.cognome, COUNT(p.id_prestito) as tot FROM utenti u JOIN prestiti p ON u.codice_alfanumerico = p.codice_alfanumerico GROUP BY u.codice_alfanumerico ORDER BY tot DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

        // Altri dati per grafici (semplificati per brevità)
        $topLibri = $pdo->query("SELECT l.titolo, COUNT(p.id_prestito) as n_prestiti FROM libri l JOIN copie c ON l.isbn = c.isbn JOIN prestiti p ON c.id_copia = p.id_copia GROUP BY l.isbn ORDER BY n_prestiti DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        $statoCopie = $pdo->query("SELECT (SELECT COUNT(*) FROM copie) - (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL) as Disponibili, (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL) as In_Prestito")->fetch(PDO::FETCH_ASSOC);
        $trendPrestiti = $pdo->query("SELECT DATE_FORMAT(data_prestito, '%m/%Y') as mese, COUNT(*) as totale FROM prestiti WHERE data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY LAST_DAY(data_prestito) ORDER BY LAST_DAY(data_prestito) ASC")->fetchAll(PDO::FETCH_ASSOC);
        $trendUtenti = $pdo->query("SELECT DATE_FORMAT(data_creazione, '%d/%m') as giorno, COUNT(*) as nuovi FROM utenti WHERE data_creazione >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY data_creazione ORDER BY data_creazione ASC")->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$title = "Dashboard Catalogo Libri";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .chart-container { position: relative; height: 250px; }
        .table-responsive { font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 fw-bold m-0 text-dark">📊 Dashboard Operativa</h2>
            <p class="text-muted small m-0">Panoramica in tempo reale del catalogo</p>
        </div>
        <div class="btn-group shadow-sm">
            <button class="btn btn-white btn-sm border" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> Aggiorna</button>
            <button class="btn btn-primary btn-sm" onclick="window.print()">Esporta Report PDF</button>
            <button class="btn btn-success btn-sm" onclick="alert('Export Excel in fase di implementazione')">Esporta CSV</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $colors = ['primary', 'success', 'warning', 'danger', 'dark', 'info'];
        $i = 0;
        foreach(['Titoli' => 'totale_titoli', 'In Prestito' => 'prestiti_attivi', 'Scadenza Oggi' => 'scadenza_oggi', 'In Ritardo' => 'prestiti_scaduti', 'Multe' => 'multe_totali', 'Utenti' => 'utenti_totali'] as $label => $key): ?>
            <div class="col-md-2">
                <div class="card p-3 border-bottom border-4 border-<?= $colors[$i++] ?>">
                    <div class="text-muted small fw-bold text-uppercase"><?= $label ?></div>
                    <div class="h3 mb-0"><?= $kpi[$key] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4"><div class="card p-4"><h6>Categorie Attive</h6><div class="chart-container"><canvas id="pieCat"></canvas></div></div></div>
        <div class="col-lg-4"><div class="card p-4"><h6>Disponibilità</h6><div class="chart-container"><canvas id="pieDispo"></canvas></div></div></div>
        <div class="col-lg-4"><div class="card p-4"><h6>Ruoli Utenti</h6><div class="chart-container"><canvas id="barRuoli"></canvas></div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h6 class="fw-bold mb-3 text-primary border-bottom pb-2">Ultimi 10 Prestiti</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Libro</th><th>Utente</th><th>Data</th></tr></thead>
                        <tbody>
                        <?php foreach($ultimiPrestiti as $p): ?>
                            <tr><td><?= substr($p['titolo'], 0, 20) ?>..</td><td><?= $p['cognome'] ?></td><td><?= date('d/m', strtotime($p['data_prestito'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h6 class="fw-bold mb-3 text-warning border-bottom pb-2">Scadenze (Oggi/Domani)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Libro</th><th>Data</th><th>Azioni</th></tr></thead>
                        <tbody>
                        <?php foreach($scadenzeProssime as $s): ?>
                            <tr class="<?= $s['data_scadenza'] == date('Y-m-d') ? 'table-warning' : '' ?>">
                                <td><?= substr($s['titolo'], 0, 20) ?>..</td><td><?= date('d/m', strtotime($s['data_scadenza'])) ?></td>
                                <td><a href="mailto:<?= $s['email'] ?>" class="btn btn-xs btn-outline-dark py-0" style="font-size: 10px;">Sollecita</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h6 class="fw-bold mb-3 text-success border-bottom pb-2">Top 10 Lettori</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Utente</th><th>N. Prestiti</th></tr></thead>
                        <tbody>
                        <?php foreach($topUtenti as $u): ?>
                            <tr><td><?= $u['nome'] ?> <?= $u['cognome'] ?></td><td class="fw-bold"><?= $u['tot'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4"><div class="card p-4"><h6>Top Libri</h6><div class="chart-container"><canvas id="barLibri"></canvas></div></div></div>
        <div class="col-lg-4"><div class="card p-4"><h6>Trend Prestiti</h6><div class="chart-container"><canvas id="linePrestiti"></canvas></div></div></div>
        <div class="col-lg-4"><div class="card p-4"><h6>Nuovi Utenti</h6><div class="chart-container"><canvas id="areaUtenti"></canvas></div></div></div>
    </div>
</div>

<script>
    // Inizializzazione Grafici (Semplificata)
    const ctxPie = document.getElementById('pieCat');
    new Chart(ctxPie, { type: 'doughnut', data: { labels: <?= json_encode(array_column($distribuzioneCat, 'categoria')) ?>, datasets: [{ data: <?= json_encode(array_column($distribuzioneCat, 'conteggio')) ?>, backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'] }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });

    const ctxDispo = document.getElementById('pieDispo');
    new Chart(ctxDispo, { type: 'pie', data: { labels: ['Disponibili', 'In Prestito'], datasets: [{ data: [<?= $statoCopie['Disponibili'] ?>, <?= $statoCopie['In_Prestito'] ?>], backgroundColor: ['#198754', '#f6c23e'] }] }, options: { maintainAspectRatio: false } });

    const ctxRuoli = document.getElementById('barRuoli');
    new Chart(ctxRuoli, { type: 'bar', data: { labels: <?= json_encode($ruoliLabels) ?>, datasets: [{ data: <?= json_encode($ruoliValori) ?>, backgroundColor: '#4e73df' }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } } });

    const ctxLibri = document.getElementById('barLibri');
    new Chart(ctxLibri, { type: 'bar', data: { labels: <?= json_encode(array_column($topLibri, 'titolo')) ?>, datasets: [{ data: <?= json_encode(array_column($topLibri, 'n_prestiti')) ?>, backgroundColor: '#36b9cc' }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } } });

    const ctxTrend = document.getElementById('linePrestiti');
    new Chart(ctxTrend, { type: 'line', data: { labels: <?= json_encode(array_column($trendPrestiti, 'mese')) ?>, datasets: [{ data: <?= json_encode(array_column($trendPrestiti, 'totale')) ?>, borderColor: '#4e73df', fill: true, backgroundColor: 'rgba(78,115,223,0.05)' }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } } } });

    const ctxArea = document.getElementById('areaUtenti');
    new Chart(ctxArea, { type: 'line', data: { labels: <?= json_encode(array_column($trendUtenti, 'giorno')) ?>, datasets: [{ data: <?= json_encode(array_column($trendUtenti, 'nuovi')) ?>, borderColor: '#6f42c1', fill: true, backgroundColor: 'rgba(111,66,193,0.1)' }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } } } });
</script>
</body>
</html>