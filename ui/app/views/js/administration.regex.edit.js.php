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

		/**
		 * @type {string}
		 */
		#form_action;

		/**
		 * @type {string}
		 */
		#clone_action;

		/**
		 * @type {string}
		 */
		#list_action;

		/**
		 * @type {string}
		 */
		#test_action;

		init({rules, action}) {
			this.form_element = document.getElementById('regexp');
			this.form = new CForm(this.form_element, rules);
			this.form_element.addEventListener('submit', (e) => {
				e.preventDefault();
				this.submit();
			});

			document.getElementById('regular-expressions-table').addEventListener('change', (e) => this.#updateRow(e));
			document.getElementById('regular-expressions-table').addEventListener('click', (e) => this.#processAction(e));
			document.getElementById('test-expression').addEventListener('click', () => this.#testExpression());
			document.getElementById('tab_test').addEventListener('click', () => this.#testExpression());

			const curl = new Curl(this.form_element.getAttribute('action'));

			curl.setArgument('action', action);
			this.#form_action = curl.getUrl();
			curl.setArgument('action', 'regex.list');
			this.#list_action = curl.getUrl();
			curl.setArgument('action', 'regex.test');
			this.#test_action = curl.getUrl();
			curl.setArgument('action', 'regex.edit');
			this.#clone_action = curl.getUrl();

			this.#test_results = document.getElementById('test-result-table').querySelector('tbody');

			const clone = document.getElementById('clone');

			if (clone) {
				clone.addEventListener('click', () => this.#clone());
			}
		}

		submit() {
			const fields = this.form.getAllValues();

			this.#setLoadingStatus();
			this.form.validateSubmit(fields)
				.then((result) => {
					if (!result) {
						this.#unsetLoadingStatus();

						return;
					}

					fetch(this.#form_action, {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify(fields)
					})
						.then((response) => response.json())
						.then((response) => {
							clearMessages();

							if ('form_errors' in response) {
								this.form.setErrors(response.form_errors, true, true);
								this.form.renderErrors();
							}
							else if ('error' in response) {
								throw {error: response.error};
							}
							else {
								if ('messages' in response.success) {
									postMessageDetails('success', response.success.messages);
								}

								postMessageOk(response.success.title);
								location.href = this.#list_action;
							}
						})
						.catch((exception) => this.#ajaxExceptionHandler(exception))
						.finally(() => this.#unsetLoadingStatus());
				});
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

			addMessage(makeMessageBox('bad', messages, title));
		}

		#clone() {
			const curl = new Curl(this.#clone_action),
				{name, expressions, test_string} = this.form.getAllValues(),
				indexes = Object.keys(expressions);

			curl.setArgument('regexp[name]', name);
			curl.setArgument('regexp[test_string]', test_string);

			for (let index of indexes) {
				const {case_sensitive, exp_delimiter, expression, expression_type} = expressions[index];

				curl.setArgument(`regexp[expressions][${index}][case_sensitive]`, case_sensitive);
				curl.setArgument(`regexp[expressions][${index}][exp_delimiter]`, exp_delimiter);
				curl.setArgument(`regexp[expressions][${index}][expression]`, expression);
				curl.setArgument(`regexp[expressions][${index}][expression_type]`, expression_type);
			}

			redirect(curl.getUrl(), 'post', 'action', undefined, true);
		}

		#processAction(e) {
			const action = e.target.getAttribute('name');

			if (action === 'remove')  {
				const row = e.target.closest('tr');

				row.nextSibling.remove();
				row.remove();
			}
			else if (action === 'add')  {
				const indexes = Object.keys(this.form.findFieldByName('expressions').getValue());
				const next_index = indexes.length ? Math.max(...indexes) + 1 : 0;
				const template = new Template(document.getElementById('row-expression-template').innerHTML);

				document
					.getElementById('expression-list-footer')
					.insertAdjacentHTML('beforebegin', template.evaluate({index: next_index}));
			}
		}

		#testExpression() {
			const {expressions, test_string} = this.form.getAllValues();

			this.#setTestLoadingStatus();

			fetch(this.#test_action, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({ajaxdata: {expressions, test_string}})
			})
				.then((response) => response.json())
				.then((response) => this.#showTestResult(response, expressions))
				.catch((exception) => this.#ajaxExceptionHandler(exception))
				.finally(() => this.#unsetTestLoadingStatus());
		}

		#setLoadingStatus() {
			[
				document.getElementById('add'),
				document.getElementById('clone'),
				document.getElementById('delete'),
				document.getElementById('update')
			].forEach(button => {
				if (button) {
					button.classList.add('is-loading');
					button.classList.add('is-loading-fadein');
					button.setAttribute('disabled', true);
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
					button.classList.remove('is-loading-fadein');
					button.removeAttribute('disabled');
				}
			});
		}

		#setTestLoadingStatus() {
			const button = document.getElementById('test-expression'),
				textarea = document.getElementById('test-string');

			button.classList.add('is-loading');
			button.setAttribute('disabled', true);
			textarea.setAttribute('disabled', true);
		}

		#unsetTestLoadingStatus() {
			const button = document.getElementById('test-expression'),
				textarea = document.getElementById('test-string');

			button.classList.remove('is-loading');
			button.removeAttribute('disabled');
			textarea.removeAttribute('disabled');
		}

		#showTestResult(response, expressions) {
			for (let row of this.#test_results.querySelectorAll('.js-expression-result-row')) {
				row.remove();
			}

			const indexes = Object.keys(expressions),
				combined_result = {message: response.final ? t('TRUE') : t('FALSE'), result: response.final};

			if (indexes.length == 0) {
				return this.#addTestResultCombined(false, t('UNKNOWN'));
			}

			for (let index of indexes) {
				const result = response.expressions[index],
					error = response.errors[index],
					expression = expressions[index];

				if (error !== undefined) {
					combined_result.message = t('UNKNOWN');
					this.#addTestResult(expression, result, error);
				}
				else {
					this.#addTestResult(expression, result, result ? t('TRUE') : t('FALSE'));
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
				expression: expression,
				type: this.#expressionTypeToString(expression_type),
				result: message,
				result_class: result ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>'
			}));
		}

		#expressionTypeToString(type) {
			switch (type) {
				case '<?= EXPRESSION_TYPE_INCLUDED ?>':
					return t('Character string included');
				case '<?= EXPRESSION_TYPE_ANY_INCLUDED ?>':
					return t('Any character string included');
				case '<?= EXPRESSION_TYPE_NOT_INCLUDED ?>':
					return t('Character string not included');
				case '<?= EXPRESSION_TYPE_TRUE ?>':
					return t('Result is TRUE');
				case '<?= EXPRESSION_TYPE_FALSE ?>':
					return t('Result is FALSE');
			}
		}

		#updateRow({target}) {
			if (target instanceof ZSelect && target.classList.contains('js-expression-type-select')) {
				const delimeter = target.closest('tr').querySelector('.js-expression-delimiter-select');

				if (target.value === '<?= EXPRESSION_TYPE_ANY_INCLUDED ?>') {
					delimeter.removeAttribute('disabled');
					delimeter.classList.remove(ZBX_STYLE_DISPLAY_NONE);
				}
				else {
					delimeter.setAttribute('disabled', true);
					delimeter.classList.add(ZBX_STYLE_DISPLAY_NONE);
				}
			}
		}
	};
</script>
