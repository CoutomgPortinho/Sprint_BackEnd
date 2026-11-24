<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * =============================================
 * ARQUIVO: cadastro_chef.php
 * =============================================
 */
include('valida_sessao.php');
include('conexao.php');

$mensagem = null;
$class    = null;

// -----------------------------------------------
// FUNÇÃO: Redimensionar e salvar imagem
// -----------------------------------------------
function redimensionarESalvarImagem(array $arquivo, $largura = 300, $altura = 300)
{
    $dir = "img/";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $nome    = uniqid() . '_' . basename($arquivo["name"]);
    $caminho = $dir . $nome;
    $tipo    = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    // Validações
    if (!getimagesize($arquivo["tmp_name"]))      return "Arquivo não é uma imagem válida.";
    if ($arquivo["size"] > 5000000)               return "Imagem muito grande. Máximo: 5 MB.";
    if (!in_array($tipo, ["jpg", "jpeg", "png"])) return "Apenas JPG e PNG são aceitos.";

    // Carrega e calcula proporções
    $original = imagecreatefromstring(file_get_contents($arquivo["tmp_name"]));
    if (!$original) return "Erro ao processar a imagem.";

    $w0    = imagesx($original);
    $h0    = imagesy($original);
    $ratio = min($largura / $w0, $altura / $h0);
    $nw    = (int)($w0 * $ratio);
    $nh    = (int)($h0 * $ratio);
    $nova  = imagecreatetruecolor($nw, $nh);

    // Preserva transparência caso seja PNG
    if ($tipo === "png") {
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
        $transparente = imagecolorallocatealpha($nova, 255, 255, 255, 127);
        imagefilledrectangle($nova, 0, 0, $nw, $nh, $transparente);
    }

    imagecopyresampled($nova, $original, 0, 0, 0, 0, $nw, $nh, $w0, $h0);

    ($tipo === "png") ? imagepng($nova, $caminho) : imagejpeg($nova, $caminho, 90);

    imagedestroy($original);
    imagedestroy($nova);
    return $caminho;
}


// -----------------------------------------------
// CREATE / UPDATE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id     = $_POST['id']          ?? '';
    $nome   = trim($_POST['nome']   ?? '');
    $genero = trim($_POST['genero'] ?? '');
    $pais   = trim($_POST['pais']   ?? '');

    if ($nome === '') {
        $mensagem = "O nome do chef é obrigatório.";
        $class    = "error";
    }

    $imagem = "";
    if (!isset($class) && isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $upload = redimensionarESalvarImagem($_FILES['imagem']);
        if (str_starts_with($upload, 'img/')) {
            $imagem = $upload;
        } else {
            $mensagem = $upload;
            $class    = "error";
        }
    }

    if (!isset($class)) {
        if ($id) {
            $idInt = intval($id);

            // Se enviou nova imagem, remove a antiga do servidor
            if ($imagem) {
                $oldStmt = $conn->prepare("SELECT imagem FROM chefs WHERE id = ?");
                $oldStmt->bind_param("i", $idInt);
                $oldStmt->execute();
                $oldImg = $oldStmt->get_result()->fetch_assoc()['imagem'] ?? '';
                $oldStmt->close();

                if ($oldImg && file_exists($oldImg)) {
                    unlink($oldImg);
                }
            }

            // UPDATE na tabela chefs
            $sql    = "UPDATE chefs SET nome=?, genero=?, pais=?";
            $params = [$nome, $genero, $pais];
            if ($imagem) {
                $sql .= ", imagem=?";
                $params[] = $imagem;
            }
            $sql .= " WHERE id=?";
            $params[] = $idInt;

            $stmt  = $conn->prepare($sql);
            $tipos = str_repeat('s', count($params) - 1) . 'i';
            $stmt->bind_param($tipos, ...$params);
            $mensagem = "Chef atualizado com sucesso!";
        } else {
            // INSERT na tabela chefs
            $stmt = $conn->prepare("INSERT INTO chefs (nome, genero, pais, imagem) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $nome, $genero, $pais, $imagem);
            $mensagem = "Chef cadastrado com sucesso!";
        }

        $class = $stmt->execute() ? "success" : "error";
        if ($class === "error") {
            $mensagem = "Erro no banco de dados: " . $stmt->error;
        }
        $stmt->close();
    }
}


// -----------------------------------------------
// DELETE
// -----------------------------------------------
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    $chk = $conn->prepare("SELECT COUNT(*) AS total FROM receitas WHERE chefs_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $total = $chk->get_result()->fetch_assoc()['total'];
    $chk->close();

    if ($total > 0) {
        $mensagem = "Não é possível excluir: O chef possui receitas cadastradas no seu livro.";
        $class    = "error";
    } else {
        // Busca a imagem para apagar do servidor antes de deletar o registro
        $imgStmt = $conn->prepare("SELECT imagem FROM chefs WHERE id = ?");
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute();
        $imgCaminho = $imgStmt->get_result()->fetch_assoc()['imagem'] ?? '';
        $imgStmt->close();

        // DELETE na tabela chefs
        $stmt = $conn->prepare("DELETE FROM chefs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $class = $stmt->execute() ? "success" : "error";

        if ($class === "success") {
            if ($imgCaminho && file_exists($imgCaminho)) {
                unlink($imgCaminho);
            }
            $mensagem = "Chef excluído com sucesso!";
        } else {
            $mensagem = "Erro ao excluir: " . $stmt->error;
        }
        $stmt->close();
    }
}


// -----------------------------------------------
// READ (edição e listagem)
// -----------------------------------------------
$chef = null;
if (isset($_GET['edit_id'])) {
    $eid  = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM chefs WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $chef = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$chefs = $conn->query("SELECT * FROM chefs ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $chef ? 'Editar Chef' : 'Novo Chef'; ?> — Diário de Receitas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=4">
</head>

<body>
    <div class="container">

        <div class="page-header-bar">
            <a href="index.php" class="brand" title="Ir para o menu"><img src="assets/logo.png" alt="Diário de receitas"></a>
            <h2><?php echo $chef ? '✏️ Editar Chef' : '👩‍🍳 Novo Chef'; ?></h2>
            <div class="header-actions">
                <a href="index.php" class="btn-back">⬅ Voltar ao Menu</a>
            </div>
        </div>

        <?php if (isset($mensagem)): ?>
            <div class="message <?php echo $class; ?>">
                <?php echo $class === 'success' ? '✅' : '⚠️'; ?> <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data">

            <input type="hidden" name="id" value="<?php echo $chef['id'] ?? ''; ?>">

            <div class="form-grid">

                <div class="form-group">
                    <label for="nome">Nome do Chef <span style="color:var(--red)">*</span></label>
                    <input type="text" id="nome" name="nome"
                        value="<?php echo htmlspecialchars($chef['nome'] ?? ''); ?>"
                        required placeholder="Ex: Érick Jacquin, Helena Rizzo…">
                </div>

                <div class="form-group">
                    <label for="genero">Cozinha Característica</label>
                    <input type="text" id="genero" name="genero"
                        value="<?php echo htmlspecialchars($chef['genero'] ?? ''); ?>"
                        placeholder="Ex: Francesa, Contemporânea, Brasileira…">
                </div>

                <div class="form-group">
                    <label for="pais">País</label>
                    <input type="text" id="pais" name="pais"
                        value="<?php echo htmlspecialchars($chef['pais'] ?? ''); ?>"
                        placeholder="Ex: Brasil, França, Itália…">
                </div>

                <div class="form-group">
                    <label for="imagem">Foto do Chef</label>
                    <input type="file" id="imagem" name="imagem" accept="image/jpeg, image/png">
                    <small>JPG ou PNG · Máximo 5 MB<?php echo $chef ? ' · Deixe vazio para manter a foto atual' : ''; ?></small>

                    <?php if (!empty($chef['imagem'])): ?>
                        <div class="current-image">
                            <img src="<?php echo htmlspecialchars($chef['imagem']); ?>" alt="Foto atual">
                            <small>Foto atual</small>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <?php echo $chef ? '💾 Atualizar' : '➕ Cadastrar'; ?>
                </button>
                <?php if ($chef): ?>
                    <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn-back">✖ Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <h3>📋 Chefs Cadastrados</h3>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="col-foto">Foto</th>
                        <th>Nome</th>
                        <th>Cozinha</th>
                        <th>País</th>
                        <th class="col-acoes">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($chefs && $chefs->num_rows > 0): ?>
                        <?php while ($row = $chefs->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($row['imagem'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['imagem']); ?>"
                                            alt="<?php echo htmlspecialchars($row['nome']); ?>"
                                            class="table-thumbnail">
                                    <?php else: ?>
                                        <div class="no-image">Sem<br>foto</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo htmlspecialchars($row['genero']) ?: '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['pais'])   ?: '—'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit_id=<?php echo $row['id']; ?>" class="btn-edit">✏️ Editar</a>
                                        <a href="?delete_id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Excluir <?php echo addslashes($row['nome']); ?>?')"
                                            class="btn-delete">🗑️</a>
                                        <a href="cadastro_receita.php?chef_id=<?php echo $row['id']; ?>"
                                            class="btn-music">📖 Receitas</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-cell">Nenhum chef cadastrado ainda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>