<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class CServiceTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CService
	 */
	protected $service;

	/**
	 * @var mixed
	 */
	protected $db_errors = null;

	/**
	 * @var array
	 */
	protected static $service_names = ['Server 1','server_2'];

	/**
	 * @var array
	 */
	protected static $created_ids = [];

	/**
	 * Set up necessaries for tests (this method is called before every test)
	 */
	public function setUp() {
		API::setApiServiceFactory(new CApiServiceFactory());

		CWebUser::$data['type'] = USER_TYPE_SUPER_ADMIN;

		$this->service = new CService();

		DBconnect($this->db_errors);
	}

	/**
	 * Provides incorrect (in terms of IT services get() options) data for testIncorectGet().
	 *
	 * @return array
	 */
	public function incorrectGetProvider() {
		return [
			// options
			[
				['serviceids' => [-1, 0]]
			]
		];
	}

	/**
	 * Returns data that is passed to $this->service->create().
	 * This method should have inner arrays as much as in costomProvider().
	 *
	 * @param int $data_number	number providet from costumeProvider()
	 * @param array $names		service names
	 *
	 * @return array
	 */
	protected function createData($data_number, array $names) {
		$data = [
			['name' => $names[0], 'algorithm' => 1, 'showsla'=> 1, 'goodsla'=> 75,
				'sortorder'=> 1
			],
			['name' => $names[1], 'algorithm' => 0, 'showsla' => 0, 'sortorder' => 1]
		];

		return $data[$data_number];
	}

	/**
	 * Returns incorrect data (in terms of IT services update() options) for testIncorectUpdate().
	 * This method should have inner arrays as much as in costomProvider().
	 *
	 * @param int $data_number	number providet from costumeProvider()
	 * @param array $ids		ids that are created by testCreate()
	 *
	 * @return array
	 */
	protected function incorectUpdateData($data_number, array $ids) {
		$data = [
			// empty name
			['serviceid' => $ids[0], 'name' => '', 'goodsla'=> 75,
				'sortorder'=> 1
			],
			// goodsla is not in 0-100
			['serviceid' => $ids[1], 'name' => 'update2', 'goodsla'=>100.1]
		];

		return $data[$data_number];
	}

	/**
	 * Returns data that is passed to $this->service->update().
	 * This method should have inner arrays as much as in costomProvider().
	 *
	 * @param int $data_number	number providet from costumeProvider()
	 * @param array $ids		ids that are created by testCreate()
	 *
	 * @return array
	 */
	protected function updateData($data_number, array $ids) {
		$data = [
			['serviceid' => $ids[0], 'name' => 'update1', 'goodsla'=> 75,
				'sortorder'=> 1
			],
			['serviceid' => $ids[1], 'name' => 'update2']
		];

		return $data[$data_number];
	}

	/**
	 * Executes test multiple times with provided test execution number.
	 *
	 * @return array of arrays
	 */
	public function costomeProvider() {
		return [
			[0],
			[1]
		];
	}

	/**
	 * Tests connection to database.
	 *
	 * @test
	 */
	public function testConnection() {
		global $DB;

		$this->assertEquals($this->db_errors, null);
		$this->assertTrue(isset($DB['DB']));
	}

	/**
	 * Test $this->service->get() with incorect values.
	 *
	 * @test
	 *
	 * @dataProvider incorrectGetProvider()
	 *
	 * @depends testConnection
	 *
	 * @param array $options
	 */
	public function testIncorrectGet(array $options) {
		$result = $this->service->get($options);
		$this->assertEquals($result, []);
	}

	/**
	 * Tests $this->service->create() and stores ids in static $created_ids.
	 *
	 * @test
	 *
	 * @dataProvider costomeProvider
	 *
	 * @depends testConnection
	 *
	 * @param int $data_number
	 */
	public function testCreate($data_number) {
		$services = $this->createData($data_number, self::$service_names);

		$result = $this->service->create($services);

		$this->assertTrue(isset($result['serviceids']));
		$this->assertTrue(is_array($result['serviceids']));

		$created_ids = [];
		foreach($result['serviceids'] as $service_id) {
			self::$created_ids[] = $service_id;
			$created_ids[] = $service_id;
		}

		$this->assertNotEquals($created_ids, []);
	}

	/**
	 * Tests $this->service->get() filtering by names that are created in testCreate().
	 *
	 * @test
	 *
	 * @depends testCreate
	 */
	public function testGet() {
		$options = ['filter' => ['name' => self::$service_names]];

		$result = $this->service->get($options);

		$this->assertTrue(is_array($result));
		$this->assertNotEquals($result, []);

		$this->assertTrue(isset($result[0]['serviceid']));
	}

	/**
	 * Tests $this->service->update() with ids that are created in testCreate().
	 *
	 * @test
	 *
	 * @dataProvider costomeProvider
	 *
	 * @depends testCreate
	 *
	 * @expectedException APIException
	 *
	 * @param int $data_number
	 */
	public function testIncorectUpdate($data_number) {
		$services = $this->incorectUpdateData($data_number, self::$created_ids);

		$result = $this->service->update($services);
	}

	/**
	 * Tests $this->service->update() with ids that are created in testCreate().
	 *
	 * @test
	 *
	 * @dataProvider costomeProvider
	 *
	 * @depends testCreate
	 *
	 * @param int $data_number
	 */
	public function testUpdate($data_number) {
		$services = $this->updateData($data_number, self::$created_ids);

		$result = $this->service->update($services);

		$this->assertTrue(is_array($result));

		$this->assertTrue(isset($result['serviceids']));
		$this->assertNotEquals($result['serviceids'], []);
	}

	/**
	 * Tests $this->service->delete() with ids that are created in testCreate().
	 *
	 * @test
	 *
	 * @depends testCreate
	 */
	public function testDelete() {
		$result = $this->service->delete(self::$created_ids);

		$this->assertTrue(is_array($result));

		$this->assertTrue(isset($result['serviceids']));
		$this->assertNotEquals($result['serviceids'], []);
	}
}
