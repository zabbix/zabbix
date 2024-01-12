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

window.widget_graph_prototype_form = new class {

	init() {
		document.getElementById('source_type').addEventListener('change', () => this.#updateForm());

		this.#updateForm();
	}

	#updateForm() {
		const form = document.getElementById('widget-dialogue-form');
		const is_graph_prototype = form.querySelector('#source_type input[name="source_type"]:checked').value
			== <?= ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE ?>;

		form.querySelectorAll('.js-graph-prototype-multiselect').forEach(element => {
			element.style.display = is_graph_prototype ? '' : 'none';
		});

		form.querySelectorAll('.js-item-prototype-multiselect').forEach(element => {
			element.style.display = is_graph_prototype ? 'none' : '';
		});

		$('#graphid').multiSelect(is_graph_prototype ? 'enable' : 'disable');
		$('#itemid').multiSelect(is_graph_prototype ? 'disable' : 'enable');
	}
};
