<?php
/**
 * functions.php - Funções auxiliares, SEO e lógica do sistema
 * Portal de Notícias SEO Pro + Enquetes
 */

require_once __DIR__ . '/db.php';

/**
 * Categorias disponíveis no portal
 * @return array
 */
function getCategories(): array {
    return [
        'gerais' => 'Gerais',
        'nacionais' => 'Nacionais',
        'internacionais' => 'Internacionais',
        'esportes-futebol-nacional' => 'Futebol Nacional',
        'esportes-futebol-internacional' => 'Futebol Internacional',
        'politica-nacional' => 'Política Nacional',
        'politica-internacional' => 'Política Internacional',
        'gospel' => 'Gospel',
        'atualidades-guerra' => 'Atualidades/Guerra'
    ];
}

/**
 * Obtém as configurações do site
 * @return array
 */
function getSiteConfig(): array {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        return [
            'titulo_site' => 'Portal de Notícias SEO Pro',
            'texto_rodape' => '© 2024 Portal de Notícias. Todos os direitos reservados.',
            'logotipo_path' => '',
            'script_ads_lateral' => ''
        ];
    }
    
    return $config;
}

/**
 * Escapa output para prevenir XSS
 * @param string $string
 * @return string
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Gera tags SEO completas para uma página
 * @param string $title
 * @param string $description
 * @param string $image
 * @param string $url
 * @return string
 */
function generateSEOTags(string $title, string $description = '', string $image = '', string $url = ''): string {
    $config = getSiteConfig();
    $siteTitle = e($config['titulo_site']);
    
    $fullTitle = $title ? e($title) . ' | ' . $siteTitle : $siteTitle;
    $metaDescription = $description ? e(substr($description, 0, 160)) : '';
    $ogImage = $image ? (filter_var($image, FILTER_VALIDATE_URL) ? e($image) : e($url . $image)) : '';
    $currentUrl = $url ?: (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    $tags = '<!-- SEO Tags -->' . PHP_EOL;
    $tags .= '<title>' . $fullTitle . '</title>' . PHP_EOL;
    $tags .= '<meta name="description" content="' . $metaDescription . '">' . PHP_EOL;
    $tags .= '<meta name="robots" content="index, follow">' . PHP_EOL;
    $tags .= '<link rel="canonical" href="' . e($currentUrl) . '">' . PHP_EOL;
    
    // Open Graph
    $tags .= '<meta property="og:type" content="article">' . PHP_EOL;
    $tags .= '<meta property="og:title" content="' . $fullTitle . '">' . PHP_EOL;
    $tags .= '<meta property="og:description" content="' . $metaDescription . '">' . PHP_EOL;
    $tags .= '<meta property="og:url" content="' . e($currentUrl) . '">' . PHP_EOL;
    $tags .= '<meta property="og:site_name" content="' . $siteTitle . '">' . PHP_EOL;
    if ($ogImage) {
        $tags .= '<meta property="og:image" content="' . $ogImage . '">' . PHP_EOL;
    }
    
    // Twitter Cards
    $tags .= '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
    $tags .= '<meta name="twitter:title" content="' . $fullTitle . '">' . PHP_EOL;
    $tags .= '<meta name="twitter:description" content="' . $metaDescription . '">' . PHP_EOL;
    if ($ogImage) {
        $tags .= '<meta name="twitter:image" content="' . $ogImage . '">' . PHP_EOL;
    }
    
    return $tags;
}

/**
 * Gera JSON-LD Schema.org para NewsArticle
 * @param array $noticia
 * @param string $url
 * @return string
 */
function generateNewsArticleSchema(array $noticia, string $url = ''): string {
    $config = getSiteConfig();
    $baseUrl = $url ?: (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $noticia['titulo'],
        'description' => mb_substr(strip_tags($noticia['conteudo']), 0, 160),
        'image' => $noticia['imagem'] ? (filter_var($noticia['imagem'], FILTER_VALIDATE_URL) ? $noticia['imagem'] : $baseUrl . '/' . $noticia['imagem']) : null,
        'datePublished' => $noticia['data_publicacao'],
        'dateModified' => $noticia['updated_at'] ?? $noticia['data_publicacao'],
        'author' => [
            '@type' => 'Organization',
            'name' => $config['titulo_site']
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $config['titulo_site'],
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $baseUrl . '/' . ($config['logotipo_path'] ?: 'assets/logo-placeholder.png')
            ]
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $baseUrl . '/noticia/' . $noticia['slug']
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Obtém notícias do banco de dados
 * @param string|null $categoria
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getNoticias(?string $categoria = null, int $limit = 10, int $offset = 0): array {
    $pdo = getDbConnection();
    
    if ($categoria) {
        $sql = "SELECT * FROM noticias WHERE categoria = :categoria ORDER BY data_publicacao DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        $sql = "SELECT * FROM noticias ORDER BY data_publicacao DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtém uma notícia pelo slug
 * @param string $slug
 * @return array|null
 */
function getNoticiaBySlug(string $slug): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM noticias WHERE slug = :slug");
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

/**
 * Obtém a notícia em destaque (mais recente)
 * @return array|null
 */
function getNoticiaDestaque(): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM noticias ORDER BY data_publicacao DESC LIMIT 1");
    return $stmt->fetch() ?: null;
}

/**
 * Obtém enquete ativa
 * @return array|null
 */
function getEnqueteAtiva(): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM enquetes WHERE status = 'ativa' ORDER BY data_criacao DESC LIMIT 1");
    $enquete = $stmt->fetch();
    
    if ($enquete) {
        $stmt = $pdo->prepare("SELECT * FROM enquetes_opcoes WHERE id_enquete = :id_enquete ORDER BY id");
        $stmt->bindValue(':id_enquete', $enquete['id'], PDO::PARAM_INT);
        $stmt->execute();
        $enquete['opcoes'] = $stmt->fetchAll();
    }
    
    return $enquete ?: null;
}

/**
 * Registra voto em enquete
 * @param int $idEnquete
 * @param int $idOpcao
 * @return bool
 */
function registrarVoto(int $idEnquete, int $idOpcao): bool {
    $pdo = getDbConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Verifica se já votou
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enquetes_votos WHERE id_enquete = :id_enquete AND ip_address = :ip");
    $stmt->bindValue(':id_enquete', $idEnquete, PDO::PARAM_INT);
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return false; // Já votou
    }
    
    try {
        $pdo->beginTransaction();
        
        // Incrementa votos
        $stmt = $pdo->prepare("UPDATE enquetes_opcoes SET votos = votos + 1 WHERE id = :id");
        $stmt->bindValue(':id', $idOpcao, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registra voto
        $stmt = $pdo->prepare("INSERT INTO enquetes_votos (id_enquete, ip_address, user_agent) VALUES (:id_enquete, :ip, :ua)");
        $stmt->bindValue(':id_enquete', $idEnquete, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':ua', $userAgent, PDO::PARAM_STR);
        $stmt->execute();
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Verifica se precisa atualizar notícias automaticamente
 * @return bool
 */
function precisaAtualizarNoticias(): bool {
    $config = getSiteConfig();
    
    if (empty($config['last_update_check'])) {
        return true;
    }
    
    $lastUpdate = new DateTime($config['last_update_check']);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastUpdate->getTimestamp();
    
    // 60 minutos = 3600 segundos
    return $diff > 3600;
}

/**
 * Atualiza timestamp da última verificação
 * @return void
 */
function atualizarTimestampVerificacao(): void {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE configuracoes SET last_update_check = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute();
}

/**
 * Simula captura de notícias via RSS/API
 * Esta função pode ser expandida para integrar com APIs reais
 * @return int Número de notícias adicionadas
 */
function capturarNoticiasAutomatico(): int {
    $pdo = getDbConnection();
    $categorias = array_keys(getCategories());
    
    // Notícias de exemplo para demonstração
    $noticiasExemplo = [
        [
            'titulo' => 'Brasil vence Argentina em clássico emocionante',
            'categoria' => 'esportes-futebol-nacional',
            'conteudo' => 'Em partida válida pelas eliminatórias, seleção brasileira derrota a argentina por 2x1...',
        ],
        [
            'titulo' => 'Champions League: Real Madrid avança às semifinais',
            'categoria' => 'esportes-futebol-internacional',
            'conteudo' => 'Real Madrid garante vaga nas semifinais da Champions League após vitória...',
        ],
        [
            'titulo' => 'Congresso aprova nova lei de infraestrutura',
            'categoria' => 'politica-nacional',
            'conteudo' => 'Após longas discussões, congresso nacional aprova projeto de lei...',
        ],
        [
            'titulo' => 'Tensões aumentam no Oriente Médio',
            'categoria' => 'atualidades-guerra',
            'conteudo' => 'Conflitos na região continuam a preocupar comunidade internacional...',
        ],
        [
            'titulo' => 'Evento gospel reúne milhares em São Paulo',
            'categoria' => 'gospel',
            'conteudo' => 'Maior evento cristão da América Latina acontece neste fim de semana...',
        ]
    ];
    
    $contador = 0;
    
    foreach ($noticiasExemplo as $noticia) {
        // Verifica se já existe notícia similar
        $slug = generateSlug($noticia['titulo']);
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM noticias WHERE slug = :slug");
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO noticias (titulo, slug, conteudo, categoria, imagem, data_publicacao) 
                VALUES (:titulo, :slug, :conteudo, :categoria, :imagem, datetime('now'))
            ");
            $stmt->bindValue(':titulo', $noticia['titulo'], PDO::PARAM_STR);
            $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
            $stmt->bindValue(':conteudo', $noticia['conteudo'], PDO::PARAM_STR);
            $stmt->bindValue(':categoria', $noticia['categoria'], PDO::PARAM_STR);
            $stmt->bindValue(':imagem', 'assets/news-placeholder.jpg', PDO::PARAM_STR);
            $stmt->execute();
            $contador++;
        }
    }
    
    atualizarTimestampVerificacao();
    return $contador;
}

/**
 * Formata data para exibição
 * @param string $data
 * @return string
 */
function formatarData(string $data): string {
    $datetime = new DateTime($data);
    return $datetime->format('d/m/Y H:i');
}

/**
 * Extrai resumo do conteúdo
 * @param string $conteudo
 * @param int $maxChars
 * @return string
 */
function extrairResumo(string $conteudo, int $maxChars = 200): string {
    $texto = strip_tags($conteudo);
    if (mb_strlen($texto) <= $maxChars) {
        return $texto;
    }
    return mb_substr($texto, 0, $maxChars) . '...';
}
