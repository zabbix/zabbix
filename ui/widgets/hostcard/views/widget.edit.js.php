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


use Widgets\HostCard\Includes\CWidgetFieldHostSections;

?>

window.widget_form = new class extends CWidgetForm {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	/**
	 * @type {HTMLTableElement}
	 */
	#sections_table;

	init() {
		this.#form = this.getForm();
		this.#sections_table = document.getElementById('sections-table');

		this.#form.addEventListener('change', () => this.#updateForm());

		jQuery(this.#sections_table).on('tableupdate.dynamicRows', () => this.#updateForm());

		this.#updateForm();
		this.ready();
	}

	#updateForm() {
		const sections = this.#sections_table.querySelectorAll('z-select');

		document.getElementById('add-row').disabled = sections.length > 0
			&& sections.length === sections[0].getOptions().length;

		let show_inventory_field = false;

		for (const element of sections) {
			if (element.value == <?= CWidgetFieldHostSections::SECTION_INVENTORY ?>) {
				show_inventory_field = true;

				break;
			}
		}

		for (const element of this.#form.querySelectorAll('.js-row-inventory-fields')) {
			element.style.display = show_inventory_field ? '' : 'none';
		}

		jQuery('#inventory_').multiSelect(show_inventory_field ? 'enable' : 'disable');
	}
};
