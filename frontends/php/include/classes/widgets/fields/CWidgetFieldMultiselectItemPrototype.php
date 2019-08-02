<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CWidgetFieldMultiselectItemPrototype extends CWidgetFieldMultiselect {

	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this
			->setObjectName('item_prototypes')
			->setPopupOptions([
				'srctbl' => 'item_prototypes',
				'srcfld1' => 'itemid',
				'real_hosts' => true,
			])
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE)
			->setInaccessibleCaption(_('Inaccessible item prototype'))
		;
	}

	public function getCaptions($values) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'key_'],
			'selectHosts' => ['name'],
			'itemids' => $values,
			'preservekeys' => true
		]);

		$items = CMacrosResolverHelper::resolveItemNames($items);

		$captions = [];

		foreach ($items as $itemid => $item) {
			$captions[$itemid] = [
				'id' => $itemid,
				'name' => $item['name_expanded'],
				'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
			];
		}

		return $captions;
	}
}
