<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		return array(
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Applications',
					'checkbox' => 'applications'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Items',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Item prototypes',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'discoveryRule' => 'Graph prototypes',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'host' => 'Host ZBX6663',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Applications',
					'checkbox' => 'applications'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Items',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Item prototypes',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'discoveryRule' => 'Graph prototypes',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'template' => 'Template ZBX6663 First',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				)
			)
		);
	}


	/**
	 * @dataProvider zbx_data
	 */
	public function testZBX6663_MassSelect($zbx_data) {

		$checkbox = $zbx_data['checkbox'];

		if (isset($zbx_data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');
			$this->zbxTestClickWait('link='.$zbx_data['host']);
		}

		if (isset($zbx_data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');
			$this->zbxTestClickWait('link='.$zbx_data['template']);
		}

		if (isset($zbx_data['discoveryRule'])) {
			$this->zbxTestClickWait('link=Discovery rules');
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait('link='.$zbx_data['discoveryRule']);
		}
		else {
			$link = $zbx_data['link'];
			$this->zbxTestClickWait("//div[@class='w']//a[text()='$link']");
		}

		$this->assertVisible('//input[@value="Go (0)"]');
		$this->zbxTestCheckboxSelect("all_$checkbox");

		$this->zbxTestClickWait('link='.$this->templated);
		$this->assertVisible('//input[@value="Go (0)"]');
	}
}
