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
 */
?>

<script>
	const view = new class {

		/** @type {HTMLFormElement} */
		#form;

		/** @type {boolean} */
		#is_templated = false;

		/** @type {string} */
		#context;

		/** @type {Object} */
		#templates = {};

		/** @type {Object} */
		#events = {};

		/** @type {HTMLTableElement} */
		#variables;

		/** @type {HTMLTableElement} */
		#headers;

		/** @type {boolean} */
		#variables_headers_initialized = false;

		/** @type {HTMLTableElement} */
		#steps;

		init({is_templated, variables, headers, steps, context}) {
			this.#form = document.getElementById('webscenario-form');
			this.#is_templated = is_templated;
			this.#context = context;
			this.#variables = document.getElementById('variables');
			this.#headers = document.getElementById('headers');
			this.#steps = document.getElementById('steps');

			this.#initTemplates();

			jQuery('#tabs').on('tabscreate tabsactivate', (e, ui) => {
				const panel = e.type === 'tabscreate' ? ui.panel : ui.newPanel;

				if (panel.attr('id') === 'scenario-tab' && !this.#variables_headers_initialized) {
					this.#initVariables(variables);
					this.#initHeaders(headers);

					this.#variables_headers_initialized = true;
				}
			});

			this.#initSteps(steps);

			for (const id of ['agent', 'authentication']) {
				document.getElementById(id).addEventListener('change', () => this.#updateForm());
			}

			this.#updateForm();
			this.#setSubmitCallback();
		}

		#initTemplates() {
			this.#templates.step_row = new Template(
				document.getElementById(this.#is_templated ? 'step-row-templated-tmpl' : 'step-row-tmpl').innerHTML
			);
		}

		#initVariables(variables) {
			const $variables = jQuery(this.#variables);

			$variables.dynamicRows({
				template: '#variable-row-tmpl',
				rows: variables
			});

			this.#initTextareaFlexible($variables);
		}

		#initHeaders(headers) {
			const $headers = jQuery(this.#headers);

			$headers
				.dynamicRows({
					template: '#header-row-tmpl',
					rows: headers,
					sortable: true,
					sortable_options: {
						target: 'tbody',
						selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
						freeze_end: 1
					}
				})
				.on('tableupdate.dynamicRows', (e) => {
					e.target.querySelectorAll('.form_row').forEach((row, index) => {
						for (const field of row.querySelectorAll('[name^="headers["]')) {
							field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
						}
					});
				});

			this.#initTextareaFlexible($headers);
		}

		#initTextareaFlexible($element) {
			$element
				.on('afteradd.dynamicRows', (e) => {
					jQuery('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', e.target).textareaFlexible();
				})
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();
		}

		#initSteps(steps) {
			for (const [row_index, step] of Object.entries(steps)) {
				step.row_index = row_index;
				this.#steps.querySelector('tbody').appendChild(this.#prepareStepRow(step));
			}

			this.#steps.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add-step')) {
					this.#editStep();
				}
				else if (e.target.classList.contains('js-edit-step')) {
					this.#editStep(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove-step')) {
					e.target.closest('tr').remove();
				}
			});

			if (!this.#is_templated) {
				new CSortable(this.#steps.querySelector('tbody'), {
					selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>'
				});
			}
		}

		#prepareStepRow(step) {
			const template = document.createElement('template');
			template.innerHTML = this.#templates.step_row.evaluate(step);
			const row = template.content.firstChild;

			for (const field of ['query_fields', 'post_fields', 'variables', 'headers']) {
				if (field in step) {
					for (const [i, pair] of Object.entries(step[field])) {
						for (const [name, value] of Object.entries(pair)) {
							const input = document.createElement('input');
							input.type = 'hidden';
							input.name = `steps[${step.row_index}][${field}][${i}][${name}]`;
							input.value = value;

							row.firstChild.appendChild(input);
						}
					}
				}
			}

			return row;
		}

		#editStep(row = null) {
			let popup_params;
			let row_index = 0;
			const names = [];
			const row_indexes = [];

			for (const row of this.#steps.querySelectorAll('tbody tr[data-row_index]')) {
				names.push(row.querySelector('[name="steps[' + row.dataset.row_index + '][name]"]').value);
				row_indexes.push(row.dataset.row_index);
			}

			if (row !== null) {
				row_index = row.dataset.row_index;

				const form = document.createElement('form');
				const fields = row.cloneNode(true).querySelectorAll('input[type=hidden]');
				form.append(...fields);
				const step_data = getFormFields(form).steps[row_index];

				popup_params = {edit: 1, templated: this.#is_templated, names, ...step_data};
			}
			else {
				if (row_indexes) {
					row_index = Math.max(row_index, ...row_indexes) + 1;
				}

				popup_params = {names};
			}

			const overlay = PopUp('webscenario.step.edit', popup_params, {
				dialogueid: 'webscenario-step-edit',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const new_row = this.#prepareStepRow({row_index, ...e.detail});

				if (row !== null) {
					row.replaceWith(new_row);
				}
				else {
					this.#steps.querySelector('tbody').appendChild(new_row);
				}
			});
		}

		#updateForm() {
			const agent_other = document.getElementById('agent').value == <?= ZBX_AGENT_OTHER ?>;
			for (const field of this.#form.querySelectorAll('.js-field-agent-other')) {
				field.style.display = agent_other ? '' : 'none';
			}

			const authentication_none = document.getElementById('authentication').value == <?= ZBX_HTTP_AUTH_NONE ?>;
			for (const field of this.#form.querySelectorAll('.js-field-http-user, .js-field-http-password')) {
				field.style.display = authentication_none ? 'none' : '';
			}
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				let curl = null;

				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}

					if ('action' in e.detail.success && e.detail.success.action === 'delete') {
						curl = new Curl('httpconf.php');
						curl.setArgument('context', this.#context);
					}
				}

				if (curl === null) {
					view.refresh();
				}
				else {
					location.href = curl.getUrl();
				}
			});
		}

		refresh() {
			const curl = new Curl('');
			const fields = getFormFields(this.#form);

			post(curl.getUrl(), fields);
		}
	};
</script>
