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


class CXmlTagGraphItem extends CXmlTagAbstract
{
	protected $tag = 'graph_items';

	protected $data_sort = ['sortorder'];

	public function __construct(array $schema = [])
	{
		$schema += [
			'item' => [
				'key' => 'itemid',
				'type' => CXmlDefine::INDEXED_ARRAY | CXmlDefine::REQUIRED,
				'schema' => (new CXmlTagGraphItemItem)->getSchema(),
			],
			'calc_fnc' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::AVG,
				'range' => [
					CXmlDefine::MIN => 'MIN',
					CXmlDefine::AVG => 'AVG',
					CXmlDefine::MAX => 'MAX',
					CXmlDefine::ALL => 'ALL',
					CXmlDefine::LAST => 'LAST'
				]
			],
			'color' => [
				'type' => CXmlDefine::STRING
			],
			'drawtype' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::SINGLE_LINE,
				'range' => [
					CXmlDefine::SINGLE_LINE => 'SINGLE_LINE',
					CXmlDefine::FILLED_REGION => 'FILLED_REGION',
					CXmlDefine::BOLD_LINE => 'BOLD_LINE',
					CXmlDefine::DOTTED_LINE => 'DOTTED_LINE',
					CXmlDefine::DASHED_LINE => 'DASHED_LINE',
					CXmlDefine::GRADIENT_LINE => 'GRADIENT_LINE'
				]
			],
			'sortorder' => [
				'type' => CXmlDefine::STRING
			],
			'type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::SIMPLE,
				'range' => [
					CXmlDefine::SIMPLE => 'SIMPLE',
					CXmlDefine::GRAPH_SUM => 'GRAPH_SUM'
				]
			],
			'yaxisside' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::LEFT,
				'range' => [
					CXmlDefine::LEFT => 'LEFT',
					CXmlDefine::RIGHT => 'RIGHT'
				]
			],
		];

		$this->schema = $schema;
	}
}
