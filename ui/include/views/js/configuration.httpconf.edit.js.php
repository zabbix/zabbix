<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

		/** @type {HTMLTableElement} */
		#step_list;

		/** @type {Object} */
		#templates = {};

		/** @type {Object} */
		#events = {};

		/** @type {boolean} */
		#variables_headers_initialized = false;

		/** @type {string} */
		#context;

		constructor() {
			this.#registerEvents();
		}

		init({is_templated, variables, headers, steps, context}) {
			this.#form = document.getElementById('webscenario-form');
			this.#is_templated = is_templated;
			this.#step_list = document.getElementById('steps');
			this.#context = context;

			this.#initTemplates();

			this.#form.addEventListener('click', e => {
				const target = e.target;

				if (target.matches('.js-edit-template')) {
					e.preventDefault();
					this.#openTemplatePopup(target.dataset);
				}
			});

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
		}

		#initTemplates() {
			this.#templates.step_row = new Template(
				document.getElementById(this.#is_templated ? 'step-row-templated-tmpl' : 'step-row-tmpl').innerHTML
			);
		}

		#initVariables(variables) {
			const $variables = jQuery('#variables');

			jQuery('#variables').dynamicRows({
				template: '#variable-row-tmpl',
				rows: variables
			});

			this.#initTextareaFlexible($variables);
		}

		#initHeaders(headers) {
			const $headers = jQuery('#headers');

			$headers
				.dynamicRows({
					template: '#header-row-tmpl',
					rows: headers
				})
				.on('tableupdate.dynamicRows', (e) => {
					this.#toggleDragIcon(e.target);
					jQuery(e.target).sortable({disabled: e.target.querySelectorAll('.sortable').length < 2});
				});

			this.#initTextareaFlexible($headers);
			this.#initSortable($headers);
		}

		#initSteps(steps) {
			for (const [row_index, step] of Object.entries(steps)) {
				step.row_index = row_index;
				this.#step_list.querySelector('tbody').appendChild(this.#prepareStepRow(step));
			}

			this.#step_list.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add-step')) {
					this.#editStep();
				}
				else if (e.target.classList.contains('js-edit-step')) {
					this.#editStep(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove-step')) {
					e.target.closest('tr').remove();
					this.#toggleDragIcon(this.#step_list);
				}
			});

			this.#initSortable(jQuery('#steps'));
		}

		#initTextareaFlexible($element) {
			$element
				.on('afteradd.dynamicRows', (e) => {
					jQuery('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', e.target).textareaFlexible();
				})
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();
		}

		#initSortable($element) {
			this.#toggleDragIcon($element[0]);

			$element.sortable({
				disabled: $element[0].querySelectorAll('.sortable').length < 2,
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: (e, ui) => {
					for (const td of ui.find('>td')) {
						const $td = jQuery(td);
						const width = Math.floor($td.width());

						$td.css({
							'width': width,
							'max-width': width
						});
					}

					// When dragging element on Safari, it jumps out of the table.
					if (SF) {
						// Move back draggable element to proper position.
						ui.css('top', 0);
					}

					return ui;
				},
				start: (e, ui) => {
					jQuery(ui.placeholder).height(jQuery(ui.helper).height());
				},
				stop: (e, ui) => {
					for (const td of ui.item.find('>td')) {
						jQuery(td).removeAttr('style');
					}

					ui.item.removeAttr('style');
				}
			});
		}

		#toggleDragIcon(container) {
			const is_disabled = container.querySelectorAll('.sortable').length < 2;

			for (const drag_icon of container.querySelectorAll('div.<?= ZBX_STYLE_DRAG_ICON ?>')) {
				drag_icon.classList.toggle('<?= ZBX_STYLE_DISABLED ?>', is_disabled);
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

			for (const row of this.#step_list.querySelectorAll('tbody tr[data-row_index]')) {
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

			const overlay = PopUp('webscenario.step.edit', popup_params, {dialogueid: 'webscenario-step-edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const new_row = this.#prepareStepRow({row_index, ...e.detail});

				if (row !== null) {
					row.replaceWith(new_row);
				}
				else {
					this.#step_list.querySelector('tbody').appendChild(new_row);
				}

				this.#initSortable(jQuery('#steps'));
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

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.#openHostPopup(host_data);
		}

		#openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.#events.elementSuccess.bind(this, this.#context), {once: true}
			);
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.#openTemplatePopup(template_data);
		}

		#openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.#events.elementSuccess.bind(this, this.#context), {once: true}
			);
		}

		refresh() {
			const curl = new Curl('');
			const fields = getFormFields(this.#form);

			post(curl.getUrl(), fields);
		}

		#registerEvents() {
			this.#events = {
				elementSuccess(context, e) {
					const data = e.detail;
					let curl = null;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}

						if ('action' in data.success && data.success.action === 'delete') {
							curl = new Curl('httpconf.php');
							curl.setArgument('context', context);
						}
					}

					if (curl == null) {
						view.refresh();
					}
					else {
						location.href = curl.getUrl();
					}
				}
			};
		}
	};
</script>
