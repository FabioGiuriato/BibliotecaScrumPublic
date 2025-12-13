<?php

require_once 'db_config.php';

$stmt = $pdo->prepare("SELECT * FROM `libri` WHERE titolo LIKE :search");
$stmt->bindValue(':search', '%'.$_GET['search'].'%');
$stmt->execute();
$result = $stmt->fetchAll();


require './src/includes/header.php';
require './src/includes/navbar.php';

?>

<div class="page_contents">

    <form>
        <label> Cerca per Nome Autore: </label>
      <input type="checkbox" name="nome"/>
        <br>
        <label> Cerca per Cognome Autore: </label>
        <input type="checkbox" name="cognome"/>
    </form>
    <h1>Controlla Risultati</h1>

    <?php if (isset($_GET['titolo'])): ?>
    <?php foreach ($result as $book): ?>
        <p> va </p>
        <p> <?= $result ?> </p>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require './src/includes/footer.php'; ?>


