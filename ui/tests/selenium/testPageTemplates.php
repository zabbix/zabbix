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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/traits/TagTrait.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup profiles
 *
 * @dataSource TagFilter
 */
class testPageTemplates extends CLegacyWebTest {

	public $templateName = 'Huawei OceanStor 5300 V5 by SNMP';

	use TagTrait;
	use TableTrait;

	public static function allTemplates() {
		return CDBHelper::getRandomizedDataProvider(
			'SELECT * FROM hosts WHERE status IN ('.HOST_STATUS_TEMPLATE.')', 25
		);
	}

	public function testPageTemplates_CheckLayout() {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestCheckTitle('Configuration of templates');
		$this->zbxTestCheckHeader('Templates');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Host groups')->select('Templates/SAN');
		$filter->submit();
		$this->zbxTestTextPresent($this->templateName);

		$table = $this->query('class:list-table')->asTable()->one();
		$headers = ['', 'Name', 'Hosts', 'Items', 'Triggers', 'Graphs', 'Dashboards', 'Discovery', 'Web',
				'Linked templates', 'Linked to templates', 'Tags'
		];
		$this->assertSame($headers, $table->getHeadersText());

		foreach (['Export', 'Mass update', 'Delete', 'Delete and clear'] as $button) {
			$element = $this->query('button', $button)->one();
			$this->assertTrue($element->isPresent());
			$this->assertFalse($element->isEnabled());
		}

		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");

	}

	/**
	 * @dataProvider allTemplates
	 */
	public function testPageTemplates_SimpleUpdate($template) {
		$host = $template['host'];
		$name = $template['name'];

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = CDBHelper::getHash($sqlTemplate);
		$sqlHosts =
				'SELECT hostid,proxy_hostid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,'.
				'ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,'.
				'name,flags,templateid,description,tls_connect,tls_accept'.
			' FROM hosts'.
			' ORDER BY hostid';
		$oldHashHosts = CDBHelper::getHash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldHashItems = CDBHelper::getHash($sqlItems);
		$sqlTriggers = "select triggerid,expression,description,url,status,value,priority,comments,error,templateid,type,state,flags from triggers order by triggerid";
		$oldHashTriggers = CDBHelper::getHash($sqlTriggers);

		$this->zbxTestLogin('templates.php?page=1');
		$this->query('button:Reset')->one()->click();

		// Filter necessary Template name.
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->fill(['Name' => $name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
				->getColumn('Name')->query('link', $name)->one()->click();

		$this->zbxTestCheckHeader('Templates');
		$this->zbxTestTextPresent('All templates');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of templates');
		$this->zbxTestCheckHeader('Templates');
		$this->zbxTestTextPresent('Template updated');
		$this->zbxTestTextPresent($name);

		$this->assertEquals($oldHashTemplate, CDBHelper::getHash($sqlTemplate));
		$this->assertEquals($oldHashHosts, CDBHelper::getHash($sqlHosts));
		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
	}

	public function testPageTemplates_FilterTemplateByName() {
		$this->zbxTestLogin('templates.php');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Host groups')->select('Templates/SAN');
		$filter->getField('Name')->fill($this->templateName);
		$filter->submit();
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='$this->templateName']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageTemplates_FilterByLinkedTemplate() {
		$this->zbxTestLogin('templates.php');
		$this->query('button:Reset')->one()->click();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Linked templates')->fill([
				'values' => 'Template ZBX6663 Second',
				'context' => 'Templates'
		]);
		$filter->submit();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='Template ZBX6663 Second']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageTemplates_FilterNone() {
		$this->zbxTestLogin('templates.php');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->fill([
			'Host groups'	=> 'Templates',
			'Name' => '123template!@#$%^&*()_"='
		]);
		$filter->submit();
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 0 of 0 found']");
		$this->zbxTestInputTypeOverwrite('filter_name', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 0 of 0 found']");
	}

	public function testPageTemplates_FilterReset() {
		$this->zbxTestLogin('templates.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public static function getFilterByTagsData() {
		return [
			// "And" and "And/Or" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'template'],
						['name' => 'test', 'operator' => 'Contains', 'value' => 'test_tag']
					],
					'expected_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'template'],
						['name' => 'test', 'operator' => 'Contains', 'value' => 'test_tag']
					],
					'expected_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone',
						'Template for tags filtering - update'
					]
				]
			],
			// "Contains" and "Equals" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'TEMPLATE']
					],
					'expected_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone',
						'Template for tags filtering - update'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Equals', 'value' => 'TEMPLATE']
					],
					'expected_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Contains']
					],
					'expected_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone',
						'Template for tags filtering - update'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Equals']
					],
					'expected_templates' => []
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Exists']
					],
					'expected_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Exists'],
						['name' => 'test', 'operator' => 'Exists']
					],
					'expected_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone',
						'Template for tags filtering - update'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist'],
						['name' => 'tag', 'operator' => 'Does not exist']
					],
					'absent_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone',
						'Template for tags filtering - update'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist'],
						['name' => 'tag', 'operator' => 'Does not exist']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag'],
						['name' => 'tag', 'operator' => 'Does not equal', 'value' => 'TEMPLATE']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag'],
						['name' => 'action', 'operator' => 'Does not equal', 'value' => 'update']
					],
					'absent_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - update'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not contain', 'value' => 'test_']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not contain', 'value' => 'test_']
					],
					'absent_templates' => [
						'Template for tags filtering'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not contain', 'value' => 'test_'],
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clo']
					],
					'absent_templates' => [
						'Template for tags filtering',
						'Template for tags filtering - clone'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Does not contain', 'value' => 'temp'],
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'upd']
					],
					'absent_templates' => [
						'Template for tags filtering - update'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterByTagsData
	 */
	public function testPageTemplates_FilterByTags($data) {
		$this->page->login()->open('templates.php?filter_name=template&filter_evaltype=0&filter_tags%5B0%5D%5Btag%5D='.
				'&filter_tags%5B0%5D%5Boperator%5D=0&filter_tags%5B0%5D%5Bvalue%5D=&filter_set=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:filter_evaltype' => $data['evaluation_type']]);
		$this->setTags($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check that correct result displayed.
		if (array_key_exists('absent_templates', $data)) {
			$filtering = $this->getTableResult('Name');
			foreach ($data['absent_templates'] as $absence) {
				if (($key = array_search($absence, $filtering))) {
					unset($filtering[$key]);
				}
			}
			$filtering = array_values($filtering);
			$this->assertTableDataColumn($filtering, 'Name');
		}
		else {
			$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected_templates', []));
		}

		// Reset filter due to not influence further tests.
		$form->query('button:Reset')->one()->click();
	}

	/**
	 * Test opening Hosts filtered by corresponding Template.
	 */
	public function testPageTemplates_CheckHostsColumn() {
		$template = 'Form test template';
		$hosts = ['Simple form test host'];

		$this->page->login()->open('templates.php?groupid=0');
		// Reset Templates filter from possible previous scenario.
		$this->resetFilter();
		// Click on Hosts link in Template row.
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', $template)->query('link:Hosts')->one()->click();
		// Check that Hosts page is opened.
		$this->page->assertHeader('Hosts');
		$filter = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table->invalidate();
		// Check that correct Hosts are filtered.
		$this->assertEquals([$template], $filter->getField('Templates')->getValue());
		$this->assertTableDataColumn($hosts);
		$this->assertTableStats(count($hosts));
		// Reset Hosts filter after scenario.
		$this->resetFilter();
	}

	private function resetFilter() {
		$filter = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$filter->query('button:Reset')->one()->click();
	}
}
