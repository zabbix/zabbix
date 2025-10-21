<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

<script>
	window.regular_expression_edit = new class {

		/**
		 * @type {CForm}
		 */
		form;

		/**
		 * @type {HTMLElement}
		 */
		form_element;

		/**
		 * @type {HTMLElement}
		 */
		#test_results;

		init({rules}) {
			this.form_element = document.getElementById('regexp-form');
			this.form = new CForm(this.form_element, rules);
			this.form_element.addEventListener('submit', e => {
				e.preventDefault();
				this.submit();
			});

			document.getElementById('test-expression').addEventListener('click', () => this.#testExpression());
			document.getElementById('tab_test').addEventListener('click', () => this.#testExpression());

			const table = document.getElementById('regular-expressions-table');

			table.querySelector('button[name="add"]').addEventListener('click', () => this.#addRow());
			table.querySelectorAll('button[name="remove"]').forEach(node =>
				node.addEventListener('click', e => this.#removeRow(e))
			);
			table.querySelectorAll('.js-expression-type-select').forEach(node =>
				node.addEventListener('change', e => this.#updateRow(e))
			);

			this.#test_results = document.getElementById('test-result-table').querySelector('tbody');

			const clone = document.getElementById('clone');

			if (clone) {
				clone.addEventListener('click', () => this.#clone());
			}

			const delete_btn = document.getElementById('delete');

			if (delete_btn) {
				delete_btn.addEventListener('click', () => this.#delete(delete_btn.getAttribute('data-redirect-url')));
			}
		}

		submit() {
			this.#setLoadingStatus(['add', 'update']);
			clearMessages();

			const fields = this.form.getAllValues();

			this.form.validateSubmit(fields)
				.then(result => {
					if (!result) {
						this.#unsetLoadingStatus();

						return;
					}

					const curl = new Curl(this.form_element.getAttribute('action'));

					curl.setArgument('action', document.getElementById('regexpid') ? 'regex.update' : 'regex.create');

					fetch(curl.getUrl(), {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify(fields)
					})
						.then(response => response.json())
						.then(response => {
							if ('form_errors' in response) {
								this.form.setErrors(response.form_errors, true, true);
								this.form.renderErrors();
								this.#unsetLoadingStatus();
							}
							else if ('error' in response) {
								throw {error: response.error};
							}
							else {
								postMessageOk(response.success.title);

								if ('messages' in response.success) {
									postMessageDetails('success', response.success.messages);
								}

								curl.setArgument('action', 'regex.list');
								location.href = curl.getUrl();
							}
						})
						.catch(exception => this.#ajaxExceptionHandler(exception));
				});
		}

		#ajaxExceptionHandler(exception) {
			if (exception instanceof TypeError) {
				throw exception;
			}

			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			addMessage(makeMessageBox('bad', messages, title));
			this.#unsetLoadingStatus();
		}

		#delete(url) {
			if (window.confirm('<?=_('Delete regular expression?') ?>')) {
				this.#setLoadingStatus(['delete']);
				redirect(url, 'post', 'action', undefined, true);
			}
		}

		#clone() {
			this.#setLoadingStatus(['clone']);

			const curl = new Curl(this.form_element.getAttribute('action'));
			const {name, expressions, test_string} = this.form.getAllValues();

			curl.setArgument('action', 'regex.edit');
			curl.setArgument('regexp[name]', name);
			curl.setArgument('regexp[test_string]', test_string);

			Object.entries(expressions)
				.forEach(([index, {case_sensitive, exp_delimiter, expression, expression_type}]) => {
					curl.setArgument(`regexp[expressions][${index}][case_sensitive]`, case_sensitive);
					curl.setArgument(`regexp[expressions][${index}][exp_delimiter]`, exp_delimiter);
					curl.setArgument(`regexp[expressions][${index}][expression]`, expression);
					curl.setArgument(`regexp[expressions][${index}][expression_type]`, expression_type);
				});

			redirect(curl.getUrl(), 'post', 'action', undefined, true);
		}

		#removeRow(e) {
			const row = e.target.closest('tr');

			row.nextSibling.remove();
			row.remove();
		}

		#addRow() {
			const indexes = Object.keys(this.form.findFieldByName('expressions').getValue());
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
			Object.values(this.form.findFieldByName('expressions').getFields())
				.forEach(field => field.setChanged());
			this.form.validateChanges(['expressions']);

			const {expressions, test_string} = this.form.getAllValues();
			const curl = new Curl(this.form_element.getAttribute('action'));

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

		#setLoadingStatus(loading_ids) {
			[
				document.getElementById('add'),
				document.getElementById('clone'),
				document.getElementById('delete'),
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.setAttribute('disabled', true);

					if (loading_ids.includes(button.id)) {
						button.classList.add('is-loading');
					}
				}
			});
		}

		#unsetLoadingStatus() {
			[
				document.getElementById('add'),
				document.getElementById('clone'),
				document.getElementById('delete'),
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.classList.remove('is-loading');
					button.removeAttribute('disabled');
				}
			});
		}

		#setTestLoadingStatus() {
			const button = document.getElementById('test-expression');
			const textarea = document.getElementById('test-string');

			button.classList.add('is-loading');
			button.setAttribute('disabled', true);
			textarea.setAttribute('disabled', true);
		}

		#unsetTestLoadingStatus() {
			const button = document.getElementById('test-expression');
			const textarea = document.getElementById('test-string');

			button.classList.remove('is-loading');
			button.removeAttribute('disabled');
			textarea.removeAttribute('disabled');
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
					return <?= json_encode(_('Character string included')) ?>;
				case <?= EXPRESSION_TYPE_ANY_INCLUDED ?>:
					return <?= json_encode(_('Any character string included')) ?>;
				case <?= EXPRESSION_TYPE_NOT_INCLUDED ?>:
					return <?= json_encode(_('Character string not included')) ?>;
				case <?= EXPRESSION_TYPE_TRUE ?>:
					return <?= json_encode(_('Result is TRUE')) ?>;
				case <?= EXPRESSION_TYPE_FALSE ?>:
					return <?= json_encode(_('Result is FALSE')) ?>;
			}
		}

		#updateRow({target}) {
			if (target.classList.contains('js-expression-type-select')) {
				const delimiter = target.closest('tr').querySelector('.js-expression-delimiter-select');

				if (target.value == <?= EXPRESSION_TYPE_ANY_INCLUDED ?>) {
					delimiter.removeAttribute('disabled');
					delimiter.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				}
				else {
					delimiter.setAttribute('disabled', true);
					delimiter.classList.add('<?= ZBX_STYLE_DISPLAY_NONE ?>');
				}
			}
		}
	}
</script>
