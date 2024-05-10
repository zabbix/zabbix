<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
