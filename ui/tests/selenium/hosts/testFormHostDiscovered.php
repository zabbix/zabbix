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
class testFormHostDiscovered extends testFormHost {

	protected static $host = 'Host prototype LLD';

	public static function createTemplate() {
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr(self::$host));

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
				'host' => '1 template with tags for link',
				'groups' => [
					'groupid' => 4
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
				'host' => '2 template with tags for link',
				'groups' => [
					'groupid' => 4
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

		foreach ($templates['templateids'] as $value) {
			$templateids[] = $value;
		}

		// Link templates.
		$template_link = CDataHelper::call('host.update',[
			'hostid' => $hostid,
			'templates' => $templateids
		]);
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
	public function testFormHostDiscovered_FormLayout($data) {
		$this->standalone = $data['standalone'];
		$this->link = $data['url'];
		$this->monitoring = $data['monitoring'];
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr(self::$host));
		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), self::$host);
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

		foreach (['Unlink', 'Unlink and clear'] as $button) {
			$this->assertTrue($template_table->findRow('Name', 'Template for link testing')->getColumn('Action')
					->query('button:'.$button)->one()->isClickable());
		}

		$this->assertEquals('', ($template_table->findRow('Name', 'Linux by Zabbix agent', true)->getColumn('Action')->getText()));
		$this->assertEquals('(linked by host discovery)', $template_table->findRow('Name', 'Linux by Zabbix agent', true)
				->getColumn('Name')->query('xpath:.//sup')->one()->getText());

		if (!$this->standalone) {
			$this->query('xpath:.//div[@class="dashboard-widget-head"]/button[@class="overlay-close-btn"]')->one()->click();
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

