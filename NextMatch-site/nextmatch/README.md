# NextMatch — Protótipo do site

Protótipo funcional (HTML, CSS e JavaScript puros) da plataforma **NextMatch**,
desenvolvido a partir da documentação do projeto (proposta, identidade visual,
estrutura do site, páginas e mobile first).

## Como abrir

Não precisa de servidor nem instalação: basta abrir o arquivo `index.html`
diretamente no navegador (duplo clique) ou usar a extensão "Live Server"
do seu editor, se preferir.

## Páginas incluídas

- `index.html` — Página inicial
- `login.html` — Entrar / cadastrar (com seleção de perfil: atleta, professor, organizador)
- `dashboard-atleta.html` — Painel do atleta
- `dashboard-professor.html` — Painel do professor/treinador
- `dashboard-gerenciador.html` — Painel do organizador (com chaveamento)
- `competicoes.html` — Listagem de campeonatos com filtros
- `detalhes-competicao.html` — Detalhes de um campeonato (regulamento, participantes, premiação)
- `equipes.html` — Equipes cadastradas
- `ranking.html` — Ranking por modalidade
- `noticias.html` — Notícias
- `contato.html` — Fale conosco
- `perfil.html` — Perfil do usuário

## Como funciona

- **Login simulado**: qualquer e-mail/senha entra na demonstração. O tipo de
  conta (atleta, professor ou organizador) é escolhido na tela de login e
  guardado no `localStorage` do navegador, apenas para fins de demonstração.
- **Dados simulados**: competições, equipes, rankings e notícias estão em
  `assets/js/data.js`. Em uma versão real, viriam do banco de dados via PHP
  (ver `include/conexao.php` planejado na documentação original).
- **Componentes reutilizáveis**: cabeçalho, rodapé, menu mobile e barra
  inferior são montados por `assets/js/components.js`, equivalente aos
  includes `header.php` / `footer.php` / `navbar.php` / `sidebar.php`
  descritos na documentação.

## Próximos passos sugeridos

- Substituir os dados simulados por integração real com banco de dados
  (MySQL/PostgreSQL) via PHP ou API.
- Implementar autenticação real com hash de senha.
- Conectar upload de imagens e chaveamento automático a uma API.

## Identidade visual aplicada

| Cor | Uso |
|---|---|
| Azul Escuro `#0F172A` | Fundo principal |
| Branco `#FFFFFF` | Textos |
| Laranja Neon `#F97316` | Botões e destaques |
| Cinza Escuro `#1E293B` | Cards e menus |

Fontes: Poppins (títulos), Roboto (texto), Montserrat (rótulos/botões).
