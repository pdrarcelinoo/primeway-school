# Requisitos e instalação — documentação preliminar

## Estado desta seção

A instalação independente em um repositório público **ainda não foi validada**. A estrutura atual foi concebida para o ambiente privado de demonstração e não deve ser interpretada como um pacote pronto para execução por terceiros.

## Requisitos identificados na versão atual

- Servidor web Apache com PHP. O contêiner existente usa **PHP 8.3**; o `composer.json` declara requisito de **PHP 8.2 ou superior**.
- Extensões PHP para JSON, PDO e PDO MySQL; a imagem também configura `mbstring` e `opcache`.
- Banco de dados **MySQL 8** conforme os pré-requisitos do ambiente documentado.
- Configuração de armazenamento privado de arquivos e sessões.
- Variáveis de ambiente para conexão com banco, sessão e configurações de execução.
- Estrutura SQL e migrações correspondentes à versão da aplicação.

## Configurações de referência (sem valores reais)

```dotenv
PRIMEWAY_DB_HOST=SEU_HOST_PRIVADO
PRIMEWAY_DB_PORT=3306
PRIMEWAY_DB_NAME=SEU_BANCO
PRIMEWAY_DB_USER=SEU_USUARIO_DE_APLICACAO
PRIMEWAY_DB_PASS=DEFINIR_APENAS_NO_AMBIENTE_PRIVADO
PRIMEWAY_APP_ENV=production
```

Os valores acima são **marcadores ilustrativos**, não credenciais. Nunca faça commit de configurações preenchidas.

## Antes de publicar um tutorial executável

1. Preparar uma cópia sanitizada do código sem dados privados ou histórico de commits sensível.
2. Definir um conjunto mínimo de pré-requisitos e variáveis de exemplo.
3. Validar a instalação completa em um banco de testes vazio e independente.
4. Documentar como aplicar a estrutura SQL/migrações na ordem correta.
5. Verificar autenticação, permissões, persistência de arquivos e proteção das rotas.
6. Validar o funcionamento em ambiente local de demonstração e registrar os comandos exatos.

**Não use o banco da Railway como ambiente de testes de instalação.** Esta documentação não instrui a copiar credenciais do ambiente atual nem a alterar sua infraestrutura.
