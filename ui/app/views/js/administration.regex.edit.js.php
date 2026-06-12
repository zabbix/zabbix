<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */
?>

window.regex_edit_popup = new class {

	/** @type {Overlay} */
	#overlay;

	/**
	 * @type {CForm}
	 */
	#form;

	/**
	 * @type {HTMLElement}
	 */
	#form_element;

	/**
	 * @type {HTMLDivElement}
	 */
	#footer;

	/** @type {string|null} */
	#regexpid;

	/**
	 * @type {HTMLElement}
	 */
	#test_results;

	/** @type {Object} */
	#clone_rules;

	init({rules, clone_rules, regexpid}) {
		this.#regexpid = regexpid;
		this.#overlay = overlays_stack.getById('regex.edit');
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#clone_rules = clone_rules;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'regex.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initActions();
	}

	#initActions() {
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.#footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
		this.#footer.querySelector('.js-test').addEventListener('click', () => this.#testExpression());

		const table = document.getElementById('regular-expressions-table');

		table.querySelector('button[name="add"]').addEventListener('click', () => this.#addRow());
		table.querySelectorAll('button[name="remove"]').forEach(node =>
			node.addEventListener('click', e => this.#removeRow(e))
		);
		table.querySelectorAll('.js-expression-type-select').forEach(node =>
			node.addEventListener('change', e => this.#updateRow(e))
		);

		table.querySelectorAll('textarea').forEach(node =>
			node.addEventListener('change', e => this.#clearRowResult(e))
		);

		document.getElementById('test-string').addEventListener('input', () => {
			table.querySelectorAll('.js-expression-result')
				.forEach(cell => {
					cell.textContent = '';
					cell.classList.remove(ZBX_STYLE_GREEN, ZBX_STYLE_RED);
				})

			this.#hideCombinedResult();
		})

		this.#hideCombinedResult();
	}

	#submit() {
		this.#removePopupMessages();

		const fields = this.#form.getAllValues();
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', document.getElementById('regexpid') !== null ? 'regex.update' : 'regex.create');

		this.#form.validateSubmit(fields)
			.then(result => {
			if (!result) {
				this.#overlay.unsetLoading();
				return;
			}

			this.#post(curl.getUrl(), fields);
		});
	}

	#clone() {
		this.#regexpid = null;
		document.getElementById('regexpid').remove();

		const title = <?= json_encode(_('New regular expression')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Test')) ?>,
				class: 'js-test',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: ZBX_STYLE_BTN_ALT,
				cancel: true,
				action: ''
			}
		];

		this.#removePopupMessages();

		this.#overlay.unsetLoading();
		this.#overlay.setProperties({title, buttons});

		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());

		this.#overlay.recoverFocus();
		this.#overlay.containFocus();
		this.#form.reload(this.#clone_rules);
	}

	#delete() {
		this.#removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'regex.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('regex')) ?>);

		this.#post(curl.getUrl(), {regexpids: [this.#regexpid]});
	}

	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
		.then(response => response.json())
		.then(response => {
			if ('error' in response) {
				throw {error: response.error};
			}

			if ('form_errors' in response) {
				this.#form.setErrors(response.form_errors, true, true);
				this.#form.renderErrors();
				return;
			}

			overlayDialogueDestroy(this.#overlay.dialogueid);
			this.#overlay.$dialogue[0].dispatchEvent(
				new CustomEvent('dialogue.submit', {detail: response})
			);
		})
		.catch(exception => this.#ajaxExceptionHandler(exception))
		.finally(() => this.#overlay.unsetLoading());
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];
		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#removeRow(e) {
		const row = e.target.closest('tr');
		row.nextSibling.remove();
		row.remove();

		this.#hideCombinedResult();
	}

	#addRow() {
		const indexes = Object.keys(this.#form.findFieldByName('expressions').getValue());
		const next_index = indexes.length ? Math.max(...indexes) + 1 : 0;
		const template = new Template(document.getElementById('row-expression-template').innerHTML);

		document
			.getElementById('expression-list-footer')
			.insertAdjacentHTML('beforebegin', template.evaluate({index: next_index}));

		const row = document
			.getElementById('regular-expressions-table')
			.querySelector(`tr[data-index="${next_index}"]`);

		row.querySelector('button[name="remove"]').addEventListener('click', e => this.#removeRow(e));
		row.querySelector('.js-expression-type-select').addEventListener('change', e => this.#updateRow(e));
		row.querySelector('textarea').addEventListener('input', e => this.#clearRowResult(e));

		this.#hideCombinedResult();
	}

	#testExpression() {
		const fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields)
			.then(result => {
				if (!result) {
					this.#overlay.unsetLoading();
					return;
				}

				const {expressions, test_string} = fields;
				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'regex.test');

				this.#setTestLoadingStatus();

				clearMessages();
				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({expressions, test_string})
				})
					.then(response => response.json())
					.then(response => this.#showTestResult(response, expressions))
					.catch(exception => this.#ajaxExceptionHandler(exception))
					.finally(() => this.#unsetTestLoadingStatus());
			});
	}

	#setTestLoadingStatus() {
		// TODO:: need to use the this.#form.lock() method after the merge task ZBXNEXT-10393
		const textarea = document.getElementById('test-string');

		textarea.disabled = true;
	}

	#unsetTestLoadingStatus() {
		// TODO:: need to use the this.#form.unlock() method after the merge task ZBXNEXT-10393
		const textarea = document.getElementById('test-string');

		textarea.disabled = false;

		this.#overlay.unsetLoading();
	}

	#showTestResult(response, expressions) {
		document.querySelectorAll('#regular-expressions-table .js-expression-result')
			.forEach(cell => {
				cell.textContent = '';
				cell.className = cell.className.replace(ZBX_STYLE_GREEN, '').replace(ZBX_STYLE_RED, '')
			})

		for (const index of Object.keys(expressions)) {
			const result = response.expressions[index];
			const cell = document.getElementById(`expressions_${index}_result`);

			if (!cell) {
				continue;
			}

			cell.textContent = result ? <?= json_encode(_('TRUE')) ?> : <?= json_encode(_('FALSE')) ?>;
			cell.classList.add(result ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
		}

		this.#setCombinedResult(response.final);
	}

	#setCombinedResult(result) {
		const span = document.getElementById('test-result-combined');

		span.textContent = result ? <?= json_encode(_('TRUE')) ?> : <?= json_encode(_('FALSE')) ?>;
		span.className = '';

		span.classList.add(result ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)

		span.closest('.form-field').previousElementSibling.style.display = '';
		span.closest('.form-field').style.display = '';
	}

	#updateRow({target}) {
		if (target.classList.contains('js-expression-type-select')) {
			const row = target.closest('tr');
			const delimiter = target.closest('tr').querySelector('.js-expression-delimiter-select');

			if (target.value == <?= EXPRESSION_TYPE_ANY_INCLUDED ?>) {
				delimiter.disabled = false;
				delimiter.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}
			else {
				delimiter.disabled = true;
				delimiter.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}

			const cell = row.querySelector('.js-expression-result');

			if (cell) {
				cell.textContent = '';
				cell.classList.remove(ZBX_STYLE_GREEN, ZBX_STYLE_RED);
			}

			this.#hideCombinedResult();
		}
	}

	#hideCombinedResult() {
		const span = document.getElementById('test-result-combined');

		span.textContent = '';
		span.closest('.form-field').previousElementSibling.style.display = 'none';
		span.closest('.form-field').style.display = 'none';
	}

	#clearRowResult(e) {
		const cell = e.target.closest('tr').querySelector('.js-expression-result');

		if(cell) {
			cell.textContent = '';
			cell.classList.remove(ZBX_STYLE_GREEN, ZBX_STYLE_RED);
		}

		this.#hideCombinedResult();
	}
};
