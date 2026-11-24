<?php
/*
 * =============================================
 * ARQUIVO: index.php
 * FUNÇÃO: Menu principal do sistema
 * =============================================
 */
include('valida_sessao.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Diário de Receitas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">

    <div class="topbar">
        <a href="index.php" class="brand" title="Ir para o menu"><img src="assets/logo.png" alt="Diário de receitas"></a>
        <a href="logout.php" class="btn-back">Sair</a>
    </div>

    <div class="page-header">
        <span class="eyebrow">Seu caderno de sabores</span>
        <h1>Seu diário de receitas</h1>
        <p>Olá, <strong><?php echo htmlspecialchars($_SESSION['usuario']); ?></strong>! O que há na cozinha hoje?</p>
    </div>

    <div class="menu-grid">

        <div class="menu-card">
            <a href="cadastro_chef.php">
                <div class="menu-card-title">Gerenciar chefs</div>
                <div class="menu-card-desc">Cadastre e gerencie chefs de cozinha</div>
            </a>
        </div>

        <div class="menu-card">
            <a href="cadastro_receita.php">
                <div class="menu-card-title">Cadastrar receitas</div>
                <div class="menu-card-desc">Cadastre e gerencie receitas</div>
            </a>
        </div>

        <div class="menu-card">
            <a href="listagem_receitas.php">
                <div class="menu-card-title">Minhas receitas</div>
                <div class="menu-card-desc">Visualize suas receitas</div>
            </a>
        </div>

    </div>

</div>
</body>
</html>