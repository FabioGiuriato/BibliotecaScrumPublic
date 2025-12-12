<?php
// pages/signup.php
session_start();
require_once 'db_config.php'; // Assumo che qui dentro ci sia la connessione $pdo

// Funzione per generare l'ID di 6 caratteri (la tua Primary Key)
function generaCodiceAlfanumerico($lunghezza = 6) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $lunghezza);
}

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero dati input
    $nome     = trim($_POST['nome'] ?? '');
    $cognome  = trim($_POST['cognome'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cf       = strtoupper(trim($_POST['codice_fiscale'] ?? ''));

    // Controlli base
    if (empty($nome) || empty($cognome) || empty($username) || empty($email) || empty($password) || empty($cf)) {
        $error_msg = "Compila tutti i campi.";
    } elseif (strlen($cf) !== 16) {
        $error_msg = "Il codice fiscale deve essere di 16 caratteri.";
    } else {
        if (isset($pdo)) {
            try {
                // 1. Genera ID casuale (codice_alfanumerico)
                $new_id = generaCodiceAlfanumerico(6);

                // 2. Hash della password identico al tuo login (SHA256)
                // IMPORTANTE: Se usassi password_hash() qui, il tuo login fallirebbe.
                $password_hash = hash('sha256', $password);

                // 3. Query per la TUA tabella 'utenti'
                // Inseriamo i dati e settiamo i default (email non confermata, account non bloccato)
                $sql = "INSERT INTO utenti (
                            codice_alfanumerico, 
                            username, 
                            nome, 
                            cognome, 
                            codice_fiscale, 
                            email, 
                            password_hash, 
                            email_confermata, 
                            account_bloccato,
                            data_creazione
                        ) VALUES (
                            :id, :user, :nome, :cognome, :cf, :email, :pass, 0, 0, NOW()
                        )";

                $stmt = $pdo->prepare($sql);
                
                $stmt->execute([
                    ':id'       => $new_id,
                    ':user'     => $username,
                    ':nome'     => $nome,
                    ':cognome'  => $cognome,
                    ':cf'       => $cf,
                    ':email'    => $email,
                    ':pass'     => $password_hash
                ]);

                $success_msg = "Registrazione completata! <a href='/login'>Vai al Login</a>";

            } catch (PDOException $e) {
                // Gestione errori (es. utente già esistente)
                if ($e->getCode() == 23000) {
                    $error_msg = "Username, Email o Codice Fiscale già presenti.";
                } else {
                    $error_msg = "Errore Database: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "Errore di connessione ($pdo non trovato).";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione</title>
    <style>
        .container { padding: 20px; max-width: 400px; margin: auto; }
        input { display: block; width: 100%; margin-bottom: 10px; padding: 8px; }
        button { padding: 10px 20px; cursor: pointer; }
        .error { color: red; } .success { color: green; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Registrazione Utente</h2>
        
        <?php if ($error_msg): ?>
            <p class="error"><?= htmlspecialchars($error_msg) ?></p>
        <?php endif; ?>
        
        <?php if ($success_msg): ?>
            <p class="success"><?= $success_msg ?></p>
        <?php else: ?>

        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Nome</label>
            <input type="text" name="nome" required>

            <label>Cognome</label>
            <input type="text" name="cognome" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Codice Fiscale</label>
            <input type="text" name="codice_fiscale" maxlength="16" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Registrati</button>
        </form>
        <?php endif; ?>
        
        <br>
        <a href="/login">Torna al Login</a>
    </div>

</body>
</html>