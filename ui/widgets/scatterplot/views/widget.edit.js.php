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


use Widgets\ScatterPlot\Includes\CWidgetFieldDataSet;

?>

window.widget_form = new class extends CWidgetForm {

	/**
	 * @type {Map<HTMLLIElement, CSortable>}
	 */
	#single_items_sortable = new Map();

	/**
	 * @type {number}
	 */
	#dataset_row_unique_id = 0;

	init({form_tabs_id, color_palette, templateid, thresholds}) {
		colorPalette.setThemeColors(color_palette);

		this._$overlay_body = jQuery('.overlay-dialogue-body');
		this._form = this.getForm();
		this._templateid = templateid;
		this._dataset_wrapper = document.getElementById('data_sets');
		this._any_ds_aggregation_function_enabled = false;

		this.#dataset_row_unique_id =
			this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length - 1;

		jQuery(`#${form_tabs_id}`)
			.on('change', 'input, z-color-picker, z-select, .multiselect', () => this.onGraphConfigChange());

		this._dataset_wrapper.addEventListener('input', e => {
			if (e.target.matches('input[name$="[data_set_label]"]') || e.target.matches('input[name$="[timeshift]"]')) {
				this.registerUpdateEvent();
			}
		});

		this._datasetTabInit();

		this._updateForm();

		this.ready();
	}

	onGraphConfigChange() {
		this.registerUpdateEvent();

		this._updateForm();
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

				['name', 'color-field-name', 'palette-field-name'].forEach(attr => {
					jQuery(`[${attr}^="${var_prefix}["]`, this)
						.filter(function () {
							return jQuery(this).attr(attr).match(/[a-z]+\[\d+]\[[a-z_]+]/);
						})
						.each(function () {
							const $this = jQuery(this);
							$this.attr(attr, $this.attr(attr).replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${k + i}$2`));
						});
				});
			});
		}
	}

	_datasetTabInit() {
		this._updateDatasetsLabel();

		// Initialize vertical accordion.

		const $data_sets = jQuery(this._dataset_wrapper);

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
				const list_item = e.target.closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>');

				if (list_item.classList.contains('<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>')) {
					return;
				}

				if (e.target.classList.contains('js-click-expand')
						|| e.target.closest(`.${ZBX_STYLE_COLOR_PICKER}`) !== null) {
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
				}
			})
			.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>'});

		// Initialize rangeControl UI elements.
		jQuery('.<?= CRangeControl::ZBX_STYLE_CLASS ?>', jQuery(this._dataset_wrapper)).rangeControl();

		this._dataset_wrapper.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this._key = this._getItemListTableKey(e.target);
				this._selectItems();
			}

			if (e.target.classList.contains('js-add-widget')) {
				this._key = this._getItemListTableKey(e.target);
				this._selectWidget();
			}

			if (e.target.matches('.single-item-table-row .table-col-name a')) {
				this._editItem(e.target);
			}

			if (e.target.classList.contains('js-single-item-row-remove')) {
				this._removeSingleItem(e.target);
			}

			if (e.target.classList.contains('js-dataset-remove')) {
				this._removeDataSet(e.target);
			}
		});

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => {
				this._addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
			});

		document
			.getElementById('dataset-menu')
			.addEventListener('click', (e) => this._addDatasetMenu(e));

		window.addPopupValues = (list) => {
			const key = this._key;

			if (!isset('object', list) || list.object !== 'itemid') {
				return false;
			}

			for (let i = 0; i < list.values.length; i++) {
				this._addSingleItem({
					itemid: list.values[i].itemid,
					name: list.values[i].name,
					key
				});
			}
		}

		this._updateSingleItemsReferences();
		this._initDataSetSortable();
	}

	_getItemListTableKey(target) {
		return target.closest('.single-item-table').attributes['data-key'].value;
	}

	_addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item patterns')) ?>,
						clickCallback: () => {
							this._addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							this._addDataset(<?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>);
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						disabled: this._getOpenedDataset() === null,
						clickCallback: () => {
							this._cloneDataset();
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

	_addDataset(type) {
		jQuery(this._dataset_wrapper).zbx_vertical_accordion('collapseAll');

		const template = type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>
			? new Template(jQuery('#dataset-single-item-tmpl').html())
			: new Template(jQuery('#dataset-pattern-item-tmpl').html());

		const used_colors = [];

		for (const color_picker of this._form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`)) {
			if (color_picker.color !== '') {
				used_colors.push(color_picker.color);
			}
		}

		const fragment = document.createRange().createContextualFragment(template.evaluate({
			rowNum: ++this.#dataset_row_unique_id,
			color: colorPalette.getNextColor(used_colors)
		}));

		this._dataset_wrapper.append(fragment);

		this.updateVariableOrder(this._dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this._updateDatasetsLabel();

		const dataset = this._getOpenedDataset();

		for (const range_control of dataset.querySelectorAll('.<?= CRangeControl::ZBX_STYLE_CLASS ?>')) {
			jQuery(range_control).rangeControl();
		}

		this._$overlay_body.scrollTop(Math.max(this._$overlay_body.scrollTop(),
			this._form.scrollHeight - this._$overlay_body.height()
		));

		this._initDataSetSortable();
		this._updateForm();

		this.registerUpdateEvent();
	}

	_cloneDataset() {
		const dataset = this._getOpenedDataset();

		this._addDataset(dataset.dataset.type);

		const cloned_dataset = this._getOpenedDataset();

		if (dataset.dataset.type == <?= CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM ?>) {
			for (const key of ['x_axis', 'y_axis']) {
				const table = dataset.querySelector(`table.single-item-table[data-key="${key}_itemids"]`);

				for (const row of table.querySelectorAll('.single-item-table-row')) {
					this._addSingleItem({
						itemid: row.querySelector(`[name$='[${key}_itemids][]`).value,
						reference: row.querySelector(`[name$='[${key}_references][]`).value,
						name: row.querySelector('.table-col-name a').textContent,
						key: `${key}_itemids`
					});
				}
			}
		}
		else {
			if (this._templateid === null) {
				jQuery('.js-hostgroups-multiselect', cloned_dataset).multiSelect('addData',
					jQuery('.js-hostgroups-multiselect', dataset).multiSelect('getData')
				);

				jQuery('.js-hosts-multiselect', cloned_dataset).multiSelect('addData',
					jQuery('.js-hosts-multiselect', dataset).multiSelect('getData')
				);
			}

			jQuery('.js-x-items-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-x-items-multiselect', dataset).multiSelect('getData')
			);
			jQuery('.js-y-items-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-y-items-multiselect', dataset).multiSelect('getData')
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
	}

	_removeDataSet(obj) {
		const dataset_remove = obj.closest('.list-accordion-item');

		dataset_remove.remove();

		if (this.#single_items_sortable.has(dataset_remove)) {
			this.#single_items_sortable.get(dataset_remove).enable(false);
			this.#single_items_sortable.delete(dataset_remove);
		}

		this.updateVariableOrder(jQuery(this._dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this._updateDatasetsLabel();

		const dataset = this._getOpenedDataset();

		if (dataset !== null) {
			this._updateSingleItemsOrder(dataset);
		}

		this._initDataSetSortable();
		this.onGraphConfigChange();
	}

	_getOpenedDataset() {
		return this._dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
	}

	_initDataSetSortable() {
		if (this._sortable_data_set === undefined) {
			this._sortable_data_set = new CSortable(document.querySelector('#data_sets'), {
				selector_handle: '.js-main-drag-icon, .js-dataset-label'
			});

			this._sortable_data_set.on(CSortable.EVENT_SORT, () => {
				this.updateVariableOrder(this._dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
				this._updateDatasetsLabel();

				this.registerUpdateEvent();
			});
		}
	}

	_selectItems() {
		if (this._templateid === null) {
			PopUp('popup.generic', {
				srctbl: 'items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this._form.id,
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
				dstfrm: this._form.id,
				numeric: 1,
				writeonly: 1,
				multiselect: 1,
				with_webitems: 1,
				hostid: this._templateid,
				hide_host_filter: 1
			}, {dialogue_class: 'modal-popup-generic'});
		}
	}

	_editItem(target) {
		const dataset = this._getOpenedDataset();
		const dataset_index = dataset.getAttribute('data-set');

		const row = target.closest('.single-item-table-row');
		const row_index = row.rowIndex;

		const key = this._getItemListTableKey(target);

		const itemid_input = row.querySelector(`input[name$="${key}][]"]`);

		if (itemid_input.value !== '0') {
			const excludeids = [];

			for (const input of dataset.querySelectorAll(`.single-item-table-row input[name$="${key}][]"]`)) {
				if (input.value !== '0') {
					excludeids.push(input.value);
				}
			}

			if (this._templateid === null) {
				PopUp('popup.generic', {
					srctbl: 'items',
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: this._form.id,
					dstfld1: `${key}_${dataset_index}_${row_index}_itemid`,
					dstfld2: `${key}_${dataset_index}_${row_index}_name`,
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
					dstfrm: this._form.id,
					dstfld1: `${key}_${dataset_index}_${row_index}_itemid`,
					dstfld2: `${key}_${dataset_index}_${row_index}_name`,
					numeric: 1,
					writeonly: 1,
					with_webitems: 1,
					hostid: this._templateid,
					hide_host_filter: 1,
					excludeids
				}, {dialogue_class: 'modal-popup-generic'});
			}
		}
		else {
			const exclude_typed_references = [];

			const reference_key = key === 'x_axis_itemids' ? 'x_axis_references' : 'y_axis_references';

			for (const input of dataset.querySelectorAll(`.single-item-table-row input[name$="${reference_key}][]"]`)) {
				if (input.value !== '') {
					exclude_typed_references.push(input.value);
				}
			}

			this._selectWidget(row, exclude_typed_references);
		}
	}

	_selectWidget(row = null, exclude_typed_references = []) {
		const widgets = ZABBIX.Dashboard.getReferableWidgets({
			type: CWidgetsData.DATA_TYPE_ITEM_ID,
			widget_context: ZABBIX.Dashboard.getWidgetEditingContext()
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
			const key = this._key;

			if (row === null) {
				this._addSingleItem({
					reference: e.detail.reference,
					name: e.detail.name,
					key
				});
			}
			else {
				const name_col = row.querySelector('.table-col-name');
				const name_col_link = name_col.querySelector('a');
				const references_input = row.querySelector('input[name$="references][]"');

				name_col.classList.remove('unavailable-widget');
				name_col_link.textContent = e.detail.name;
				references_input.value = e.detail.reference;
			}
		});
	}

	_addSingleItem({itemid = '0', reference = '', name, key = 'x_axis_itemids'} = {}) {
		const dataset = this._getOpenedDataset();
		const dataset_index = dataset.getAttribute('data-set');

		const items_tbody = dataset.querySelector(`.single-item-table[data-key=${key}] tbody`);

		if (itemid !== '0') {
			if (items_tbody.querySelector(`input[name$="[${key}][]"][value="${itemid}"]`) !== null) {
				return;
			}
		}
		else {
			if (items_tbody.querySelector(`input[name$="[${key}][]"][value="${reference}"]`) !== null) {
				return;
			}
		}

		const items_new_index = items_tbody.rows.length + 1;

		const template = new Template(jQuery('#dataset-item-row-tmpl').html());

		const row = template.evaluateToElement({
			dsNum: dataset_index,
			rowNum: items_new_index,
			itemid,
			reference,
			name,
			key,
			key_reference: key === 'x_axis_itemids' ? 'x_axis_references' : 'y_axis_references'
		});

		if (itemid === '0') {
			row.querySelector('.table-col-name .reference-hint').classList.remove(ZBX_STYLE_DISPLAY_NONE);
		}

		items_tbody.appendChild(row);

		this.registerUpdateEvent();
	}

	_removeSingleItem(element) {
		element.closest('.single-item-table-row').remove();

		const dataset = this._getOpenedDataset();

		this._updateSingleItemsOrder(dataset);

		this.registerUpdateEvent();
	}

	_updateSingleItemsReferences() {
		const widgets = ZABBIX.Dashboard
			.getReferableWidgets({
				type: CWidgetsData.DATA_TYPE_ITEM_ID,
				widget_context: ZABBIX.Dashboard.getWidgetEditingContext()
			})
			.reduce((map, widget) => map.set(widget.getFields().reference, widget.getHeaderName()), new Map());

		for (const dataset of this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			for (const row of dataset.querySelectorAll('.single-item-table-row')) {
				const itemid_input = row.querySelector(`input[name$="itemids][]"`);
				const reference_input = row.querySelector(`input[name$="references][]"`);

				if (itemid_input.value !== '0') {
					continue;
				}

				const name_col = row.querySelector('.table-col-name');
				const name_col_link = name_col.querySelector('a');
				const name_col_hint = name_col.querySelector('.reference-hint');

				const {reference} = CWidgetBase.parseTypedReference(reference_input.value);

				if (reference !== '') {
					name_col_link.textContent = widgets.get(reference);
				}
				else {
					name_col.classList.add('unavailable-widget');
					name_col_link.textContent = <?= json_encode(_('Unavailable widget')) ?>;
				}

				name_col_hint.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			}
		}
	}

	_updateSingleItemsOrder(dataset) {
		const dataset_index = dataset.getAttribute('data-set');

		for (const table of dataset.querySelectorAll('.single-item-table')) {
			const key = table.attributes['data-key'].value;

			for (const row of table.querySelectorAll('.single-item-table-row')) {
				const prefix = `${key}_${dataset_index}_${row.rowIndex}`;

				row.querySelector('.table-col-name a').id = `${prefix}_name`;
				row.querySelector('.table-col-action input[name$="itemids][]"]').id = `${prefix}_itemid`;
				row.querySelector('.table-col-action input[name$="references][]"]').id = `${prefix}_reference`;
			}
		}
	}

	_updateDatasetsLabel() {
		for (const dataset of this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			this._updateDatasetLabel(dataset);
		}
	}

	_updateDatasetLabel(dataset) {
		const placeholder_text = <?= json_encode(_('Data set')) ?> + ` #${parseInt(dataset.dataset.set) + 1}`;

		const data_set_label = dataset.querySelector('.js-dataset-label');

		data_set_label.textContent = placeholder_text;
	}

	_updateForm() {
		const x_axis_checkbox = document.getElementById('x_axis');

		for (const element of document.querySelectorAll('#x_axis_scale, #x_axis_min, #x_axis_max, #x_axis_units')) {
			element.disabled = !x_axis_checkbox.checked;
		}

		document.getElementById('x_axis_static_units').disabled = !x_axis_checkbox.checked
			|| document.getElementById('x_axis_units').value != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>;

		const y_axis_checkbox = document.getElementById('y_axis');

		for (const element of document.querySelectorAll('#y_axis_scale, #y_axis_min, #y_axis_max, #y_axis_units')) {
			element.disabled = !y_axis_checkbox.checked;
		}

		document.getElementById('y_axis_static_units').disabled = !y_axis_checkbox.checked
			|| document.getElementById('y_axis_units').value != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>;

		// Legend tab.
		const show_legend = document.getElementById('legend').checked;

		document.getElementById('legend_aggregation').disabled = !show_legend;

		for (const input of this._form.querySelectorAll('[name=legend_lines_mode]')) {
			input.disabled = !show_legend;
		}

		jQuery('#legend_lines').rangeControl(show_legend ? 'enable' : 'disable');
		jQuery('#legend_columns').rangeControl(show_legend ? 'enable' : 'disable');

		document.querySelector('[for=legend_lines]')
			.textContent = document.querySelector('[name=legend_lines_mode]:checked').value === '1'
				? <?= json_encode(_('Maximum number of rows')) ?>
				: <?= json_encode(_('Number of rows')) ?>;

		// Trigger event to update tab indicators.
		document.getElementById('tabs').dispatchEvent(new Event(TAB_INDICATOR_UPDATE_EVENT));
	}
};
