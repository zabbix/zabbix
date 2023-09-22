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


?>

window.widget_host_availability_form = new class {

	init() {
		for (const element of document.querySelectorAll('[name="interface_type[]"]')) {
			element.addEventListener('change', () => this.#updateForm());
		}

		this.#updateForm();
	}

	#updateForm() {
		document.getElementById('only_totals')
			.disabled = document.querySelectorAll('[name="interface_type[]"]:checked').length === 1;
	}
}
