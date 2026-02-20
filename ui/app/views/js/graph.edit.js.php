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
 */

?>

window.graph_edit_popup = new class {

	init({rules, action, theme_colors, graphs, readonly, items, context, hostid, return_url}) {
		this.action = action;
		this.graph = graphs;
		this.readonly = readonly;
		this.context = context;
		this.hostid = hostid;
		this.graph_type = this.graph.graphtype;
		this.overlay = overlays_stack.getById(action === 'graph.edit' ? 'graph.edit' : 'graph.prototype.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.footer = this.overlay.$dialogue.$footer[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		colorPalette.setThemeColors(theme_colors);

		ZABBIX.PopupManager.setReturnUrl(return_url);

		window.addPopupValues = (data) => {
			this.addPopupValues(data.values);
		}

		const template_type = this.graph_type == <?= GRAPH_TYPE_EXPLODED ?> ? <?= GRAPH_TYPE_PIE ?> : this.graph_type;
		const item_row_template = new Template(document.getElementById(`tmpl-item-row-${template_type}`).innerHTML);

		this.items = Array.isArray(items) ? items : Object.values(items);
		this.items.forEach((item, i) => {
			item.number = i;
			item.name = item.host + '<?= NAME_DELIMITER ?>' + item.name;

			this.#loadItem(item, item_row_template);
		});

		this.form_element.style.display = '';

		this.#initActions();
		this.#initPreviewTab();
		this.#initPopupListeners();

		this.overlay.recoverFocus();
		this.initial_form_fields = this.form.getAllValues();
	}

	#initActions() {
		this.form.findFieldByName('graphtype').getField().addEventListener('change', (e) => {
			this.#toggleGraphTypeFields(e.target.value);
			this.#updateItemsTable(e.target.value);

			this.graph_type = e.target.value;
		});

		document.getElementById('items-table').addEventListener('click', (e) => {
			if (e.target.classList.contains('js-item-name')) {
				this.#openEditItemPopup(e.target);
			}
			else if (e.target.classList.contains('js-add-item')) {
				this.#openAddItemPopup();
			}
			else if (e.target.classList.contains('js-add-item-prototype')) {
				this.#openAddItemPopup(true);
			}
			else if (e.target.classList.contains('js-remove')) {
				this.#removeItem(e.target);
			}
		});

		this.form_element.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-item-prototype-select')) {
				this.#openItemPrototypeSelectPopup(e.target.dataset);
			}
		});

		this.#toggleGraphTypeFields(this.form.findFieldByName('graphtype').getValue());
		this.#toggleYaxisTypeFields();

		// Percent fields.
		this.form.findFieldByName('visible[percent_left]').getField().addEventListener('change', () => {
			this.#togglePercentField('left');
		});

		this.form.findFieldByName('visible[percent_right]').getField().addEventListener('change', () => {
			this.#togglePercentField('right');
		});

		this.#togglePercentField('left');
		this.#togglePercentField('right');

		// Initialize sortable instance for items table.
		if (this.form_element.querySelector('#items-table tbody')) {
			new CSortable(this.form_element.querySelector('#items-table tbody'), {
				selector_span: ':not(.error-container-row)',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.readonly
			}).on(CSortable.EVENT_SORT, this.#recalculateSortOrder);
		}

		this.footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
	}

	#toggleGraphTypeFields(graph_type) {
		// Toggle fields.
		let show_fields = [];
		let hide_fields = [];

		switch (parseInt(graph_type)) {
			case <?= GRAPH_TYPE_NORMAL ?>:
				show_fields = ['#show_work_period_field', '#show_triggers_field', '#percent_left_field',
					'#percent_right_field', '#yaxis_min_field', '#yaxis_max_field'
				];
				hide_fields = ['#show_3d_field'];
				break;

			case <?= GRAPH_TYPE_STACKED ?>:
				show_fields = ['#show_work_period_field', '#show_triggers_field', '#yaxis_min_field',
					'#yaxis_max_field'
				];
				hide_fields = ['#percent_left_field', '#percent_right_field', '#show_3d_field'];
				break;

			case <?= GRAPH_TYPE_PIE ?>:
			case <?= GRAPH_TYPE_EXPLODED ?>:
				show_fields = ['#show_3d_field'];
				hide_fields = ['#show_work_period_field', '#show_triggers_field', '#percent_left_field',
					'#percent_right_field', '#yaxis_min_field', '#yaxis_max_field'
				];
				break;
		}

		show_fields.forEach((field) => {
			const field_element = this.form_element.querySelector(field);

			field_element.style.display = '';
			field_element.previousElementSibling.style.display = '';
			field_element.querySelectorAll('input').forEach((input) => input.disabled = false);
		});

		hide_fields.forEach((field) => {
			const field_element = this.form_element.querySelector(field);

			field_element.style.display = 'none';
			field_element.previousElementSibling.style.display = 'none';
			field_element.querySelectorAll('input').forEach((input) => input.disabled = true);
		});

		// Update items table columns and update column classes.
		const table = document.getElementById('items-table');
		let name_column = table.querySelector('#name-column');

		if (graph_type == <?= GRAPH_TYPE_NORMAL ?>) {
			name_column.classList.remove('table-col-name');
			name_column.classList.add('table-col-name-normal');
		}
		else {
			name_column.classList.remove('table-col-name-normal');
			name_column.classList.add('table-col-name');
		}

		const type_col = table.querySelector('.table-col-type');
		type_col.style.display = graph_type == <?= GRAPH_TYPE_PIE ?> || graph_type == <?= GRAPH_TYPE_EXPLODED ?>
			? ''
			: 'none';

		const draw_style_col = table.querySelector('.table-col-draw-style');
		draw_style_col.style.display = graph_type == <?= GRAPH_TYPE_NORMAL ?> ? '' : 'none';

		const y_axis_col = table.querySelector('.table-col-y-axis-side');
		y_axis_col.style.display = graph_type == <?= GRAPH_TYPE_NORMAL ?> || graph_type == <?= GRAPH_TYPE_STACKED ?>
			? ''
			: 'none';
	}

	#toggleYaxisTypeFields() {
		const ymin_type = this.form.findFieldByName('ymin_type').getField();
		const ymax_type = this.form.findFieldByName('ymax_type').getField();

		ymin_type.addEventListener('change', (e) => {
			this.#toggleYAxisFields(e.target, 'min');
		});

		ymax_type.addEventListener('change', (e) => {
			this.#toggleYAxisFields(e.target, 'max');
		});

		this.#toggleYAxisFields(ymin_type, 'min');
		this.#toggleYAxisFields(ymax_type, 'max');
	}

	#togglePercentField(type) {
		const percent_field = this.form.findFieldByName(`percent_${type}`);

		if (this.form.findFieldByName(`visible[percent_${type}]`).getField().checked) {
			percent_field.getField().style.display = '';
			percent_field.getField().disabled = false;

			this.form.validateChanges([`percent_${type}`]);
		}
		else {
			percent_field.getField().style.display = 'none';
			percent_field.getField().disabled = true;
			percent_field.unsetErrors();
			percent_field.showErrors();
		}
	}

	#initPreviewTab() {
		$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
			const $panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if ($panel.attr('id') === 'graph-preview-tab') {
				const $preview_chart = $('#preview-chart');
				const src = new Curl('chart3.php');

				if ($preview_chart.find('.is-loading').length) {
					return false;
				}

				src.setArgument('period', '3600');
				src.setArgument('name', $('#name').val());
				src.setArgument('width', $('#width').val());
				src.setArgument('height', $('#height').val());
				src.setArgument('graphtype', $('#graphtype').val());
				src.setArgument('legend', $('#show_legend').is(':checked') ? 1 : 0);
				src.setArgument('resolve_macros', this.context === 'template' ? 0 : 1);

				if (this.graph_type == <?= GRAPH_TYPE_PIE ?>
						|| this.graph_type == <?= GRAPH_TYPE_EXPLODED ?>) {
					src.setPath('chart7.php');
					src.setArgument('graph3d', $('#show_3d').is(':checked') ? 1 : 0);
				}
				else {
					if (this.graph_type == <?= GRAPH_TYPE_NORMAL ?>) {
						src.setArgument('percent_left', $('#percent_left').val());
						src.setArgument('percent_right', $('#percent_right').val());
					}

					src.setArgument('ymin_type', $('#ymin_type').val());
					src.setArgument('ymax_type', $('#ymax_type').val());
					src.setArgument('yaxismin', $('#yaxismin').val());
					src.setArgument('yaxismax', $('#yaxismax').val());

					if ($('#ymin_type').val() == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>) {
						const ymin_item_data = $('#ymin_itemid').multiSelect('getData');

						if (ymin_item_data.length) {
							src.setArgument('ymin_itemid', ymin_item_data[0]['id']);
						}
					}

					if ($('#ymax_type').val() == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>) {
						const ymax_item_data = $('#ymax_itemid').multiSelect('getData');

						if (ymax_item_data.length) {
							src.setArgument('ymax_itemid', ymax_item_data[0]['id']);
						}
					}

					src.setArgument('showworkperiod', $('#show_work_period').is(':checked') ? 1 : 0);
					src.setArgument('showtriggers', $('#show_triggers').is(':checked') ? 1 : 0);
				}

				$('#items-table tbody tr.graph-item').each((i, node) => {
					const short_fmt = [];

					$(node).find('*[name]').each((_, input) => {
						if (!$.isEmptyObject(input) && input.name != null) {
							const regex = /items\[\d+\]\[([a-zA-Z0-9\-\_\.]+)\]/;
							const name = input.name.match(regex);

							if (input.name !== 'remove') {
								short_fmt.push((name[1]).substr(0, 2) + ':' + input.value);
							}
						}
					});

					src.setArgument('i[' + i + ']', short_fmt.join(','));
				});

				const $image = $('img', $preview_chart);

				if ($image.length != 0) {
					$image.remove();
				}

				$preview_chart.append($('<div>', {css: {'position': 'relative', 'min-height': '50px'}})
					.addClass('is-loading'));

				$('<img>')
					.attr('src', src.getUrl())
					.on('load', function() {
						$preview_chart.html($(this));
					});
			}
		});
	}

	#recalculateSortOrder() {
		document.querySelectorAll('#items-table tbody tr.graph-item [id]').forEach(element => {
			element.id = 'tmp' + element.id;
		});

		document.querySelectorAll('#items-table tbody tr.graph-item').forEach(element => {
			element.id = 'tmp' + element.id;
		});

		for (const [index, row] of document.querySelectorAll('#items-table tbody tr.graph-item').entries()) {
			row.id = row.id.substring(3).replace(/\d+/, `${index}`);

			row.querySelectorAll('[id]').forEach(element => {
				element.id = element.id.substring(3).replace(/\d+/, `${index}`);

				if (element.id.includes('sortorder')) {
					element.value = index;
				}
			});

			row.querySelectorAll('[name]').forEach(element => {
				element.name = element.name.replace(/\d+/, `${index}`);
			});
		}

		document.querySelectorAll('#items-table tbody tr.graph-item').forEach((row, index) => {
			const remove_element = document.getElementById('items_' + index + '_remove');

			if (remove_element) {
				remove_element.setAttribute('data-remove', index);
			}
		});

		this.form.discoverAllFields();
	}

	#openEditItemPopup(target) {
		const item_num = target.id.match(/\d+/g);
		const flag_field = this.form.findFieldByName(`items[${item_num}][flags]`);
		const is_prototype = flag_field.getValue() == <?= ZBX_FLAG_DISCOVERY_PROTOTYPE ?>;

		const parameters = this.#getItemPopupParameters(item_num, is_prototype);

		PopUp('popup.generic', parameters, {dialogue_class: "modal-popup-generic", trigger_element: target});
	}

	#openAddItemPopup(is_prototype = false) {
		const parameters = this.#getItemPopupParameters(null, is_prototype);

		PopUp('popup.generic', parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#getItemPopupParameters(item_num = null, is_prototype = false) {
		const parameters = {
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_element.getAttribute('name'),
			numeric: 1,
			writeonly: 1
		};

		if (item_num === null) {
			parameters.multiselect = 1;
		}
		else {
			parameters.dstfld1 = 'items_' + item_num + '_itemid';
			parameters.dstfld2 = 'items_' + item_num + '_name';
		}

		if (is_prototype) {
			parameters.srctbl = 'item_prototypes';
			parameters.parent_discoveryid = this.graph.parent_discoveryid;

			if (item_num !== null) {
				parameters.srcfld3 = 'flags';
				parameters.dstfld3 = 'items_' + item_num + '_flags';
			}
		}
		else {
			parameters.srctbl = 'items';
		}

		if (this.graph.is_template) {
			parameters.only_hostid = this.graph.hostid
		}
		else {
			parameters.real_hosts = 1;
			parameters.hostid = this.graph.hostid;
		}

		return parameters;
	}

	#removeItem(target) {
		const row = target.closest('tr');

		row.nextSibling.remove();
		row.remove();

		this.#recalculateSortOrder();
	}

	#updateItemsTable(graph_type) {
		const tbody = this.form_element.querySelector('#items-table tbody');
		const template_type = graph_type == <?= GRAPH_TYPE_EXPLODED ?> ?  <?= GRAPH_TYPE_PIE ?> : graph_type;
		const template = new Template(document.getElementById(`tmpl-item-row-${template_type}`).innerHTML);
		const rows = [...tbody.querySelectorAll('tr.graph-item')];

		rows.forEach((row, index) => {
			let row_data = {};

			row.querySelectorAll('input[type="hidden"]').forEach(input => {
				const match = input.name.match(/\[(\w+)\]$/);
				if (match) {
					const key = match[1];
					row_data[key] = input.value;
				}
			});

			row_data.number = index;

			const name_element = row.querySelector('[id^="items_"][id$="_name"]');
			row_data.name = name_element.textContent;

			const new_row = template.evaluateToElement(row_data);

			if (!('gitemid' in row_data)) {
				new_row.querySelector(`input[name="items[${index}][gitemid]"]`).remove();
			}

			// Replace the old row with the new row.
			tbody.replaceChild(new_row, row);
		});

		this.form.discoverAllFields();
	}

	#loadItem(item, template) {
		const $row = $(template.evaluate(item));

		$('#item-buttons-row').before($row);
	}

	#openItemPrototypeSelectPopup(popup_parameters = {}) {
		const parameters = {
			srctbl: 'item_prototypes',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_element.getAttribute('name'),
			numeric: '1',
			parent_discoveryid: this.graph.parent_discoveryid
		}

		Object.assign(parameters, popup_parameters);

		PopUp('popup.generic', parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#toggleYAxisFields(target, yaxis) {
		const text_field = this.form_element.querySelector(`#yaxis_${yaxis}_value`);
		const ms_field = this.form_element.querySelector(`#yaxis_${yaxis}_ms`);
		const ms_prototype_button = this.form_element.querySelector(`#yaxis_${yaxis}_prototype_ms`);

		const display_text_field = target.value == <?= GRAPH_YAXIS_TYPE_FIXED ?>;
		const display_ms_field = target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>;
		const display_ms_prototype = display_ms_field && this.graph.parent_discoveryid;

		text_field.style.display = display_text_field ? '' : 'none';
		text_field.querySelectorAll('input').forEach(input => {
			input.disabled = !display_text_field;
		});

		ms_field.style.display = display_ms_field ? '' : 'none';
		ms_field.querySelectorAll('input').forEach(input => {
			input.disabled = !display_ms_field;
		});

		ms_prototype_button.style.display = display_ms_prototype ? '' : 'none';

		this.form_element.querySelector(`label[for="y${yaxis}_type_label"]`)
			.classList.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>',
				target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>
			);
	}

	addPopupValues(list) {
		if (!Array.isArray(list) || !list.every(item => item && typeof item === 'object' && 'itemid' in item)) {
			return false;
		}

		const template_type = this.graph_type == <?= GRAPH_TYPE_EXPLODED ?> ? <?= GRAPH_TYPE_PIE ?> : this.graph_type;
		const template = new Template(document.getElementById(`tmpl-item-row-${template_type}`).innerHTML);

		for (let i = 0; i < list.length; i++) {
			const used_colors = [];

			for (const color_picker of this.form_element.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`)) {
				if (color_picker.color !== '') {
					used_colors.push(color_picker.color);
				}
			}

			const number = document.querySelectorAll('#items-table tbody tr.graph-item').length;

			const item = {
				number: number,
				gitemid: null,
				itemid: list[i].itemid,
				calc_fnc: null,
				drawtype: 0,
				yaxisside: 0,
				sortorder: number,
				flags: (list[i].flags === undefined) ? 0 : list[i].flags,
				color: colorPalette.getNextColor(used_colors),
				name: list[i].name
			};

			this.items.push(item);

			const tbody = document.createElement('tbody');
			tbody.innerHTML = template.evaluate(item);

			tbody.querySelector(`input[name="items[${number}][gitemid]"]`).remove();
			const button_row = document.getElementById('item-buttons-row');

			tbody.querySelectorAll('tr').forEach(row => {
				button_row.parentNode.insertBefore(row, button_row);
			});
		}
	}

	#clone() {
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'clone');
		form_refresh.setAttribute('value', '1');

		this.form.findFieldByName('graphid').getField().remove();
		this.graph.graphid = 0;

		this.form.release();

		reloadPopup(this.form_element, this.action);
	}

	#delete() {
		const confirm_message = this.action === 'graph.edit'
			? <?= json_encode(_('Delete graph?')) ?>
			: <?= json_encode(_('Delete graph prototype?')) ?>;


		if (window.confirm(confirm_message)) {
			this.#removePopupMessages();

			const fields = {
				action: this.action === 'graph.edit' ? 'graph.delete' : 'graph.prototype.delete',
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('graph')) ?>
			};

			this.#post(zabbixUrl(fields), {graphids: [this.graph.graphid]}, (response) => {
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			});
		}
		else {
			this.overlay.unsetLoading();
		}
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				const action = this.action === 'graph.edit'
					? (this.graph.graphid ? 'graph.update' : 'graph.create')
					: (this.graph.graphid ? 'graph.prototype.update' : 'graph.prototype.create');

				this.#post(zabbixUrl({action}), fields, (response) => {
					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
			});
	}

	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.overlay.unsetLoading());
	}

	#isConfirmed() {
		return JSON.stringify(this.initial_form_fields) === JSON.stringify(getFormFields(this.form.getAllValues()))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	#initPopupListeners() {
		const subscriptions = [];

		for (const action of ['graph.edit', 'graph.prototype.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({data, event}) => {
						if (data.action_parameters.graphid === this.graph.graphid || this.graph.graphid == 0) {
							return;
						}

						if (!this.#isConfirmed()) {
							event.preventDefault();
						}
					}
				})
			);
		}

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}

	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
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

		this.form_element.parentNode.insertBefore(message_box, this.form_element);
	}
};
