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

window.graph_edit_popup = new class {

	init({form_name, action, theme_colors, graphs, readonly, items, context, hostid, overlayid, return_url}) {
		this.form_name = form_name;
		this.action = action;
		this.graph = graphs;
		this.readonly = readonly;
		this.context = context;
		this.hostid = hostid;
		this.graph_type = this.graph.graphtype;
		this.overlay = overlays_stack.getById(overlayid);
		this.dialogue = this.overlay.$dialogue[0];
		this.return_url = return_url;
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		colorPalette.setThemeColors(theme_colors);

		ZABBIX.PopupManager.setReturnUrl(return_url);

		window.addPopupValues = (data) => {
			this.addPopupValues(data.values);
		}

		this.items = items;

		this.items.forEach((item, i) => {
			item.number = i;
			item.name = item.host + '<?= NAME_DELIMITER ?>' + item.name;

			this.#loadItem(item);
		});

		this.#initActions();
		this.#initPreviewTab();
		this.overlay.recoverFocus();
	}

	#recalculateSortOrder() {
		let i = 0;

		$('#items-table tbody tr.graph-item').find('*[id]').each(function() {
			const $obj = $(this);

			$obj.attr('id', 'tmp' + $obj.attr('id'));
		});

		$('#items-table tbody tr.graph-item').each(function() {
			const $obj = $(this);

			$obj.attr('id', 'tmp' + $obj.attr('id'));
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

		$('#items-table tbody tr.graph-item').each(function() {
			// Set remove number.
			$('#items_' + i + '_remove').data('remove', i);

			i++;
		});
	}

	#initActions() {
		this.form.querySelector('#graphtype').addEventListener('change', (e) => {
			this.#toggleGraphTypeFields(e.target.value);
			this.#updateItemsTable(e.target.value);

			this.graph_type = e.target.value;
		});

		this.#toggleGraphTypeFields(this.form.querySelector('#graphtype').value);

		// todo - move to 1 method?
		const ymin_type = this.form.querySelector('#ymin_type');

		if (ymin_type) {
			ymin_type.addEventListener('change', (e) => {
				this.#toggleYAxisFields(e.target, 'min');
			});
		}

		const ymax_type = this.form.querySelector('#ymax_type');

		if (ymax_type) {
			ymax_type.addEventListener('change', (e) => {
				this.#toggleYAxisFields(e.target, 'max');
			});
		}

		this.#toggleYAxisFields(ymin_type, 'min');
		this.#toggleYAxisFields(ymax_type, 'max');

		// Percent fields
		this.form.querySelector('#visible_percent_left').addEventListener('change', () => {
			this.#togglePercentField('left');
		});

		this.form.querySelector('#visible_percent_right').addEventListener('change', () => {
			this.#togglePercentField('right');
		});

		this.#togglePercentField('left');
		this.#togglePercentField('right');

		// Initialize sortable instance for items table.
		if (this.form.querySelector('#items-table tbody')) {
			new CSortable(this.form.querySelector('#items-table tbody'), {
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.readonly
			}).on(CSortable.EVENT_SORT, this.#recalculateSortOrder);
		}

		document.getElementById('items-table').addEventListener('click', (e) => {
			if (e.target.classList.contains('js-item-name')) {
				this.#openItemPopup(e.target);
			}
			else if (e.target.classList.contains('js-add-item')) {
				this.#openItemSelectPopup();
			}
			else if (e.target.classList.contains('js-add-item-prototype')) {
				this.#openItemPrototypeSelectPopup();
			}
			else if (e.target.classList.contains('js-remove')) {
				this.#removeItem(e.target);
			}
		});

		this.#toggleColorPickerStatus();
	}

	#openItemPopup(target) {
		const item_num = target.id.match(/\d+/g);
		const parameters = {
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_name,
			dstfld1: 'items_' + item_num + '_itemid',
			dstfld2: 'items_' + item_num + '_name',
			numeric: 1,
			writeonly: 1,
			normal_only: 1
		};

		if ($('#items_' + item_num + '_flags') == <?= ZBX_FLAG_DISCOVERY_PROTOTYPE ?>) {
			parameters['srctbl'] = 'item_prototypes';
			parameters['srcfld3'] = 'flags';
			parameters['dstfld3'] = 'items_' + item_num + '_flags';
			parameters['parent_discoveryid'] = this.graph.parent_discoveryid;
		}
		else {
			parameters['srctbl'] = 'items';
		}

		if (!this.graph.parent_discoveryid && this.graph.hostid) {
			parameters['hostid'] = this.graph.hostid;
		}

		if (this.graph.is_template) {
			parameters['only_hostid'] = this.graph.hostid
		}
		else {
			parameters['real_hosts'] = '1';
			parameters['hostid'] = this.graph.hostid;
		}

		PopUp('popup.generic', parameters, {dialogue_class: "modal-popup-generic", trigger_element: target});
	}

	#toggleColorPickerStatus() {
		if (this.graph.readonly) {
			const size = $('#items-table tbody tr.graph-item').length;

			for (let i = 0; i < size; i++) {
				$('#items_' + i + '_color')
					.removeAttr('onchange')
					.prop('readonly', true);
				$('#lbl_items_' + i + '_color')
					.removeAttr('onclick')
					.prop('readonly', true);
			}
		}
	}

	#removeItem(target) {
		const number = target.dataset.remove;
		this.form.querySelector(`#items_${number}`).remove();
	}

	#updateItemsTable(graph_type) {
		const tbody = this.form.querySelector('#items-table tbody');
		const item_row_template = new Template($('#tmpl-item-row-' + graph_type).html());
		const rows = Array.from(tbody.querySelectorAll('tr.graph-item'));

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

			const new_row_html = item_row_template.evaluate(row_data);

			const temp = document.createElement('tbody');
			temp.innerHTML = new_row_html.trim();
			const new_row = temp.firstElementChild;

			// Replace the old row with the new row.
			tbody.replaceChild(new_row, row);

			const $row = $(new_row);
			$row.find('#items_' + index + '_calc_fnc').val('<?= CALC_FNC_AVG ?>');
			$(`#items_${index}_color`).colorpicker();
		});
	}

	#loadItem(item) {
		const item_template = new Template($('#tmpl-item-row-' + this.graph_type).html());

		const $row = $(item_template.evaluate(item));

		$('#item-buttons-row').before($row);
		$row.find('.<?= ZBX_STYLE_COLOR_PICKER ?> input').colorpicker();
	}

	#togglePercentField(type) {
		const percent_field = this.form.querySelector(`#percent_${type}`);

		if (this.form.querySelector(`input[name="visible[percent_${type}]"]:checked`)) {
			percent_field.style.display = '';
			percent_field.disabled = false;
		}
		else {
			percent_field.style.display = 'none';
			percent_field.disabled = true;
		}
	}

	#openItemSelectPopup() {
		const parameters = {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_name,
			numeric: 1,
			writeonly: 1,
			multiselect: 1,
			hostid: this.hostid
		};

		if (this.graph.normal_only == 1) {
			parameters['normal_only'] = this.graph.normal_only;
		}

		PopUp('popup.generic', parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#openItemPrototypeSelectPopup() {
		const parameters = {
			srctbl: 'item_prototypes',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_name,
			numeric: '1',
			writeonly: '1',
			multiselect: '1',
			graphtype: this.graph_type,
			parent_discoveryid: this.graph.parent_discoveryid
		}

		if (this.graph.normal_only == 1) {
			parameters['normal_only'] = this.graph.normal_only;
		}

		PopUp('popup.generic', parameters, {dialogue_class: 'modal-popup-generic'});
	}

	#toggleYAxisFields(target, yaxis) {
		const text_field = this.form.querySelector(`#yaxis_${yaxis}_value`);
		const ms_field = this.form.querySelector(`#yaxis_${yaxis}_ms`);

		const display_text_field = target.value == <?= GRAPH_YAXIS_TYPE_FIXED ?>;
		const display_ms_field = target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>;

		text_field.style.display = display_text_field ? '' : 'none';
		text_field.querySelectorAll('input').forEach(input => {
			input.disabled = !display_text_field;
		});

		ms_field.style.display = display_ms_field ? '' : 'none';
		ms_field.querySelectorAll('input').forEach(input => {
			input.disabled = !display_ms_field;
		});

		this.form.querySelector(`label[for="y${yaxis}_type_label"]`)
			.classList.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>
		);
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
				show_fields = ['#show_work_period_field', '#show_triggers_field', '#yaxis_min_field', '#yaxis_max_field'];
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
			const field_element = this.form.querySelector(field);

			field_element.style.display = '';
			field_element.previousElementSibling.style.display = '';
			field_element.querySelectorAll('input').forEach((input) => input.disabled = false);
		});

		hide_fields.forEach((field) => {
			const field_element = this.form.querySelector(field);

			field_element.style.display = 'none';
			field_element.previousElementSibling.style.display = 'none';
			field_element.querySelectorAll('input').forEach((input) => input.disabled = true);
		});

		// Update items table columns and update column classes.
		const table = this.form.querySelector('#items-table');
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

	#initPreviewTab() {
		$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
			const $panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;;

			if ($panel.attr('id') === 'preview-tab') {
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

		if (this.readonly) {
			const size = $('#items-table tbody tr.graph-item').length;

			for (let i = 0; i < size; i++) {
				$('#items_' + i + '_color')
					.removeAttr('onchange')
					.prop('readonly', true);
				$('#lbl_items_' + i + '_color')
					.removeAttr('onclick')
					.prop('readonly', true);
			}
		}
	}

	addPopupValues(list) {
		// todo - update method
		// if (!isset('object', list) || list.object != 'itemid') {
		//	return false;
		// }

		const item_row_template = new Template($('#tmpl-item-row-' + this.graph_type).html());

		for (let i = 0; i < list.length; i++) {
			const used_colors = [];

			for (const color of this.form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
				if (color.value !== '') {
					used_colors.push(color.value);
				}
			}

			const number = $('#items-table tbody tr.graph-item').length;

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

			const $row = $(item_row_template.evaluate(item));

			$('#item-buttons-row').before($row);
			$row.find('#items_' + number + '_calc_fnc').val('<?= CALC_FNC_AVG ?>');
			$(`#items_${number}_color`).colorpicker();
		}
	}

	clone() {
		const form_refresh = document.createElement('input');

		form_refresh.setAttribute('type', 'hidden');
		form_refresh.setAttribute('name', 'clone');
		form_refresh.setAttribute('value', 1);
		this.form.append(form_refresh);

		this.form.querySelector('[name="graphid"]').remove();
		this.graph.graphid = 0;

		reloadPopup(this.form, this.action);
	}

	delete() {
		const action = this.action === 'graph.edit' ? 'graph.delete' : 'graph.prototype.delete';

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', action);
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('graph')) ?>);

		this.#post(curl.getUrl(), {graphids: [this.graph.graphid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);
		fields.name = fields.name.trim();

		const curl = new Curl('zabbix.php');

		if (this.action === 'graph.edit') {
			curl.setArgument('action', !this.graph.graphid ? 'graph.create' : 'graph.update');
		}
		else {
			curl.setArgument('action', !this.graph.graphid ? 'graph.prototype.create' : 'graph.prototype.update');
		}

		this.#post(curl.getUrl(), fields);
	}

	#post(url, data) {
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
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
