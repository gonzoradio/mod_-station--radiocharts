/* mod_ciwv_radiocharts – dashboard.js v2 */
document.addEventListener('DOMContentLoaded', function () {

  // ── Column index map (must match tmpl/default.php column order) ──────────
  // Columns: TW(0), NW(1), Artist(2), Title(3), WEEKS(4), CAT(5),
  //          Spins ATD(6), #Streams CA(7), #Streams Van(8),
  //          #Spins TW(9), #Stns TW(10), Avg Spins(11),
  //          MB Cht(12), Rk(13), Peak(14), BB SJ Chart(15),
  //          Freq/Listen ATD(16), Impres ATD(17)
  const COL = {
    tw:          0,
    nw:          1,
    artist:      2,
    title:       3,
    weeks:       4,
    cat:         5,
    spins_atd:   6,
    streams_ca:  7,
    streams_van: 8,
    spins_tw:    9,
    stns_tw:     10,
    avg_spins:   11,
    mb_cht:      12,
    rk:          13,
    peak:        14,
    bb_sj:       15,
    freq_atd:    16,
    imp_atd:     17
  };

  // Custom sort order for TW category
  const TW_ORDER = ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT',''];

  // TW and NW option lists (must match helper.php)
  const TW_VALS = ['','A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT'];
  const NW_VALS = ['','A1','J','A2','P','B','C','D','GOLD','PC2','PC3','HOLD','ADD','Q','OUT',
                   'A1?','J?','A2?','P?','B?','C?','D?','GOLD?','PC2?','PC3?','Q?','OUT?'];
  const CAT_VALS = ['','1','2','3','S','PSG','G','F','GS','GP','P','V','T','TG','SP','TS','GT'];

  // ── Helpers ────────────────────────────────────────────────────────────────

  function getTable() { return document.querySelector('.rc-dashboard-table'); }
  function getTBody() { const t = getTable(); return t ? t.tBodies[0] : null; }

  // Match PHP's normalize() for artist|title key generation
  function normalizeKey(artist, title) {
    let a = artist.trim().toLowerCase();
    let t = title.trim().toLowerCase();
    if (typeof a.normalize === 'function') {
      a = a.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    a = a.replace(/\s*&\s*|\s+and\s+/gi, ',');
    a = a.replace(/\s*(feat\.|ft\.|featuring|f\/)\s*.+$/i, '');
    t = t.replace(/\s*(f\/|feat\.|ft\.|featuring)\s*.+$/i, '');
    t = t.replace(/\.\.\.$/, '');
    a = a.replace(/[\"'()\[\].,!?\-]/g, '').replace(/\s+/g, ' ').replace(/^,|,$/g, '').trim();
    t = t.replace(/[\"'()\[\].,!?\-]/g, '').trim();
    return a + '|' + t;
  }

  // Get value from a cell that may contain a <select>
  function selectValue(row, col) {
    const cell = row.cells[col];
    if (!cell) return '';
    const sel = cell.querySelector('select');
    return sel ? (sel.value || '') : cell.textContent.trim();
  }

  // ── Week selector ──────────────────────────────────────────────────────────
  const weekSelect = document.getElementById('rc-week-select');
  if (weekSelect) {
    weekSelect.addEventListener('change', function () {
      const v = weekSelect.value;
      location.href = location.pathname + '?week_start=' + encodeURIComponent(v);
    });
  }

  // Read module options injected via Joomla.addScriptOptions
  const rcOpts       = (typeof Joomla !== 'undefined' && Joomla.getOptions)
                       ? (Joomla.getOptions('rciwv') || {}) : {};
  const weekStart    = rcOpts.weekStart    || window.RCIWV_WEEK_START    || '';
  const selectedWeek = rcOpts.selectedWeek || window.RCIWV_SELECTED_WEEK || 'current';

  // ── Sorting ────────────────────────────────────────────────────────────────
  const sortSelect   = document.getElementById('rc-sort-select');
  const reverseCheck = document.getElementById('rc-reverse-sort');

  function sortTable() {
    const tbody = getTBody();
    if (!tbody) return;
    const rows   = Array.from(tbody.rows);
    const sortBy = sortSelect ? sortSelect.value : 'tw';

    if (sortBy === 'tw') {
      rows.sort((a, b) => {
        const vA = selectValue(a, COL.tw);
        const vB = selectValue(b, COL.tw);
        const iA = TW_ORDER.indexOf(vA);
        const iB = TW_ORDER.indexOf(vB);
        return (iA === -1 ? TW_ORDER.length : iA) - (iB === -1 ? TW_ORDER.length : iB);
      });
    } else {
      const colMap = {
        artist: COL.artist, title: COL.title, weeks: COL.weeks,
        spins_atd: COL.spins_atd, streams_ca: COL.streams_ca, streams_van: COL.streams_van,
        spins_tw: COL.spins_tw, stns_tw: COL.stns_tw, avg_spins: COL.avg_spins,
        rk: COL.rk
      };
      const colIdx = colMap[sortBy];
      if (colIdx !== undefined) {
        rows.sort((a, b) => {
          const cA = (a.cells[colIdx]?.textContent.trim() ?? '').replace(/,/g, '');
          const cB = (b.cells[colIdx]?.textContent.trim() ?? '').replace(/,/g, '');
          if (cA === '' && cB !== '') return 1;
          if (cB === '' && cA !== '') return -1;
          if (!isNaN(cA) && !isNaN(cB) && cA !== '' && cB !== '') {
            return parseFloat(cA) - parseFloat(cB);
          }
          return cA.localeCompare(cB, undefined, { numeric: true, sensitivity: 'base' });
        });
      }
    }

    if (reverseCheck && reverseCheck.checked) rows.reverse();
    rows.forEach(r => tbody.appendChild(r));
  }

  function initialSort() {
    const tbody = getTBody();
    if (!tbody) return;
    const rows  = Array.from(tbody.rows);
    const hasTW = rows.some(r => {
      const sel = r.cells[COL.tw]?.querySelector('select');
      return sel && sel.value !== '';
    });
    if (sortSelect) sortSelect.value = hasTW ? 'tw' : 'rk';
    sortTable();
  }

  if (sortSelect)   sortSelect.addEventListener('change', sortTable);
  if (reverseCheck) reverseCheck.addEventListener('change', sortTable);

  // ── Load saved TW / NW / CAT from DB ──────────────────────────────────────
  if (weekStart) {
    fetch('/modules/mod_ciwv_radiocharts/ajax/loadstate.php?week_start=' + encodeURIComponent(weekStart))
      .then(r => r.json())
      .then(savedState => {
        const stateMap = {};
        if (Array.isArray(savedState)) {
          savedState.forEach(entry => {
            const k = normalizeKey(entry.artist ?? '', entry.title ?? '');
            stateMap[k] = entry;
          });
        }
        const tbody = getTBody();
        if (!tbody) return;
        Array.from(tbody.rows).forEach(row => {
          const artist = row.cells[COL.artist]?.textContent.trim() || '';
          const title  = row.cells[COL.title]?.textContent.trim()  || '';
          const k      = normalizeKey(artist, title);
          if (stateMap[k]) {
            const twSel  = row.querySelector('select.rc-sel-tw');
            const nwSel  = row.querySelector('select.rc-sel-nw');
            const catSel = row.querySelector('select.rc-sel-cat');
            if (twSel)  twSel.value  = stateMap[k].tw  || '';
            if (nwSel)  nwSel.value  = stateMap[k].nw  || '';
            // Only override the PHP-pre-selected CAT (from MusicMaster CSV) when
            // the saved value is explicitly non-empty; avoids clearing it with
            // empty values from state saved before the CAT field was introduced.
            if (catSel && stateMap[k].cat) catSel.value = stateMap[k].cat;
          }
        });
        initialSort();
      })
      .catch(() => initialSort());
  } else {
    initialSort();
  }

  // ── Save Data ──────────────────────────────────────────────────────────────
  document.getElementById('rc-save-state')?.addEventListener('click', function () {
    const tbody = getTBody();
    if (!tbody) return;

    const stateRows = Array.from(tbody.rows).map(row => ({
      tw:              selectValue(row, COL.tw),
      nw:              selectValue(row, COL.nw),
      artist:          row.cells[COL.artist]?.textContent.trim()      ?? '',
      title:           row.cells[COL.title]?.textContent.trim()       ?? '',
      weeks:           row.cells[COL.weeks]?.textContent.trim()       ?? '',
      cat:             selectValue(row, COL.cat),
      'Spins ATD':     row.cells[COL.spins_atd]?.textContent.trim()   ?? '',
      '#Streams CA':   row.cells[COL.streams_ca]?.textContent.trim()  ?? '',
      '#Streams Van':  row.cells[COL.streams_van]?.textContent.trim() ?? '',
      '#Spins TW':     row.cells[COL.spins_tw]?.textContent.trim()    ?? '',
      '#Stns TW':      row.cells[COL.stns_tw]?.textContent.trim()     ?? '',
      'Avg Spins':     row.cells[COL.avg_spins]?.textContent.trim()   ?? '',
      'MB Cht':        row.cells[COL.mb_cht]?.textContent.trim()      ?? '',
      'Rk':            row.cells[COL.rk]?.textContent.trim()          ?? '',
      'Peak':          row.cells[COL.peak]?.textContent.trim()        ?? '',
      'BB SJ Chart':   row.cells[COL.bb_sj]?.textContent.trim()      ?? '',
      'Freq/Listen ATD': row.cells[COL.freq_atd]?.textContent.trim()  ?? '',
      'Impres ATD':    row.cells[COL.imp_atd]?.textContent.trim()     ?? '',
    }));

    const metaElem = document.getElementById('rc-meta-line');
    const metaLine = metaElem ? metaElem.textContent.trim() : '';

    fetch('/modules/mod_ciwv_radiocharts/ajax/savestate.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ state: stateRows, week_start: weekStart, meta_line: metaLine })
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        alert('Saved for week: ' + d.week_start);
        // If the week selector doesn't already have this week, add it
        if (weekSelect && weekStart) {
          const existing = Array.from(weekSelect.options).some(o => o.value === d.week_start);
          if (!existing) {
            const opt = document.createElement('option');
            opt.value       = d.week_start;
            opt.textContent = d.week_start;
            weekSelect.insertBefore(opt, weekSelect.options[1]);
          }
        }
      } else {
        alert('Save failed: ' + (d.error || 'unknown error'));
      }
    })
    .catch(() => alert('Save failed – could not reach server.'));
  });

  // ── Export CSV ─────────────────────────────────────────────────────────────
  document.getElementById('rc-export-csv')?.addEventListener('click', function () {
    const tbody = getTBody();
    if (!tbody) return;

    const allRows = Array.from(tbody.rows);

    // Group rows by TW category; rows with no TW/NW go into a "Considerations" group
    const groups     = {};
    const addsByNW   = {};
    const noCategory = [];
    ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3'].forEach(c => {
      groups[c]   = [];
      addsByNW[c] = [];
    });
    const others = [];

    allRows.forEach(row => {
      const tw = selectValue(row, COL.tw);
      const nw = selectValue(row, COL.nw);
      if (tw === 'ADD' && addsByNW[nw]) {
        addsByNW[nw].push(row);
      } else if (groups[tw]) {
        groups[tw].push(row);
      } else if (tw !== '' || nw !== '') {
        others.push(row);
      } else {
        noCategory.push(row);
      }
    });

    const finalRows = [];
    ['A1','J','A2','P','B','C','D','GOLD','PC2','PC3'].forEach(k => {
      finalRows.push(...groups[k], ...addsByNW[k]);
    });
    finalRows.push(...others, ...noCategory);

    // Header rows matching final-output-example.csv layout
    const h1 = ['','','','','','','Spins','OD Streams','','National','','','Chart Info','','','','',''];
    const h2 = ['TW','NW','Artist','Title','WEEKS','CAT','ATD',
                '#Streams CA','#Streams Van','#Spins TW','#Stns TW','Avg Spins',
                'MB Cht','Rk','Peak','BB SJ Chart','Freq/Listen ATD','Impres ATD'];

    function escCSV(v) {
      if (v == null) return '';
      v = String(v).replace(/"/g, '""');
      return (v.match(/("|,|\n)/)) ? '"' + v + '"' : v;
    }

    const lines = [h1.map(escCSV).join(','), h2.map(escCSV).join(',')];
    finalRows.forEach(row => {
      const cells = Array.from(row.cells).map((cell, idx) => {
        const sel = cell.querySelector('select');
        return sel ? sel.value : cell.textContent.trim();
      });
      lines.push(cells.map(escCSV).join(','));
    });

    const BOM  = '\uFEFF';
    const blob = new Blob([BOM + lines.join('\r\n')], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const wkLabel = (weekStart && weekStart !== 'current') ? weekStart : 'current';
    a.href     = url;
    a.download = 'CIWV_radiocharts_' + wkLabel + '.csv';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
  });

  // ── Manual Add Row ─────────────────────────────────────────────────────────
  function makeSelect(name, cssClass, vals) {
    const sel = document.createElement('select');
    sel.name      = name;
    sel.className = cssClass;
    vals.forEach(v => {
      const opt = document.createElement('option');
      opt.value = v; opt.textContent = v;
      sel.appendChild(opt);
    });
    return sel;
  }

  document.getElementById('rc-add-manual-row')?.addEventListener('click', function () {
    const artist = document.getElementById('rc-manual-artist')?.value.trim() || '';
    const title  = document.getElementById('rc-manual-title')?.value.trim()  || '';
    const weeks  = document.getElementById('rc-manual-weeks')?.value.trim()  || '';
    if (!artist || !title) { alert('Artist and Title are required.'); return; }

    const tbody = getTBody();
    if (!tbody) return;

    const tr = document.createElement('tr');
    const addTd = v => { const td = document.createElement('td'); td.textContent = v; tr.appendChild(td); return td; };
    const addSelTd = (name, cls, vals) => { const td = document.createElement('td'); td.appendChild(makeSelect(name, cls, vals)); tr.appendChild(td); return td; };

    addSelTd('TW[]', 'rc-sel-tw', TW_VALS);
    addSelTd('NW[]', 'rc-sel-nw', NW_VALS);
    addTd(artist);
    addTd(title);
    addTd(weeks);
    addSelTd('CAT[]', 'rc-sel-cat', CAT_VALS);
    // Remaining data columns (blank)
    for (let i = 6; i < Object.keys(COL).length; i++) addTd('');

    tbody.appendChild(tr);

    document.getElementById('rc-manual-artist').value = '';
    document.getElementById('rc-manual-title').value  = '';
    document.getElementById('rc-manual-weeks').value  = '';
  });

});
