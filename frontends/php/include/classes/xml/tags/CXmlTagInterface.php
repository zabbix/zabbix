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


class CXmlTagInterface extends CXmlTagAbstract
{
	protected $tag = 'interfaces';

	protected $data_sort = ['type', 'ip', 'dns', 'port'];

	public function __construct(array $schema = [])
	{
		$schema += [
			'interface_ref' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'bulk' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'default' => [
				'key' => 'main',
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'dns' => [
				'type' => CXmlDefine::STRING
			],
			'ip' => [
				'type' => CXmlDefine::STRING,
				'value' => '127.0.0.1'
			],
			'port' => [
				'type' => CXmlDefine::STRING,
				'value' => '10050'
			],
			'type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ZABBIX,
				'range' => [
					CXmlDefine::ZABBIX => 'ZABBIX',
					CXmlDefine::SNMP => 'SNMP',
					CXmlDefine::IPMI => 'IPMI',
					CXmlDefine::JMX => 'JMX'
				]
			],
			'useip' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			]
		];

		$this->schema = $schema;
	}
}
