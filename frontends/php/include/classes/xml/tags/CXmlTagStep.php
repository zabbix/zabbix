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


class CXmlTagStep extends CXmlTagAbstract
{
	protected $tag = 'steps';

	public function __construct(array $schema = [])
	{
		$schema += [
			'name' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'url' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'follow_redirects' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'headers' => [
				'type' => CXmlDefine::STRING
			],
			'posts' => [
				'type' => CXmlDefine::STRING
			],
			'query_fields' => [
				'type' => CXmlDefine::STRING
			],
			'required' => [
				'type' => CXmlDefine::STRING
			],
			'retrieve_mode' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::BODY,
				'range' => [
					CXmlDefine::BODY => 'BODY',
					CXmlDefine::HEADERS => 'HEADERS',
					CXmlDefine::BOTH => 'BOTH'
				]
			],
			'status_codes' => [
				'type' => CXmlDefine::STRING
			],
			'timeout' => [
				'type' => CXmlDefine::STRING,
				'value' => '15s'
			],
			'variables' => [
				'type' => CXmlDefine::STRING
			],
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		order_result($data, 'no');

		return $data;
	}
}
