/* =====================================================
   NextMatch — Componentes reutilizáveis
   (equivalente aos includes header.php / footer.php /
   navbar.php / sidebar.php descritos em include/includes.md)
   ===================================================== */

const NM_SESSION_KEY = "nm_session";

function nmGetSession(){
  try{
    const raw = localStorage.getItem(NM_SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  }catch(e){ return null; }
}
function nmSetSession(session){ localStorage.setItem(NM_SESSION_KEY, JSON.stringify(session)); }
function nmClearSession(){ localStorage.removeItem(NM_SESSION_KEY); }
function nmInitials(nome){
  return nome.split(" ").filter(Boolean).slice(0,2).map(n=>n[0]).join("").toUpperCase();
}
const NM_ROLE_LABEL = { atleta:"Atleta", professor:"Professor(a)", organizador:"Organizador(a)" };
const NM_ROLE_DASH = { atleta:"dashboard-atleta.html", professor:"dashboard-professor.html", organizador:"dashboard-gerenciador.html" };

const NM_NAV_LINKS = [
  { href:"index.html", label:"Início" },
  { href:"competicoes.html", label:"Competições" },
  { href:"equipes.html", label:"Equipes" },
  { href:"ranking.html", label:"Ranking" },
  { href:"noticias.html", label:"Notícias" },
  { href:"contato.html", label:"Contato" },
];

const NM_ICO = {
  menu:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>`,
  close:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>`,
  home:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-9z"/></svg>`,
  trophy:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v5a4 4 0 0 1-8 0V4z"/><path d="M8 5H4v2a4 4 0 0 0 4 3M16 5h4v2a4 4 0 0 1-4 3M10 15h4M12 15v3M8 21h8"/></svg>`,
  users:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.7-3.4 3.3-5.4 6.5-5.4s5.8 2 6.5 5.4"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.5 14.7c2.6.2 4.6 2.1 5 4.9"/></svg>`,
  rank:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V11M10 20V4M16 20v-7"/></svg>`,
  news:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11a2 2 0 0 1 2 2v13a1 1 0 0 1-1.5.87L15 19l-1.5.87a1 1 0 0 1-1 0L11 19l-1.5.87a1 1 0 0 1-1.5-.87V6a2 2 0 0 0-3-1.7"/><path d="M8 9h6M8 12h6"/></svg>`,
  phone:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h3.2l1.3 4.3-2 1.4a12 12 0 0 0 6.8 6.8l1.4-2 4.3 1.3V19a2 2 0 0 1-2.2 2C9.9 20.6 3.4 14.1 3 6.2A2 2 0 0 1 5 4z"/></svg>`,
  user:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1-4 4-6.2 7.5-6.2S18.5 16 19.5 20"/></svg>`,
  calendar:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5"/></svg>`,
  pin:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.3 7-11.5A7 7 0 0 0 5 9.5C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>`,
  bracket:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h4v3H4zM4 15h4v3H4zM8 7.5h4M8 16.5h4M12 7.5v9M12 12h4v0M16 12h4"/></svg>`,
  layers:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5M3 8l9 5 9-5"/></svg>`,
  bell:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10z"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>`,
  logout:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3M15 16l4-4-4-4M19 12H9"/></svg>`,
  chart:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h16M8 15l3-3 2.5 2.5L18 9"/></svg>`,
  instagram:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1"/></svg>`,
  whatsapp:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20l1.3-3.8A8 8 0 1 1 8.7 19L4 20z"/><path d="M8.5 9.5c0 3.5 2.5 6 6 6"/></svg>`,
  youtube:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 10l4 2-4 2v-4z"/></svg>`,
};

function nmHeaderHTML(current){
  const session = nmGetSession();
  const links = NM_NAV_LINKS.map(l => `<a href="${l.href}" class="${current===l.href?'active':''}">${l.label}</a>`).join("");

  const desktopAction = session
    ? `<a href="${NM_ROLE_DASH[session.tipo]}" class="user-chip"><span class="avatar">${nmInitials(session.nome)}</span> ${session.nome.split(" ")[0]}</a>`
    : `<a href="login.html" class="btn btn-outline hide-mobile">Entrar</a><a href="login.html" class="btn btn-primary">Cadastrar</a>`;

  const mobileAction = session
    ? `<a href="${NM_ROLE_DASH[session.tipo]}" class="btn btn-primary btn-block">Meu painel</a><a href="perfil.html" class="btn btn-ghost btn-block">Meu perfil</a>`
    : `<a href="login.html" class="btn btn-primary btn-block">Entrar / Cadastrar</a>`;

  const mobileLinks = NM_NAV_LINKS.map(l => `<a href="${l.href}" class="${current===l.href?'active':''}">${l.label} ›</a>`).join("");

  return `
  <header class="site-header">
    <div class="container">
      <a href="index.html" class="brand"><img src="assets/img/logo.png" alt="NextMatch"> Next<span>Match</span></a>
      <nav class="navbar" aria-label="Navegação principal">${links}</nav>
      <div class="header-actions">
        ${desktopAction}
        <button class="nav-toggle" id="nmNavToggle" aria-label="Abrir menu">${NM_ICO.menu}</button>
      </div>
    </div>
  </header>
  <div class="mobile-menu" id="nmMobileMenu">
    ${mobileLinks}
    <div class="mm-actions">${mobileAction}</div>
  </div>`;
}

function nmFooterHTML(){
  return `
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.html" class="brand"><img src="assets/img/logo.png" alt="NextMatch"> Next<span>Match</span></a>
          <p>Plataforma multiesportiva para organização e gerenciamento de competições, equipes e atletas.</p>
          <div class="social-row">
            <a href="#" aria-label="Instagram">${NM_ICO.instagram}</a>
            <a href="#" aria-label="WhatsApp">${NM_ICO.whatsapp}</a>
            <a href="#" aria-label="YouTube">${NM_ICO.youtube}</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Plataforma</h4>
          <a href="competicoes.html">Competições</a>
          <a href="equipes.html">Equipes</a>
          <a href="ranking.html">Ranking</a>
          <a href="noticias.html">Notícias</a>
        </div>
        <div class="footer-col">
          <h4>Conta</h4>
          <a href="login.html">Entrar</a>
          <a href="login.html">Criar conta</a>
          <a href="perfil.html">Meu perfil</a>
        </div>
        <div class="footer-col">
          <h4>Suporte</h4>
          <p>suporte@nextmatch.com.br</p>
          <p>(11) 4000-1234</p>
          <a href="contato.html">Fale conosco</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© 2026 NextMatch. Todos os direitos reservados.</span>
        <span>Feito para atletas, professores e organizadores.</span>
      </div>
    </div>
  </footer>`;
}

function nmBottomNavHTML(current){
  const items = [
    { href:"index.html", label:"Início", ico:NM_ICO.home },
    { href:"competicoes.html", label:"Competições", ico:NM_ICO.trophy },
    { href:"ranking.html", label:"Ranking", ico:NM_ICO.rank },
    { href:"noticias.html", label:"Notícias", ico:NM_ICO.news },
  ];
  const session = nmGetSession();
  items.push(session
    ? { href:NM_ROLE_DASH[session.tipo], label:"Painel", ico:NM_ICO.user }
    : { href:"login.html", label:"Entrar", ico:NM_ICO.user });
  return `<nav class="bottom-nav" aria-label="Navegação inferior">${
    items.map(i=>`<a href="${i.href}" class="${current===i.href?'active':''}">${i.ico}${i.label}</a>`).join("")
  }</nav>`;
}

const NM_SIDEBAR_MAP = {
  atleta:[
    { href:"dashboard-atleta.html", label:"Meu painel", ico:NM_ICO.home },
    { href:"competicoes.html", label:"Competições", ico:NM_ICO.trophy },
    { href:"ranking.html", label:"Rankings", ico:NM_ICO.rank },
    { href:"perfil.html", label:"Meu perfil", ico:NM_ICO.user },
  ],
  professor:[
    { href:"dashboard-professor.html", label:"Painel", ico:NM_ICO.home },
    { href:"equipes.html", label:"Minhas equipes", ico:NM_ICO.users },
    { href:"competicoes.html", label:"Competições", ico:NM_ICO.trophy },
    { href:"perfil.html", label:"Meu perfil", ico:NM_ICO.user },
  ],
  organizador:[
    { href:"dashboard-gerenciador.html", label:"Painel", ico:NM_ICO.home },
    { href:"competicoes.html", label:"Campeonatos", ico:NM_ICO.trophy },
    { href:"equipes.html", label:"Equipes", ico:NM_ICO.users },
    { href:"ranking.html", label:"Rankings", ico:NM_ICO.rank },
    { href:"perfil.html", label:"Meu perfil", ico:NM_ICO.user },
  ],
};

function nmSidebarHTML(role, current){
  const items = NM_SIDEBAR_MAP[role] || NM_SIDEBAR_MAP.atleta;
  return `
  <aside class="dash-sidebar">
    <div class="side-title">${NM_ROLE_LABEL[role]}</div>
    ${items.map(i=>`<a href="${i.href}" class="${current===i.href?'active':''}">${i.ico}${i.label}</a>`).join("")}
    <div class="side-title" style="margin-top:22px;">Conta</div>
    <a href="#" onclick="nmLogout(event)">${NM_ICO.logout}Sair</a>
  </aside>`;
}

function nmLogout(e){
  if(e) e.preventDefault();
  nmClearSession();
  window.location.href = "index.html";
}

/* Garante uma sessão de demonstração para abrir os painéis
   diretamente, mesmo sem passar pelo login.html */
function nmEnsureDemoSession(role, nome){
  let s = nmGetSession();
  if(!s){
    s = { nome: nome, tipo: role, email: (nome.split(" ")[0]).toLowerCase()+"@nextmatch.com.br" };
    nmSetSession(s);
  }
  return s;
}

function nmMountChrome(current, opts){
  opts = opts || {};
  const headerEl = document.getElementById("app-header");
  const footerEl = document.getElementById("app-footer");
  const bottomEl = document.getElementById("app-bottomnav");
  if(headerEl) headerEl.innerHTML = nmHeaderHTML(current);
  if(footerEl) footerEl.innerHTML = nmFooterHTML();
  if(bottomEl) bottomEl.innerHTML = nmBottomNavHTML(current);

  const toggle = document.getElementById("nmNavToggle");
  const menu = document.getElementById("nmMobileMenu");
  if(toggle && menu){
    toggle.addEventListener("click", ()=>{
      const open = menu.classList.toggle("open");
      toggle.innerHTML = open ? NM_ICO.close : NM_ICO.menu;
      document.body.style.overflow = open ? "hidden" : "";
    });
    menu.querySelectorAll("a").forEach(a=>a.addEventListener("click", ()=>{
      menu.classList.remove("open");
      toggle.innerHTML = NM_ICO.menu;
      document.body.style.overflow = "";
    }));
  }

  if(opts.dashboardRole){
    const sideEl = document.getElementById("app-sidebar");
    if(sideEl) sideEl.innerHTML = nmSidebarHTML(opts.dashboardRole, current);
  }
}
