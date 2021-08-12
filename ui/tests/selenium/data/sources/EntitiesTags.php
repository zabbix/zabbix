<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

class EntitiesTags {

	/**
	 * Create data for testFormTags tests.
	 *
	 * @return array
	 */
	public static function load() {
		// Create host groups.
		CDataHelper::call('hostgroup.create', [
			['name' => 'HostTags'],
			['name' => 'TemplateTags']
		]);

		$groupids = CDataHelper::getIds('name');

		$interface = [
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => '10050'
			]
		];

		// Create hosts.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for tags testing',
				'interfaces' => $interface,
				'groups' => [
					'groupid' => $groupids['HostTags']
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'a:',
						'value' => 'a'
					],
					// Common tag on host and template.
					[
						'tag' => 'action',
						'value' => 'simple'
					],
					[
						'tag' => 'tag',
						'value' => 'HOST'
					],
					[
						'tag' => 'host tag without value'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				],
				'items' => [
					[
						'name' => 'Host tag item',
						'key_' => 'trap.host',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Trapper discovery',
						'key_' => 'trap_discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => 'Host with tags for cloning',
				'interfaces' => $interface,
				'groups' => [
					'groupid' => $groupids['HostTags']
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'a:',
						'value' => 'a'
					],
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'tag',
						'value' => 'clone'
					],
					[
						'tag' => 'tag'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				],
				'items' => [
					[
						'name' => 'Item for trigger cloning',
						'key_' => 'tags.clone',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Trapper discovery for prototypes cloning',
						'key_' => 'trap_discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => 'Host with tags for updating',
				'interfaces' => $interface,
				'groups' => [
					'groupid' => $groupids['HostTags']
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag without value'
					],
					[
						'tag' => 'test',
						'value' => 'update'
					]
				]
			]
		]);

		// Create templates.
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template for tags testing',
				'groups' => [
					'groupid' => $groupids['TemplateTags']
				],
				'hosts' => [
					'hostid' => $hosts['hostids']['Host for tags testing']
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'simple'
					],
					[
						'tag' => 'tag',
						'value' => 'TEMPLATE'
					],
					[
						'tag' => 'templateTag without value'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				],
				'items' => [
					[
						'name' => 'Template item',
						'key_' => 'trap.template',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Template trapper discovery',
						'key_' => 'template_trap_discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => '1 template with tags for cloning',
				'groups' => [
					'groupid' => $groupids['TemplateTags']
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'tag',
						'value' => 'clone'
					],
					[
						'tag' => 'tag'
					]
				]
			],
			[
				'host' => '2 template with tags for updating',
				'groups' => [
					'groupid' => $groupids['TemplateTags']
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag without value'
					],
					[
						'tag' => 'test',
						'value' => 'update'
					]
				]
			]
		]);

		// Create host triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger with tags for updating',
				'expression' => '{Host for tags testing:trap.host.last()}=0',
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag without value'
					],
					[
						'tag' => 'test',
						'value' => 'update'
					]
				]
			],
			[
				'description' => 'Trigger with tags for cloning',
				'expression' => '{Host with tags for cloning:tags.clone.last()}=0',
				'tags' => [
					[
						'tag' => 'a',
						'value' => ':a'
					],
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'trigger tag without value'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				]
			],
			[
				'description' => 'Template trigger with tags for full cloning',
				'expression' => '{Template for tags testing:trap.template.last()}=0',
				'tags' => [
					[
						'tag' => 'a',
						'value' => ':a'
					],
					[
						'tag' => 'action',
						'value' => 'fullclone'
					],
					[
						'tag' => 'triggerTag without value'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				]
			]
		]);

		// Create item prototypes.
		CDataHelper::call('itemprototype.create', [
			[
				'hostid' => $hosts['hostids']['Host for tags testing'],
				'ruleid' => $hosts['discoveryruleids']['Host for tags testing:trap_discovery'],
				'name' => 'Item prototype: {#KEY}',
				'key_' => 'itemprototype_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			[
				'hostid' => $hosts['hostids']['Host with tags for cloning'],
				'ruleid' => $hosts['discoveryruleids']['Host with tags for cloning:trap_discovery'],
				'name' => 'Item prototype with tags for cloning: {#KEY}',
				'key_' => 'cloning_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			[
				'hostid' => $templates['templateids']['Template for tags testing'],
				'ruleid' => $templates['discoveryruleids']['Template for tags testing:template_trap_discovery'],
				'name' => 'Template item prototype: {#KEY}',
				'key_' => 'template.itemprototype_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			]
		]);

		// Create trigger prototypes.
		CDataHelper::call('triggerprototype.create', [
			[
				'description' => 'Trigger prototype with tags for cloning',
				'expression' => '{Host with tags for cloning:cloning_trap[{#KEY}].last()}=0',
				'tags' => [
					[
						'tag' => 'a',
						'value' => ':a'
					],
					[
						'tag' => 'action',
						'value' => 'clone'
					],
					[
						'tag' => 'action'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				]
			],
			[
				'description' => 'Trigger prototype with tags for updating',
				'expression' => '{Host for tags testing:itemprototype_trap[{#KEY}].last()}=0',
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'update'
					],
					[
						'tag' => 'tag without value'
					],
					[
						'tag' => 'test',
						'value' => 'update'
					]
				]
			],
			[
				'description' => 'Template trigger prototype with tags for full cloning',
				'expression' => '{Template for tags testing:template.itemprototype_trap[{#KEY}].last()}=0',
				'tags' => [
					[
						'tag' => 'a',
						'value' => ':a'
					],
					[
						'tag' => 'action',
						'value' => 'fullclone'
					],
					[
						'tag' => 'triggerTag without value'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				]
			]
		]);

		$result = array_merge_recursive($hosts, $templates);

		return $result;
	}
}
