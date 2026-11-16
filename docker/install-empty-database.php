<?php
declare(strict_types=1);

/* Instalação única: somente no banco Railway confirmado, MySQL 8.x e vazio. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este instalador só pode ser executado por CLI.\n");
    exit(1);
}
if (getenv('PRIMEWAY_EMPTY_DATABASE_CONFIRM') !== 'INSTALL_EMPTY_RAILWAY_DB') {
    fwrite(STDERR, "Defina PRIMEWAY_EMPTY_DATABASE_CONFIRM=INSTALL_EMPTY_RAILWAY_DB para confirmar a instalação inicial.\n");
    exit(2);
}

$app = '/opt/primeway/app';
require_once $app . '/config/database.php';
require_once $app . '/database/export_schema.php';

function splitPrimewaySql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $character = $sql[$i];
        if ($quote !== null) {
            $buffer .= $character;
            if ($character === '\\' && $quote !== chr(96) && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }
            if ($character === $quote) {
                if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                    $buffer .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }
            continue;
        }
        if ($character === "'" || $character === '"' || $character === chr(96)) {
            $quote = $character;
            $buffer .= $character;
        } elseif ($character === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        } else {
            $buffer .= $character;
        }
    }
    if ($quote !== null) {
        throw new RuntimeException("SQL exportado contém uma string não terminada.");
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function primewayInstallerPdo(array $config, string $user, string $password): PDO
{
    $config['user'] = $user;
    $config['password'] = $password;
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        primewayDatabaseOptions($config)
    );
    primewayDatabaseRequireTls($pdo, $config);
    return $pdo;
}

function primewayInstallerCurrentUser(PDO $pdo): string
{
    return (string)$pdo->query('SELECT CURRENT_USER()')->fetchColumn();
}

try {
    $config = primewayDatabaseConfig();
    if ((string)$config['database'] !== 'railway') {
        throw new RuntimeException("Instalação bloqueada: o banco deve ser exatamente 'railway'.");
    }

    $appUser = trim((string)$config['user']);
    if ($appUser !== 'primeway_app' || strtolower($appUser) === 'root') {
        throw new RuntimeException("A aplicação deve autenticar como primeway_app; root é proibido.");
    }
    if ((string)$config['password'] === '') {
        throw new RuntimeException("Senha da conta de aplicação ausente.");
    }

    $migrationUser = trim((string)(getenv('PRIMEWAY_MIGRATION_DB_USER') ?: ''));
    $migrationPassword = (string)(getenv('PRIMEWAY_MIGRATION_DB_PASS') ?: '');
    if ($migrationUser !== 'primeway_migrate' || strtolower($migrationUser) === 'root'
        || $migrationUser === $appUser || $migrationPassword === '') {
        throw new RuntimeException("Defina uma conta temporária primeway_migrate distinta da aplicação e de root.");
    }

    $confirmedVersion = trim((string)(getenv('PRIMEWAY_DB_CONFIRMED_VERSION') ?: ''));
    if ($confirmedVersion === '' || !preg_match('/^8\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$/', $confirmedVersion)) {
        throw new RuntimeException("Defina PRIMEWAY_DB_CONFIRMED_VERSION com a saída exata e previamente confirmada de SELECT VERSION().");
    }

    /* Confirma que a credencial de runtime funciona antes de executar DDL. */
    $appPdo = primewayInstallerPdo($config, $appUser, (string)$config['password']);
    if (stripos(primewayInstallerCurrentUser($appPdo), 'primeway_app@') !== 0
        || (string)$appPdo->query('SELECT DATABASE()')->fetchColumn() !== 'railway') {
        throw new RuntimeException("A conexão de runtime não corresponde ao usuário/banco esperados.");
    }

    /* DDL usa a conta temporária limitada ao banco railway.*. */
    $pdo = primewayInstallerPdo($config, $migrationUser, $migrationPassword);
    if (stripos(primewayInstallerCurrentUser($pdo), 'primeway_migrate@') !== 0
        || (string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'railway') {
        throw new RuntimeException("A conexão de migrations não corresponde ao usuário/banco esperados.");
    }

    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $versionComment = (string)$pdo->query('SELECT @@version_comment')->fetchColumn();
    if (!hash_equals($confirmedVersion, $version)) {
        throw new RuntimeException("A versão em execução diverge da versão confirmada previamente; nenhuma alteração foi feita.");
    }
    if (!preg_match('/^8\.(\d+)\.(\d+)/', $version, $versionParts)
        || ((int)$versionParts[1] === 0 && (int)$versionParts[2] < 16)
        || !preg_match('/^MySQL\b/i', $versionComment)) {
        throw new RuntimeException("MySQL 8.0.16 ou posterior é obrigatório para validar as restrições CHECK; serviço detectado: " . $version . " (" . $versionComment . ").");
    }

    $tableCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();
    if ($tableCount !== 0) {
        throw new RuntimeException("O banco railway já contém tabelas/views. Nenhuma alteração foi feita.");
    }

    $schema = primewayProductionSchema();
    if (preg_match('/\b(?:INSERT\s+INTO|REPLACE\s+INTO)\s+[\x60"]?(usuarios|users|contas|accounts)\b/i', $schema)
        || preg_match('/admin@primeway\.com|\$2y\$/i', $schema)) {
        throw new RuntimeException("Exportação contém contas de desenvolvimento; instalação bloqueada.");
    }

    $migrations = glob($app . '/database/migrations/*.sql') ?: [];
    if ($migrations === []) {
        throw new RuntimeException("Migrations oficiais ausentes.");
    }
    foreach ($migrations as $migration) {
        $contents = file_get_contents($migration);
        if (!is_string($contents)
            || preg_match('/\b(?:INSERT\s+INTO|REPLACE\s+INTO)\s+[\x60"]?(usuarios|users|contas|accounts)\b/i', $contents)) {
            throw new RuntimeException("Migration pode inserir contas; instalação bloqueada.");
        }
    }

    $statements = splitPrimewaySql($schema);
    if ($statements === []) {
        throw new RuntimeException("Exportador não produziu esquema.");
    }
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $countUsers = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($countUsers !== 0) {
        throw new RuntimeException("O esquema inicializou usuários inesperados; revisar manualmente.");
    }

    echo "Esquema vazio instalado. Aplicando migrations oficiais com primeway_migrate...\n";
    require $app . '/database/migrate.php';

    $countUsers = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($countUsers !== 0) {
        throw new RuntimeException("Migrations criaram contas; revisar manualmente.");
    }
    echo "Instalação concluída. Nenhuma conta foi criada. Remova a conta/segredos temporários após validar.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Instalação interrompida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}