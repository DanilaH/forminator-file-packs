/* global FFP */
(() => {
	'use strict';
	const app = document.getElementById('ffp-app');
	if (!app || !document.getElementById('ffp-selection')) return;
	const $ = id => document.getElementById(`ffp-${id}`);
	const s = FFP.strings;
	const selected = new Set();
	let page = 1, pages = 1, rows = [], preview = null, busy = false;
	const node = (tag, text, cls) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (cls) el.className = cls; return el; };
	function message(text, error = false) { $('status').textContent = text; $('status').classList.toggle('ffp-error', error); $('status').setAttribute('role', error ? 'alert' : 'status'); }
	function refreshButtons() {
		app.querySelectorAll('button,input,select').forEach(el => { el.disabled = busy; });
		$('prev').disabled = busy || page <= 1; $('next').disabled = busy || page >= pages;
		$('preview-button').disabled = busy || !selected.size;
		$('download').disabled = busy || !preview || (preview.warnings.length > 0 && !$('partial').checked);
		$('all').disabled = busy || !rows.length;
		$('all').checked = rows.length > 0 && rows.every(row => selected.has(row.id));
		$('all').indeterminate = rows.some(row => selected.has(row.id)) && !$('all').checked;
		$('count').textContent = `${s.selected} ${selected.size}`;
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
	async function operation(label, fn) {
		if (busy) return;
		busy = true; app.setAttribute('aria-busy', 'true'); refreshButtons(); message(label);
		try { await fn(); } catch (e) { message(e.message, true); }
		finally { busy = false; app.setAttribute('aria-busy', 'false'); refreshButtons(); }
	}
	async function loadForms() {
		await operation(s.loading, async () => {
			const data = await request('forms', { search: $('search').value });
			selected.clear(); invalidate(); $('selection').hidden = true;
			$('form').replaceChildren(new Option(s.choose, ''));
			data.forms.forEach(form => $('form').add(new Option(`${form.title} (#${form.id})`, form.id)));
			$('form-note').textContent = data.truncated ? s.truncated : data.forms.length ? '' : s.noForms;
			message('');
		});
	}
	async function loadEntries(nextPage = 1) {
		await operation(s.loading, async () => {
			const data = await request('entries', { form_id: $('form').value, page: nextPage, from: $('from').value, to: $('to').value });
			page = data.page; pages = data.pages; rows = data.entries; $('rows').replaceChildren();
			for (const row of rows) {
				const tr = node('tr'); const td = node('td'); const box = node('input'); box.type = 'checkbox'; box.checked = selected.has(row.id); box.setAttribute('aria-label', `${s.selectEntry} #${row.id}`);
				box.addEventListener('change', () => {
					if (box.checked && selected.size >= FFP.maxEntries) { box.checked = false; message(s.max, true); return; }
					if (box.checked) selected.add(row.id); else selected.delete(row.id);
					invalidate(); message('');
				});
				td.append(box); tr.append(td, node('td', `#${row.id}`), node('td', row.date), node('td', row.summary || '—')); $('rows').append(tr);
			}
			if (!rows.length) { const tr = node('tr'); const td = node('td', s.noEntries); td.colSpan = 4; tr.append(td); $('rows').append(tr); }
			$('page').textContent = `${s.page} ${page} ${s.of} ${pages} · ${data.total} ${s.total}`;
			$('timezone').textContent = `${s.dateZone} ${data.timezone}`;
			$('selection').hidden = false; message('');
		});
	}
	$('search-button').addEventListener('click', loadForms);
	$('search').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loadForms(); } });
	$('form').addEventListener('change', () => {
		selected.clear(); invalidate(); page = 1; $('selection').hidden = true; $('from').value = ''; $('to').value = '';
		if ($('form').value) loadEntries();
	});
 $('filter').addEventListener('click', () => { selected.clear(); invalidate(); loadEntries(); });
 ['from', 'to'].forEach(id => $(id).addEventListener('change', invalidate));
 $('prev').addEventListener('click', () => loadEntries(page - 1)); $('next').addEventListener('click', () => loadEntries(page + 1));
 $('clear').addEventListener('click', () => { selected.clear(); $('rows').querySelectorAll('input').forEach(el => { el.checked = false; }); invalidate(); });
 $('all').addEventListener('change', () => {
		const checked = $('all').checked;
		if (checked && selected.size + rows.filter(row => !selected.has(row.id)).length > FFP.maxEntries) { message(s.max, true); refreshButtons(); return; }
		rows.forEach(row => { if (checked) selected.add(row.id); else selected.delete(row.id); });
		$('rows').querySelectorAll('input').forEach(el => { el.checked = checked; }); invalidate();
	});
 $('partial').addEventListener('change', refreshButtons);
 $('preview-button').addEventListener('click', () => operation(s.previewing, async () => {
		preview = await request('preview', { form_id: $('form').value, ids: JSON.stringify([...selected]) });
		$('totals').textContent = `${preview.entries.length} ${s.submissions} · ${preview.file_count} ${s.files} · ${(preview.bytes / 1048576).toFixed(2)} MiB · ${preview.warnings.length} ${s.warnings}`;
		$('warnings').replaceChildren(); $('warnings').hidden = !preview.warnings.length;
		if (preview.warnings.length) {
			const ul = node('ul'); preview.warnings.forEach(w => ul.append(node('li', `#${w.entry} · ${w.field}: ${w.reason}`))); $('warnings').append(ul);
		}
		$('tree').replaceChildren();
		preview.entries.forEach(row => {
			const details = node('details'); details.append(node('summary', `${row.folder}/ · ${row.files.length} ${s.files}`));
			const ul = node('ul'); ul.append(node('li', 'request.html'));
			row.files.forEach(file => ul.append(node('li', `${file.name} · ${(file.size / 1024).toFixed(1)} KiB`)));
			if (!row.files.length) ul.append(node('li', s.noFiles)); details.append(ul); $('tree').append(details);
		});
  $('partial').checked = false; $('partial-label').hidden = !preview.warnings.length; $('preview').hidden = false;
  message(''); $('preview-heading').focus();
	}));
 $('download').addEventListener('click', () => operation(s.building, async () => {
		const response = await request('download', { form_id: $('form').value, ids: JSON.stringify([...selected]), fingerprint: preview.fingerprint, allow_partial: $('partial').checked ? '1' : '0' });
		const blob = await response.blob(); const url = URL.createObjectURL(blob);
		const link = node('a'); link.href = url; link.download = `form-${$('form').value}-file-pack.zip`; document.body.append(link); link.click(); link.remove();
		setTimeout(() => URL.revokeObjectURL(url), 60000);
		const warnings = Number(response.headers.get('X-FFP-Warnings') || 0);
		message(warnings ? `${s.incomplete} (${warnings} ${s.warnings})` : s.complete);
	}));
 refreshButtons(); loadForms();
})();
