<?php
/**
 * router.php - Router para PHP Built-in Server
 * Portal de Notícias SEO Pro + Enquetes
 */

// Roteia todas as requisições para index.php, exceto arquivos estáticos
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Verifica se é um arquivo estático existente
if ($requestUri !== '/' && file_exists(__DIR__ . $requestUri) && is_file(__DIR__ . $requestUri)) {
    // Serve o arquivo estático diretamente
    return false;
}

// Roteia para index.php
require __DIR__ . '/index.php';
