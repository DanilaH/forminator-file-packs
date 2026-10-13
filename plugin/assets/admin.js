/* global FFP */
(() => {
	'use strict';
	const app = document.getElementById('ffp-app');
	if (!app || !document.getElementById('ffp-selection')) return;
	const $ = id => document.getElementById(`ffp-${id}`);
	const s = FFP.strings;
	const selected = new Set();
	const selectedRows = new Map();
	let retry = null, activeController = null, ready = false, periods = {}, downloading = false, currentPeriod = 'all';
 const help = app.querySelector('.ffp-help');
 try { if (localStorage.getItem('ffp.helpOpen') === '1') help.open = true; } catch (e) { /* Storage is optional. */ }
 help.addEventListener('toggle', () => { try { localStorage.setItem('ffp.helpOpen', help.open ? '1' : '0'); } catch (e) { /* Storage is optional. */ } });
 window.addEventListener('beforeunload', e => { if (downloading) { e.preventDefault(); e.returnValue = ''; } });
	let page = 1, pages = 1, rows = [], preview = null, busy = false, filterDirty = false, allPeriod = false, totalRows = 0;
 const jobHeadingLabel = $('job-heading').textContent;
 let job = null, jobPage = 1, jobPages = 1, jobTimer = null, jobPolling = false;
	const node = (tag, text, cls) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (cls) el.className = cls; return el; };
	function size(bytes) { return bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KiB` : `${(bytes / 1048576).toFixed(2)} MiB`; }
	function message(text, error = false) {
  $('status').textContent = text; $('notification').hidden = !text;
  $('notification').classList.toggle('ffp-error', error); $('status').setAttribute('role', error ? 'alert' : 'status');
  $('retry').hidden = !error || !retry;
  if (retry) $('retry').textContent = retry.label;
 }
 function clearRecovery() { retry = null; message(''); }
 function clearSelection() { allPeriod = false; selected.clear(); selectedRows.clear(); }
 function reviewSelection() {
  $('selected-list').replaceChildren();
  for (const id of [...selected].slice(0, 100)) {
   const row = selectedRows.get(id); const li = node('li', `#${id} · ${row?.display_date || row?.date || ''} · ${row?.summary || '—'} `);
   const remove = node('button', `${s.remove} #${id}`, 'button-link'); remove.type = 'button'; remove.disabled = busy || filterDirty;
   remove.addEventListener('click', () => { clearRecovery(); selected.delete(id); selectedRows.delete(id); $('rows').querySelectorAll('input').forEach(box => { if (box.dataset.entry === String(id)) box.checked = false; }); invalidate(); message(''); $('selected-review').querySelector('summary').focus(); });
   li.append(remove); $('selected-list').append(li);
  }
  $('selection-help').textContent = allPeriod ? s.allPeriod : selected.size > 100 ? s.reviewMore : selected.size ? '' : s.emptySelection;
 }

	function refreshButtons() {
		app.querySelectorAll('button,input,select').forEach(el => { el.disabled = busy; });
		$('cancel').hidden = !busy; $('cancel').disabled = !busy || !activeController;
  for (const [id, period] of [['reset-period', 'all'], ['week', 'week'], ['month', 'month']]) $(id).setAttribute('aria-pressed', String(currentPeriod === period));
  $('week').disabled = busy || !periods.week; $('month').disabled = busy || !periods.month;
		$('prev').disabled = busy || filterDirty || page <= 1; $('next').disabled = busy || filterDirty || page >= pages;
		$('preview-button').textContent = preview ? s.recheckSelection : s.checkSelection;
  $('preview-button').classList.toggle('button-primary', !preview);
  $('download').textContent = preview?.warnings.length ? s.downloadPartial : s.downloadFull;
  $('preview-button').disabled = busy || !ready || filterDirty || (!selected.size && !allPeriod) || activeJob();
		$('download').disabled = busy || !ready || filterDirty || !preview || (preview.warnings.length > 0 && !$('partial').checked);
		$('all').disabled = busy || filterDirty || !rows.length || allPeriod;
  $('select-period').disabled = busy || filterDirty || !totalRows || activeJob();
		$('rows').querySelectorAll('input').forEach(el => { el.disabled = busy || filterDirty || allPeriod; });
		$('all').checked = rows.length > 0 && (allPeriod || rows.every(row => selected.has(row.id)));
		$('all').indeterminate = rows.some(row => selected.has(row.id)) && !$('all').checked;
		$('count').textContent = `${s.selected} ${allPeriod ? totalRows : selected.size}`;
  $('count').dataset.count = String(allPeriod ? totalRows : selected.size);
  reviewSelection();
  $('job-start').disabled = busy || jobPolling || job?.status !== 'review' || (job.warnings > 0 && !$('job-partial').checked);
  $('job-cancel').disabled = busy || jobPolling;
  $('job-prev').disabled = busy || jobPolling || jobPage <= 1; $('job-next').disabled = busy || jobPolling || jobPage >= jobPages;
	}
	function invalidate() { $('result').hidden = true; preview = null; $('preview').hidden = true; $('partial').checked = false; refreshButtons(); }
	async function request(action, extra = {}) {
  const controller = new AbortController(); activeController = controller;
  $('cancel').disabled = false;
  let timedOut = false;
  const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, action === 'download' ? FFP.downloadTimeout : FFP.requestTimeout);
  const data = new URLSearchParams({ action: `ffp_${action}`, nonce: FFP.nonce, ...extra });
  try {
   const response = await fetch(FFP.url, { method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal });
   if (action === 'download' && response.ok && response.headers.get('Content-Type')?.includes('application/zip')) {
    const blob = await response.blob();
    const match = response.headers.get('Content-Disposition')?.match(/filename\*=UTF-8''([^;]+)/i);
    return {blob, name: match ? decodeURIComponent(match[1]) : `form-${$('form').value}-file-pack.zip`, warnings: Number(response.headers.get('X-FFP-Warnings') || 0)};
   }
   let result;
   try { result = await response.json(); } catch (e) { if (controller.signal.aborted) throw e; throw new Error(s.error); }
   if (!response.ok || !result.success) {
    if (result.data?.refresh) invalidate();
    const error = new Error(result.data?.message || s.error);
    error.reload = result.data?.code === 'session_expired' || response.status === 403; throw error;
   }
   return result.data;
  } catch (e) {
   if (controller.signal.aborted) throw new Error(timedOut ? s.timeout : s.cancelled);
   if (e instanceof TypeError) throw new Error(s.network);
   throw e;
  } finally { clearTimeout(timeout); activeController = null; }
 }
	async function operation(label, fn, recovery = null) {
		if (busy) return;
		retry = recovery; downloading = label === s.building; if (downloading) $('result').hidden = true; busy = true; app.setAttribute('aria-busy', 'true'); refreshButtons(); message(label);
		let failed = false;
  try { await fn(); } catch (e) { failed = true; if (e.reload) { retry = {label: s.reload, run: () => window.location.reload()}; invalidate(); }
   message(e.message, true); if (label === s.building) invalidate(); }
		finally { downloading = false; busy = false; app.setAttribute('aria-busy', 'false'); refreshButtons(); if (failed && label === s.building && !$('retry').hidden) $('retry').focus(); }
	}
	async function loadForms() {
  let autoSelect = false;
		await operation(s.loading, async () => {
			const data = await request('forms', { search: $('search').value });
   ready = data.readiness.ready; periods = data.periods;
   if (data.job) { job = data.job; await renderJob(); scheduleJob(); }
   $('readiness').textContent = ready ? s.ready : s.notReady;
   $('requirements').replaceChildren(...data.readiness.messages.map(text => node('li', text)));
   $('environment').hidden = ready; $('environment-heading').textContent = s.unavailableTitle;
   $('environment').classList.toggle('ffp-environment-error', !ready);
   if (!ready) invalidate();
   const current = $('form').value; const keep = data.forms.some(form => String(form.id) === current);
   if (!keep) { clearSelection(); invalidate(); $('selection').hidden = true; }
			$('form').replaceChildren(new Option(s.choose, ''));
			data.forms.forEach(form => $('form').add(new Option(`${form.title} (#${form.id})`, form.id)));
			autoSelect = !current && !$('search').value.trim() && data.forms.length === 1 && !data.truncated;
   $('form').value = keep ? current : autoSelect ? String(data.forms[0].id) : '';
   $('form-note').textContent = data.truncated ? s.truncated : data.forms.length ? '' : $('search').value.trim() ? s.noForms : s.emptyForms;
   $('form-help').hidden = data.forms.length > 0;
   message(current && !keep ? s.searchReset : '');
		}, {label: s.retryLoad, run: loadForms});
  if (autoSelect) await loadEntries();
	}
	async function loadEntries(nextPage = 1, reset = false, preset = '') {
		await operation(s.loading, async () => {
			const data = await request('entries', { form_id: $('form').value, page: nextPage, from: $('from').value, to: $('to').value, period: preset });
   if (data.applied_period) { $('from').value = data.applied_period.from; $('to').value = data.applied_period.to; }
			if (reset) clearSelection();
   if (preset || reset || (!$('from').value && !$('to').value)) currentPeriod = preset || (!$('from').value && !$('to').value ? 'all' : 'custom');
   filterDirty = false; totalRows = data.total; page = data.page; pages = data.pages; rows = data.entries; $('rows').replaceChildren();
			for (const row of rows) {
    if (selected.has(row.id)) selectedRows.set(row.id, row);
				const tr = node('tr'); const td = node('td'); const box = node('input'); box.type = 'checkbox'; box.dataset.entry = String(row.id); box.checked = allPeriod || selected.has(row.id); box.setAttribute('aria-label', `${s.selectEntry} #${row.id}`);
				box.addEventListener('change', () => {
					clearRecovery();
					if (box.checked) { selected.add(row.id); selectedRows.set(row.id, row); } else { selected.delete(row.id); selectedRows.delete(row.id); }
					invalidate();
				});
				const identity = node('td'); identity.id = `ffp-entry-${row.id}`; box.setAttribute('aria-describedby', identity.id); identity.append(node('strong', row.summary || `#${row.id}`), node('small', `#${row.id} · ${row.display_date || row.date}`, 'ffp-mobile-date'), node('small', `${s.files}: ${row.attachment_count}`, 'ffp-mobile-files'));
    const attachments = node('td', String(row.attachment_count)); attachments.setAttribute('aria-label', `${s.attachmentRefs}: ${row.attachment_count}`);
				td.append(box); tr.append(td, identity, attachments); $('rows').append(tr);
			}
			if (!rows.length) { const tr = node('tr'); const td = node('td', $('from').value || $('to').value ? s.noEntries : s.emptyEntries); td.colSpan = 3; tr.append(td); $('rows').append(tr); }
			$('page').textContent = `${s.page} ${page} ${s.of} ${pages} · ${s.total}: ${data.total}`;
			$('timezone').textContent = `${s.dateZone} ${data.timezone}`;
   $('applied-period').textContent = $('from').value || $('to').value ? `${$('from').value || '…'} — ${$('to').value || '…'} · ${data.timezone}` : '';
			$('selection').hidden = false; $('pagination').hidden = pages <= 1; clearRecovery();
		}, {label: s.retryLoad, run: () => loadEntries(nextPage, reset, preset)});
	}
	$('check').addEventListener('click', loadForms);
 $('check-ready').addEventListener('click', loadForms);
 $('cancel').addEventListener('click', () => activeController?.abort());
 for (const id of ['week', 'month']) $(id).addEventListener('click', () => { clearRecovery(); filterDirty = true; invalidate(); loadEntries(1, true, id); });
	$('search-button').addEventListener('click', loadForms);
	$('search').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loadForms(); } });
	$('form').addEventListener('change', () => {
		clearRecovery(); filterDirty = false; clearSelection(); invalidate(); page = 1; $('selection').hidden = true; $('from').value = ''; $('to').value = '';
		if ($('form').value) loadEntries();
	});
 $('filter').addEventListener('click', () => { clearRecovery(); filterDirty = true; invalidate(); loadEntries(1, true); });
 $('reset-period').addEventListener('click', () => { clearRecovery(); $('from').value = ''; $('to').value = ''; filterDirty = true; invalidate(); loadEntries(1, true); });
 ['from', 'to'].forEach(id => $(id).addEventListener('change', () => { clearRecovery(); filterDirty = true; invalidate(); message(s.listChanged); }));
 $('prev').addEventListener('click', () => loadEntries(page - 1)); $('next').addEventListener('click', () => loadEntries(page + 1));
 $('clear').addEventListener('click', () => { clearRecovery(); clearSelection(); $('rows').querySelectorAll('input').forEach(el => { el.checked = false; }); invalidate(); });
 $('all').addEventListener('change', () => {
		clearRecovery(); const checked = $('all').checked;
		rows.forEach(row => { if (checked) { selected.add(row.id); selectedRows.set(row.id, row); } else { selected.delete(row.id); selectedRows.delete(row.id); } });
		$('rows').querySelectorAll('input').forEach(el => { el.checked = checked; }); invalidate();
	});
 $('partial').addEventListener('change', refreshButtons);
 function showPreview() { if (allPeriod || selected.size > FFP.maxEntries) return beginJob(); if (!ready || filterDirty || !selected.size) { clearRecovery(); message(!ready ? s.notReady : filterDirty ? s.listChanged : s.emptySelection); return; } invalidate(); return operation(s.previewing, async () => {
		preview = await request('preview', { form_id: $('form').value, ids: JSON.stringify([...selected]) });
		$('totals').textContent = `${s.submissions}: ${preview.entries.length} · ${s.files}: ${preview.file_count} · ${size(preview.bytes)}${preview.warnings.length ? ` · ${s.warnings}: ${preview.warnings.length}` : ''}`;
		$('warnings').replaceChildren(); $('warnings').hidden = !preview.warnings.length;
		if (preview.warnings.length) {
			const ul = node('ul'); preview.warnings.forEach(w => { const li = node('li', `#${w.entry} · ${w.field}: ${w.reason}`); li.append(node('p', w.next_step, 'description')); ul.append(li); }); $('warnings').append(ul);
		}
		$('tree').replaceChildren();
		preview.entries.forEach(row => {
			const details = node('details'); details.append(node('summary', `#${row.id} · ${row.summary || row.date} · ${s.files}: ${row.files.length}`));
			const ul = node('ul'); ul.append(node('li', 'request.html'));
			row.files.forEach(file => ul.append(node('li', `${file.name} · ${size(file.size)}`)));
			if (!row.files.length) ul.append(node('li', s.noFiles)); details.append(ul); $('tree').append(details);
		});
  $('warning-help').hidden = !preview.warnings.length; $('partial').checked = false; $('partial-label').hidden = !preview.warnings.length; $('preview').hidden = false;
  message(''); $('preview').scrollIntoView({block: 'nearest'}); $('preview-heading').focus({preventScroll: true});
	}, {label: s.retryPreview, run: showPreview}); }
 $('preview-button').addEventListener('click', showPreview);
 $('retry').addEventListener('click', () => { if (retry) retry.run(); });
 $('download').addEventListener('click', () => operation(s.building, async () => {
		const response = await request('download', { form_id: $('form').value, ids: JSON.stringify([...selected]), fingerprint: preview.fingerprint, allow_partial: $('partial').checked ? '1' : '0' });
		const url = URL.createObjectURL(response.blob);
		const link = node('a'); link.href = url; link.download = response.name; document.body.append(link); link.click(); link.remove();
		setTimeout(() => URL.revokeObjectURL(url), 60000);
		const warnings = response.warnings;
		message(warnings ? `${s.incomplete} (${s.warnings}: ${warnings})` : s.complete);
  preview = null; $('preview').hidden = true; $('partial').checked = false;
  $('result-name').textContent = response.name; $('result-status').textContent = warnings ? s.incomplete : s.complete;
  $('result').hidden = false; $('result').scrollIntoView({block: 'start'}); $('result-heading').focus({preventScroll: true});
	}, {label: s.retryPreview, run: showPreview}));

 $('select-period').addEventListener('click', () => { clearRecovery(); selected.clear(); selectedRows.clear(); allPeriod = true; $('rows').querySelectorAll('input').forEach(box => { box.checked = true; }); invalidate(); });
 function activeJob() { return job && ['selecting', 'discovering', 'checking', 'review', 'building'].includes(job.status); }
 async function jobRequest(action, extra = {}) {
  const controller = new AbortController(); const timer = setTimeout(() => controller.abort(), FFP.requestTimeout);
  try {
   const response = await fetch(FFP.url, {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({action: `ffp_job_${action}`, nonce: FFP.nonce, ...extra}), signal: controller.signal});
   const result = await response.json();
   if (!response.ok || !result.success) { const error = new Error(result.data?.message || s.error); error.reload = response.status === 403 || result.data?.code === 'session_expired'; throw error; }
   return result.data;
  } catch (error) { if (controller.signal.aborted) throw new Error(s.timeout); if (error instanceof TypeError) throw new Error(s.network); throw error; }
  finally { clearTimeout(timer); }
 }
 async function beginJob() {
  if (!ready || filterDirty || activeJob()) return;
  invalidate(); jobPage = 1;
  await operation(s.previewing, async () => {
   job = await jobRequest('create', {form_id: $('form').value, mode: allPeriod ? 'all' : 'manual', from: $('from').value, to: $('to').value});
   await renderJob();
   if (!allPeriod) {
    const ids = [...selected];
    for (let i = 0; i < ids.length; i += 100) job = await jobRequest('append', {job: job.id, sequence: i / 100, ids: JSON.stringify(ids.slice(i, i + 100))});
    job = await jobRequest('finish', {job: job.id});
   }
   await renderJob(); message(''); scheduleJob(); $('job-heading').focus({preventScroll: true}); $('job').scrollIntoView({block: 'nearest'});
  });
 }
 function scheduleJob() {
  clearTimeout(jobTimer);
  if (job && ['discovering', 'checking', 'building', 'cancelled', 'expired'].includes(job.status) && !job.cleanup_done) jobTimer = setTimeout(pollJob, 1200);
 }
 async function pollJob() {
  if (!job || jobPolling || busy) { scheduleJob(); return; }
  jobPolling = true;
  try { job = await jobRequest('status', {job: job.id}); await renderJob(); scheduleJob(); }
  catch (error) { clearTimeout(jobTimer); jobFailure(error, pollJob); refreshButtons(); }
  finally { jobPolling = false; refreshButtons(); }
 }
 async function renderJob() {
  $('job').hidden = !job; if (!job) return;
  $('job-heading').textContent = `${jobHeadingLabel} · ${job.form_title || '#' + job.form}`;
  const labels = {discovering: s.discovering, selecting: s.selectingJob, checking: s.checking, review: s.review, building: s.buildingJob, ready: s.readyJob, cancelled: s.cancelledJob, expired: s.expiredJob, error: job.error};
  $('job-totals').hidden = ['discovering', 'selecting'].includes(job.status);
  const processed = job.status === 'building' || job.status === 'ready' ? job.built : job.checked;
  $('job-status').textContent = `${labels[job.status] || s.error}${job.total ? job.status === 'discovering' ? ` · ${s.submissions}: ${job.total}` : ` · ${s.processed}: ${processed} ${s.of} ${job.total}` : ''}`;
  if (Date.now() / 1000 - job.updated > 120 && activeJob()) $('job-status').textContent += ` · ${s.jobDelay}`;
  $('job-totals').textContent = `${job.from || job.to ? (job.from || '…') + ' — ' + (job.to || '…') : s.allDates} · ${s.submissions}: ${job.total} · ${s.files}: ${job.files} · ${size(job.bytes)}${job.warnings ? ` · ${s.warnings}: ${job.warnings}` : ''}`;
  $('job-review').hidden = true; $('job-result').hidden = true;
  $('job-cancel').hidden = ['cancelled', 'expired'].includes(job.status);
  $('job-partial-label').hidden = !job.warnings;
  $('job-parts').replaceChildren(); $('job-review-list').replaceChildren(); $('job-pager').hidden = true; $('job-expiry').textContent = '';
  if (['review', 'ready'].includes(job.status)) {
   const data = await jobRequest(job.status === 'ready' ? 'parts' : 'review', {job: job.id, page: jobPage}); jobPages = data.pages;
   $('job-pager').hidden = jobPages <= 1; $('job-page').textContent = `${s.page} ${jobPage} ${s.of} ${jobPages}`;
   if (job.status === 'review') for (const row of data.rows) {
    const li = node('li', `#${row.id} · ${row.summary}${row.files !== undefined ? ` · ${s.files}: ${row.files} · ${size(row.bytes)}` : ''}`);
    for (const warning of row.warnings) li.append(node('p', `${warning.field}: ${warning.reason}`, 'description'));
    $('job-review-list').append(li);
   }
   else {
    $('job-expiry').textContent = `${s.expires} ${expiryDate(job.expires)}`;
    for (const part of data.rows) {
     const li = node('li'); const button = node('button', `${s.downloadFull} · ${s.part} ${part.number + 1} ${s.of} ${job.parts}`, 'button'); button.type = 'button';
     button.addEventListener('click', () => downloadPart(part.number));
     li.append(button, node('p', `${part.name} · ${s.submissions}: ${part.submissions} · ${s.files}: ${part.files} · ${size(part.size)}${part.warnings ? ` · ${s.warnings}: ${part.warnings}` : ''}`, 'description')); $('job-parts').append(li);
    }
   }
  }
  $('job-review').hidden = job.status !== 'review'; $('job-result').hidden = job.status !== 'ready';
  refreshButtons();
 }
 function expiryDate(timestamp) { try { return new Date(timestamp * 1000).toLocaleString((FFP.locale || 'en_US').split('_').slice(0, 2).join('-')); } catch (error) { return new Date(timestamp * 1000).toISOString().slice(0, 16).replace('T', ' ') + ' UTC'; } }
 function jobFailure(error, again) { retry = error.reload ? {label: s.reload, run: () => window.location.reload()} : {label: s.retryLoad, run: again}; message(error.message, true); }
 async function jobCommand(command) {
  if (!job || jobPolling) return;
  clearTimeout(jobTimer); jobPolling = true; refreshButtons();
  try { job = await jobRequest(command, {job: job.id, allow_partial: $('job-partial').checked ? '1' : '0'}); jobPage = 1; $('job-partial').checked = false; await renderJob(); message(''); scheduleJob(); }
  catch (error) { jobFailure(error, () => jobCommand(command)); }
  finally { jobPolling = false; refreshButtons(); }
 }
 async function downloadPart(number) {
  try {
   const current = await jobRequest('parts', {job: job.id, page: Math.floor(number / 10) + 1});
   if (!current.rows.some(row => row.number === number)) throw new Error(s.expiredJob);
   let frame = document.getElementById('ffp-download-frame');
   if (!frame) {
    frame = node('iframe'); frame.id = 'ffp-download-frame'; frame.name = 'ffp-download-frame'; frame.hidden = true; frame.title = s.downloadFull;
    frame.addEventListener('load', () => { try { const text = frame.contentDocument.body.textContent; if (text.startsWith('{')) { const result = JSON.parse(text); if (!result.success) message(result.data?.message || s.error, true); } } catch (error) { /* Download responses do not expose a document. */ } });
    document.body.append(frame);
   }
   const form = node('form'); form.method = 'POST'; form.action = FFP.url; form.target = frame.name;
   for (const [name, value] of Object.entries({action: 'ffp_job_file', nonce: FFP.nonce, job: job.id, page: number + 1})) { const input = node('input'); input.type = 'hidden'; input.name = name; input.value = String(value); form.append(input); }
   document.body.append(form); form.submit(); form.remove(); message(s.downloadStarted);
  } catch (error) { jobFailure(error, () => downloadPart(number)); }
 }
 $('job-start').addEventListener('click', () => jobCommand('start'));
 $('job-cancel').addEventListener('click', () => jobCommand('cancel'));
 $('job-resume').addEventListener('click', pollJob);
 $('job-partial').addEventListener('change', refreshButtons);
 for (const [id, delta] of [['job-prev', -1], ['job-next', 1]]) $(id).addEventListener('click', async () => { jobPage += delta; try { await renderJob(); } catch (error) { jobFailure(error, renderJob); } });
 refreshButtons(); loadForms();
})();
