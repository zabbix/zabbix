<?php declare(strict_types = 0);
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

	private $output_fields = ['reportid', 'userid', 'name', 'description', 'status', 'dashboardid', 'period', 'cycle',
		'weekdays', 'start_time', 'active_since', 'active_till', 'state', 'lastsent', 'info', 'subject', 'message'
	];
	private $user_output_fields = ['userid', 'access_userid', 'exclude'];
	private $usrgrp_output_fields = ['usrgrpid', 'access_userid'];

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
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['reportid', 'userid', 'name', 'dashboardid', 'status', 'state']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
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

		$sql_parts = $this->createSelectQueryParts($this->tableName(), $this->tableAlias(), $options);

		// expired
		if ($options['expired'] !== null) {
			$sql_parts['where'][] = $options['expired']
				? '(r.active_till>0 AND r.active_till<'.time().')'
				: '(r.active_till=0 OR r.active_till>='.time().')';
		}

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
		self::validateCreate($reports);

		$reportids = DB::insert('report', $reports);

		foreach ($reports as $index => &$report) {
			$report['reportid'] = $reportids[$index];
		}
		unset($report);

		self::updateParams($reports, __FUNCTION__);
		self::updateUsers($reports);
		self::updateUserGroups($reports);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_SCHEDULED_REPORT, $reports);

		return ['reportids' => $reportids];
	}

	/**
	 * @param array $reports
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$reports): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'userid' =>				['type' => API_ID, 'default' => self::$userData['userid']],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('report', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report', 'description')],
			'status' =>				['type' => API_INT32, 'in' => ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED],
			'dashboardid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'period' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR])],
			'cycle' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY]), 'default' => DB::getDefault('report', 'cycle')],
			'weekdays' =>			['type' => API_INT32],
			'start_time' =>			['type' => API_INT32, 'in' => '0:86340'],
			'active_since' =>		['type' => API_DATE, 'default' => ''],
			'active_till' =>		['type' => API_DATE, 'default' => ''],
			// The length of the "report.subject" and "media_type_message.subject" fields should match.
			'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
			'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report_param', 'value')],
			'users' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'default' => [], 'fields' => [
				'userid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID],
				'exclude' =>			['type' => API_INT32, 'in' => ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE, 'default' => DB::getDefault('report_user', 'exclude')]
			]],
			'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['usrgrpid']], 'default' => [], 'fields' => [
				'usrgrpid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $reports, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($reports as $i => &$report) {
			if ($report['cycle'] == ZBX_REPORT_CYCLE_WEEKLY) {
				if (!array_key_exists('weekdays', $report)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('the parameter "%1$s" is missing', 'weekdays')
					));
				}

				if ($report['weekdays'] < 1 || $report['weekdays'] > 127) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/weekdays', _s('value must be one of %1$s', '1-127')
					));
				}
			}
			elseif (array_key_exists('weekdays', $report) && $report['weekdays'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i + 1).'/weekdays', _s('value must be %1$s', '0')
				));
			}

			$report['active_since'] = ($report['active_since'] !== '')
				? $report['active_since'] = (DateTime::createFromFormat(ZBX_DATE, $report['active_since'],
					new DateTimeZone('UTC')
				))
					->setTime(0, 0)
					->getTimestamp()
				: 0;

			$report['active_till'] = ($report['active_till'] !== '')
				? $report['active_till'] = (DateTime::createFromFormat(ZBX_DATE, $report['active_till'],
					new DateTimeZone('UTC')
				))
					->setTime(23, 59, 59)
					->getTimestamp()
				: 0;

			if ($report['active_till'] > 0 && $report['active_since'] > $report['active_till']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('"%1$s" must be an empty string or greater than "%2$s".', 'active_till', 'active_since')
				);
			}
		}
		unset($report);

		self::checkDuplicates($reports);
		self::checkDashboards($reports);
		self::checkUsers($reports);
		self::checkUserGroups($reports);
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $reports, array $db_reports = null): void {
		$names = [];

		foreach ($reports as $report) {
			if (!array_key_exists('name', $report)) {
				continue;
			}

			if ($db_reports === null || $report['name'] !== $db_reports[$report['reportid']]['name']) {
				$names[] = $report['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('report', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Report "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException
	 */
	private static function checkDashboards(array $reports, array $db_reports = null): void {
		$dashboardids = [];

		foreach ($reports as $i => $report) {
			if ($db_reports === null) {
				$dashboardids[$report['dashboardid']] = true;

				continue;
			}

			if (!array_key_exists('dashboardid', $report)
					|| bccomp($report['dashboardid'], $db_reports[$report['reportid']]['dashboardid']) == 0) {
				continue;
			}

			// When changing the dashboard, new lists of users and user groups must be provided.

			if (!array_key_exists('users', $report)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_s('the parameter "%1$s" is missing', 'users')
				));
			}

			if (!array_key_exists('user_groups', $report)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_s('the parameter "%1$s" is missing', 'user_groups')
				));
			}

			$dashboardids[$report['dashboardid']] = true;
		}

		if (!$dashboardids) {
			return;
		}

		$dashboardids = array_keys($dashboardids);

		$db_dashboards = API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $dashboardids,
			'preservekeys' => true
		]);

		foreach ($dashboardids as $dashboardid) {
			if (!array_key_exists($dashboardid, $db_dashboards)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Dashboard with ID "%1$s" is not available.', $dashboardid)
				);
			}
		}
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException
	 */
	private static function checkUsers(array $reports, array $db_reports = null): void {
		$userids = [];

		foreach ($reports as $report) {
			$db_report = [];
			$users = array_key_exists('users', $report) ? $report['users'] : [];
			$user_groups = array_key_exists('user_groups', $report) ? $report['user_groups'] : [];

			if ($db_reports !== null) {
				$db_report = $db_reports[$report['reportid']];

				if (!array_key_exists('users', $report)) {
					$users = $db_report['users'];
				}
				if (!array_key_exists('user_groups', $report)) {
					$user_groups = $db_report['user_groups'];
				}
			}

			if (array_key_exists('userid', $report) && (!$db_report || $report['userid'] != $db_report['userid'])) {
				if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
					if ((!$db_report && $report['userid'] != self::$userData['userid'])
							|| ($db_report && $report['userid'] != $db_report['userid'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Only super admins can set report owner.'));
					}
				}

				$userids[$report['userid']] = true;
			}

			if (!$user_groups) {
				if (!$users) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('At least one user or user group must be specified.'));
				}

				if (!array_key_exists(ZBX_REPORT_EXCLUDE_USER_FALSE, array_column($users, 'exclude', 'exclude'))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('If no user groups are specified, at least one user must be included in the mailing list.')
					);
				}
			}

			if (array_key_exists('users', $report) && $report['users']) {
				$db_userids = [];
				$db_access_userids = [];
				if ($db_report) {
					$db_userids = array_flip(array_column($db_report['users'], 'userid'));
					$db_access_userids = array_flip(array_column($db_report['users'], 'access_userid'));
				}

				foreach ($report['users'] as $user) {
					if (!array_key_exists($user['userid'], $db_userids)) {
						$userids[$user['userid']] = true;
					}

					if (array_key_exists('access_userid', $user) && $user['access_userid'] != 0
							&& !array_key_exists($user['access_userid'], $db_access_userids)) {
						$userids[$user['access_userid']] = true;
					}
				}
			}

			if (array_key_exists('user_groups', $report) && $report['user_groups']) {
				$db_access_userids = $db_report
					? array_flip(array_column($db_report['user_groups'], 'access_userid'))
					: [];

				foreach ($report['user_groups'] as $usrgrp) {
					if (array_key_exists('access_userid', $usrgrp) && $usrgrp['access_userid'] != 0
							&& !array_key_exists($usrgrp['access_userid'], $db_access_userids)) {
						$userids[$usrgrp['access_userid']] = true;
					}
				}
			}
		}

		unset($userids[self::$userData['userid']]);

		if (!$userids) {
			return;
		}

		$userids = array_keys($userids);

		$db_users = API::User()->get([
			'output' => [],
			'userids' => $userids,
			'preservekeys' => true
		]);

		foreach ($userids as $userid) {
			if (!array_key_exists($userid, $db_users)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with ID "%1$s" is not available.', $userid));
			}
		}
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException
	 */
	private static function checkUserGroups(array $reports, array $db_reports = null): void {
		$usrgrpids = [];

		foreach ($reports as $report) {
			if (array_key_exists('user_groups', $report) && $report['user_groups']) {
				$db_usrgrpids = $db_reports !== null
					? array_flip(array_column($db_reports[$report['reportid']]['user_groups'], 'usrgrpid'))
					: [];

				foreach ($report['user_groups'] as $usrgrp) {
					if (!array_key_exists($usrgrp['usrgrpid'], $db_usrgrpids)) {
						$usrgrpids[$usrgrp['usrgrpid']] = true;
					}
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = API::UserGroup()->get([
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
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
			$upd_report = DB::getUpdatedValues('report', $report, $db_reports[$report['reportid']]);

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

		self::updateParams($reports, __FUNCTION__);
		self::updateUsers($reports, $db_reports);
		self::updateUserGroups($reports, $db_reports);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SCHEDULED_REPORT, $reports, $db_reports);

		return ['reportids' => array_column($reports, 'reportid')];
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$reports, ?array &$db_reports): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'reportid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'userid' =>				['type' => API_ID],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('report', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report', 'description')],
			'status' =>				['type' => API_INT32, 'in' => ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED],
			'dashboardid' =>		['type' => API_ID],
			'period' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR])],
			'cycle' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY])],
			'weekdays' =>			['type' => API_INT32],
			'start_time' =>			['type' => API_INT32, 'in' => '0:86340'],
			'active_since' =>		['type' => API_DATE],
			'active_till' =>		['type' => API_DATE],
			// The length of the "report.subject" and "media_type_message.subject" fields should match.
			'subject' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('media_type_message', 'subject')],
			'message' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('report_param', 'value')],
			'users' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'access_userid' =>		['type' => API_ID],
				'exclude' =>			['type' => API_INT32, 'in' => ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE, 'default' => DB::getDefault('report_user', 'exclude')]
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
			'output' => ['reportid', 'userid', 'name', 'description', 'status', 'dashboardid', 'period', 'cycle',
				'weekdays', 'start_time', 'subject', 'message'
			],
			'reportids' => array_column($reports, 'reportid'),
			'preservekeys' => true
		]);

		if (count($reports) != count($db_reports)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		// Get raw values of "active_*" fields.
		$options = [
			'output' => ['reportid', 'active_since', 'active_till'],
			'reportids' => array_keys($db_reports)
		];
		$db_reports_active_fields = DBselect(DB::makeSql('report', $options));

		while ($db_report_active_fields = DBfetch($db_reports_active_fields)) {
			$db_reports[$db_report_active_fields['reportid']] +=
				array_diff_key($db_report_active_fields, array_flip(['reportid']));
		}

		foreach ($reports as $i => &$report) {
			$db_report = $db_reports[$report['reportid']];

			if (array_key_exists('cycle', $report) || array_key_exists('weekdays', $report)) {
				$cycle = array_key_exists('cycle', $report) ? $report['cycle'] : $db_report['cycle'];
				$weekdays = array_key_exists('weekdays', $report) ? $report['weekdays'] : $db_report['weekdays'];

				if ($cycle == ZBX_REPORT_CYCLE_WEEKLY) {
					if ($weekdays < 1 || $weekdays > 127) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($i + 1).'/weekdays', _s('value must be one of %1$s', '1-127')
						));
					}
				}
				elseif ($weekdays != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1).'/weekdays', _s('value must be %1$s', '0')
					));
				}
			}

			if (array_key_exists('active_since', $report) || array_key_exists('active_till', $report)) {
				$active_since = $db_report['active_since'];
				$active_till = $db_report['active_till'];

				if (array_key_exists('active_since', $report)) {
					$active_since = ($report['active_since'] !== '')
						? (DateTime::createFromFormat(ZBX_DATE, $report['active_since'],
							new DateTimeZone('UTC')
						))
							->setTime(0, 0)
							->getTimestamp()
						: 0;
					$report['active_since'] = $active_since;
				}

				if (array_key_exists('active_till', $report)) {
					$active_till = ($report['active_till'] !== '')
						? (DateTime::createFromFormat(ZBX_DATE, $report['active_till'],
							new DateTimeZone('UTC')
						))
							->setTime(23, 59, 59)
							->getTimestamp()
						: 0;
					$report['active_till'] = $active_till;
				}

				if ($active_till > 0 && $active_since > $active_till) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('"%1$s" must be an empty string or greater than "%2$s".', 'active_till', 'active_since')
					);
				}
			}
		}
		unset($report);

		self::checkDuplicates($reports, $db_reports);
		self::checkDashboards($reports, $db_reports);

		self::addAffectedObjects($reports, $db_reports);

		self::checkUsers($reports, $db_reports);
		self::checkUserGroups($reports, $db_reports);
	}

	/**
	 * Update table "report_param".
	 *
	 * @param array  $reports
	 * @param string $method
	 */
	private static function updateParams(array $reports, string $method): void {
		$params_by_name = [
			'subject' => 'subject',
			'body' => 'message'
		];
		$report_params = [];

		foreach ($reports as $report) {
			foreach ($params_by_name as $name => $param) {
				if (!array_key_exists($param, $report)) {
					continue;
				}

				$report_params[$report['reportid']][$name] = [
					'name' => $name,
					'value' => $report[$param]
				];
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
			$reportid = $db_report_param['reportid'];
			$name = $db_report_param['name'];

			if (array_key_exists($name, $report_params[$reportid])) {
				$report_param = $report_params[$reportid][$name];
				unset($report_params[$reportid][$name]);

				if ($report_param['value'] === '') {
					$del_reportparamids[] = $db_report_param['reportparamid'];
				}
				else {
					$upd_report_param = DB::getUpdatedValues('report_param', $report_param, $db_report_param);

					if ($upd_report_param) {
						$upd_report_params[] = [
							'values' => $upd_report_param,
							'where' => ['reportparamid' => $db_report_param['reportparamid']]
						];
					}
				}
			}
		}

		foreach ($report_params as $reportid => $report_param) {
			foreach ($report_param as $param) {
				if ($param['value'] !== '') {
					$ins_report_params[] = ['reportid' => $reportid] + $param;
				}
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
	 * @param array      $reports
	 * @param array|null $db_reports
	 */
	private static function updateUsers(array &$reports, array $db_reports = null): void {
		$ins_report_users = [];
		$upd_report_users = [];
		$del_reportuserids = [];

		foreach ($reports as &$report) {
			if (!array_key_exists('users', $report)) {
				continue;
			}

			$db_report_users = $db_reports !== null
				? array_column($db_reports[$report['reportid']]['users'], null, 'userid')
				: [];

			foreach ($report['users'] as &$report_user) {
				if (array_key_exists($report_user['userid'], $db_report_users)) {
					$db_report_user = $db_report_users[$report_user['userid']];
					$report_user['reportuserid'] = $db_report_user['reportuserid'];
					unset($db_report_users[$report_user['userid']]);

					$upd_report_user = DB::getUpdatedValues('report_user', $report_user, $db_report_user);

					if ($upd_report_user) {
						$upd_report_users[] = [
							'values' => $upd_report_user,
							'where' => ['reportuserid' => $db_report_user['reportuserid']]
						];
					}
				}
				else {
					$ins_report_users[] = ['reportid' => $report['reportid']] + $report_user;
				}
			}
			unset($report_user);

			$del_reportuserids = array_merge($del_reportuserids, array_column($db_report_users, 'reportuserid'));
		}
		unset($report);

		if ($del_reportuserids) {
			DB::delete('report_user', ['reportuserid' => $del_reportuserids]);
		}

		if ($upd_report_users) {
			DB::update('report_user', $upd_report_users);
		}

		if ($ins_report_users) {
			$reportuserids = DB::insert('report_user', $ins_report_users);
		}

		foreach ($reports as &$report) {
			if (!array_key_exists('users', $report)) {
				continue;
			}

			foreach ($report['users'] as &$report_user) {
				if (!array_key_exists('reportuserid', $report_user)) {
					$report_user['reportuserid'] = array_shift($reportuserids);
				}
			}
			unset($report_user);
		}
		unset($report);
	}

	/**
	 * @param array      $reports
	 * @param array|null $db_reports
	 */
	private static function updateUserGroups(array &$reports, array $db_reports = null): void {
		$ins_report_usrgrps = [];
		$upd_report_usrgrps = [];
		$del_reportusrgrpids = [];

		foreach ($reports as &$report) {
			if (!array_key_exists('user_groups', $report)) {
				continue;
			}

			$db_report_usrgrps = $db_reports !== null
				? array_column($db_reports[$report['reportid']]['user_groups'], null, 'usrgrpid')
				: [];

			foreach ($report['user_groups'] as &$report_usrgrp) {
				if (array_key_exists($report_usrgrp['usrgrpid'], $db_report_usrgrps)) {
					$db_report_usrgrp = $db_report_usrgrps[$report_usrgrp['usrgrpid']];
					$report_usrgrp['reportusrgrpid'] = $db_report_usrgrp['reportusrgrpid'];
					unset($db_report_usrgrps[$report_usrgrp['usrgrpid']]);

					$upd_report_usrgrp = DB::getUpdatedValues('report_user', $report_usrgrp, $db_report_usrgrp);

					if ($upd_report_usrgrp) {
						$upd_report_usrgrps[] = [
							'values' => $upd_report_usrgrp,
							'where' => ['reportusrgrpid' => $db_report_usrgrp['reportusrgrpid']]
						];
					}
				}
				else {
					$ins_report_usrgrps[] = ['reportid' => $report['reportid']] + $report_usrgrp;
				}
			}
			unset($report_usrgrp);

			$del_reportusrgrpids = array_merge($del_reportusrgrpids,
				array_column($db_report_usrgrps, 'reportusrgrpid')
			);
		}
		unset($report);

		if ($del_reportusrgrpids) {
			DB::delete('report_usrgrp', ['reportusrgrpid' => $del_reportusrgrpids]);
		}

		if ($upd_report_usrgrps) {
			DB::update('report_usrgrp', $upd_report_usrgrps);
		}

		if ($ins_report_usrgrps) {
			$reportusrgrpids = DB::insert('report_usrgrp', $ins_report_usrgrps);
		}

		foreach ($reports as &$report) {
			if (!array_key_exists('user_groups', $report)) {
				continue;
			}

			foreach ($report['user_groups'] as &$report_usrgrp) {
				if (!array_key_exists('reportusrgrpid', $report_usrgrp)) {
					$report_usrgrp['reportusrgrpid'] = array_shift($reportusrgrpids);
				}
			}
			unset($report_usrgrp);
		}
		unset($report);
	}

	/**
	 * @param array $reportids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $reportids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $reportids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_reports = $this->get([
			'output' => ['reportid', 'name'],
			'reportids' => $reportids,
			'preservekeys' => true
		]);

		if (count($db_reports) != count($reportids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('report', ['reportid' => $reportids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_SCHEDULED_REPORT, $db_reports);

		return ['reportids' => $reportids];
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$reportids = array_keys($result);

		// If requested, convert 'active_since' and 'active_till' from timestamp to date.
		if ($this->outputIsRequested('active_since', $options['output'])
				|| $this->outputIsRequested('active_till', $options['output'])) {
			foreach ($result as &$report) {
				if (array_key_exists('active_since', $report)) {
					$report['active_since'] = ($report['active_since'] != 0)
						? (new DateTime('@'.$report['active_since']))->format(ZBX_DATE)
						: '';
				}
				if (array_key_exists('active_till', $report)) {
					$report['active_till'] = ($report['active_till'] != 0)
						? (new DateTime('@'.$report['active_till']))->format(ZBX_DATE)
						: '';
				}
			}
			unset($report);
		}

		// Adding email subject and message.
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

		// Adding users.
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

		// Adding user groups.
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

	/**
	 * @param array $reports
	 * @param array $db_reports
	 */
	private static function addAffectedObjects(array $reports, array &$db_reports): void {
		self::addAffectedUsers($reports, $db_reports);
		self::addAffectedUserGroups($reports, $db_reports);
	}

	/**
	 * @param array $reports
	 * @param array $db_reports
	 */
	private static function addAffectedUsers(array $reports, array &$db_reports): void {
		$reportids = [];

		foreach ($reports as $report) {
			$reportids[] = $report['reportid'];
			$db_reports[$report['reportid']]['users'] = [];
		}

		$options = [
			'output' => ['reportuserid', 'reportid', 'userid', 'exclude', 'access_userid'],
			'filter' => ['reportid' => $reportids]
		];
		$db_report_users = DBselect(DB::makeSql('report_user', $options));

		while ($db_report_user = DBfetch($db_report_users)) {
			$db_reports[$db_report_user['reportid']]['users'][$db_report_user['reportuserid']] =
				array_diff_key($db_report_user, array_flip(['reportid']));
		}
	}

	/**
	 * @param array $reports
	 * @param array $db_reports
	 */
	private static function addAffectedUserGroups(array $reports, array &$db_reports): void {
		$reportids = [];

		foreach ($reports as $report) {
			$reportids[] = $report['reportid'];
			$db_reports[$report['reportid']]['user_groups'] = [];
		}

		$options = [
			'output' => ['reportusrgrpid', 'reportid', 'usrgrpid', 'access_userid'],
			'filter' => ['reportid' => $reportids]
		];
		$db_report_usrgrps = DBselect(DB::makeSql('report_usrgrp', $options));

		while ($db_report_usrgrp = DBfetch($db_report_usrgrps)) {
			$db_reports[$db_report_usrgrp['reportid']]['user_groups'][$db_report_usrgrp['reportusrgrpid']] =
				array_diff_key($db_report_usrgrp, array_flip(['reportid']));
		}
	}
}
