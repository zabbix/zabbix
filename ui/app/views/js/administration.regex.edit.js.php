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

		this.#test_results = document.getElementById('test-result-table').querySelector('tbody');

		this.#initActions();
	}

	#initActions() {
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.#footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());

		const table = document.getElementById('regular-expressions-table');

		table.querySelector('button[name="add"]').addEventListener('click', () => this.#addRow());
		table.querySelectorAll('button[name="remove"]').forEach(node =>
			node.addEventListener('click', e => this.#removeRow(e))
		);
		table.querySelectorAll('.js-expression-type-select').forEach(node =>
			node.addEventListener('change', e => this.#updateRow(e))
		);

		document.getElementById('test-expression').addEventListener('click', () => this.#testExpression());
		//document.getElementById('tab_test').addEventListener('click', () => this.#testExpression());
	}

	#submit() {
		this.#removePopupMessages();

		const fields = this.#form.getAllValues();
		const curl = new Curl('zabbix.php');
		console.log(document.getElementById('regexpid'));
		console.log(this.#regexpid);

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
	}

	#testExpression() {
		Object.values(this.#form.findFieldByName('expressions').getFields())
			.forEach(field => field.setChanged());
		this.#form.validateChanges(['expressions']);

		const {expressions, test_string} = this.#form.getAllValues();
		const curl = new Curl(this.#form_element.getAttribute('action'));

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
	}

	#setTestLoadingStatus() {
		const button = document.getElementById('test-expression');
		const textarea = document.getElementById('test-string');

		button.classList.add('is-loading');
		button.disabled = true;
		textarea.disabled = true;
	}

	#unsetTestLoadingStatus() {
		const button = document.getElementById('test-expression');
		const textarea = document.getElementById('test-string');

		button.classList.remove('is-loading');
		button.disabled = false;
		textarea.disabled = false;
	}

	#showTestResult(response, expressions) {
		this.#test_results.querySelectorAll('.js-expression-result-row').forEach(row => row.remove());

		const indexes = Object.keys(expressions);
		const message = response.final ? <?= json_encode(_('TRUE')) ?> : <?= json_encode(_('FALSE')) ?>;
		const combined_result = {message, result: response.final};

		if (indexes.length == 0) {
			return this.#addTestResultCombined(false, <?= json_encode(_('UNKNOWN')) ?>);
		}

		for (let index of indexes) {
			const result = response.expressions[index];
			const error = response.errors[index];
			const expression = expressions[index];

			if (error !== undefined) {
				combined_result.message = <?= json_encode(_('UNKNOWN')) ?>;
				this.#addTestResult(expression, result, error);
			}
			else {
				this.#addTestResult(expression, result, result
					? <?= json_encode(_('TRUE')) ?>
					: <?= json_encode(_('FALSE')) ?>
				);
			}
		}

		this.#addTestResultCombined(combined_result.result, combined_result.message);
	}

	#addTestResultCombined(result, message) {
		const template = new Template(document.getElementById('combined-result-template').innerHTML);

		this.#test_results.append(template.evaluateToElement({
			result_class: result ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>',
			result: message
		}));
	}

	#addTestResult({expression_type, expression}, result, message) {
		const template = new Template(document.getElementById('result-row-template').innerHTML);

		this.#test_results.append(template.evaluateToElement({
			expression,
			type: this.#expressionTypeToString(expression_type),
			result: message,
			result_class: result ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>'
		}));
	}

	#expressionTypeToString(type) {
		switch (+type) {
			case <?= EXPRESSION_TYPE_INCLUDED ?>:
				return <?= json_encode(_('Contains string')) ?>;
			case <?= EXPRESSION_TYPE_ANY_INCLUDED ?>:
				return <?= json_encode(_('String is in list')) ?>;
			case <?= EXPRESSION_TYPE_NOT_INCLUDED ?>:
				return <?= json_encode(_('Does not contain string')) ?>;
			case <?= EXPRESSION_TYPE_TRUE ?>:
				return <?= json_encode(_('Matches regular expression')) ?>;
			case <?= EXPRESSION_TYPE_FALSE ?>:
				return <?= json_encode(_('Does not match regular expression')) ?>;
		}
	}

	#updateRow({target}) {
		if (target.classList.contains('js-expression-type-select')) {
			const delimiter = target.closest('tr').querySelector('.js-expression-delimiter-select');

			if (target.value == <?= EXPRESSION_TYPE_ANY_INCLUDED ?>) {
				delimiter.disabled = false;
				delimiter.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}
			else {
				delimiter.disabled = true;
				delimiter.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
			}
		}
	}
};
