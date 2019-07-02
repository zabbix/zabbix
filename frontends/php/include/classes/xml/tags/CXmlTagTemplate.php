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


class CXmlTagTemplate extends CXmlTagAbstract
{
	protected $tag = 'templates';

	public function __construct(array $schema = [])
	{
		$schema += [
			'template' => [
				'key' => 'host',
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'description' => [
				'type' => CXmlDefine::STRING
			],
			'name' => [
				'type' => CXmlDefine::STRING
			],
			'applications' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagApplication)->getSchema()
			],
			'discovery_rules' => [
				'key' => 'discoveryRules',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagDiscoveryRule)->getSchema()
			],
			'groups' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagGroup)->getSchema()
			],
			'httptests' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagHttptest)->getSchema()
			],
			'items' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagItem)->getSchema()
			],
			'macros' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagMacro)->getSchema()
			],
			'screens' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagScreen)->getSchema()
			],
			'tags' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagTag)->getSchema()
			],
			'templates' => [
				'key' => 'parentTemplates',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagLinkedTemplate)->getSchema()
			]
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		CArrayHelper::sort($data, ['host']);

		foreach ($data as &$template) {
			if ($template['applications']) {
				$template['applications'] = (new CXmlTagApplication)->prepareData($template['applications']);
			}
			if ($template['discoveryRules']) {
				$template['discoveryRules'] = (new CXmlTagDiscoveryRule)->prepareData($template['discoveryRules']);
			}
			if ($template['groups']) {
				$template['groups'] = (new CXmlTagGroup)->prepareData($template['groups']);
			}
			if ($template['httptests']) {
				$template['httptests'] = (new CXmlTagHttptest)->prepareData($template['httptests']);
			}
			if ($template['items']) {
				$template['items'] = (new CXmlTagItem)->prepareData($template['items']);
			}
			if ($template['macros']) {
				$template['macros'] = (new CXmlTagMacro)->prepareData($template['macros']);
			}
			if ($template['screens']) {
				$template['screens'] = (new CXmlTagScreen)->prepareData($template['screens']);
			}
			if ($template['tags']) {
				$template['tags'] = (new CXmlTagTag)->prepareData($template['tags']);
			}
			if ($template['parentTemplates']) {
				$template['parentTemplates'] = (new CXmlTagLinkedTemplate)->prepareData($template['parentTemplates']);
			}
		}

		return $data;
	}
}
