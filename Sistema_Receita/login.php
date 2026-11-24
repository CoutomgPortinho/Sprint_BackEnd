<?php
/*
 * =============================================
 * ARQUIVO: login.php
 * FUNÇÃO: Autenticar o usuário no sistema
 * =============================================
 *
 * Fluxo desta página:
 * 1. Se já estiver logado → vai para index.php
 * 2. Se enviou o formulário (POST) → valida login
 * 3. Se login ok → salva sessão e vai para index.php
 * 4. Se login falhou → mostra mensagem de erro
 */

session_start();

// Se já está logado, redireciona direto para o menu
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

include('conexao.php');

$mensagem      = '';
$tipo_mensagem = '';

// -----------------------------------------------
// PROCESSAMENTO DO FORMULÁRIO
// -----------------------------------------------
// $_SERVER['REQUEST_METHOD'] diz como a página foi chamada:
// 'GET'  = acesso normal  |  'POST' = formulário enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $usuario = $_POST['usuario'] ?? '';
    $senha   = $_POST['senha']   ?? '';

    if (empty($usuario) || empty($senha)) {
        $mensagem      = "Preencha todos os campos.";
        $tipo_mensagem = "error";
    } else {
        // Prepared statement: o '?' é substituído com segurança
        // evitando SQL Injection (ex: usuario = "' OR '1'='1")
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario); // "s" = tipo string
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Confere a senha digitada contra o hash bcrypt salvo no banco
            if (password_verify($senha, $user['senha'])) {
                // ✅ Login ok: salva dados na sessão
                $_SESSION['usuario']    = $usuario;
                $_SESSION['usuario_id'] = $user['id'];
                header('Location: index.php');
                exit();
            } else {
                $mensagem      = "Senha incorreta.";
                $tipo_mensagem = "error";
            }
        } else {
            $mensagem      = "Usuário não encontrado.";
            $tipo_mensagem = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Diário de Receitas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">

    <main class="login-split">

        <section class="login-panel">

            <a href="login.php" class="brand"><img src="assets/logo.png" alt="Diário de receitas"></a>

            <div class="login-content">

                <span class="eyebrow">Seu caderno de sabores</span>
                <h1>Seu diário de receitas</h1>
                <p class="login-sub">Entre e deixe suas melhores receitas sempre à mão.</p>

                <!-- Mensagem de erro ou feedback de logout -->
                <?php if ($mensagem): ?>
                    <div class="message <?php echo $tipo_mensagem; ?>">
                        <?php echo $tipo_mensagem === 'error' ? '⚠️' : '✅'; ?>
                        <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                    <div class="message success">✅ Você saiu do sistema.</div>
                <?php endif; ?>

                <!--
                    method="post" → dados vão no corpo da requisição (não aparecem na URL)
                    action=""     → envia para a própria página (login.php)
                -->
                <form method="post" action="">
                    <div class="form-group">
                        <label for="usuario">Usuário</label>
                        <!--
                            htmlspecialchars() converte < > & em entidades HTML
                            evitando XSS (Cross-Site Scripting). Use sempre ao exibir
                            dados vindos do usuário.
                        -->
                        <input type="text"
                            id="usuario"
                            name="usuario"
                            value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                            required
                            autocomplete="username"
                            placeholder="Digite seu usuário">
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <div class="password-field">
                            <input type="password"
                                id="senha"
                                name="senha"
                                required
                                autocomplete="current-password"
                                placeholder="Digite sua senha">
                            <button type="button" class="toggle-password" id="toggleSenha" aria-controls="senha">Mostrar</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Entrar</button>
                </form>

                <!-- Dica visível apenas em ambiente de desenvolvimento -->
                <div class="login-hint">
                    <strong>Credenciais de teste:</strong><br>
                    Usuário: <code>Miguelito3</code> &nbsp;·&nbsp; Senha: <code>BoloBomDemais</code>
                </div>

            </div>

            <footer class="login-footer">© <?php echo date('Y'); ?> Diário de receitas</footer>

        </section>

        <aside class="login-hero" aria-hidden="true">
            <span class="eyebrow">Da sua cozinha para a vida</span>
            <h2>O seu próximo prato começa aqui.</h2>
        </aside>

    </main>

    <script>
        // Alterna a visibilidade da senha
        document.getElementById('toggleSenha').addEventListener('click', function () {
            var campo = document.getElementById('senha');
            var mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            this.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        });
    </script>

</body>

</html>
