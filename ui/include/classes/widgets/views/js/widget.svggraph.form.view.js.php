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
		this._dataset_index = 0;
	}

	init({form_id, form_tabs_id, color_palette}) {
		colorPalette.setThemeColors(color_palette);

		this._$overlay_body = jQuery('.overlay-dialogue-body');
		this._form = document.getElementById(form_id);
		this._form_id = form_id;

		this._dataset_wrapper = document.getElementById('data_sets');

		this._$overlay_body.on('scroll', () => {
			const $preview = jQuery('.<?= ZBX_STYLE_SVG_GRAPH_PREVIEW ?>', this._$overlay_body);

			if (!$preview.length) {
				this._$overlay_body.off('scroll');
				return;
			}

			if ($preview.offset().top < this._$overlay_body.offset().top && this._$overlay_body.height() > 400) {
				jQuery('#svg-graph-preview').css('top',
					this._$overlay_body.offset().top - $preview.offset().top
				);
				jQuery('.graph-widget-config-tabs .ui-tabs-nav').css('top', $preview.height());
			}
			else {
				jQuery('#svg-graph-preview').css('top', 0);
				jQuery('.graph-widget-config-tabs .ui-tabs-nav').css('top', 0);
			}
		});

		jQuery(`#${form_tabs_id}`)
			.on('tabsactivate', () => jQuery.colorpicker('hide'))
			.on('change', 'input, z-select, .multiselect', (e) => this.onGraphConfigChange(e));

		this._datasetTabInit();
		this._timePeriodTabInit();
		this._legendTabInit();
		this._problemsTabInit();

		this.onGraphConfigChange();
	}

	onGraphConfigChange() {
		this._updateForm();
		this._updatePreview();
	}

	updateVariableOrder(obj, row_selector, var_prefix) {
		for (const k of [10000, 0]) {
			jQuery(row_selector, obj).each(function(i) {
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

	_datasetTabInit() {
		this._dataset_index = this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		// Initialize vertical accordion.
		jQuery(this._dataset_wrapper)
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

					widget_svggraph_form._initSingleItemSortable(dataset);
				}
			})
			.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>'});

		// Initialize rangeControl UI elements.
		jQuery('.<?= CRangeControl::ZBX_STYLE_CLASS ?>', jQuery(this._dataset_wrapper)).rangeControl();

		// Initialize pattern fields.
		jQuery('.multiselect', jQuery(this._dataset_wrapper)).each(function() {
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
		}

		this._dataset_wrapper.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this._selectItems();
			}

			if (e.target.classList.contains('element-table-remove')) {
				this._removeSingleItem(e.target);
			}

			if (e.target.classList.contains('btn-remove')) {
				this._removeDataSet(e.target);
			}
		});

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => {
				this._addDataset(<?= CWidgetHelper::DATASET_TYPE_PATTERN_ITEM ?>);
			});

		document
			.getElementById('dataset-menu')
			.addEventListener('click', this._addDatasetMenu);

		window.addPopupValues = (list) => {
			if (!isset('object', list) || list.object != 'itemid') {
				return false;
			}

			for (let i = 0; i < list.values.length; i++) {
				widget_svggraph_form._addSingleItem(list.values[i].itemid, list.values[i].name);
			}

			widget_svggraph_form._updateSingleItemsLinks();
			widget_svggraph_form._initSingleItemSortable(widget_svggraph_form._getOpenedDataset());

			widget_svggraph_form._updatePreview();
		}

		this._updateSingleItemsLinks();
		this._initDataSetSortable();

		this._initSingleItemSortable(widget_svggraph_form._getOpenedDataset());
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

	_addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(<?= CWidgetHelper::DATASET_TYPE_PATTERN_ITEM ?>);
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(<?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>);
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						disabled: widget_svggraph_form._getOpenedDataset() === null,
						clickCallback: () => {
							widget_svggraph_form._cloneDataset();
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

	_addDataset(type) {
		jQuery(this._dataset_wrapper).zbx_vertical_accordion('collapseAll');

		const template = type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>
			? new Template(jQuery('#dataset-single-item-tmpl').html())
			: new Template(jQuery('#dataset-pattern-item-tmpl').html());

		const used_colors = [];

		for (const color of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		this._dataset_wrapper.insertAdjacentHTML('beforeend', template.evaluate({
			rowNum: this._dataset_index++,
			color: type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>
				? ''
				: colorPalette.getNextColor(used_colors)
		}));

		const dataset = this._getOpenedDataset();

		for (const colorpicker of dataset.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}

		for (const multiselect of dataset.querySelectorAll('.multiselect')) {
			jQuery(multiselect).multiSelect(jQuery(multiselect).data('params'));
		}

		for (const range_control of dataset.querySelectorAll('.<?= CRangeControl::ZBX_STYLE_CLASS ?>')) {
			jQuery(range_control).rangeControl();
		}

		this._$overlay_body.scrollTop(Math.max(this._$overlay_body.scrollTop(),
			this._form.scrollHeight - this._$overlay_body.height()
		));

		this._initDataSetSortable();
		this._updateForm();
	}

	_cloneDataset() {
		const dataset = this._getOpenedDataset();

		this._addDataset(dataset.dataset.type);

		const cloned_dataset = this._getOpenedDataset();

		if (dataset.dataset.type == <?= CWidgetHelper::DATASET_TYPE_SINGLE_ITEM ?>) {
			for (const row of dataset.querySelectorAll('.single-item-table-row')) {
				this._addSingleItem(
					row.querySelector(`[name^='ds[${dataset.getAttribute('data-set')}][itemids]`).value,
					row.querySelector('.table-col-name a').textContent
				);
			}

			this._updateSingleItemsLinks();
			this._initSingleItemSortable(cloned_dataset);
		}
		else {
			jQuery('.js-hosts-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-hosts-multiselect', dataset).multiSelect('getData')
			);
			jQuery('.js-items-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-items-multiselect', dataset).multiSelect('getData')
			);
		}

		for (const input of dataset.querySelectorAll('[name^=ds]')) {
			const cloned_name = input.name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/,
				`$1${cloned_dataset.getAttribute('data-set')}$2`
			);

			if (input.tagName.toLowerCase() === 'z-select') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = input.value;
			}
			else if (input.type === 'text') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = input.value;

				if (input.classList.contains('<?= CRangeControl::ZBX_STYLE_CLASS ?>')) {
					// Fire change event to redraw range input.
					cloned_dataset.querySelector(`[name="${cloned_name}"]`).dispatchEvent(new Event('change'));
				}
			}
			else if (input.type === 'checkbox' || input.type === 'radio') {
				// Click to fire events.
				cloned_dataset.querySelector(`[name="${cloned_name}"][value="${input.value}"]`).checked = input.checked;
			}
		}

		this._updatePreview();
	}

	_removeDataSet(obj) {
		obj
			.closest('.list-accordion-item')
			.remove();

		this.updateVariableOrder(jQuery(this._dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');

		const dataset = this._getOpenedDataset();

		if (dataset !== null) {
			this._updateSingleItemsOrder(dataset);
			this._initSingleItemSortable(dataset);
		}

		this._initDataSetSortable();
		this._updateSingleItemsLinks();
		this.onGraphConfigChange();
	}

	_getOpenedDataset() {
		return this._dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
	}

	_initDataSetSortable() {
		const datasets_count = this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		for (const drag_icon of this._dataset_wrapper.querySelectorAll('.js-main-drag-icon')) {
			drag_icon.classList.toggle('disabled', datasets_count < 2);
		}

		if (this._sortable_data_set !== undefined) {
			this._sortable_data_set.deactivate();
		}

		this._sortable_data_set = new CSortable(
			document.querySelector('#data_set .<?= ZBX_STYLE_LIST_VERTICAL_ACCORDION ?>'),
			{is_vertical: true}
		);

		this._sortable_data_set.on(SORTABLE_EVENT_DRAG_END, () => {
			widget_svggraph_form.updateVariableOrder(this._dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>',
				'ds'
			);
			widget_svggraph_form._updatePreview();
		});
	}

	_selectItems() {
		PopUp('popup.generic', {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this._form_id,
			numeric: 1,
			writeonly: 1,
			multiselect: 1,
			with_webitems: 1,
			real_hosts: 1
		});
	}

	_addSingleItem(itemid, name) {
		const dataset = widget_svggraph_form._getOpenedDataset();
		const items_table = dataset.querySelector('.single-item-table');

		if (items_table.querySelector(`input[value="${itemid}"]`) !== null) {
			return;
		}

		const dataset_index = dataset.getAttribute('data-set');
		const template = new Template(jQuery('#dataset-item-row-tmpl').html());
		const item_next_index = items_table.querySelectorAll('.single-item-table-row').length + 1;

		items_table.querySelector('tbody').insertAdjacentHTML('beforeend', template.evaluate({
			dsNum: dataset_index,
			rowNum: item_next_index,
			name: name,
			itemid: itemid
		}));

		const used_colors = [];

		for (const color of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		jQuery(`#items_${dataset_index}_${item_next_index}_color`)
			.val(colorPalette.getNextColor(used_colors))
			.colorpicker();
	}

	_removeSingleItem(element) {
		element.closest('.single-item-table-row').remove();

		const dataset = this._getOpenedDataset();

		this._updateSingleItemsOrder(dataset);
		this._updateSingleItemsLinks();
		this._initSingleItemSortable(dataset);
		this._updatePreview();
	}

	_initSingleItemSortable(dataset) {
		const item_rows = dataset.querySelectorAll('.single-item-table-row');

		if (item_rows.length < 1) {
			return;
		}

		for (const row of item_rows) {
			row.querySelector('.<?= ZBX_STYLE_DRAG_ICON ?>').classList.toggle('disabled', item_rows.length < 2);
		}

		jQuery(`.single-item-table`, dataset).sortable({
			disabled: item_rows.length < 2,
			items: 'tbody .single-item-table-row',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: () => {
				this._updateSingleItemsOrder(dataset);
				this._updateSingleItemsLinks();
			},
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

	_updateSingleItemsLinks() {
		for (const dataset of this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			const dataset_index = dataset.getAttribute('data-set');
			const size = dataset.querySelectorAll('.single-item-table-row').length + 1;

			for (let i = 0; i < size; i++) {
				jQuery(`#items_${dataset_index}_${i}_name`).off('click').on('click', () => {
					let ids = [];
					for (let i = 0; i < size; i++) {
						ids.push(jQuery(`#items_${dataset_index}_${i}_input`).val());
					}

					PopUp('popup.generic', {
						srctbl: 'items',
						srcfld1: 'itemid',
						srcfld2: 'name',
						dstfrm: widget_svggraph_form._form_id,
						dstfld1: `items_${dataset_index}_${i}_input`,
						dstfld2: `items_${dataset_index}_${i}_name`,
						numeric: 1,
						writeonly: 1,
						with_webitems: 1,
						real_hosts: 1,
						dialogue_class: 'modal-popup-generic',
						excludeids: ids
					});
				});
			}
		}
	}

	_updateSingleItemsOrder(dataset) {
		jQuery.colorpicker('destroy', jQuery('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input', dataset));

		const dataset_index = dataset.getAttribute('data-set');

		for (const row of dataset.querySelectorAll('.single-item-table-row')) {
			const prefix = `items_${dataset_index}_${row.rowIndex}`;

			row.querySelector('.table-col-no span').textContent = `${row.rowIndex} :`;
			row.querySelector('.table-col-name a').id = `${prefix}_name`;
			row.querySelector('.table-col-action input').id = `${prefix}_input`;

			const colorpicker = row.querySelector('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input');

			colorpicker.id = `${prefix}_color`;
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}
	}

	_updateForm() {
		const axes_used = {<?= GRAPH_YAXIS_SIDE_LEFT ?>: 0, <?= GRAPH_YAXIS_SIDE_RIGHT ?>: 0};

		for (const element of this._form.querySelectorAll('[type=radio], [type=hidden]')) {
			if (element.name.match(/ds\[\d+]\[axisy]/) && element.checked) {
				axes_used[element.value]++;
			}
		}

		for (const element of this._form.querySelectorAll('[type=hidden]')) {
			if (element.name.match(/or\[\d+]\[axisy]/)) {
				axes_used[element.value]++;
			}
		}

		const dataset = this._getOpenedDataset();

		if (dataset !== null) {
			const dataset_index = dataset.getAttribute('data-set');

			const draw_type = dataset.querySelector(`[name="ds[${dataset_index}][type]"]:checked`);
			const is_stacked = dataset.querySelector(`[type=checkbox][name="ds[${dataset_index}][stacked]"]`).checked;

			// Data set tab.
			const aggregate_function_select = dataset.querySelector(`[name="ds[${dataset_index}][aggregate_function]"]`);
			const approximation_select = dataset.querySelector(`[name="ds[${dataset_index}][approximation]"]`);

			let stacked_enabled = true;
			let width_enabled = true;
			let pointsize_enabled = true;
			let fill_enabled = true;
			let missingdata_enabled = true;
			let aggregate_none_enabled = true;
			let approximation_all_enabled = true;

			switch (draw_type.value) {
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

			dataset.querySelector(`[type=checkbox][name="ds[${dataset_index}][stacked]"]`).disabled = !stacked_enabled;
			jQuery(`[name="ds[${dataset_index}][width]"]`, dataset).rangeControl(width_enabled ? 'enable' : 'disable');
			jQuery(`[name="ds[${dataset_index}][pointsize]"]`, dataset).rangeControl(
				pointsize_enabled ? 'enable' : 'disable'
			);
			jQuery(`[name="ds[${dataset_index}][fill]"]`, dataset).rangeControl(fill_enabled ? 'enable' : 'disable');

			for (const element of dataset.querySelectorAll(`[name="ds[${dataset_index}][missingdatafunc]"]`)) {
				element.disabled = !missingdata_enabled;
			}

			aggregate_function_select.getOptionByValue(<?= AGGREGATE_NONE ?>).disabled = !aggregate_none_enabled;
			if (!aggregate_none_enabled && aggregate_function_select.value == <?= AGGREGATE_NONE ?>) {
				aggregate_function_select.value = <?= AGGREGATE_AVG ?>;
			}

			const aggregation_enabled = aggregate_function_select.value != <?= AGGREGATE_NONE ?>;

			dataset.querySelector(`[name="ds[${dataset_index}][aggregate_interval]"]`).disabled = !aggregation_enabled;

			for (const element of dataset.querySelectorAll(`[name="ds[${dataset_index}][aggregate_grouping]"]`)) {
				element.disabled = !aggregation_enabled;
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

		// Trigger event to update tab indicators.
		document.getElementById('tabs').dispatchEvent(new Event(TAB_INDICATOR_UPDATE_EVENT));
	}

	_updatePreview() {
		// Update graph preview.
		const $preview = jQuery('#svg-graph-preview');
		const $preview_container = $preview.parent();
		const preview_data = $preview_container.data();
		const $form = jQuery(this._form);
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
};
