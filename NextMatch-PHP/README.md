# NextMatch — Plataforma multiesportiva

Plataforma para organizar competições esportivas: atletas, professores e
organizadores cuidam de inscrições, equipes, chaveamentos, resultados e
rankings em um só lugar, sem planilhas nem grupos de WhatsApp.

Feito com **HTML, CSS, JavaScript, PHP e MySQL**, seguindo a documentação em
`docs/` (proposta, identidade visual, estrutura do site, mobile first e
requisitos).

---

## 1. Como rodar no computador (XAMPP)

**Você precisa de:** [XAMPP](https://www.apachefriends.org/pt_br/) com PHP 8.0 ou mais novo.

1. Copie a pasta **`NextMatch`** inteira para dentro de `C:\xampp\htdocs\`
   (no Mac/Linux: `/Applications/XAMPP/htdocs/` ou `/opt/lampp/htdocs/`).
2. Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.
3. No navegador, abra **http://localhost/phpmyadmin**.
4. Clique na aba **Importar** → **Escolher arquivo** → selecione
   `NextMatch/database/nextmatch.sql` → clique em **Importar** (no fim da página).
   O banco `nextmatch` é criado com todas as tabelas e os dados de demonstração.
5. Acesse **http://localhost/NextMatch** — pronto.

> Se o seu MySQL tiver senha, ou o banco tiver outro nome, ajuste em
> `include/config.php`.
>
> Para **zerar tudo** e voltar aos dados de demonstração, basta importar o
> `nextmatch.sql` de novo. Para começar **sem dados fictícios** (todos os
> números em zero, só as modalidades), importe `database/nextmatch-limpo.sql`.

### Contas de demonstração

A senha de todas é **`nextmatch123`**.

| Perfil | E-mail | O que já tem |
|---|---|---|
| Organizador | `organizador@nextmatch.com` | 9 campeonatos, 4 inscrições esperando aprovação |
| Professor | `professor@nextmatch.com` | Equipes Falcões FC e Falcões Basquete, 2 pedidos de atletas |
| Atleta | `atleta@nextmatch.com` | Lucas Martins, do Falcões FC, com inscrições e resultados |

Qualquer pessoa também pode criar uma conta nova em **Cadastrar**.

---

## 2. Roteiro para a apresentação (≈ 5 minutos)

1. **Página inicial** — banner em carrossel, números reais do banco, próximas partidas.
2. Entre como **organizador** → no painel, **aprove** as inscrições do
   *Torneio de Basquete 3x3* → clique em **Gerenciar** → aba
   **Chaveamento e resultados** → **Gerar chaveamento**. O sorteio é
   automático, e quem fica sem adversário avança direto.
3. Ainda como organizador, abra a **Copa NextMatch de Futebol** → lance o
   placar da segunda semifinal (teste um **empate**: o sistema pede quem
   venceu nos pênaltis) → o vencedor aparece sozinho na **final**. Lance a
   final e o campeonato é **encerrado automaticamente**, com o pódio publicado.
4. Abra o **Ranking** → a classificação já mudou com os novos resultados.
5. Saia e entre como **professor** → aceite um pedido de atleta, crie uma
   equipe com escudo e inscreva-a num campeonato aberto.
6. Entre como **atleta** → veja as **notificações** (sino no topo), o gráfico
   de desempenho e faça uma inscrição individual no *Circuito de Tênis*.
7. Mostre no **celular** (ou com o modo celular do navegador, F12): barra
   inferior, menu, calendário em lista.

---

## 3. Funcionalidades (conforme `docs/funcionalidades.md`)

| Funcionalidade | Onde está |
|---|---|
| Login de usuários e autenticação | `login.php` (entrar e cadastrar), `logout.php`, sessão PHP |
| Separação atleta / professor / organizador | cada perfil tem seu painel e só acessa o que é dele |
| Chaveamentos automáticos | `include/chaveamento.php` — mata-mata com sorteio e byes, pontos corridos (todos contra todos) e provas por classificação |
| Upload de imagens | foto de perfil, escudo da equipe, capa do campeonato e imagem de notícia (`uploads/`) |
| Rankings | `ranking.php` — calculado a partir das partidas (V=3, E=1) e das provas (10-8-6-5-4-3-2-1) |
| Dashboards | `dashboard-atleta.php`, `dashboard-professor.php`, `dashboard-gerenciador.php`, `gerenciar-campeonato.php` |
| Calendário esportivo | `calendario.php` — grade no computador e lista no celular |
| Notificações | sino no cabeçalho + `notificacoes.php`. São criadas em inscrições, aprovações, pedidos de equipe, chaveamento e resultados |
| Gráficos | desempenho (vitórias/empates/derrotas), atletas por equipe, participantes por campeonato, pontos do ranking |
| Sistema responsivo | mobile first, barra de navegação inferior no celular |
| Formulários | cadastro, perfil, contato (salvo no banco), criação de campeonato, equipe e notícia |
| Gerenciamento de equipes | professor cria equipes, adiciona/remove atletas, aceita pedidos e inscreve em campeonatos |

---

## 4. Estrutura de pastas

```
NextMatch/
├── index.php                  Página inicial
├── login.php / logout.php     Entrar, cadastrar e sair
├── competicoes.php            Lista de campeonatos com filtros
├── detalhes-competicao.php    Regulamento, participantes, chave, premiação e inscrição
├── calendario.php             Calendário esportivo
├── equipes.php                Lista de equipes e página de cada equipe
├── ranking.php                Rankings por modalidade
├── noticias.php               Notícias (lista e leitura)
├── contato.php                Fale conosco
├── perfil.php                 Perfil do usuário
├── notificacoes.php           Notificações
├── dashboard-atleta.php       Painel do atleta
├── dashboard-professor.php    Painel do professor
├── dashboard-gerenciador.php  Painel do organizador
├── gerenciar-campeonato.php   Gestão de um campeonato (inscrições, chave, resultados)
├── include/                   Partes reutilizáveis (ver include/includes.md)
│   ├── config.php             ← dados do banco
│   ├── conexao.php            Conexão PDO
│   ├── funcoes.php            Sessão, segurança, datas, ranking, gráficos, upload
│   ├── chaveamento.php        Geração da chave e lançamento de resultados
│   ├── header.php / navbar.php / footer.php / sidebar.php
│   └── icones.php             Ícones SVG
├── assets/css/style.css       Design system (cores e fontes de docs/identidade-visual.md)
├── assets/js/main.js          Menu, carrossel, abas, avisos
├── assets/img/                Logo e banners
├── uploads/                   Imagens enviadas pelos usuários
├── database/nextmatch.sql     Banco completo (estrutura + dados de demonstração)
└── docs/                      Documentação original do projeto
```

## 5. Banco de dados

Baseado em `database/modelo-banco.md`. Tabelas:

- **usuarios** — atletas, professores e organizadores (coluna `tipo`), senha criptografada
- **modalidades** — Futebol, Vôlei, Basquete, Tênis, Atletismo, Natação, Lutas, Capoeira
- **equipes** e **equipe_atletas** — elencos e pedidos de entrada
- **campeonatos** e **categorias**
- **inscricoes** — de equipes ou de atletas, com status pendente/aprovada/recusada
- **partidas** — jogos gerados pelo chaveamento, com placar e vencedor
- **resultados** — colocação e marca nas provas (natação, atletismo, capoeira)
- **noticias**, **notificacoes**, **contatos**
- **vw_desempenho** (view) — base do cálculo do ranking

## 6. Segurança (conforme `docs/requisitos.md`)

- Senhas guardadas com `password_hash` (bcrypt), nunca em texto puro.
- Todas as consultas usam *prepared statements* (proteção contra SQL injection).
- Todo texto exibido passa por `e()` / `htmlspecialchars` (proteção contra XSS).
- Todos os formulários têm token CSRF.
- A sessão é renovada no login, e há um limite de 5 tentativas de senha erradas.
- Cada perfil só acessa as próprias páginas e só altera o que é seu.
- Uploads: só JPG/PNG/WEBP de até 2 MB, com o conteúdo do arquivo conferido e
  nome aleatório. Imagens grandes são reduzidas, e a pasta `uploads/` não executa scripts.
- `include/` e `database/` ficam bloqueadas para acesso direto pelo navegador (`.htaccess`).

## 7. Publicar na internet

Funciona em qualquer hospedagem com PHP 8 e MySQL (inclusive gratuitas, como InfinityFree).

1. Crie o banco no painel da hospedagem e anote host, nome, usuário e senha.
2. No `database/nextmatch.sql`, apague as linhas `CREATE DATABASE ...` e
   `USE ...` do início. Depois importe o arquivo pelo phpMyAdmin da hospedagem.
3. Coloque esses dados em `include/config.php` e mude `MODO_DEBUG` para `false`.
4. Envie todos os arquivos por FTP e confira se a pasta `uploads/` permite gravação.
5. Antes de divulgar, troque a senha das contas de demonstração ou apague essas contas.

## 8. Limitações conhecidas

- **Esqueci a senha:** não há envio de e-mail (o XAMPP não envia e-mails sem
  configuração extra). O link leva ao formulário de contato.
- As notificações aparecem dentro da plataforma, sem e-mail ou push.
- O limite de tentativas de login vale por sessão do navegador.
