<?php
// Configurazione
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$saveDir = __DIR__ . '/public/bookCover/';
$concurrentRequests = 10; // Numero di richieste simultanee (non esagerare per evitare ban IP)

if (!file_exists($saveDir)) {
    mkdir($saveDir, 0777, true);
}

require_once 'db_config.php';

echo "<h1>Scaricamento Parallelo (Multi-Curl)</h1><pre>";

try {
    // 1. Prendo tutti i libri
    $stmt = $pdo->query("SELECT isbn FROM libri WHERE isbn IS NOT NULL AND isbn != ''");
    $libri = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Filtro quelli che mancano
    $daScaricare = [];
    foreach ($libri as $l) {
        $isbn = trim($l['isbn']);
        if (!file_exists($saveDir . $isbn . '.png') && !file_exists($saveDir . $isbn . '.jpg')) {
            $daScaricare[] = $isbn;
        }
    }

    $totale = count($daScaricare);
    echo "Libri da scaricare: $totale\n\n";

    if ($totale === 0) {
        echo "Tutto aggiornato.";
        exit;
    }

    // 3. Processo a blocchi paralleli
    $batches = array_chunk($daScaricare, $concurrentRequests);
    
    foreach ($batches as $batchIndex => $batchIsbns) {
        $mh = curl_multi_init();
        $curlHandles = [];

        // Preparo le richieste multiple
        foreach ($batchIsbns as $isbn) {
            $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . $isbn;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Aggiungo alla "pila" multi-curl
            curl_multi_add_handle($mh, $ch);
            
            // Salvo il riferimento usando l'ISBN come chiave per ritrovarlo dopo
            $curlHandles[$isbn] = $ch;
        }

        // Eseguo tutte le richieste simultaneamente
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // Raccolgo i risultati
        foreach ($curlHandles as $isbn => $ch) {
            $response = curl_multi_get_content($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['items'][0]['volumeInfo']['imageLinks'])) {
                    $links = $data['items'][0]['volumeInfo']['imageLinks'];
                    
                    // Cerco la qualità migliore
                    $imgUrl = $links['extraLarge'] 
                           ?? $links['large'] 
                           ?? $links['medium'] 
                           ?? $links['small'] 
                           ?? $links['thumbnail'] 
                           ?? null;

                    if ($imgUrl) {
                        $imgUrl = str_replace('http://', 'https://', $imgUrl);
                        $imgData = @file_get_contents($imgUrl); // Scarico l'immagine effettiva
                        if ($imgData) {
                            file_put_contents($saveDir . $isbn . '.png', $imgData);
                            echo "[OK] $isbn salvato.\n";
                        }
                    } else {
                        echo "[NO IMG] $isbn (Nessun link immagine nel JSON)\n";
                    }
                } else {
                    echo "[NO DATA] $isbn (Libro non trovato o senza copertina)\n";
                }
            } else {
                echo "[ERR API] $isbn (Http Code: $httpCode)\n";
            }

            // Pulisco la memoria
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        
        echo "--- Blocco completato ---\n";
        flush(); // Forza l'output a video
        sleep(1); // Pausa di sicurezza tra un blocco e l'altro
    }

} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}

echo "\nFinito.";
?>