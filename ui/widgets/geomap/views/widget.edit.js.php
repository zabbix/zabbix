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


?>

window.widget_form = new class extends CWidgetForm {

	init() {
		this._form = this.getForm();
		document.getElementById('clustering_mode').addEventListener('change', () => this.#updateForm());

		this.#updateForm();
		this.ready();
	}

	#updateForm() {
		const clustering_mode = this._form.querySelector('[name="clustering_mode"]:checked');

		if (!clustering_mode) {
			return;
		}

		const clustering_zoom_level = this._form.querySelector('[name="clustering_zoom_level"]');
		const wrapper = clustering_zoom_level.closest('.form-field');

		if (clustering_mode.value === '1') {
			wrapper.classList.remove('display-none');
		} else {
			wrapper.classList.add('display-none');
		}
	}
};
