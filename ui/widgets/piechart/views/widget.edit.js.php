<?php declare(strict_types = 0);
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


use Widgets\PieChart\Includes\{
	CWidgetFieldDataSet,
	WidgetForm
};

?>

window.widget_pie_chart_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {HTMLElement}
	 */
	#dataset_wrapper;

	/**
	 * @type {String}
	 */
	#templateid;

	init({form_tabs_id, color_palette, templateid}) {
		colorPalette.setThemeColors(color_palette);

		this.#form = document.getElementById('widget-dialogue-form');
		this.#dataset_wrapper = document.getElementById('data_sets');

		this.#templateid = templateid;

		jQuery('.overlay-dialogue-body').off('scroll');

		for (const colorpicker of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: !['ds', 'merge_color'].includes(colorpicker.name)
			});
		}

		jQuery(`#${form_tabs_id}`)
			.on('tabsactivate', () => jQuery.colorpicker('hide'))
			.on('change', 'input, z-select, .multiselect', () => this.#updateForm());

		this.#datasetTabInit();
		this.#displayingOptionsTabInit();
		this.#updateForm();
	}

	#datasetTabInit() {
		this.#updateDatasetsLabel();

		// Initialize vertical accordion.
		jQuery(this.#dataset_wrapper)
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

				if (dataset.dataset.type == '<?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'block';
					}
				}
			})
			.on('expand', (event, data) => {
				jQuery(window).trigger('resize');
				const dataset = data.section[0];

				if (dataset.dataset.type == '<?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'none';
					}

					this.#initSingleItemSortable(dataset);
				}
			})
			.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>'});

		// Initialize pattern fields.
		jQuery('.multiselect', jQuery(this.#dataset_wrapper)).multiSelect();

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

		this.#dataset_wrapper.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add')) {
				this.#selectItems();
			}

			if (e.target.classList.contains('element-table-remove')) {
				this.#removeSingleItem(e.target);
			}

			if (e.target.classList.contains('js-remove')) {
				this.#removeDataSet(e.target);
			}
		});

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => {
				this.#addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
			});

		document
			.getElementById('dataset-menu')
			.addEventListener('click', (e) => this.#addDatasetMenu(e));

		window.addPopupValues = (list) => {
			if (!isset('object', list) || list.object !== 'itemid') {
				return false;
			}

			for (let i = 0; i < list.values.length; i++) {
				this.#addSingleItem(list.values[i].itemid, list.values[i].name, list.values[i].type);
			}

			this.#updateSingleItemsLinks();
			this.#initSingleItemSortable(this.#getOpenedDataset());
		}

		this.#updateSingleItemsLinks();
		this.#initDataSetSortable();

		this.#initSingleItemSortable(this.#getOpenedDataset());
	}

	#displayingOptionsTabInit() {
		const used_colors = [];

		for (const color of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		const merge_color_set = document.getElementById('merge_color').value !== '';

		if (!merge_color_set) {
			const merge_color = colorPalette.getNextColor(used_colors);
			$.colorpicker('set_color_by_id', 'merge_color', merge_color);
		}
	}

	#addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: () => {
							this.#addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							this.#addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>);
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						disabled: this.#getOpenedDataset() === null,
						clickCallback: () => {
							this.#cloneDataset();
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

	#addDataset(type) {
		jQuery(this.#dataset_wrapper).zbx_vertical_accordion('collapseAll');

		const template = type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>
			? new Template(document.querySelector('#dataset-single-item-tmpl').innerHTML)
			: new Template(document.querySelector('#dataset-pattern-item-tmpl').innerHTML);

		const used_colors = [];

		for (const color of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		this.#dataset_wrapper.insertAdjacentHTML('beforeend', template.evaluate({
			rowNum: this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length,
			color: type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>
				? ''
				: colorPalette.getNextColor(used_colors)
		}));

		const dataset = this.#getOpenedDataset();

		for (const colorpicker of dataset.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}

		for (const multiselect of dataset.querySelectorAll('.multiselect')) {
			jQuery(multiselect).multiSelect();
		}

		const $overlay_body = jQuery('.overlay-dialogue-body')

		$overlay_body.scrollTop(Math.max($overlay_body.scrollTop(), this.#form.scrollHeight - $overlay_body.height()));

		this.#initDataSetSortable();
		this.#updateForm();
	}

	#cloneDataset() {
		const dataset = this.#getOpenedDataset();

		this.#addDataset(dataset.dataset.type);

		const cloned_dataset = this.#getOpenedDataset();

		if (dataset.dataset.type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>) {
			for (const row of dataset.querySelectorAll('.single-item-table-row')) {
				this.#addSingleItem(
					row.querySelector(`[name^='ds[${dataset.getAttribute('data-set')}][itemids]`).value,
					row.querySelector('.table-col-name a').textContent,
					row.querySelector(`.table-col-type z-select`).value
				);
			}

			this.#updateSingleItemsLinks();
			this.#initSingleItemSortable(cloned_dataset);
		}
		else {
			if (this.#templateid === null) {
				jQuery('.js-hosts-multiselect', cloned_dataset).multiSelect('addData',
					jQuery('.js-hosts-multiselect', dataset).multiSelect('getData')
				);
			}

			jQuery('.js-items-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-items-multiselect', dataset).multiSelect('getData')
			);
		}

		for (const input of dataset.querySelectorAll('[name^=ds]')) {
			const is_template_input = input.closest('.single-item-table-row') !== null;

			if (is_template_input) {
				continue;
			}

			const cloned_name = input.name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/,
				`$1${cloned_dataset.getAttribute('data-set')}$2`
			);

			if (input.tagName.toLowerCase() === 'z-select' || input.type === 'text') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = input.value;
			}
		}

		this.#updateDatasetLabel(cloned_dataset);
	}

	#removeDataSet(obj) {
		obj
			.closest('.list-accordion-item')
			.remove();

		this.#updateVariableOrder(jQuery(this.#dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this.#updateDatasetsLabel();

		const dataset = this.#getOpenedDataset();

		if (dataset !== null) {
			this.#updateSingleItemsOrder(dataset);
			this.#initSingleItemSortable(dataset);
		}

		this.#initDataSetSortable();
		this.#updateSingleItemsLinks();
		this.#updateForm();
	}

	#getOpenedDataset() {
		return this.#dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
	}

	#updateDatasetsLabel() {
		for (const dataset of this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			this.#updateDatasetLabel(dataset);
		}
	}

	#updateDatasetLabel(dataset) {
		const placeholder_text = <?= json_encode(_('Data set')) ?> + ` #${parseInt(dataset.dataset.set) + 1}`;

		const data_set_label = dataset.querySelector('.js-dataset-label');
		const data_set_label_input = dataset.querySelector(`[name="ds[${dataset.dataset.set}][data_set_label]"]`);

		data_set_label.textContent = data_set_label_input.value !== '' ? data_set_label_input.value : placeholder_text;
		data_set_label_input.placeholder = placeholder_text;
	}

	#initDataSetSortable() {
		const datasets_count = this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		for (const drag_icon of this.#dataset_wrapper.querySelectorAll('.js-main-drag-icon')) {
			drag_icon.classList.toggle('disabled', datasets_count < 2);
		}

		if (this._sortable_data_set === undefined) {
			this._sortable_data_set = new CSortable(
				document.querySelector('#data_set .<?= ZBX_STYLE_LIST_VERTICAL_ACCORDION ?>'),
				{is_vertical: true}
			);

			this._sortable_data_set.on(SORTABLE_EVENT_DRAG_END, () => {
				this.#updateVariableOrder(this.#dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
				this.#updateDatasetsLabel();
			});
		}

		this._sortable_data_set.enableSorting(datasets_count > 1);
	}

	#selectItems() {
		if (this.#templateid === null) {
			PopUp('popup.generic', {
				srctbl: 'items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this.#form.id,
				numeric: 1,
				writeonly: 1,
				multiselect: 1,
				with_webitems: 1,
				real_hosts: 1
			}, {dialogue_class: 'modal-popup-generic'});
		}
		else {
			PopUp('popup.generic', {
				srctbl: 'items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this.#form.id,
				numeric: 1,
				writeonly: 1,
				multiselect: 1,
				with_webitems: 1,
				hostid: this.#templateid,
				hide_host_filter: 1
			}, {dialogue_class: 'modal-popup-generic'});
		}
	}

	#addSingleItem(itemid, name, type) {
		const dataset = this.#getOpenedDataset();
		const items_table = dataset.querySelector('.single-item-table');

		if (items_table.querySelector(`input[value="${itemid}"]`) !== null) {
			return;
		}

		const dataset_index = dataset.getAttribute('data-set');
		const template = new Template(document.querySelector('#dataset-item-row-tmpl').innerHTML);
		const item_next_index = items_table.querySelectorAll('.single-item-table-row').length + 1;

		items_table.querySelector('tbody').insertAdjacentHTML('beforeend', template.evaluate({
			dsNum: dataset_index,
			rowNum: item_next_index,
			name: name,
			itemid: itemid,
			type: type
		}));

		const used_colors = [];

		for (const color of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		jQuery(`#items_${dataset_index}_${item_next_index}_color`)
			.val(colorPalette.getNextColor(used_colors))
			.colorpicker();
	}

	#removeSingleItem(element) {
		element.closest('.single-item-table-row').remove();

		const dataset = this.#getOpenedDataset();

		this.#updateSingleItemsOrder(dataset);
		this.#updateSingleItemsLinks();
		this.#initSingleItemSortable(dataset);
	}

	#initSingleItemSortable(dataset) {
		const item_rows = dataset.querySelectorAll('.single-item-table-row');

		if (item_rows.length < 1) {
			return;
		}

		for (const row of item_rows) {
			row.querySelector('.<?= ZBX_STYLE_DRAG_ICON ?>').classList.toggle('disabled', item_rows.length < 2);
		}

		jQuery(`.single-item-table`, dataset).sortable({
			disabled: item_rows.length < 2,
			items: '.single-item-table-row',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: '.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: () => {
				this.#updateSingleItemsOrder(dataset);
				this.#updateSingleItemsLinks();
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

	#updateSingleItemsLinks() {
		for (const dataset of this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			const dataset_index = dataset.getAttribute('data-set');
			const size = dataset.querySelectorAll('.single-item-table-row').length + 1;

			for (let i = 0; i < size; i++) {
				jQuery(`#items_${dataset_index}_${i}_name`).off('click').on('click', () => {
					let ids = [];
					for (let i = 0; i < size; i++) {
						ids.push(jQuery(`#items_${dataset_index}_${i}_input`).val());
					}

					if (this.#templateid === null) {
						PopUp('popup.generic', {
							srctbl: 'items',
							srcfld1: 'itemid',
							srcfld2: 'name',
							dstfrm: this.#form.id,
							dstfld1: `items_${dataset_index}_${i}_input`,
							dstfld2: `items_${dataset_index}_${i}_name`,
							numeric: 1,
							writeonly: 1,
							with_webitems: 1,
							real_hosts: 1,
							excludeids: ids
						}, {dialogue_class: 'modal-popup-generic'});
					}
					else {
						PopUp('popup.generic', {
							srctbl: 'items',
							srcfld1: 'itemid',
							srcfld2: 'name',
							dstfrm: this.#form.id,
							dstfld1: `items_${dataset_index}_${i}_input`,
							dstfld2: `items_${dataset_index}_${i}_name`,
							numeric: 1,
							writeonly: 1,
							with_webitems: 1,
							hostid: this.#templateid,
							hide_host_filter: 1,
							excludeids: ids
						}, {dialogue_class: 'modal-popup-generic'});
					}
				});
			}
		}
	}

	#updateSingleItemsOrder(dataset) {
		jQuery.colorpicker('destroy', jQuery('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input', dataset));

		const dataset_index = dataset.getAttribute('data-set');

		for (const row of dataset.querySelectorAll('.single-item-table-row')) {
			const prefix = `items_${dataset_index}_${row.rowIndex}`;

			row.querySelector('.table-col-no span').textContent = `${row.rowIndex}:`;
			row.querySelector('.table-col-name a').id = `${prefix}_name`;
			row.querySelector('.table-col-type z-select').id = `${prefix}_type`
			row.querySelector('.table-col-action input').id = `${prefix}_input`;

			const colorpicker = row.querySelector('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input');

			colorpicker.id = `${prefix}_color`;
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}
	}

	#updateVariableOrder(obj, row_selector, var_prefix) {
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

	#updateForm() {
		// Data set tab changes.
		const dataset = this.#getOpenedDataset();

		if (dataset !== null) {
			this.#updateDatasetLabel(dataset);
		}

		const datasets = this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>');
		const items_type = [];
		let is_total = false;

		for (let i = 0; i < datasets.length; i++) {
			const item_type_fields = document.querySelectorAll(`input[name="ds[${i}][type][]"]`);

			if (item_type_fields) {
				items_type.push(item_type_fields);
			}
		}

		if (items_type.length > 0) {
			for (let i = 0; i < datasets.length; i++) {
				for (let j = 0; j < items_type[i].length; j++) {
					if (items_type[i][j].value == <?= CWidgetFieldDataSet::ITEM_TYPE_TOTAL ?>) {
						is_total = true;
					}
				}
			}

			for (let k = 0; k < datasets.length; k++) {
				document.querySelector(`[name="ds[${k}][dataset_aggregation]"]`).disabled = is_total;
			}
		}

		// Displaying options tab changes.
		const is_doughnut = this.#form
			.querySelector('[name="draw_type"]:checked').value == <?= WidgetForm::DRAW_TYPE_DOUGHNUT ?>;
		const do_merge_sectors = document.getElementById('merge').checked;
		const is_total_value_visible = document.getElementById('total_show').checked;
		const is_value_size_custom = this.#form
			.querySelector('[name="value_size_type"]:checked').value == <?= WidgetForm::VALUE_SIZE_CUSTOM ?>;
		const is_units_visible = document.getElementById('units_show').checked;

		for (const element of this.#form.querySelectorAll('#width_label, #width_range, #show_total_fields')) {
			element.style.display = is_doughnut ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !is_doughnut;
			}
		}

		jQuery('#width').rangeControl(is_doughnut ? 'enable' : 'disable');

		document.getElementById('merge_percent').disabled = !do_merge_sectors;
		document.getElementById('merge_color').disabled = !do_merge_sectors;

		for (const field of this.#form.querySelectorAll('#value_size_type_0, #value_size_type_1,' +
			'#value_size_custom_input, #decimal_places, #units_show, #units, #value_bold, #value_color'
		)) {
			field.disabled = !is_total_value_visible;
		}

		const value_size_input = document.getElementById('value_size_custom_input');
		value_size_input.disabled = !is_value_size_custom || !is_total_value_visible;
		value_size_input.style.display = is_value_size_custom ? '' : 'none';
		value_size_input.nextSibling.nodeValue = is_value_size_custom ? ' %' : '';

		if (document.activeElement === document.getElementById('value_size_type_1')) {
			value_size_input.focus();
		}

		document.getElementById('units').disabled = !is_units_visible || !is_total_value_visible;

		// Legend tab changes.
		const is_legend_visible = document.getElementById('legend').checked;

		jQuery('#legend_lines').rangeControl(is_legend_visible ? 'enable' : 'disable');
		jQuery('#legend_columns').rangeControl(is_legend_visible ? 'enable' : 'disable');
		document.getElementById('legend_aggregation').disabled = !is_legend_visible;

		// Trigger event to update tab indicators.
		document.getElementById('tabs').dispatchEvent(new Event(TAB_INDICATOR_UPDATE_EVENT));
	}
};
