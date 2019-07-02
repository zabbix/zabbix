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


class CXmlTagDItem extends CXmlTagItem
{
	protected $tag = 'items';

	public function __construct(array $schema = [])
	{
		parent::__construct();

		$schema += [
			'trigger_prototypes' => [
				'key' => 'triggerPrototypes',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagTrigger)->getSchema()
			]
		];

		$this->schema += $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		$data = parent::prepareData($data, $simple_triggers);

		foreach ($data as &$item) {
			if ($item['triggerPrototypes']) {
				$item['triggerPrototypes'] = (new CXmlTagTrigger)->prepareData($item['triggerPrototypes']);
			}
		}

		return $data;
	}
}
