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


class CXmlTagScreenItem extends CXmlTagAbstract
{
	protected $tag = 'screen_items';

	protected $data_sort = ['y', 'x'];

	public function __construct(array $schema = [])
	{
		$schema += [
			'x' => [
				'type' => CXmlDefine::STRING
			],
			'y' => [
				'type' => CXmlDefine::STRING
			],
			'application' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagApplication)->getSchema()
			],
			'colspan' => [
				'type' => CXmlDefine::STRING
			],
			'dynamic' => [
				'type' => CXmlDefine::STRING
			],
			'elements' => [
				'type' => CXmlDefine::STRING
			],
			'halign' => [
				'type' => CXmlDefine::STRING
			],
			'height' => [
				'type' => CXmlDefine::STRING
			],
			'max_columns' => [
				'type' => CXmlDefine::STRING
			],
			'resource' => [
				'key' => 'resourceid',
				'type' => CXmlDefine::STRING
			],
			'resourcetype' => [
				'type' => CXmlDefine::STRING
			],
			'rowspan' => [
				'type' => CXmlDefine::STRING
			],
			'sort_triggers' => [
				'type' => CXmlDefine::STRING
			],
			'style' => [
				'type' => CXmlDefine::STRING
			],
			'url' => [
				'type' => CXmlDefine::STRING
			],
			'valign' => [
				'type' => CXmlDefine::STRING
			],
			'width' => [
				'type' => CXmlDefine::STRING
			]
		];

		$this->schema = $schema;
	}
}
