<?php
/**
 * index.php - Portal de Notícias (Front-end)
 * Portal de Notícias SEO Pro + Enquetes
 */

require_once __DIR__ . '/functions.php';

// Verifica auto-update
if (precisaAtualizarNoticias()) {
    capturarNoticiasAutomatico();
}

// Roteamento simples
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($basePath !== '/' && $basePath !== '') {
    $requestUri = substr($requestUri, strlen($basePath));
}
$requestUri = trim($requestUri, '/');

// Determina a página atual
$page = 'home';
$noticia = null;
$categoria = null;

if (empty($requestUri) || $requestUri === 'index.php') {
    $page = 'home';
} elseif (strpos($requestUri, 'noticia/') === 0) {
    $page = 'noticia';
    $slug = str_replace('noticia/', '', $requestUri);
    $noticia = getNoticiaBySlug($slug);
    if (!$noticia) {
        http_response_code(404);
    }
} elseif (strpos($requestUri, 'categoria/') === 0) {
    $page = 'categoria';
    $categoria = str_replace('categoria/', '', $requestUri);
    if (!array_key_exists($categoria, getCategories())) {
        http_response_code(404);
        $page = 'home';
        $categoria = null;
    }
} elseif ($requestUri === 'votar') {
    // Processa voto em enquete
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $idEnquete = filter_input(INPUT_POST, 'id_enquete', FILTER_VALIDATE_INT);
        $idOpcao = filter_input(INPUT_POST, 'id_opcao', FILTER_VALIDATE_INT);
        
        if ($idEnquete && $idOpcao) {
            $success = registrarVoto($idEnquete, $idOpcao);
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?voto=' . ($success ? '1' : '0'));
            exit;
        }
    }
    header('Location: /');
    exit;
}

$config = getSiteConfig();
$categories = getCategories();
$enquete = getEnqueteAtiva();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <?php if ($page === 'noticia' && $noticia): ?>
        <?= generateSEOTags($noticia['titulo'], extrairResumo($noticia['conteudo']), $noticia['imagem']) ?>
        <?= generateNewsArticleSchema($noticia) ?>
    <?php else: ?>
        <?= generateSEOTags($categoria ? $categories[$categoria] : 'Últimas Notícias', 'Portal de Notícias com foco em Futebol, Política, Gospel e Atualidades') ?>
    <?php endif; ?>
    
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-top">
            <div class="container">
                <span><?= date('d/m/Y H:i') ?></span>
                <span><a href="/admin.php">Área Administrativa</a></span>
            </div>
        </div>
        
        <div class="header-main">
            <div class="container">
                <div class="logo">
                    <?php if (!empty($config['logotipo_path'])): ?>
                        <img src="<?= e($config['logotipo_path']) ?>" alt="<?= e($config['titulo_site']) ?>">
                    <?php else: ?>
                        <a href="/"><?= e($config['titulo_site']) ?></a>
                    <?php endif; ?>
                </div>
                
                <nav>
                    <ul>
                        <li><a href="/" class="<?= $page === 'home' ? 'active' : '' ?>">Home</a></li>
                        <?php foreach ($categories as $catKey => $catName): ?>
                            <li><a href="/categoria/<?= $catKey ?>" class="<?= $categoria === $catKey ? 'active' : '' ?>"><?= e($catName) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="main-content">
            <!-- News Section -->
            <main class="news-section">
                <?php if ($page === 'home'): ?>
                    <h1 class="section-title">Últimas Notícias</h1>
                    
                    <?php
                    $destaque = getNoticiaDestaque();
                    if ($destaque):
                    ?>
                    <div class="news-featured mb-2">
                        <article class="news-card">
                            <img src="<?= e($destaque['imagem'] ?: 'assets/news-placeholder.jpg') ?>" 
                                 alt="<?= e($destaque['titulo']) ?>" 
                                 class="news-card-image">
                            <div class="news-card-content">
                                <span class="news-card-category"><?= e($categories[$destaque['categoria']] ?? $destaque['categoria']) ?></span>
                                <h2 class="news-card-title">
                                    <a href="/noticia/<?= e($destaque['slug']) ?>"><?= e($destaque['titulo']) ?></a>
                                </h2>
                                <p class="news-card-excerpt"><?= extrairResumo($destaque['conteudo'], 300) ?></p>
                                <div class="news-card-meta">
                                    <span><?= formatarData($destaque['data_publicacao']) ?></span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endif; ?>
                    
                    <div class="categories-filter">
                        <ul>
                            <li><a href="/" class="<?= empty($categoria) ? 'active' : '' ?>">Todas</a></li>
                            <?php foreach ($categories as $catKey => $catName): ?>
                                <li><a href="/categoria/<?= $catKey ?>" class="<?= $categoria === $catKey ? 'active' : '' ?>"><?= e($catName) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="news-grid">
                        <?php
                        $noticias = getNoticias($categoria, 12);
                        foreach ($noticias as $n):
                            if ($destaque && $n['id'] === $destaque['id']) continue;
                        ?>
                        <article class="news-card">
                            <img src="<?= e($n['imagem'] ?: 'assets/news-placeholder.jpg') ?>" 
                                 alt="<?= e($n['titulo']) ?>" 
                                 class="news-card-image">
                            <div class="news-card-content">
                                <span class="news-card-category"><?= e($categories[$n['categoria']] ?? $n['categoria']) ?></span>
                                <h3 class="news-card-title">
                                    <a href="/noticia/<?= e($n['slug']) ?>"><?= e($n['titulo']) ?></a>
                                </h3>
                                <p class="news-card-excerpt"><?= extrairResumo($n['conteudo']) ?></p>
                                <div class="news-card-meta">
                                    <span><?= formatarData($n['data_publicacao']) ?></span>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($noticias)): ?>
                        <p class="text-center mt-2">Nenhuma notícia encontrada.</p>
                    <?php endif; ?>
                    
                <?php elseif ($page === 'categoria'): ?>
                    <h1 class="section-title"><?= e($categories[$categoria]) ?></h1>
                    
                    <div class="news-grid">
                        <?php
                        $noticias = getNoticias($categoria, 12);
                        foreach ($noticias as $n):
                        ?>
                        <article class="news-card">
                            <img src="<?= e($n['imagem'] ?: 'assets/news-placeholder.jpg') ?>" 
                                 alt="<?= e($n['titulo']) ?>" 
                                 class="news-card-image">
                            <div class="news-card-content">
                                <span class="news-card-category"><?= e($categories[$n['categoria']] ?? $n['categoria']) ?></span>
                                <h3 class="news-card-title">
                                    <a href="/noticia/<?= e($n['slug']) ?>"><?= e($n['titulo']) ?></a>
                                </h3>
                                <p class="news-card-excerpt"><?= extrairResumo($n['conteudo']) ?></p>
                                <div class="news-card-meta">
                                    <span><?= formatarData($n['data_publicacao']) ?></span>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="/" class="btn btn-secondary mt-2">&larr; Voltar para Home</a>
                    
                <?php elseif ($page === 'noticia' && $noticia): ?>
                    <article class="news-single">
                        <header class="news-single-header">
                            <span class="news-card-category"><?= e($categories[$noticia['categoria']] ?? $noticia['categoria']) ?></span>
                            <h1 class="news-single-title"><?= e($noticia['titulo']) ?></h1>
                            <div class="news-single-meta">
                                <span>Publicado em <?= formatarData($noticia['data_publicacao']) ?></span>
                                <?php if (!empty($noticia['updated_at']) && $noticia['updated_at'] !== $noticia['data_publicacao']): ?>
                                    <span>Atualizado em <?= formatarData($noticia['updated_at']) ?></span>
                                <?php endif; ?>
                            </div>
                        </header>
                        
                        <?php if (!empty($noticia['imagem'])): ?>
                            <img src="<?= e($noticia['imagem']) ?>" 
                                 alt="<?= e($noticia['titulo']) ?>" 
                                 class="news-single-image">
                        <?php endif; ?>
                        
                        <div class="news-single-content">
                            <?= nl2br(e($noticia['conteudo'])) ?>
                        </div>
                    </article>
                    
                    <a href="/" class="btn btn-secondary mt-2">&larr; Voltar para Home</a>
                <?php endif; ?>
            </main>
            
            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Enquete Widget -->
                <?php if ($enquete): ?>
                <div class="sidebar-widget">
                    <h3 class="widget-title">Enquete</h3>
                    <form action="/votar" method="POST">
                        <input type="hidden" name="id_enquete" value="<?= $enquete['id'] ?>">
                        <p class="mb-1"><strong><?= e($enquete['pergunta']) ?></strong></p>
                        <ul class="poll-options">
                            <?php foreach ($enquete['opcoes'] as $opcao): ?>
                                <li class="poll-option">
                                    <label>
                                        <input type="radio" name="id_opcao" value="<?= $opcao['id'] ?>" required>
                                        <?= e($opcao['texto_opcao']) ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="submit" class="poll-submit">Votar</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Categorias Widget -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">Categorias</h3>
                    <ul style="list-style: none;">
                        <?php foreach ($categories as $catKey => $catName): ?>
                            <li class="mb-1">
                                <a href="/categoria/<?= $catKey ?>">→ <?= e($catName) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
            
            <!-- Ads Sidebar (Fixed) -->
            <aside class="ads-sidebar">
                <h3 class="widget-title">Publicidade</h3>
                <?php if (!empty($config['script_ads_lateral'])): ?>
                    <?= $config['script_ads_lateral'] ?>
                <?php else: ?>
                    <div class="ad-space">
                        <div class="ad-placeholder">
                    Espaço Publicitário<br>
                    (Configure no Admin)
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Sobre</h4>
                    <p><?= e($config['texto_rodape']) ?></p>
                </div>
                <div class="footer-section">
                    <h4>Categorias Principais</h4>
                    <ul style="list-style: none;">
                        <li><a href="/categoria/esportes-futebol-nacional">Futebol Nacional</a></li>
                        <li><a href="/categoria/esportes-futebol-internacional">Futebol Internacional</a></li>
                        <li><a href="/categoria/politica-nacional">Política Nacional</a></li>
                        <li><a href="/categoria/atualidades-guerra">Atualidades</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Links Úteis</h4>
                    <ul style="list-style: none;">
                        <li><a href="/">Home</a></li>
                        <li><a href="/admin.php">Admin</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <?= e($config['texto_rodape']) ?>
            </div>
        </div>
    </footer>
</body>
</html>
