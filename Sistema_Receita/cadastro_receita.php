<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * =============================================
 * ARQUIVO: cadastro_receita.php
 * =============================================
 *
 * Cada receita pertence a um chef (chefs_id é
 * chave estrangeira que referencia chefs.id).
 */
include('valida_sessao.php');
include('conexao.php');

function getYoutubeVideoId(string $url)
{
    $patterns = [
        '/youtube\.com\/watch\?v=([^\&\?\/]+)/',
        '/youtube\.com\/embed\/([^\&\?\/]+)/',
        '/youtube\.com\/v\/([^\&\?\/]+)/',
        '/youtu\.be\/([^\&\?\/]+)/'
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $url, $m)) return $m[1];
    }
    return "";
}

function redimensionarESalvarImagem(array $arquivo, $largura = 300, $altura = 300)
{
    $dir = "img/";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $caminho = $dir . uniqid() . '_' . basename($arquivo["name"]);
    $tipo    = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    if (!getimagesize($arquivo["tmp_name"]))      return "Arquivo não é uma imagem válida.";
    if ($arquivo["size"] > 5000000)               return "Imagem muito grande. Máximo: 5 MB.";
    if (!in_array($tipo, ["jpg", "jpeg", "png"])) return "Apenas JPG e PNG são aceitos.";

    $orig = imagecreatefromstring(file_get_contents($arquivo["tmp_name"]));
    if (!$orig) return "Erro ao processar a imagem.";

    $w0   = imagesx($orig);
    $h0   = imagesy($orig);
    $r    = min($largura / $w0, $altura / $h0);
    $nw   = (int)($w0 * $r);
    $nh   = (int)($h0 * $r);
    $nova = imagecreatetruecolor($nw, $nh);

    if ($tipo === "png") {
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
        $transparente = imagecolorallocatealpha($nova, 255, 255, 255, 127);
        imagefilledrectangle($nova, 0, 0, $nw, $nh, $transparente);
    }

    imagecopyresampled($nova, $orig, 0, 0, 0, 0, $nw, $nh, $w0, $h0);
    ($tipo === "png") ? imagepng($nova, $caminho) : imagejpeg($nova, $caminho, 90);
    imagedestroy($orig);
    imagedestroy($nova);
    return $caminho;
}


// -----------------------------------------------
// CREATE / UPDATE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id           = $_POST['id']                 ?? '';
    $chefs_id     = intval($_POST['chefs_id']    ?? 0);
    $nome         = trim($_POST['nome']          ?? '');
    $pais         = trim($_POST['pais']          ?? '');
    $categoria    = trim($_POST['categoria']     ?? '');
    $receita      = trim($_POST['receita']       ?? '');
    $youtube_link = trim($_POST['youtube_link']  ?? '');

    if ($chefs_id <= 0 || $nome === '') {
        $mensagem = "Selecione um chef e informe o nome da receita.";
        $class    = "error";
    } elseif ($youtube_link !== '' && empty(getYoutubeVideoId($youtube_link))) {
        $mensagem = "Insira um link válido do YouTube (ex: https://www.youtube.com/watch?v=...) ou deixe em branco.";
        $class    = "error";
    } else {
        $imagem_capa = "";
        if (isset($_FILES['imagem_capa']) && $_FILES['imagem_capa']['error'] == 0) {
            $upload = redimensionarESalvarImagem($_FILES['imagem_capa']);
            if (str_starts_with($upload, 'img/')) {
                $imagem_capa = $upload;
            } else {
                $mensagem = $upload;
                $class    = "error";
            }
        }

        if (!isset($class)) {
            if ($id) {
                $idInt = intval($id);

                // Remove foto antiga se uma nova foi enviada
                if ($imagem_capa) {
                    $oldStmt = $conn->prepare("SELECT imagem_capa FROM receitas WHERE id = ?");
                    $oldStmt->bind_param("i", $idInt);
                    $oldStmt->execute();
                    $oldImg = $oldStmt->get_result()->fetch_assoc()['imagem_capa'] ?? '';
                    $oldStmt->close();

                    if ($oldImg && file_exists($oldImg)) {
                        unlink($oldImg);
                    }
                }

                // UPDATE na tabela receitas
                $sql    = "UPDATE receitas SET chefs_id=?, nome=?, pais=?, categoria=?, receita=?, youtube_link=?";
                $params = [$chefs_id, $nome, $pais, $categoria, $receita, $youtube_link];
                $tipos  = "isssss";

                if ($imagem_capa) {
                    $sql     .= ", imagem_capa=?";
                    $params[] = $imagem_capa;
                    $tipos   .= "s";
                }
                $sql     .= " WHERE id=?";
                $params[] = $idInt;
                $tipos   .= "i";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($tipos, ...$params);
                $mensagem = "Receita atualizada com sucesso!";
            } else {
                // INSERT na tabela receitas
                $stmt = $conn->prepare("INSERT INTO receitas (chefs_id, nome, pais, categoria, receita, youtube_link, imagem_capa) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param("issssss", $chefs_id, $nome, $pais, $categoria, $receita, $youtube_link, $imagem_capa);
                $mensagem = "Receita cadastrada com sucesso!";
            }

            $class = $stmt->execute() ? "success" : "error";
            if ($class === "error") {
                $mensagem = "Erro no banco de dados: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}


// -----------------------------------------------
// DELETE
// -----------------------------------------------
if (isset($_GET['delete_id'])) {
    $id   = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT imagem_capa FROM receitas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $arq = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($arq && !empty($arq['imagem_capa']) && file_exists($arq['imagem_capa'])) {
        unlink($arq['imagem_capa']);
    }

    $stmt = $conn->prepare("DELETE FROM receitas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $class    = $stmt->execute() ? "success" : "error";
    $mensagem = $class === "success" ? "Receita excluída com sucesso!" : "Erro: " . $stmt->error;
    $stmt->close();
}


// -----------------------------------------------
// READ (edição e listagem)
// -----------------------------------------------
$receita_edit = null;
if (isset($_GET['edit_id'])) {
    $eid  = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM receitas WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $receita_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$chefs          = $conn->query("SELECT id, nome FROM chefs ORDER BY nome");
$chef_id_filtro = isset($_GET['chef_id']) ? intval($_GET['chef_id'])
    : ($receita_edit['chefs_id'] ?? null);

$receitas = null;
if ($chef_id_filtro) {
    $stmt = $conn->prepare("
        SELECT r.*, c.nome AS chef_nome
        FROM receitas r
        JOIN chefs c ON r.chefs_id = c.id
        WHERE r.chefs_id = ?
        ORDER BY r.nome
    ");
    $stmt->bind_param("i", $chef_id_filtro);
    $stmt->execute();
    $receitas = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $receita_edit ? 'Editar Receita' : 'Nova Receita'; ?> — Diário de Receitas</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27><text y=%27.9em%27 font-size=%2790%27>🍜</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=4">
</head>

<body>
    <div class="container">

        <div class="page-header-bar">
            <a href="index.php" class="brand" title="Ir para o menu"><img src="assets/logo.png" alt="Diário de receitas"></a>
            <h2><?php echo $receita_edit ? '✏️ Editar Receita' : '🍜 Nova Receita'; ?></h2>
            <div class="header-actions">
                <?php if ($chef_id_filtro): ?>
                    <a href="cadastro_chef.php" class="btn-back">⬅ Voltar para Chefs</a>
                <?php else: ?>
                    <a href="index.php" class="btn-back">⬅ Voltar ao Menu</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($mensagem)): ?>
            <div class="message <?php echo $class; ?>">
                <?php echo $class === 'success' ? '✅' : '⚠️'; ?> <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- FORMULÁRIO -->
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $receita_edit['id'] ?? ''; ?>">

            <div class="form-grid">

                <!-- Linha 1: Chef + Nome do Prato -->
                <div class="form-group">
                    <label for="chefs_id">Chef <span style="color:var(--red)">*</span></label>
                    <select name="chefs_id" id="chefs_id" required
                        <?php echo isset($_GET['chef_id']) ? 'disabled' : ''; ?>>
                        <option value="">Selecione um chef</option>
                        <?php if ($chefs): ?>
                            <?php while ($row = $chefs->fetch_assoc()): ?>
                                <?php $sel = $receita_edit['chefs_id'] ?? ($_GET['chef_id'] ?? ''); ?>
                                <option value="<?php echo $row['id']; ?>"
                                    <?php echo ($sel == $row['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($_GET['chef_id'])): ?>
                        <input type="hidden" name="chefs_id" value="<?php echo intval($_GET['chef_id']); ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="nome">Nome da Receita <span style="color:var(--red)">*</span></label>
                    <input type="text" id="nome" name="nome"
                        value="<?php echo htmlspecialchars($receita_edit['nome'] ?? ''); ?>"
                        required placeholder="Ex: Risoto de Cogumelos, Feijoada…">
                </div>

                <!-- Linha 2: País de Origem + Categoria -->
                <div class="form-group">
                    <label for="pais">País de Origem</label>
                    <input type="text" id="pais" name="pais"
                        value="<?php echo htmlspecialchars($receita_edit['pais'] ?? ''); ?>"
                        placeholder="Ex: Itália, Brasil, França…">
                </div>

                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <input type="text" id="categoria" name="categoria"
                        value="<?php echo htmlspecialchars($receita_edit['categoria'] ?? ''); ?>"
                        placeholder="Ex: Prato Principal, Sobremesa, Massa…">
                </div>

                <!-- Linha 3: YouTube + Foto do Prato -->
                <div class="form-group">
                    <label for="youtube_link">Link do YouTube ▶️</label>
                    <input type="url" id="youtube_link" name="youtube_link"
                        value="<?php echo htmlspecialchars($receita_edit['youtube_link'] ?? ''); ?>"
                        placeholder="https://www.youtube.com/watch?v=…">
                    <small>Cole a URL do vídeo de preparo (opcional).</small>
                </div>

                <div class="form-group">
                    <label for="imagem_capa">Foto do Prato 🥘</label>
                    <input type="file" id="imagem_capa" name="imagem_capa" accept="image/jpeg, image/png">
                    <small>JPG ou PNG · Máximo 5 MB<?php echo $receita_edit ? ' · Deixe vazio para manter a foto atual' : ''; ?></small>
                    <?php if (!empty($receita_edit['imagem_capa'])): ?>
                        <div class="current-image">
                            <img src="<?php echo htmlspecialchars($receita_edit['imagem_capa']); ?>" alt="Foto atual">
                            <small>Foto atual</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Linha 4: Ingredientes e Modo de Preparo (ocupa as 2 colunas) -->
                <div class="form-group full">
                    <label for="receita">Ingredientes e Modo de Preparo</label>
                    <textarea id="receita" name="receita"
                        placeholder="Descreva os ingredientes e o passo a passo da receita…"><?php
                                                                                                echo htmlspecialchars($receita_edit['receita'] ?? '');
                                                                                                ?></textarea>
                </div>

            </div><!-- fim .form-grid -->

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <?php echo $receita_edit ? '💾 Atualizar' : '➕ Cadastrar'; ?>
                </button>
                <?php if ($receita_edit): ?>
                    <a href="cadastro_receita.php<?php echo $chef_id_filtro ? '?chef_id=' . $chef_id_filtro : ''; ?>"
                        class="btn-back">✖ Cancelar</a>
                <?php endif; ?>
            </div>
        </form>


        <!-- -----------------------------------------------
         TABELA DE RECEITAS DO CHEF SELECIONADO
    ----------------------------------------------- -->
        <?php if ($receitas && $receitas->num_rows > 0): ?>
            <h3>📖 Receitas Cadastradas</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="col-capa">Foto</th>
                            <th>Receita</th>
                            <th>Categoria</th>
                            <th class="col-youtube">YouTube</th>
                            <th class="col-acoes">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $receitas->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($row['imagem_capa'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['imagem_capa']); ?>"
                                            alt="Foto do prato" class="table-thumbnail" loading="lazy">
                                    <?php else: ?>
                                        <div class="no-image">Sem<br>foto</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo htmlspecialchars($row['categoria']) ?: '—'; ?></td>
                                <td>
                                    <?php
                                    $vid = getYoutubeVideoId($row['youtube_link'] ?? '');
                                    if ($vid):
                                        $watch_url = "https://www.youtube.com/watch?v={$vid}";
                                    ?>
                                        <a href="<?php echo $watch_url; ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn-youtube">▶ Assistir</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?chef_id=<?php echo $chef_id_filtro; ?>&edit_id=<?php echo $row['id']; ?>"
                                            class="btn-edit">✏️ Editar</a>
                                        <a href="?chef_id=<?php echo $chef_id_filtro; ?>&delete_id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Excluir <?php echo addslashes($row['nome']); ?>?')"
                                            class="btn-delete">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>