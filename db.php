<?php
/**
 * db.php - Conexão PDO com SQLite3
 * Portal de Notícias SEO Pro + Enquetes
 */

// Configurações do banco de dados
define('DB_PATH', __DIR__ . '/database.db');

/**
 * Obtém a conexão PDO com o SQLite
 * @return PDO
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Habilita foreign keys
            $pdo->exec('PRAGMA foreign_keys = ON');
            
        } catch (PDOException $e) {
            die('Erro na conexão com o banco de dados: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Inicializa o banco de dados criando as tabelas se não existirem
 * @return void
 */
function initializeDatabase(): void {
    $pdo = getDbConnection();
    
    $queries = [
        // Tabela de notícias
        "CREATE TABLE IF NOT EXISTS noticias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            conteudo TEXT NOT NULL,
            imagem TEXT,
            categoria TEXT NOT NULL,
            data_publicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Tabela de enquetes
        "CREATE TABLE IF NOT EXISTS enquetes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pergunta TEXT NOT NULL,
            status TEXT DEFAULT 'ativa' CHECK(status IN ('ativa', 'inativa')),
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Tabela de opções das enquetes
        "CREATE TABLE IF NOT EXISTS enquetes_opcoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_enquete INTEGER NOT NULL,
            texto_opcao TEXT NOT NULL,
            votos INTEGER DEFAULT 0,
            FOREIGN KEY (id_enquete) REFERENCES enquetes(id) ON DELETE CASCADE
        )",
        
        // Tabela de configurações
        "CREATE TABLE IF NOT EXISTS configuracoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            logotipo_path TEXT,
            titulo_site TEXT DEFAULT 'Portal de Notícias',
            texto_rodape TEXT DEFAULT '© 2024 Portal de Notícias. Todos os direitos reservados.',
            script_ads_lateral TEXT,
            last_update_check DATETIME,
            UNIQUE(id)
        )",
        
        // Tabela para armazenar sessões de voto em enquetes (evitar votos múltiplos)
        "CREATE TABLE IF NOT EXISTS enquetes_votos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_enquete INTEGER NOT NULL,
            ip_address TEXT NOT NULL,
            user_agent TEXT,
            data_voto DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_enquete) REFERENCES enquetes(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($queries as $query) {
        $pdo->exec($query);
    }
    
    // Inserir configuração padrão se não existir
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM configuracoes");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $pdo->exec("INSERT INTO configuracoes (titulo_site, texto_rodape) 
                    VALUES ('Portal de Notícias SEO Pro', '© 2024 Portal de Notícias. Todos os direitos reservados.')");
    }
}

/**
 * Gera um slug URL-friendly a partir de um título
 * @param string $title
 * @return string
 */
function generateSlug(string $title): string {
    $slug = mb_strtolower(trim($title), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Inicializa o banco ao carregar este arquivo
initializeDatabase();
