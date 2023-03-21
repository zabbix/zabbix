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

		constructor() {
			this._registerEvents();
			this.templates = {};
			this.variables_headers_initialized = false;
		}

		init({is_templated, variables, headers, steps}) {
			this.form = document.getElementById('webscenario-form');
			this.is_templated = is_templated;
			this.step_list = document.getElementById('steps');

			this._initTemplates();

			jQuery('#tabs').on('tabscreate tabsactivate', (e, ui) => {
				const panel = e.type === 'tabscreate' ? ui.panel : ui.newPanel;

				if (panel.attr('id') === 'scenario-tab' && !this.variables_headers_initialized) {
					this._initVariables(variables);
					this._initHeaders(headers);

					this.variables_headers_initialized = true;
				}
			});

			this._initSteps(steps);

			for (const id of ['agent', 'authentication']) {
				document.getElementById(id).addEventListener('change', () => this._updateForm());
			}

			this._updateForm();
		}

		_initTemplates() {
			this.templates.step_row = new Template(
				document.getElementById(this.is_templated ? 'step-row-templated-tmpl' : 'step-row-tmpl').innerHTML
			);
		}

		_initVariables(variables) {
			const $variables = jQuery('#variables');

			jQuery('#variables').dynamicRows({
				template: '#variable-row-tmpl',
				rows: variables
			});

			this._initTextareaFlexible($variables);
		}

		_initHeaders(headers) {
			const $headers = jQuery('#headers');

			$headers
				.dynamicRows({
					template: '#header-row-tmpl',
					rows: headers
				})
				.on('tableupdate.dynamicRows', (e) => {
					this._toggleDragIcon(e.target);
					jQuery(e.target).sortable({disabled: e.target.querySelectorAll('.sortable').length < 2});
				});

			this._initTextareaFlexible($headers);
			this._initSortable($headers);
		}

		_initSteps(steps) {
			for (const [row_index, step] of Object.entries(steps)) {
				step.row_index = row_index;
				this.step_list.querySelector('tbody').appendChild(this._prepareStepRow(step));
			}

			this.step_list.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add-step')) {
					this._editStep();
				}
				else if (e.target.classList.contains('js-edit-step')) {
					this._editStep(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove-step')) {
					e.target.closest('tr').remove();
					this._toggleDragIcon(this.step_list);
				}
			});

			this._initSortable(jQuery('#steps'));
		}

		_initTextareaFlexible($element) {
			$element
				.on('afteradd.dynamicRows', (e) => {
					jQuery('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', e.target).textareaFlexible();
				})
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();
		}

		_initSortable($element) {
			this._toggleDragIcon($element[0]);

			$element.sortable({
				disabled: $element[0].querySelectorAll('.sortable').length < 2,
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				helper: function(e, ui) {
					for (let td of ui.find('>td')) {
						const $td = jQuery(td);
						$td.attr('width', $td.width());
					}

					// When dragging element on safari, it jumps out of the table.
					if (SF) {
						// Move back draggable element to proper position.
						ui.css('left', (ui.offset().left - 2) + 'px');
					}

					return ui;
				},
				stop: function(e, ui) {
					ui.item.find('>td').removeAttr('width');
					ui.item.removeAttr('style');
				},
				start: function(e, ui) {
					jQuery(ui.placeholder).height(jQuery(ui.helper).height());
				}
			});
		}

		_toggleDragIcon(container) {
			const is_disabled = container.querySelectorAll('.sortable').length < 2;

			for (const drag_icon of container.querySelectorAll('div.<?= ZBX_STYLE_DRAG_ICON ?>')) {
				drag_icon.classList.toggle('<?= ZBX_STYLE_DISABLED ?>', is_disabled);
			}
		}

		_prepareStepRow(step) {
			const template = document.createElement('template');
			template.innerHTML = this.templates.step_row.evaluate(step);
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

		_editStep(row = null) {
			let popup_params;
			let row_index = 0;
			const names = [];
			const row_indexes = [];

			for (const row of this.step_list.querySelectorAll('tbody tr[data-row_index]')) {
				names.push(row.querySelector('[name="steps[' + row.dataset.row_index + '][name]"]').value);
				row_indexes.push(row.dataset.row_index);
			}

			if (row !== null) {
				row_index = row.dataset.row_index;

				const form = document.createElement('form');
				const fields = row.cloneNode(true).querySelectorAll('input[type=hidden]');
				form.append(...fields);
				const step_data = getFormFields(form).steps[row_index];

				popup_params = {edit: 1, templated: this.is_templated, names, ...step_data};
			}
			else {
				if (row_indexes) {
					row_index = Math.max(row_index, ...row_indexes) + 1;
				}

				popup_params = {names};
			}

			const overlay = PopUp('webscenario.step.edit', popup_params, {dialogueid: 'webscenario_step_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const new_row = this._prepareStepRow({row_index, ...e.detail});

				if (row !== null) {
					row.replaceWith(new_row);
				}
				else {
					this.step_list.querySelector('tbody').appendChild(new_row);
				}

				this._toggleDragIcon(this.step_list);
			});
		}

		_updateForm() {
			const agent_other = document.getElementById('agent').value == <?= ZBX_AGENT_OTHER ?>;
			for (const field of this.form.querySelectorAll('.js-field-agent-other')) {
				field.style.display = agent_other ? '' : 'none';
			}

			const authentication_none = document.getElementById('authentication').value == <?= ZBX_HTTP_AUTH_NONE ?>;
			for (const field of this.form.querySelectorAll('.js-field-http-user, .js-field-http-password')) {
				field.style.display = authentication_none ? 'none' : '';
			}
		}

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this._openHostPopup(host_data);
		}

		_openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this._events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this._events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this._events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		}

		refresh() {
			const curl = new Curl('');
			const fields = getFormFields(this.form);

			post(curl.getUrl(), fields);
		}

		_registerEvents() {
			this._events = {
				hostSuccess(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					view.refresh();
				},

				hostDelete(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					const curl = new Curl('zabbix.php');
					curl.setArgument('action', 'host.list');

					location.href = curl.getUrl();
				}
			};
		}
	};
</script>
