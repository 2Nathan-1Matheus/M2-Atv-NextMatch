<?php
/* =====================================================
   NextMatch — Ícones SVG (traço, herdam a cor do texto)
   Uso: echo icone('trophy');  /  echo icone_modalidade('volei');
   ===================================================== */

const NM_ICONES = [
  'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
  'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
  'home'     => '<path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-9z"/>',
  'trophy'   => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4z"/><path d="M8 5H4v2a4 4 0 0 0 4 3M16 5h4v2a4 4 0 0 1-4 3M10 15h4M12 15v3M8 21h8"/>',
  'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.7-3.4 3.3-5.4 6.5-5.4s5.8 2 6.5 5.4"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.5 14.7c2.6.2 4.6 2.1 5 4.9"/>',
  'rank'     => '<path d="M4 20V11M10 20V4M16 20v-7"/>',
  'news'     => '<path d="M5 4h11a2 2 0 0 1 2 2v13a1 1 0 0 1-1.5.87L15 19l-1.5.87a1 1 0 0 1-1 0L11 19l-1.5.87a1 1 0 0 1-1.5-.87V6a2 2 0 0 0-3-1.7"/><path d="M8 9h6M8 12h6"/>',
  'phone'    => '<path d="M5 4h3.2l1.3 4.3-2 1.4a12 12 0 0 0 6.8 6.8l1.4-2 4.3 1.3V19a2 2 0 0 1-2.2 2C9.9 20.6 3.4 14.1 3 6.2A2 2 0 0 1 5 4z"/>',
  'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/>',
  'user'     => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c1-4 4-6.2 7.5-6.2S18.5 16 19.5 20"/>',
  'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5"/>',
  'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
  'pin'      => '<path d="M12 21s7-6.3 7-11.5A7 7 0 0 0 5 9.5C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/>',
  'bracket'  => '<path d="M4 6h4v3H4zM4 15h4v3H4zM8 7.5h4M8 16.5h4M12 7.5v9M12 12h4M16 12h4"/>',
  'layers'   => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5M3 8l9 5 9-5"/>',
  'bell'     => '<path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
  'logout'   => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3M15 16l4-4-4-4M19 12H9"/>',
  'chart'    => '<path d="M4 19V5M4 19h16M8 15l3-3 2.5 2.5L18 9"/>',
  'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  'plus'     => '<path d="M12 5v14M5 12h14"/>',
  'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
  'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
  'medal'    => '<circle cx="12" cy="15" r="5"/><path d="M8.5 11.5L6 3h4l2 5 2-5h4l-2.5 8.5"/><path d="M12 13v4"/>',
  'shield'   => '<path d="M12 3l7 3v5c0 5-3.2 8.3-7 10-3.8-1.7-7-5-7-10V6l7-3z"/>',
  'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
  'instagram'=> '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1"/>',
  'whatsapp' => '<path d="M4 20l1.3-3.8A8 8 0 1 1 8.7 19L4 20z"/><path d="M8.5 9.5c0 3.5 2.5 6 6 6"/>',
  'youtube'  => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 10l4 2-4 2v-4z"/>',
  // Modalidades
  'futebol'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7l3.5 2.5-1.3 4.1H9.8L8.5 9.5 12 7z"/><path d="M12 3v4M12 21v-3.5M3.5 9l3 1M20.5 9l-3 1M4.5 16.5l3.5-1.5M19.5 16.5l-3.5-1.5"/>',
  'volei'    => '<circle cx="12" cy="12" r="9"/><path d="M12 3c3 3 3 15 0 18M4 8c4 2 12 2 16 0M4 16c4-2 12-2 16 0"/>',
  'basquete' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18M5.5 5.5c3.5 3 3.5 10 0 13M18.5 5.5c-3.5 3-3.5 10 0 13"/>',
  'tenis'    => '<circle cx="12" cy="12" r="9"/><path d="M6 6c3 3 9 9 12 12M18 6c-3 3-9 9-12 12"/>',
  'atletismo'=> '<circle cx="16" cy="5" r="1.6"/><path d="M14 9l-3.5 2 1 3.5-3 2.5M11 11l3.5 1 3-2 2.5 4.5M6.5 21l3-5"/>',
  'natacao'  => '<circle cx="17" cy="6" r="1.6"/><path d="M3 17c1.5 1.4 3 1.4 4.5 0s3-1.4 4.5 0 3 1.4 4.5 0 3-1.4 4.5 0M9 13l4-5 3 2.5-2 3"/>',
  'lutas'    => '<path d="M7 10V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v6a5 5 0 0 1-5 5h-1a4 4 0 0 1-4-4v-4z"/><path d="M7 10h6a2 2 0 0 1 0 4h-2M9 18v3h6v-3"/>',
  'capoeira' => '<circle cx="8" cy="5" r="1.6"/><path d="M4 21l4-6 1-5 4 3 6-6M9 10l-3-1M13 13l2 8"/>',
];

function icone(string $nome): string {
    $d = NM_ICONES[$nome] ?? NM_ICONES['trophy'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function icone_modalidade(?string $icone): string {
    return icone(isset(NM_ICONES[$icone]) ? $icone : 'trophy');
}
