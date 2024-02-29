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


window.widget_graphprototype_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init(options) {
		this.#form = document.getElementById(options.form_id);

		document.getElementById('source_type').addEventListener('change', () => this.#updateForm());

		this.#updateForm();
	}

	#updateForm() {
		const is_graph_prototype = this.#form.querySelector('#source_type input[name="source_type"]:checked').value
			== <?= ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE ?>;

		this.#form.querySelectorAll('.js-row-graphid').forEach(element => {
			element.style.display = is_graph_prototype ? '' : 'none';
		});

		this.#form.querySelectorAll('.js-row-itemid').forEach(element => {
			element.style.display = is_graph_prototype ? 'none' : '';
		});

		$('#graphid').multiSelect(is_graph_prototype ? 'enable' : 'disable');
		$('#itemid').multiSelect(is_graph_prototype ? 'disable' : 'enable');

		const ms_itemid_input = this.#form.querySelector('[name="itemid"]');
		const ms_graphid_input = this.#form.querySelector('[name="graphid"]');

		if (ms_itemid_input !== null) {
			ms_itemid_input.disabled = is_graph_prototype;
		}

		if (ms_graphid_input !== null) {
			ms_graphid_input.disabled = !is_graph_prototype;
		}
	}
};
