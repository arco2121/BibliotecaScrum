<?php
require_once 'security.php';
if (!checkAccess('amministratore') ) {
    header('Location: ../index.php');
    exit;
}
require_once 'db_config.php';

$sogliaScaduti = 10;
ciao
try {
    // 1. KPI - Corretto IS NULL
    $stmtKpi = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM libri) as totale_titoli,
            (SELECT COUNT(*) FROM copie) as copie_fisiche,
            (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL) as prestiti_attivi,
            (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL AND data_scadenza < CURDATE()) as prestiti_scaduti,
            (SELECT IFNULL(SUM(importo), 0) FROM multe WHERE pagata = 0) as multe_totali,
            (SELECT COUNT(*) FROM utenti) as utenti_totali,
            (SELECT COUNT(*) FROM utenti WHERE data_creazione >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as nuovi_utenti_mese
    ");
    $kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC);

    // 2. Prestiti Mensili
    $trendPrestiti = $pdo->query("
        SELECT DATE_FORMAT(data_prestito, '%m/%Y') as mese, COUNT(*) as totale
        FROM prestiti WHERE data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY LAST_DAY(data_prestito) ORDER BY LAST_DAY(data_prestito) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Top 10 Libri
    $topLibri = $pdo->query("
        SELECT l.titolo, COUNT(p.id_prestito) as n_prestiti 
        FROM libri l JOIN copie c ON l.isbn = c.isbn JOIN prestiti p ON c.id_copia = p.id_copia
        GROUP BY l.isbn ORDER BY n_prestiti DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Categorie
    $distribuzioneCat = $pdo->query("
        SELECT c.categoria, COUNT(lc.isbn) as conteggio 
        FROM categorie c JOIN libro_categoria lc ON c.id_categoria = lc.id_categoria
        GROUP BY c.id_categoria
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Nuovi Utenti
    $trendUtenti = $pdo->query("
        SELECT DATE_FORMAT(data_creazione, '%d/%m') as giorno, COUNT(*) as nuovi 
        FROM utenti WHERE data_creazione >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY data_creazione ORDER BY data_creazione ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Errore: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Report Biblioteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f7f6; }
        .card-kpi { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        /* FISSA L'ALTEZZA DEI GRAFICI PER EVITARE CHE VADANO IN GIÙ */
        .chart-wrapper { position: relative; height: 280px; width: 100%; }
        h5 { font-size: 1rem; font-weight: 700; color: #444; margin-bottom: 1.5rem; }
        .bg-alert-soft { background-color: #fff5f5; border: 1px solid #feb2b2; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-2">
        <h2 class="h4 fw-bold m-0">📊 Report Dashboard</h2>
        <button class="btn btn-sm btn-white border shadow-sm" onclick="window.print()">Stampa PDF</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card card-kpi p-3 border-start border-primary border-4">
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Titoli</small>
                <div class="h4 m-0"><?= $kpi['totale_titoli'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card card-kpi p-3 border-start border-success border-4">
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Prestiti Attivi</small>
                <div class="h4 m-0"><?= $kpi['prestiti_attivi'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card card-kpi p-3 border-start border-danger border-4 <?= ($kpi['prestiti_scaduti'] > $sogliaScaduti) ? 'bg-alert-soft' : '' ?>">
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Scaduti</small>
                <div class="h4 m-0 <?= ($kpi['prestiti_scaduti'] > $sogliaScaduti) ? 'text-danger' : '' ?>"><?= $kpi['prestiti_scaduti'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card card-kpi p-3 border-start border-warning border-4">
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Multe</small>
                <div class="h4 m-0">€<?= number_format($kpi['multe_totali'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card card-kpi p-3 border-start border-info border-4">
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Utenti Totali</small>
                <div class="h4 m-0"><?= $kpi['utenti_totali'] ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 15px;">
                <h5>Andamento Prestiti Mensili</h5>
                <div class="chart-wrapper"><canvas id="linePrestiti"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 15px;">
                <h5>Categorie</h5>
                <div class="chart-wrapper"><canvas id="pieCategorie"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 15px;">
                <h5>I 10 Libri più richiesti</h5>
                <div class="chart-wrapper"><canvas id="barLibri"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4" style="border-radius: 15px;">
                <h5>Trend Nuove Iscrizioni</h5>
                <div class="chart-wrapper"><canvas id="areaUtenti"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Configurazione globale
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#888';

    const ctxLine = document.getElementById('linePrestiti');
    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trendPrestiti, 'mese')) ?>,
            datasets: [{
                label: 'Prestiti',
                data: <?= json_encode(array_column($trendPrestiti, 'totale')) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.05)',
                fill: true,
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    const ctxPie = document.getElementById('pieCategorie');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($distribuzioneCat, 'categoria')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($distribuzioneCat, 'conteggio')) ?>,
                backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#198754']
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
    });

    const ctxBar = document.getElementById('barLibri');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topLibri, 'titolo')) ?>,
            datasets: [{
                label: 'N. Prestiti',
                data: <?= json_encode(array_column($topLibri, 'n_prestiti')) ?>,
                backgroundColor: '#0dcaf0',
                borderRadius: 5
            }]
        },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    const ctxArea = document.getElementById('areaUtenti');
    new Chart(ctxArea, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trendUtenti, 'giorno')) ?>,
            datasets: [{
                label: 'Iscritti',
                data: <?= json_encode(array_column($trendUtenti, 'nuovi')) ?>,
                fill: true,
                backgroundColor: 'rgba(111, 66, 193, 0.1)',
                borderColor: '#6f42c1',
                tension: 0.3
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
</script>

</body>
</html>