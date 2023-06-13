<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageMonitoringWebDetails extends CWebTest {

	use TableTrait;

	protected const HOST_NAME = 'Host with web scenarios';

	protected static $host_id;

	public function prepareData() {
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [
					'groupid' => '6'
				]
			]
		]);
		self::$host_id = $response['hostids'][self::HOST_NAME];
	}

	public function getWebScenarioData()
	{
		return [
			[
				[
					'name' => 'Many steps',
					'step_names' => $this->getGeneratedStepNames(50)
				]
			],
			[
				[
					'name' => 'TEST ЗАББИКС !@#$%-() 🙂🙃 <br/> &nbsp;',
					'step_names' => [
						'test ēõšŗ тест 测试 テスト δοκιμή',
						'!@#$%^&*_+\\/()[]{}<>🙂🙃',
						'<script>window.onload=function(){alert("hi!");}</script>'
					]
				]
			],
			[
				[
					'name' => ' 	Test whitespace 	',
					'step_names' => [
						'	Tabs	',
						' Spaces ',
						"\nNewline\n"
					]
				]
			]
		];
	}

	/**
	 * Test the general layout.
	 *
	 * @dataProvider getWebScenarioData
	 */
	public function testPageMonitoringWebDetails_DataDisplay($data) {
		// Used for step creation with API.
		$steps = [];

		// Fill in step data that's not displayed in this page.
		foreach ($data['step_names'] as $i => $step_name){
			$step = [];
			$step['name'] = $step_name;
			$step['url'] = 'http://example.com';
			$step['no'] = $i;
			$steps[] = $step;
		}

		// Create a new web scenario.
		$response = CDataHelper::call('httptest.create', [
			[
				'name' => $data['name'],
				'hostid' => self::$host_id,
				'steps' => $steps
			]
		]);
		$this->page->login()->open('httpdetails.php?httptestid='.$response['httptestids'][0])->waitUntilReady();

		// Assert page.
		$this->assertEquals('Details of web scenario: '.trim($data['name']), $this->query('id:page-title-general')->one()->getText());
		$expected_step_names = [];

		foreach ($steps as $step){
			$expected_step_names[] = [trim($step['name'])];
		}

		// The table contains an additional TOTAL row.
		$expected_step_names[] = ['TOTAL'];
		$this->assertTableData($expected_step_names);
	}

	/**
	 * Generates an array of strings like 'Step-1' with a length of $count.
	 */
	protected  function getGeneratedStepNames($count) {
		$result = [];
		for ($i = 1; $i <= $count; $i++) {
			$result[] = 'Step-'.$i;
		}
		return $result;
	}
}
