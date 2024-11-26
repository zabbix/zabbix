<?php declare(strict_types = 0);
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
	 * @type {Map<HTMLLIElement, CSortable>}
	 */
	#single_items_sortable = new Map();

	/**
	 * @type {String}
	 */
	#templateid;

	/**
	 * @type {number}
	 */
	#dataset_row_unique_id = 0;

	init({form_tabs_id, color_palette, templateid}) {
		colorPalette.setThemeColors(color_palette);

		this.#form = document.getElementById('widget-dialogue-form');
		this.#dataset_wrapper = document.getElementById('data_sets');

		this.#templateid = templateid;

		this.#dataset_row_unique_id =
			this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		jQuery('.overlay-dialogue-body').off('scroll');

		for (const colorpicker of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			$(colorpicker).colorpicker({
				appendTo: '.overlay-dialogue-body',
				use_default: colorpicker.name === 'value_color'
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

		const $data_sets = jQuery(this.#dataset_wrapper);

		$data_sets
			.on('focus', '.<?= CMultiSelect::ZBX_STYLE_CLASS ?> input.input', function(e) {
				const list_item = e.target.closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>');

				if (list_item.classList.contains('<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>')) {
					return;
				}

				$data_sets.zbx_vertical_accordion('expandNth',
					[...list_item.parentElement.children].indexOf(list_item)
				);
			})
			.on('click', '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', function(e) {
				if (!e.target.classList.contains('color-picker-preview')) {
					jQuery.colorpicker('hide');
				}

				const list_item = e.target.closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>');

				if (list_item.classList.contains('<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>')) {
					return;
				}

				if (e.target.classList.contains('js-click-expand')
						|| e.target.classList.contains('color-picker-preview')) {
					$data_sets.zbx_vertical_accordion('expandNth',
						[...list_item.parentElement.children].indexOf(list_item)
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
			if (e.target.classList.contains('js-add-item')) {
				this.#selectItems();
			}

			if (e.target.classList.contains('js-add-widget')) {
				this.#selectWidget();
			}

			if (e.target.matches('.single-item-table-row .table-col-name a')) {
				this.#editItem(e.target);
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
				this.#addSingleItem({
					itemid: list.values[i].itemid,
					name: list.values[i].name,
					type: list.values[i].type
				});
			}

			this.#initSingleItemSortable(this.#getOpenedDataset());
		}

		this.updateSingleItemsReferences();
		this.#initDataSetSortable();

		this.#initSingleItemSortable(this.#getOpenedDataset());
	}

	#displayingOptionsTabInit() {
		if (document.getElementById('merge_color').value === '') {
			$.colorpicker('set_color_by_id', 'merge_color', '<?= WidgetForm::MERGE_COLOR_DEFAULT ?>');
		}
	}

	#addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item patterns')) ?>,
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
				within: 'body'
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

		const fragment = document.createRange().createContextualFragment(template.evaluate({
			rowNum: this.#dataset_row_unique_id++,
			color: type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>
				? ''
				: colorPalette.getNextColor(used_colors)
		}));

		this.#dataset_wrapper.append(fragment);

		this.#updateVariableOrder(this.#dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this.#updateDatasetsLabel();

		const dataset = this.#getOpenedDataset();

		for (const colorpicker of dataset.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
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
				this.#addSingleItem({
					itemid: row.querySelector(`[name$='[itemids][]`).value,
					reference: row.querySelector(`[name$='[references][]`).value,
					name: row.querySelector('.table-col-name a').textContent,
					type: row.querySelector(`.table-col-type z-select`).value
				});
			}

			this.updateSingleItemsReferences();
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
		const dataset_remove = obj.closest('.list-accordion-item');

		dataset_remove.remove();

		if (this.#single_items_sortable.has(dataset_remove)) {
			this.#single_items_sortable.get(dataset_remove).enable(false);
			this.#single_items_sortable.delete(dataset_remove);
		}

		this.#updateVariableOrder(jQuery(this.#dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this.#updateDatasetsLabel();

		const dataset = this.#getOpenedDataset();

		if (dataset !== null) {
			this.#updateSingleItemsOrder(dataset);
			this.#initSingleItemSortable(dataset);
		}

		this.#initDataSetSortable();
		this.updateSingleItemsReferences();
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
		if (this._sortable_data_set === undefined) {
			this._sortable_data_set = new CSortable(document.querySelector('#data_sets'), {
				selector_handle: '.js-main-drag-icon, .js-dataset-label'
			});

			this._sortable_data_set.on(CSortable.EVENT_SORT, () => {
				this.#updateVariableOrder(this.#dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
				this.#updateDatasetsLabel();
			});
		}
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
				real_hosts: 1,
				resolve_macros: 1
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

	#editItem(target) {
		const dataset = this.#getOpenedDataset();
		const dataset_index = dataset.getAttribute('data-set');

		const row = target.closest('.single-item-table-row');
		const row_index = row.rowIndex;

		const itemid_input = row.querySelector('input[name$="[itemids][]"');

		if (itemid_input.value !== '0') {
			const excludeids = [];

			for (const input of dataset.querySelectorAll('.single-item-table-row input[name$="[itemids][]"]')) {
				if (input.value !== '0') {
					excludeids.push(input.value);
				}
			}

			if (this.#templateid === null) {
				PopUp('popup.generic', {
					srctbl: 'items',
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: widget_svggraph_form._form.id,
					dstfld1: `items_${dataset_index}_${row_index}_itemid`,
					dstfld2: `items_${dataset_index}_${row_index}_name`,
					numeric: 1,
					writeonly: 1,
					with_webitems: 1,
					real_hosts: 1,
					resolve_macros: 1,
					excludeids
				}, {dialogue_class: 'modal-popup-generic'});
			}
			else {
				PopUp('popup.generic', {
					srctbl: 'items',
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: widget_svggraph_form._form.id,
					dstfld1: `items_${dataset_index}_${row_index}_itemid`,
					dstfld2: `items_${dataset_index}_${row_index}_name`,
					numeric: 1,
					writeonly: 1,
					with_webitems: 1,
					hostid: this.#templateid,
					hide_host_filter: 1,
					excludeids
				}, {dialogue_class: 'modal-popup-generic'});
			}
		}
		else {
			const exclude_typed_references = [];

			for (const input of dataset.querySelectorAll('.single-item-table-row input[name$="[references][]"]')) {
				if (input.value !== '') {
					exclude_typed_references.push(input.value);
				}
			}

			this.#selectWidget(row, exclude_typed_references);
		}
	}

	#selectWidget(row = null, exclude_typed_references = []) {
		const widgets = ZABBIX.Dashboard.getReferableWidgets({
			type: CWidgetsData.DATA_TYPE_ITEM_ID,
			widget_context: ZABBIX.Dashboard.getEditingWidgetContext()
		});

		widgets.sort((a, b) => a.getHeaderName().localeCompare(b.getHeaderName()));

		const result = [];

		for (const widget of widgets) {
			const typed_reference = CWidgetBase.createTypedReference({
				reference: widget.getFields().reference,
				type: CWidgetsData.DATA_TYPE_ITEM_ID
			});

			if (exclude_typed_references.includes(typed_reference)) {
				continue;
			}

			result.push({
				id: CWidgetBase.createTypedReference({
					reference: widget.getFields().reference,
					type: CWidgetsData.DATA_TYPE_ITEM_ID
				}),
				name: widget.getHeaderName()
			});

		}

		const popup = new CWidgetSelectPopup(result);

		popup.on('dialogue.submit', (e) => {
			if (row === null) {
				this.#addSingleItem({
					reference: e.detail.reference,
					name: e.detail.name,
					type: e.detail.type
				});
			}
			else {
				const name_col = row.querySelector('.table-col-name');
				const name_col_link = name_col.querySelector('a');
				const type_input = row.querySelector('z-select');
				const references_input = row.querySelector('[name$="[references][]"');

				name_col.classList.remove('unavailable-widget');
				name_col_link.textContent = e.detail.name;
				type_input.textContent = e.detail.type;
				references_input.value = e.detail.reference;
			}
		});
	}

	#addSingleItem({itemid = '0', reference = '', name, type} = {}) {
		const dataset = this.#getOpenedDataset();
		const dataset_index = dataset.getAttribute('data-set');

		const items_tbody = dataset.querySelector('.single-item-table tbody');

		if (itemid !== '0') {
			if (items_tbody.querySelector(`input[name$="[itemids][]"][value="${itemid}"]`) !== null) {
				return;
			}
		}
		else {
			if (items_tbody.querySelector(`input[name$="[references][]"][value="${reference}"]`) !== null) {
				return;
			}
		}

		const items_new_index = items_tbody.rows.length + 1;

		const template = new Template(document.querySelector('#dataset-item-row-tmpl').innerHTML);

		const row = template.evaluateToElement({
			dsNum: dataset_index,
			rowNum: items_new_index,
			name: name,
			itemid: itemid,
			reference,
			type: type
		})

		if (itemid === '0') {
			row.querySelector('.table-col-name .reference-hint').classList.remove(ZBX_STYLE_DISPLAY_NONE);
		}

		items_tbody.appendChild(row);

		const used_colors = [];

		for (const color of this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		jQuery(`#items_${dataset_index}_${items_new_index}_color`)
			.val(colorPalette.getNextColor(used_colors))
			.colorpicker();
	}

	#removeSingleItem(element) {
		element.closest('.single-item-table-row').remove();

		const dataset = this.#getOpenedDataset();

		this.#updateSingleItemsOrder(dataset);
		this.#initSingleItemSortable(dataset);
	}

	#initSingleItemSortable(dataset) {
		const rows_container = dataset.querySelector('.single-item-table tbody');

		if (rows_container === null) {
			return;
		}

		if (this.#single_items_sortable.has(dataset)) {
			return;
		}

		const sortable = new CSortable(rows_container, {
			selector_handle: '.table-col-handle'
		});

		sortable.on(CSortable.EVENT_SORT, () => {
			this.#updateSingleItemsOrder(dataset);
		});

		this.#single_items_sortable.set(dataset, sortable);
	}

	updateSingleItemsReferences() {
		const widgets = ZABBIX.Dashboard
			.getReferableWidgets({
				type: CWidgetsData.DATA_TYPE_ITEM_ID,
				widget_context: ZABBIX.Dashboard.getEditingWidgetContext()
			})
			.reduce((map, widget) => map.set(widget.getFields().reference, widget.getHeaderName()), new Map());

		for (const dataset of this.#dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			for (const row of dataset.querySelectorAll('.single-item-table-row')) {
				const itemid_input = row.querySelector('input[name$="[itemids][]"');
				const reference_input = row.querySelector('input[name$="[references][]"');

				if (itemid_input.value !== '0') {
					continue;
				}

				const name_col = row.querySelector('.table-col-name');
				const name_col_link = name_col.querySelector('a');
				const name_col_hint = name_col.querySelector('.reference-hint');

				const {reference} = CWidgetBase.parseTypedReference(reference_input.value);

				if (reference !== '') {
					name_col_link.textContent = widgets.get(reference);
				} else {
					name_col.classList.add('unavailable-widget');
					name_col_link.textContent = <?= json_encode(_('Unavailable widget')) ?>;
				}

				name_col_hint.classList.remove(ZBX_STYLE_DISPLAY_NONE);
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

		for (const element of this.#form.querySelectorAll('#width_label, #width_range, #stroke_label, #stroke_range,' +
			'#show_total_fields'
		)) {
			element.style.display = is_doughnut ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !is_doughnut;
			}
		}

		jQuery('#width').rangeControl(is_doughnut ? 'enable' : 'disable');
		jQuery('#stroke').rangeControl(is_doughnut ? 'enable' : 'disable');

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
		const legend_value = document.getElementById('legend_value');

		jQuery('#legend_lines').rangeControl(is_legend_visible ? 'enable' : 'disable');
		jQuery('#legend_columns').rangeControl(is_legend_visible && !legend_value.checked ? 'enable' : 'disable');

		legend_value.disabled = !is_legend_visible;
		document.getElementById('legend_aggregation').disabled = !is_legend_visible;

		for (const input of this.#form.querySelectorAll('[name=legend_lines_mode]')) {
			input.disabled = !is_legend_visible;
		}

		const legend_lines_mode = this.#form.querySelector('[name=legend_lines_mode]:checked').value;

		this.#form.querySelector('[for=legend_lines]')
			.textContent = legend_lines_mode == <?= WidgetForm::LEGEND_LINES_MODE_VARIABLE ?>
				? <?= json_encode(_('Maximum number of rows')) ?>
				: <?= json_encode(_('Number of rows')) ?>;

		// Trigger event to update tab indicators.
		document.getElementById('tabs').dispatchEvent(new Event(TAB_INDICATOR_UPDATE_EVENT));
	}
};
