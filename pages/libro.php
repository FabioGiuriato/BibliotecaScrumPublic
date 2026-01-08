<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

$messaggio_db = "";
$messaggio_form = ""; // Nuovo: per messaggi relativi all'invio del commento
$libro = null;
$autori = [];
$categorie = [];
$recensioni = [];
$mediaVoto = 0;
$totaleRecensioni = 0;

$isbn = $_GET['isbn'] ?? null;

if (!$isbn) {
    die("<h1>Errore</h1><p>ISBN non specificato.</p>");
}

// --- GESTIONE NUOVO COMMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // IMPORTANTE: Sostituisci 'codice_alfanumerico' con la tua variabile di sessione utente reale se diversa
    if (!isset($_SESSION['codice_alfanumerico'])) {
        $messaggio_form = "<div class='alert alert-warning'>Devi effettuare il login per lasciare una recensione.</div>";
    } else {
        $voto = filter_input(INPUT_POST, 'voto', FILTER_VALIDATE_INT);
        $commento = trim(filter_input(INPUT_POST, 'commento', FILTER_SANITIZE_STRING));
        $utente_id = $_SESSION['codice_alfanumerico'];

        if ($voto < 1 || $voto > 5 || empty($commento)) {
            $messaggio_form = "<div class='alert alert-danger'>Per favore, inserisci un voto valido (1-5) e un commento.</div>";
        } else {
            try {
                // Query di inserimento (assicurati che i nomi delle colonne siano corretti nel tuo DB)
                $stmtInsert = $pdo->prepare("
                    INSERT INTO recensioni (isbn, codice_alfanumerico, voto, commento, data_commento) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmtInsert->execute([$isbn, $utente_id, $voto, $commento]);
                
                // Refresh della pagina per mostrare il nuovo commento ed evitare doppio invio
                header("Location: " . $_SERVER['PHP_SELF'] . "?isbn=" . $isbn . "&succ=1");
                exit;
            } catch (PDOException $e) {
                $messaggio_form = "<div class='alert alert-danger'>Errore durante il salvataggio: " . $e->getMessage() . "</div>";
            }
        }
    }
}

if (isset($_GET['succ'])) {
    $messaggio_form = "<div class='alert alert-success'>Recensione aggiunta con successo!</div>";
}
// --- FINE GESTIONE COMMENTO ---


try {
    // 1. Recupera info libro
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            (SELECT editore FROM copie c WHERE c.isbn = l.isbn LIMIT 1) as editore_temp,
            (SELECT COUNT(*) FROM copie c WHERE c.isbn = l.isbn AND c.disponibile = 1) as numero_copie_disponibili
        FROM libri l
        WHERE l.isbn = ?
    ");
    $stmt->execute([$isbn]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($libro) {
        $libro['editore'] = $libro['editore_temp'] ?? 'N/D';

        // 2. Recupera Autori
        $stmt = $pdo->prepare("
            SELECT a.nome, a.cognome
            FROM autori a
            JOIN autore_libro al ON al.id_autore = a.id_autore
            WHERE al.isbn = ?
        ");
        $stmt->execute([$isbn]);
        $autori = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Recupera Categorie
        $stmt = $pdo->prepare("
            SELECT categoria
            FROM categorie c
            JOIN libro_categoria lc ON lc.id_categoria = c.id_categoria
            WHERE lc.isbn = ?
        ");
        $stmt->execute([$isbn]);
        $categorie = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 4. Calcola Media Voti e Totale
        $stmt = $pdo->prepare("
            SELECT CAST(AVG(voto) AS DECIMAL(3,1)) as media, COUNT(*) as totale 
            FROM recensioni 
            WHERE isbn = ?
        ");
        $stmt->execute([$isbn]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $mediaVoto = $stats['media'] ? number_format((float)$stats['media'], 1) : 0;
        $totaleRecensioni = $stats['totale'];

        // 5. Recupera SOLO le ultime Recensioni (Ho aumentato a 3 per estetica)
        $stmt = $pdo->prepare("
            SELECT r.*, u.username
            FROM recensioni r
            JOIN utenti u ON r.codice_alfanumerico = u.codice_alfanumerico
            WHERE r.isbn = ?
            ORDER BY r.data_commento DESC
            LIMIT 3 
        ");
        $stmt->execute([$isbn]);
        $recensioni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $messaggio_db = "Libro non trovato nel database.";
    }

} catch (PDOException $e) {
    $messaggio_db = "Errore nel recupero dati: " . $e->getMessage();
}

function getCoverPath($isbn)
{
    $localPath = __DIR__ . "/../public/bookCover/$isbn.png";
    $publicPath = "public/bookCover/$isbn.png";
    if (file_exists($localPath)) {
        return $publicPath;
    }
    // Placeholder se l'immagine non esiste (assicurati di averne uno o togli questa riga)
    return "https://via.placeholder.com/200x300?text=Nessuna+Copertina"; 
}
?>

<?php require './src/includes/header.php'; ?>
<?php require './src/includes/navbar.php'; ?>

<div class="page_contents_container">

    <?php if ($messaggio_db || !$libro): ?>
        <div class="error-container box-shadow">
            <h1>Ops!</h1>
            <p><?= htmlspecialchars($messaggio_db ?: "Impossibile trovare il libro richiesto.") ?></p>
            <a href="index.php" class="btn-primary">Torna alla Home</a>
        </div>
    <?php else: ?>

        <div class="book-header-section box-shadow">
            <div class="book-cover-column">
                 <img id="book-cover-image" src="<?= getCoverPath($libro['isbn']) ?>" alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>" class="book_cover">
            </div>
            
            <div class="book-details-column">
                <h1 class="book-title"><?= htmlspecialchars($libro['titolo']) ?></h1>

                <p class="book-meta">
                    <strong>Autori:</strong>
                    <?= htmlspecialchars(implode(', ', array_map(fn($a) => $a['nome'] . ' ' . $a['cognome'], $autori))) ?>
                </p>

                <div class="meta-grid">
                    <p><strong>Editore:</strong> <?= htmlspecialchars($libro['editore']) ?></p>
                    <p><strong>Anno:</strong> <?= htmlspecialchars($libro['anno_pubblicazione'] ?? 'N/D') ?></p>
                </div>

                <div class="rating-availability-box">
                    <div class="rating-summary">
                         <?php if ($totaleRecensioni > 0): ?>
                            <span class="star-gold big-star">★</span>
                            <span class="rating-value"><?= $mediaVoto ?></span>
                            <span class="rating-count">/ 5.0 (<small><?= $totaleRecensioni ?> voti</small>)</span>
                        <?php else: ?>
                            <span class="no-rating">Nessuna valutazione</span>
                        <?php endif; ?>
                    </div>

                    <div class="availability-status">
                         <?php if ($libro['numero_copie_disponibili'] > 0): ?>
                            <span class="badge badge-available">Disponibile (<?= $libro['numero_copie_disponibili'] ?> copie)</span>
                        <?php else: ?>
                             <span class="badge badge-unavailable">Non disponibile al momento</span>
                        <?php endif; ?>
                    </div>
                </div>
                 <p class="book-categories">
                    <strong>Categorie:</strong>
                    <?php foreach($categorie as $cat): ?>
                        <span class="category-tag"><?= htmlspecialchars($cat) ?></span>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>

        <div class="book-description-section box-shadow">
            <h3>Descrizione</h3>
            <div class="description-text">
                <?= nl2br(htmlspecialchars($libro['descrizione'])) ?>
            </div>
        </div>

        <hr class="section-divider">

        <div class="reviews-section-container">
            <div class="reviews-header">
                <h2>Recensioni</h2>
            </div>

            <?= $messaggio_form ?>

            <?php 
            // VERIFICA SE L'UTENTE È LOGGATO PRIMA DI MOSTRARE IL FORM
            // Sostituisci 'codice_alfanumerico' con la tua variabile di sessione corretta
            if (isset($_SESSION['codice_alfanumerico'])): 
            ?>
            <div class="add-review-box box-shadow">
                <h3>Lascia la tua recensione</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="voto">Il tuo voto:</label>
                        <select name="voto" id="voto" required class="form-select">
                            <option value="" disabled selected>Seleziona stelle</option>
                            <option value="5">★★★★★ (5 - Eccellente)</option>
                            <option value="4">★★★★☆ (4 - Molto buono)</option>
                            <option value="3">★★★☆☆ (3 - Buono)</option>
                            <option value="2">★★☆☆☆ (2 - Discreto)</option>
                            <option value="1">★☆☆☆☆ (1 - Pessimo)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="commento">Il tuo commento:</label>
                        <textarea name="commento" id="commento" rows="4" required class="form-textarea" placeholder="Cosa ne pensi di questo libro?"></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn-submit">Pubblica Recensione</button>
                </form>
            </div>
            <?php else: ?>
                <div class="login-to-comment-box box-shadow">
                    <p>Vuoi lasciare una recensione? <a href="./login">Effettua il login</a> per condividere la tua opinione.</p>
                </div>
            <?php endif; ?>
            <?php if ($recensioni): ?>
                <div class="reviews-list">
                    <?php foreach ($recensioni as $r): ?>
                        <div class="review_card box-shadow">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <strong><?= htmlspecialchars($r['username']) ?></strong>
                                    <small class="review-date"><?= date('d/m/Y', strtotime($r['data_commento'])) ?></small>
                                </div>
                                <div class="review-stars">
                                    <?php for ($i = 0; $i < $r['voto']; $i++) echo "<span class='star-gold'>★</span>"; ?>
                                    <?php for ($i = $r['voto']; $i < 5; $i++) echo "<span class='star-gray'>★</span>"; ?>
                                </div>
                            </div>
                            <div class="review-body">
                                <p><em>"<?= nl2br(htmlspecialchars($r['commento'])) ?>"</em></p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totaleRecensioni > count($recensioni)): ?>
                        <div class="show-more-reviews">
                            <a href="#" class="btn-outline">Vedi tutte le <?= $totaleRecensioni ?> recensioni</a>
                        </div>
                    <?php endif; ?>

                </div>
            <?php else: ?>
                <p class="no-reviews-yet">Non ci sono ancora recensioni per questo libro. Sii il primo!</p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php require './src/includes/footer.php'; ?>
<style>
/* --- CSS Grafica Pagina Libro --- */

/* Variabili colori per coerenza */
:root {
    --primary-color: #0056b3; /* Un bel blu professionale */
    --secondary-color: #f8f9fa; /* Grigio chiaro per sfondi */
    --text-dark: #333;
    --text-muted: #6c757d;
    --gold-star: #ffc107;
    --gray-star: #e4e5e9;
    --success-green: #28a745;
    --danger-red: #dc3545;
    --border-radius: 8px;
    --box-shadow: 0 4px 8px rgba(0,0,0,0.08);
}

/* Contenitore principale */
.page_contents_container {
    max-width: 1100px;
    margin: 30px auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: var(--text-dark);
}

/* Utility Classes */
.box-shadow {
    background: #fff;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 25px;
}

.section-divider {
    border: 0;
    height: 1px;
    background: #eee;
    margin: 30px 0;
}

/* Layout Header Libro (Copertina + Dettagli) */
.book-header-section {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

.book-cover-column {
    flex: 0 0 250px; /* Larghezza fissa per la colonna copertina */
    display: flex;
    justify-content: center;
    align-items: start;
}

.book_cover {
    width: 100%;
    max-width: 220px;
    height: auto;
    border-radius: var(--border-radius);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    object-fit: cover;
}

.book-details-column {
    flex: 1; /* Prende il resto dello spazio */
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.book-title {
    margin-top: 0;
    font-size: 2.2rem;
    color: #222;
    line-height: 1.2;
}

.book-meta {
    font-size: 1.1rem;
}

.meta-grid {
    display: flex;
    gap: 20px;
    color: var(--text-muted);
}

/* Box Voto e Disponibilità */
.rating-availability-box {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    background: var(--secondary-color);
    padding: 15px;
    border-radius: var(--border-radius);
}

.rating-summary {
    display: flex;
    align-items: center;
    font-size: 1.2rem;
}

.big-star { font-size: 1.5rem; margin-right: 5px; }
.rating-value { font-weight: bold; margin-right: 5px; }
.rating-count { font-size: 0.9rem; color: var(--text-muted); }

.badge {
    padding: 8px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}
.badge-available { background: #d4edda; color: var(--success-green); }
.badge-unavailable { background: #f8d7da; color: var(--danger-red); }

/* Categorie tags */
.category-tag {
    display: inline-block;
    background: #e9ecef;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.85rem;
    margin-right: 5px;
    color: var(--primary-color);
}

/* Sezione Descrizione */
.book-description-section h3 {
    margin-top: 0;
    border-bottom: 2px solid var(--secondary-color);
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.description-text {
    line-height: 1.7;
    color: #444;
}

/* --- SEZIONE RECENSIONI E FORM --- */
.reviews-header h2 {
    margin-bottom: 25px;
}

/* Stili per i messaggi di alert (successo/errore) */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: var(--border-radius);
}
.alert-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
.alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.alert-warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }


/* Modulo Aggiungi Recensione */
.add-review-box h3 { margin-top: 0; }

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-select, .form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: var(--border-radius);
    font-family: inherit;
    font-size: 1rem;
    transition: border-color 0.3s;
    box-sizing: border-box; /* Importante per il padding */
}

.form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary-color);
}

.form-textarea {
    resize: vertical; /* Permette resize solo verticale */
}

.btn-submit {
    background-color: var(--primary-color);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: var(--border-radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-submit:hover {
    background-color: #004494;
}

.login-to-comment-box {
    background: #e9ecef;
    text-align: center;
}

/* Card delle Recensioni */
.review_card {
    border-left: 4px solid var(--primary-color);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.reviewer-info strong { font-size: 1.1rem; }
.review-date { display: block; font-size: 0.85rem; color: var(--text-muted); }

.star-gold { color: var(--gold-star); }
.star-gray { color: var(--gray-star); }

.review-body p {
    margin: 0;
    font-style: italic;
    color: #555;
    line-height: 1.6;
}

.no-reviews-yet {
    text-align: center;
    padding: 30px;
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .book-header-section {
        flex-direction: column;
        align-items: center;
    }
    .book-cover-column {
        flex: 0 0 auto;
        margin-bottom: 20px;
    }
    .book-details-column {
        text-align: center;
    }
    .meta-grid, .rating-availability-box {
        justify-content: center;
    }
    .review-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>