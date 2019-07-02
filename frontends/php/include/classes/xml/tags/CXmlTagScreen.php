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


class CXmlTagScreen extends CXmlTagAbstract
{
	protected $tag = 'screens';

	public function __construct(array $schema = [])
	{
		$schema += [
			'name' => [
				'type' => CXmlDefine::STRING
			],
			'hsize' => [
				'type' => CXmlDefine::STRING
			],
			'screen_items' => [
				'key' => 'screenitems',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagScreenItem)->getSchema()
			],
			'vsize' => [
				'type' => CXmlDefine::STRING
			],
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		CArrayHelper::sort($data, ['name']);

		foreach ($data as &$screen) {
			if ($screen['screenitems']) {
				$screen['screenitems'] = (new CXmlTagScreenItem)->prepareData($screen['screenitems']);
			}
		}

		return $data;
	}
}
