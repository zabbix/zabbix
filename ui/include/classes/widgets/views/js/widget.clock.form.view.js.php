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


window.widget_clock_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {HTMLFormElement}
	 */
	#time_type;

	init(options) {
		this.#form = document.getElementById(options.form_id);

		this.#time_type = document.getElementById('time_type');

		this.#time_type.addEventListener('change', () => this.#updateForm());

		this.#updateForm();
	}

	#updateForm() {
		this.#form.querySelectorAll('.js-row-itemid').forEach(element => {
			element.style.display = this.#time_type.value == <?= TIME_TYPE_HOST ?> ? '' : 'none'
		});

		$('#itemid').multiSelect(this.#time_type.value != <?= TIME_TYPE_HOST ?> ? 'disable' : 'enable');

		const ms_itemid_input = this.#form.querySelector('[name="itemid"]');

		if (ms_itemid_input !== null && this.#time_type.value != <?= TIME_TYPE_HOST ?>) {
			ms_itemid_input.disabled = true;
		}
	}
};
