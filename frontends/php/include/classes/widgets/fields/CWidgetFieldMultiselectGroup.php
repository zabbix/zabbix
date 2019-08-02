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


class CWidgetFieldMultiselectGroup extends CWidgetFieldMultiselect {

	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this
			->setObjectName('hostGroup')
			->setPopupOptions([
				'srctbl' => 'host_groups',
				'srcfld1' => 'groupid',
				'real_hosts' => true,
				'enrich_parent_groups' => true
			])
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_GROUP)
			->setInaccessibleCaption(_('Inaccessible group'))
		;
	}

	public function getCaptions($values) {
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $values,
			'preservekeys' => true
		]);

		$captions = [];

		foreach ($groups as $groupid => $group) {
			$captions[$groupid] = [
				'id' => $groupid,
				'name' => $group['name']
			];
		}

		return $captions;
	}
}
