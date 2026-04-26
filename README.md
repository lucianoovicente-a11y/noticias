# Portal de Notícias SEO Pro + Enquetes

Sistema completo de portal de notícias desenvolvido em **PHP 8.x Puro** e **SQLite3**, focado em performance, SEO e facilidade de implantação.

## 🚀 Características Principais

- **PHP Vanilla**: Sem dependências de frameworks externos
- **SQLite3**: Banco de dados em arquivo único, sem necessidade de configuração
- **SEO Otimizado**: URLs amigáveis, Schema.org, Open Graph, Twitter Cards
- **Layout Responsivo**: CSS Grid moderno
- **Sistema de Enquetes**: Engajamento com votos em tempo real
- **Auto-Update**: Atualização automática de notícias a cada 60 minutos
- **Painel Administrativo**: CRUD completo para notícias, enquetes e configurações

## 📁 Estrutura de Arquivos

```
/
├── index.php          # Front-end do portal
├── admin.php          # Painel administrativo
├── db.php             # Conexão PDO com SQLite
├── functions.php      # Funções auxiliares e lógica
├── style.css          # Estilos CSS
├── .htaccess          # URL rewriting para SEO
├── database.db        # Banco de dados SQLite (gerado automaticamente)
└── uploads/           # Pasta para uploads (logotipo)
```

## 📋 Categorias Implementadas

- **Gerais**
- **Nacionais** e **Internacionais**
- **Esportes**: Futebol Nacional e Internacional
- **Política**: Nacional e Internacional
- **Gospel**
- **Atualidades/Guerra**

## 🔧 Instalação

### Requisitos Mínimos
- PHP 8.0 ou superior
- Extensão PDO SQLite habilitada
- Servidor Apache com mod_rewrite (para URLs amigáveis)

### Passos

1. **Clone ou copie os arquivos** para seu servidor:
   ```bash
   cp -r /workspace/* /var/www/html/portal/
   ```

2. **Permissões** (garanta que o PHP possa gravar):
   ```bash
   chmod 755 /var/www/html/portal/
   chmod 644 /var/www/html/portal/*.php
   chmod 644 /var/www/html/portal/style.css
   mkdir /var/www/html/portal/uploads
   chmod 755 /var/www/html/portal/uploads
   ```

3. **Acesse o site**:
   - Front-end: `http://seusite.com/`
   - Admin: `http://seusite.com/admin.php`

4. **Login padrão**:
   - Usuário: `admin`
   - Senha: `admin123`

   ⚠️ **Importante**: Altere as credenciais em `admin.php` (linhas 13-14) antes de colocar em produção!

## 🎯 Funcionalidades

### Front-End (index.php)
- Grade de notícias com destaque principal
- Filtro por categorias
- Página individual de notícia com SEO completo
- Widget de enquetes com votação
- Sidebar de publicidade configurável
- Rodapé editável

### Painel Administrativo (admin.php)

#### Gestão de Notícias
- Criar, editar e excluir notícias
- Geração automática de slugs SEO-friendly
- Upload de imagens via URL
- Botão "Atualizar Notícias" para captura automática

#### Sistema de Enquetes
- Criar perguntas com múltiplas opções
- Apenas uma enquete ativa por vez
- Monitoramento de votos em tempo real
- Prevenção de votos duplicados por IP

#### Personalização
- Upload de logotipo
- Edição do título do site
- Texto do rodapé personalizável
- Script de publicidade lateral (Google AdSense, etc.)

## 🔒 Segurança

- **PDO Prepared Statements**: Proteção contra SQL Injection
- **htmlspecialchars()**: Proteção contra XSS
- **Validação de Input**: Filtros para dados de formulário
- **Controle de Sessão**: Autenticação no admin
- **Proteção do Banco**: .htaccess bloqueia acesso direto ao database.db

## 📊 SEO de Elite

Cada página de notícia inclui automaticamente:

```html
<!-- Meta Tags -->
<title>Título da Notícia | Nome do Site</title>
<meta name="description" content="Resumo da notícia...">
<link rel="canonical" href="https://site.com/noticia/slug">

<!-- Open Graph -->
<meta property="og:type" content="article">
<meta property="og:title" content="...">
<meta property="og:image" content="...">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">

<!-- Schema.org JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "NewsArticle",
  "headline": "...",
  ...
}
</script>
```

## 🔄 Auto-Update

O sistema verifica automaticamente se há mais de 60 minutos desde a última atualização e executa a função `capturarNoticiasAutomatico()` para adicionar novas notícias.

Para atualizar manualmente, use o botão **"🔄 Atualizar Notícias"** no painel administrativo.

## 🎨 Personalização

### Cores (style.css)
Edite as variáveis CSS no início do arquivo:

```css
:root {
    --primary-color: #1a5f7a;
    --secondary-color: #c0392b;
    --accent-color: #e67e22;
    /* ... */
}
```

### Categorias
Adicione ou remova categorias em `functions.php`:

```php
function getCategories(): array {
    return [
        'nova-categoria' => 'Nome da Categoria',
        // ...
    ];
}
```

## 📝 Notas Importantes

1. **Produção**: 
   - Altere as credenciais de admin
   - Habilite HTTPS
   - Configure backup automático do `database.db`

2. **APIs Reais**: 
   - A função `capturarNoticiasAutomatico()` atualmente usa dados de exemplo
   - Para produção, integre com APIs como NewsAPI, RSS feeds, etc.

3. **Performance**:
   - SQLite é ideal para sites de pequeno a médio porte
   - Para alto tráfego, considere migrar para MySQL/PostgreSQL

## 🐛 Troubleshooting

### Erro "Database locked"
Verifique as permissões do arquivo `database.db` e da pasta.

### URLs não funcionam
Certifique-se de que o mod_rewrite está habilitado no Apache:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Imagens não aparecem
Verifique se as URLs das imagens são acessíveis publicamente.

## 📄 Licença

Este projeto é fornecido "como está" para uso livre.

---

**Desenvolvido com ❤️ usando PHP Puro e SQLite**
