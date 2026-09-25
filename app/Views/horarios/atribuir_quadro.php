<?php
use App\Services\ColorHelper;

$pageTitle     = 'Atribuição de Professores (Modo Quadro)';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];

// Todo o estado da tela vai para o JS de uma vez: a edição é 100% local e só
// vira POST no "Salvar". Nenhuma ida ao servidor durante o arrasto.
$discJs = [];
foreach ($disciplinas as $d) {
    $discJs[] = [
        'id'       => (int)$d['id'],
        'nome'     => $d['nome'],
        'turma'    => $d['curso_nome'] . ' – ' . $d['turma_nome'],
        'ndaId'    => (int)($d['nda_id'] ?? 0),
        'ndaNome'  => $d['nda_nome'] ?? 'Qualquer NDA',
        'enc'      => (int)$d['qtd_encontros_semanais'],
        'aulas'    => (int)$d['qtd_aulas'],
        'ead'      => (int)($d['qtd_aulas_ead'] ?? 0),
        'dur'      => (int)$d['duracao_aula_minutos'],
        'qtdProfs' => max(1, (int)($d['qtd_professores'] ?? 1)),
        // Sala não é editada aqui, mas viaja no POST: salvarAtribuicoes()
        // regrava semestre_disciplina_salas inteira e a perderia.
        'sala'     => $d['sala_atribuida'] ? (int)$d['sala_atribuida'] : '',
        'profs'    => array_values(array_map('intval', $d['professores_atribuidos'] ?? [])),
    ];
}

$profJs = [];
foreach ($professores as $p) {
    $corPri = $p['cor'] ?? '#3b82f6';
    $corSec = $p['cor_secundaria'] ?? '#f97316';
    $profJs[] = [
        'id'      => (int)$p['id'],
        'nome'    => $p['nome'],
        'ndaId'   => (int)($p['nda_id'] ?? 0),
        'ndaNome' => $p['nda_nome'] ?? 'Sem NDA',
        'cor'     => $corPri,
        'corSec'  => $corSec,
        // Mesma regra de contraste da grade: a faixa é medida na secundária
        // cheia; o corpo do cartão, na primária a 35%.
        'txtSec'  => ColorHelper::textoSobre($corSec),
        'txtPri'  => ColorHelper::textoSobre($corPri, 0.35),
    ];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-grid-1x2 me-2 text-primary"></i>Atribuição de Professores (Modo Quadro)</h5>
    <small class="text-muted"><?= $semestreLabel ?> · arraste a disciplina até o professor</small>
  </div>
  <a href="<?= $base ?>/horarios/<?= $semestreId ?>/atribuir" class="btn btn-sm btn-success ms-auto">
    <i class="bi bi-list-check me-1"></i>Tela clássica (com salas)
  </a>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($avisos)): ?>
<div class="alert alert-danger py-2">
  <div class="fw-semibold small mb-1">
    <i class="bi bi-exclamation-octagon me-1"></i>Problemas de viabilidade — a geração não conseguirá agendar tudo:
  </div>
  <ul class="mb-0 small">
    <?php foreach ($avisos as $a): ?>
    <li><?= htmlspecialchars($a) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<style>
/* Os dois painéis rolam de forma independente: arrastar de uma lista longa
   para outra exige poder alinhar origem e destino na mesma altura da tela.
   A altura real é medida em JS (ajustarAltura) — o que há acima dos painéis
   varia com os alertas de flash e de viabilidade, e um valor fixo aqui faria
   a página inteira passar de 100vh justamente quando há aviso. */
.quadro-pane { max-height: calc(100vh - 210px); overflow-y: auto; overscroll-behavior: contain; }
.quadro-col  { position: sticky; top: 0; }

.disc-card {
  border: 1px solid var(--bs-border-color); border-left: 3px solid #94a3b8;
  border-radius: .375rem; padding: .4rem .55rem; margin-bottom: .35rem;
  background: #fff; cursor: grab; font-size: 12.5px; line-height: 1.25;
}
.disc-card:hover     { border-color: var(--bs-primary); box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.disc-card.dragging  { opacity: .4; }
.disc-card.armado    { outline: 2px solid var(--bs-primary); outline-offset: 1px; background: #eff6ff; }
.disc-card.completa  { border-left-color: #22c55e; background: #f8fafc; opacity: .75; }
.disc-card.parcial   { border-left-color: #f59e0b; }

/* Três professores por linha via flex-wrap, NÃO via grid: com grid isso
   dependia do cálculo de altura das linhas e do grid-column:1/-1 do cabeçalho
   de NDA, e ambos falhavam em alguns navegadores (cartões sobrepostos e
   cabeçalho espremido numa coluna). Aqui cada cartão é um item comum de
   largura fixa e altura natural, e o cabeçalho quebra a linha só por ocupar
   100% — sem depender de posicionamento em grade.
   align-items:flex-start = cada cartão com a altura das suas disciplinas;
   sem isso (stretch, o padrão) todos esticam até o mais alto da linha. */
.prof-grid { display: flex; flex-wrap: wrap; gap: .5rem;
             align-items: flex-start; align-content: flex-start; }
.prof-grid > .prof-card { flex: 0 0 calc((100% - 1rem) / 3); }   /* 3 col = 2 gaps */
.prof-grid > .grupo-titulo,
.prof-grid > .grid-full  { flex: 0 0 100%; }                     /* ocupa a linha = quebra */
/* Abaixo de xl o painel não comporta 3 colunas legíveis; abaixo de sm, nenhuma. */
@media (max-width: 1199.98px) { .prof-grid > .prof-card { flex-basis: calc((100% - .5rem) / 2); } }
@media (max-width:  575.98px) { .prof-grid > .prof-card { flex-basis: 100%; } }

.prof-card {
  border: 1px solid var(--bs-border-color); border-radius: .5rem;
  overflow: hidden; transition: box-shadow .12s, transform .12s;
  /* flex-column garante que o cartão conte a altura da faixa E do corpo;
     position:relative faz o overflow:hidden valer para qualquer descendente
     posicionado — sem isso, conteúdo fora de fluxo escaparia por cima do
     cartão seguinte em vez de ficar contido. */
  display: flex; flex-direction: column; position: relative; min-width: 0;
}
.prof-card.dragover { box-shadow: 0 0 0 3px rgba(13,110,253,.35); transform: translateY(-1px); }
.prof-card.nda-off  { opacity: .35; }
.prof-card.alvo     { cursor: copy; }

/* Em ~1/3 da largura não cabe nome + contadores na mesma linha: o cabeçalho
   passa a ter duas linhas, com os números em texto corrido. */
.prof-faixa-q { padding: .3rem .5rem; display: flex; flex-direction: column; gap: .1rem; }
.prof-nome    { font-size: 12.5px; font-weight: 600; }
.prof-metricas{ font-size: 10.5px; opacity: .85; display: flex; flex-wrap: wrap; gap: .3rem; }
/* flow-root: o corpo estabelece contexto próprio e nunca colapsa a altura. */
.prof-corpo   { padding: .35rem .4rem; min-height: 46px; display: flow-root; }
.prof-vazio   { font-size: 11.5px; font-style: italic; opacity: .7; }

/* Um chip por linha, ocupando a largura do cartão: lado a lado eles não
   caberiam sem truncar o nome da disciplina a ponto de ficar irreconhecível. */
.disc-chip {
  display: flex; align-items: flex-start; gap: .25rem; width: 100%;
  background: rgba(255,255,255,.78); border: 1px solid rgba(0,0,0,.12);
  border-radius: .3rem; padding: .15rem .25rem .15rem .35rem;
  margin-bottom: .2rem; font-size: 11.5px; line-height: 1.25; cursor: grab;
}
.disc-chip .chip-txt   { flex: 1; min-width: 0; }
.disc-chip .chip-nome  { display: block; overflow-wrap: anywhere; }
.disc-chip .chip-turma { display: block; font-size: 10px; opacity: .7; }
.disc-chip .btn-tirar  { border: 0; background: none; padding: 0 .1rem; line-height: 1; opacity: .55; }
.disc-chip .btn-tirar:hover { opacity: 1; color: #dc2626; }

.grupo-titulo { font-size: 11px; font-weight: 600; text-transform: uppercase;
              letter-spacing: .03em; color: #64748b; margin: .6rem 0 .25rem; }
.grupo-titulo:first-child { margin-top: 0; }
</style>

<form method="POST" action="<?= $base ?>/horarios/<?= $semestreId ?>/atribuir/quadro" id="formQuadro">
<div id="camposOcultos"></div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 d-flex align-items-center flex-wrap gap-2">
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" id="destacarNda" checked>
      <label class="form-check-label small" for="destacarNda">Destacar o NDA da disciplina</label>
    </div>
    <div class="vr d-none d-md-block"></div>
    <span class="badge text-bg-light border" id="contadorPendentes">—</span>
    <div class="ms-auto d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDesfazer" disabled>
        <i class="bi bi-arrow-counterclockwise me-1"></i>Desfazer
      </button>
      <button type="submit" class="btn btn-sm btn-primary" id="btnSalvar">
        <i class="bi bi-check-lg me-1"></i>Salvar Atribuições
      </button>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Pool de disciplinas -->
  <div class="col-lg-3">
    <div class="quadro-col">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-2">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fw-semibold small"><i class="bi bi-journals me-1"></i>Disciplinas</span>
            <div class="form-check form-switch ms-auto mb-0">
              <input class="form-check-input" type="checkbox" id="soPendentes" checked>
              <label class="form-check-label small text-muted" for="soPendentes">só pendentes</label>
            </div>
          </div>
          <input type="text" class="form-control form-control-sm" id="buscaDisc" placeholder="Filtrar disciplina ou turma...">
        </div>
        <?php // Soltar aqui devolve a disciplina para a fila (tira do professor). ?>
        <div class="card-body p-2 quadro-pane" id="pool"></div>
      </div>
    </div>
  </div>

  <!-- Professores -->
  <div class="col-lg-9">
    <div class="quadro-col">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-2">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fw-semibold small"><i class="bi bi-person-badge me-1"></i>Professores</span>
            <div class="form-check form-switch ms-auto mb-0">
              <input class="form-check-input" type="checkbox" id="soSubcarga">
              <label class="form-check-label small text-muted" for="soSubcarga"
                     title="Mostra só quem ainda tem folga: menos de 10 aulas semanais (presenciais + EaD)">
                menos de 10 aulas
              </label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="buscaProf" placeholder="Filtrar professor ou NDA...">
            <?php // Padrão NDA: é o agrupamento que orienta a distribuição de carga. ?>
            <select class="form-select form-select-sm" id="ordemProf" style="width:auto" title="Ordem dos professores">
              <option value="nda" selected>Ordem: NDA</option>
              <option value="nome">Ordem: A–Z</option>
            </select>
          </div>
        </div>
        <div class="card-body p-2 quadro-pane prof-grid" id="profs"></div>
      </div>
    </div>
  </div>
</div>
</form>

<script>
(function () {
  'use strict';

  const DISC = <?= json_encode($discJs, $jsonFlags) ?>;
  const PROF = <?= json_encode($profJs, $jsonFlags) ?>;

  const LIMITE_AULAS = 10;   // filtro "menos de 10 aulas" (semanais)

  const discPorId = new Map(DISC.map(d => [d.id, d]));
  const profPorId = new Map(PROF.map(p => [p.id, p]));

  // Estado: disciplinaId -> [professorId, ...] DENSO (índice 0 = slot 1).
  // Denso porque o gerador divide os encontros por slot (teto nos primeiros):
  // um buraco no slot 1 mudaria silenciosamente a divisão.
  const atrib = new Map(DISC.map(d => [d.id, d.profs.slice(0, d.qtdProfs)]));

  const undoStack = [];
  let armado = null;      // disciplina "presa" por clique, aguardando o professor
  let arrastando = null;  // { discId, origemProfId|null }
  let sujo = false;

  const $ = s => document.querySelector(s);
  const pool = $('#pool'), painelProfs = $('#profs');

  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const dur = m => m < 60 ? m + 'min' : (m % 60 ? Math.floor(m/60) + 'h' + (m%60) + 'min' : Math.floor(m/60) + 'h');

  // Mesma divisão de encontros de ScheduleGenerator/cargaPorProfessor:
  // teto nos primeiros slots.
  const fatia = (total, qtdProfs, slot) =>
    Math.floor(total / qtdProfs) + (slot <= total % qtdProfs ? 1 : 0);

  function cargaDoProf(profId) {
    let discs = 0, aulas = 0, min = 0, ead = 0;
    for (const d of DISC) {
      const i = (atrib.get(d.id) || []).indexOf(profId);
      if (i < 0) continue;
      const enc = fatia(d.enc, d.qtdProfs, i + 1);
      discs++;
      aulas += enc * d.aulas;
      min   += enc * d.aulas * d.dur;
      ead   += fatia(d.ead, d.qtdProfs, i + 1);
    }
    return { discs, aulas, min, ead };
  }

  const vagas    = d => d.qtdProfs - (atrib.get(d.id) || []).length;
  const pendente = d => vagas(d) > 0;

  // ── Mutações ────────────────────────────────────────────────
  const copia = () => new Map([...atrib].map(([k, v]) => [k, v.slice()]));

  // Um GESTO do usuário = um passo de "Desfazer", mesmo quando mexe em duas
  // atribuições (mover de um professor para outro é tirar + pôr). Só empilha
  // se algo mudou de fato, senão o botão acumularia passos vazios.
  function mutar(fn) {
    const antes = copia();
    fn();
    const mudou = DISC.some(d => (antes.get(d.id) || []).join() !== (atrib.get(d.id) || []).join());
    if (!mudou) return false;
    undoStack.push(antes);
    if (undoStack.length > 50) undoStack.shift();
    sujo = true;
    return true;
  }

  function atribuir(discId, profId) {
    const d = discPorId.get(discId);
    const lista = atrib.get(discId);
    if (!d || lista.includes(profId)) return false;   // já está com esse professor
    if (lista.length >= d.qtdProfs) return false;     // slots cheios
    lista.push(profId);
    return true;
  }

  function desatribuir(discId, profId) {
    const lista = atrib.get(discId);
    const i = lista.indexOf(profId);
    if (i < 0) return false;
    lista.splice(i, 1);   // compacta: os slots seguem 1..n sem buraco
    return true;
  }

  function desfazer() {
    const ant = undoStack.pop();
    if (!ant) return;
    atrib.clear();
    for (const [k, v] of ant) atrib.set(k, v);
    render();
  }

  // ── Altura dos painéis ──────────────────────────────────────
  // Cada painel vai do seu topo até o fim da janela, para a página nunca
  // passar de 100vh. Medido, não calculado em CSS: o que fica acima muda de
  // altura conforme haja alerta de flash, de viabilidade, ambos ou nenhum.
  function ajustarAltura() {
    const wrapper = document.querySelector('.content-wrapper');
    const margem = wrapper ? parseFloat(getComputedStyle(wrapper).paddingBottom) : 16;
    for (const pane of [pool, painelProfs]) {
      // + scrollY converte para posição no documento: sem isso a medida mudaria
      // conforme a rolagem da página e a altura ficaria oscilando.
      const topo = pane.getBoundingClientRect().top + window.scrollY;
      pane.style.maxHeight = Math.max(240, window.innerHeight - topo - margem) + 'px';
    }
  }

  // ── Render ──────────────────────────────────────────────────
  function render() {
    const topoPool = pool.scrollTop, topoProf = painelProfs.scrollTop;
    renderPool();
    renderProfs();
    pool.scrollTop = topoPool;
    painelProfs.scrollTop = topoProf;

    const falta = DISC.reduce((s, d) => s + vagas(d), 0);
    const ct = $('#contadorPendentes');
    ct.textContent = falta ? falta + ' atribuição(ões) pendente(s)' : 'Todas as disciplinas atribuídas';
    ct.className = 'badge ' + (falta ? 'text-bg-warning' : 'text-bg-success');
    $('#btnDesfazer').disabled = undoStack.length === 0;
  }

  function renderPool() {
    const termo = $('#buscaDisc').value.trim().toLowerCase();
    const soPend = $('#soPendentes').checked;

    const visiveis = DISC.filter(d => {
      if (soPend && !pendente(d)) return false;
      if (!termo) return true;
      return (d.nome + ' ' + d.turma + ' ' + d.ndaNome).toLowerCase().includes(termo);
    });

    if (!visiveis.length) {
      pool.innerHTML = '<div class="text-muted small text-center py-4">'
        + (soPend ? '<i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>Nada pendente.'
                  : 'Nenhuma disciplina encontrada.') + '</div>';
      return;
    }

    // Agrupa por turma: é assim que o usuário lê a oferta.
    const grupos = new Map();
    for (const d of visiveis) {
      if (!grupos.has(d.turma)) grupos.set(d.turma, []);
      grupos.get(d.turma).push(d);
    }

    let html = '';
    for (const [turma, lista] of grupos) {
      html += '<div class="grupo-titulo">' + esc(turma) + '</div>';
      for (const d of lista) {
        const usados = (atrib.get(d.id) || []).length;
        const cls = usados === 0 ? '' : (usados >= d.qtdProfs ? 'completa' : 'parcial');
        const nomesProf = (atrib.get(d.id) || [])
          .map(id => (profPorId.get(id) || {}).nome || '?').join(', ');
        html += '<div class="disc-card ' + cls + (armado === d.id ? ' armado' : '')
             + '" draggable="true" data-disc="' + d.id + '">'
             + '<div class="fw-semibold">' + esc(d.nome) + '</div>'
             + '<div class="text-muted d-flex flex-wrap align-items-center gap-1" style="font-size:11px">'
             + '<span>' + d.enc + '× de ' + dur(d.aulas * d.dur) + '</span>'
             + (d.ead > 0 ? '<span class="badge text-bg-info">' + d.ead + ' EaD</span>' : '')
             + (d.ndaId ? '<span class="badge text-bg-light border">' + esc(d.ndaNome) + '</span>' : '')
             + (d.qtdProfs > 1
                 ? '<span class="badge text-bg-warning">' + usados + '/' + d.qtdProfs + ' prof.</span>' : '')
             + (nomesProf ? '<span class="text-success"><i class="bi bi-person-check"></i> '
                 + esc(nomesProf) + '</span>' : '')
             + '</div></div>';
      }
    }
    pool.innerHTML = html;
  }

  function renderProfs() {
    const termo = $('#buscaProf').value.trim().toLowerCase();
    const soSubcarga = $('#soSubcarga').checked;
    // NDA em foco: o da disciplina sendo arrastada ou presa por clique.
    const focoDisc = arrastando ? discPorId.get(arrastando.discId)
                   : (armado !== null ? discPorId.get(armado) : null);
    const destacar = $('#destacarNda').checked && focoDisc && focoDisc.ndaId !== 0;

    const porNda = $('#ordemProf').value === 'nda';
    const visiveis = PROF
      .filter(p => !termo || (p.nome + ' ' + p.ndaNome).toLowerCase().includes(termo))
      // Aulas SEMANAIS = presenciais + EaD: as EaD não ocupam tempo na grade,
      // mas contam na carga semanal do professor (mesma regra do relatório).
      .filter(p => !soSubcarga
        || (c => c.aulas + c.ead < LIMITE_AULAS)(cargaDoProf(p.id)))
      .sort((a, b) => porNda
        // "Sem NDA" por último, como no resto do sistema; dentro do núcleo, A–Z.
        ? (a.ndaId === 0) - (b.ndaId === 0)
            || a.ndaNome.localeCompare(b.ndaNome, 'pt-BR')
            || a.nome.localeCompare(b.nome, 'pt-BR')
        : a.nome.localeCompare(b.nome, 'pt-BR'));

    let html = '';
    let ndaCorrente = null;
    for (const p of visiveis) {
      // Cabeçalho de núcleo só na ordem por NDA; em A–Z o NDA vai no cartão.
      if (porNda && p.ndaNome !== ndaCorrente) {
        ndaCorrente = p.ndaNome;
        html += '<div class="grupo-titulo">' + esc(ndaCorrente) + '</div>';
      }
      const c = cargaDoProf(p.id);

      const off = destacar && p.ndaId !== focoDisc.ndaId;
      const chips = DISC
        .filter(d => (atrib.get(d.id) || []).includes(p.id))
        .map(d => {
          const i = atrib.get(d.id).indexOf(p.id);
          const enc = fatia(d.enc, d.qtdProfs, i + 1);
          // Aulas DESTE professor nesta disciplina — numa disciplina dividida
          // é a fatia dele, não o total da disciplina.
          const eadDele = fatia(d.ead, d.qtdProfs, i + 1);
          const rotAulas = (enc * d.aulas) + (eadDele > 0 ? '+' + eadDele + ' EaD' : '') + ' aulas';
          const divergeNda = d.ndaId !== 0 && p.ndaId !== d.ndaId;
          return '<span class="disc-chip" draggable="true" data-disc="' + d.id + '" data-de="' + p.id + '"'
               + ' title="' + esc(d.nome) + ' — ' + esc(d.turma) + ' — ' + enc + ' encontro(s) por semana, '
               + rotAulas + '">'
               + (divergeNda ? '<i class="bi bi-exclamation-triangle-fill text-warning"'
                   + ' title="NDA diferente do da disciplina"></i>' : '')
               + '<span class="chip-txt">'
               +   '<span class="chip-nome">' + esc(d.nome)
               +     (d.qtdProfs > 1 ? ' <span class="badge text-bg-light border">dividida</span>' : '')
               +   '</span>'
               +   '<span class="chip-turma">' + esc(d.turma) + ' · ' + rotAulas + '</span>'
               + '</span>'
               + '<button type="button" class="btn-tirar" data-tirar="' + d.id + '" data-prof="' + p.id + '"'
               + ' title="Remover">&times;</button></span>';
        }).join('');

      html += '<div class="prof-card' + (off ? ' nda-off' : '') + (focoDisc ? ' alvo' : '')
           + '" data-prof="' + p.id + '" style="background:' + p.cor + '59">'
           + '<div class="prof-faixa-q" style="background:' + p.corSec + ';color:' + p.txtSec + '">'
           +   '<span class="prof-nome text-truncate" title="' + esc(p.nome) + '">'
           +     esc(p.nome) + '</span>'
           +   '<span class="prof-metricas">'
           +     '<span title="Disciplinas atribuídas">' + c.discs + ' disc.</span><span>·</span>'
           +     '<span title="Aulas por semana">' + c.aulas
           +       (c.ead > 0 ? '+' + c.ead + ' EaD' : '') + ' aulas</span><span>·</span>'
           +     '<span title="Carga relógio">' + (c.min ? dur(c.min) : '0h') + '</span>'
           +     (porNda ? ''   // na ordem por NDA o cabeçalho do grupo já informa
                   : '<span class="ms-auto text-truncate" title="' + esc(p.ndaNome) + '">'
                     + esc(p.ndaNome) + '</span>')
           +   '</span>'
           + '</div>'
           + '<div class="prof-corpo" style="color:' + p.txtPri + '">'
           +   (chips || '<span class="prof-vazio">Solte uma disciplina aqui</span>')
           + '</div></div>';
    }

    painelProfs.innerHTML = visiveis.length
      ? html
      : '<div class="grid-full text-muted small text-center py-4">Nenhum professor encontrado.</div>';
  }

  // Destaque de NDA mexendo SÓ em classes dos cartões já renderizados.
  // Durante um arrasto é proibido reconstruir o painel: o innerHTML removeria
  // do DOM o próprio chip que está sendo arrastado e o navegador cancelaria o
  // drag — era por isso que não dava para arrastar de um professor para outro
  // (da fila funcionava, porque a fila não era reconstruída no dragstart).
  function pintarDestaque(focoDisc) {
    const destacar = $('#destacarNda').checked && focoDisc && focoDisc.ndaId !== 0;
    painelProfs.querySelectorAll('.prof-card').forEach(card => {
      const p = profPorId.get(+card.dataset.prof);
      card.classList.toggle('nda-off', !!(destacar && p && p.ndaId !== focoDisc.ndaId));
      card.classList.toggle('alvo', !!focoDisc);
    });
  }

  // ── Drag & drop ─────────────────────────────────────────────
  // Delegação no documento: os painéis são reconstruídos a cada mudança,
  // então não dá para prender listener em cada bloco.
  document.addEventListener('dragstart', e => {
    const el = e.target.closest('.disc-card, .disc-chip');
    if (!el) return;
    arrastando = {
      discId: +el.dataset.disc,
      origemProfId: el.dataset.de ? +el.dataset.de : null,
    };
    armado = null;
    el.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(arrastando.discId));
    pintarDestaque(discPorId.get(arrastando.discId));
  });

  document.addEventListener('dragend', () => {
    arrastando = null;
    document.querySelectorAll('.dragging, .dragover')
            .forEach(el => el.classList.remove('dragging', 'dragover'));
    pintarDestaque(null);
  });

  document.addEventListener('dragover', e => {
    const card = e.target.closest('.prof-card');
    const napool = e.target.closest('#pool');
    if (!card && !napool) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (card) card.classList.add('dragover');
  });

  document.addEventListener('dragleave', e => {
    const card = e.target.closest('.prof-card');
    if (card) card.classList.remove('dragover');
  });

  document.addEventListener('drop', e => {
    if (!arrastando) return;
    const card = e.target.closest('.prof-card');
    const napool = e.target.closest('#pool');
    if (!card && !napool) return;
    e.preventDefault();

    const { discId, origemProfId } = arrastando;
    mutar(() => {
      if (card) {
        const destino = +card.dataset.prof;
        if (destino === origemProfId) return;
        // Mover de um professor para outro: tira do antigo antes, senão os
        // slots enchem e a atribuição é recusada em silêncio.
        if (origemProfId !== null) desatribuir(discId, origemProfId);
        if (!atribuir(discId, destino) && origemProfId !== null) {
          atribuir(discId, origemProfId);   // destino recusou: devolve
        }
      } else if (origemProfId !== null) {
        desatribuir(discId, origemProfId);  // soltou na fila = desatribuir
      }
    });
    arrastando = null;
    render();
  });

  // ── Clique: prender disciplina e soltar no professor ────────
  // Alternativa ao arrasto — com listas longas, rolar dois painéis ao mesmo
  // tempo segurando o mouse é inviável.
  document.addEventListener('click', e => {
    const tirar = e.target.closest('[data-tirar]');
    if (tirar) {
      mutar(() => desatribuir(+tirar.dataset.tirar, +tirar.dataset.prof));
      render();
      return;
    }

    const bloco = e.target.closest('.disc-card');
    if (bloco) {
      armado = armado === +bloco.dataset.disc ? null : +bloco.dataset.disc;
      render();
      return;
    }

    const card = e.target.closest('.prof-card');
    if (card && armado !== null) {
      if (mutar(() => atribuir(armado, +card.dataset.prof))) {
        const d = discPorId.get(armado);
        if (!pendente(d)) armado = null;   // completou os slots: solta a seleção
      }
      render();
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && armado !== null) { armado = null; render(); }
  });

  // ── Filtros ─────────────────────────────────────────────────
  $('#buscaDisc').addEventListener('input', renderPool);
  $('#soPendentes').addEventListener('change', renderPool);
  $('#buscaProf').addEventListener('input', renderProfs);
  $('#soSubcarga').addEventListener('change', renderProfs);

  // A ordem escolhida sobrevive ao Salvar (que recarrega a tela) e à sessão.
  const ORDEM_KEY = 'sgaQuadroOrdemProf';
  const ordemSalva = localStorage.getItem(ORDEM_KEY);
  if (ordemSalva === 'nda' || ordemSalva === 'nome') $('#ordemProf').value = ordemSalva;
  $('#ordemProf').addEventListener('change', e => {
    localStorage.setItem(ORDEM_KEY, e.target.value);
    renderProfs();
  });
  $('#destacarNda').addEventListener('change', renderProfs);
  $('#btnDesfazer').addEventListener('click', desfazer);

  // ── Salvar ──────────────────────────────────────────────────
  $('#formQuadro').addEventListener('submit', () => {
    // TODA disciplina precisa ir no POST, mesmo sem professor: salvarAtribuicoes()
    // apaga semestre_disciplina_salas e só regrava as chaves que chegarem —
    // uma disciplina ausente perderia a sala junto.
    const campos = [];
    for (const d of DISC) {
      const lista = atrib.get(d.id) || [];
      if (lista.length) {
        lista.forEach((profId, i) => {
          campos.push('<input type="hidden" name="atribuicao[' + d.id + '][' + (i + 1) + ']" value="' + profId + '">');
        });
      } else {
        campos.push('<input type="hidden" name="atribuicao[' + d.id + '][1]" value="">');
      }
      campos.push('<input type="hidden" name="sala[' + d.id + ']" value="' + d.sala + '">');
    }
    $('#camposOcultos').innerHTML = campos.join('');
    sujo = false;
  });

  window.addEventListener('beforeunload', e => {
    if (sujo) { e.preventDefault(); e.returnValue = ''; }
  });

  // O espaço acima dos painéis muda por três caminhos, e todos precisam
  // remedir — senão sobra faixa morta no rodapé ou a página passa de 100vh.
  window.addEventListener('resize', ajustarAltura);
  document.querySelectorAll('.alert').forEach(al => {
    // O app.js fecha o flash sozinho em 5s. O listener vai no PRÓPRIO alerta:
    // o Bootstrap remove o elemento antes de disparar closed.bs.alert, então
    // num listener no document o evento não chega (não há mais o que borbulhar).
    al.addEventListener('closed.bs.alert', ajustarAltura);
  });
  // Recolher a barra lateral muda a largura, não a altura da janela: não passa
  // pelo 'resize'. O observer pega esse caso (e é idempotente, não entra em laço).
  const wrapper = document.querySelector('.content-wrapper');
  if (wrapper && window.ResizeObserver) new ResizeObserver(ajustarAltura).observe(wrapper);

  render();
  ajustarAltura();
})();
</script>
