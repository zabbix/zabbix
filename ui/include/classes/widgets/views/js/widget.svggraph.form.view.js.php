<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
?>


window.widget_svggraph_form = new class {

	constructor() {
		this._dataset_number = 0;
	}

	init({form_id, form_tabs_id}) {
		colorPalette.setThemeColors(<?= json_encode(CWidgetFieldGraphDataSet::DEFAULT_COLOR_PALETTE) ?>);

		this.overlay_body = jQuery('.overlay-dialogue-body');
		this.form = document.getElementById(form_id);
		this.form_id = form_id;
		this.form_tabs = form_tabs_id;

		this.dataset_wrapper = document.getElementById('data_sets');

		this.overlay_body.on('scroll', () => {
			const $preview_container = jQuery('.<?= ZBX_STYLE_SVG_GRAPH_PREVIEW ?>');

			if (!$preview_container.length) {
				this.overlay_body.off('scroll');
				return;
			}

			if ($preview_container.offset().top < this.overlay_body.offset().top && this.overlay_body.height() > 400) {
				jQuery('#svg-graph-preview').css('top',
					this.overlay_body.offset().top - $preview_container.offset().top
				);
				jQuery('.graph-widget-config-tabs .ui-tabs-nav').css('top', $preview_container.height());
			}
			else {
				jQuery('#svg-graph-preview').css('top', 0);
				jQuery('.graph-widget-config-tabs .ui-tabs-nav').css('top', 0);
			}
		});

		jQuery(`#${this.form_tabs}`)
			.on('tabsactivate', () => jQuery.colorpicker('hide'))
			.on('change', 'input, z-select, .multiselect', (e) => this.onGraphConfigChange(e));

		jQuery('.overlay-dialogue').on('overlay-dialogue-resize', (event, size_new, size_old) => {
			if (jQuery('#svg-graph-preview').length) {
				if (size_new.width != size_old.width) {
					this._updatePreview();
				}
			} else {
				jQuery('.overlay-dialogue').off('overlay-dialogue-resize');
			}
		});

		this._datasetTabInit();
		this._timePeriodTabInit();
		this._legendTabInit();
		this._problemsTabInit();

		this.onGraphConfigChange();
	}

	_datasetTabInit() {
		this._dataset_number = this.dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		// Initialize vertical accordion.
		jQuery(this.dataset_wrapper)
			.on('focus', '.<?= CMultiSelect::ZBX_STYLE_CLASS ?> input.input', function() {
				jQuery('#data_sets').zbx_vertical_accordion('expandNth',
					jQuery(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index()
				);
			})
			.on('click', function(e) {
				if (!e.target.classList.contains('color-picker-preview')) {
					jQuery.colorpicker('hide');
				}

				if (e.target.classList.contains('js-click-expend')
						|| e.target.classList.contains('color-picker-preview')
						|| e.target.classList.contains('<?= ZBX_STYLE_BTN_GREY ?>')) {
					jQuery('#data_sets').zbx_vertical_accordion('expandNth',
						jQuery(e.target).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index()
					);
				}
			})
			.on('collapse', function(event, data) {
				jQuery('textarea, .multiselect', data.section).scrollTop(0);
				jQuery(window).trigger('resize');
				const dataset = data.section[0];

				if (dataset.dataset.type == '<?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'block';
					}
				}
			})
			.on('expand', function(event, data) {
				jQuery(window).trigger('resize');
				const dataset = data.section[0];

				if (dataset.dataset.type == '<?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'none';
					}

					widget_svggraph_form.recalculateSortOrder();
					widget_svggraph_form.initSingleItemSortable();
				}
			})
			.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>'});

		for (const element of this.dataset_wrapper.querySelectorAll('.js-type, .js-stacked')) {
			element.addEventListener('change', () => this._updatedForm());
		}

		// Initialize rangeControl UI elements.
		jQuery('.<?= CRangeControl::ZBX_STYLE_CLASS ?>', jQuery(this.dataset_wrapper)).rangeControl();

		// Initialize pattern fields.
		jQuery('.multiselect', jQuery(this.dataset_wrapper)).each(function() {
			jQuery(this).multiSelect(jQuery(this).data('params'));
		});

		for (const colorpicker of jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({
				onUpdate: function(color) {
					jQuery('.<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>',
						jQuery(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')
					).css('background-color', `#${color}`);
				},
				appendTo: '.overlay-dialogue-body'
			});

			colorPalette.incrementNextColor();
		}

		this.initDataSetSortable();

		this.overlay_body.on('change', 'z-select[id$="aggregate_function"]', (e) => {
			widget_svggraph_form.changeDataSetAggregateFunction(e.target);
		});

		this.rewriteNameLinks();

		this.dataset_wrapper.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this._selectItems();
			}

			if (e.target.classList.contains('element-table-remove')) {
				this.removeSingleItem(e.target);
			}

			if (e.target.classList.contains('btn-remove')) {
				this.removeDataSet(e.target);
			}
		});

		this.initSingleItemSortable();

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => this._addDataset(<?= CWidgetHelper::DATASET_TYPE_PATTERN_ITEM ?>), false);

		document
			.getElementById('dataset-menu')
			.addEventListener('click', this._addDatasetMenu);
	}

	_timePeriodTabInit() {
		document.getElementById('graph_time')
			.addEventListener('click', (e) => {
				document.getElementById('time_from').disabled = !e.target.checked;
				document.getElementById('time_to').disabled = !e.target.checked;
				document.getElementById('time_from_calendar').disabled = !e.target.checked;
				document.getElementById('time_to_calendar').disabled = !e.target.checked;
			});
	}

	_legendTabInit() {
		document.getElementById('legend')
			.addEventListener('click', (e) => {
				jQuery('#legend_lines').rangeControl(
					e.target.checked ? 'enable' : 'disable'
				);
				if (!e.target.checked) {
					jQuery('#legend_columns').rangeControl('disable');
				}
				else if (!document.getElementById('legend_statistic').checked) {
					jQuery('#legend_columns').rangeControl('enable');
				}
				document.getElementById('legend_statistic').disabled = !e.target.checked;
			});

		document.getElementById('legend_statistic')
			.addEventListener('click', (e) => {
				jQuery('#legend_columns').rangeControl(
					!e.target.checked ? 'enable' : 'disable'
				);
			});
	}

	_problemsTabInit() {
		const widget = document.getElementById('problems');

		document.getElementById('show_problems')
			.addEventListener('click', (e) => {
				jQuery('#graph_item_problems, #problem_name, #problemhosts_select').prop('disabled', !e.target.checked);
				jQuery('#problemhosts_').multiSelect(e.target.checked ? 'enable' : 'disable');
				jQuery('[name^="severities["]', jQuery(widget)).prop('disabled', !e.target.checked);
				jQuery('[name="evaltype"]', jQuery(widget)).prop('disabled', !e.target.checked);
				jQuery('input, button, z-select', jQuery('#tags_table_tags', jQuery(widget))).prop('disabled',
					!e.target.checked
				);
			});
	}

	_selectItems() {
		PopUp('popup.generic', {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_id,
			numeric: 1,
			writeonly: 1,
			multiselect: 1,
			with_webitems: 1,
			real_hosts: 1
		});
	}

	_addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(<?= CWidgetHelper::DATASET_TYPE_PATTERN_ITEM ?>, false)
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(<?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>, false)
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						disabled: jQuery(
							'#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>'
						).length === 0,
						clickCallback: () => {
							widget_svggraph_form.clone();
						}
					}
				]
			}
		];

		jQuery(e.target).menuPopup(menu, new jQuery.Event(e), {
			position: {
				of: e.target,
				my: 'left top',
				at: 'left bottom',
				within: '.wrapper'
			}
		});
	}

	_addDataset(type, clone) {
		jQuery(this.dataset_wrapper).zbx_vertical_accordion('collapseAll');

		const template = (type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>)
			? new Template(jQuery('#dataset-single-item-tmpl').html())
			: new Template(jQuery('#dataset-pattern-item-tmpl').html());

		jQuery('#data_sets').append(
			template.evaluate({
				rowNum: this._dataset_number++,
				color: (type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>)
					? ''
					: colorPalette.getNextColor()
			})
		);

		this.overlay_body.scrollTop(Math.max(this.overlay_body.scrollTop(),
			jQuery('#widget-dialogue-form')[0].scrollHeight - this.overlay_body.height()
		));

		jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input').colorpicker({onUpdate: function(color) {
			jQuery('.<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>',
					jQuery(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')
			).css('background-color', `#${color}`);
		}, appendTo: '.overlay-dialogue-body'});

		jQuery('.multiselect', jQuery(this.dataset_wrapper)).each(function() {
			jQuery(this).multiSelect(jQuery(this).data('params'));
		});

		jQuery('.<?= CRangeControl::ZBX_STYLE_CLASS ?>',
			jQuery('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>')
		).rangeControl();

		if (!clone) {
			this.recalculateSortOrder();
			this.updateVariableOrder(jQuery(this.dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');

			this.initDataSetSortable();
			this.initSingleItemSortable();

			this.onGraphConfigChange();
		}
	}

	removeDataSet(obj) {
		obj
			.closest('.list-accordion-item')
			.remove();

		this.recalculateSortOrder();
		this.updateVariableOrder(jQuery(this.dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');

		this.initDataSetSortable();
		this.initSingleItemSortable();
		this.onGraphConfigChange();
	}

	getDataSetNumber() {
		if (jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]').length) {
			return jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]').attr('data-set');
		}

		return jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>[data-set]:last').attr('data-set');
	}

	onGraphConfigChange() {
		this._updatedForm();
		this._updatePreview();
	}

	updateVariableOrder(obj, row_selector, var_prefix) {
		for (const k of [10000, 0]) {
			jQuery(row_selector, obj).each(function(i) {
				console.log('ROW:', i);

				if (var_prefix === 'ds') {
					jQuery(this).attr('data-set', i);
					jQuery('.single-item-table', this).attr('data-set', i);
				}

				jQuery('.multiselect[data-params]', this).each(function() {
					const name = jQuery(this).multiSelect('getOption', 'name');

					if (name !== null) {
						jQuery(this).multiSelect('modify', {
							name: name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${k + i}$2`)
						});
					}
				});

				jQuery(`[name^="${var_prefix}["]`, this)
					.filter(function () {
						return jQuery(this).attr('name').match(/[a-z]+\[\d+]\[[a-z_]+]/);
					})
					.each(function () {
						jQuery(this).attr('name',
							jQuery(this).attr('name').replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${k + i}$2`)
						);
					});
			});
		}
	}

	initDataSetSortable() {
		if (jQuery('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length === 1) {
			jQuery('#data_sets .js-main-drag-icon').addClass('disabled');
		}
		else {
			jQuery('#data_sets .js-main-drag-icon').removeClass('disabled');
		}

		if (this.sortable_data_set !== undefined) {
			this.sortable_data_set.deactivate();
		}

		this.sortable_data_set = new CSortable(
			document.querySelector('#data_set .<?= ZBX_STYLE_LIST_VERTICAL_ACCORDION ?>'),
			{is_vertical: true}
		);

		this.sortable_data_set.on(SORTABLE_EVENT_DRAG_END, () => {
			widget_svggraph_form.updateVariableOrder(jQuery('#data_sets'), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>',
				'ds'
			);
			widget_svggraph_form._updatePreview();
		});
	}

	initSingleItemSortable() {
		const dataset_number = this.getDataSetNumber();

		if (jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row').length == 1) {
			jQuery('.single-item-table[data-set='+dataset_number+'] .<?= ZBX_STYLE_DRAG_ICON ?>').addClass('disabled');
		}
		else {
			jQuery('.single-item-table[data-set=' + dataset_number + '] .<?= ZBX_STYLE_DRAG_ICON ?>')
				.removeClass('disabled');
		}

		jQuery('.single-item-table[data-set='+dataset_number+']').sortable({
			disabled: jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row').length < 2,
			items: 'tbody .single-item-table-row',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: this.recalculateSortOrder,
			helper: (e, ui) => {
				for (const td of ui.find('>td')) {
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
			stop: (e, ui) => {
				ui.item.find('>td').removeAttr('width');
			},
			start: (e, ui) => {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});
	}

	rewriteNameLinks() {
		[...document.querySelectorAll('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>[data-set]')].map((element) => {
			const dataset_number = element.getAttribute('data-set');
			const size = jQuery('.single-item-table-row', jQuery(element)).length + 1;

			for (let i = 0; i < size; i++) {
				jQuery('#items_' + dataset_number + '_' + i + '_name').off('click').on('click', () => {
					let ids = [];
					for (let i = 0; i < size; i++) {
						ids.push(jQuery('#items_' + dataset_number + '_' + i + '_input').val());
					}

					PopUp('popup.generic', {
						srctbl: 'items',
						srcfld1: 'itemid',
						srcfld2: 'name',
						dstfrm: widget_svggraph_form.form_id,
						dstfld1: `items_${dataset_number}_${i}_input`,
						dstfld2: `items_${dataset_number}_${i}_name`,
						numeric: 1,
						writeonly: 1,
						with_webitems: 1,
						real_hosts: 1,
						dialogue_class: 'modal-popup-generic',
						excludeids: ids
					});
				});
			}
		});
	}

	removeSingleItem(obj) {
		const table_row = obj.closest('.single-item-table-row');

		table_row.remove();

		this.recalculateSortOrder();

		this.initSingleItemSortable();
		this.onGraphConfigChange();
	}

	recalculateSortOrder() {
		const dataset_number = widget_svggraph_form.getDataSetNumber();
		const rows = jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row');

		rows.each(function (i) {
			const $obj = jQuery(this);

			$obj.data('number', i + 1);

			jQuery.colorpicker('destroy', jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input', $obj));

			jQuery('.table-col-name a', $obj).attr('id', `items_${dataset_number}_${i + 1}_name`);
			jQuery('.table-col-action input', $obj).attr('id', `items_${dataset_number}_${i + 1}_input`);
			jQuery('.table-col-no span', $obj).text((i + 1) + ':');
		});

		rows.each(function (i) {
			const $obj = jQuery(this);

			jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input', $obj)
				.attr('id', `items_${dataset_number}_${i + 1}_color`)
				.colorpicker({appendTo: '.overlay-dialogue-body'});
		});

		widget_svggraph_form.rewriteNameLinks();
	}

	clone() {
		let dataset_elem = this.dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');

		if (!dataset_elem) {
			return;
		}

		const dataset_number = this.getDataSetNumber();
		const dataset_type = dataset_elem.dataset.type;
		const inputs = dataset_elem.querySelectorAll('input[name^=ds]');

		this._addDataset(dataset_type, true);

		const cloned_dataset = this.dataset_wrapper.querySelector(
			'.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]'
		);

		const cloned_number = cloned_dataset.getAttribute('data-set');

		if (dataset_type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>) {
			const list = {
				object: 'itemid',
				values: []
			};

			[...dataset_elem.querySelectorAll('.single-item-table-row')].map((elem) => {
				const itemid = elem.querySelector(`[name^='ds[${dataset_number}][itemids]`).value;
				const name = elem.querySelector('.table-col-name a').textContent;

				list.values.push({
					itemid: itemid,
					name: name
				});
			});

			window.addPopupValues(list);
		}
		else {
			const host_pattern_data = jQuery(dataset_elem.querySelector('.js-hosts-multiselect'))
				.multiSelect('getData');

			const items_pattern_data = jQuery(dataset_elem.querySelector('.js-items-multiselect'))
				.multiSelect('getData');

			jQuery(cloned_dataset.querySelector('.js-hosts-multiselect')).multiSelect('addData', host_pattern_data);
			jQuery(cloned_dataset.querySelector('.js-items-multiselect')).multiSelect('addData', items_pattern_data);
		}

		[...inputs].map((elem) => {
			const name = elem.name;
			const type = elem.type;
			const value = elem.value;

			const cloned_name = name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${cloned_number}$2`);

			if (type === 'text') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = value;

				if (elem.classList.contains('<?= CRangeControl::ZBX_STYLE_CLASS ?>')) {
					// Fire change event to redraw range input.
					cloned_dataset.querySelector(`[name="${cloned_name}"]`).dispatchEvent(new Event('change'));
				}
			}
			else if (type === 'checkbox' || type === 'radio') {
				if (elem.checked) {
					// Click to fire events.
					cloned_dataset.querySelector(`[name="${cloned_name}"][value="${value}"]`)
						.dispatchEvent(new Event('click'));
				}
			}
			else if (cloned_dataset.querySelector(`z-select[name="${cloned_name}"]`)) {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = value;
			}
		});

		this.recalculateSortOrder();
		this.updateVariableOrder(jQuery(this.dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');

		this.rewriteNameLinks();
		this.initDataSetSortable();
		this.initSingleItemSortable();
		this.onGraphConfigChange();
	}

	_updatedForm() {
		const axes_used = {<?= GRAPH_YAXIS_SIDE_LEFT ?>: 0, <?= GRAPH_YAXIS_SIDE_RIGHT ?>: 0};

		for (const element of this.form.querySelectorAll('[type=radio], [type=hidden]')) {
			if (element.name.match(/ds\[\d+]\[axisy]/) && element.checked) {
				axes_used[element.value]++;
			}
		}

		for (const element of this.form.querySelectorAll('[type=hidden]')) {
			if (element.name.match(/or\[\d+]\[axisy]/)) {
				axes_used[element.value]++;
			}
		}

		const row_num = this.getDataSetNumber();
		const draw_type = document.querySelector(`#ds_${row_num}_type`);

		if (draw_type !== null) {
			const is_stacked = document.getElementById(`ds_${row_num}_stacked`).checked;

			// Data set tab.
			const aggregate_function_select = document.getElementById(`ds_${row_num}_aggregate_function`);
			const approximation_select = document.getElementById(`ds_${row_num}_approximation`);

			let stacked_enabled = true;
			let width_enabled = true;
			let pointsize_enabled = true;
			let fill_enabled = true;
			let missingdata_enabled = true;
			let aggregate_none_enabled = true;
			let approximation_all_enabled = true;

			switch (draw_type.querySelector(':checked').value) {
				case '<?= SVG_GRAPH_TYPE_LINE ?>':
					pointsize_enabled = false;
					if (is_stacked) {
						approximation_all_enabled = false;
					}
					break;

				case '<?= SVG_GRAPH_TYPE_POINTS ?>':
					stacked_enabled = false;
					width_enabled = false;
					fill_enabled = false;
					missingdata_enabled = false;
					approximation_all_enabled = false;
					break;

				case '<?= SVG_GRAPH_TYPE_STAIRCASE ?>':
					pointsize_enabled = false;
					approximation_all_enabled = false;
					break;

				case '<?= SVG_GRAPH_TYPE_BAR ?>':
					width_enabled = false;
					pointsize_enabled = false;
					fill_enabled = false;
					missingdata_enabled = false;

					if (is_stacked) {
						aggregate_none_enabled = false;
					}

					approximation_all_enabled = false;
					break;
			}

			document.getElementById(`ds_${row_num}_stacked`).disabled = !stacked_enabled;
			jQuery(`#ds_${row_num}_width`).rangeControl(width_enabled ? 'enable' : 'disable');
			jQuery(`#ds_${row_num}_pointsize`).rangeControl(pointsize_enabled ? 'enable' : 'disable');
			jQuery(`#ds_${row_num}_fill`).rangeControl(fill_enabled ? 'enable' : 'disable');
			document.getElementById(`ds_${row_num}_missingdatafunc_0`).disabled = !missingdata_enabled;
			document.getElementById(`ds_${row_num}_missingdatafunc_1`).disabled = !missingdata_enabled;
			document.getElementById(`ds_${row_num}_missingdatafunc_2`).disabled = !missingdata_enabled;
			document.getElementById(`ds_${row_num}_missingdatafunc_3`).disabled = !missingdata_enabled;

			aggregate_function_select.getOptionByValue(<?= AGGREGATE_NONE ?>).disabled = !aggregate_none_enabled;
			if (!aggregate_none_enabled && aggregate_function_select.value == <?= AGGREGATE_NONE ?>) {
				aggregate_function_select.value = <?= AGGREGATE_AVG ?>;
			}

			approximation_select.getOptionByValue(<?= APPROXIMATION_ALL ?>).disabled = !approximation_all_enabled;
			if (!approximation_all_enabled && approximation_select.value == <?= APPROXIMATION_ALL ?>) {
				approximation_select.value = <?= APPROXIMATION_AVG ?>;
			}
		}

		// Displaying options tab.
		const percentile_left_checkbox = document.getElementById('percentile_left');
		percentile_left_checkbox.disabled = !axes_used[<?= GRAPH_YAXIS_SIDE_LEFT ?>];

		document.getElementById('percentile_left_value').disabled = !percentile_left_checkbox.checked
			|| !axes_used[<?= GRAPH_YAXIS_SIDE_LEFT ?>];

		const percentile_right_checkbox = document.getElementById('percentile_right');
		percentile_right_checkbox.disabled = !axes_used[<?= GRAPH_YAXIS_SIDE_RIGHT ?>];

		document.getElementById('percentile_right_value').disabled = !percentile_right_checkbox.checked
			|| !axes_used[<?= GRAPH_YAXIS_SIDE_RIGHT ?>];

		// Axes tab.
		const lefty_checkbox = document.getElementById('lefty');
		lefty_checkbox.disabled = !axes_used[<?= GRAPH_YAXIS_SIDE_LEFT ?>];

		const lefty_on = !lefty_checkbox.disabled && lefty_checkbox.checked;

		if (lefty_checkbox.disabled) {
			lefty_checkbox.checked = true;
		}

		for (const element of document.querySelectorAll('#lefty_min, #lefty_max, #lefty_units')) {
			element.disabled = !lefty_on;
		}

		document.getElementById('lefty_static_units').disabled = !lefty_on
			|| document.getElementById('lefty_units').value != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>;

		const righty_checkbox = document.getElementById('righty');
		righty_checkbox.disabled = !axes_used[<?= GRAPH_YAXIS_SIDE_RIGHT ?>];

		const righty_on = !righty_checkbox.disabled && righty_checkbox.checked;

		if (righty_checkbox.disabled) {
			righty_checkbox.checked = true;
		}

		for (const element of document.querySelectorAll('#righty_min, #righty_max, #righty_units')) {
			element.disabled = !righty_on;
		}

		document.getElementById('righty_static_units').disabled = !righty_on
			|| document.getElementById('righty_units').value != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>;
	}

	_updatePreview() {
		// Update graph preview.
		const $preview = jQuery('#svg-graph-preview');
		const $preview_container = $preview.parent();
		const preview_data = $preview_container.data();
		const $form = jQuery(this.form);
		const url = new Curl('zabbix.php');
		const data = {
			uniqueid: 0,
			preview: 1,
			content_width: Math.floor($preview.width()),
			content_height: Math.floor($preview.height()) - 10
		};

		url.setArgument('action', 'widget.svggraph.view');

		const form_fields = $form.serializeJSON();

		if ('ds' in form_fields) {
			for (const i in form_fields.ds) {
				form_fields.ds[i] = jQuery.extend({'hosts': [], 'items': []}, form_fields.ds[i]);
			}
		}
		if ('or' in form_fields) {
			for (const i in form_fields.or) {
				form_fields.or[i] = jQuery.extend({'hosts': [], 'items': []}, form_fields.or[i]);
			}
		}
		data.fields = JSON.stringify(form_fields);

		if (preview_data.xhr) {
			preview_data.xhr.abort();
		}

		if (preview_data.timeoutid) {
			clearTimeout(preview_data.timeoutid);
		}

		preview_data.timeoutid = setTimeout(function() {
			$preview_container.addClass('is-loading');
		}, 1000);

		preview_data.xhr = jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(data),
			dataType: 'json',
			success: function(r) {
				if (preview_data.timeoutid) {
					clearTimeout(preview_data.timeoutid);
				}
				$preview_container.removeClass('is-loading');

				$form.prev('.msg-bad').remove();

				if ('error' in r) {
					const message_box = makeMessageBox('bad', r.error.messages, r.error.title);
					message_box.insertBefore($form);
				}

				if (typeof r.body !== 'undefined') {
					$preview.html(jQuery(r.body)).attr('unselectable', 'on').css('user-select', 'none');
				}
			}
		});

		$preview_container.data(preview_data);
	}

	changeDataSetAggregateFunction(obj) {
		const row_num = this.getDataSetNumber();
		const no_aggregation = (jQuery(obj).val() == <?= AGGREGATE_NONE ?>);

		jQuery(`#ds_${row_num}_aggregate_interval`).prop('disabled', no_aggregation);
		jQuery(`#ds_${row_num}_aggregate_grouping0`).prop('disabled', no_aggregation);
		jQuery(`#ds_${row_num}_aggregate_grouping1`).prop('disabled', no_aggregation);
	}
};

window.addPopupValues = (list) => {
	if (!isset('object', list) || list.object != 'itemid') {
		return false;
	}

	const ds_number = widget_svggraph_form.getDataSetNumber();
	const tmpl = new Template(jQuery('#dataset-item-row-tmpl').html());

	for (let i = 0; i < list.values.length; i++) {
		const size = jQuery(`.single-item-table[data-set=${ds_number}] .single-item-table-row`).length + 1;
		const value = list.values[i];
		const name = value.name;
		const itemid = value.itemid;

		if (jQuery(`.single-item-table[data-set=${ds_number}] .single-item-table-row input[value=${itemid}]`).length) {
			continue;
		}

		jQuery(`.single-item-table[data-set=${ds_number}] tbody`).append(tmpl.evaluate({
			dsNum: ds_number,
			rowNum: size,
			name: name,
			itemid: itemid
		}));

		jQuery(`#items_${ds_number}_${size}_color`).val(colorPalette.getNextColor());
		jQuery(`#items_${ds_number}_${size}_color`).colorpicker();
	}

	widget_svggraph_form.rewriteNameLinks();
	widget_svggraph_form.initSingleItemSortable();
	widget_svggraph_form.onGraphConfigChange();
}
