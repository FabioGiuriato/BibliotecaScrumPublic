<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOGICA MESSAGGI CENTRALIZZATA ---
$display_status = null;

if (isset($_SESSION['status'])) {
    $display_status = $_SESSION['status'];
    unset($_SESSION['status']);
}

if (isset($status) && !empty($status)) {
    $display_status = $status;
}

$nome_visualizzato = 'Utente';  // username da database

if (isset($_SESSION['nome_utente'])) {
    $nome_visualizzato = $_SESSION['nome_utente'];
}
?>

<style>
    nav {
        background: #333;
        padding: 10px;
        margin-bottom: 20px;
        color: white;
    }

    nav a {
        color: white;
        text-decoration: none;
        margin-right: 15px;
        font-family: sans-serif;
    }

    nav a:hover {
        text-decoration: underline;
    }

    .msg-box {
        display: inline;
        padding: 10px;
        margin: 15px;
        border-radius: 5px;
        font-family: sans-serif;
        font-weight: bold;
        height: 80%;
    }

    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<nav>
    <a href="./">Home</a>

    <?php if ($display_status): ?>
        <div class="msg-box <?php echo $display_status; ?>">
            <?php
            if ($display_status === 'success') {
                echo "Logout riuscito";
            } elseif ($display_status === 'error') {
                echo "Login non riuscito";
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['logged']) && $_SESSION['logged'] === true): ?>
        <a href="./logout" style="color: #ff9999; float: right;">Logout</a>
        <span style="float: right; margin-right: 10px;">Ciao, <?php echo htmlspecialchars($nome_visualizzato); ?></span>
    <?php else: ?>
        <a href="./login" style="float: right;">Login</a>
    <?php endif; ?>
    <div style="clear: both;"></div>
</nav>