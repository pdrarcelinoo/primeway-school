PRIMEWay School
Sistema web de gestão escolar | Projeto acadêmico — SENAI Jairo Cândido  
Desenvolvedor: Pedro Sousa Arcelino
O PRIMEWay School centraliza processos acadêmicos e administrativos em cinco áreas de acesso: Administrador, Secretaria, Professor, Aluno e Responsável. Foi desenvolvido com HTML, CSS, JavaScript, PHP e MySQL, com foco em organização, comunicação e experiência de uso.
Experimente o sistema
Aplicação online: Abrir PRIMEWay School
> **Acesso de demonstração:** o sistema exige login. No momento, não há credenciais públicas verificadas. Para solicitar uma conta fictícia de teste, [abra uma solicitação nas Issues do projeto](https://github.com/pdrarcelinoo/primeway-school/issues/new) (se estiverem habilitadas) ou utilize um canal profissional de contato disponível no [perfil do autor](https://github.com/pdrarcelinoo). As credenciais deverão ser fornecidas **em particular**, nunca em uma Issue pública. **Não use contas administrativas reais.**
O passo a passo de avaliação está em Como testar o PRIMEWay School. O guia explica os perfis, os testes sugeridos e o funcionamento da Saída Segura com GPS.
Para quem está avaliando o projeto: o código e a documentação demonstram a implementação; a aplicação online permite avaliar o sistema com uma conta de demonstração válida. Uma captura de tela ou acesso à página de login, por si só, não equivale à validação dos módulos autenticados.
Principal diferencial: Saída Segura com GPS
A funcionalidade organiza a retirada de estudantes:
Um responsável autorizado solicita a retirada pelo sistema.
O navegador, após permissão do usuário, fornece a localização para verificar a proximidade de um ponto de retirada configurado.
Ao entrar no raio definido, a equipe escolar recebe uma notificação.
A Secretaria confirma a preparação e, posteriormente, registra a liberação.
Fluxo implementado: `Aguardando → No raio → Preparando → Liberado`.
A liberação não é automática apenas pelo GPS: depende de uma confirmação da equipe escolar. A versão acadêmica utiliza um ponto de referência fictício; o GPS real não pode ser demonstrado de qualquer lugar sem proximidade com esse ponto. O cálculo de distância utiliza a localização durante a solicitação sem gravar as coordenadas atuais do responsável no MySQL, conforme o código revisado.
Funcionalidades
Perfil	Exemplos de recursos
Administrador	Gestão geral, permissões e visão administrativa
Secretaria	Cadastros acadêmicos, turmas, comunicados e Saída Segura
Professor	Atividades, avaliações, notas, frequência e comunicação
Aluno	Consulta de informações acadêmicas, atividades e comunicados
Responsável	Acompanhamento do estudante, comunicação e solicitação de retirada
O sistema também inclui chat, calendário, notificações e painéis de acompanhamento.
Tecnologias
Interface: HTML5, CSS3 e JavaScript.  
Servidor: PHP.  
Banco de dados: SQL e MySQL.  
Execução da aplicação publicada: Apache e Railway, com configuração de contêiner no projeto.
Organização do repositório
`public/`: páginas web, CSS, JavaScript, imagens e rotas de entrada das APIs.
`private/app/`: implementação PHP, configurações e arquivos SQL.
`docs/`: documentação do projeto.
`tests/`: testes automatizados disponíveis no código.
`docker/` e `Dockerfile`: infraestrutura de execução.
Como avaliar a qualidade
No guia de testes, sugiro verificar a separação de permissões entre os perfis, a experiência da interface, a navegação, as operações acadêmicas, o chat, as notificações e o fluxo de retirada. Foram realizados testes de sintaxe de PHP e JavaScript e verificações automatizadas locais durante a preparação do portfólio; esses testes não equivalem a uma certificação de segurança nem garantem ausência de erros.
Observações
O PRIMEWay School é um projeto acadêmico, não uma plataforma certificada para operação de uma escola real.
As informações de demonstração devem ser fictícias; não inclua dados pessoais reais nos testes.
Perfis administrativos e operações que alteram dados devem ter acesso controlado.
Credenciais de acesso não devem ser copiadas do ambiente privado ou inventadas para este README.
Desenvolvido por Pedro Sousa Arcelino durante a formação técnica no SENAI Jairo Cândido.
