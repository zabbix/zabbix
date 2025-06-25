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


require_once __DIR__.'/../common/testLowLevelDiscoveryPrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareLLDPrototypeData
 */
class testPageLowLevelDiscoveryPrototypes extends testLowLevelDiscoveryPrototypes {
	public $source = 'discovery';

	const COMMON_URL = 'host_discovery_prototypes.php?parent_discoveryid=';

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
	 * Discovery rule prototype delete.
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

		$table = $this->query('class:list-table')->asTable()->one();
		$name = $table->findRow('Name', $data['name'], true)->getColumn('Name');
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
			// #6 Navigate to the list of prototypes of the parent LLD tule on host.
			[
				[
					'link' => self::ROOT_LLD_NAME,
					'context' => 'host',
					'hidden' => true
				]
			],
			// #7 Navigate to the list of prototypes of the parent LLD tule on template.
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
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['host'].'&context=host')->waitUntilReady();

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
		$this->page->login()->open(self::COMMON_URL.self::$ids['parent_lldid']['template'].'&context=template')
				->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$ids['child_lldids']['template'][$name];
		}

		$this->checkDelete($data, $ids);
	}
}
