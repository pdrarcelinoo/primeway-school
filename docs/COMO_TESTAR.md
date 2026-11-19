# Como testar o PRIMEWay School

Este guia foi escrito para recrutadores, avaliadores e profissionais interessados em conhecer o projeto.

## 1. Acesso

**Endereço:** https://primeway-web-production.up.railway.app/pages/login.html

O sistema exige e-mail e senha de uma conta válida. **Ainda não existe um login público verificado para divulgação.** Solicite ao responsável pelo projeto uma **conta de demonstração fictícia** por https://github.com/pdrarcelinoo/primeway-school/issues/new (se as Issues estiverem habilitadas) ou pelo contato profissional indicado em https://github.com/pdrarcelinoo. A senha deve ser enviada por um canal privado, **não** por uma Issue pública do GitHub.

Quando receber a conta, entre no endereço acima, informe as credenciais e siga os cenários correspondentes ao perfil disponibilizado. O acesso a um perfil não concede permissão para os demais.

## 2. Roteiro de avaliação

| Área | O que observar | Como verificar |
| --- | --- | --- |
| Login | Autenticação, mensagens de erro e saída da sessão | Acesse com a conta fictícia; termine usando a opção de sair |
| Interface | Legibilidade, responsividade, consistência dos menus e telas | Navegue pelo painel em desktop e celular |
| Perfil do usuário | Dados apresentados, opções de edição permitidas e navegação | Abra a área de perfil com a conta de teste |
| Acadêmico | Turmas, disciplinas, atividades, notas e frequência | Consulte as áreas permitidas; use somente registros fictícios |
| Calendário e notificações | Organização dos eventos, avisos e acompanhamento | Consulte eventos e notificações de demonstração |
| Chat | Clareza das mensagens e recursos disponíveis para o perfil | Teste apenas com outra conta fictícia autorizada |
| Permissões | Separação entre Aluno, Professor, Responsável, Secretaria e Administrador | Compare perfis somente se receber credenciais específicas para cada um |
| Saída Segura | Solicitação, estados da retirada, notificações e confirmação pela equipe | Siga o roteiro específico abaixo; não force a localização |

Não realize exclusões em massa, testes de carga, tentativas de acesso indevido ou ações com dados que não sejam de demonstração.

## 3. Saída Segura com GPS

**Objetivo:** organizar a retirada de um aluno por um responsável autorizado e registrar a liberação pela escola.

### Fluxo a observar

1. **Responsável:** inicia uma solicitação para estudante ao qual esteja vinculado e autorizado.
2. **Sistema:** registra a solicitação com status `Aguardando`.
3. **GPS:** após autorização do navegador, a proximidade com o ponto configurado é verificada. Ao entrar no raio, o status muda para `No raio` e a equipe é avisada.
4. **Secretaria:** a equipe pode confirmar `Preparando`.
5. **Secretaria:** a equipe confirma `Liberado`, concluindo a solicitação.

**Limitações relevantes:** na implementação atual, a etapa `Preparando` exige que o responsável já tenha entrado no raio; o GPS, sozinho, **não libera automaticamente** o aluno. A instância acadêmica utiliza **um ponto fictício de demonstração**, não a localização de uma escola real. Se o avaliador não estiver fisicamente na região do ponto configurado, não poderá completar a verificação real de proximidade. Uma demonstração gravada desse fluxo é uma alternativa adequada nesse caso.

A localização enviada durante a solicitação é utilizada para cálculo da distância sem persistir as coordenadas atuais do responsável no MySQL, de acordo com a implementação revisada.

## 4. Como avaliar a qualidade técnica

Além da navegação, o repositório permite observar:

- **Organização do código:** separação entre a interface web em `public/` e a lógica PHP em `private/app/`.
- **Banco de dados:** estrutura SQL e migrações, com tabelas para usuários, vínculos acadêmicos, notas, frequência, comunicação e Saída Segura.
- **Controle de acesso:** autenticação e validações de perfis nas APIs.
- **Proteções implementadas:** verificações de sessão e CSRF em operações de escrita, entre outros mecanismos presentes no código.
- **Testes:** arquivos disponíveis em `tests/`. A execução de alguns testes automatizados e das verificações de sintaxe foi relatada durante a preparação do projeto; os resultados não representam auditoria independente nem certificação de segurança.

## 5. Limites desta demonstração

O projeto é acadêmico. Não pressupõe dados de uma escola real, não comprova adequação regulatória ou de produção e não deve receber informações pessoais reais. Um avaliador com credenciais de um único perfil verá apenas as funcionalidades destinadas àquele perfil. Alguns fluxos exigem dois perfis ou dispositivos e precisam de demonstração acompanhada.

## 6. Para disponibilizar acesso público sem comprometer a segurança

Uma conta com senha publicada no README pode ser utilizada e alterada por qualquer visitante. **Não divulgue a senha de administrador, Secretaria, professor ou responsável que tenha acesso a dados compartilhados.**

O caminho mais seguro é preparar **um ambiente de demonstração isolado**, com apenas dados fictícios, usuários próprios e possibilidade de reinicialização. Após criá-lo e **testar a autenticação em uma janela anônima**, o responsável poderá atualizar este guia com o link e os dados da conta de visitante apropriada. Até lá, o acesso autenticado deve ser fornecido individualmente.

Se o avaliador não tiver credenciais, ainda poderá consultar o código e a documentação do repositório; o link acima levará somente à tela de login.
