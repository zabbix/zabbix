<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CGraphWidgetForm extends CWidgetForm {
	public function __construct($data)
	{
		parent::__construct($data);

		// Select graph field
		$field_graph = (new CWidgetFieldSelectResource('graphid', _('Graph'), WIDGET_FIELD_SELECT_RES_GRAPH))
			->setRequired(true);
		if (array_key_exists('graphid', $data)) {
			$field_graph->setValue($data['graphid']);
		}
		$this->fields[] = $field_graph;

		// Dynamic item
		$field_dynamic = (new CWidgetFieldCheckbox('dynamic', _('Dynamic item')));
		if (array_key_exists('dynamic', $data)) {
			$field_dynamic->setValue($data['dynamic']);
		}
		else {
			$field_dynamic->setValue(false);
		}
		$this->fields[] = $field_dynamic;
	}
}
