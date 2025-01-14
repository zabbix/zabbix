<?php
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup hosts
 *
 * @onBefore getIDs
 */
class testFormHostLinkTemplates extends CLegacyWebTest {
	protected static $host = 'Visible host for template linkage';
	protected static $template = 'Form test template';
	protected static $link_template = 'Linux by Zabbix agent active';

	protected static $hostid;
	protected static $templateid;
	protected static $link_templateid;

	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	protected static function getIDs() {
		$ids = CDBHelper::getAll('SELECT hostid FROM hosts WHERE host IN ('.zbx_dbstr(self::$template).', '.
				zbx_dbstr(self::$link_template).') OR name='.zbx_dbstr(self::$host).' ORDER BY host DESC'
		);

		self::$hostid = $ids[0]['hostid'];
		self::$templateid = $ids[1]['hostid'];
		self::$link_templateid = $ids[2]['hostid'];
	}

	public static function getLinkUnlinkTemplateData() {
		return [
				// #0 Attach Template to Template
			[
				[
					'link' => 'templates.php?form=update&templateid=',
					'entity' => 'Template',
					'standalone' => 'true'
				]
			],
					// #1 Attach Template to Host from Configuration -> Hosts
			[
				[
					'link' => 'zabbix.php?action=host.list',
					'entity' => 'Host'
				]
			],
					// #2 Attach Template to Host from Monitoring -> Hosts
			[
				[
					'link' => 'zabbix.php?action=host.view',
					'entity' => 'Host'
				]
			],
					// #3 Attach Template to Host from Standalone view
			[
				[
					'link' => 'zabbix.php?action=host.edit&hostid=',
					'entity' => 'Host',
					'standalone' => 'true'
				]
			]
		];
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
		$this->zbxTestClickLinkTextWait(self::$host);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		$dialog->fill(['Templates' => self::$link_template]);

		$this->zbxTestTextPresent(self::$link_template);
		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent(self::$host);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLink
	 */
	public function testFormHostLinkTemplates_TemplateUnlink() {
		// Unlink a template from a host from host properties page.
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait(self::$host);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink" next to a template by name.
		$this->assertTrue($dialog->query('link', self::$link_template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', self::$link_template)->getColumn('Action')
				->query('button:Unlink')->one()->click();
		$this->assertFalse($dialog->query('link', self::$link_template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		// this should be a separate test
		// should check that items, triggers and graphs are not linked to the template anymore
		$this->zbxTestClickXpathWait("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".self::$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent(self::$link_template.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::$link_template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::$link_template.':');
	}

	public function testFormHostLinkTemplates_TemplateLinkUpdate() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait(self::$host);

		$form = $this->query('name:host-form')->asForm()->waitUntilReady()->one();
		$form->fill(['Templates' => self::$link_template]);

		$this->zbxTestTextPresent(self::$link_template);
		$form->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent(self::$host);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLinkUpdate
	 */
	public function testFormHostLinkTemplates_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page.
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait(self::$host);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink and clear" next to a template by name.
		$this->assertTrue($dialog->query('link', self::$link_template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', self::$link_template)->getColumn('Action')
				->query('button:Unlink and clear')->one()->click();
		$this->assertFalse($dialog->query('link', self::$link_template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$this->zbxTestClickXpathWait("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".self::$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent(self::$link_template.':');

		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::$link_template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid);
		$this->zbxTestTextNotPresent(self::$link_template.':');
	}

	/**
	 * @dataProvider getLinkUnlinkTemplateData
	 */
	public function testFormHostLinkTemplates_HostTemplateRelinkage($data) {
		// Open corresponding configuration form.
		if (CTestArrayHelper::get($data, 'standalone')) {
			if ($data['entity'] === 'Host') {
				$entity_id = self::$hostid;
				$form_id = 'host-form';
				$name = self::$template;
			}
			else {
				$entity_id = self::$templateid;
				$form_id = 'templates-form';
				$name = self::$host;
			}
			$this->page->login()->open($data['link'].$entity_id)->waitUntilReady();
			$form = $this->query('id', $form_id)->asForm()->waitUntilVisible()->one();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$name = self::$host;
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', self::$host)->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		}
		else {
			$name = self::$host;
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', self::$host)->waitUntilVisible()->one()->click();
			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		}

		// Check if template is linked from previous test runs, if not then links it.
		if (!$form->query('id:linked-templates')->exists()) {
			$form->getField('Templates')->asMultiselect()->fill(self::$template);
			$this->assertEquals(self::$template, $form->query('class:subfilter-enabled')->one()->getText());
			$form->submit();
			$this->assertMessage(TEST_GOOD, $data['entity'].' updated');
		}

		// Open host configuration again, remove template link.
		if (CTestArrayHelper::get($data, 'standalone')) {
			$this->page->open($data['link'].$entity_id)->waitUntilReady();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$this->query('link', $name)->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->query('link', $name)->waitUntilVisible()->one()->click();
		}

		$form->query('id:linked-templates')->waitUntilVisible()->asTable()->one()->findRow('Name', self::$template)
				->getColumn('Action')->query('button:Unlink')->one()->click();
		$this->assertEquals('', $form->query('id:add_templates__ms')->one()->getText());

		// Relink the template, save the form and assert that template is successfully linked.
		$form->getField('Templates')->asMultiselect()->fill(self::$template);
		$this->assertEquals(self::$template, $form->query('class:subfilter-enabled')->one()->getText());
		$form->submit();

		$this->assertMessage(TEST_GOOD, $data['entity'].' updated');

		// Check that template is linked successfully.
		if (CTestArrayHelper::get($data, 'standalone')) {
			$this->page->open($data['link'].$entity_id)->waitUntilReady();
		}
		elseif($data['link'] === 'zabbix.php?action=host.view') {
			$this->query('link', $name)->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->query('link', $name)->waitUntilVisible()->one()->click();
		}
		$this->assertTrue($form->query('link', self::$template)->exists());
	}
}
