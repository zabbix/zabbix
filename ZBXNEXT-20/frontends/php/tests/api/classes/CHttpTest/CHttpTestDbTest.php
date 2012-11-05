<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once __DIR__.'/../../../include/db/ZbxDbTestCase.php';

require_once __DIR__.'/../../../../api/rpc/class.czbxrpc.php';


class CHttpTestTest extends ZbxDbTestCase {

	protected function getTestInitialDataSet() {
		return $this->loadInitialDataSet(__DIR__);
	}

	/**
	 * @covers CHttpTest::create
	 */
	public function testCreateOnTemplate() {
		$httpTests = array(
			'hostid' => 1,
			'name' => 'HttpTestCreate',
			'authentication' => 0,
			'applicationid' => 0,
			'delay' => 60,
			'status' => 0,
			'agent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
			'macros' => '',
			'steps' => array(
				array(
					'name' => 'step1',
					'timeout' => 15,
					'url' => 'url1',
					'posts' => '',
					'required' => '',
					'status_codes' => '',
					'no' => 1,
				),
				array(
					'name' => 'step2',
					'timeout' => 15,
					'url' => 'url2',
					'posts' => '',
					'required' => '',
					'status_codes' => '',
					'no' => 2,
				),
			),
			'http_user' => '',
			'http_password' => '',
		);

		$apiHttpTest = new CHttpTest();
		$apiHttpTest->create($httpTests);

		$expectedDataSet = $this->getExpectedDataSet(__DIR__, __METHOD__);

		$queryTable = $this->getConnection()->createQueryTable('httptest',
			'SELECT * FROM httptest WHERE httptestid IN (3, 4) ORDER BY httptestid');
		$expectedTable = $expectedDataSet->getTable('httptest');
		$this->assertTablesEqual($expectedTable, $queryTable);

		$queryTable = $this->getConnection()->createQueryTable('items',
			'SELECT * FROM items WHERE itemid IN ('.implode(',', range(19, 36)).') ORDER BY itemid');
		$expectedTable = $expectedDataSet->getTable('items');
		$this->assertTablesEqual($expectedTable, $queryTable);
	}

	/**
	 * @covers CHttpTest::update
	 */
	public function testUpdateOnTemplate() {
		$httpTests = array(
			'httptestid' => 1,
			'hostid' => 1,
			'status' => 0,
			'name' => 'HttpTestUpdate',
			'delay' => 70,
			'steps' => array(
				array(
					'httpstepid' => 1,
					'name' => 'step1',
					'timeout' => 15,
					'url' => 'url1',
					'posts' => '',
					'required' => '',
					'status_codes' => '',
					'no' => 1,
				),
				array(
					'httpstepid' => 2,
					'name' => 'step2',
					'timeout' => 15,
					'url' => 'url2',
					'posts' => '',
					'required' => '',
					'status_codes' => '',
					'no' => 2,
				),
			),
		);

		$apiHttpTest = new CHttpTest();
		$apiHttpTest->update($httpTests);

		$expectedDataSet = $this->getExpectedDataSet(__DIR__, __METHOD__);

		$queryTable = $this->getConnection()->createQueryTable('httptest',
			'SELECT * FROM httptest WHERE httptestid IN (1, 2) ORDER BY httptestid');
		$expectedTable = $expectedDataSet->getTable('httptest');
		$this->assertTablesEqual($expectedTable, $queryTable);

		$queryTable = $this->getConnection()->createQueryTable('items',
			'SELECT * FROM items ORDER BY itemid');
		$expectedTable = $expectedDataSet->getTable('items');
		$this->assertTablesEqual($expectedTable, $queryTable);
	}

	/**
	 * @covers CHttpTest::update
	 */
	public function testUpdateStepsOnTemplate() {
		$httpTests = array(
			'httptestid' => 1,
			'hostid' => 1,
			'status' => 0,
			'name' => 'HttpTestUpdate',
			'delay' => 70,
			'steps' => array(
				array(
					'httpstepid' => 1,
					'name' => 'step11',
					'timeout' => 20,
					'url' => 'url11',
					'posts' => '200',
					'required' => '',
					'status_codes' => '',
					'no' => 1,
				),
			),
		);

		$apiHttpTest = new CHttpTest();
		$apiHttpTest->update($httpTests);

		$expectedDataSet = $this->getExpectedDataSet(__DIR__, __METHOD__);

		$queryTable = $this->getConnection()->createQueryTable('httptest',
			'SELECT * FROM httptest WHERE httptestid IN (1, 2) ORDER BY httptestid');
		$expectedTable = $expectedDataSet->getTable('httptest');
		$this->assertTablesEqual($expectedTable, $queryTable);

		$queryTable = $this->getConnection()->createQueryTable('items',
			'SELECT * FROM items ORDER BY itemid');
		$expectedTable = $expectedDataSet->getTable('items');
		$this->assertTablesEqual($expectedTable, $queryTable);
	}
}
