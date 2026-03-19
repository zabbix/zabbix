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


class CDataTableContextPopupMonitoringProblemsProblem extends CDataTableContextPopup {
	getFields() {
		const show_opdata = this.getElement().querySelector('[name="show_opdata"]');
		const details = this.getElement().querySelector('[name="details"]');
		const show_suppressed = this.getElement().querySelector('[name="show_suppressed"]');

		return {show_opdata, details, show_suppressed};
	}

	getTemplate() {
		return document.querySelector('template#problem');
	}

	getFieldData() {
		const show_opdata = this.getField('show_opdata').checked ? '1' : '0';
		const details = this.getField('details').checked ? '1' : '0';
		const show_suppressed = this.getField('show_suppressed').checked ? '1' : '0';

		return {show_opdata, details, show_suppressed};
	}

	getDefaultData() {
		return {
			show_opdata: 0,
			details: 0,
			show_suppressed: 0
		}
	}

	getValidatedData(data) {
		const defaults = this.getDefaultData();

		for (const key in Object.keys(data)) {
			if (!defaults.hasOwnProperty(key)) {
				delete data[key];

				continue;
			}

			if (data[key] < 0 || data[key] > 1) {
				data[key] = defaults[key];
			}
		}

		return {...defaults, ...data};
	}

	onInit() {
		super.onInit();

		const context_popup_data = this.getColumnConfig().getContextPopupData();
		const compact_view = this.getDataTable().getOption('compact_view');

		for (const field of Object.keys(this.getDefaultData())) {
			const input = this.getField(field);
			input.checked = context_popup_data[field] == 1;

			if (['show_opdata', 'details'].includes(field)) {
				input.disabled = compact_view.checked;
			}

			input.addEventListener('input', event => event.stopPropagation());

			input.addEventListener('change', event => {
				event.stopPropagation();

				const context_popup_data = {
					...this.getColumnConfig().getContextPopupData(),
					[field]: event.target.checked ? '1' : '0'
				};

				this.getColumnConfig().setContextPopupData(context_popup_data);

				this.getDataTable().updateUserConfig();

				this.getDataTable().dispatchEvent(CDataTable.EVENT_INIT);
				this.getDataTable().dispatchEvent(CDataTable.EVENT_SAVE);
			});
		}
	}
}
