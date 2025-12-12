<?php
session_start();
require_once 'db_config.php'; 

// 1. INCLUDO IL TUO FILE CON LA LOGICA DEL CODICE FISCALE
// Assicurati che il percorso sia giusto rispetto a dove si trova signup.php
require_once __DIR__ . '/../src/includes/codiceFiscaleMethods.php'; 

$error_msg = "";
$success_msg = "";

// Funzione per generare ID univoco (per la tabella utenti)
function genID($l=6) { return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"),0,$l); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. PRENDO I DATI DAL TUO FORM HTML
    // Verifica che i 'name' nel tuo HTML siano questi:
    $nome        = $_POST['nome'] ?? '';
    $cognome     = $_POST['cognome'] ?? '';
    $dataNascita = $_POST['data_nascita'] ?? ''; // Deve essere formato YYYY-MM-DD
    $sesso       = $_POST['sesso'] ?? '';        // M o F
    $codiceComune= $_POST['codice_comune'] ?? ''; // Es. H501
    
    $username    = $_POST['username'] ?? '';
    $email       = $_POST['email'] ?? '';
    $password    = $_POST['password'] ?? '';

    if (isset($pdo)) {
        try {
            // 3. USO LA TUA FUNZIONE PER CALCOLARE IL CF
            // Richiama la funzione definita in codiceFiscaleMethods.php
            $cf_generato = generateCodiceFiscale($nome, $cognome, $dataNascita, $sesso, $codiceComune);

            // 4. PREPARO I DATI PER IL LOGIN (SHA256) E L'ID
            $id_utente = genID();
            $pass_hash = hash('sha256', $password); // Compatibile col tuo Login

            // 5. INSERIMENTO NEL DATABASE
            $sql = "INSERT INTO utenti 
                    (codice_alfanumerico, username, nome, cognome, email, codice_fiscale, password_hash, email_confermata, data_creazione) 
                    VALUES (:id, :user, :nome, :cogn, :email, :cf, :pass, 0, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'    => $id_utente,
                ':user'  => $username,
                ':nome'  => $nome,
                ':cogn'  => $cognome,
                ':email' => $email,
                ':cf'    => $cf_generato, // Inseriamo quello calcolato dalla tua funzione
                ':pass'  => $pass_hash
            ]);

            $success_msg = "Registrato con successo! CF: " . $cf_generato;
            // header("Location: /login"); exit; // Scommenta per redirect

        } catch (PDOException $e) {
            $error_msg = "Errore Database: " . $e->getMessage();
        } catch (TypeError $e) {
            $error_msg = "Errore nei dati per il calcolo CF: " . $e->getMessage();
        }
    } else {
        $error_msg = "Errore di connessione al database.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione</title>
</head>

<body>

    <?php require_once './src/includes/header.php'; ?>
    <?php require_once './src/includes/navbar.php'; ?>

    <div class="container">

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <h2>Registrati<?php echo $tipologia ?></h2>
        <form method="post">

            <label for="username">Username:</label>
            <input placeholder="Username" required type="text" id="username" name="username">

            <label for="nome">Nome:</label>
            <input placeholder="Nome" required type="text" id="nome" name="nome">

            <label for="cognome">Cognome:</label>
            <input placeholder="Cognome" required type="text" id="cognome" name="cognome">

            <?php if ($registratiConCodice) { ?>
                <label for="codice_fiscale">Codice Fiscale:</label>
                <input placeholder="Codice Fiscale" required type="text" id="codice_fiscale" name="codice_fiscale">
            <?php } else { ?>
                <label for="comune_nascita">Comune di Nascita:</label>
                <input placeholder="Comune di Nascita" required type="text" id="comune_nascita" name="comune_nascita">
                <label for="data_nascita">Data di Nascita:</label>
                <input placeholder="Data di Nascita" required type="date" id="data_nascita" name="data_nascita">
                <label for="sesso">Sesso:</label>
                <select required name="sesso" id="sesso">
                    <option value="">--Sesso--</option>
                    <optgroup label="Preferenze">
                        <option value="M">Maschio</option>
                        <option value="F">Femmina</option>
                    </optgroup>
                </select>

            <?php } ?>
            <label for="email">Email:</label>
            <input placeholder="Email" required type="email" id="email" name="email">
            <label for="password">Password:</label>
            <input required type="password" id="password" name="password">
            <input placeholder="Password" type="submit" value="Registrami">
        </form>
        <?php if ($registratiConCodice) { ?>
            <a href="#" onclick='redirectConCodice(false)'>Non hai il codice fiscale?</a>
        <?php } else { ?>
            <a href="#" onclick='redirectConCodice(true)'>Hai il codice fiscale?</a>
        <?php } ?>

    </div>

    <?php require_once "./src/includes/footer.php" ?>

    <script>
        const redirectConCodice = (conCodice) => {
            const virtual_form = document.createElement("form");
            virtual_form.style.display = "none"
            virtual_form.method = "POST";
            virtual_form.action = "./signup"
            const decision = document.createElement("input");
            decision.name = "conCodiceFiscale";
            decision.type = "hidden";
            decision.value = conCodice;
            virtual_form.appendChild(decision)
            document.body.appendChild(virtual_form);
            virtual_form.submit();
        }
    </script>
</body>

</html>