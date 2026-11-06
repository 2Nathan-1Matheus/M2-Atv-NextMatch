/* =====================================================
   NextMatch — Interações da interface
   Os dados agora vêm do banco (PHP). Este arquivo cuida só
   do comportamento: menu mobile, carrossel, abas, avisos,
   confirmações e pequenos ajustes de formulário.
   ===================================================== */

/* ---------------- Menu mobile ---------------- */
function nmInitMenu() {
  const toggle = document.getElementById("nmNavToggle");
  const menu = document.getElementById("nmMobileMenu");
  if (!toggle || !menu) return;
  const iconeMenu = toggle.innerHTML;
  const iconeFechar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  toggle.addEventListener("click", () => {
    const aberto = menu.classList.toggle("open");
    toggle.innerHTML = aberto ? iconeFechar : iconeMenu;
    toggle.setAttribute("aria-expanded", aberto);
    document.body.style.overflow = aberto ? "hidden" : "";
  });
}

/* ---------------- Banner (carrossel) ----------------
   Só roda na página que tem <section class="banner">. */
const banners = [
  "assets/img/banner-hero.jpg",
  "assets/img/banner2.jpg",
  "assets/img/banner3.jpg",
  "assets/img/banner4.jpg",
];
let bannerAtual = 0;
let temporizador;
let banner = null;

function mostrarBanner() {
  if (!banner) return;
  banner.style.backgroundImage = `url("${banners[bannerAtual]}")`;
}

function mudarBanner(direcao) {
  if (!banner) return;
  bannerAtual = (bannerAtual + direcao + banners.length) % banners.length;
  mostrarBanner();
  reiniciarTemporizador();
}

function reiniciarTemporizador() {
  clearTimeout(temporizador);
  temporizador = setTimeout(() => mudarBanner(1), 5000);
}

function nmInitBanner() {
  banner = document.querySelector(".banner");
  if (!banner) return;
  banners.forEach(src => { const img = new Image(); img.src = src; });

  banner.querySelector(".anterior")?.addEventListener("click", () => mudarBanner(-1));
  banner.querySelector(".proximo")?.addEventListener("click", () => mudarBanner(1));

  let toqueInicioX = null;
  banner.addEventListener("touchstart", (e) => { toqueInicioX = e.touches[0].clientX; }, { passive: true });
  banner.addEventListener("touchend", (e) => {
    if (toqueInicioX === null) return;
    const distancia = e.changedTouches[0].clientX - toqueInicioX;
    if (Math.abs(distancia) > 40) mudarBanner(distancia < 0 ? 1 : -1);
    toqueInicioX = null;
  });

  mostrarBanner();
  reiniciarTemporizador();
}

/* ---------------- Abas (data-tab / data-pane) ---------------- */
function nmInitTabs() {
  document.querySelectorAll(".tabs[data-tabs]").forEach(grupo => {
    const botoes = grupo.querySelectorAll("button[data-tab]");
    const painel = grupo.parentElement;
    const panes = painel.querySelectorAll(":scope > .tab-pane");
    function ativar(nome) {
      botoes.forEach(b => {
        const ativo = b.dataset.tab === nome;
        b.classList.toggle("active", ativo);
        b.setAttribute("aria-selected", ativo);
      });
      panes.forEach(p => { p.hidden = p.dataset.pane !== nome; });
    }
    botoes.forEach(b => b.addEventListener("click", () => {
      ativar(b.dataset.tab);
      history.replaceState(null, "", "#" + b.dataset.tab);
    }));
    function pelaUrl() {
      const hash = location.hash.replace("#", "");
      if (hash && [...botoes].some(b => b.dataset.tab === hash)) ativar(hash);
    }
    pelaUrl();
    window.addEventListener("hashchange", pelaUrl);
  });
}

/* ---------------- Avisos (toasts) ---------------- */
function nmInitToasts() {
  document.querySelectorAll(".toast").forEach(t => {
    const fechar = () => { t.classList.add("hide"); setTimeout(() => t.remove(), 300); };
    t.querySelector(".toast-close")?.addEventListener("click", fechar);
    setTimeout(fechar, t.classList.contains("err") ? 8000 : 5000);
  });
}

/* ---------------- Confirmação antes de ações sensíveis ----------------
   <form data-confirm="Tem certeza?"> */
function nmInitConfirm() {
  document.querySelectorAll("form[data-confirm]").forEach(f => {
    f.addEventListener("submit", (e) => {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    });
  });
}

/* ---------------- Selects que enviam o formulário sozinhos ---------------- */
function nmInitAutoSubmit() {
  document.querySelectorAll("[data-autosubmit]").forEach(el => {
    el.addEventListener("change", () => el.form && el.form.submit());
  });
  document.querySelectorAll(".js-hide").forEach(el => el.hidden = true);
}

/* ---------------- Prévia da foto antes de enviar ---------------- */
function nmInitPreview() {
  document.querySelectorAll("input[type=file][data-preview]").forEach(input => {
    input.addEventListener("change", () => {
      const alvo = document.getElementById(input.dataset.preview);
      const arq = input.files[0];
      if (!alvo || !arq) return;
      if (arq.size > 2 * 1024 * 1024) {
        alert("A imagem deve ter no máximo 2 MB.");
        input.value = "";
        return;
      }
      const leitor = new FileReader();
      leitor.onload = () => { alvo.innerHTML = `<img src="${leitor.result}" alt="">`; };
      leitor.readAsDataURL(arq);
    });
  });
}

/* ---------------- Inscrição de equipe: mostra só campeonatos da modalidade da equipe ---------------- */
function nmInitFiltroModalidade() {
  document.querySelectorAll("select[data-filtra]").forEach(origem => {
    const destino = document.getElementById(origem.dataset.filtra);
    if (!destino) return;
    function filtrar() {
      const mod = origem.selectedOptions[0]?.dataset.modalidade;
      let algumVisivel = false;
      destino.querySelectorAll("option[data-modalidade]").forEach(op => {
        const ok = !mod || op.dataset.modalidade === mod;
        op.hidden = !ok;
        op.disabled = !ok;
        if (ok) algumVisivel = true;
      });
      destino.querySelectorAll("optgroup").forEach(g => {
        g.hidden = ![...g.querySelectorAll("option")].some(o => !o.hidden);
      });
      if (destino.selectedOptions[0]?.disabled) destino.value = "";
      const aviso = document.getElementById(origem.dataset.aviso);
      if (aviso) aviso.hidden = algumVisivel || !mod;
    }
    origem.addEventListener("change", filtrar);
    filtrar();
  });
}

/* ---------------- Resultado no mata-mata: em empate, pede o vencedor ---------------- */
function nmInitEmpate() {
  document.querySelectorAll("form[data-mata-mata]").forEach(f => {
    const a = f.querySelector("[name=placar_a]");
    const b = f.querySelector("[name=placar_b]");
    const caixa = f.querySelector(".desempate");
    if (!a || !b || !caixa) return;
    function verificar() {
      const empate = a.value !== "" && b.value !== "" && Number(a.value) === Number(b.value);
      caixa.hidden = !empate;
      caixa.querySelector("select").required = empate;
    }
    a.addEventListener("input", verificar);
    b.addEventListener("input", verificar);
    verificar();
  });
}

document.addEventListener("DOMContentLoaded", () => {
  nmInitMenu();
  nmInitBanner();
  nmInitTabs();
  nmInitToasts();
  nmInitConfirm();
  nmInitAutoSubmit();
  nmInitPreview();
  nmInitFiltroModalidade();
  nmInitEmpate();
});
