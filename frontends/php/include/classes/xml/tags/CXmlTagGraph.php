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


class CXmlTagGraph extends CXmlTagAbstract
{
	protected $tag = 'graphs';

	public function __construct(array $schema = [])
	{
		$schema += [
			'name' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'graph_items' => [
				'key' => 'gitems',
				'type' => CXmlDefine::ARRAY | CXmlDefine::REQUIRED,
				'schema' => (new CXmlTagGraphItem)->getSchema()
			],
			'height' => [
				'type' => CXmlDefine::STRING,
				'value' => 200
			],
			'percent_left' => [
				'type' => CXmlDefine::STRING,
				'value' => 0
			],
			'percent_right' => [
				'type' => CXmlDefine::STRING,
				'value' => 0
			],
			'show_3d' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'show_legend' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'show_triggers' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'show_work_period' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'type' => [
				'key' => 'graphtype',
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NORMAL,
				'range' => [
					CXmlDefine::NORMAL => 'NORMAL',
					CXmlDefine::STACKED => 'STACKED',
					CXmlDefine::PIE => 'PIE',
					CXmlDefine::EXPLODED => 'EXPLODED'
				]
			],
			'width' => [
				'type' => CXmlDefine::STRING,
				'value' => 900
			],
			'yaxismax' => [
				'type' => CXmlDefine::STRING,
				'value' => 100
			],
			'yaxismin' => [
				'type' => CXmlDefine::STRING,
				'value' => 0
			],
			'ymax_item_1' => [
				'key' => 'ymax_itemid',
				'type' => CXmlDefine::STRING
			],
			'ymax_type_1' => [
				'key' => 'ymax_type',
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::CALCULATED,
				'range' => [
					CXmlDefine::CALCULATED => 'CALCULATED',
					CXmlDefine::FIXED => 'FIXED',
					CXmlDefine::ITEM => 'ITEM'
				]
			],
			'ymin_item_1' => [
				'key' => 'ymin_itemid',
				'type' => CXmlDefine::STRING
			],
			'ymin_type_1' => [
				'key' => 'ymin_type',
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::CALCULATED,
				'range' => [
					CXmlDefine::CALCULATED => 'CALCULATED',
					CXmlDefine::FIXED => 'FIXED',
					CXmlDefine::ITEM => 'ITEM'
				]
			],
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		CArrayHelper::sort($data, ['name']);

		foreach ($data as &$graph) {
			$graph['gitems'] = (new CXmlTagGraphItem)->prepareData($graph['gitems']);
		}

		return $data;
	}
}
