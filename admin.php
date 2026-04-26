<?php
/**
 * admin.php - Painel Administrativo
 * Portal de Notícias SEO Pro + Enquetes
 */

require_once __DIR__ . '/functions.php';

// Sistema de autenticação simples (em produção, use sessões e senhas hash)
session_start();

// Configurações de autenticação (em produção, mover para arquivo de configuração seguro)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123'); // Em produção, usar password_hash()

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Usuário ou senha inválidos.';
    }
}

// Verifica se está logado
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Processa ações apenas se logado
$message = '';
$messageType = '';

if ($isLoggedIn) {
    $pdo = getDbConnection();
    
    // Ação: Atualizar notícias manualmente
    if (isset($_POST['action']) && $_POST['action'] === 'atualizar_noticias') {
        $count = capturarNoticiasAutomatico();
        $message = "Atualização concluída! {$count} nova(s) notícia(s) adicionada(s).";
        $messageType = 'success';
    }
    
    // Ação: Criar/Editar notícia
    if (isset($_POST['action']) && $_POST['action'] === 'salvar_noticia') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $titulo = trim($_POST['titulo'] ?? '');
        $categoria = $_POST['categoria'] ?? '';
        $conteudo = trim($_POST['conteudo'] ?? '');
        $imagem = trim($_POST['imagem'] ?? '');
        
        if ($titulo && $categoria && $conteudo) {
            $slug = generateSlug($titulo);
            
            // Verifica slug duplicado
            $stmt = $pdo->prepare("SELECT id FROM noticias WHERE slug = :slug" . ($id ? " AND id != :id" : ""));
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            if ($id) {
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $slug .= '-' . time();
            }
            
            if ($id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE noticias 
                    SET titulo = :titulo, slug = :slug, categoria = :categoria, 
                        conteudo = :conteudo, imagem = :imagem, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO noticias (titulo, slug, categoria, conteudo, imagem, data_publicacao)
                    VALUES (:titulo, :slug, :categoria, :conteudo, :imagem, datetime('now'))
                ");
            }
            
            $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
            $stmt->bindValue(':conteudo', $conteudo, PDO::PARAM_STR);
            $stmt->bindValue(':imagem', $imagem, PDO::PARAM_STR);
            $stmt->execute();
            
            $message = "Notícia " . ($id ? "atualizada" : "criada") . " com sucesso!";
            $messageType = 'success';
        } else {
            $message = "Preencha todos os campos obrigatórios.";
            $messageType = 'error';
        }
    }
    
    // Ação: Excluir notícia
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_noticia') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $message = "Notícia excluída com sucesso!";
            $messageType = 'success';
        }
    }
    
    // Ação: Criar enquete
    if (isset($_POST['action']) && $_POST['action'] === 'criar_enquete') {
        $pergunta = trim($_POST['pergunta'] ?? '');
        $opcoes = $_POST['opcoes'] ?? [];
        
        if ($pergunta && !empty($opcoes)) {
            // Desativa todas as enquetes ativas
            $pdo->exec("UPDATE enquetes SET status = 'inativa' WHERE status = 'ativa'");
            
            // Cria nova enquete
            $stmt = $pdo->prepare("INSERT INTO enquetes (pergunta, status) VALUES (:pergunta, 'ativa')");
            $stmt->bindValue(':pergunta', $pergunta, PDO::PARAM_STR);
            $stmt->execute();
            $idEnquete = $pdo->lastInsertId();
            
            // Insere opções
            $stmt = $pdo->prepare("INSERT INTO enquetes_opcoes (id_enquete, texto_opcao) VALUES (:id_enquete, :texto)");
            foreach ($opcoes as $opcao) {
                if (!empty(trim($opcao))) {
                    $stmt->bindValue(':id_enquete', $idEnquete, PDO::PARAM_INT);
                    $stmt->bindValue(':texto', trim($opcao), PDO::PARAM_STR);
                    $stmt->execute();
                }
            }
            
            $message = "Enquete criada com sucesso!";
            $messageType = 'success';
        } else {
            $message = "Preencha a pergunta e pelo menos uma opção.";
            $messageType = 'error';
        }
    }
    
    // Ação: Excluir enquete
    if (isset($_POST['action']) && $_POST['action'] === 'excluir_enquete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM enquetes WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $message = "Enquete excluída com sucesso!";
            $messageType = 'success';
        }
    }
    
    // Ação: Salvar configurações
    if (isset($_POST['action']) && $_POST['action'] === 'salvar_configuracoes') {
        $tituloSite = trim($_POST['titulo_site'] ?? '');
        $textoRodape = trim($_POST['texto_rodape'] ?? '');
        $scriptAds = trim($_POST['script_ads_lateral'] ?? '');
        
        // Upload de logotipo
        $logotipoPath = $_POST['logotipo_path_atual'] ?? '';
        if (isset($_FILES['logotipo']) && $_FILES['logotipo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['logotipo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo-' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['logotipo']['tmp_name'], $filepath)) {
                $logotipoPath = 'uploads/' . $filename;
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE configuracoes 
            SET titulo_site = :titulo, texto_rodape = :rodape, script_ads_lateral = :ads, logotipo_path = :logo
            WHERE id = 1
        ");
        $stmt->bindValue(':titulo', $tituloSite, PDO::PARAM_STR);
        $stmt->bindValue(':rodape', $textoRodape, PDO::PARAM_STR);
        $stmt->bindValue(':ads', $scriptAds, PDO::PARAM_STR);
        $stmt->bindValue(':logo', $logotipoPath, PDO::PARAM_STR);
        $stmt->execute();
        
        $message = "Configurações salvas com sucesso!";
        $messageType = 'success';
    }
}

// Busca dados para exibição
$noticias = [];
$enquetes = [];
$config = getSiteConfig();
$categories = getCategories();

if ($isLoggedIn) {
    $noticias = $pdo->query("SELECT * FROM noticias ORDER BY data_publicacao DESC")->fetchAll();
    $enquetes = $pdo->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM enquetes_opcoes WHERE id_enquete = e.id) as num_opcoes,
               (SELECT SUM(votos) FROM enquetes_opcoes WHERE id_enquete = e.id) as total_votos
        FROM enquetes e 
        ORDER BY e.data_criacao DESC
    ")->fetchAll();
    
    // Notícia sendo editada
    $editNoticia = null;
    if (isset($_GET['editar_noticia'])) {
        $id = filter_input(INPUT_GET, 'editar_noticia', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM noticias WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $editNoticia = $stmt->fetch();
        }
    }
}

// Determina aba ativa
$activeTab = $_GET['tab'] ?? 'noticias';
if (!in_array($activeTab, ['noticias', 'enquetes', 'configuracoes'])) {
    $activeTab = 'noticias';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - <?= e($config['titulo_site']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f6fa; }
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .opcao-dinamica { margin-bottom: 10px; display: flex; gap: 10px; }
        .opcao-dinamica input { flex: 1; }
    </style>
</head>
<body>
    <?php if (!$isLoggedIn): ?>
        <!-- Formulário de Login -->
        <div class="admin-container">
            <div class="admin-panel" style="max-width: 400px; margin: 100px auto;">
                <h2 class="text-center mb-2">Login Administrativo</h2>
                
                <?php if ($loginError): ?>
                    <div class="message message-error"><?= e($loginError) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Usuário</label>
                        <input type="text" name="username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Entrar</button>
                </form>
                <p class="text-center mt-2"><a href="/">← Voltar ao site</a></p>
            </div>
        </div>
    <?php else: ?>
        <!-- Painel Administrativo -->
        <div class="admin-container">
            <div class="admin-panel">
                <div class="admin-header">
                    <h1>Painel Administrativo</h1>
                    <div>
                        <a href="/" class="btn btn-secondary" target="_blank">Ver Site</a>
                        <a href="?logout=1" class="btn btn-danger">Sair</a>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="admin-tabs">
                    <a href="?tab=noticias" class="admin-tab <?= $activeTab === 'noticias' ? 'active' : '' ?>">Notícias</a>
                    <a href="?tab=enquetes" class="admin-tab <?= $activeTab === 'enquetes' ? 'active' : '' ?>">Enquetes</a>
                    <a href="?tab=configuracoes" class="admin-tab <?= $activeTab === 'configuracoes' ? 'active' : '' ?>">Configurações</a>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="message message-<?= $messageType ?>"><?= e($message) ?></div>
                <?php endif; ?>
                
                <!-- Tab: Notícias -->
                <?php if ($activeTab === 'noticias'): ?>
                    <div class="mb-2">
                        <button onclick="document.getElementById('form-noticia').style.display='block'" class="btn btn-success">
                            + Nova Notícia
                        </button>
                        <form method="POST" class="inline-block" style="display: inline; margin-left: 10px;">
                            <input type="hidden" name="action" value="atualizar_noticias">
                            <button type="submit" class="btn">🔄 Atualizar Notícias (Auto)</button>
                        </form>
                    </div>
                    
                    <!-- Formulário de Notícia -->
                    <div id="form-noticia" style="display: <?= $editNoticia ? 'block' : 'none' ?>;" class="mt-2 mb-2">
                        <div class="sidebar-widget">
                            <h3><?= $editNoticia ? 'Editar' : 'Nova' ?> Notícia</h3>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="action" value="salvar_noticia">
                                <?php if ($editNoticia): ?>
                                    <input type="hidden" name="id" value="<?= $editNoticia['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label>Título *</label>
                                    <input type="text" name="titulo" value="<?= e($editNoticia['titulo'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Categoria *</label>
                                    <select name="categoria" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($categories as $key => $name): ?>
                                            <option value="<?= $key ?>" <?= ($editNoticia['categoria'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= e($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>URL da Imagem</label>
                                    <input type="url" name="imagem" value="<?= e($editNoticia['imagem'] ?? '') ?>" placeholder="https://exemplo.com/imagem.jpg">
                                </div>
                                
                                <div class="form-group">
                                    <label>Conteúdo *</label>
                                    <textarea name="conteudo" rows="10" required><?= e($editNoticia['conteudo'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success"><?= $editNoticia ? 'Atualizar' : 'Criar' ?> Notícia</button>
                                <?php if ($editNoticia): ?>
                                    <a href="admin.php?tab=noticias" class="btn btn-secondary">Cancelar</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lista de Notícias -->
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($noticias as $n): ?>
                                <tr>
                                    <td><?= $n['id'] ?></td>
                                    <td><a href="/noticia/<?= e($n['slug']) ?>" target="_blank"><?= e($n['titulo']) ?></a></td>
                                    <td><?= e($categories[$n['categoria']] ?? $n['categoria']) ?></td>
                                    <td><?= formatarData($n['data_publicacao']) ?></td>
                                    <td class="admin-actions">
                                        <a href="?tab=noticias&editar_noticia=<?= $n['id'] ?>" class="btn">Editar</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza?')">
                                            <input type="hidden" name="action" value="excluir_noticia">
                                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                            <button type="submit" class="btn btn-danger">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                <?php elseif ($activeTab === 'enquetes'): ?>
                    <!-- Formulário de Enquete -->
                    <div class="sidebar-widget mb-2">
                        <h3>Nova Enquete</h3>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="action" value="criar_enquete">
                            
                            <div class="form-group">
                                <label>Pergunta *</label>
                                <input type="text" name="pergunta" required placeholder="Ex: Quem vence o clássico de domingo?">
                            </div>
                            
                            <div class="form-group">
                                <label>Opções de Resposta *</label>
                                <div id="opcoes-container">
                                    <div class="opcao-dinamica">
                                        <input type="text" name="opcoes[]" placeholder="Opção 1" required>
                                        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">×</button>
                                    </div>
                                    <div class="opcao-dinamica">
                                        <input type="text" name="opcoes[]" placeholder="Opção 2" required>
                                        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">×</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary mt-1" onclick="adicionarOpcao()">+ Adicionar Opção</button>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Criar Enquete</button>
                        </form>
                    </div>
                    
                    <!-- Lista de Enquetes -->
                    <h3>Enquetes</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pergunta</th>
                                <th>Status</th>
                                <th>Opções</th>
                                <th>Total Votos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enquetes as $e): ?>
                                <tr>
                                    <td><?= $e['id'] ?></td>
                                    <td><?= e($e['pergunta']) ?></td>
                                    <td>
                                        <span class="news-card-category" style="background: <?= $e['status'] === 'ativa' ? '#27ae60' : '#7f8c8d' ?>">
                                            <?= e($e['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $e['num_opcoes'] ?></td>
                                    <td><?= $e['total_votos'] ?? 0 ?></td>
                                    <td class="admin-actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza?')">
                                            <input type="hidden" name="action" value="excluir_enquete">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn btn-danger">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                <?php elseif ($activeTab === 'configuracoes'): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="salvar_configuracoes">
                        
                        <div class="form-group">
                            <label>Título do Site</label>
                            <input type="text" name="titulo_site" value="<?= e($config['titulo_site']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Logotipo</label>
                            <?php if (!empty($config['logotipo_path'])): ?>
                                <div class="mb-1">
                                    <img src="<?= e($config['logotipo_path']) ?>" alt="Logotipo atual" style="max-height: 80px;">
                                </div>
                                <input type="hidden" name="logotipo_path_atual" value="<?= e($config['logotipo_path']) ?>">
                            <?php endif; ?>
                            <input type="file" name="logotipo" accept="image/*">
                            <small>Formatos: JPG, PNG, GIF. Tamanho máximo: 2MB</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Texto do Rodapé</label>
                            <textarea name="texto_rodape" rows="3"><?= e($config['texto_rodape']) ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Script de Publicidade Lateral</label>
                            <textarea name="script_ads_lateral" rows="5" placeholder="Cole aqui o código HTML/JS do seu anúncio..."><?= e($config['script_ads_lateral']) ?></textarea>
                            <small>Cole o código fornecido pela sua rede de anúncios (Google AdSense, etc.)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Salvar Configurações</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function adicionarOpcao() {
            const container = document.getElementById('opcoes-container');
            const count = container.children.length + 1;
            const div = document.createElement('div');
            div.className = 'opcao-dinamica';
            div.innerHTML = `
                <input type="text" name="opcoes[]" placeholder="Opção ${count}" required>
                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(div);
        }
        </script>
    <?php endif; ?>
</body>
</html>
