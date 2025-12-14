<?php
require_once 'db_config.php';

function highlight_text(?string $text, string $search): string {
    if ($text === null) return '';
    if ($search === '') return htmlspecialchars($text);
    $safe = htmlspecialchars($text);
    return preg_replace('/' . preg_quote($search, '/') . '/iu', '<mark>$0</mark>', $safe);
}

// Recupero della query di ricerca dalla navbar
$search_query = trim($_GET['search'] ?? '');

// Risultati inizializzati
$books = [];
$users = [];

if (!empty($search_query)) {
    // --- RICERCA LIBRI ---
    $sql_books = "
        SELECT
            l.*,
            c.copertina,
            c.editore,
            GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') AS autore_nome,
            GROUP_CONCAT(DISTINCT a.cognome SEPARATOR ', ') AS autore_cognome
        FROM libri l
        LEFT JOIN autore_libro al ON al.isbn = l.isbn
        LEFT JOIN autori a ON a.id_autore = al.id_autore
        LEFT JOIN copie c ON c.isbn = l.isbn
        GROUP BY l.isbn
        ORDER BY l.titolo ASC
    ";

    try {
        $stmt = $pdo->query($sql_books);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $books = [];
    }

    // --- RICERCA UTENTI ---
    $sql_users = "
        SELECT
            username,
            nome,
            cognome,
            email
        FROM utenti
        ORDER BY username ASC
    ";

    try {
        $stmt = $pdo->query($sql_users);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $users = [];
    }
}

require './src/includes/header.php';
require './src/includes/navbar.php';
?>

<div class="page_contents">
    <h2>🔎 Filtri di Ricerca</h2>

    <form id="filter_form">
        <h3>Libri / Autori</h3>
        <label><input type="checkbox" name="filtra_titolo" checked> Titolo libro</label><br>
        <label><input type="checkbox" name="filtra_autore_nome" checked> Nome autore</label><br>
        <label><input type="checkbox" name="filtra_autore_cognome" checked> Cognome autore</label><br>
        <label><input type="checkbox" name="filtra_editore" checked> Editore</label><br>
        <label><input type="checkbox" name="filtra_descrizione" checked> Descrizione libro</label><br>

        <h3>Utenti</h3>
        <label><input type="checkbox" name="filtra_username" checked> Username utente</label><br>
        <label><input type="checkbox" name="filtra_user_nome" checked> Nome utente</label><br>
        <label><input type="checkbox" name="filtra_user_cognome" checked> Cognome utente</label>
    </form>

    <hr>

    <h1>Risultati Libri</h1>
    <p>Trovati <strong id="results_count_books"><?= count($books) ?></strong> libri per <strong id="search_term_books"><?= htmlspecialchars($search_query) ?></strong></p>
    <div id="results_container_books">
        <?php foreach ($books as $book): ?>
            <div class="book_card"
                 data-titolo="<?= htmlspecialchars($book['titolo']) ?>"
                 data-autore_nome="<?= htmlspecialchars($book['autore_nome']) ?>"
                 data-autore_cognome="<?= htmlspecialchars($book['autore_cognome']) ?>"
                 data-editore="<?= htmlspecialchars($book['editore']) ?>"
                 data-descrizione="<?= htmlspecialchars($book['descrizione'] ?? '') ?>"
                 style="width:180px;border:1px solid #ccc;padding:10px;border-radius:5px;">
                <img src="<?= htmlspecialchars($book['copertina'] ?? 'src/assets/placeholder.jpg') ?>" style="width:100%">
                <h3 class="book_titolo"><?= highlight_text($book['titolo'], $search_query) ?></h3>
                <p class="book_autore_nome"><strong>Nome autore:</strong> <?= highlight_text($book['autore_nome'], $search_query) ?></p>
                <p class="book_autore_cognome"><strong>Cognome autore:</strong> <?= highlight_text($book['autore_cognome'], $search_query) ?></p>
                <p class="book_editore"><strong>Editore:</strong> <?= highlight_text($book['editore'], $search_query) ?></p>
                <p class="book_descrizione"><strong>Descrizione:</strong> <?= highlight_text(substr($book['descrizione'] ?? '', 0, 100), $search_query) ?>...</p>
                <a href="/info_libro?isbn=<?= htmlspecialchars($book['isbn']) ?>">Dettagli</a>
            </div>
        <?php endforeach; ?>
    </div>

    <hr>

    <h1>Risultati Utenti</h1>
    <p>Trovati <strong id="results_count_users"><?= count($users) ?></strong> utenti per <strong id="search_term_users"><?= htmlspecialchars($search_query) ?></strong></p>
    <div id="results_container_users">
        <?php foreach ($users as $user): ?>
            <div class="user_card"
                 data-username="<?= htmlspecialchars($user['username']) ?>"
                 data-user_nome="<?= htmlspecialchars($user['nome']) ?>"
                 data-user_cognome="<?= htmlspecialchars($user['cognome']) ?>"
                 style="width:180px;border:1px solid #ccc;padding:10px;border-radius:5px;">
                <p class="user_username"><strong>Username:</strong> <?= highlight_text($user['username'], $search_query) ?></p>
                <p class="user_user_nome"><strong>Nome:</strong> <?= highlight_text($user['nome'], $search_query) ?></p>
                <p class="user_user_cognome"><strong>Cognome:</strong> <?= highlight_text($user['cognome'], $search_query) ?></p>
                <p class="user_email"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const checkboxes = document.querySelectorAll('#filter_form input[type=checkbox]');
    const searchQuery = "<?= strtolower(htmlspecialchars($search_query)) ?>";

    function highlightText(text, search) {
        if (!search) return text;
        const regex = new RegExp(`(${search})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    function filterResults() {
        const activeFilters = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.name.replace('filtra_', ''));

        // Filtra libri
        let visibleCountBooks = 0;
        document.querySelectorAll('.book_card').forEach(card => {
            const show = activeFilters.some(field => (card.dataset[field] || '').toLowerCase().includes(searchQuery));
            card.style.display = show ? 'block' : 'none';
            if(show) visibleCountBooks++;
            if(show) {
                card.querySelectorAll('h3, p').forEach(el => {
                    const field = el.className.replace('book_', '');
                    if(activeFilters.includes(field)) el.innerHTML = highlightText(card.dataset[field], searchQuery);
                });
            }
        });
        document.getElementById('results_count_books').textContent = visibleCountBooks;

        // Filtra utenti
        let visibleCountUsers = 0;
        document.querySelectorAll('.user_card').forEach(card => {
            const show = activeFilters.some(field => (card.dataset[field] || '').toLowerCase().includes(searchQuery));
            card.style.display = show ? 'block' : 'none';
            if(show) visibleCountUsers++;
            if(show) {
                card.querySelectorAll('p').forEach(el => {
                    const field = el.className.replace('user_', '');
                    if(activeFilters.includes(field)) el.innerHTML = highlightText(card.dataset[field], searchQuery);
                });
            }
        });
        document.getElementById('results_count_users').textContent = visibleCountUsers;
    }

    // onchange sui checkbox
    checkboxes.forEach(cb => cb.addEventListener('change', filterResults));

    // Applica filtro iniziale
    filterResults();
</script>

<?php require './src/includes/footer.php'; ?>
