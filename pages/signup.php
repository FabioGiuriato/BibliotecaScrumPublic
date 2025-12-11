<?php
session_start();
require_once "./src/includes/codiceFiscaleMethods.php";
require_once 'db_config.php';

// Redirect se già loggato
if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
    header("Location: /");
    exit();
}

$registratiConCodice = isset($_POST['conCodiceFiscale']) && $_POST['conCodiceFiscale'] == "true";
$tipologia = $registratiConCodice ? " con Codice Fiscale" : "";
$error_msg = '';

// LOGICA DI SIGNUP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try{
        if(isset($pdo)) {
            $email = $_POST['email'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $cognome = $_POST['cognome'] ?? '';
            $daCodiceFiscale = isset($_POST['daCodiceFiscale']) ? boolval($_POST['daCodiceFiscale']) : false;
            $follow_along = false;

            if ($nome != '' && $cognome != '' && $email != '' && $username != '' && $password != '') {
                if (!$daCodiceFiscale) {
                    $data_nascita = $_POST['data_nascita'] ?? '';
                    $comune_nascita = $_POST['comune_nascita'] ?? '';
                    $sesso = $_POST['sesso'] ?? '';
                    if ($data_nascita == '' || $comune_nascita == '') {
                        $error_msg = "Dati inseriti non validi";
                    }
                    $codice_fiscale = generateCodiceFiscale($nome, $cognome, $data_nascita, $comune_nascita, $sesso);
                } else {
                    $codice_fiscale = $_POST['codice_fiscale'] ?? '';
                    if (empty($datiDaCodice)) {
                        $error_msg = "Codice Fiscale non valido";
                    }
                }
                $follow_along = true;
            }
            // Inserimento Utente
            if ($error_msg == '' && $follow_along) {
                $insert_string = "CALL sp_crea_utente_alfanumerico(:username, :nome, :cognome, :codice_fiscale, :email, :password)";
                $stmt = $pdo->prepare($insert_string);
                $password_hash = hash("sha256", $password);
                $stmt->bindParam(":nome", $nome);
                $stmt->bindParam(":cognome", $cognome);
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":codice_fiscale", $codice_fiscale);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $password_hash);
                $resu = $stmt->execute();
                if ($resu) {
                    header("Location: /login");
                    exit();
                } else {
                    $status = "Errore nell'inserimento dell'utente";
                }
            }
        }
        else {
            $error_msg = "Errore di connessione al Database.";
        }
    } catch (PDOException $e) {
        $error_msg = "Errore di sistema: " . $e->getMessage();
    }
}
$title = "Registrati";
$page_css = "./public/css/style_forms.css";
?>
<?php include './src/includes/header.php'; ?>

<div class="form_container_2">

    <h2 class="form_title_2">Registrati</h2>

    <?php if (!empty($error_msg)): ?>
        <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <form method="post">

        <label class="form_2_label" for="username">Username:</label>
        <input class="form_2_input_string" placeholder="Username" required type="text" id="username" name="username">

        <div class="form_row"> <div>
                <label class="form_2_label" for="nome">Nome:</label>
                <input class="form_2_input_string" placeholder="Nome" required type="text" id="nome" name="nome">
            </div>
            <div>
                <label class="form_2_label" for="cognome">Cognome:</label>
                <input class="form_2_input_string" placeholder="Cognome" required type="text" id="cognome" name="cognome">
            </div>
        </div>

        <hr class="form_separator"> <?php if ($registratiConCodice) { ?>
            <label class="form_2_label" for="codice_fiscale">Codice Fiscale:</label>
            <input class="form_2_input_string" placeholder="Codice Fiscale" required type="text" id="codice_fiscale" name="codice_fiscale">
        <?php } else { ?>

            <div class="form_row"> <div>
                    <label class="form_2_label" for="comune_nascita">Comune di Nascita:</label>
                    <input class="form_2_input_string" placeholder="Comune di Nascita" required type="text" id="comune_nascita" name="comune_nascita"> </div>
                <div>
                    <label class="form_2_label" for="data_nascita">Data di Nascita:</label>
                    <input class="form_2_input_date" placeholder="Data di Nascita" required type="date" id="data_nascita" name="data_nascita"> </div>
            </div>

            <label class="form_2_label" for="sesso">Sesso:</label>
            <select class="form_2_select" required name="sesso" id="sesso"> <option value="">--Sesso--</option>
                <optgroup label="Preferenze">
                    <option value="M">Maschio</option>
                    <option value="F">Femmina</option>
                </optgroup>
            </select>

        <?php } ?>

        <hr class="form_separator"> <label class="form_2_label" for="email">Email:</label>
        <input  class="form_2_input_string" placeholder="Email" required type="email" id="email" name="email"> <label class="form_2_label" for="password">Password:</label>
        <input  class="form_2_input_string" required type="password" id="password" name="password"> <input class="form_2_btn_submit" type="submit" value="Registrami"> </form>

    <?php if ($registratiConCodice) { ?>
        <a href="#" onclick='redirectConCodice(false)'>Non hai il codice fiscale?</a>
    <?php } else { ?>
        <a href="#" onclick='redirectConCodice(true)'>Hai il codice fiscale?</a>
    <?php } ?>
    <a href="./login">Hai già un account? Accedi</a>

</div>

</div>

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