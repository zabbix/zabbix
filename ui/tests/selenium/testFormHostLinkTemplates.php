<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
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

		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

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

		$form = $this->query('name:host-form')->waitUntilReady()->asForm()->one();
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

		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

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

	public static function getLinkUnlinkTemplateData() {
		return [
			// #0 Attach template to template
			[
				[
					'link' => 'templates.php?form=update&templateid=',
					'entity' => 'Template',
					'standalone' => 'true'
				]
			],
			// #1 Attach template to host from Configuration -> Hosts
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
			],
			// #3 Attach template to host from Standalone view
			[
				[
					'link' => 'zabbix.php?action=host.edit&hostid=',
					'standalone' => 'true'
				]
			]
		];
	}

	/**
	 * @dataProvider getLinkUnlinkTemplateData
	 */
	public function testFormHostLinkTemplates_HostTemplateRelinkage($data) {
		$entity = CTestArrayHelper::get($data, 'entity', 'Host');
		$name = ($entity === 'Template') ? self::$template : self::$host;

		// Open corresponding configuration form.
		if (CTestArrayHelper::get($data, 'standalone')) {
			if ($entity === 'Host') {
				$entity_id = self::$hostid;
				$form_id = 'host-form';
			}
			else {
				$entity_id = self::$templateid;
				$form_id = 'templates-form';
			}
			$data['link'] = $data['link'].$entity_id;
			$this->page->login()->open($data['link'])->waitUntilReady();
			$form = $this->query('id', $form_id)->asForm()->waitUntilVisible()->one();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', self::$host)->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		}
		else {
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', self::$host)->waitUntilVisible()->one()->click();
			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		}

		// Link template and save form.
		if (!$form->query('id:linked-templates')->exists()) {
			$form->getField('Templates')->asMultiselect()->fill(self::$template);
			$this->assertEquals(self::$template, $form->query('class:subfilter-enabled')->one()->getText());
			$form->submit();
			$this->assertMessage(TEST_GOOD, $entity.' updated');

			// Open host configuration again, remove template link.
			$this->openConfigurationForm($data, $name);
		}

		$form->query('id:linked-templates')->waitUntilVisible()->asTable()->one()->findRow('Name', self::$template)
				->getColumn('Action')->query('button:Unlink')->one()->click();
		$this->assertEquals('', $form->query('id:add_templates__ms')->one()->getText());

		// Relink the template, save the form.
		$form->getField('Templates')->asMultiselect()->fill(self::$template);
		$this->assertEquals(self::$template, $form->query('class:subfilter-enabled')->one()->getText());
		$form->submit();
		$this->assertMessage(TEST_GOOD, $entity.' updated');

		// Check that template is linked successfully.
		$this->openConfigurationForm($data, $name);
		$this->assertTrue($form->query('link', self::$template)->exists());

		if (!CTestArrayHelper::get($data, 'standalone')) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Open host/template configuration form.
	 *
	 * @param array		$data	data provider
	 * @param string	$name	name of the host/template to be opened
	 */
	protected function openConfigurationForm($data, $name) {
		if (CTestArrayHelper::get($data, 'standalone')) {
			$this->page->open($data['link'])->waitUntilReady();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$this->query('link', $name)->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->query('link', $name)->waitUntilVisible()->one()->click();
		}
	}
}
