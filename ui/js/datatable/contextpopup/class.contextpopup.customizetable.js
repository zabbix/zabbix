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


class CDataTableCustomizeTablePopup extends CDataTableContextPopup {

	static ZBX_STYLE_CUSTOMIZE_TABLE = 'datatable-customize';
	static ZBX_STYLE_CUSTOMIZE_TABLE_HEADER = 'datatable-customize-header';
	static ZBX_STYLE_CUSTOMIZE_LIST = 'datatable-customize-list';
	static ZBX_STYLE_CUSTOMIZE_LIST_ITEM = 'datatable-customize-list-item';
	static ZBX_STYLE_CUSTOMIZE_LIST_ITEM_LABEL = 'datatable-customize-list-item-label';
	static ZBX_STYLE_CUSTOMIZE_RESET = 'datatable-customize-reset';

	#sortable = null;

	getTemplate() {
		const template = document.createElement('div');
		const options = this.getDataTable().getOptions();

		if (Object.keys(options).length > 0) {
			const table_options_header = document.createElement('div');
			table_options_header.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_TABLE_HEADER);
			table_options_header.innerText = t('Table options');

			const table_options = document.createElement('ul');
			table_options.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST);

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
				label.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST_ITEM_LABEL);
				label.innerText = option.name;

				const input_label = document.createElement('label');
				input_label.setAttribute('for', option.id);
				input_label.append(document.createElement('span'), label);

				const item = document.createElement('li');
				item.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST_ITEM);
				item.append(input, input_label);

				table_options.appendChild(item);
			}

			template.append(table_options_header, table_options);
		}

		const popup_header = document.createElement('div');
		popup_header.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_TABLE_HEADER);
		popup_header.innerText = t('Column list');

		this.#sortable = document.createElement('ul');
		this.#sortable.classList.add(
			CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST,
			CSortable.ZBX_STYLE_CLASS
		);

		const reset_button = document.createElement('button');
		reset_button.classList.add(ZBX_STYLE_BTN_ALT);
		reset_button.innerText = t('Reset layout');
		reset_button.setAttribute('type', 'button');
		reset_button.addEventListener('click', () => {
			const message = t('Your table settings will be reset to default and duplicated columns will be removed.');

			if (confirm(message)) {
				this.getDataTable().dispatchEvent(CDataTable.EVENT_RESET);
			}
		});

		const reset = document.createElement('div');
		reset.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_RESET);
		reset.appendChild(reset_button);

		template.append(popup_header, this.#sortable, reset);

		this.getDataTable().getColumns()
			.filter(column_config => column_config.isShowInCustomizeTable())
			.forEach(column_config => {
				const customize_table_item = this.#createSortableItem(column_config);

				this.#sortable.appendChild(customize_table_item);
			});

		return template;
	}

	onInit() {
		super.onInit();

		this.getElement().classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_TABLE);
	}

	onOpen() {
		super.onOpen();

		const sortable = new CSortable(this.#sortable, {selector_handle: `.${ZBX_STYLE_DRAG_ICON}`})
			.on(CSortable.EVENT_SORT, event => {
				const items = sortable.getTarget()
					.querySelectorAll(`.${CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST_ITEM}`);

				const {index, index_to} = event.detail;

				this.getDataTable().dispatchEvent(CDataTable.EVENT_COLUMNS_SORT, { items, index, index_to });
			});
	}

	#createSortableItem(column_config) {
		const column_index = column_config.getColumnIndex();
		const id = `col-${column_index.toString()}`;

		const icon = document.createElement('div');
		icon.classList.add(ZBX_STYLE_DRAG_ICON);

		const input = document.createElement('input');
		input.classList.add(ZBX_STYLE_CHECKBOX_RADIO);
		input.setAttribute('id', id);
		input.setAttribute('type', 'checkbox');
		input.setAttribute('data-field-type', 'checkbox');
		input.checked = column_config.isVisible();
		input.disabled = !column_config.isTogglable();
		input.value = '1';
		input.addEventListener('change', (event) => {
			const visible = event.target.checked;

			this.getDataTable().dispatchEvent(CDataTable.EVENT_COLUMN_TOGGLE, {column_index, visible});
		});

		const label = document.createElement('div');
		label.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST_ITEM_LABEL);
		label.innerText = column_config.getName();

		const input_label = document.createElement('label');
		input_label.setAttribute('for', id);
		input_label.append(document.createElement('span'), label);

		const item = document.createElement('li');
		item.classList.add(CDataTableCustomizeTablePopup.ZBX_STYLE_CUSTOMIZE_LIST_ITEM);
		item.setAttribute('data-col', column_index.toString());
		item.append(icon, input, input_label);

		return item;
	}
}
