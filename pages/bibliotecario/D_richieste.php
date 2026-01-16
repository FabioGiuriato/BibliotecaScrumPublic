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

// GESTIONE AZIONI (POST)
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
    .page_contents { 
        background-color: #fcfcfc; 
        min-height: 90vh; 
        padding-top: 30px; 
        font-family: 'Instrument Sans', sans-serif;
    }

    .dashboard-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: none;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .page-header {
        margin-bottom: 25px;
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
        vertical-align: middle;
    }
    .table-custom td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f2f5;
        color: #444;
    }
    .table-custom tr:last-child td { border-bottom: none; }

    .user-avatar-placeholder {
        width: 38px;
        height: 38px;
        background-color: #e9ecef;
        color: #495057;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 12px;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .status-approved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* Bottoni con testo */
    .btn-accetta {
        background-color: #198754; 
        color: white; 
        border: none; 
        padding: 8px 15px; 
        border-radius: 8px; 
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .btn-accetta:hover { background-color: #157347; transform: translateY(-1px); color: white; }
    
    .btn-rifiuta {
        background-color: white; 
        color: #dc3545; 
        border: 1px solid #dc3545; 
        padding: 8px 15px; 
        border-radius: 8px; 
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .btn-rifiuta:hover { background-color: #dc3545; color: white; transform: translateY(-1px); }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #adb5bd;
    }
</style>

<div class="page_contents">
    <div class="container-fluid" style="max-width: 1400px;">
        
        <div class="page-header">
            <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-inbox-fill text-primary me-2"></i>Gestione Richieste</h2>
            <p class="text-muted mb-0">Visualizza e gestisci le richieste di rinnovo degli utenti.</p>
        </div>
        
        <?php if ($messaggio): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($messaggio) ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Utente</th>
                            <th scope="col">Libro & Prestito</th>
                            <th scope="col">Tempistiche</th>
                            <th scope="col">Stato</th>
                            <th scope="col">Affidabilità</th>
                            <th scope="col" class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($richieste)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-check fs-1"></i>
                                        <h5>Nessuna richiesta</h5>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($richieste as $req): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border">#<?= $req['id_richiesta'] ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-placeholder"><?= strtoupper(substr($req['nome'], 0, 1)) ?></div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($req['cognome'] . ' ' . $req['nome']) ?></div>
                                                <small class="text-muted"><?= $req['codice_alfanumerico'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($req['titolo'] ) ?></div>
                                        <small class="text-muted">Prestito #<?= $req['id_prestito'] ?></small>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <i class="bi bi-calendar-event me-1"></i> Rich: <?= date('d/m/y', strtotime($req['data_richiesta'])) ?><br>
                                            <span class="text-danger fw-bold"><i class="bi bi-hourglass-split"></i> Scade: <?= date('d/m/y', strtotime($req['scadenza_attuale_prestito'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($req['stato'] == 'approvata'): ?>
                                            <span class="status-badge status-approved">Approvata</span>
                                        <?php elseif($req['stato'] == 'rifiutata'): ?>
                                            <span class="status-badge status-rejected">Rifiutata</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">In Attesa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($req['numero_multe'] > 0): ?>
                                            <span class="text-danger fw-bold"><i class="bi bi-warning"></i> <?= $req['numero_multe'] ?> Multe</span>
                                        <?php else: ?>
                                            <span class="text-success small fw-bold">Regolare</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-end">
                                            <?php if ($req['stato'] === 'in_attesa'): ?>
                                                <form method="post" class="m-0 d-flex gap-2">
                                                    <input type="hidden" name="id_richiesta" value="<?= $req['id_richiesta'] ?>">
                                                    <button type="submit" name="action" value="approva" class="btn-accetta">
                                                        <i class="bi bi-check-lg"></i> Accetta
                                                    </button>
                                                    <button type="submit" name="action" value="rifiuta" class="btn-rifiuta" onclick="return confirm('Rifiutare?');">
                                                        <i class="bi bi-x-lg"></i> Rifiuta
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">--</span>
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

<?php require_once './src/includes/footer.php'; ?>