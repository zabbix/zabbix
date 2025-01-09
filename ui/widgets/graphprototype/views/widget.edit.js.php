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

window.widget_graph_prototype_form = new class {

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init() {
		this.#form = document.getElementById('widget-dialogue-form');

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
	}
};
