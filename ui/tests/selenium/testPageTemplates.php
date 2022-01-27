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
require_once dirname(__FILE__).'/traits/FilterTrait.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @dataSource TagFilter
 */
class testPageTemplates extends CLegacyWebTest {

	public $templateName = 'Huawei OceanStor 5300 V5 SNMP';

	use FilterTrait;
	use TableTrait;

	public static function allTemplates() {
		// TODO: remove 'AND name NOT LIKE "%Cisco Catalyst%"' and change to single quotes after fix ZBX-19356
		return CDBHelper::getRandomizedDataProvider("SELECT * FROM hosts WHERE status IN (".HOST_STATUS_TEMPLATE.")".
				" AND name NOT LIKE '%Cisco Catalyst%' AND name NOT LIKE '%Mellanox%'", 25);
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
		// TODO: Remove the below if condition along with its content when ZBX-19356 is merged
		$skip_templates = [
			'Alcatel Timetra TiMOS SNMP',
			'Arista SNMP',
			'Brocade FC SNMP',
			'Brocade_Foundry Nonstackable SNMP',
			'Brocade_Foundry Stackable SNMP',
			'Cisco Catalyst 3750V2-24FS SNMP',
			'Cisco Catalyst 3750V2-24PS SNMP',
			'Cisco Catalyst 3750V2-24TS SNMP',
			'Cisco Catalyst 3750V2-48PS SNMP',
			'Cisco Catalyst 3750V2-48TS SNMP',
			'Cisco IOS SNMP',
			'Cisco IOS versions 12.0_3_T-12.2_3.5 SNMP',
			'Cisco UCS Manager SNMP',
			'D-Link DES 7200 SNMP',
			'D-Link DES_DGS Switch SNMP',
			'Dell Force S-Series SNMP',
			'Extreme EXOS SNMP',
			'HP Comware HH3C SNMP',
			'HP Enterprise Switch SNMP',
			'Huawei VRP SNMP',
			'Intel_Qlogic Infiniband SNMP',
			'Juniper SNMP',
			'Linux SNMP',
			'Mellanox SNMP',
			'MikroTik CCR1009-7G-1C-1SPC SNMP',
			'MikroTik CCR1009-7G-1C-1S SNMP',
			'MikroTik CCR1009-7G-1C-PC SNMP',
			'MikroTik CCR1016-12G SNMP',
			'MikroTik CCR1016-12S-1S SNMP',
			'MikroTik CCR1036-8G-2SEM SNMP',
			'MikroTik CCR1036-8G-2S SNMP',
			'MikroTik CCR1036-12G-4S-EM SNMP',
			'MikroTik CCR1036-12G-4S SNMP',
			'MikroTik CCR1072-1G-8S SNMP',
			'MikroTik CCR2004-1G-12S2XS SNMP',
			'MikroTik CCR2004-16G-2S SNMP',
			'MikroTik CRS106-1C-5S SNMP',
			'MikroTik CRS109-8G-1S-2HnD-IN SNMP',
			'MikroTik CRS112-8G-4S-IN SNMP',
			'MikroTik CRS112-8P-4S-IN SNMP',
			'MikroTik CRS125-24G-1S-2HnD-IN SNMP',
			'MikroTik CRS212-1G-10S-1SIN SNMP',
			'MikroTik CRS305-1G-4SIN SNMP',
			'MikroTik CRS309-1G-8SIN SNMP',
			'MikroTik CRS312-4C8XG-RM SNMP',
			'MikroTik CRS317-1G-16SRM SNMP',
			'MikroTik CRS326-24G-2SIN SNMP',
			'MikroTik CRS326-24G-2SRM SNMP',
			'MikroTik CRS326-24S2QRM SNMP',
			'MikroTik CRS328-4C-20S-4SRM SNMP',
			'MikroTik CRS328-24P-4SRM SNMP',
			'MikroTik CRS354-48G-4S2QRM SNMP',
			'MikroTik CRS354-48P-4S2QRM SNMP',
			'MikroTik CSS326-24G-2SRM SNMP',
			'MikroTik CSS610-8G-2SIN SNMP',
			'MikroTik FiberBox SNMP',
			'MikroTik hEX lite SNMP',
			'MikroTik hEX PoE lite SNMP',
			'MikroTik hEX PoE SNMP',
			'MikroTik hEX SNMP',
			'MikroTik hEX S SNMP',
			'MikroTik netPower 15FR SNMP',
			'MikroTik netPower 16P SNMP',
			'MikroTik netPower Lite 7R SNMP',
			'MikroTik PowerBox Pro SNMP',
			'MikroTik PowerBox SNMP',
			'MikroTik RB260GSP SNMP',
			'MikroTik RB260GS SNMP',
			'MikroTik RB1100AHx4 Dude Edition SNMP',
			'MikroTik RB1100AHx4 SNMP',
			'MikroTik RB2011iL-IN SNMP',
			'MikroTik RB2011iL-RM SNMP',
			'MikroTik RB2011iLS-IN SNMP',
			'MikroTik RB2011UiAS-IN SNMP',
			'MikroTik RB2011UiAS-RM SNMP',
			'MikroTik RB3011UiAS-RM SNMP',
			'MikroTik RB4011iGSRM SNMP',
			'MikroTik RB5009UGSIN SNMP',
			'Mikrotik SNMP',
			'Netgear Fastpath SNMP',
			'Network Generic Device SNMP',
			'PFSense SNMP',
			'QTech QSW SNMP',
			'Windows SNMP'
		];

		if ($template['name'] === 'Cisco UCS Manager SNMP') {
			return;
		}

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
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

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
