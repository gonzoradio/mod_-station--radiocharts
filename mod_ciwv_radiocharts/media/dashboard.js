/* mod_ciwv_radiocharts – dashboard.js v4 */
document.addEventListener('DOMContentLoaded', function () {

  // ── Column index map (must match tmpl/default.php column order) ──────────
  // Columns: TW(0), NW(1), Artist(2), Title(3), WEEKS(4), CAT(5),
  //          Spins TW(6), Spins ATD(7), #Streams CA(8), #Streams Van(9),
  //          #Spins TW(10), #Stns TW(11), Avg Spins(12),
  //          MB Cht(13), Rk(14), Peak(15), BB SJ Chart(16),
  //          Freq/Listen ATD(17), Impres ATD(18)
  const COL = {
    tw:          0,
    nw:          1,
    artist:      2,
    title:       3,
    weeks:       4,
    cat:         5,
    spins_tw:    6,
    spins_atd:   7,
    streams_ca:  8,
    streams_van: 9,
    nat_spins_tw: 10,
    stns_tw:     11,
    avg_spins:   12,
    mb_cht:      13,
    rk:          14,
    peak:        15,
    bb_sj:       16,
    freq_atd:    17,
    imp_atd:     18
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
      // Text columns sort ascending by default; numeric columns sort descending (highest first).
      // Empty cells always sink to the bottom regardless of the Reverse checkbox.
      const textCols = { artist: COL.artist, title: COL.title };
      const numCols  = {
        weeks: COL.weeks,
        spins_tw: COL.spins_tw, spins_atd: COL.spins_atd, streams_ca: COL.streams_ca, streams_van: COL.streams_van,
        nat_spins_tw: COL.nat_spins_tw, stns_tw: COL.stns_tw, avg_spins: COL.avg_spins,
        mb_cht: COL.mb_cht, rk: COL.rk, peak: COL.peak,
        bb_sj: COL.bb_sj, freq_atd: COL.freq_atd, imp_atd: COL.imp_atd
      };
      const isNumeric = sortBy in numCols;
      const colIdx    = isNumeric ? numCols[sortBy] : textCols[sortBy];
      if (colIdx !== undefined) {
        // Separate rows with data from rows with empty cells
        const filled = [], empty = [];
        rows.forEach(r => {
          const v = (r.cells[colIdx]?.textContent.trim() ?? '').replace(/,/g, '');
          (v === '' ? empty : filled).push(r);
        });
        const reverse = reverseCheck && reverseCheck.checked;
        if (isNumeric) {
          // Descending by default (highest first); Reverse → ascending
          filled.sort((a, b) => {
            const nA = parseFloat((a.cells[colIdx]?.textContent.trim() ?? '').replace(/,/g, '')) || 0;
            const nB = parseFloat((b.cells[colIdx]?.textContent.trim() ?? '').replace(/,/g, '')) || 0;
            return reverse ? nA - nB : nB - nA;
          });
        } else {
          // Ascending by default; Reverse → descending
          filled.sort((a, b) => {
            const cA = a.cells[colIdx]?.textContent.trim() ?? '';
            const cB = b.cells[colIdx]?.textContent.trim() ?? '';
            const cmp = cA.localeCompare(cB, undefined, { numeric: true, sensitivity: 'base' });
            return reverse ? -cmp : cmp;
          });
        }
        // Rebuild rows: sorted filled rows first, then empties always last
        rows.length = 0;
        filled.forEach(r => rows.push(r));
        empty.forEach(r => rows.push(r));
        rows.forEach(r => tbody.appendChild(r));
        return; // skip the generic reverse+append below
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
            // Restore green Rk highlight when it was set on save
            if (stateMap[k].rk_green && row.cells[COL.rk]) {
              row.cells[COL.rk].classList.add('rc-rk-up');
            }
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
      artist:          row.cells[COL.artist]?.textContent.trim()         ?? '',
      title:           row.cells[COL.title]?.textContent.trim()          ?? '',
      weeks:           row.cells[COL.weeks]?.textContent.trim()          ?? '',
      cat:             selectValue(row, COL.cat),
      'Spins TW':      row.cells[COL.spins_tw]?.textContent.trim()       ?? '',
      'Spins ATD':     row.cells[COL.spins_atd]?.textContent.trim()      ?? '',
      '#Streams CA':   row.cells[COL.streams_ca]?.textContent.trim()     ?? '',
      '#Streams Van':  row.cells[COL.streams_van]?.textContent.trim()    ?? '',
      '#Spins TW':     row.cells[COL.nat_spins_tw]?.textContent.trim()   ?? '',
      '#Stns TW':      row.cells[COL.stns_tw]?.textContent.trim()        ?? '',
      'Avg Spins':     row.cells[COL.avg_spins]?.textContent.trim()      ?? '',
      'MB Cht':        row.cells[COL.mb_cht]?.textContent.trim()         ?? '',
      'Rk':            row.cells[COL.rk]?.textContent.trim()             ?? '',
      'Peak':          row.cells[COL.peak]?.textContent.trim()           ?? '',
      'BB SJ Chart':   row.cells[COL.bb_sj]?.textContent.trim()         ?? '',
      'Freq/Listen ATD': row.cells[COL.freq_atd]?.textContent.trim()     ?? '',
      'Impres ATD':    row.cells[COL.imp_atd]?.textContent.trim()        ?? '',
      rk_green:        row.cells[COL.rk]?.classList.contains('rc-rk-up') ?? false,
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
    const h1 = ['','','','','','','Spins','','OD Streams','','National','','','Chart Info','','','','',''];
    const h2 = ['TW','NW','Artist','Title','WEEKS','CAT','TW','ATD',
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

  // ── Row Editor ─────────────────────────────────────────────────────────────

  // Build a <select> element (reused by Add Row to create table cells with selects)
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

  // Ordered map of editor field IDs → { column index, whether it is a <select> }
  const EDITOR_FIELDS = [
    { id: 're-tw',           col: COL.tw,           isSelect: true  },
    { id: 're-nw',           col: COL.nw,           isSelect: true  },
    { id: 're-artist',       col: COL.artist,       isSelect: false },
    { id: 're-title',        col: COL.title,        isSelect: false },
    { id: 're-weeks',        col: COL.weeks,        isSelect: false },
    { id: 're-cat',          col: COL.cat,          isSelect: true  },
    { id: 're-spins-tw',     col: COL.spins_tw,     isSelect: false },
    { id: 're-spins-atd',    col: COL.spins_atd,    isSelect: false },
    { id: 're-streams-ca',   col: COL.streams_ca,   isSelect: false },
    { id: 're-streams-van',  col: COL.streams_van,  isSelect: false },
    { id: 're-nat-spins-tw', col: COL.nat_spins_tw, isSelect: false },
    { id: 're-stns-tw',      col: COL.stns_tw,      isSelect: false },
    { id: 're-avg-spins',    col: COL.avg_spins,    isSelect: false },
    { id: 're-mb-cht',       col: COL.mb_cht,       isSelect: false },
    { id: 're-rk',           col: COL.rk,           isSelect: false },
    { id: 're-peak',         col: COL.peak,         isSelect: false },
    { id: 're-bb-sj',        col: COL.bb_sj,        isSelect: false },
    { id: 're-freq-atd',     col: COL.freq_atd,     isSelect: false },
    { id: 're-imp-atd',      col: COL.imp_atd,      isSelect: false },
  ];

  function clearEditor() {
    EDITOR_FIELDS.forEach(f => {
      const el = document.getElementById(f.id);
      if (el) el.value = '';
    });
    const status = document.getElementById('rc-editor-status');
    if (status) status.textContent = '';
  }

  // Build a new <tr> from the current editor field values
  function buildRowFromEditor() {
    const tr = document.createElement('tr');
    EDITOR_FIELDS.forEach(f => {
      const td  = document.createElement('td');
      const el  = document.getElementById(f.id);
      const val = el ? el.value.trim() : '';
      if (f.isSelect) {
        const selName = f.id === 're-tw' ? 'TW[]' : f.id === 're-nw' ? 'NW[]' : 'CAT[]';
        const selCls  = f.id === 're-tw' ? 'rc-sel-tw' : f.id === 're-nw' ? 'rc-sel-nw' : 'rc-sel-cat';
        const selVals = f.id === 're-tw' ? TW_VALS : f.id === 're-nw' ? NW_VALS : CAT_VALS;
        const sel     = makeSelect(selName, selCls, selVals);
        sel.value     = val;
        td.appendChild(sel);
      } else {
        td.textContent = val;
      }
      tr.appendChild(td);
    });
    return tr;
  }

  // Update every cell of an existing row from the current editor values
  function updateRowInPlace(tr) {
    EDITOR_FIELDS.forEach(f => {
      const td  = tr.cells[f.col];
      if (!td) return;
      const el  = document.getElementById(f.id);
      const val = el ? el.value.trim() : '';
      if (f.isSelect) {
        const sel = td.querySelector('select');
        if (sel) sel.value = val;
      } else {
        td.textContent = val;
        // Clear any stale highlight on the Rk cell; user can re-save to restore it
        if (f.col === COL.rk) td.classList.remove('rc-rk-up');
      }
    });
  }

  // Click-to-edit: populate editor from the clicked tbody row
  const editorEl = document.getElementById('rc-row-editor');
  const tbodyEl  = getTBody();
  if (editorEl && tbodyEl) {
    tbodyEl.addEventListener('click', function (e) {
      const row = e.target.closest('tr');
      if (!row || row.parentElement !== tbodyEl) return;
      EDITOR_FIELDS.forEach(f => {
        const el = document.getElementById(f.id);
        if (el) el.value = selectValue(row, f.col);
      });
      const artist = document.getElementById('re-artist')?.value || '';
      const title  = document.getElementById('re-title')?.value  || '';
      const status = document.getElementById('rc-editor-status');
      if (status) status.textContent = 'Editing: ' + artist + ' \u2013 ' + title;
    });
  }

  // Add Row: append a brand-new row built from editor values
  document.getElementById('rc-add-row')?.addEventListener('click', function () {
    const artist = document.getElementById('re-artist')?.value.trim() || '';
    const title  = document.getElementById('re-title')?.value.trim()  || '';
    if (!artist || !title) { alert('Artist and Title are required.'); return; }
    const tbody = getTBody();
    if (!tbody) return;
    tbody.appendChild(buildRowFromEditor());
    clearEditor();
  });

  // Update Row: find an existing row by Artist+Title and replace its cell values
  document.getElementById('rc-update-row')?.addEventListener('click', function () {
    const artist = document.getElementById('re-artist')?.value.trim() || '';
    const title  = document.getElementById('re-title')?.value.trim()  || '';
    if (!artist || !title) { alert('Artist and Title are required.'); return; }
    const tbody = getTBody();
    if (!tbody) return;
    const targetKey = normalizeKey(artist, title);
    let matchRow = null;
    Array.from(tbody.rows).forEach(row => {
      const rArtist = row.cells[COL.artist]?.textContent.trim() || '';
      const rTitle  = row.cells[COL.title]?.textContent.trim()  || '';
      if (normalizeKey(rArtist, rTitle) === targetKey) matchRow = row;
    });
    if (!matchRow) {
      alert('No matching row found for \u201c' + artist + ' \u2013 ' + title + '\u201d. Use Add Row to create a new entry.');
      return;
    }
    updateRowInPlace(matchRow);
    clearEditor();
  });

});
