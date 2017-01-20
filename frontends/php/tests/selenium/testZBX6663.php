<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testZBX6663 extends CWebTest {


	/**
	 * The name of the discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'DiscoveryRule ZBX6663 Second';

	/**
	 * The template created in the test data set.
	 *
	 * @var string
	 */
	protected $templated = 'Template ZBX6663 Second';


	// Returns test data
	public static function zbx_data() {
		return [
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Applications',
					'checkbox' => 'applications'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Items',
					'checkbox' => 'items'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Item prototypes',
					'checkbox' => 'items'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Graph prototypes',
					'checkbox' => 'graphs'
				]
			],
			[
				[
					'host' => 'Host ZBX6663',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Applications',
					'checkbox' => 'applications'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Items',
					'checkbox' => 'items'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Item prototypes',
					'checkbox' => 'items'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Graph prototypes',
					'checkbox' => 'graphs'
				]
			],
			[
				[
					'template' => 'Template ZBX6663 First',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				]
			]
		];
	}


	/**
	 * @dataProvider zbx_data
	 */
	public function testZBX6663_MassSelect($zbx_data) {

		$checkbox = $zbx_data['checkbox'];

		if (isset($zbx_data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');
			$this->zbxTestClickLinkText($zbx_data['host']);
		}

		if (isset($zbx_data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');
			$this->zbxTestClickLinkText($zbx_data['template']);
		}

		if (isset($zbx_data['discoveryRule'])) {
			$this->zbxTestClickLinkTextWait('Discovery rules');
			$this->zbxTestCheckHeader('Discovery rules');
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait($zbx_data['discoveryRule']);
		}
		else {
			$link = $zbx_data['link'];
			$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='$link']");
		}

		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('selected_count'));
		$this->zbxTestTextPresent('0 selected');
		$this->zbxTestCheckboxSelect("all_$checkbox");

		$this->zbxTestClickLinkText($this->templated);
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::id('selected_count'));
		$this->zbxTestTextPresent('0 selected');
	}
}
