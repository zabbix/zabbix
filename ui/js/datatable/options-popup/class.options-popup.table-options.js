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


class CDataTableOptionsPopupTableOptions extends CDataTableOptionsPopup {

	static ZBX_STYLE_OPTIONS_TABLE = 'datatable-options';
	static ZBX_STYLE_OPTIONS_TABLE_HEADER = 'datatable-options-header';
	static ZBX_STYLE_OPTIONS_LIST = 'datatable-options-list';
	static ZBX_STYLE_OPTIONS_LIST_ITEM = 'datatable-options-list-item';
	static ZBX_STYLE_OPTIONS_LIST_ITEM_LABEL = 'datatable-options-list-item-label';
	static ZBX_STYLE_OPTIONS_RESET = 'datatable-options-reset';

	#sortable = null;

	getTemplate() {
		const template = document.createElement('div');
		const options = this.getDataTable().getOptions();

		if (Object.keys(options).length > 0) {
			const table_options_header = document.createElement('div');
			table_options_header.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_TABLE_HEADER);
			table_options_header.textContent = t('Table options');

			const table_options = document.createElement('ul');
			table_options.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST);

			for (const [id, option] of Object.entries(options)) {
				const input = document.createElement('input');
				input.id = id;
				input.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
				input.setAttribute('type', 'checkbox');
				input.setAttribute('data-field-type', 'checkbox');
				input.value = '1';
				input.checked = option.checked;
				input.addEventListener('change', event => option.onChange(event, option));

				const label = document.createElement('div');
				label.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM_LABEL);
				label.textContent = option.name;

				const input_label = document.createElement('label');
				input_label.setAttribute('for', option.id);
				input_label.append(document.createElement('span'), label);

				const item = document.createElement('li');
				item.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM);
				item.append(input, input_label);

				table_options.appendChild(item);
			}

			template.append(table_options_header, table_options);
		}

		const popup_header = document.createElement('div');
		popup_header.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_TABLE_HEADER);
		popup_header.textContent = t('Column list');

		this.#sortable = document.createElement('ul');
		this.#sortable.classList.add(
			CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST,
			CSortable.ZBX_STYLE_CLASS
		);

		const reset_button = document.createElement('button');
		reset_button.classList.add(ZBX_STYLE_BTN_ALT);
		reset_button.textContent = t('Reset layout');
		reset_button.setAttribute('type', 'button');
		reset_button.addEventListener('click', () => {
			const message = t('Your table settings will be reset to default and duplicated columns will be removed.');

			if (confirm(message)) {
				this.getDataTable().dispatchEvent(CDataTable.EVENT_RESET);
			}
		});

		const reset = document.createElement('div');
		reset.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_RESET);
		reset.appendChild(reset_button);

		template.append(popup_header, this.#sortable, reset);

		this.getDataTable().getColumns()
			.filter(column => column.isShowInTableOptions())
			.forEach(column => {
				const OPTIONS_table_item = this.#createSortableItem(column);

				this.#sortable.appendChild(OPTIONS_table_item);
			});

		return template;
	}

	onInit() {
		super.onInit();

		this.getElement().classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_TABLE);
	}

	onOpen() {
		super.onOpen();

		const sortable = new CSortable(this.#sortable, {
			selector_handle: `.${CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM}`
		})
			.on(CSortable.EVENT_SORT, e => {
				const items = sortable.getTarget()
					.querySelectorAll(`.${CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM}`);

				const {index, index_to} = e.detail;

				this.getDataTable().dispatchEvent(CDataTable.EVENT_COLUMNS_SORT, { items, index, index_to });
			});
	}

	#createSortableItem(column) {
		const column_index = column.getColumnIndex();
		const id = `col-${column_index.toString()}`;

		const icon = document.createElement('div');
		icon.classList.add(ZBX_STYLE_DRAG_ICON);

		const input = document.createElement('input');
		input.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
		input.setAttribute('id', id);
		input.setAttribute('type', 'checkbox');
		input.setAttribute('data-field-type', 'checkbox');
		input.checked = column.isVisible();
		input.disabled = !column.isTogglable();
		input.value = '1';
		input.addEventListener('change', e => {
			const visible = e.target.checked;

			this.getDataTable().dispatchEvent(CDataTable.EVENT_COLUMN_TOGGLE, {column_index, visible},
				{cancelable: true});
		});

		const label = document.createElement('div');
		label.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM_LABEL);
		label.textContent = column.getName();

		const input_label = document.createElement('label');
		input_label.setAttribute('for', id);
		input_label.append(document.createElement('span'), label);

		const item = document.createElement('li');
		item.classList.add(CDataTableOptionsPopupTableOptions.ZBX_STYLE_OPTIONS_LIST_ITEM);
		item.setAttribute('data-col', column_index.toString());
		item.append(icon, input, input_label);

		return item;
	}
}
