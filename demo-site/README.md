# Demonstração Beta do PRIMEWay School

Esta pasta contém uma versão estática e independente para apresentação pública do projeto.

## O que contém
- Escolha de cinco perfis demonstrativos: Administrador, Secretaria, Professor, Aluno e Responsável.
- Painéis com nomes, indicadores e registros inteiramente fictícios.
- Navegação local no navegador e tempo de demonstração de 30 minutos.
- Nenhuma autenticação, chamada à API PHP ou conexão com banco de dados.
- Nenhuma operação de escrita: mensagens, cadastros e ações sensíveis são apenas exemplos visuais ou ficam desativados.

## Publicação gratuita pelo GitHub Pages
O workflow `.github/workflows/deploy-demo-pages.yml` publica somente esta pasta (`demo-site/`). Ele não publica a pasta `public/` inteira, pois a aplicação principal contém endpoints PHP que não devem ser expostos como arquivos estáticos.

Para habilitar a primeira publicação, acesse **Settings → Pages** no repositório e, em **Build and deployment → Source**, selecione **GitHub Actions**. Confirme também em **Actions → General** que workflows estão habilitados. Depois disso, a publicação será feita pelo workflow nas próximas atualizações da pasta. O endereço esperado do projeto é:

`https://pdrarcelinoo.github.io/primeway-school/`

O endereço só estará disponível depois que a publicação do workflow terminar com sucesso e o GitHub Pages estiver habilitado.

## Limitações importantes
Esta experiência é uma vitrine navegável, não uma cópia funcional conectada ao sistema real. Os dados são exemplos fictícios e não refletem a base de produção. A contagem de 30 minutos é um limite visual no navegador, não um controle de sessão de servidor, pois a demonstração não possui backend.

## Segurança e custo
- Não utiliza Railway, MySQL, serviços externos de backend ou variáveis secretas.
- O workflow publica somente HTML, CSS e JavaScript estáticos.
- Nenhum arquivo PHP, configuração ou credencial da aplicação é incluído no artefato publicado.
- GitHub Pages está sujeito aos limites e termos de uso do GitHub para repositórios públicos.
