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

	init({form_name, theme_colors, graphs, items, context, parent_discoveryid, return_url, overlayid}) {
		this.form_name = form_name;
		this.graphs = graphs;
		this.context = context;
		this.is_discovery = parent_discoveryid !== null;
		this.graph_type = this.graphs.graphtype;
		this.overlay = overlays_stack.getById(overlayid);
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

		// Rewrite IDs, set "tmp" prefix.
		$('#items-table tbody tr.graph-item').find('*[id]').each(function() {
			const $obj = $(this);

			$obj.attr('id', 'tmp' + $obj.attr('id'));
		});

		$('#items-table tbody tr.graph-item').each(function() {
			const $obj = $(this);

			$obj.attr('id', 'tmp' + $obj.attr('id'));
		});

		for (const [index, row] of document.querySelectorAll('#itemsTable tbody tr.graph-item').entries()) {
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

		!this.graphs.readonly &&this.#rewriteNameLinks();
	}

	#rewriteNameLinks() {
		const size = $('#itemsTable tbody tr.graph-item').length;

		for (let i = 0; i < size; i++) {
			const parameters = {
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this.form_name,
				dstfld1: 'items_' + i + '_itemid',
				dstfld2: 'items_' + i + '_name',
				numeric: 1,
				writeonly: 1
			};

			if ($('#items_' + i + '_flags').val() == <?= ZBX_FLAG_DISCOVERY_PROTOTYPE ?>) {
				parameters['srctbl'] = 'item_prototypes',
					parameters['srcfld3'] = 'flags',
					parameters['dstfld3'] = 'items_' + i + '_flags',
					parameters['parent_discoveryid'] = this.graphs.parent_discoveryid;
			}
			else {
				parameters['srctbl'] = 'items';
			}

			if (this.graphs.normal_only !== '') {
				parameters['normal_only'] = '1';
			}

			if (!this.graphs.parent_discoveryid && this.graphs.hostid) {
				parameters['hostid'] = this.graphs.hostid;
			}

			$('#items_' + i + '_name').attr('onclick', 'PopUp("popup.generic", ' +
				'$.extend(' + JSON.stringify(parameters) + ', view.getOnlyHostParam()),' +
				'{dialogue_class: "modal-popup-generic", trigger_element: this.parentNode});'
			);
		}
	}

	#initActions() {
		// on graph type change
		this.form.querySelector('#graphtype').addEventListener('change', (e) => {
			this.#toggleGraphTypeFields(e.target.value);
			this.graph_type = e.target.value;
		});

		this.#toggleGraphTypeFields(this.form.querySelector('#graphtype').value);

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
		this.#toggleYAxisFields(ymin_type, 'max');

		this.form.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this.#openItemSelectPopup();
			}
		});

		// Percent fields
		this.form.querySelector('#visible_percent_left').addEventListener('change', () => {
			this.#togglePercentField('left');
		});

		this.form.querySelector('#visible_percent_right').addEventListener('change', () => {
			this.#togglePercentField('right');
		});

		this.#togglePercentField('left');
		this.#togglePercentField('right');

		// todo - fix sortable
		if (this.form.querySelector('#items-table tbody')) {
			new CSortable(this.form.querySelector('#items-table tbody'), {
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.graphs.readonly
			}).on(CSortable.EVENT_SORT, this.#recalculateSortOrder);
		}

		//!this.graphs.readonly && this.#rewriteNameLinks();

		//this.initPopupListeners();
	}

	#loadItem(item) {
		const item_template = new Template($('#tmpl-item-row-' + this.graph_type).html());

		const $row = $(item_template.evaluate(item));

		$('#item-buttons-row').before($row);
		$row.find('.<?= ZBX_STYLE_COLOR_PICKER ?> input').colorpicker();

		!this.graphs.readonly && this.#rewriteNameLinks();
	}

	#togglePercentField(type) {
		if (this.form.querySelector(`input[name="visible[percent_${type}]"]:checked`)) {
			this.form.querySelector(`#percent_${type}`).style.display = '';
		}
		else {
			this.form.querySelector(`#percent_${type}`).style.display = 'none';
		}
	}

	#openItemSelectPopup() {
		PopUp('popup.generic', {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_name,
			numeric: '1',
			writeonly: '1',
			multiselect: '1'
		}, {dialogue_class: 'modal-popup-generic'});
	}

	#toggleYAxisFields(target, yaxis) {
		const text_field = this.form.querySelector(`#yaxis_${yaxis}_value`);
		const ms_field = this.form.querySelector(`#yaxis_${yaxis}_ms`);

		text_field.style.display = (target.value == <?= GRAPH_YAXIS_TYPE_FIXED ?>) ? '' : 'none';
		ms_field.style.display = (target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>) ? '' : 'none';

		this.form.querySelector(`label[for="y${yaxis}_type_label"]`)
			.classList.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', target.value == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>
		);
	}

	#toggleGraphTypeFields(graph_type) {
		const work_period_field = this.form.querySelector('#show_work_period_field');
		const show_triggers_field = this.form.querySelector('#show_triggers_field');
		const percent_left_field = this.form.querySelector('#percent_left_field');
		const percent_right_field = this.form.querySelector('#percent_right_field');
		const yaxis_min_field = this.form.querySelector('#yaxis_min_field');
		const yaxis_max_field = this.form.querySelector('#yaxis_max_field');
		const show_3d_field = this.form.querySelector('#show_3d_field');

		// Toggle fields.
		let show_fields = [];
		let hide_fields = [];

		switch (parseInt(graph_type)) {
			case <?= GRAPH_TYPE_NORMAL ?>:
				show_fields = [work_period_field, show_triggers_field, percent_left_field, percent_right_field,
					yaxis_min_field, yaxis_max_field
				];
				hide_fields = [show_3d_field];
				break;

			case <?= GRAPH_TYPE_STACKED ?>:
				show_fields = [work_period_field, show_triggers_field, yaxis_min_field, yaxis_max_field];
				hide_fields = [percent_left_field, percent_right_field, show_3d_field];
				break;

			case <?= GRAPH_TYPE_PIE ?>:
			case <?= GRAPH_TYPE_EXPLODED ?>:
				show_fields = [show_3d_field];
				hide_fields = [work_period_field, show_triggers_field, percent_left_field, percent_right_field,
					yaxis_min_field, yaxis_max_field
				];
				break;
		}

		show_fields.forEach(field => {
			field.style.display = '';
			field.previousElementSibling.style.display = '';
		});

		hide_fields.forEach(field => {
			field.style.display = 'none';
			field.previousElementSibling.style.display = 'none';
		});

		// Toggle items table columns and update column classes.
		const table = this.form.querySelector('#items_table');
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

			// todo - rewrite all existing rows using the new template!
	}

	#initPreviewTab() {
		$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
			const $panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

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

				$('#items_table tbody tr.graph-item').each((i, node) => {
					const short_fmt = [];

					$(node).find('*[name]').each((_, input) => {
						if (!$.isEmptyObject(input) && input.name != null) {
							const regex = /items\[\d+\]\[([a-zA-Z0-9\-\_\.]+)\]/;
							const name = input.name.match(regex);

							short_fmt.push((name[1]).substr(0, 2) + ':' + input.value);
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
			const $row = $(item_row_template.evaluate(item));

			$('#item-buttons-row').before($row);
			$row.find('#items_' + number + '_calc_fnc').val('<?= CALC_FNC_AVG ?>');
			// todo - fix colorpicker
			//$(`#items_${number}_color`).colorpicker();

		}

		//!this.graphs.readonly && this.rewriteNameLinks();
	}

	refresh() {
		const url = new Curl('');
		const form = document.getElementsByName(this.form_name)[0];
		const fields = getFormFields(form);

		post(url.getUrl(), fields);
	}

	initPopupListeners() {
		ZABBIX.EventHub.subscribe({
			require: {
				context: CPopupManager.EVENT_CONTEXT,
				event: CPopupManagerEvent.EVENT_SUBMIT
			},
			callback: ({data, event}) => {
				if (data.submit.success.action === 'delete') {
					// todo - update to graph.list:
					const url = new URL(this.is_discovery ? 'host_discovery.php' : 'graphs.php', location.href);

					url.searchParams.set('context', this.context);

					event.setRedirectUrl(url.href);
				}
				else {
					this.refresh();
				}
			}
		});
	}
}
