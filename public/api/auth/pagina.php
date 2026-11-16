<?php
declare(strict_types=1);

// Páginas internas: a autorização acontece no servidor, nunca no sessionStorage.
// O Apache reescreve /pages/*.html para este controlador privado da API.
require_once '/opt/primeway/app/api/_bootstrap.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if ($metodo !== 'GET' && $metodo !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$caminho = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!is_string($caminho) || preg_match('#^/pages/([a-z][a-z0-9_-]*)\.html$#D', $caminho, $partes) !== 1) {
    http_response_code(404);
    exit;
}
$pagina = $partes[1];

// As páginas administrativas não devem reaparecer nos perfis da escola.
// Novas páginas ficam bloqueadas até receberem uma classificação explícita.
$admin = [
    'dashboard', 'professores', 'alunos', 'responsaveis',
    'turmas', 'disciplinas', 'calendario', 'notificacoes',
    'admin_chat', 'chat-professor', 'configuracoes', 'administradores_tcc'
];
if ($pagina === 'chat' || $pagina === 'perfil' || $pagina === 'preferencias' || $pagina === 'privacidade') {
    // Alias compartilhado: nunca servir uma página de outro perfil.
    $perfilNecessario = null;
} elseif (in_array($pagina, $admin, true)) {
    $perfilNecessario = 'admin';
} elseif ($pagina === 'professor' || str_starts_with($pagina, 'professor_')) {
    $perfilNecessario = 'professor';
} elseif ($pagina === 'aluno_portal' || str_starts_with($pagina, 'aluno_')) {
    $perfilNecessario = 'aluno';
} elseif ($pagina === 'responsavel' || str_starts_with($pagina, 'responsavel_')) {
    $perfilNecessario = 'responsavel';
} elseif ($pagina === 'secretaria' || str_starts_with($pagina, 'secretaria_')) {
    $perfilNecessario = 'secretaria';
} else {
    http_response_code(404);
    exit;
}

$usuario = primewayUsuarioAtualSessao();
if ($usuario === null) {
    header('Location: /pages/login.html', true, 302);
    header('Cache-Control: no-store, private');
    exit;
}

$perfilAtual = (string)$usuario['perfil'];
if ($pagina === 'chat') {
    // Roteamento por perfil validado no banco, não por estado do navegador.
    $destinos = [
        'admin' => 'admin_chat.html',
        'professor' => 'professor_chat.html',
        'aluno' => 'aluno_chat.html',
        'responsavel' => 'responsavel_chat.html',
        'secretaria' => 'secretaria_chat.html'
    ];
    $destino = $destinos[$perfilAtual] ?? 'login.html';
    header('Cache-Control: no-store, private');
    header('Location: /pages/' . $destino, true, 302);
    exit;
}
if ($pagina === 'chat-professor' && $perfilAtual === 'admin') {
    // Compatibilidade com favoritos antigos, sem nomes de Professor no Admin.
    header('Cache-Control: no-store, private');
    header('Location: /pages/admin_chat.html', true, 302);
    exit;
}
if ($pagina === 'perfil' || $pagina === 'preferencias' || $pagina === 'privacidade') {
    // Páginas pessoais compartilhadas, sempre autenticadas no servidor.
    $perfilNecessario = $perfilAtual;
}
if ($perfilAtual !== $perfilNecessario) {
    $inicio = [
        'admin' => 'dashboard.html',
        'professor' => 'professor.html',
        'aluno' => 'aluno_portal.html',
        'responsavel' => 'responsavel.html',
        'secretaria' => 'secretaria.html'
    ][$perfilAtual] ?? 'login.html';
    header('Location: /pages/' . $inicio, true, 302);
    header('Cache-Control: no-store, private');
    exit;
}

$diretorioPaginas = realpath(dirname(__DIR__, 2) . '/pages');
$arquivo = realpath(dirname(__DIR__, 2) . '/pages/' . $pagina . '.html');
if ($diretorioPaginas === false || $arquivo === false ||
    dirname($arquivo) !== $diretorioPaginas || !is_file($arquivo)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Vary: Cookie');
if ($metodo !== 'HEAD') {
    // O ícone oficial do PrimeWay aparece nas abas de todas as páginas internas
    // protegidas, sem precisar alterar a autorização ou duplicar 50 páginas.
    $html = file_get_contents($arquivo);
    if ($html === false) {
        http_response_code(500);
        exit;
    }
    // Tema vinculado ao usuário autenticado (nunca usa a conta de outra sessão).
    // Script executado logo após <head> evita piscar em modo claro ao navegar.
    $html = preg_replace(
        '~<html\\b~i',
        '<html data-primeway-theme-account="' . (int)$usuario['id'] .
            '" data-primeway-theme-role="' . htmlspecialchars($perfilAtual, ENT_QUOTES, 'UTF-8') . '"',
        $html,
        1
    ) ?? $html;
    $html = preg_replace_callback(
        '~<head\\b[^>]*>~i',
        static fn (array $match): string => $match[0] .
            "\n<script src=\"../js/modo_noturno.js?v=20261110-dark1\"></script>",
        $html,
        1
    ) ?? $html;
    $icone = '<link rel="icon" type="image/png" href="/img/logo.png?v=20261026">';
    if (stripos($html, 'rel="icon"') === false) {
        $html = preg_replace('~</head>~i', "    " . $icone . "\n</head>", $html, 1) ?? $html;
    }
    // Invalida a versão antiga do JS global em todas as páginas protegidas.
    // A mesma injeção inclui o link Perfil, mesmo em páginas legadas.
    $html = preg_replace('~(\\.\\./js/core\\.js)(?:\\?v=[\\w.-]+)?~', '$1?v=20261027-perfil', $html) ?? $html;
    // Camada visual leve, compartilhada entre perfis e restrita às páginas autenticadas.
    // Sem alterar HTML das páginas, rotas, funcionalidades ou permissões.
    if (stripos($html, 'visual_polish.css') === false) {
        $visualCss = '<link rel="stylesheet" href="../css/visual_polish.css?v=20261103-2">';
        $html = preg_replace('~</head>~i', $visualCss."\n</head>", $html, 1) ?? $html;
    }
    // Atualização de avisos na navegação sem duplicar o HTML de mais de 50 páginas.
    $html = preg_replace('~(\.\./js/(?:aluno|responsavel|professor)_common\.js)(?:\?v=[\w.-]+)?~', '$1?v=20261103-avisos1', $html) ?? $html;
    $html = preg_replace('~(\.\./js/secretaria_common\.js)(?:\?v=[\w.-]+)?~', '$1?v=20261110-gestao1', $html) ?? $html;
    if (stripos($html, 'avisos_inteligentes.js') === false) {
        $css = '<link rel="stylesheet" href="../css/avisos_inteligentes.css?v=20261103-1">';
        $script = '<script src="../js/avisos_inteligentes.js?v=20261103-1"></script>';
        $html = preg_replace('~</head>~i', $css."\n</head>", $html, 1) ?? $html;
        $html = preg_replace('~</body>~i', $script."\n</body>", $html, 1) ?? $html;
    }
    // Camada escura aplica-se por opt-in; nunca altera o tema claro original.
    $themeCss = '<link rel="stylesheet" href="../css/modo_noturno.css?v=20261110-dark2">';
    $html = preg_replace('~</head>~i', $themeCss . "\n</head>", $html, 1) ?? $html;
    // Acessibilidade por conta, carregada no head para evitar mudança visual tardia.
    $accessScript = '<script src="../js/acessibilidade.js?v=20261110-access1"></script>';
    $html = preg_replace('~</head>~i', $accessScript."\n</head>", $html, 1) ?? $html;
    $extraStyle = '<link rel="stylesheet" href="../css/configuracoes_extras.css?v=20261110-settings3">';
    $accessStyle = '<link rel="stylesheet" href="../css/acessibilidade.css?v=20261110-access1">';
    $noticeStyle = '<link rel="stylesheet" href="../css/portal_global.css?v=20261110-portal1">';
    $html = preg_replace('~</head>~i', $extraStyle."\n".$accessStyle."\n".$noticeStyle."\n</head>", $html, 1) ?? $html;
    // Padronização tipográfica de TODOS os módulos autenticados, sempre
    // após CSS de tema/acessibilidade; sem alterar HTML, scripts ou permissões.
    $typographyCss = '<link rel="stylesheet" href="../css/tipografia_global.css?v=20261110-1">';
    if (stripos($html, 'tipografia_global.css') === false) {
        $html = preg_replace('~</head>~i', $typographyCss."\n</head>", $html, 1) ?? $html;
    }
    $settingsScript = '<script src="../js/configuracoes_extras.js?v=20261110-settings2"></script>';
    $noticeScript = '<script src="../js/portal_global.js?v=20261110-portal1"></script>';
    $html = preg_replace('~</body>~i', $settingsScript."\n".$noticeScript."\n</body>", $html, 1) ?? $html;
    // As páginas pessoais compartilham a MESMA navegação do painel de seu perfil.
    // A origem é uma página interna conhecida e aprovada pelo servidor, nunca uma URL
    // ou um papel fornecido pelo navegador. Os menus seguem as mesmas permissões.
    if (in_array($pagina, ['perfil', 'preferencias', 'privacidade'], true)) {
        $homes = [
            'admin' => 'dashboard', 'professor' => 'professor',
            'aluno' => 'aluno_portal', 'responsavel' => 'responsavel',
            'secretaria' => 'secretaria'
        ];
        $roleStyles = [
            'admin' => 'dashboard', 'professor' => 'professor_shared',
            'aluno' => 'aluno_shared', 'responsavel' => 'responsavel_shared',
            'secretaria' => 'secretaria_shared'
        ];
        $commonScripts = [
            'professor' => 'professor_common', 'secretaria' => 'secretaria_common',
            'aluno' => 'aluno_common', 'responsavel' => 'responsavel_common'
        ];
        $homeFile = $diretorioPaginas . '/' . ($homes[$perfilAtual] ?? '') . '.html';
        $homeHtml = is_file($homeFile) ? file_get_contents($homeFile) : false;
        $sidePattern = '~<aside\b[^>]*\bclass\s*=\s*"[^"]*\bsidebar\b[^"]*"[^>]*>.*?</aside>~si';
        if (is_string($homeHtml) && preg_match($sidePattern, $homeHtml, $sidebarMatch) === 1) {
            $canonicalSidebar = $sidebarMatch[0];
            // O item ativo na página original não deve continuar ativo no Perfil.
            $canonicalSidebar = preg_replace('~(\s)class="active"~i', '$1', $canonicalSidebar) ?? $canonicalSidebar;
            $canonicalSidebar = preg_replace('~\saria-current="page"~i', '', $canonicalSidebar) ?? $canonicalSidebar;
            $html = preg_replace_callback($sidePattern,
                static fn(array $match): string => $canonicalSidebar, $html, 1) ?? $html;
        }
        $roleCss = '../css/' . ($roleStyles[$perfilAtual] ?? 'components') . '.css';
        $roleLink = '<link rel="stylesheet" href="' . $roleCss . '?v=20261110-nav1">';
        $html = preg_replace(
            '~(<link\b[^>]*href="../css/components\.css"[^>]*>)~i',
            '$1' . "\n" . $roleLink, $html, 1
        ) ?? $html;
        if (isset($commonScripts[$perfilAtual])) {
            $common = '<script src="../js/' . $commonScripts[$perfilAtual] .
                '.js?v=20261110-nav1"></script>';
            $html = preg_replace('~</body>~i', $common . "\n</body>", $html, 1) ?? $html;
        }
        $navigation = '<script src="../js/navegacao_pessoal.js?v=20261110-nav1"></script>';
        $html = preg_replace('~</body>~i', $navigation . "\n</body>", $html, 1) ?? $html;
    }
    echo $html;
}
