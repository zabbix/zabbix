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
		this._form = document.getElementById('widget-dialogue-form');
		this._source_type = this._form.querySelector('#source_type input[name="source_type"]:checked').value;

		document.getElementById('source_type').addEventListener('change', (e) => {
			this._source_type = e.target.value;
			this.updateForm();
		});

		this.updateForm();
	}

	updateForm() {
		const is_simple_graph_prototype = this._source_type == <?= ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE ?>;
		const is_graph_prototype = this._source_type == <?= ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE ?>;

		Array.from(document.getElementsByClassName('item_prototye_multiselect')).map((element) => {
			element.style.display = is_simple_graph_prototype ? '' : 'none';
		});

		Array.from(document.getElementsByClassName('graph_prototye_multiselect')).map((element) => {
			element.style.display = is_graph_prototype ? '' : 'none';
		});

		$('#itemid').multiSelect(is_simple_graph_prototype ? 'enable' : 'disable');
		$('#graphid').multiSelect(is_graph_prototype ? 'enable' : 'disable');
	}
};
