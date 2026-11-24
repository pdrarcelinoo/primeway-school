# PRIMEWay School

**Sistema Web de Gestão Escolar | Projeto Acadêmico — SENAI Jairo Cândido**

**Desenvolvido por Pedro Sousa Arcelino**

O **PRIMEWay School** é um sistema web de gestão escolar desenvolvido como projeto acadêmico durante minha formação no curso Técnico em Desenvolvimento de Software do SENAI Jairo Cândido.

O projeto surgiu com o propósito de centralizar processos administrativos e acadêmicos, organizar informações e facilitar a comunicação entre os diferentes integrantes da comunidade escolar.

A aplicação integra funcionalidades de gestão, acompanhamento acadêmico e comunicação, oferecendo ambientes específicos para **Administradores, Secretaria, Professores, Alunos e Responsáveis**.

O desenvolvimento do PRIMEWay School proporcionou a oportunidade de aplicar conhecimentos de programação, banco de dados, desenvolvimento de interfaces, autenticação, organização de sistemas e resolução de problemas.

---

## Acesse e conheça o sistema

**Aplicação oficial:**

[**Acessar PRIMEWay School — Sistema completo**](https://primeway-web-production.up.railway.app/pages/login.html)

**Demonstração pública (Beta):**

[**Conhecer a demonstração Beta do PRIMEWay School**](https://pdrarcelinoo.github.io/primeway-school/)

A demonstração Beta é uma versão estática e independente, criada para permitir que visitantes conheçam a navegação e a proposta visual do projeto sem precisar de credenciais. Ela utiliza dados fictícios e não se conecta ao banco de dados nem ao back-end PHP da aplicação oficial. Algumas ações são apenas ilustrativas e não representam operações reais.

**Documentação para avaliação:**

[**Como testar o PRIMEWay School — Guia completo**](docs/COMO_TESTAR.md)

### Como acessar o ambiente de demonstração?

O PRIMEWay School possui autenticação, sendo necessário utilizar um e-mail e uma senha válidos para acessar suas funcionalidades internas.

No momento, não existem credenciais públicas de demonstração verificadas para divulgação.

Empresas, recrutadores e avaliadores interessados em conhecer o funcionamento da aplicação podem solicitar uma conta fictícia de demonstração:

[**Solicitar acesso de demonstração**](https://github.com/pdrarcelinoo/primeway-school/issues/new)

As credenciais serão disponibilizadas individualmente, por um canal privado, após a verificação do acesso. Não publique senhas em solicitações públicas do GitHub.

O acesso de demonstração permite conhecer os recursos correspondentes ao perfil disponibilizado. Algumas funcionalidades exigem permissões específicas ou a interação entre diferentes usuários.

**Observação:** o código-fonte e a documentação podem ser analisados diretamente neste repositório, mesmo sem uma conta para acessar a aplicação.

---

## Principal diferencial — Saída Segura com GPS

Uma das funcionalidades de destaque do PRIMEWay School é o módulo **Saída Segura**, desenvolvido para auxiliar no gerenciamento da retirada de estudantes da instituição.

O recurso utiliza a geolocalização do dispositivo do responsável autorizado para verificar sua proximidade em relação a um ponto de retirada previamente configurado.

### Como funciona?

1. **Solicitação:** o responsável autorizado seleciona o estudante e solicita a retirada pelo sistema.
2. **Verificação de localização:** após a autorização do navegador, a aplicação verifica a proximidade do responsável em relação ao ponto configurado.
3. **Identificação de chegada:** quando o responsável entra no raio estabelecido, a solicitação é atualizada e a equipe escolar recebe a informação.
4. **Preparação:** a Secretaria ou equipe autorizada registra a preparação da saída do estudante.
5. **Liberação:** a equipe confirma a liberação, finalizando a solicitação.

**Fluxo implementado:**

`Aguardando → No raio → Preparando → Liberado`

A confirmação de liberação é realizada pela equipe escolar, e não automaticamente pelo GPS.

Na demonstração acadêmica, o ponto de retirada utilizado é fictício. Por esse motivo, a verificação física de proximidade depende da localização do avaliador e pode exigir uma apresentação acompanhada.

Essa funcionalidade representa a proposta de utilizar a tecnologia para melhorar a organização, a comunicação e o acompanhamento das retiradas escolares.

---

## Funcionalidades do sistema

O PRIMEWay School possui cinco perfis de acesso, com funcionalidades e permissões específicas.

### Administrador

- Acesso ao painel administrativo.
- Gerenciamento de usuários e informações do sistema.
- Acompanhamento de indicadores e informações acadêmicas.
- Administração de recursos e configurações disponíveis.
- Comunicação com outros usuários.

### Secretaria

- Gerenciamento de estudantes, professores e responsáveis.
- Organização de turmas, disciplinas e matrículas.
- Acompanhamento de informações acadêmicas e administrativas.
- Gerenciamento de comunicados e calendário.
- Acompanhamento das solicitações de Saída Segura.

### Professor

- Acesso às turmas e disciplinas vinculadas.
- Cadastro e acompanhamento de atividades e avaliações.
- Registro de notas e frequência.
- Consulta de informações acadêmicas pertinentes ao perfil.
- Comunicação com outros usuários autorizados.

### Aluno

- Consulta às informações acadêmicas.
- Acompanhamento de atividades, avaliações e desempenho.
- Visualização de notas e frequência.
- Acesso a comunicados e calendário.
- Comunicação conforme as permissões do perfil.

### Responsável

- Acompanhamento das informações acadêmicas dos estudantes vinculados.
- Consulta a comunicados e informações escolares.
- Comunicação com a instituição.
- Solicitação e acompanhamento da retirada de estudantes autorizados pelo módulo Saída Segura.

### Outros recursos

Além das funcionalidades específicas de cada perfil, o sistema contempla:

- Chat integrado entre usuários autorizados.
- Notificações e comunicados.
- Calendário escolar.
- Painéis com informações e indicadores.
- Área de perfil do usuário.
- Controle de autenticação, sessões e permissões.

---

## Tecnologias utilizadas

O PRIMEWay School foi desenvolvido utilizando diferentes tecnologias, organizadas de acordo com suas responsabilidades na aplicação.

| Tecnologia | Aplicação no projeto |
| --- | --- |
| **HTML5** | Estruturação das páginas e dos elementos da interface |
| **CSS3** | Estilização, identidade visual e responsividade |
| **JavaScript** | Interatividade, comunicação com APIs e funcionalidades dinâmicas |
| **PHP** | Desenvolvimento back-end e processamento das operações |
| **MySQL** | Armazenamento e gerenciamento de dados |
| **SQL** | Consultas, relacionamentos e estruturação do banco |
| **Apache** | Servidor web utilizado na implantação |
| **Docker** | Configuração do ambiente de execução |
| **Railway** | Hospedagem da aplicação publicada |
| **Git e GitHub** | Versionamento, organização e disponibilização do código |

---

## Estrutura do projeto

O repositório está organizado em diretórios que separam as responsabilidades da aplicação.

```text
primeway-school/
|
|-- public/
|   |-- Páginas públicas e recursos web
|   |-- Arquivos CSS e JavaScript
|   |-- Imagens
|   |-- Rotas públicas de entrada das APIs
|
|-- private/
|   |-- app/
|       |-- Lógica de negócio em PHP
|       |-- APIs internas
|       |-- Configurações
|       |-- Estrutura e migrações do banco de dados
|
|-- docs/
|   |-- Documentação e guia de testes
|
|-- tests/
|   |-- Scripts de testes e verificações
|
|-- docker/
|   |-- Configurações do servidor
|
|-- mysql/
|   |-- Arquivos auxiliares de banco de dados
|
|-- Dockerfile
|-- README.md
```

Essa organização busca separar os arquivos públicos da aplicação, a implementação back-end, os recursos de banco de dados, os testes e a documentação.

---

## Arquitetura e desenvolvimento

O projeto utiliza uma arquitetura web com comunicação entre interface, servidor e banco de dados.

**Front-end:** responsável pela apresentação das páginas, navegação e interação com o usuário.

**Back-end:** desenvolvido em PHP para receber solicitações, aplicar regras de negócio, realizar validações e controlar o acesso às funcionalidades.

**Banco de dados:** utiliza MySQL para organizar as informações de usuários, vínculos acadêmicos, registros escolares e demais funcionalidades.

**Autenticação e permissões:** o sistema possui controle de sessões e separação de recursos conforme o perfil do usuário.

**Comunicação:** as interfaces utilizam JavaScript e APIs PHP para realizar operações e atualizar informações.

O desenvolvimento envolveu a integração desses componentes, buscando manter uma estrutura organizada e facilitar a manutenção do código.

---

## Qualidade de software e testes

Durante o desenvolvimento e a preparação do projeto, foram realizadas verificações destinadas a identificar problemas e avaliar aspectos técnicos da aplicação.

Entre as atividades realizadas estão:

- Verificações de sintaxe em arquivos PHP e JavaScript.
- Testes automatizados de aspectos relacionados à segurança e à navegação.
- Análise estática de consultas e vinculações SQL.
- Validação de importação e estruturação do banco MySQL em ambiente local.
- Verificações de organização de arquivos e dependências.
- Análise de autenticação e restrições de acesso presentes no código.

Os testes disponíveis no repositório podem ser consultados no diretório [`tests/`](tests/).

Essas verificações fazem parte do processo de desenvolvimento e revisão do projeto. **Não constituem uma certificação independente de segurança, desempenho ou adequação para uso em produção.**

### Como uma empresa pode avaliar o projeto?

Para facilitar a análise por recrutadores e profissionais da área, foi preparado um guia que apresenta:

- O endereço da aplicação publicada.
- Como solicitar acesso de demonstração.
- As funcionalidades disponíveis nos diferentes perfis.
- Um roteiro sugerido para navegar e testar o sistema.
- Os principais pontos técnicos que podem ser avaliados.
- As limitações da demonstração da Saída Segura com GPS.

[**Consultar o guia de testes do PRIMEWay School**](docs/COMO_TESTAR.md)

---

## Ambiente de demonstração

A aplicação está hospedada na plataforma Railway.

[**Abrir aplicação online**](https://primeway-web-production.up.railway.app/pages/login.html)

O ambiente utiliza autenticação e controle de permissões. Para realizar testes nas áreas internas, é necessário obter credenciais válidas de demonstração.

As informações utilizadas nos cenários demonstrativos devem ser fictícias. As operações com permissões administrativas ou alterações de dados compartilhados precisam ser realizadas de forma controlada.

O PRIMEWay School é um projeto acadêmico e não deve ser considerado uma solução certificada para uso real em uma instituição de ensino.

---

## Aprendizados e experiência adquirida

O desenvolvimento do PRIMEWay School foi uma experiência importante durante minha formação técnica.

Por meio desse projeto, pude aprofundar meus conhecimentos sobre:

- Planejamento e organização de sistemas.
- Desenvolvimento de interfaces web.
- Programação back-end com PHP.
- Modelagem e integração de banco de dados.
- Autenticação e controle de permissões.
- Integração de diferentes funcionalidades.
- Identificação e correção de problemas.
- Testes e revisão de código.
- Documentação e apresentação de software.

Mais do que desenvolver funcionalidades, essa experiência contribuiu para compreender a importância de analisar necessidades, planejar soluções e buscar melhorias durante o processo de desenvolvimento.

O PRIMEWay School representa uma etapa da minha formação e do meu aprendizado prático na área de tecnologia.

---

## Sobre o desenvolvedor

**Pedro Sousa Arcelino**

Estudante do curso Técnico em Desenvolvimento de Software no **SENAI Jairo Cândido**, atualmente em fase de conclusão da formação.

Tenho previsão de ingressar na graduação em **Engenharia de Software em fevereiro de 2027**, com o objetivo de ampliar meus conhecimentos, adquirir experiência profissional e continuar evoluindo na área de desenvolvimento de software.

Busco oportunidades para aplicar meus conhecimentos, aprender com outros profissionais, conhecer novas tecnologias e contribuir para o desenvolvimento de soluções tecnológicas.

**Meu GitHub:**

[github.com/pdrarcelinoo](https://github.com/pdrarcelinoo)

---

## Direitos e licenciamento

O código-fonte de origem possui indicação de licença proprietária.

A disponibilização deste repositório tem finalidade de apresentação acadêmica e profissional. A visualização do código não significa, por si só, autorização para sua reprodução, redistribuição ou utilização comercial.

Eventuais usos e distribuições devem respeitar os direitos e as autorizações aplicáveis.

---

**PRIMEWay School — Projeto acadêmico desenvolvido por Pedro Sousa Arcelino durante sua formação técnica em Desenvolvimento de Software no SENAI Jairo Cândido.**
