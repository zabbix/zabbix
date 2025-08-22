<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareLLDPrototypeData
 */
class testPageLowLevelDiscoveryPrototypes extends testPagePrototypes {
	public $source = 'discovery';

	const COMMON_URL = 'host_discovery_prototypes.php?parent_discoveryid=';
	const HOST_NAME = 'Host for prototype check';
	const TEMPLATE_NAME = 'Template for prototype check';
	const SOURCE_TEMPLATE = 'Linked template for LLD prototypes test';
	const ROOT_LLD_NAME = 'Drule for prototype check';
	const MASTER_ITEM_NAME = 'Master item prototype: {#KEY}';
	const PROTOTYPE_NAME_FOR_DISCOVERY = '{#KEY} prototype';

	protected static $ids;

	public static function prepareLLDPrototypeData() {
		$link_template_ids = CDataHelper::createTemplates([
			[
				'host' => self::SOURCE_TEMPLATE,
				'groups' => [
					'groupid' => 1 // Templates
				],
				'discoveryrules' => [
					[
						'name' => self::ROOT_LLD_NAME,
						'key_' => 'root_lld',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);

		self::$ids['linked_templateid'] = $link_template_ids['templateids'][self::SOURCE_TEMPLATE];
		self::$ids['linked_template_ruleid'] = $link_template_ids['discoveryruleids'][self::SOURCE_TEMPLATE.':root_lld'];

		CDataHelper::call('discoveryruleprototype.create', [
			[
				'hostid' => self::$ids['linked_templateid'],
				'ruleid' => self::$ids['linked_template_ruleid'],
				'name' => '{#KEY} inherited LLD prototype',
				'key_' => 'inherited_prototype[{#KEY}]',
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => '5s'
			]
		]);

		// Create item prototype for LLD prototypes.
		CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::$ids['linked_templateid'],
				'ruleid' => self::$ids['linked_template_ruleid'],
				'name' => self::MASTER_ITEM_NAME,
				'key_' => 'master.itemprototype[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			]
		]);

		$hostid = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'interfaces' => [
					'type'=> INTERFACE_TYPE_AGENT,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				],
				'templates' => [['templateid' => self::$ids['linked_templateid']]]
			]
		]);
		self::$ids['host'] = $hostid['hostids'][self::HOST_NAME];

		$templateid = CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE_NAME,
				'groups' => [
					'groupid' => 1 // Templates
				],
				'templates' => [['templateid' => self::$ids['linked_templateid']]]
			]
		]);
		self::$ids['template'] = $templateid['templateids'][self::TEMPLATE_NAME];

		self::$ids['parent_lldid'] = [];

		foreach (['host' => self::HOST_NAME, 'template' => self::TEMPLATE_NAME] as $context => $context_name) {
			self::$ids['parent_lldid'][$context] = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.
					zbx_dbstr(self::ROOT_LLD_NAME).' AND hostid='.self::$ids[$context]
			);
			self::$ids['master_item_id'][$context] = CDBHelper::getValue('SELECT itemid FROM items WHERE name ='.zbx_dbstr(self::MASTER_ITEM_NAME).
					' AND hostid='.self::$ids[$context]
			);

			// Create LLD rule prototypes
			self::$ids['lldid_for_prototypes'][$context] = CDataHelper::call('discoveryruleprototype.create', [
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => self::PROTOTYPE_WITH_PROTOTYPES,
					'key_' => 'with_prototypes[{#KEY}]',
					'type' => ITEM_TYPE_NESTED
				]
			])['itemids'][0];

			CDataHelper::call('discoveryruleprototype.create', [
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => '123 Disabled LLD prototype with interval',
					'key_' => '123_disabled_with_interval[{#KEY}]',
					'type' => ITEM_TYPE_INTERNAL,
					'delay' => '333s',
					'status' => ZBX_PROTOTYPE_STATUS_DISABLED
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => 'The LLD prototype with Discover set to No',
					'key_' => 'the_disabled_prototype_with[{#KEY}]',
					'type' => ITEM_TYPE_SIMPLE,
					'delay' => '20m',
					'username' => 'Admin',
					'password' => 'zabbix',
					'discover' => ZBX_PROTOTYPE_NO_DISCOVER
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => '4 Disabled LLD prototype with Discover set to No',
					'key_' => '4_disabled_with_no_discover[{#KEY}]',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '1h',
					'status' => ZBX_PROTOTYPE_STATUS_DISABLED,
					'discover' => ZBX_PROTOTYPE_NO_DISCOVER
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => '12a2 Dependent LLD prototype',
					'key_' => '12a2_dependent[{#KEY}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => self::$ids['master_item_id'][$context]
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['parent_lldid'][$context],
					'name' => '🙃 良い一日を zēma līmeņa atklājuma prototips',
					'key_' => 'the.symbols.key[{#KEY}]',
					'type' => ITEM_TYPE_SCRIPT,
					'params' => 'return "Have a nice day!";',
					'delay' => '59m'
				]
			]);

			/**
			 * Retrieve IDs of LLD prototypes under LLD rule "Drule for prototype check" for verification of prototype
			 * deletion in the Delete scenario.
			 * Inherited and dependent prototypes have template and master item names as prefixes. Therefore, the
			 * prototype names, that are used as keys in the nested array with prototype ids, should be changed accordingly.
			 */
			$prototypeids_sql = CDBHelper::getAll('SELECT name, itemid FROM items WHERE flags=3 AND itemid IN (SELECT '.
					'itemid FROM item_discovery WHERE lldruleid='.self::$ids['parent_lldid'][$context].')'
			);

			$name_prefixes = [
				'{#KEY} inherited LLD prototype' => self::SOURCE_TEMPLATE,
				'12a2 Dependent LLD prototype' => self::MASTER_ITEM_NAME
			];
			foreach ($prototypeids_sql as $prototype_info) {
				// Add template name or master item name to prototype name as prefix for inherited and dependent ptorotypes.
				$prototype_name = (in_array($prototype_info['name'], array_keys($name_prefixes)))
					? $name_prefixes[$prototype_info['name']].': '.$prototype_info['name']
					: $prototype_info['name'];

				self::$ids['child_lldids'][$context][$prototype_name] = $prototype_info['itemid'];
			}

			self::$entity_count = count($prototypeids_sql);

			// Create item prototype for LLD prototypes.
			$itemprototype_ids = CDataHelper::call('itemprototype.create', [
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['lldid_for_prototypes'][$context],
					'name' => 'Item prototype: {#KEY}',
					'key_' => 'itemprototype[{#KEY}]',
					'type' => ITEM_TYPE_TRAPPER,
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['lldid_for_prototypes'][$context],
					'name' => '2nd item prototype: {#KEY}',
					'key_' => '2nd_itemprototype[{#KEY}]',
					'type' => ITEM_TYPE_TRAPPER,
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['lldid_for_prototypes'][$context],
					'name' => '3rd item prototype: {#KEY}',
					'key_' => '3rd_itemprototype[{#KEY}]',
					'type' => ITEM_TYPE_TRAPPER,
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				]
			]);

			// Create trigger prototypes.
			CDataHelper::call('triggerprototype.create', [
				[
					'description' => '1st Trigger prototype',
					'expression' => 'last(/'.$context_name.'/itemprototype[{#KEY}])=0'
				],
				[
					'description' => '2nd Trigger prototype',
					'expression' => 'last(/'.$context_name.'/2nd_itemprototype[{#KEY}])=0'
				],
				[
					'description' => '3rd Trigger prototype',
					'expression' => 'last(/'.$context_name.'/3rd_itemprototype[{#KEY}])=0'
				],
				[
					'description' => '4th Trigger prototype',
					'expression' => 'last(/'.$context_name.'/itemprototype[{#KEY}])=10'
				]
			]);

			// Create graph prototypes.
			CDataHelper::call('graphprototype.create', [
				[
					'name' => 'Graph prototype 1 {#KEY}',
					'width' => 900,
					'height' => 200,
					'gitems' => [
						[
							'itemid' => $itemprototype_ids['itemids'][0],
							'color' => 'BF00FF'
						]
					]
				],
				[
					'name' => 'Graph prototype 2 {#KEY}',
					'width' => 666,
					'height' => 333,
					'gitems' => [
						[
							'itemid' => $itemprototype_ids['itemids'][1],
							'color' => 'FFFFFF'
						]
					]
				]
			]);

			// Create host prototype.
			CDataHelper::call('hostprototype.create', [
				[
					'ruleid' => self::$ids['lldid_for_prototypes'][$context],
					'host' => '{#HOST} prototype for LLD prototype test',
					'groupLinks' => [
						['groupid' => 4] // Zabbix servers
					]
				]
			]);

			// Create an LLD rule prototype under LLD rule prototype, to create a discovered LLD rule prototype afterwards.
			self::$ids['protorypeid_for_prototype_discovery'][$context] = CDataHelper::call('discoveryruleprototype.create', [
				[
					'hostid' => self::$ids[$context],
					'ruleid' => self::$ids['lldid_for_prototypes'][$context],
					'name' => self::PROTOTYPE_NAME_FOR_DISCOVERY,
					'key_' => 'discovered_prototype[{#KEY}, {#KEY2}]',
					'type' => ITEM_TYPE_NESTED
				]
			])['itemids'][0];

			if ($context === 'host') {
				// Create discovered LLD and LLD prototype.
				$prototype_for_discovery_id = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.
						zbx_dbstr(self::PROTOTYPE_WITH_PROTOTYPES).' AND hostid='.self::$ids[$context]
				);

				$discovered_lld_response = CDataHelper::call('discoveryrule.create', [
					'name' => self::PROTOTYPE_WITH_PROTOTYPES,
					'key_' => 'with_prototypes[Discovered]',
					'hostid' => self::$ids[$context],
					'type' => ITEM_TYPE_TRAPPER
				]);

				$discovered_prototype_response = CDataHelper::call('discoveryruleprototype.create', [
					[
						'hostid' => self::$ids[$context],
						'ruleid' => $discovered_lld_response['itemids'][0],
						'name' => 'Discovered prototype',
						'key_' => 'discovered_prototype[Discovered, {#KEY2}]',
						'type' => ITEM_TYPE_NESTED
					]
				]);

				// Make previously created LLD rule a discovered LLD rule.
				DBExecute('UPDATE items SET flags=5 WHERE itemid='.$discovered_lld_response['itemids'][0]);
				DBexecute('INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values ('.
						$discovered_lld_response['itemids'][0].', '.$discovered_lld_response['itemids'][0].
						', '.$prototype_for_discovery_id.');'
				);

				// Make previously created LLD rule prototype a discovered LLD rule prototype.
				DBExecute('UPDATE items SET flags=7 WHERE itemid='.$discovered_prototype_response['itemids'][0]);
				DBExecute('UPDATE item_discovery SET parent_itemid='.self::$ids['protorypeid_for_prototype_discovery'][$context].
						' WHERE itemid='.$discovered_prototype_response['itemids'][0]
				);

				self::$ids['discovered_parent_lldid'][$context] = $discovered_lld_response['itemids'][0];
			}
		}
	}

	public function testPageLowLevelDiscoveryPrototypes_CheckLayoutHost() {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['host'].'&context=host')->waitUntilReady();
		$this->checkLayout();
	}

	public function testPageLowLevelDiscoveryPrototypes_CheckLayoutTemplate() {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['template'].'&context=template')
				->waitUntilReady();
		$this->checkLayout(true);
	}

	/**
	 * @dataProvider getDiscoveryPrototypesSortingData
	 */
	public function testPageLowLevelDiscoveryPrototypes_SortingHost($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['host'].'&context=host&sort='.
				$data['sort'].'&sortorder=ASC'
		)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * @dataProvider getDiscoveryPrototypesSortingData
	 */
	public function testPageLowLevelDiscoveryPrototypes_SortingTemplate($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['template'].'&context=template&sort='.
				$data['sort'].'&sortorder=ASC'
		)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * @dataProvider getDiscoveryPrototypesButtonLinkData
	 */
	public function testPageLowLevelDiscoveryPrototypes_ButtonLinkHost($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['host'].'&context=host')->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * @dataProvider getDiscoveryPrototypesButtonLinkData
	 */
	public function testPageLowLevelDiscoveryPrototypes_ButtonLinkTemplate($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['template'].'&context=template')
				->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Discovery rule prototype prefixes data.
	 */
	public static function getPrototypePrefixesData() {
		return [
			// #0 Check master item prefix on dependent lld rul prototype on host.
			[
				[
					'name' => '12a2 Dependent LLD prototype',
					'parent_key' => 'parent_lldid',
					'prefix_type' => 'master_item',
					'context' => 'host'
				]
			],
			// #1 Check master item prefix on dependent lld rul prototype on template.
			[
				[
					'name' => '12a2 Dependent LLD prototype',
					'parent_key' => 'parent_lldid',
					'prefix_type' => 'master_item',
					'context' => 'template'
				]
			],
			// #2 Check template prefix for inherited lld rule prototype on host.
			[
				[
					'name' => '{#KEY} inherited LLD prototype',
					'parent_key' => 'parent_lldid',
					'prefix_type' => 'template',
					'context' => 'host'
				]
			],
			// #3 Check template prefix for inherited lld rule prototype on template.
			[
				[
					'name' => '{#KEY} inherited LLD prototype',
					'parent_key' => 'parent_lldid',
					'prefix_type' => 'template',
					'context' => 'template'
				]
			],
			/**
			 * #4 Check parent LLD rule prefix for discovered lld rule prototype on host. Performed only on host since
			 * in real life it is not possible to discover anything on template.
			 */
			[
				[
					'name' => 'Discovered prototype',
					'parent_key' => 'discovered_parent_lldid',
					'prefix_type' => 'parent_lld_prototype',
					'context' => 'host'
				]
			]
		];
	}

	/**
	 * Check the link, the color (class) and the actual URL where the link prefixes (parent template, master item and parent
	 * discovery prototype) lead in discovery prototype names.
	 *
	 * @dataProvider getPrototypePrefixesData
	 *
	 * @ignoreBrowserErrors
	 * TODO: Remove the above annotation when DEV-4233 is fixed.
	 */
	public function testPageLowLevelDiscoveryPrototypes_CheckPrefixes($data) {
		$prefixes = [
			'template' => [
				'name' => self::SOURCE_TEMPLATE,
				'url' => 'host_discovery_prototypes.php?parent_discoveryid='.self::$ids['linked_template_ruleid'].
						'&context=template',
				'class' => 'grey'
			],
			'master_item' => [
				'name' => self::MASTER_ITEM_NAME,
				'url' => 'zabbix.php?action=popup&popup=item.prototype.edit&context='.$data['context'].'&parent_discoveryid='.
						self::$ids['parent_lldid'][$data['context']].'&itemid='.self::$ids['master_item_id'][$data['context']],
				'class' => 'teal'
			],
			'parent_lld_prototype' => [
				'name' => self::PROTOTYPE_WITH_PROTOTYPES,
				'url' => 'host_discovery_prototypes.php?form=update&parent_discoveryid='.
						self::$ids['lldid_for_prototypes'][$data['context']].'&itemid='.
						self::$ids['protorypeid_for_prototype_discovery'][$data['context']].'&context='.$data['context'],
				'class' => 'orange',
				'prototype_name' => self::PROTOTYPE_NAME_FOR_DISCOVERY
			]
		];

		$expected_prefix = $prefixes[$data['prefix_type']];

		$this->page->login()->open(self::COMMON_URL.self::$ids[$data['parent_key']][$data['context']].'&context='.
				$data['context']
		)->waitUntilReady();

		$name = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'], true)->getColumn('Name');
		$this->assertEquals($expected_prefix['name'].': '.$data['name'], $name->getText());

		$prefix_link = $name->query('link', $expected_prefix['name'])->one();
		$this->assertTrue($prefix_link->hasClass($expected_prefix['class']));
		$this->assertEquals($expected_prefix['url'], $prefix_link->getAttribute('href'));

		$prefix_link->click();

		switch ($data['prefix_type']) {
			case 'template':
				$this->page->waitUntilReady();
				$this->assertStringContainsString($expected_prefix['url'], $this->page->getCurrentUrl());
				$this->assertTrue($this->query('xpath://ul[@class="breadcrumbs"]//span[@title='.
						CXPathHelper::escapeQuotes($expected_prefix['name']).']')->waitUntilVisible()->one()->isValid()
				);
				break;

			case 'master_item':
				$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$this->assertEquals($expected_prefix['name'], $dialog->asForm()->getField('Name')->getValue());
				$dialog->close();
				break;

			case 'parent_lld_prototype':
				$this->page->waitUntilReady();
				$this->assertStringContainsString($expected_prefix['url'], $this->page->getCurrentUrl());
				$this->assertEquals($expected_prefix['prototype_name'], $this->query('id:name')->one()->getValue());
				break;

		}
	}

	/**
	 * Navigation through LLD related part of breadcrumbs.
	 */
	public static function getDiscoveryPrototypeNavigationData() {
		return [
			// #0 Navigate to discovery list on host.
			[
				[
					'link' => 'Discovery list',
					'context' => 'host'
				]
			],
			// #1 Navigate to discovery list on template.
			[
				[
					'link' => 'Discovery list',
					'context' => 'template'
				]
			],
			// #2 Navigate to parent LLD configuration on host.
			[
				[
					'link' => self::PROTOTYPE_NAME_FOR_DISCOVERY,
					'context' => 'host'
				]
			],
			// #3 Navigate to parent LLD configuration on template.
			[
				[
					'link' => self::PROTOTYPE_NAME_FOR_DISCOVERY,
					'context' => 'template'
				]
			],
			// #4 Navigate to ancestor LLD configuration on host.
			[
				[
					'link' => self::PROTOTYPE_WITH_PROTOTYPES,
					'context' => 'host',
					'hidden' => true
				]
			],
			// #5 Navigate to ancestor LLD configuration on template.
			[
				[
					'link' => self::PROTOTYPE_WITH_PROTOTYPES,
					'context' => 'template',
					'hidden' => true
				]
			],
			// #6 Navigate to the list of prototypes of the parent LLD rule on host.
			[
				[
					'link' => self::ROOT_LLD_NAME,
					'context' => 'host',
					'hidden' => true
				]
			],
			// #7 Navigate to the list of prototypes of the parent LLD rule on template.
			[
				[
					'link' => self::ROOT_LLD_NAME,
					'context' => 'template',
					'hidden' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getDiscoveryPrototypeNavigationData
	 */
	public function testPageLowLevelDiscoveryPrototypes_Navigation($data) {
		$urls = [
			'Discovery list' => 'host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$ids[$data['context']].
					'&context='.$data['context'],
			self::PROTOTYPE_NAME_FOR_DISCOVERY => 'host_discovery_prototypes.php?form=update&itemid='.
					self::$ids['protorypeid_for_prototype_discovery'][$data['context']].'&parent_discoveryid='.
					self::$ids['lldid_for_prototypes'][$data['context']].'&context='.$data['context'],
			self::PROTOTYPE_WITH_PROTOTYPES => self::COMMON_URL.self::$ids['lldid_for_prototypes'][$data['context']].
					'&context='.$data['context'],
			self::ROOT_LLD_NAME => self::COMMON_URL.self::$ids['parent_lldid'][$data['context']].'&context='.$data['context']
		];

		$this->page->login()->open(self::COMMON_URL.self::$ids['protorypeid_for_prototype_discovery'][$data['context']].
				'&context='.$data['context']
		)->waitUntilReady();

		$lld_navigation = $this->query('xpath:(//ul[@class="breadcrumbs"])[2]')->waitUntilVisible()->one();

		if (CTestArrayHelper::get($data, 'hidden')) {
			$lld_navigation->query('class:btn-icon')->one()->click();
			$popup = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilVisible()->one();
			$popup->query('link', $data['link'])->waitUntilClickable()->one()->click();
		}
		else {
			$lld_navigation->query('link', $data['link'])->waitUntilClickable()->one()->click();
		}

		$this->page->waitUntilReady();

		// Check that the user was redirected to correct URL and that there are no error messages on the page.
		$this->assertStringContainsString($urls[$data['link']], $this->page->getCurrentUrl());
		$this->assertFalse($this->query('class:msg-bad')->one(false)->isValid());
	}

	/**
	 * @dataProvider getDiscoveryPrototypesDeleteData
	 */
	public function testPageLowLevelDiscoveryPrototypes_DeleteHost($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['host'].'&context=host&sort=name&sortorder=DESC')
				->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$ids['child_lldids']['host'][$name];
		}

		$this->checkDelete($data, $ids);
	}

	/**
	 * @dataProvider getDiscoveryPrototypesDeleteData
	 */
	public function testPageLowLevelDiscoveryPrototypes_DeleteTemplate($data) {
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['template'].'&context=template&sort=name&sortorder=DESC')
				->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$ids['child_lldids']['template'][$name];
		}

		$this->checkDelete($data, $ids);
	}
}
