<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../behaviors/CDatatableBehavior.php';

/**
 * @backup hosts
 *
 * @onBefore prepareHostData
 */
class testFormHostLinkTemplates extends CLegacyWebTest {
	const HOST_VISIBLE_NAME = 'Visible host for template linkage';
	const TEMPLATE = 'Form test template';
	const LINKED_TEMPLATE = 'Linux by Zabbix agent active';

	protected static $hostid;

	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CDatatableBehavior::class
		];
	}

	public function prepareHostData() {
		self::$hostid = CDataHelper::call('host.create', [
			[
				'host' => 'Template linkage test host',
				'name' => self::HOST_VISIBLE_NAME,
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		])['hostids'][0];

		// Change the width of datatable columns so that the whole host name would be visible clickable.
		$layout = '{"columns":[{"id":"name","resized":true,"width":"33%"},{"id":"items","resized":true,"width":"5.5%"},'.
				'{"id":"triggers","resized":true,"width":"6.9%"},{"id":"graphs","resized":true,"width":"6.3%"},'.
				'{"id":"discovery","resized":true,"width":"7.6%"},{"id":"web","resized":true,"width":"5%"},'.
				'{"id":"interface","resized":true,"width":"9.6%"},{"id":"proxy","resized":true,"width":"5.6%"},'.
				'{"id":"templates","resized":true,"width":"7.8%"},{"id":"status","resized":true,"width":"6.1%"},'.
				'{"id":"availability","resized":true,"width":"12.3%"},{"id":"encryption","resized":true,"width":"11%"},'.
				'{"id":"info","resized":true,"width":"4.7%"},{"id":"tags","resized":true,"width":"7.7%"},{"id":"tagvalue"}],'.
				'"options":{}}';

		$this->updateDatatableLayout($layout, 'web.hosts.datatable');
	}

	public function testFormHostLinkTemplates_Layout() {
		$this->page->login()->open('zabbix.php?action=host.list')->waitUntilReady();
		$this->query('button:Create host')->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->selectTab('Inventory');

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_toHash($inventoryFields, 'db_field');
		foreach ($inventoryFields as $fieldId => $fieldName) {
			$this->zbxTestTextPresent($fieldName['title']);
			$this->zbxTestAssertElementPresentId('host_inventory_'.$fieldId.'');
		}
		COverlayDialogElement::find()->one()->close();
	}

	public function testFormHostLinkTemplates_TemplateLink() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();

		// Additional 20px scrolling needed because host name is behindthe horizontal scrollbar.
		$this->query('link', self::HOST_VISIBLE_NAME)->waitUntilClickable()->one()->scrollIntoView(50)->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$dialog->fill(['Templates' => self::LINKED_TEMPLATE]);

		$this->zbxTestTextPresent(self::LINKED_TEMPLATE);
		$dialog->submit();
		$this->page->waitUntilReady();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		$this->query('link', self::HOST_VISIBLE_NAME)->one()->waitUntilPresent();
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLink
	 */
	public function testFormHostLinkTemplates_TemplateUnlink() {
		// Unlink a template from a host from host properties page
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();

		// Additional 20px scrolling needed because host name is behindthe horizontal scrollbar.
		$this->query('link', self::HOST_VISIBLE_NAME)->waitUntilClickable()->one()->scrollIntoView(50)->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		// Clicks button named "Unlink" next to a template by name.
		$this->assertTrue($dialog->query('link', self::LINKED_TEMPLATE)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', self::LINKED_TEMPLATE)->getColumn('Actions')
				->query('button:Unlink')->one()->click();
		$this->assertFalse($dialog->query('link', self::LINKED_TEMPLATE)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		// this should be a separate test
		// should check that items, triggers and graphs are not linked to the template anymore
		$items_link = 'xpath://a[contains(@href,"zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostid.'&context=host")]';
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady()->query($items_link)->one()
				->scrollIntoView(50)->click();

		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');
		$this->zbxTestHrefClickWait('zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateUnlink
	 */
	public function testFormHostLinkTemplates_TemplateLinkUpdate() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();

		// Additional 50px scrolling needed because host name is behindthe horizontal scrollbar.
		$this->query('link', self::HOST_VISIBLE_NAME)->waitUntilClickable()->one()->scrollIntoView(50)->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
		$dialog->fill(['Templates' => self::LINKED_TEMPLATE]);

		$this->zbxTestTextPresent(self::LINKED_TEMPLATE);
		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		$this->zbxTestTextPresent(self::HOST_VISIBLE_NAME);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLinkUpdate
	 */
	public function testFormHostLinkTemplates_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();

		// Additional 20px scrolling needed because host name is behindthe horizontal scrollbar.
		$this->query('link', self::HOST_VISIBLE_NAME)->waitUntilClickable()->one()->scrollIntoView(50)->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		// Clicks button named "Unlink and clear" next to a template by name.
		$this->assertTrue($dialog->query('link', self::LINKED_TEMPLATE)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', self::LINKED_TEMPLATE)->getColumn('Actions')
				->query('button:Unlink and clear')->one()->click();
		$this->assertFalse($dialog->query('link', self::LINKED_TEMPLATE)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$items_locator = 'xpath://a[contains(@href,"zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostid.'&context=host")]';
		$this->query($items_locator)->one()->scrollIntoView(50)->click();
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');

		$this->zbxTestHrefClickWait('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');
		$this->zbxTestHrefClickWait('zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');
		$this->zbxTestTextNotPresent(self::LINKED_TEMPLATE.':');
	}

	public static function getLinkUnlinkTemplateData() {
		return [
			// #0 Attach template to template
			[
				[
					'link' => 'zabbix.php?action=template.list',
					'entity' => 'Template'
				]
			],
			// #1 Attach template to host from Data collection -> Hosts
			[
				[
					'link' => 'zabbix.php?action=host.list'
				]
			],
			// #2 Attach template to host from Monitoring -> Hosts
			[
				[
					'link' => 'zabbix.php?action=host.view'
				]
			]
		];
	}

	/**
	 * @dataProvider getLinkUnlinkTemplateData
	 */
	public function testFormHostLinkTemplates_HostTemplateRelinkage($data) {
		$entity = CTestArrayHelper::get($data, 'entity', 'Host');

		// Open corresponding configuration form.
		$this->page->login()->open($data['link'])->waitUntilReady();
		$this->query('button:Reset')->one()->click();
		$this->openConfigurationForm($data);
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		// Link template and save form.
		if (!$form->query('id:linked-templates')->exists()) {
			$form->getField('Templates')->asMultiselect()->fill(self::LINKED_TEMPLATE);
			$this->assertEquals(self::LINKED_TEMPLATE, $form->query('class:subfilter-enabled')->one()->getText());
			$form->submit();
			$this->assertMessage(TEST_GOOD, $entity.' updated');
			CMessageElement::find()->one()->close();

			$this->openConfigurationForm($data);
			$form->invalidate();
		}

		// Remove template link.
		$form->query('id:linked-templates')->waitUntilVisible()->asTable()->one()->findRow('Name', self::LINKED_TEMPLATE)
				->getColumn('Actions')->query('button:Unlink')->one()->click();
		$selector = ($entity === 'Template') ? 'id:template_add_templates__ms' : 'id:add_templates__ms';
		$this->assertEquals('', $form->query($selector)->one()->getText());

		// Relink the template, save the form.
		$form->getField('Templates')->asMultiselect()->fill(self::LINKED_TEMPLATE);
		$this->assertEquals(self::LINKED_TEMPLATE, $form->query('class:subfilter-enabled')->one()->getText());
		$form->submit();
		$this->assertMessage(TEST_GOOD, $entity.' updated');

		// Check that template is linked successfully.
		$this->openConfigurationForm($data);
		$this->assertTrue($form->query('link', self::LINKED_TEMPLATE)->exists());
		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Open host/template configuration form.
	 *
	 * @param array		$data	data provider
	 * @param string	$name	name of the host/template to be opened
	 */
	protected function openConfigurationForm($data) {
		if (CTestArrayHelper::get($data, 'entity', 'Host') === 'Host') {
			$host_link = $this->query('link', self::HOST_VISIBLE_NAME)->waitUntilVisible()->one();
			if ($data['link'] === 'zabbix.php?action=host.view') {
				$host_link->asPopupButton()->select('Host');
			}
			else {
				$host_link->scrollIntoView(50)->click();
			}
		}
		else {
			if (!$this->query('link', self::TEMPLATE)->exists()) {
				$this->query('name:zbx_filter')->asForm()->one()->fill(['Name' => self::TEMPLATE])->submit();
				$this->page->waitUntilReady();
			}

			$this->query('link', self::TEMPLATE)->waitUntilVisible()->one()->scrollIntoView(50)->click();
		}
	}
}
