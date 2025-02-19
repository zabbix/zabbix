<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup services
 */
class testAuditlogService extends testAuditlogCommon {

	/**
	 * Created service ID.
	 */
	protected static $resourceid;

	/**
	 * Created service status rule ID (before update).
	 */
	protected static $status_ruleid;

	/**
	 * Created service tag ID (before update).
	 */
	protected static $tagid;

	/**
	 * Created service problems tag ID (before update).
	 */
	protected static $problem_tagid;

	public function testAuditlogService_Create() {
		$create = $this->call('service.create', [
			[
				'name' => 'Created service 1',
				'algorithm' => 1,
				'sortorder' => 1,
				'weight' => 1,
				'propagation_rule' => 1,
				'propagation_value' => 3,
				'description' => 'Description of created service',
				'uuid' => '1ae9f2504d2d45f9b17c4fc6c3c61000',
				'status_rules' => [
					[
						'type' => 1,
						'limit_value' => 1,
						'limit_status' => 2,
						'new_status' => 3
					]
				],
				'tags' => [
					[
						'tag' => 'tag1',
						'value' => 'value1'
					]
				],
				'problem_tags' => [
					[
						'tag' => 'prob_tag_1',
						'operator' => 2,
						'value' => 'prob_val_1'
					]
				]
			]
		]);

		self::$resourceid = $create['result']['serviceids'][0];
		self::$status_ruleid = CDBHelper::getRow('SELECT service_status_ruleid FROM service_status_rule WHERE serviceid='.
				zbx_dbstr(self::$resourceid)
		);
		self::$tagid = CDBHelper::getRow('SELECT servicetagid FROM service_tag WHERE serviceid='.zbx_dbstr(self::$resourceid));
		self::$problem_tagid = CDBHelper::getRow('SELECT service_problem_tagid FROM service_problem_tag WHERE serviceid='.
				zbx_dbstr(self::$resourceid)
		);

		$created = json_encode([
			'service.name' => ['add', 'Created service 1'],
			'service.algorithm' => ['add', '1'],
			'service.sortorder' => ['add', '1'],
			'service.weight' => ['add', '1'],
			'service.propagation_rule' => ['add', '1'],
			'service.propagation_value' => ['add', '3'],
			'service.description' => ['add', 'Description of created service'],
			'service.uuid' => ['add', '1ae9f2504d2d45f9b17c4fc6c3c61000'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].']' => ['add'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].'].type' => ['add', '1'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].'].limit_value' => ['add', '1'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].'].limit_status' => ['add', '2'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].'].new_status' => ['add', '3'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].'].service_status_ruleid'
					=> ['add', self::$status_ruleid['service_status_ruleid']],
			'service.tags['.self::$tagid['servicetagid'].']' => ['add'],
			'service.tags['.self::$tagid['servicetagid'].'].tag' => ['add', 'tag1'],
			'service.tags['.self::$tagid['servicetagid'].'].value' => ['add', 'value1'],
			'service.tags['.self::$tagid['servicetagid'].'].servicetagid' => ['add', self::$tagid['servicetagid']],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].']' => ['add'],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].'].tag' => ['add', 'prob_tag_1'],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].'].operator' => ['add', '2'],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].'].value' => ['add', 'prob_val_1'],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].'].service_problem_tagid'
					=> ['add', self::$problem_tagid['service_problem_tagid']],
			'service.serviceid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogService_Create
	 */
	public function testAuditlogService_Update() {
		$this->call('service.update', [
			[
				'serviceid' => self::$resourceid,
				'name' => 'Update service audit',
				'algorithm' => 2,
				'sortorder' => 55,
				'weight' => 100,
				'propagation_rule' => 4,
				'propagation_value' => 2,
				'description' => 'Updated description',
				'status_rules' => [
					[
						'type' => 2,
						'limit_value' => 50,
						'limit_status' => 3,
						'new_status' => 4
					]
				],
				'tags' => [
					[
						'tag' => 'tag2',
						'value' => 'value2'
					]
				],
				'problem_tags' => [
					[
						'tag' => 'prob_tag_2',
						'operator' => 0,
						'value' => 'prob_val_2'
					]
				]
			]
		]);

		$upd_status_ruleid = CDBHelper::getRow('SELECT service_status_ruleid FROM service_status_rule WHERE serviceid='.
				zbx_dbstr(self::$resourceid)
		);
		$upd_tagid = CDBHelper::getRow('SELECT servicetagid FROM service_tag WHERE serviceid='.zbx_dbstr(self::$resourceid));
		$upd_problem_tagid = CDBHelper::getRow('SELECT service_problem_tagid FROM service_problem_tag WHERE serviceid='.
				zbx_dbstr(self::$resourceid)
		);

		$updated = json_encode([
			'service.tags['.self::$tagid['servicetagid'].']' => ['delete'],
			'service.problem_tags['.self::$problem_tagid['service_problem_tagid'].']' => ['delete'],
			'service.status_rules['.self::$status_ruleid['service_status_ruleid'].']' => ['delete'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].']' => ['add'],
			'service.tags['.$upd_tagid['servicetagid'].']' => ['add'],
			'service.problem_tags['.$upd_problem_tagid['service_problem_tagid'].']' => ['add'],
			'service.name' => ['update', 'Update service audit','Created service 1'],
			'service.algorithm' => ['update', '2', '1'],
			'service.sortorder' => ['update', '55', '1'],
			'service.weight' => ['update', '100', '1'],
			'service.propagation_rule' => ['update', '4', '1'],
			'service.propagation_value' => ['update', '2', '3'],
			'service.description' => ['update', 'Updated description','Description of created service'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].'].type' => ['add', '2'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].'].limit_value' => ['add', '50'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].'].limit_status' => ['add', '3'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].'].new_status' => ['add', '4'],
			'service.status_rules['.$upd_status_ruleid['service_status_ruleid'].'].service_status_ruleid'
					=> ['add', $upd_status_ruleid['service_status_ruleid']],
			'service.tags['.$upd_tagid['servicetagid'].'].tag' => ['add', 'tag2'],
			'service.tags['.$upd_tagid['servicetagid'].'].value' => ['add', 'value2'],
			'service.tags['.$upd_tagid['servicetagid'].'].servicetagid' => ['add', $upd_tagid['servicetagid']],
			'service.problem_tags['.$upd_problem_tagid['service_problem_tagid'].'].tag' => ['add', 'prob_tag_2'],
			'service.problem_tags['.$upd_problem_tagid['service_problem_tagid'].'].value' => ['add', 'prob_val_2'],
			'service.problem_tags['.$upd_problem_tagid['service_problem_tagid'].'].service_problem_tagid'
					=> ['add', $upd_problem_tagid['service_problem_tagid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogService_Create
	 */
	public function testAuditlogService_Delete() {
		$this->call('service.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Update service audit', self::$resourceid);
	}
}
