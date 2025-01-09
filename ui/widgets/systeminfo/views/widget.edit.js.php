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

window.widget_systeminfo_form = new class {

	init() {
		this._form = document.getElementById('widget-dialogue-form');
		this._info_type = document.getElementById('info_type');

		this._info_type.addEventListener('change', () => this.#updateForm());

		this.#updateForm();
	}

	#updateForm() {
		const show_system_info =
			this._info_type.querySelector('input:checked').value == <?= ZBX_SYSTEM_INFO_SERVER_STATS ?>;

		for (const element of this._form.querySelectorAll('.js-show-software-update-check-details')) {
			element.style.display = show_system_info ? '' : 'none';
		}
	}
}
