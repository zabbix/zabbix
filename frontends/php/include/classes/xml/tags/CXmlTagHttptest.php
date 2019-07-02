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


class CXmlTagHttptest extends CXmlTagAbstract
{
	protected $tag = 'httptests';

	public function __construct(array $schema = [])
	{
		$schema += [
			'name' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'steps' => [
				'type' => CXmlDefine::ARRAY | CXmlDefine::REQUIRED,
				'schema' => (new CXmlTagStep)->getSchema()
			],
			'agent' => [
				'type' => CXmlDefine::STRING,
				'value' => 'Zabbix'
			],
			'application' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CxmlTagApplication)->getSchema()
			],
			'attempts' => [
				'key' => 'retries',
				'type' => CXmlDefine::STRING,
				'value' => 1
			],
			'authentication' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NONE,
				'range' => [
					CXmlDefine::NONE => 'NONE',
					CXmlDefine::BASIC => 'BASIC',
					CXmlDefine::NTLM => 'NTLM'
				]
			],
			'delay' => [
				'type' => CXmlDefine::STRING,
				'value' => '1m'
			],
			'headers' => [
				'type' => CXmlDefine::STRING
			],
			'http_password' => [
				'type' => CXmlDefine::STRING
			],
			'http_proxy' => [
				'type' => CXmlDefine::STRING
			],
			'http_user' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_cert_file' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_key_file' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_key_password' => [
				'type' => CXmlDefine::STRING
			],
			'status' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ENABLED,
				'range' => [
					CXmlDefine::ENABLED => 'ENABLED',
					CXmlDefine::DISABLED => 'DISABLED'
				]
			],
			'variables' => [
				'type' => CXmlDefine::STRING
			],
			'verify_host' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'verify_peer' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			]
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		order_result($data, 'name');

		foreach ($data as &$http) {
			$http['step'] = (new CXmlTagStep)->prepareData($http['step']);
		}

		return $data;
	}
}
