<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		CDataHelper::setSessionId(null);

		// Create host groups.
		CDataHelper::call('hostgroup.create', [
			['name' => 'HostTags'],
			['name' => 'TemplateTags'],
			['name' => 'HostPrototypeTags']
		]);

		$groupids = CDataHelper::getIds('name');

		// Create templates.
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template for tags testing',
				'groups' => [
					'groupid' => $groupids['TemplateTags']
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
					],
					[
						'name' => 'Template item with tags for full cloning',
						'key_' => 'template.tags.clone',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
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
								'tag' => 'itemTag without value'
							],
							[
								'tag' => 'common tag on template and element',
								'value' => 'common value'
							]
						]
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
			],
			[
				'host' => '1 template for removing tags',
				'groups' => [
					'groupid' => $groupids['TemplateTags']
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			]
		]);

		// Create hosts.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for tags testing',
				'interfaces' => [],
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
				'templates' => [
					'templateid' => $templates['templateids']['Template for tags testing']
				],
				'items' => [
					[
						'name' => 'Host tag item',
						'key_' => 'trap.host',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item with tags for updating',
						'key_' => 'tags.update',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
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
						'name' => 'Item for tags removing',
						'key_' => 'tags.remove',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'action',
								'value' => 'remove'
							],
							[
								'tag' => 'tag',
								'value' => 'remove'
							],
							[
								'tag' => 'tag'
							]
						]
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
				'interfaces' => [],
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
						'name' => 'Item with tags for cloning',
						'key_' => 'tags.clone',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
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
				'interfaces' => [],
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
			],
			[
				'host' => 'Host for removing tags',
				'interfaces' => [],
				'groups' => [
					'groupid' => $groupids['HostTags']
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			]
		]);

		// Create host triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger with tags for updating',
				'expression' => 'last(/Host for tags testing/trap.host)=0',
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
				'description' => 'Trigger for tags removing',
				'expression' => 'last(/Host for tags testing/trap.host)=0',
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			],
			[
				'description' => 'Trigger with tags for cloning',
				'expression' => 'last(/Host with tags for cloning/tags.clone)=0',
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
				'expression' => 'last(/Template for tags testing/trap.template)=0',
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
				'hostid' => $hosts['hostids']['Host for tags testing'],
				'ruleid' => $hosts['discoveryruleids']['Host for tags testing:trap_discovery'],
				'name' => 'Item prototype with tags for updating: {#KEY}',
				'key_' => 'updating_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
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
				'hostid' => $hosts['hostids']['Host for tags testing'],
				'ruleid' => $hosts['discoveryruleids']['Host for tags testing:trap_discovery'],
				'name' => 'Item prototype for removig tags: {#KEY}',
				'key_' => 'remving.tags_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			],
			[
				'hostid' => $hosts['hostids']['Host with tags for cloning'],
				'ruleid' => $hosts['discoveryruleids']['Host with tags for cloning:trap_discovery'],
				'name' => 'Item prototype with tags for cloning: {#KEY}',
				'key_' => 'cloning_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
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
				'hostid' => $templates['templateids']['Template for tags testing'],
				'ruleid' => $templates['discoveryruleids']['Template for tags testing:template_trap_discovery'],
				'name' => 'Template item prototype: {#KEY}',
				'key_' => 'template.itemprototype_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			[
				'hostid' => $templates['templateids']['Template for tags testing'],
				'ruleid' => $templates['discoveryruleids']['Template for tags testing:template_trap_discovery'],
				'name' => 'Template item prototype with tags for full cloning: {#KEY}',
				'key_' => 'template.cloning_trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
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
						'tag' => 'action'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				]
			]
		]);

		// Create trigger prototypes.
		CDataHelper::call('triggerprototype.create', [
			[
				'description' => 'Trigger prototype with tags for cloning',
				'expression' => 'last(/Host with tags for cloning/cloning_trap[{#KEY}])=0',
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
				'expression' => 'last(/Host for tags testing/itemprototype_trap[{#KEY}])=0',
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
				'description' => 'Trigger prototype for removing tags',
				'expression' => 'count(/Host for tags testing/itemprototype_trap[{#KEY}],1)=0',
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
				'expression' => 'last(/Template for tags testing/template.itemprototype_trap[{#KEY}])=0',
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

		// Create host prototypes.
		CDataHelper::call('hostprototype.create', [
			[
				'ruleid' => $hosts['discoveryruleids']['Host with tags for cloning:trap_discovery'],
				'host' => '{#HOST} prototype with tags for cloning',
				'groupLinks' => [
					['groupid' => $groupids['HostPrototypeTags']]
				],
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
				]
			],
			[
				'ruleid' => $hosts['discoveryruleids']['Host for tags testing:trap_discovery'],
				'host' => '{#HOST} prototype with tags for updating',
				'groupLinks' => [
					['groupid' => $groupids['HostPrototypeTags']]
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
			],
			[
				'ruleid' => $hosts['discoveryruleids']['Host for tags testing:trap_discovery'],
				'host' => '{#HOST} prototype with for removing tags',
				'groupLinks' => [
					['groupid' => $groupids['HostPrototypeTags']]
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			],
			[
				'ruleid' => $templates['discoveryruleids']['Template for tags testing:template_trap_discovery'],
				'host' => '{#TEMPLATE} prototype with tags for full cloning',
				'groupLinks' => [
					['groupid' => $groupids['HostPrototypeTags']]
				],
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
						'tag' => 'action'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				]
			]
		]);

		// Create host web scenarios.
		CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario with tags for updating',
				'hostid' => $hosts['hostids']['Host for tags testing'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
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
			],
			[
				'name' => 'Web scenario with tags for cloning',
				'hostid' => $hosts['hostids']['Host with tags for cloning'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				],
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
						'tag' => 'web tag without value'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				]
			],
			[
				'name' => 'Web scenario for removing tags',
				'hostid' => $hosts['hostids']['Host for tags testing'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				],
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				]
			],
			[
				'name' => 'Template web scenario with tags for full cloning',
				'hostid' => $templates['templateids']['Template for tags testing'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				],
				'tags' => [
					[
						'tag' => 'a',
						'value' => 'a:'
					],
					[
						'tag' => 'action',
						'value' => 'fullclone'
					],
					[
						'tag' => 'webTag without value'
					],
					[
						'tag' => 'common tag on template and element',
						'value' => 'common value'
					]
				]
			]
		]);

		// Create servises.
		CDataHelper::call('service.create', [
			[
				'name' => 'Service with tags for updating',
				'algorithm' => 1,
				'sortorder' => 0,
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
				],
				'problem_tags' => [
					[
						'tag' => 'problem action',
						'operator' => 2,
						'value' => 'problem update'
					],
					[
						'tag' => 'problem tag without value'
					],
					[
						'tag' => 'problem test',
						'operator' => 0,
						'value' => 'problem update'
					]
				]
			],
			[
				'name' => 'Service with tags for cloning',
				'algorithm' => 1,
				'sortorder' => 0,
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
						'tag' => 'tag without value'
					],
					[
						'tag' => 'common tag on host and element',
						'value' => 'common value'
					]
				],
				'problem_tags' => [
					[
						'tag' => 'problem a',
						'operator' => 2,
						'value' => ':problem a'
					],
					[
						'tag' => 'problem action',
						'operator' => 0,
						'value' => 'problem clone'
					],
					[
						'tag' => 'problem tag without value',
						'operator' => 2
					],
					[
						'tag' => 'problem common tag on host and element',
						'operator' => 0,
						'value' => 'problem common value'
					]
				]
			],
			[
				'name' => 'Service for removing tags',
				'algorithm' => 1,
				'sortorder' => 0,
				'tags' => [
					[
						'tag' => 'action',
						'value' => 'remove'
					],
					[
						'tag' => 'tag',
						'value' => 'remove'
					],
					[
						'tag' => 'tag'
					]
				],
				'problem_tags' => [
					[
						'tag' => 'problem remove',
						'operator' => 0,
						'value' => 'problem remove'
					],
					[
						'tag' => 'problem tag',
						'operator' => 2,
						'value' => 'problem remove'
					],
					[
						'tag' => 'problem tag',
						'operator' => 2
					]
				]
			]
		]);

		$result = array_merge_recursive($hosts, $templates);

		return $result;
	}
}
