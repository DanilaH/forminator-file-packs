/* global FFP */
(() => {
	'use strict';
	const app = document.getElementById('ffp-app');
	if (!app || !document.getElementById('ffp-selection')) return;
	const $ = id => document.getElementById(`ffp-${id}`);
	const s = FFP.strings;
	const selected = new Set();
	const selectedRows = new Map();
	let retry = null;
	let page = 1, pages = 1, rows = [], preview = null, busy = false, filterDirty = false;
	const node = (tag, text, cls) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (cls) el.className = cls; return el; };
	function size(bytes) { return bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KiB` : `${(bytes / 1048576).toFixed(2)} MiB`; }
	function message(text, error = false) {
  $('status').textContent = text; $('notification').hidden = !text;
  $('notification').classList.toggle('ffp-error', error); $('status').setAttribute('role', error ? 'alert' : 'status');
  $('retry').hidden = !error || !retry;
  if (retry) $('retry').textContent = retry.label;
 }
 function clearSelection() { selected.clear(); selectedRows.clear(); }
 function reviewSelection() {
  $('selected-list').replaceChildren();
  for (const id of selected) {
   const row = selectedRows.get(id); const li = node('li', `#${id} · ${row?.date || ''} · ${row?.summary || '—'} `);
   const remove = node('button', `${s.remove} #${id}`, 'button-link'); remove.type = 'button'; remove.disabled = busy || filterDirty;
   remove.addEventListener('click', () => { selected.delete(id); selectedRows.delete(id); $('rows').querySelectorAll('input').forEach(box => { if (box.dataset.entry === String(id)) box.checked = false; }); invalidate(); message(''); $('selected-review').querySelector('summary').focus(); });
   li.append(remove); $('selected-list').append(li);
  }
  $('selection-help').textContent = selected.size ? '' : s.emptySelection;
 }

	function refreshButtons() {
		app.querySelectorAll('button,input,select').forEach(el => { el.disabled = busy; });
		$('prev').disabled = busy || filterDirty || page <= 1; $('next').disabled = busy || filterDirty || page >= pages;
		$('preview-button').disabled = busy || filterDirty || !selected.size;
		$('download').disabled = busy || filterDirty || !preview || (preview.warnings.length > 0 && !$('partial').checked);
		$('all').disabled = busy || filterDirty || !rows.length;
		$('rows').querySelectorAll('input').forEach(el => { el.disabled = busy || filterDirty; });
		$('all').checked = rows.length > 0 && rows.every(row => selected.has(row.id));
		$('all').indeterminate = rows.some(row => selected.has(row.id)) && !$('all').checked;
		$('count').textContent = `${s.selected} ${selected.size}`;
  reviewSelection();
	}
	function invalidate() { preview = null; $('preview').hidden = true; $('partial').checked = false; refreshButtons(); }
	async function request(action, extra = {}) {
		const data = new URLSearchParams({ action: `ffp_${action}`, nonce: FFP.nonce, ...extra });
		let response;
		try { response = await fetch(FFP.url, { method: 'POST', credentials: 'same-origin', body: data }); }
		catch (e) { throw new Error(s.network); }
		if (action === 'download' && response.ok && response.headers.get('Content-Type')?.includes('application/zip')) return response;
		let result;
		try { result = await response.json(); } catch (e) { throw new Error(s.error); }
		if (!response.ok || !result.success) { if (result.data?.refresh) invalidate(); throw new Error(result.data?.message || s.error); }
		return result.data;
	}
	async function operation(label, fn, recovery = null) {
		if (busy) return;
		retry = recovery; busy = true; app.setAttribute('aria-busy', 'true'); refreshButtons(); message(label);
		let failed = false;
  try { await fn(); } catch (e) { failed = true; message(e.message, true); if (label === s.building) invalidate(); }
		finally { busy = false; app.setAttribute('aria-busy', 'false'); refreshButtons(); if (failed && label === s.building) $('retry').focus(); }
	}
	async function loadForms() {
		await operation(s.loading, async () => {
			const data = await request('forms', { search: $('search').value });
   const current = $('form').value; const keep = data.forms.some(form => String(form.id) === current);
   if (!keep) { clearSelection(); invalidate(); $('selection').hidden = true; }
			$('form').replaceChildren(new Option(s.choose, ''));
			data.forms.forEach(form => $('form').add(new Option(`${form.title} (#${form.id})`, form.id)));
			$('form').value = keep ? current : '';
   $('form-note').textContent = data.truncated ? s.truncated : data.forms.length ? '' : $('search').value.trim() ? s.noForms : s.emptyForms;
   $('form-help').hidden = data.forms.length > 0;
   message(current && !keep ? s.searchReset : '');
		}, {label: s.retryLoad, run: loadForms});
	}
	async function loadEntries(nextPage = 1, reset = false) {
		await operation(s.loading, async () => {
			const data = await request('entries', { form_id: $('form').value, page: nextPage, from: $('from').value, to: $('to').value });
			if (reset) clearSelection();
   filterDirty = false; page = data.page; pages = data.pages; rows = data.entries; $('rows').replaceChildren();
			for (const row of rows) {
				const tr = node('tr'); const td = node('td'); const box = node('input'); box.type = 'checkbox'; box.dataset.entry = String(row.id); box.checked = selected.has(row.id); box.setAttribute('aria-label', `${s.selectEntry} #${row.id}`);
				box.addEventListener('change', () => {
					if (box.checked && selected.size >= FFP.maxEntries) { box.checked = false; message(s.max, true); return; }
					if (box.checked) { selected.add(row.id); selectedRows.set(row.id, row); } else { selected.delete(row.id); selectedRows.delete(row.id); }
					invalidate(); message('');
				});
				const identity = node('td', `#${row.id}`); identity.append(node('small', row.date, 'ffp-mobile-date'));
				td.append(box); tr.append(td, identity, node('td', row.date), node('td', row.summary || '—')); $('rows').append(tr);
			}
			if (!rows.length) { const tr = node('tr'); const td = node('td', $('from').value || $('to').value ? s.noEntries : s.emptyEntries); td.colSpan = 4; tr.append(td); $('rows').append(tr); }
			$('page').textContent = `${s.page} ${page} ${s.of} ${pages} · ${s.total}: ${data.total}`;
			$('timezone').textContent = `${s.dateZone} ${data.timezone}`;
			$('selection').hidden = false; $('pagination').hidden = pages <= 1; message('');
		}, {label: s.retryLoad, run: () => loadEntries(nextPage, reset)});
	}
	$('search-button').addEventListener('click', loadForms);
	$('search').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loadForms(); } });
	$('form').addEventListener('change', () => {
		filterDirty = false; clearSelection(); invalidate(); page = 1; $('selection').hidden = true; $('from').value = ''; $('to').value = '';
		if ($('form').value) loadEntries();
	});
 $('filter').addEventListener('click', () => { filterDirty = true; invalidate(); loadEntries(1, true); });
 $('reset-period').addEventListener('click', () => { $('from').value = ''; $('to').value = ''; filterDirty = true; invalidate(); loadEntries(1, true); });
 ['from', 'to'].forEach(id => $(id).addEventListener('change', () => { filterDirty = true; invalidate(); message(s.listChanged); }));
 $('prev').addEventListener('click', () => loadEntries(page - 1)); $('next').addEventListener('click', () => loadEntries(page + 1));
 $('clear').addEventListener('click', () => { clearSelection(); $('rows').querySelectorAll('input').forEach(el => { el.checked = false; }); invalidate(); });
 $('all').addEventListener('change', () => {
		const checked = $('all').checked;
		if (checked && selected.size + rows.filter(row => !selected.has(row.id)).length > FFP.maxEntries) { message(s.max, true); refreshButtons(); return; }
		rows.forEach(row => { if (checked) { selected.add(row.id); selectedRows.set(row.id, row); } else { selected.delete(row.id); selectedRows.delete(row.id); } });
		$('rows').querySelectorAll('input').forEach(el => { el.checked = checked; }); invalidate();
	});
 $('partial').addEventListener('change', refreshButtons);
 function showPreview() { return operation(s.previewing, async () => {
		preview = await request('preview', { form_id: $('form').value, ids: JSON.stringify([...selected]) });
		$('totals').textContent = `${s.submissions}: ${preview.entries.length} · ${s.files}: ${preview.file_count} · ${size(preview.bytes)} · ${s.warnings}: ${preview.warnings.length}`;
		$('warnings').replaceChildren(); $('warnings').hidden = !preview.warnings.length;
		if (preview.warnings.length) {
			const ul = node('ul'); preview.warnings.forEach(w => ul.append(node('li', `#${w.entry} · ${w.field}: ${w.reason}`))); $('warnings').append(ul);
		}
		$('tree').replaceChildren();
		preview.entries.forEach(row => {
			const details = node('details'); details.append(node('summary', `${row.folder}/ · ${s.files}: ${row.files.length}`));
			const ul = node('ul'); ul.append(node('li', 'request.html'));
			row.files.forEach(file => ul.append(node('li', `${file.name} · ${size(file.size)}`)));
			if (!row.files.length) ul.append(node('li', s.noFiles)); details.append(ul); $('tree').append(details);
		});
  $('warning-help').hidden = !preview.warnings.length; $('partial').checked = false; $('partial-label').hidden = !preview.warnings.length; $('preview').hidden = false;
  message(''); $('preview-heading').focus();
	}, {label: s.retryPreview, run: showPreview}); }
 $('preview-button').addEventListener('click', showPreview);
 $('retry').addEventListener('click', () => { if (retry) retry.run(); });
 $('download').addEventListener('click', () => operation(s.building, async () => {
		const response = await request('download', { form_id: $('form').value, ids: JSON.stringify([...selected]), fingerprint: preview.fingerprint, allow_partial: $('partial').checked ? '1' : '0' });
		const blob = await response.blob(); const url = URL.createObjectURL(blob);
		const link = node('a'); link.href = url; link.download = `form-${$('form').value}-file-pack.zip`; document.body.append(link); link.click(); link.remove();
		setTimeout(() => URL.revokeObjectURL(url), 60000);
		const warnings = Number(response.headers.get('X-FFP-Warnings') || 0);
		message(warnings ? `${s.incomplete} (${s.warnings}: ${warnings})` : s.complete);
	}, {label: s.retryPreview, run: showPreview}));
 refreshButtons(); loadForms();
})();
