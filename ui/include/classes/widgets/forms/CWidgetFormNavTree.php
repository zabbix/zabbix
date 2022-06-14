<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Map navigation widget form.
 */
class CWidgetFormNavTree extends CWidgetForm {

	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_NAV_TREE);

		$this->data = self::convertDottedKeys($this->data);

		// Widget reference field.
		$field_reference = (new CWidgetFieldReference())->setDefault('');

		if (array_key_exists($field_reference->getName(), $this->data)) {
			$field_reference->setValue($this->data[$field_reference->getName()]);
		}

		$this->fields[$field_reference->getName()] = $field_reference;

		// Elements of the tree.
		$field_navtree = new CWidgetFieldNavTree('navtree', '');

		if (array_key_exists('navtree', $this->data)) {
			$field_navtree->setValue($this->data['navtree']);
		}

		$this->fields[$field_navtree->getName()] = $field_navtree;

		// Show unavailable maps.
		$show_unavailable_maps = (new CWidgetFieldCheckBox('show_unavailable', _('Show unavailable maps')))
			->setDefault(0);

		if (array_key_exists('show_unavailable', $this->data)) {
			$show_unavailable_maps->setValue($this->data['show_unavailable']);
		}

		$this->fields[$show_unavailable_maps->getName()] = $show_unavailable_maps;
	}
}
