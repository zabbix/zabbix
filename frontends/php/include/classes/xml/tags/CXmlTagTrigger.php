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


class CXmlTagTrigger extends CXmlTagAbstract
{
	protected $tag = 'triggers';

	protected $data_sort = ['description', 'expression', 'recovery_expression'];

	public function __construct(array $schema = [])
	{
		$schema += [
			'expression' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'name' => [
				'key' => 'description',
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'correlation_mode' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::TRIGGER_DISABLED,
				'range' => [
					CXmlDefine::TRIGGER_DISABLED => 'DISABLED',
					CXmlDefine::TRIGGER_TAG_VALUE => 'TAG_VALUE'
				]
			],
			'correlation_tag' => [
				'type' => CXmlDefine::STRING
			],
			'dependencies' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagDependency)->getSchema()
			],
			'description' => [
				'key' => 'comments',
				'type' => CXmlDefine::STRING
			],
			'manual_close' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'priority' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NOT_CLASSIFIED,
				'range' => [
					CXmlDefine::NOT_CLASSIFIED => 'NOT_CLASSIFIED',
					CXmlDefine::INFO => 'INFO',
					CXmlDefine::WARNING => 'WARNING',
					CXmlDefine::AVERAGE => 'AVERAGE',
					CXmlDefine::HIGH => 'HIGH',
					CXmlDefine::DISASTER => 'DISASTER'
				]
			],
			'recovery_expression' => [
				'type' => CXmlDefine::STRING
			],
			'recovery_mode' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::TRIGGER_EXPRESSION,
				'range' => [
					CXmlDefine::TRIGGER_EXPRESSION => 'EXPRESSION',
					CXmlDefine::TRIGGER_RECOVERY_EXPRESSION => 'RECOVERY_EXPRESSION',
					CXmlDefine::TRIGGER_NONE => 'NONE'
				]
			],
			'status' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ENABLED,
				'range' => [
					CXmlDefine::ENABLED => 'ENABLED',
					CXmlDefine::DISABLED => 'DISABLED'
				]
			],
			'tags' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagTag)->getSchema()
			],
			'type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::SINGLE,
				'range' => [
					CXmlDefine::SINGLE => 'SINGLE',
					CXmlDefine::MULTIPLE => 'MULTIPLE'
				]
			],
			'url' => [
				'type' => CXmlDefine::STRING
			]
		];

		$this->schema = $schema;
	}
}
