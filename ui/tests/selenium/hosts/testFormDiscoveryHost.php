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

require_once dirname(__FILE__).'/../common/testFormHost.php';

/**
 * @backup hosts
 *
 * @onBefore createTemplate
 */
class testFormDiscoveryHost extends testFormHost {

	protected static $host = 'Host prototype LLD';
	protected static $template_names;
	protected static $templateids;
	protected static $hostid;

	public static function createTemplate() {
		self::$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr(self::$host));

		// Create templates.
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template for link testing',
				'groups' => [
					'groupid' => 4
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
						'name' => 'Template item with tags',
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
				'host' => '1 template for unlink',
				'groups' => [
					'groupid' => 4
				],
				'items' => [
					[
						'name' => 'Template1 item1',
						'key_' => 'trap.template1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Template1 item2',
						'key_' => 'template.item1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
					]
				]
			],
			[
				'host' => '2 template for clear',
				'groups' => [
					'groupid' => 4
				],
				'items' => [
					[
						'name' => 'Template2 item1',
						'key_' => 'trap.template2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Template2 item2',
						'key_' => 'template.item2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
					]
				]
			]
		]);

		foreach ($templates['templateids'] as $name => $value) {
			self::$template_names[] = $name;
			self::$templateids[] = $value;
		}
	}

	public static function getState() {
		return [
			[
				[
					'standalone' => true,
					'url' => 'zabbix.php?action=host.edit&hostid=',
					'monitoring' => false
				]
			],
			[
				[
					'standalone' => false,
					'url' => 'zabbix.php?action=host.list',
					'monitoring' => false
				]
			],
			[
				[
					'standalone' => false,
					'url' => 'zabbix.php?action=host.view',
					'monitoring' => true
				]
			]
		];
	}

	/**
	 * Test to check LLD Host with templates.
	 *
	 * @dataProvider getState
	 */
	public function testFormDiscoveryHost_FormLayout($data) {
		$this->standalone = $data['standalone'];
		$this->link = $data['url'];
		$this->monitoring = $data['monitoring'];

		// Link templates.
		CDataHelper::call('host.update',[
			'hostid' => self::$hostid,
			'templates' => self::$templateids
		]);
		$this->checkItems();
		$form = $this->openForm(($this->standalone ? $this->link.self::$hostid : $this->link), self::$host);
		$interfaces_form = ($form->getFieldContainer('Interfaces')->asHostInterfaceElement());
		$this->assertEquals('Discovery rule 1',($form->getField('Discovered by')->gettext()));

		// Check host form disabled fields.
		foreach (['Host name', 'Visible name', 'Groups', 'Monitored by proxy'] as $field) {
			$this->assertFalse($form->getField($field)->isEnabled());
		}

		foreach (['IP address', 'DNS name', 'Port'] as $column) {
			$this->assertFalse($interfaces_form->getRow('Agent')->getColumn($column)->query('tag:input')->one()->isEnabled());
		}

		foreach (['Connect to', 'Default'] as $column) {
			$this->assertFalse($interfaces_form->getRow('Agent')->getColumn($column)->query('xpath:.//input[@type="radio"]')
					->one()->isEnabled());
		}

		// Check "Enabled" checkbox.
		$enabled = $form->getField('Enabled');
		$this->assertTrue($enabled->isChecked());
		$this->assertTrue($enabled->isChecked());

		// Check "Description" field.
		$description = $form->getField('Description');
		$this->assertTrue($description->isEnabled());
		$form->fill(['Description' => 'Some text']);
		$this->assertEquals('Some text', $description->getValue());

		// Check hintbox.
		$form->query('class:icon-help-hint')->one()->click();
		$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

		// Assert text.
		$this->assertEquals("Templates linked by host discovery cannot be unlinked.".
				"\nUse host prototype configuration form to remove automatically linked templates on upcoming discovery.",
				$hint->one()->getText());

		// Close the hint-box.
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();
		$template_table = $form->query('id:linked-templates')->asTable()->one()->waitUntilVisible();

		foreach (self::$template_names as $name) {
			$template_row = $template_table->findRow('Name', $name);

			foreach (['Unlink', 'Unlink and clear'] as $button) {
				$this->assertTrue($template_row->getColumn('Action')->query('button:'.$button)->one()->isClickable());
			}

			(strpos($name, 'unlink') !== false) ?
				$template_row->getColumn('Action')->query('button:Unlink')->one()->click() :
				$template_row->getColumn('Action')->query('button:Unlink and clear')->one()->click();
		}

		$this->assertEquals('', ($template_table->findRow('Name', 'Linux by Zabbix agent', true)->getColumn('Action')->getText()));
		$this->assertEquals('(linked by host discovery)', $template_table->findRow('Name', 'Linux by Zabbix agent', true)
				->getColumn('Name')->query('xpath:.//sup')->one()->getText());
		$form->submit();
		$form->waitUntilNotVisible();
		$this->checkItems(true);
	}

	private function checkItems($clear = false) {
		if ($clear) {
			$this->page->open('items.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host')->waitUntilReady();
		}
		else {
			$this->page->login()->open('items.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host')->waitUntilReady();
		}

		$table = $this->query('xpath://form[@name="items"]/table')->asTable()->one();

		foreach ($table->getRows() as $row) {
			$names[] = $row->getColumn('Name')->getText();
		}

		if ($clear) {
			foreach (['Template1 item1', 'Template1 item2'] as $item) {
				$this->assertTrue(in_array($item, $names));
			}

			foreach (['2 template for clear: Template2 item1', '2 template for clear: Template2 item2'] as $item) {
				$this->assertFalse(in_array($item, $names));
			}
		}
		else {

			foreach (['1 template for unlink: Template1 item1', '1 template for unlink: Template1 item2',
						'2 template for clear: Template2 item1', '2 template for clear: Template2 item2'] as $item) {
				$this->assertTrue(in_array($item, $names));
			}
		}
	}

	private function openForm($url, $host) {
		$this->page->login()->open($url)->waitUntilReady();

		return ($this->standalone)
			? $this->query('id:host-form')->asForm()->waitUntilReady()->one()
			: $this->filterAndSelectHost($host);
	}

	public function filterAndSelectHost($host) {
		$this->query('button:Reset')->one()->click();
		$this->query('name:zbx_filter')->asForm()->waitUntilReady()->one()->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();
		$host_link = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible()
				->findRow('Name', $host, true)->getColumn('Name')->query($this->monitoring ? 'tag:a' : 'xpath://a[@onclick]')
				->waitUntilClickable();

		if ($this->monitoring) {
			$host_link->asPopupButton()->one()->select('Configuration');
		}
		else {
			$host_link->one()->click();
		}

		return COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
	}
}

