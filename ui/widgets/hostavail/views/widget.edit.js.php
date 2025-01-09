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
