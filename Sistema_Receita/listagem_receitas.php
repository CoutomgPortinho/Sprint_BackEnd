
<?php
/*
 * =============================================
 * ARQUIVO: listagem_receitas.php
 * FUNÇÃO: Listar, pesquisar, filtrar e excluir receitas
 * =============================================
 */

// Exibição temporária de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include('valida_sessao.php');
include('conexao.php');

// Inicialização das variáveis
$mensagem = null;
$tipo_msg = null;

// -----------------------------------------------
// MONTAGEM DOS FILTROS DINÂMICOS
// -----------------------------------------------

$where  = "1=1";
$params = [];
$types  = "";

// Filtro por chef
if (!empty($_GET['chef'])) {
    $where   .= " AND c.id = ?";
    $params[] = intval($_GET['chef']);
    $types   .= "i";
}

// Filtro por categoria
if (!empty($_GET['categoria'])) {
    $where   .= " AND r.categoria LIKE ?";
    $params[] = "%" . $_GET['categoria'] . "%";
    $types   .= "s";
}

// Busca por nome da receita ou chef
if (!empty($_GET['busca'])) {
    $where .= " AND (r.nome LIKE ? OR c.nome LIKE ?)";

    $busca = "%" . $_GET['busca'] . "%";

    $params[] = $busca;
    $params[] = $busca;

    $types .= "ss";
}

// -----------------------------------------------
// CARREGA OS FILTROS
// -----------------------------------------------

$chefs = $conn->query(
    "SELECT id, nome FROM chefs ORDER BY nome"
);

$categorias = $conn->query(
    "SELECT DISTINCT categoria
     FROM receitas
     WHERE categoria IS NOT NULL
     AND categoria != ''
     ORDER BY categoria"
);

// -----------------------------------------------
// EXCLUSÃO DE RECEITA
// -----------------------------------------------

if (isset($_GET['delete_id'])) {

    $id = intval($_GET['delete_id']);

    // Busca a imagem antes de excluir a receita
    $stmt = $conn->prepare(
        "SELECT imagem_capa FROM receitas WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $arq = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Remove o arquivo da imagem, caso exista
    if (
        $arq &&
        !empty($arq['imagem_capa']) &&
        file_exists($arq['imagem_capa'])
    ) {
        unlink($arq['imagem_capa']);
    }

    // Exclui a receita do banco
    $stmt = $conn->prepare(
        "DELETE FROM receitas WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $tipo_msg = "success";
        $mensagem = "Receita excluída com sucesso!";
    } else {
        $tipo_msg = "error";
        $mensagem = "Erro ao excluir receita: " . $stmt->error;
    }

    $stmt->close();
}

// -----------------------------------------------
// CONSULTA PRINCIPAL COM FILTROS
// -----------------------------------------------

// JOIN une receitas e chefs pelo ID do chef
$sql = "SELECT r.*, c.nome AS chef_nome
        FROM receitas r
        INNER JOIN chefs c ON r.chefs_id = c.id
        WHERE $where
        ORDER BY r.nome";

// -----------------------------------------------
// EXECUTA A CONSULTA
// -----------------------------------------------

if (!empty($params)) {

    $stmt = $conn->prepare($sql);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $receitas = $stmt->get_result();

} else {

    $receitas = $conn->query($sql);

}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Livro de Receitas — Listagem</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css?v=4">
</head>

<body>

    <div class="container">

        <!-- CABEÇALHO -->
        <div class="page-header-bar">
            <a href="index.php" class="brand" title="Ir para o menu"><img src="assets/logo.png" alt="Diário de receitas"></a>

            <h2>📖 Livro de Receitas</h2>

            <div class="header-actions">

                <a href="index.php" class="btn-back">
                    ⬅ Voltar ao Menu
                </a>

                <a href="cadastro_receita.php" class="btn-primary">
                    ➕ Nova Receita
                </a>

            </div>

        </div>

        <!-- FILTROS -->
        <div class="filters">

            <form method="get" class="filter-form">

                <div class="filter-group">

                    <div class="filter-item">

                        <label for="busca">Buscar receita</label>

                        <input
                            type="text"
                            name="busca"
                            id="busca"
                            value="<?php echo htmlspecialchars($_GET['busca'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Digite o nome da receita ou chef...">

                    </div>

                    <div class="filter-item">

                        <label for="chef">Chef</label>

                        <select name="chef" id="chef">

                            <option value="">Todos os chefs</option>

                            <?php while ($c = $chefs->fetch_assoc()): ?>

                                <option
                                    value="<?php echo $c['id']; ?>"
                                    <?php echo (
                                        isset($_GET['chef']) &&
                                        $_GET['chef'] == $c['id']
                                    ) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8'); ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <div class="filter-item">

                        <label for="categoria">Categoria</label>

                        <select name="categoria" id="categoria">

                            <option value="">Todas as categorias</option>

                            <?php while ($cat = $categorias->fetch_assoc()): ?>

                                <option
                                    value="<?php echo htmlspecialchars($cat['categoria'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo (
                                        isset($_GET['categoria']) &&
                                        $_GET['categoria'] == $cat['categoria']
                                    ) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($cat['categoria'], ENT_QUOTES, 'UTF-8'); ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                </div>

                <div class="filter-actions">

                    <button type="submit" class="btn-primary">
                        🔍 Filtrar
                    </button>

                    <?php if (!empty($_GET)): ?>

                        <a href="listagem_receitas.php" class="btn-back">
                            ✖ Limpar
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

        <!-- FEEDBACK DE OPERAÇÕES -->

        <?php if (isset($mensagem)): ?>

            <div class="message <?php echo $tipo_msg; ?>">

                <?php echo $tipo_msg === 'success' ? '✅' : '⚠️'; ?>

                <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>

            </div>

        <?php endif; ?>

        <!-- TABELA DE RECEITAS -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th class="col-capa">Capa</th>
                        <th>Receita</th>
                        <th>Chef</th>
                        <th>Categoria</th>
                        <th class="col-acoes">Ações</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if ($receitas->num_rows > 0): ?>

                        <?php while ($row = $receitas->fetch_assoc()): ?>

                            <tr>

                                <!-- IMAGEM DA RECEITA -->

                                <td>

                                    <?php if (!empty($row['imagem_capa'])): ?>

                                        <img
                                            src="<?php echo htmlspecialchars($row['imagem_capa'], ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="Capa da receita"
                                            class="table-thumbnail"
                                            loading="lazy">

                                    <?php else: ?>

                                        <div class="no-image">
                                            Sem<br>imagem
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <!-- NOME DA RECEITA -->

                                <td>
                                    <?php echo htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- CHEF RESPONSÁVEL -->

                                <td>
                                    <?php echo htmlspecialchars($row['chef_nome'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- CATEGORIA -->

                                <td>
                                    <?php echo htmlspecialchars($row['categoria'] ?? '—', ENT_QUOTES, 'UTF-8') ?: '—'; ?>
                                </td>

                                <!-- AÇÕES -->

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="cadastro_receita.php?edit_id=<?php echo $row['id']; ?>"
                                            class="btn-edit">
                                            ✏️ Editar
                                        </a>

                                        <a
                                            href="?delete_id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Deseja realmente excluir esta receita?')"
                                            class="btn-delete">
                                            🗑️
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5" class="empty-cell">

                                <?php
                                echo !empty($_GET)
                                    ? 'Nenhuma receita encontrada com esses filtros.'
                                    : 'Nenhuma receita cadastrada ainda.';
                                ?>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</body>

</html>
