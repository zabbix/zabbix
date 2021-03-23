<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


/**
 * Class containing methods for operations with scheduled reports.
 */
class CReport extends CApiService {

	public const ACCESS_RULES = [
		'get' => [
			'min_user_type' => USER_TYPE_ZABBIX_ADMIN
		],
		'create' => [
			'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
			'action' => CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS
		],
		'update' => [
			'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
			'action' => CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS
		],
		'delete' => [
			'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
			'action' => CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS
		]
	];

	protected $tableName = 'report';
	protected $tableAlias = 'r';
	protected $sortColumns = ['reportid', 'name', 'status'];

	protected $output_fields = ['reportid', 'userid', 'name', 'description', 'status', 'dashboardid', 'period', 'cycle',
		'weekdays', 'start_time', 'active_since', 'active_till', 'state', 'lastsent', 'error', 'subject', 'message'
	];
	protected $user_output_fields = ['userid', 'access_userid', 'exclude'];
	protected $usrgrp_output_fields = ['usrgrpid', 'access_userid'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'reportids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'expired' =>				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'reportid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'userid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'dashboardid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED],
				'state' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_REPORT_STATE_UNKNOWN, ZBX_REPORT_STATE_SENT, ZBX_REPORT_STATE_ERROR])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $this->user_output_fields), 'default' => null],
			'selectUserGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $this->usrgrp_output_fields), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select'	=> ['report' => 'r.reportid'],
			'from'		=> ['report' => 'report r'],
			'where'		=> [],
			'order'		=> [],
			'group'		=> []
		];

		// reportids
		if ($options['reportids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('r.reportid', $options['reportids']);
		}

		// expired
		if ($options['expired'] !== null) {
			$sql_parts['where'][] = $options['expired']
				? '(r.active_till>0 AND r.active_till<'.strtotime('tomorrow UTC').')'
				: '(r.active_till=0 OR r.active_till>='.strtotime('tomorrow UTC').')';
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('report r', $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('report r', $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_reports = [];
		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_reports[$row['reportid']] = $row;
		}

		if ($db_reports) {
			$db_reports = $this->addRelatedObjects($options, $db_reports);
			$db_reports = $this->unsetExtraFields($db_reports, ['reportid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_reports = array_values($db_reports);
			}
		}

		return $db_reports;
	}

	/**
	 * @param array $reports
	 *
	 * @return array
	 */
	public function create(array $reports): array {
		$this->validateCreate($reports);

		$ins_reports = [];

		foreach ($reports as $report) {
			unset($report['subject'], $report['message'], $report['users'], $report['user_groups']);
			$ins_reports[] = $report;
		}

		$reportids = DB::insert('report', $ins_reports);

		foreach ($reports as $index => &$report) {
			$report['reportid'] = $reportids[$index];
		}
		unset($report);

		$this->updateParams($reports, __FUNCTION__);
		$this->updateUsers($reports, __FUNCTION__);
		$this->updateUserGroups($reports, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_SCHEDULED_REPORT, $reports);

		return ['reportids' => $reportids];
	}

	/**
	 * @param array $reports
	 *
	 * @throws APIException if no permissions or the input is invalid.
	 */
	protected function validateCreate(array &$reports): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'userid' =>				['type' => API_ID, 'default' => self::$userData['userid']],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('report', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report', 'description')],
			'status' =>				['type' => API_INT32, 'in' => ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED],
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'period' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR])],
			'cycle' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY])],
			'weekdays' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'cycle', 'in' => ZBX_REPORT_CYCLE_DAILY.','.ZBX_REPORT_CYCLE_WEEKLY], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:127', 'default' => 127],
										['if' => ['field' => 'cycle', 'in' => ZBX_REPORT_CYCLE_MONTHLY.','.ZBX_REPORT_CYCLE_YEARLY], 'type' => API_INT32, 'in' => 0]
			]],
			'start_time' =>			['type' => API_INT32, 'in' => '0:86340'],
			'active_since' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE, 'default' => DB::getDefault('report', 'active_since')],
			'active_till' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE, 'default' => DB::getDefault('report', 'active_till')],
			// The length of the "report.subject" and "media_type_message.subject" fields should match.
			'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
			'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report_param', 'value')],
			'users' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'default' => [], 'fields' => [
				'userid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID],
				'exclude' =>			['type' => API_INT32, 'in' => ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE]
			]],
			'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['usrgrpid']], 'default' => [], 'fields' => [
				'usrgrpid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $reports, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($reports as $index => $report) {
			if ($report['active_till'] > 0 && $report['active_since'] > $report['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('"%1$s" must be greater than "%2$s or equal to "%3$s".', 'active_till', 'active_since', 0)
				);
			}

			if ($report['active_since'] > 0) {
				$day_start_timestamp = (new DateTime('@'.$report['active_since']))
					->setTime(0,0)
					->getTimestamp();

				if ($report['active_since'] != $day_start_timestamp) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'active_since',
						_s('must be a timestamp representing the beginning of a particular day (00:00:00).')
					));
				}
			}

			if ($report['active_till'] > 0) {
				$day_end_timestamp = (new DateTime('@'.$report['active_till']))
					->setTime(23,59,59)
					->getTimestamp();

				if ($report['active_till'] != $day_end_timestamp) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'active_till', _s('must be a timestamp representing the end of a particular day (23:59:59).')
					));
				}
			}

			if (!$report['users'] && !$report['user_groups']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one user or user group must be specified.'));
			}
		}

		$this->checkDuplicates(array_column($reports, 'name'));
	}

	/**
	 * Check for duplicated reports.
	 *
	 * @param array $names
	 *
	 * @throws APIException if report already exists.
	 */
	protected function checkDuplicates(array $names): void {
		$db_reports = DB::select('report', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_reports) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Report "%1$s" already exists.', $db_reports[0]['name']));
		}
	}

	/**
	 * @param array $reports
	 *
	 * @return array
	 */
	public function update(array $reports): array {
		$this->validateUpdate($reports, $db_reports);

		$upd_reports = [];

		foreach ($reports as $report) {
			$db_report = $db_reports[$report['reportid']];

			$upd_report = [];

			foreach (['userid', 'status', 'dashboardid', 'period', 'cycle', 'weekdays', 'start_time', 'active_since',
					'active_till'] as $field_name) {
				if (array_key_exists($field_name, $report) && $report[$field_name] != $db_report[$field_name]) {
					$upd_report[$field_name] = $report[$field_name];
				}
			}

			foreach (['name', 'description'] as $field_name) {
				if (array_key_exists($field_name, $report) && $report[$field_name] !== $db_report[$field_name]) {
					$upd_report[$field_name] = $report[$field_name];
				}
			}

			if ($upd_report) {
				$upd_reports[] = [
					'values' => $upd_report,
					'where' => ['reportid' => $report['reportid']]
				];
			}
		}

		if ($upd_reports) {
			DB::update('report', $upd_reports);
		}

		$this->updateParams($reports, __FUNCTION__);
		$this->updateUsers($reports, __FUNCTION__);
		$this->updateUserGroups($reports, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCHEDULED_REPORT, $reports, $db_reports);

		return ['reportids' => array_column($reports, 'reportid')];
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException if no permissions or the input is invalid.
	 */
	protected function validateUpdate(array &$reports, ?array &$db_reports = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'reportid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userid' =>				['type' => API_ID],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('report', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report', 'description')],
			'status' =>				['type' => API_INT32, 'in' => ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED],
			'dashboardid' =>		['type' => API_ID],
			'period' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR])],
			'cycle' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY])],
			'weekdays' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'cycle', 'in' => ZBX_REPORT_CYCLE_DAILY.','.ZBX_REPORT_CYCLE_WEEKLY], 'type' => API_INT32, 'in' => '1:127'],
										['if' => ['field' => 'cycle', 'in' => ZBX_REPORT_CYCLE_MONTHLY.','.ZBX_REPORT_CYCLE_YEARLY], 'type' => API_INT32, 'in' => '0']
			]],
			'start_time' =>			['type' => API_INT32, 'in' => '0:86340'],
			'active_since' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE],
			'active_till' =>		['type' => API_INT32, 'in' => '0:'.ZBX_MAX_DATE],
			// The length of the "report.subject" and "media_type_message.subject" fields should match.
			'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
			'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report_param', 'value')],
			'users' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID],
				'exclude' =>			['type' => API_INT32, 'in' => ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE]
			]],
			'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $reports, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_reports = $this->get([
			'output' => $this->output_fields,
			'selectUsers' => $this->user_output_fields,
			'selectUserGroups' => $this->usrgrp_output_fields,
			'reportids' => array_column($reports, 'reportid'),
			'preservekeys' => true
		]);

		if (count($reports) != count($db_reports)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$names = [];

		foreach ($reports as $report) {
			$db_report = $db_reports[$report['reportid']];

			if (array_key_exists('name', $report) && $report['name'] !== $db_report['name']) {
				$names[] = $report['name'];
			}

			$active_since = $db_report['active_since'];
			if (array_key_exists('active_since', $report) && $report['active_since'] > 0) {
				$day_start_timestamp = (new DateTime('@'.$report['active_since']))
					->setTime(0,0)
					->getTimestamp();

				if ($report['active_since'] != $day_start_timestamp) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'active_since',
						_s('must be a timestamp representing the beginning of a particular day (00:00:00).')
					));
				}

				$active_since = $report['active_since'];
			}

			$active_till = $db_report['active_till'];
			if (array_key_exists('active_till', $report) && $report['active_till'] > 0) {
				$day_end_timestamp = (new DateTime('@'.$report['active_till']))
					->setTime(23,59,59)
					->getTimestamp();

				if ($report['active_till'] != $day_end_timestamp) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'active_till', _s('must be a timestamp representing the end of a particular day (23:59:59).')
					));
				}

				$active_till = $report['active_till'];
			}

			if ($active_till > 0 && $active_since > $active_till) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('"%1$s" must be greater than "%2$s or equal to "%3$s".', 'active_till', 'active_since', 0)
				);
			}

			$report_users = array_key_exists('users', $report) ? $report['users'] : $db_report['users'];
			$report_user_groups = array_key_exists('user_groups', $report)
				? $report['user_groups']
				: $db_report['user_groups'];

			if (!$report_users && !$report_user_groups) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one user or user group must be specified.'));
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}
	}

	/**
	 * Update table "report_param".
	 *
	 * @param array  $reports
	 * @param string $method
	 */
	protected function updateParams(array $reports, string $method): void {
		$params_by_name = [
			'subject' => 'subject',
			'body' => 'message'
		];
		$report_params = [];

		foreach ($reports as $report) {
			$report_params[$report['reportid']] = [];

			foreach ($params_by_name as $name => $param) {
				if (array_key_exists($param, $report) && $report[$param] !== '') {
					$report_params[$report['reportid']][] = [
						'name' => $name,
						'value' => $report[$param]
					];
				}
			}
		}

		if (!$report_params) {
			return;
		}

		$db_report_params = ($method === 'update')
			? DB::select('report_param', [
				'output' => ['reportparamid', 'reportid', 'name', 'value'],
				'filter' => ['reportid' => array_keys($report_params)]
			])
			: [];

		$ins_report_params = [];
		$upd_report_params = [];
		$del_reportparamids = [];

		foreach ($db_report_params as $db_report_param) {
			if ($report_params[$db_report_param['reportid']]) {
				$report_param = array_shift($report_params[$db_report_param['reportid']]);

				$upd_report_param = [];

				foreach (['name', 'value'] as $field_name) {
					if (array_key_exists($field_name, $report_param)
							&& $report_param[$field_name] !== $db_report_param[$field_name]) {
						$upd_report_param[$field_name] = $report_param[$field_name];
					}
				}

				if ($upd_report_param) {
					$upd_report_params[] = [
						'values' => $upd_report_param,
						'where' => ['reportparamid' => $db_report_param['reportparamid']]
					];
				}
			}
			else {
				$del_reportparamids[] = $db_report_param['reportparamid'];
			}
		}

		foreach ($report_params as $reportid => $report_param) {
			foreach ($report_param as $param) {
				$ins_report_params[] = [
					'reportid' => $reportid,
					'name' => $param['name'],
					'value' => $param['value']
				];
			}
		}

		if ($ins_report_params) {
			DB::insertBatch('report_param', $ins_report_params);
		}

		if ($upd_report_params) {
			DB::update('report_param', $upd_report_params);
		}

		if ($del_reportparamids) {
			DB::delete('report_param', ['reportparamid' => $del_reportparamids]);
		}
	}

	/**
	 * Update table "report_user".
	 *
	 * @param array  $reports
	 * @param string $method
	 */
	protected function updateUsers(array $reports, string $method): void {
		$report_users = [];

		foreach ($reports as $report) {
			if (array_key_exists('users', $report)) {
				$report_users[$report['reportid']] = $report['users'];
			}
		}

		if (!$report_users) {
			return;
		}

		$db_report_users = ($method === 'update')
			? DB::select('report_user', [
				'output' => ['reportuserid', 'reportid', 'userid', 'access_userid', 'exclude'],
				'filter' => ['reportid' => array_keys($report_users)]
			])
			: [];

		$ins_report_users = [];
		$upd_report_users = [];
		$del_reportuserids = [];

		foreach ($db_report_users as $db_report_user) {
			if ($report_users[$db_report_user['reportid']]) {
				$report_user = array_shift($report_users[$db_report_user['reportid']]);

				$upd_report_user = [];

				foreach (['userid', 'access_userid', 'exclude'] as $field_name) {
					if (array_key_exists($field_name, $report_user)
							&& $report_user[$field_name] != $db_report_user[$field_name]) {
						$upd_report_user[$field_name] = $report_user[$field_name];
					}
				}

				if ($upd_report_user) {
					$upd_report_users[] = [
						'values' => $upd_report_user,
						'where' => ['reportuserid' => $db_report_user['reportuserid']]
					];
				}
			}
			else {
				$del_reportuserids[] = $db_report_user['reportuserid'];
			}
		}

		foreach ($report_users as $reportid => $users) {
			foreach ($users as $user) {
				$ins_report_users[] = ['reportid' => $reportid] + $user;
			}
		}

		if ($ins_report_users) {
			DB::insertBatch('report_user', $ins_report_users);
		}

		if ($upd_report_users) {
			DB::update('report_user', $upd_report_users);
		}

		if ($del_reportuserids) {
			DB::delete('report_user', ['reportuserid' => $del_reportuserids]);
		}
	}

	/**
	 * Update table "report_usrgrp".
	 *
	 * @param array  $reports
	 * @param string $method
	 */
	protected function updateUserGroups(array $reports, string $method): void {
		$report_usrgrps = [];

		foreach ($reports as $report) {
			if (array_key_exists('user_groups', $report)) {
				$report_usrgrps[$report['reportid']] = $report['user_groups'];
			}
		}

		if (!$report_usrgrps) {
			return;
		}

		$db_report_usrgrps = ($method === 'update')
			? DB::select('report_usrgrp', [
				'output' => ['reportusrgrpid', 'reportid', 'usrgrpid', 'access_userid'],
				'filter' => ['reportid' => array_keys($report_usrgrps)]
			])
			: [];

		$ins_report_usrgrps = [];
		$upd_report_usrgrps = [];
		$del_reportusrgrpids = [];

		foreach ($db_report_usrgrps as $db_report_usrgrp) {
			if ($report_usrgrps[$db_report_usrgrp['reportid']]) {
				$report_usrgrp = array_shift($report_usrgrps[$db_report_usrgrp['reportid']]);

				$upd_report_usrgrp = [];

				foreach (['usrgrpid', 'access_userid'] as $field_name) {
					if (array_key_exists($field_name, $report_usrgrp)
							&& $report_usrgrp[$field_name] != $db_report_usrgrp[$field_name]) {
						$upd_report_usrgrp[$field_name] = $report_usrgrp[$field_name];
					}
				}

				if ($upd_report_usrgrp) {
					$upd_report_usrgrps[] = [
						'values' => $upd_report_usrgrp,
						'where' => ['reportusrgrpid' => $db_report_usrgrp['reportusrgrpid']]
					];
				}
			}
			else {
				$del_reportusrgrpids[] = $db_report_usrgrp['reportusrgrpid'];
			}
		}

		foreach ($report_usrgrps as $reportid => $usrgrps) {
			foreach ($usrgrps as $usrgrp) {
				$ins_report_usrgrps[] = ['reportid' => $reportid] + $usrgrp;
			}
		}

		if ($ins_report_usrgrps) {
			DB::insertBatch('report_usrgrp', $ins_report_usrgrps);
		}

		if ($upd_report_usrgrps) {
			DB::update('report_usrgrp', $upd_report_usrgrps);
		}

		if ($del_reportusrgrpids) {
			DB::delete('report_usrgrp', ['reportusrgrpid' => $del_reportusrgrpids]);
		}
	}

	/**
	 * @param array $reportids
	 *
	 * @return array
	 */
	public function delete(array $reportids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $reportids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_reports = $this->get([
			'output' => [],
			'reportids' => $reportids,
			'preservekeys' => true
		]);

		foreach ($reportids as $reportid) {
			if (!array_key_exists($reportid, $db_reports)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		DB::delete('report', ['reportid' => $reportids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCHEDULED_REPORT, $db_reports);

		return ['reportids' => $reportids];
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$reportids = array_keys($result);

		// adding email subject and message
		$fields_by_name = [];
		if ($this->outputIsRequested('subject', $options['output'])) {
			$fields_by_name['subject'] = 'subject';
		}
		if ($this->outputIsRequested('message', $options['output'])) {
			$fields_by_name['body'] = 'message';
		}

		if ($fields_by_name) {
			foreach ($result as &$report) {
				foreach ($fields_by_name as $field) {
					$report[$field] = '';
				}
			}
			unset($report);

			$params = DBselect(
				'SELECT rp.reportid,rp.name,rp.value'.
				' FROM report_param rp'.
				' WHERE '.dbConditionInt('rp.reportid', $reportids)
			);

			while ($param = DBfetch($params)) {
				if (array_key_exists($param['name'], $fields_by_name)) {
					$result[$param['reportid']][$fields_by_name[$param['name']]] = $param['value'];
				}
			}
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] !== API_OUTPUT_COUNT) {
			if ($options['selectUsers'] === API_OUTPUT_EXTEND) {
				$options['selectUsers'] = $this->user_output_fields;
			}

			foreach ($result as &$report) {
				$report['users'] = [];
			}
			unset($report);

			if ($options['selectUsers']) {
				$output_fields = [
					$this->fieldId('reportid', 'ru')
				];
				foreach ($options['selectUsers'] as $field) {
					$output_fields[$field] = $this->fieldId($field, 'ru');
				}

				$users = DBselect(
					'SELECT '.implode(',', $output_fields).
					' FROM report_user ru'.
					' WHERE '.dbConditionInt('reportid', $reportids)
				);

				while ($user = DBfetch($users)) {
					$reportid = $user['reportid'];
					unset($user['reportid']);
					$result[$reportid]['users'][] = $user;
				}
			}
		}

		// adding user groups
		if ($options['selectUserGroups'] !== null && $options['selectUserGroups'] !== API_OUTPUT_COUNT) {
			if ($options['selectUserGroups'] === API_OUTPUT_EXTEND) {
				$options['selectUserGroups'] = $this->usrgrp_output_fields;
			}

			foreach ($result as &$report) {
				$report['user_groups'] = [];
			}
			unset($report);

			if ($options['selectUserGroups']) {
				$output_fields = [
					$this->fieldId('reportid', 'rug')
				];
				foreach ($options['selectUserGroups'] as $field) {
					$output_fields[$field] = $this->fieldId($field, 'rug');
				}

				$user_groups = DBselect(
					'SELECT '.implode(',', $output_fields).
					' FROM report_usrgrp rug'.
					' WHERE '.dbConditionInt('reportid', $reportids)
				);

				while ($user_group = DBfetch($user_groups)) {
					$reportid = $user_group['reportid'];
					unset($user_group['reportid']);
					$result[$reportid]['user_groups'][] = $user_group;
				}
			}
		}

		return $result;
	}
}
