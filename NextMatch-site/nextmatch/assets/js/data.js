/* =====================================================
   NextMatch — Dados simulados (mock)
   Em produção, estes dados viriam do banco de dados
   (ver docs/database/modelo-banco.md) via PHP/API.
   ===================================================== */

const NM_ICONS = {
  futebol: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7l3.5 2.5-1.3 4.1H9.8L8.5 9.5 12 7z"/><path d="M12 3v4M12 21v-3.5M3.5 9l3 1M20.5 9l-3 1M4.5 16.5l3.5-1.5M19.5 16.5l-3.5-1.5"/></svg>`,
  volei: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 3c3 3 3 15 0 18M4 8c4 2 12 2 16 0M4 16c4-2 12-2 16 0"/></svg>`,
  basquete: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18M5.5 5.5c3.5 3 3.5 10 0 13M18.5 5.5c-3.5 3-3.5 10 0 13"/></svg>`,
  tenis: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M6 6c3 3 9 9 12 12M18 6c-3 3-9 9-12 12"/></svg>`,
  atletismo: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="16" cy="5" r="1.6"/><path d="M14 9l-3.5 2 1 3.5-3 2.5M11 11l3.5 1 3-2 2.5 4.5M6.5 21l3-5"/></svg>`,
  natacao: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="17" cy="6" r="1.6"/><path d="M3 17c1.5 1.4 3 1.4 4.5 0s3-1.4 4.5 0 3 1.4 4.5 0 3-1.4 4.5 0M9 13l4-5 3 2.5-2 3"/></svg>`,
};

const NM_MODALIDADES = ["Futebol", "Vôlei", "Basquete", "Tênis", "Atletismo", "Natação"];
const NM_MOD_ICON = { Futebol:"futebol", "Vôlei":"volei", Basquete:"basquete", "Tênis":"tenis", Atletismo:"atletismo", "Natação":"natacao" };

const NM_COMPETITIONS = [
  { id:1, nome:"Copa NextMatch de Futebol", modalidade:"Futebol", categoria:"Sub-20 Masculino", status:"Inscrições abertas", local:"Arena Central, São Paulo/SP", data:"14 Set 2026", premiacao:"R$ 5.000 + troféu", equipes:16, regulamento:"Fase de grupos seguida de mata-mata em chaveamento único. Cada equipe pode inscrever até 22 atletas. Cartões e suspensões seguem regras da CBF adaptadas." },
  { id:2, nome:"Liga Municipal de Vôlei", modalidade:"Vôlei", categoria:"Adulto Feminino", status:"Em andamento", local:"Ginásio Poliesportivo, Campinas/SP", data:"02 Ago 2026", premiacao:"Medalhas + troféu", equipes:12, regulamento:"Melhor de 5 sets, pontuação corrida até 25 pontos (15 no set decisivo). Classificação por pontos corridos na fase de grupos." },
  { id:3, nome:"Torneio de Basquete 3x3", modalidade:"Basquete", categoria:"Livre", status:"Inscrições abertas", local:"Quadra do Parque, Jundiaí/SP", data:"28 Set 2026", premiacao:"Kits esportivos", equipes:24, regulamento:"Partidas de 10 minutos corridos ou 21 pontos. Chaveamento eliminatório simples com repescagem para 3º lugar." },
  { id:4, nome:"Circuito de Tênis Amador", modalidade:"Tênis", categoria:"Individual", status:"Em breve", local:"Clube Vila Verde, Bragança/SP", data:"10 Out 2026", premiacao:"Ranking + brindes", equipes:32, regulamento:"Melhor de 3 sets com tie-break no terceiro. Chaveamento por ranking prévio dos inscritos." },
  { id:5, nome:"Meia Maratona NextMatch", modalidade:"Atletismo", categoria:"Livre", status:"Inscrições abertas", local:"Orla do Rio, Sorocaba/SP", data:"22 Nov 2026", premiacao:"Medalha finisher", equipes:200, regulamento:"Percurso de 21km cronometrado por chip. Premiação por faixa etária e geral." },
  { id:6, nome:"Campeonato de Natação Sub-18", modalidade:"Natação", categoria:"Sub-18", status:"Encerrado", local:"Centro Aquático, Itu/SP", data:"18 Jun 2026", premiacao:"Medalhas por prova", equipes:18, regulamento:"Provas de 50m, 100m e 200m nos quatro estilos, seguindo regras da CBDA." },
];

const NM_TEAMS = [
  { id:1, nome:"Falcões FC", modalidade:"Futebol", treinador:"Prof. Ricardo Alves", atletas:22, cidade:"São Paulo/SP" },
  { id:2, nome:"Vôlei Fênix", modalidade:"Vôlei", treinador:"Profª. Camila Rocha", atletas:14, cidade:"Campinas/SP" },
  { id:3, nome:"Tigres Basquete", modalidade:"Basquete", treinador:"Prof. Eduardo Lima", atletas:12, cidade:"Jundiaí/SP" },
  { id:4, nome:"Águias do Tênis", modalidade:"Tênis", treinador:"Prof. Marcelo Sousa", atletas:9, cidade:"Bragança/SP" },
  { id:5, nome:"Panteras Futsal", modalidade:"Futebol", treinador:"Profª. Juliana Prado", atletas:18, cidade:"Sorocaba/SP" },
  { id:6, nome:"Golfinhos Natação", modalidade:"Natação", treinador:"Prof. Bruno Castro", atletas:20, cidade:"Itu/SP" },
];

const NM_RANKING = {
  Futebol: [
    { pos:1, nome:"Falcões FC", pontos:34, v:11, e:1, d:1 },
    { pos:2, nome:"Panteras Futsal", pontos:30, v:9, e:3, d:1 },
    { pos:3, nome:"União Atlética", pontos:27, v:8, e:3, d:2 },
    { pos:4, nome:"Real Sorocaba", pontos:22, v:6, e:4, d:3 },
    { pos:5, nome:"Estrela Azul FC", pontos:18, v:5, e:3, d:5 },
  ],
  "Vôlei": [
    { pos:1, nome:"Vôlei Fênix", pontos:28, v:10, e:0, d:1 },
    { pos:2, nome:"Águias do Saque", pontos:24, v:8, e:0, d:3 },
    { pos:3, nome:"Ases da Rede", pontos:21, v:7, e:0, d:4 },
    { pos:4, nome:"Vôlei Vila Nova", pontos:15, v:5, e:0, d:6 },
  ],
  Basquete: [
    { pos:1, nome:"Tigres Basquete", pontos:26, v:12, e:0, d:1 },
    { pos:2, nome:"Furacão 3x3", pontos:23, v:10, e:0, d:3 },
    { pos:3, nome:"Rinos BBall", pontos:19, v:8, e:0, d:5 },
  ],
};

const NM_NEWS = [
  { id:1, categoria:"Futebol", data:"12 Ago 2026", titulo:"Copa NextMatch define grupos para a próxima fase", resumo:"Sorteio realizado nesta semana define os confrontos da fase de grupos da Copa NextMatch de Futebol Sub-20." },
  { id:2, categoria:"Plataforma", data:"05 Ago 2026", titulo:"Novo sistema de chaveamento automático já está no ar", resumo:"Organizadores agora podem gerar chaveamentos automaticamente a partir das equipes inscritas em cada categoria." },
  { id:3, categoria:"Vôlei", data:"29 Jul 2026", titulo:"Vôlei Fênix assume a liderança da Liga Municipal", resumo:"Equipe campineira vence mais uma rodada e abre vantagem na tabela de classificação da liga." },
  { id:4, categoria:"Atletismo", data:"20 Jul 2026", titulo:"Inscrições abertas para a Meia Maratona NextMatch", resumo:"Evento acontece em novembro na orla do Rio Sorocaba e já ultrapassa 500 inscritos." },
  { id:5, categoria:"Plataforma", data:"10 Jul 2026", titulo:"NextMatch chega a 50 equipes cadastradas na plataforma", resumo:"Marca reforça o crescimento da base de treinadores e organizadores que usam o sistema no dia a dia." },
  { id:6, categoria:"Basquete", data:"02 Jul 2026", titulo:"Torneio 3x3 abre vagas para categoria livre", resumo:"Inscrições seguem até setembro para o torneio disputado na quadra do Parque, em Jundiaí." },
];
