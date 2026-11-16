# Segurança, privacidade e limites da demonstração

O PRIMEWay School envolve informações escolares e recursos de geolocalização. Por esse motivo, a publicação de código ou a demonstração de telas exige cuidados adicionais.

## Acesso e autorização

O projeto possui verificações de perfis e proteções CSRF nos endpoints de Saída Segura examinados. Essas verificações são importantes, mas não equivalem a uma auditoria de segurança completa.

## Geolocalização

O módulo utiliza a geolocalização fornecida pelo navegador com permissão do responsável e calcula a distância até o ponto de referência configurado. O endpoint examinado não grava as coordenadas recebidas no MySQL, mas uma avaliação completa de privacidade também precisaria considerar logs, fornecedores de mapas, telemetria, navegador e políticas de retenção.

GPS não é mecanismo suficiente para verificar identidade e autorização de retirada. A liberação final requer verificação pela equipe responsável e procedimentos presenciais definidos pela instituição.

## Portfólio e publicação

- Não incluir dados reais de estudantes, responsáveis, funcionários ou conversas.
- Não divulgar senhas de demonstração, tokens, chaves, variáveis de produção ou backups.
- Não publicar capturas com dados pessoais ou informações de acesso.
- Não transportar o histórico completo do Git privado sem revisar se já houve exposição de segredos.
- Não assumir que arquivos ignorados por `.gitignore` estejam ausentes de commits anteriores.
- Confirmar autoria, participação de terceiros e condições de distribuição antes de tornar público o código.
- Definir claramente a licença: o `composer.json` original usa a indicação `proprietary`.

## Declaração de uso

Esta documentação se refere a um projeto acadêmico e a um ambiente de demonstração. Não representa certificação de segurança nem autorização para utilizar o sistema em uma escola real sem validações técnicas, jurídicas e operacionais adicionais.
