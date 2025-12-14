<?php
require_once 'db_config.php';

function highlight_text(?string $text, string $search): string {
    if ($text === null) return '';
    if ($search === '') return htmlspecialchars($text);
    $safe = htmlspecialchars($text);
    return preg_replace('/' . preg_quote($search, '/') . '/iu', '<mark>$0</mark>', $safe);
}

$search_query = trim($_GET['search'] ?? '');

$books = [];
$users = [];
$authors = [];

if (!empty($search_query)) {
    // --- Libri ---
    $sql_books = "
        SELECT l.isbn, l.titolo, c.copertina, 
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
        $all_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_books as $row) {
            // Libri solo se il titolo contiene il termine di ricerca
            if (stripos($row['titolo'], $search_query) !== false) {
                $books[$row['isbn']] = $row;
            }
            // Autori solo se nome o cognome contengono il termine
            if (!empty($row['autore_nome']) && !empty($row['autore_cognome']) &&
                    (stripos($row['autore_nome'], $search_query) !== false || stripos($row['autore_cognome'], $search_query) !== false)) {
                $authors[$row['isbn']] = $row;
            }
        }
    } catch (PDOException $e) {
        $books = [];
        $authors = [];
    }

    // --- Utenti ---
    $sql_users = "SELECT username, nome, cognome, email FROM utenti ORDER BY username ASC";
    try {
        $stmt = $pdo->query($sql_users);
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_users as $user) {
            if (stripos($user['username'], $search_query) !== false ||
                    stripos($user['nome'], $search_query) !== false ||
                    stripos($user['cognome'], $search_query) !== false) {
                $users[] = $user;
            }
        }
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
        <h3>Libri</h3>
        <label><input type="checkbox" name="filtra_titolo" checked> Titolo libro</label><br>
        <label><input type="checkbox" name="filtra_autore_nome" checked> Nome autore</label><br>
        <label><input type="checkbox" name="filtra_autore_cognome" checked> Cognome autore</label><br>

        <h3>Utenti</h3>
        <label><input type="checkbox" name="filtra_username" checked> Username utente</label><br>
        <label><input type="checkbox" name="filtra_user_nome" checked> Nome utente</label><br>
        <label><input type="checkbox" name="filtra_user_cognome" checked> Cognome utente</label>
    </form>

    <hr>

    <!-- LIBRI -->
    <h1>Risultati Libri</h1>
    <p>Trovati <strong id="results_count_books"><?= count($books) ?></strong> libri per <strong><?= htmlspecialchars($search_query) ?></strong></p>
    <div id="results_container_books">
        <?php foreach ($books as $isbn => $book): ?>
            <div class="book_card"
                 data-titolo="<?= htmlspecialchars($book['titolo']) ?>"
                 data-autore_nome="<?= htmlspecialchars($book['autore_nome']) ?>"
                 data-autore_cognome="<?= htmlspecialchars($book['autore_cognome']) ?>"
                 style="margin-bottom:10px; display:flex; align-items:center;">
                <img src="<?= htmlspecialchars($book['copertina'] ?? 'src/assets/placeholder.jpg') ?>" alt="Copertina" style="width:50px;height:70px;margin-right:10px;">
                <div>
                    <h3 class="book_titolo"><?= highlight_text($book['titolo'], $search_query) ?></h3>
                    <p class="book_autore_nome"><strong>Nome autore:</strong> <?= highlight_text($book['autore_nome'], $search_query) ?></p>
                    <p class="book_autore_cognome"><strong>Cognome autore:</strong> <?= highlight_text($book['autore_cognome'], $search_query) ?></p>
                    <a href="/libro_info?isbn=<?= urlencode($isbn) ?>">Dettagli</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <hr>

    <!-- AUTORI -->
    <h1>Risultati Autori</h1>
    <p>Trovati <strong id="results_count_authors"><?= count($authors) ?></strong> autori per <strong><?= htmlspecialchars($search_query) ?></strong></p>
    <div id="results_container_authors">
        <?php foreach ($authors as $isbn => $book): ?>
            <div class="author_card"
                 data-autore_nome="<?= htmlspecialchars($book['autore_nome']) ?>"
                 data-autore_cognome="<?= htmlspecialchars($book['autore_cognome']) ?>"
                 style="margin-bottom:10px;">
                <p class="author_nome"><strong>Nome autore:</strong> <?= highlight_text($book['autore_nome'], $search_query) ?></p>
                <p class="author_cognome"><strong>Cognome autore:</strong> <?= highlight_text($book['autore_cognome'], $search_query) ?></p>
                <p><strong>Libro:</strong> <?= htmlspecialchars($book['titolo']) ?></p>
                <img src="<?= htmlspecialchars($book['copertina'] ?? 'src/assets/placeholder.jpg') ?>" alt="Copertina" style="width:50px;height:70px;">
                <p><a href="/libro_info?isbn=<?= urlencode($isbn) ?>">Dettagli libro</a></p>
            </div>
        <?php endforeach; ?>
    </div>

    <hr>

    <!-- UTENTI -->
    <h1>Risultati Utenti</h1>
    <p>Trovati <strong id="results_count_users"><?= count($users) ?></strong> utenti per <strong><?= htmlspecialchars($search_query) ?></strong></p>
    <div id="results_container_users">
        <?php foreach ($users as $user): ?>
            <div class="user_card"
                 data-username="<?= htmlspecialchars($user['username']) ?>"
                 data-user_nome="<?= htmlspecialchars($user['nome']) ?>"
                 data-user_cognome="<?= htmlspecialchars($user['cognome']) ?>"
                 style="margin-bottom:10px;">
                <p class="user_username"><strong>Username:</strong> <?= highlight_text($user['username'], $search_query) ?></p>
                <p class="user_user_nome"><strong>Nome:</strong> <?= highlight_text($user['nome'], $search_query) ?></p>
                <p class="user_user_cognome"><strong>Cognome:</strong> <?= highlight_text($user['cognome'], $search_query) ?></p>
                <p class="user_email"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><a href="/utente_info?username=<?= urlencode($user['username']) ?>">Profilo</a></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const checkboxes = document.querySelectorAll('#filter_form input[type=checkbox]');
    const searchQuery = '<?= addslashes($search_query) ?>'.toLowerCase();

    function highlightText(text, search) {
        if (!search) return text;
        const regex = new RegExp(`(${search})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    function filterResults() {
        const activeFilters = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.name.replace('filtra_', ''));

        // --- libri ---
        let visibleCountBooks = 0;
        document.querySelectorAll('.book_card').forEach(card => {
            let show = activeFilters.some(field => (card.dataset[field] || '').toLowerCase().includes(searchQuery));
            card.style.display = show ? 'flex' : 'none';
            if(show) visibleCountBooks++;
            if(show) card.querySelectorAll('h3, p').forEach(el => {
                const field = el.className.replace('book_', '');
                if(activeFilters.includes(field)) el.innerHTML = highlightText(card.dataset[field], searchQuery);
            });
        });
        document.getElementById('results_count_books').textContent = visibleCountBooks;

        // --- autori ---
        let visibleCountAuthors = 0;
        document.querySelectorAll('.author_card').forEach(card => {
            let show = activeFilters.some(field => (card.dataset[field] || '').toLowerCase().includes(searchQuery));
            card.style.display = show ? 'block' : 'none';
            if(show) visibleCountAuthors++;
            if(show) card.querySelectorAll('p').forEach(el => {
                const field = el.className.replace('author_', '');
                if(activeFilters.includes(field)) el.innerHTML = highlightText(card.dataset[field], searchQuery);
            });
        });
        document.getElementById('results_count_authors').textContent = visibleCountAuthors;

        // --- utenti ---
        let visibleCountUsers = 0;
        document.querySelectorAll('.user_card').forEach(card => {
            let show = activeFilters.some(field => (card.dataset[field] || '').toLowerCase().includes(searchQuery));
            card.style.display = show ? 'block' : 'none';
            if(show) visibleCountUsers++;
            if(show) card.querySelectorAll('p').forEach(el => {
                const field = el.className.replace('user_', '');
                if(activeFilters.includes(field)) el.innerHTML = highlightText(card.dataset[field], searchQuery);
            });
        });
        document.getElementById('results_count_users').textContent = visibleCountUsers;
    }

    checkboxes.forEach(cb => cb.addEventListener('change', filterResults));
    filterResults();
</script>

<?php require './src/includes/footer.php'; ?>
