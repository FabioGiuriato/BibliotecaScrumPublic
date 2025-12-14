<?php
// Configurazione base
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Rimuove limiti di tempo

// ---------------- CONFIGURAZIONE ---------------- //
$saveDir = __DIR__ . '/public/bookCover/';
$batchSize = 35; // Google Books supporta max 40 risultati per pagina. Stiamo sotto per sicurezza.
// ------------------------------------------------ //

// Crea cartella se non esiste
if (!file_exists($saveDir)) {
    if (!mkdir($saveDir, 0777, true)) {
        die("Errore: Impossibile creare la cartella $saveDir.");
    }
}

require_once 'db_config.php';

echo "<h1>Scaricamento Copertine Bulk (Ottimizzato)</h1>";
echo "<pre>";

try {
    // 1. Recupero tutti gli ISBN dal DB
    $stmt = $pdo->query("SELECT isbn FROM libri WHERE isbn IS NOT NULL AND isbn != ''");
    $libri = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Totale libri nel DB: " . count($libri) . "\n";

    // 2. Identifico solo quelli che MANCANO
    $isbnsDaScaricare = [];
    $giaPresenti = 0;

    foreach ($libri as $libro) {
        $isbn = trim($libro['isbn']);
        $pathPng = $saveDir . $isbn . '.png';
        $pathJpg = $saveDir . $isbn . '.jpg';

        if (file_exists($pathPng) || file_exists($pathJpg)) {
            $giaPresenti++;
        } else {
            $isbnsDaScaricare[] = $isbn;
        }
    }

    echo "Già presenti: $giaPresenti\n";
    echo "Da scaricare: " . count($isbnsDaScaricare) . "\n\n";

    if (empty($isbnsDaScaricare)) {
        echo "Nessun libro da scaricare. Fine.";
        echo "</pre>";
        exit;
    }

    // 3. Suddivido gli ISBN in pacchetti (Batch)
    $batches = array_chunk($isbnsDaScaricare, $batchSize);
    $totalBatches = count($batches);

    foreach ($batches as $index => $batchIsbns) {
        $currentBatchNum = $index + 1;
        echo "Processing Batch $currentBatchNum di $totalBatches (" . count($batchIsbns) . " ISBN)...\n";

        // 4. Costruisco la query con OR (isbn:AAA OR isbn:BBB ...)
        $queryParts = [];
        foreach ($batchIsbns as $isbn) {
            $queryParts[] = "isbn:" . $isbn;
        }
        $queryString = implode(' OR ', $queryParts);
        
        // URL Encode della query
        $encodedQuery = urlencode($queryString);
        
        // Costruzione URL API (maxResults è fondamentale qui)
        $apiUrl = "https://www.googleapis.com/books/v1/volumes?q=" . $encodedQuery . "&maxResults=" . count($batchIsbns);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: PHPScript/1.0'
            ]
        ]);

        $json = @file_get_contents($apiUrl, false, $context);
        
        if ($json === FALSE) {
            echo " - ERRORE nella richiesta API per questo batch.\n";
            continue;
        }

        $data = json_decode($json, true);

        if (!isset($data['items'])) {
            echo " - Nessun risultato trovato per questo gruppo di ISBN.\n";
            continue; // Passa al prossimo batch
        }

        // 5. Processo i risultati del batch
        $scaricatiBatch = 0;

        foreach ($data['items'] as $item) {
            $volInfo = $item['volumeInfo'];
            
            // Cerco l'immagine
            if (isset($volInfo['imageLinks'])) {
                $links = $volInfo['imageLinks'];
                $imageUrl = $links['extraLarge'] ?? $links['large'] ?? $links['medium'] ?? $links['small'] ?? $links['thumbnail'] ?? null;

                if ($imageUrl) {
                    $imageUrl = str_replace('http://', 'https://', $imageUrl);
                    
                    // PROBLEMA: Google restituisce i libri, ma non sappiamo in che ordine.
                    // Dobbiamo cercare tra gli identifier del risultato quale ISBN corrisponde 
                    // a uno dei nostri ISBN richiesti nel batch ($batchIsbns).
                    
                    $matchedIsbn = null;
                    if (isset($volInfo['industryIdentifiers'])) {
                        foreach ($volInfo['industryIdentifiers'] as $identifier) {
                            if (in_array($identifier['identifier'], $batchIsbns)) {
                                $matchedIsbn = $identifier['identifier'];
                                break;
                            }
                        }
                    }

                    // Se abbiamo trovato a quale ISBN corrisponde questa immagine
                    if ($matchedIsbn) {
                        $imageContent = @file_get_contents($imageUrl, false, $context);
                        if ($imageContent) {
                            file_put_contents($saveDir . $matchedIsbn . '.png', $imageContent);
                            echo "   -> Salvato: $matchedIsbn\n";
                            $scaricatiBatch++;
                            
                            // Rimuovo l'ISBN dalla lista del batch per evitare doppi salvataggi se Google duplica
                            $key = array_search($matchedIsbn, $batchIsbns);
                            if ($key !== false) unset($batchIsbns[$key]);
                        }
                    }
                }
            }
        }
        
        echo " - Batch completato. Scaricati: $scaricatiBatch\n";
        
        // Pausa tra i batch (non tra i singoli libri)
        sleep(1); 
    }

} catch (PDOException $e) {
    echo "Errore DB: " . $e->getMessage();
}

echo "\nOperazione Bulk Completata.</pre>";
?>