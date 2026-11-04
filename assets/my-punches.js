(() => {
  'use strict';
  const openers = [...document.querySelectorAll('[data-my-punches-open]')];
  const summaries = [...document.querySelectorAll('[data-my-punches-summary]')];
  if (!openers.length && !summaries.length) return;
  const dialog = document.createElement('dialog');
  dialog.className = 'my-punches-dialog';
  dialog.setAttribute('aria-labelledby', 'my-punches-title');
  dialog.innerHTML = `<div class="my-punches-heading"><div><h2 id="my-punches-title">My punches</h2><p>Every recorded IN and OUT, with the exact time.</p></div><button type="button" data-close aria-label="Close punch history">Close</button></div>
    <div class="my-punches-filters"><label>Shift start date<input type="date" data-date></label><button type="button" data-current>Current shift</button><button type="button" data-refresh>Refresh</button></div>
    <p class="my-punches-meta" data-context>Loading…</p>
    <div class="my-punches-counts"><div><span>Total punches</span><strong data-count="total">—</strong></div><div><span>IN punches</span><strong data-count="in">—</strong></div><div><span>OUT punches</span><strong data-count="out">—</strong></div></div>
    <p class="my-punches-message" data-message role="status"></p>
    <div class="my-punches-table-wrap" tabindex="0" role="region" aria-label="Punch times"><table><caption class="my-punches-sr">Punches in chronological order</caption><thead><tr><th scope="col">Punch time</th><th scope="col">IN / OUT</th><th scope="col">Source &amp; details</th></tr></thead><tbody data-rows></tbody></table></div>
    <p class="my-punches-meta">Overnight punches stay with the shift start date. Inferred directions follow the attendance punch sequence. Only punches received and linked to your account appear here.</p>`;
  document.body.append(dialog);
  const q = s => dialog.querySelector(s);
  const input = q('[data-date]');
  let selected = '', currentData = null, requestId = 0, currentBusy = false, origin = null;
  const set = (root, selector, value) => { root.querySelector(selector).textContent = value; };
  const counts = (root, attr, data) => ['total', 'in', 'out'].forEach(k => set(root, `[${attr}="${k}"]`, data.counts[k]));
  const context = data => `Shift ${data.date} · ${data.timezone} · Updated ${data.updated_label}`;
  async function fetchData(date) {
    const response = await fetch('my-punches.php' + (date ? '?date=' + encodeURIComponent(date) : ''), {credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(15000)});
    if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Please sign in again to view your punches.');
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Unable to load punch history.');
    if (!Array.isArray(data.punches) || !data.counts) throw new Error('Unable to load punch history.');
    return data;
  }
  function render(data) {
    input.max = data.today; input.value = data.date;
    set(dialog, '[data-context]', context(data)); counts(dialog, 'data-count', data);
    const notes = [];
    if (data.message) notes.push(data.message);
    else if (!data.punches.length) notes.push('No punches received for this shift. If you scanned, ask HR to check the device sync and employee mapping.');
    if (data.counts.unknown) notes.push(`${data.counts.unknown} unclassified punch(es) included in the total; IN / OUT needs review.`);
    if (data.corrected) notes.push('Attendance has a correction. These are the original punches, not edited working-time boundaries.');
    set(dialog, '[data-message]', notes.join(' '));
    const rows = document.createDocumentFragment();
    data.punches.forEach(p => {
      const tr = document.createElement('tr');
      const timeCell = document.createElement('td'), time = document.createElement('time'), date = document.createElement('small');
      time.dateTime = p.at; time.textContent = p.time_label; date.textContent = p.date_label;
      timeCell.append(time, date);
      const direction = document.createElement('td'), badge = document.createElement('span');
      badge.className = 'my-punches-badge ' + (['in','out'].includes(p.direction) ? p.direction : 'unknown');
      badge.textContent = p.direction === 'in' ? 'IN' : p.direction === 'out' ? 'OUT' : 'Unclassified'; direction.append(badge);
      const detail = document.createElement('td'), source = document.createElement('strong'), note = document.createElement('small');
      source.textContent = p.source; note.textContent = p.note; detail.append(source, note);
      tr.append(timeCell, direction, detail); rows.append(tr);
    });
    q('[data-rows]').replaceChildren(rows);
  }
  async function loadDialog() {
    const id = ++requestId, date = selected;
    dialog.setAttribute('aria-busy', 'true');
    set(dialog, '[data-message]', 'Loading punch history…');
    try { const data = await fetchData(date); if (id === requestId && dialog.open) render(data); }
    catch (e) { if (id === requestId && dialog.open) set(dialog, '[data-message]', `${e.message} Any displayed records are from the last successful refresh.`); }
    finally { if (id === requestId) dialog.removeAttribute('aria-busy'); }
  }
  async function loadCurrent() {
    if (!summaries.length || currentBusy) return;
    currentBusy = true;
    try {
      currentData = await fetchData('');
      summaries.forEach(root => { counts(root, 'data-punch-count', currentData); set(root, '[data-punch-summary-note]', currentData.message || context(currentData) + (currentData.counts.unknown ? ` · ${currentData.counts.unknown} unclassified — view details` : ' · Updates every 10 seconds')); });
      if (dialog.open && selected === '') render(currentData);
    } catch (e) {
      summaries.forEach(root => set(root, '[data-punch-summary-note]', e.message + (currentData ? ` Last successful update: ${currentData.updated_label}.` : '')));
      if (dialog.open && selected === '') set(dialog, '[data-message]', e.message + ' Displayed records may be outdated.');
    } finally { currentBusy = false; }
  }
  openers.forEach(button => button.addEventListener('click', () => { origin = button; selected = ''; dialog.showModal(); loadDialog(); }));
  q('[data-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { ++requestId; dialog.removeAttribute('aria-busy'); origin?.focus(); });
  input.addEventListener('change', () => {
    if (!input.value || !input.checkValidity()) { input.reportValidity(); return; }
    selected = input.value;
    // Never leave a different date's rows beneath the newly selected date.
    q('[data-rows]').replaceChildren(); ['total','in','out'].forEach(k => set(dialog, `[data-count="${k}"]`, '—'));
    set(dialog, '[data-context]', `Shift ${selected}`); loadDialog();
  });
  q('[data-current]').addEventListener('click', () => { selected = ''; q('[data-rows]').replaceChildren(); ['total','in','out'].forEach(k => set(dialog, `[data-count="${k}"]`, '—')); set(dialog, '[data-context]', 'Current shift'); loadDialog(); });
  q('[data-refresh]').addEventListener('click', loadDialog);
  loadCurrent();
  setInterval(() => {
    if (document.hidden) return;
    if (summaries.length) loadCurrent();
    else if (dialog.open && selected === '') loadDialog();
  }, 10000);
})();
