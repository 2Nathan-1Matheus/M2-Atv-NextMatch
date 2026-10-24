/* =====================================================
   NextMatch — Interações principais
   ===================================================== */

function nmStatusChip(status){
  const map = {
    "Inscrições abertas":"green",
    "Em andamento":"yellow",
    "Em breve":"gray",
    "Encerrado":"red",
  };
  return `<span class="chip ${map[status]||'gray'}">${status}</span>`;
}

function nmCompCard(c){
  return `
  <a href="detalhes-competicao.html?id=${c.id}" class="comp-card">
    <div class="thumb">${NM_ICO_SVG(c.modalidade)}${nmStatusChip(c.status)}</div>
    <div class="body">
      <span class="chip gray">${c.modalidade} · ${c.categoria}</span>
      <h4>${c.nome}</h4>
      <div class="meta">
        <span>${NM_ICO.calendar}${c.data}</span>
        <span>${NM_ICO.pin}${c.local.split(",")[0]}</span>
      </div>
      <div class="foot">
        <span class="faint" style="font-size:12px;">${c.equipes} equipes</span>
        <span class="btn btn-ghost btn-sm">Ver detalhes</span>
      </div>
    </div>
  </a>`;
}
function NM_ICO_SVG(modalidade){
  const key = NM_MOD_ICON[modalidade] || "futebol";
  return NM_ICONS[key];
}

function nmNewsCard(n){
  return `
  <a href="noticias.html" class="news-card">
    <div class="thumb">${NM_ICO.news}</div>
    <div class="body">
      <span class="date">${n.categoria} · ${n.data}</span>
      <h4>${n.titulo}</h4>
      <p>${n.resumo}</p>
    </div>
  </a>`;
}

/* ---------------- Página inicial ---------------- */
function nmRenderHome(){
  const wrap = document.getElementById("homeCompetitions");
  if(wrap){
    wrap.innerHTML = NM_COMPETITIONS
      .filter(c=>c.status!=="Encerrado")
      .slice(0,3)
      .map(nmCompCard).join("");
  }
  const news = document.getElementById("homeNews");
  if(news){
    news.innerHTML = NM_NEWS.slice(0,3).map(nmNewsCard).join("");
  }
}

/* ---------------- Login ---------------- */
function nmInitLogin(){
  const form = document.getElementById("loginForm");
  if(!form) return;
  let role = "atleta";
  const tabs = document.querySelectorAll(".role-tabs button");
  tabs.forEach(btn=>{
    btn.addEventListener("click", ()=>{
      tabs.forEach(b=>b.classList.remove("active"));
      btn.classList.add("active");
      role = btn.dataset.role;
    });
  });

  const modeLink = document.getElementById("authModeLink");
  const modeTitle = document.getElementById("authTitle");
  const nameField = document.getElementById("nameField");
  const submitBtn = document.getElementById("authSubmit");
  let isRegister = false;
  if(modeLink){
    modeLink.addEventListener("click", (e)=>{
      e.preventDefault();
      isRegister = !isRegister;
      modeTitle.textContent = isRegister ? "Criar conta" : "Entrar na plataforma";
      nameField.style.display = isRegister ? "block" : "none";
      submitBtn.textContent = isRegister ? "Criar conta" : "Entrar";
      modeLink.innerHTML = isRegister
        ? 'Já tem conta? <a href="#">Entrar</a>'
        : 'Ainda não tem conta? <a href="#">Cadastre-se</a>';
    });
  }

  form.addEventListener("submit", (e)=>{
    e.preventDefault();
    const email = document.getElementById("loginEmail").value.trim();
    const nomeInput = document.getElementById("loginNome");
    const nome = (isRegister && nomeInput && nomeInput.value.trim()) || (email.split("@")[0] || "Usuário");
    const displayName = nome.charAt(0).toUpperCase() + nome.slice(1);
    nmSetSession({ nome: displayName, tipo: role, email: email || (displayName.toLowerCase()+"@nextmatch.com.br") });
    window.location.href = NM_ROLE_DASH[role];
  });
}

/* ---------------- Competições (listagem) ---------------- */
function nmInitCompeticoes(){
  const grid = document.getElementById("compGrid");
  if(!grid) return;
  const selMod = document.getElementById("filtroModalidade");
  const selStatus = document.getElementById("filtroStatus");
  const search = document.getElementById("filtroBusca");

  function render(){
    const mod = selMod.value;
    const status = selStatus.value;
    const q = search.value.toLowerCase().trim();
    const items = NM_COMPETITIONS.filter(c=>{
      return (!mod || c.modalidade===mod) &&
             (!status || c.status===status) &&
             (!q || c.nome.toLowerCase().includes(q));
    });
    grid.innerHTML = items.length ? items.map(nmCompCard).join("") :
      `<div class="empty-state" style="grid-column:1/-1;">${NM_ICO.trophy}<p>Nenhuma competição encontrada com esses filtros.</p></div>`;
  }
  [selMod, selStatus].forEach(el=>el.addEventListener("change", render));
  search.addEventListener("input", render);
  render();
}

/* ---------------- Detalhes da competição ---------------- */
function nmInitDetalhes(){
  const holder = document.getElementById("compDetail");
  if(!holder) return;
  const params = new URLSearchParams(window.location.search);
  const id = parseInt(params.get("id")) || NM_COMPETITIONS[0].id;
  const c = NM_COMPETITIONS.find(x=>x.id===id) || NM_COMPETITIONS[0];

  document.title = c.nome + " · NextMatch";
  document.getElementById("compNome").textContent = c.nome;
  document.getElementById("compStatus").innerHTML = nmStatusChip(c.status);
  document.getElementById("compModalidade").textContent = c.modalidade + " · " + c.categoria;
  document.getElementById("compData").textContent = c.data;
  document.getElementById("compLocal").textContent = c.local;
  document.getElementById("compPremiacao").textContent = c.premiacao;
  document.getElementById("compEquipes").textContent = c.equipes + " equipes inscritas";
  document.getElementById("compRegulamento").textContent = c.regulamento;
  document.getElementById("compIcon").innerHTML = NM_ICO_SVG(c.modalidade);

  const partList = document.getElementById("compParticipantes");
  if(partList){
    const teams = NM_TEAMS.filter(t=>t.modalidade===c.modalidade).concat(NM_TEAMS).slice(0,6);
    partList.innerHTML = teams.map((t,i)=>`
      <div class="list-row">
        <div class="thumb">${NM_ICO.users}</div>
        <div class="info"><b>${t.nome}</b><span>${t.cidade} · ${t.atletas} atletas</span></div>
        <span class="chip gray">#${i+1}</span>
      </div>`).join("");
  }

  const tabs = document.querySelectorAll(".tabs button");
  const panes = document.querySelectorAll(".tab-pane");
  tabs.forEach(btn=>btn.addEventListener("click", ()=>{
    tabs.forEach(b=>b.classList.remove("active"));
    btn.classList.add("active");
    panes.forEach(p=>p.style.display = p.dataset.pane===btn.dataset.tab ? "block" : "none");
  }));
}

/* ---------------- Equipes ---------------- */
function nmInitEquipes(){
  const grid = document.getElementById("teamsGrid");
  if(!grid) return;
  const search = document.getElementById("teamSearch");
  const selMod = document.getElementById("teamFiltroModalidade");

  function card(t){
    return `<div class="card">
      <div class="flex gap-12" style="margin-bottom:14px;">
        <div class="thumb" style="width:44px;height:44px;border-radius:12px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">${NM_ICO_SVG(t.modalidade)}</div>
        <div>
          <h4 style="margin:0;font-size:15px;">${t.nome}</h4>
          <span class="faint" style="font-size:12px;">${t.cidade}</span>
        </div>
      </div>
      <div class="meta" style="display:flex;flex-direction:column;gap:6px;font-size:13px;" class="muted">
        <span class="muted">Modalidade: <b style="color:var(--text)">${t.modalidade}</b></span>
        <span class="muted">Treinador(a): <b style="color:var(--text)">${t.treinador}</b></span>
        <span class="muted">Atletas: <b style="color:var(--text)">${t.atletas}</b></span>
      </div>
      <div class="foot" style="margin-top:14px;"><a href="ranking.html" class="btn btn-outline btn-sm btn-block">Ver desempenho</a></div>
    </div>`;
  }

  function render(){
    const q = search.value.toLowerCase().trim();
    const mod = selMod.value;
    const items = NM_TEAMS.filter(t => (!mod||t.modalidade===mod) && (!q || t.nome.toLowerCase().includes(q)));
    grid.innerHTML = items.length ? items.map(card).join("") :
      `<div class="empty-state" style="grid-column:1/-1;">${NM_ICO.users}<p>Nenhuma equipe encontrada.</p></div>`;
  }
  search.addEventListener("input", render);
  selMod.addEventListener("change", render);
  render();
}

/* ---------------- Ranking ---------------- */
function nmInitRanking(){
  const tbody = document.getElementById("rankingBody");
  if(!tbody) return;
  const tabs = document.querySelectorAll("#rankingTabs button");

  function render(mod){
    const rows = NM_RANKING[mod] || [];
    tbody.innerHTML = rows.map(r=>`
      <tr>
        <td><span class="rank-pos ${r.pos===1?'p1':r.pos===2?'p2':r.pos===3?'p3':''}">${r.pos}º</span></td>
        <td><div class="team-cell"><span class="dot">${nmInitials(r.nome)}</span>${r.nome}</div></td>
        <td>${r.v}</td><td>${r.e}</td><td>${r.d}</td>
        <td><b>${r.pontos}</b></td>
      </tr>`).join("");
  }
  tabs.forEach(btn=>btn.addEventListener("click", ()=>{
    tabs.forEach(b=>b.classList.remove("active"));
    btn.classList.add("active");
    render(btn.dataset.mod);
  }));
  render(tabs[0]?.dataset.mod || "Futebol");
}

/* ---------------- Notícias ---------------- */
function nmInitNoticias(){
  const grid = document.getElementById("newsGrid");
  if(!grid) return;
  grid.innerHTML = NM_NEWS.map(nmNewsCard).join("");
}

/* ---------------- Contato ---------------- */
function nmInitContato(){
  const form = document.getElementById("contatoForm");
  if(!form) return;
  form.addEventListener("submit", (e)=>{
    e.preventDefault();
    const msg = document.getElementById("contatoMsg");
    msg.textContent = "Mensagem enviada! Nossa equipe responde em até 2 dias úteis.";
    msg.classList.add("show","ok");
    form.reset();
    setTimeout(()=>msg.classList.remove("show"), 5000);
  });
}

/* ---------------- Perfil ---------------- */
function nmInitPerfil(){
  const holder = document.getElementById("perfilRoot");
  if(!holder) return;
  const session = nmGetSession() || nmEnsureDemoSession("atleta","Convidado NextMatch");

  document.getElementById("perfilNome").value = session.nome;
  document.getElementById("perfilEmail").value = session.email;
  document.getElementById("perfilTipo").textContent = NM_ROLE_LABEL[session.tipo];
  document.getElementById("perfilAvatarTxt").textContent = nmInitials(session.nome);

  const form = document.getElementById("perfilForm");
  form.addEventListener("submit", (e)=>{
    e.preventDefault();
    session.nome = document.getElementById("perfilNome").value.trim() || session.nome;
    session.email = document.getElementById("perfilEmail").value.trim() || session.email;
    nmSetSession(session);
    document.getElementById("perfilAvatarTxt").textContent = nmInitials(session.nome);
    const msg = document.getElementById("perfilMsg");
    msg.textContent = "Dados atualizados com sucesso.";
    msg.classList.add("show","ok");
    setTimeout(()=>msg.classList.remove("show"), 4000);
  });

  const fileInput = document.getElementById("perfilFoto");
  if(fileInput){
    fileInput.addEventListener("change", ()=>{
      const file = fileInput.files[0];
      if(!file) return;
      const reader = new FileReader();
      reader.onload = ()=>{
        document.getElementById("perfilAvatarBox").innerHTML = `<img src="${reader.result}" alt="Foto de perfil">`;
      };
      reader.readAsDataURL(file);
    });
  }
}

/* ---------------- Dashboard Atleta ---------------- */
function nmInitDashAtleta(){
  const root = document.getElementById("dashAtletaRoot");
  if(!root) return;
  const session = nmEnsureDemoSession("atleta","Lucas Martins");
  document.getElementById("dashAtletaNome").textContent = session.nome.split(" ")[0];

  document.getElementById("atletaInscricoes").innerHTML = NM_COMPETITIONS.slice(0,3).map(c=>`
    <div class="list-row">
      <div class="thumb">${NM_ICO_SVG(c.modalidade)}</div>
      <div class="info"><b>${c.nome}</b><span>${c.data} · ${c.local.split(",")[0]}</span></div>
      ${nmStatusChip(c.status)}
    </div>`).join("");

  document.getElementById("atletaResultados").innerHTML = `
    <div class="table-wrap"><table>
      <thead><tr><th>Competição</th><th>Adversário</th><th>Resultado</th><th>Data</th></tr></thead>
      <tbody>
        <tr><td>Copa NextMatch</td><td>União Atlética</td><td><span class="chip green">Vitória 3x1</span></td><td>10 Ago</td></tr>
        <tr><td>Copa NextMatch</td><td>Real Sorocaba</td><td><span class="chip yellow">Empate 2x2</span></td><td>27 Jul</td></tr>
        <tr><td>Liga Regional</td><td>Estrela Azul</td><td><span class="chip red">Derrota 0x1</span></td><td>14 Jul</td></tr>
      </tbody>
    </table></div>`;
}

/* ---------------- Dashboard Professor ---------------- */
function nmInitDashProfessor(){
  const root = document.getElementById("dashProfRoot");
  if(!root) return;
  const session = nmEnsureDemoSession("professor","Profª. Camila Rocha");
  document.getElementById("dashProfNome").textContent = session.nome.split(" ")[0];

  document.getElementById("profEquipes").innerHTML = NM_TEAMS.slice(0,4).map(t=>`
    <div class="list-row">
      <div class="thumb">${NM_ICO_SVG(t.modalidade)}</div>
      <div class="info"><b>${t.nome}</b><span>${t.modalidade} · ${t.atletas} atletas</span></div>
      <a href="equipes.html" class="btn btn-ghost btn-sm">Gerenciar</a>
    </div>`).join("");

  document.getElementById("profSolicitacoes").innerHTML = [
    { nome:"Rafael Souza", equipe:"Falcões FC" },
    { nome:"Bianca Farias", equipe:"Panteras Futsal" },
    { nome:"Diego Martins", equipe:"Falcões FC" },
  ].map(s=>`
    <div class="list-row">
      <div class="thumb">${NM_ICO.user}</div>
      <div class="info"><b>${s.nome}</b><span>Solicita entrar em ${s.equipe}</span></div>
      <div class="flex gap-8"><button class="btn btn-primary btn-sm">Aceitar</button><button class="btn btn-ghost btn-sm">Recusar</button></div>
    </div>`).join("");

  const form = document.getElementById("addAtletaForm");
  if(form) form.addEventListener("submit", (e)=>{
    e.preventDefault();
    const msg = document.getElementById("addAtletaMsg");
    msg.textContent = "Atleta adicionado à equipe com sucesso.";
    msg.classList.add("show","ok");
    form.reset();
    setTimeout(()=>msg.classList.remove("show"), 4000);
  });
}

/* ---------------- Dashboard Organizador ---------------- */
function nmInitDashOrganizador(){
  const root = document.getElementById("dashOrgRoot");
  if(!root) return;
  const session = nmEnsureDemoSession("organizador","Rodrigo Nunes");
  document.getElementById("dashOrgNome").textContent = session.nome.split(" ")[0];

  document.getElementById("orgCampeonatos").innerHTML = `
    <div class="table-wrap"><table>
      <thead><tr><th>Campeonato</th><th>Modalidade</th><th>Status</th><th>Equipes</th><th></th></tr></thead>
      <tbody>
        ${NM_COMPETITIONS.map(c=>`
          <tr>
            <td>${c.nome}</td>
            <td>${c.modalidade}</td>
            <td>${nmStatusChip(c.status)}</td>
            <td>${c.equipes}</td>
            <td><a href="detalhes-competicao.html?id=${c.id}" class="btn btn-ghost btn-sm">Ver</a></td>
          </tr>`).join("")}
      </tbody>
    </table></div>`;

  const form = document.getElementById("novoCampeonatoForm");
  if(form) form.addEventListener("submit", (e)=>{
    e.preventDefault();
    const nome = document.getElementById("novoCampNome").value.trim() || "Novo Campeonato";
    const row = document.createElement("div");
    row.className = "list-row";
    row.innerHTML = `<div class="thumb">${NM_ICO.trophy}</div><div class="info"><b>${nome}</b><span>Criado agora · aguardando configuração</span></div><span class="chip gray">Rascunho</span>`;
    document.getElementById("orgRecemCriados").prepend(row);
    document.getElementById("orgRecemCriados").style.display = "block";
    const msg = document.getElementById("novoCampMsg");
    msg.textContent = "Campeonato criado! Configure categorias e chaveamento a seguir.";
    msg.classList.add("show","ok");
    form.reset();
    setTimeout(()=>msg.classList.remove("show"), 4500);
  });
}

document.addEventListener("DOMContentLoaded", ()=>{
  nmRenderHome();
  nmInitLogin();
  nmInitCompeticoes();
  nmInitDetalhes();
  nmInitEquipes();
  nmInitRanking();
  nmInitNoticias();
  nmInitContato();
  nmInitPerfil();
  nmInitDashAtleta();
  nmInitDashProfessor();
  nmInitDashOrganizador();
});
