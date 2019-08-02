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


class CWidgetFieldMultiselectGraph extends CWidgetFieldMultiselect {

	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this
			->setObjectName('graphs')
			->setPopupOptions([
				'srctbl' => 'graphs',
				'srcfld1' => 'graphid',
				'srcfld2' => 'name',
				'real_hosts' => true,
				'with_graphs' => true
			])
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_GRAPH)
			->setInaccessibleCaption(_('Inaccessible graph'))
		;
	}

	public function getCaptions($values) {
		$graphs = API::Graph()->get([
			'output' => ['graphid', 'name'],
			'selectHosts' => ['name'],
			'graphids' => $values,
			'preservekeys' => true
		]);

		$captions = [];

		foreach ($graphs as $graphid => $graph) {
			$captions[$graphid] = [
				'id' => $graphid,
				'name' => $graph['name'],
				'prefix' => $graph['hosts'][0]['name'].NAME_DELIMITER
			];
		}

		return $captions;
	}
}
