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


class CXmlTagDiscoveryRule extends CXmlTagHostItem
{
	protected $tag = 'discovery_rules';

	public function __construct(array $schema = [])
	{
		parent::__construct();

		$schema += [
			'application_prototypes' => [
				'key' => 'applicationPrototypes',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagApplication)->getSchema()
			],
			'condition' => [
				'type' => CXmlDefine::INDEXED_ARRAY,
				'schema' => (new CXmlTagCondition)->getSchema()
			],
			'filter' => [
				'type' => CXmlDefine::INDEXED_ARRAY,
				'schema' => (new CXmlTagFilter)->getSchema()
			],
			'graph_prototypes' => [
				'key' => 'graphPrototypes',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagGraph)->getSchema()
			],
			// 'host_prototypes' => [
			// 	'key' => 'hostPrototypes',
			// 	'type' => CXmlDefine::ARRAY,
			// 	'schema' => (new CXmlTagHostPrototype)->getSchema()
			// ],
			'item_prototypes' => [
				'key' => 'itemPrototypes',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagDItem)->getSchema()
			],
			'lifetime' => [
				'type' => CXmlDefine::STRING,
				'value' => '30d'
			],
			'lld_macro_paths' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagLldMacroPaths)->getSchema()
			],
			'master_item' => [
				'type' => CXmlDefine::INDEXED_ARRAY,
				'schema' => (new CXmlTagMasterItem)->getSchema()
			],
			'preprocessings' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagPreprocessing)->getSchema()
			],
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

		CArrayHelper::sort($data, ['key_']);

		$simple_trigger_prototypes = [];

		foreach ($data as &$discoveryRule) {
			foreach ($discoveryRule['triggerPrototypes'] as $i => $trigger_prototype) {
				if (count($trigger_prototype['items']) == 1) {
					$simple_trigger_prototypes[] = $trigger_prototype;
					unset($discoveryRule['triggerPrototypes'][$i]);
				}
			}

			if ($discoveryRule['itemPrototypes']) {
				$discoveryRule['itemPrototypes'] = (new CXmlTagItem)->prepareData($discoveryRule['itemPrototypes'], $simple_trigger_prototypes);
			}

			if ($discoveryRule['triggerPrototypes']) {
				$discoveryRule['triggerPrototypes'] = (new CXmlTagTrigger)->prepareData($discoveryRule['triggerPrototypes']);
			}

			if ($discoveryRule['graphPrototypes']) {
				$discoveryRule['graphPrototypes'] = (new CXmlTagGraph)->prepareData($discoveryRule['graphPrototypes']);
			}

			if ($discoveryRule['query_fields']) {
				$discoveryRule['query_fields'] = (new CXmlTagQueryField)->prepareData($discoveryRule['query_fields']);
			}

			if ($discoveryRule['headers']) {
				$discoveryRule['headers'] = (new CXmlTagHeader)->prepareData($discoveryRule['headers']);
			}

			if (array_key_exists('master_item', $discoveryRule)) {
				$discoveryRule['master_item'] = ($discoveryRule['type'] == CXmlDefine::ITEM_TYPE_DEPENDENT)
					? ['key' => $discoveryRule['master_item']['key_']]
					: [];
			}
		}

		return $data;
	}
}
