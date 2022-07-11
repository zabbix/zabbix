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


class CControllerAuditLogList extends CController {

	protected function checkInput(): bool {
		$fields = [
			'page' =>					'ge 1',
			'filter_actions' =>			'array',
			'filter_resourcetype' =>	'in -1,'.implode(',', array_keys(self::getResourcesList())),
			'filter_rst' =>				'in 1',
			'filter_set' =>				'in 1',
			'filter_userids' =>			'array_db users.userid',
			'filter_resourceid' =>		'string',
			'filter_recordsetid' =>		'string',
			'from' =>					'range_time',
			'to' =>						'range_time'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [];

			if ($this->getInput('filter_resourceid', '') !== '') {
				$fields['filter_resourceid'] = 'id';
			}

			if ($this->getInput('filter_recordsetid', '') !== '') {
				$fields['filter_recordsetid'] = 'cuid';
			}

			if ($fields) {
				$validator = new CNewValidator($this->getInputAll(), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_AUDIT);
	}

	protected function doAction(): void {
		if ($this->getInput('filter_set', 0)) {
			$this->updateProfiles();
		}
		elseif ($this->getInput('filter_rst', 0)) {
			$this->deleteProfiles();
		}

		$timeselector_options = [
			'profileIdx' => 'web.auditlog.filter',
			'profileIdx2' => 0,
			'from' => null,
			'to' => null
		];
		$this->getInputs($timeselector_options, ['from', 'to']);
		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'page' => $this->getInput('page', 1),
			'userids' => CProfile::getArray('web.auditlog.filter.userids', []),
			'resourcetype' => CProfile::get('web.auditlog.filter.resourcetype', -1),
			'auditlog_actions' => CProfile::getArray('web.auditlog.filter.actions', []),
			'resourceid' => CProfile::get('web.auditlog.filter.resourceid', ''),
			'recordsetid' => CProfile::get('web.auditlog.filter.recordsetid', ''),
			'action' => $this->getAction(),
			'actions' => self::getActionsList(),
			'resources' => self::getResourcesList(),
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'auditlogs' => [],
			'active_tab' => CProfile::get('web.auditlog.filter.active', 1)
		];
		$users = [];
		$non_existent_userids = [];

		$filter = [
			'action' => $data['auditlog_actions']
		];

		if ($data['resourcetype'] != -1) {
			$filter['resourcetype'] = $data['resourcetype'];
		}

		if ($data['resourceid'] !== '') {
			$filter['resourceid'] = $data['resourceid'];
		}

		if ($data['recordsetid'] !== '') {
			$filter['recordsetid'] = $data['recordsetid'];
		}

		$params = [
			'output' => ['auditid', 'userid', 'username', 'clock', 'action', 'resourcetype', 'ip', 'resourceid',
				'resourcename', 'details', 'recordsetid'
			],
			'filter' => $filter,
			'sortfield' => 'auditid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		if ($data['timeline']['from_ts'] !== null) {
			$params['time_from'] = $data['timeline']['from_ts'];
		}

		if ($data['timeline']['to_ts'] !== null) {
			$params['time_till'] = $data['timeline']['to_ts'];
		}

		if ($data['userids']) {
			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => $data['userids'],
				'preservekeys' => true
			]);

			$data['userids'] = $this->sanitizeUsersForMultiselect($users);

			if ($users) {
				$params['userids'] = array_column($users, 'userid');
				$data['auditlogs'] = API::AuditLog()->get($params);
			}

			$users = array_map(function(array $value): string {
				return $value['username'];
			}, $users);
		}
		else {
			$data['auditlogs'] = API::AuditLog()->get($params);
		}

		$data['paging'] = CPagerHelper::paginate($data['page'], $data['auditlogs'], ZBX_SORT_UP,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$data['auditlogs'] = $this->sanitizeDetails($data['auditlogs']);

		if (!$users && $data['auditlogs']) {
			$db_users = API::User()->get([
				'output' => ['username'],
				'userids' => array_unique(array_column($data['auditlogs'], 'userid')),
				'preservekeys' => true
			]);

			$users = [];
			foreach ($data['auditlogs'] as $auditlog) {
				if (!array_key_exists($auditlog['userid'], $db_users)) {
					$non_existent_userids[$auditlog['userid']] = true;
					continue;
				}

				$users[$auditlog['userid']] = $db_users[$auditlog['userid']]['username'];
			}
		}

		$data['users'] = $users;
		$data['non_existent_userids'] = array_keys($non_existent_userids);

		natsort($data['actions']);
		natsort($data['resources']);

		$data['resources'] = [-1 => _('All')] + $data['resources'];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Audit log'));
		$this->setResponse($response);
	}

	protected function init(): void {
		$this->disableSIDValidation();
	}

	/**
	 * Return associated list of available actions and labels.
	 *
	 * @return array
	 */
	private static function getActionsList(): array {
		return [
			CAudit::ACTION_LOGIN_SUCCESS => _('Login'),
			CAudit::ACTION_LOGIN_FAILED => _('Failed login'),
			CAudit::ACTION_LOGOUT => _('Logout'),
			CAudit::ACTION_ADD => _('Add'),
			CAudit::ACTION_UPDATE => _('Update'),
			CAudit::ACTION_DELETE => _('Delete'),
			CAudit::ACTION_EXECUTE => _('Execute'),
			CAudit::ACTION_HISTORY_CLEAR => _('History clear')
		];
	}

	/**
	 * Return associated list of available resources and labels.
	 *
	 * @return array
	 */
	private static function getResourcesList(): array {
		return [
			CAudit::RESOURCE_ACTION => _('Action'),
			CAudit::RESOURCE_AUTH_TOKEN => _('API token'),
			CAudit::RESOURCE_AUTHENTICATION => _('Authentication'),
			CAudit::RESOURCE_AUTOREGISTRATION  => _('Autoregistration'),
			CAudit::RESOURCE_CORRELATION => _('Event correlation'),
			CAudit::RESOURCE_DASHBOARD => _('Dashboard'),
			CAudit::RESOURCE_DISCOVERY_RULE => _('Discovery rule'),
			CAudit::RESOURCE_GRAPH => _('Graph'),
			CAudit::RESOURCE_GRAPH_PROTOTYPE => _('Graph prototype'),
			CAudit::RESOURCE_HA_NODE => _('High availability node'),
			CAudit::RESOURCE_HOST => _('Host'),
			CAudit::RESOURCE_HOST_GROUP => _('Host group'),
			CAudit::RESOURCE_HOST_PROTOTYPE => _('Host prototype'),
			CAudit::RESOURCE_HOUSEKEEPING => _('Housekeeping'),
			CAudit::RESOURCE_ICON_MAP => _('Icon mapping'),
			CAudit::RESOURCE_IMAGE => _('Image'),
			CAudit::RESOURCE_IT_SERVICE => _('Service'),
			CAudit::RESOURCE_ITEM => _('Item'),
			CAudit::RESOURCE_ITEM_PROTOTYPE => _('Item prototype'),
			CAudit::RESOURCE_MACRO => _('Macro'),
			CAudit::RESOURCE_MAINTENANCE => _('Maintenance'),
			CAudit::RESOURCE_MAP => _('Map'),
			CAudit::RESOURCE_MEDIA_TYPE => _('Media type'),
			CAudit::RESOURCE_MODULE => _('Module'),
			CAudit::RESOURCE_PROXY => _('Proxy'),
			CAudit::RESOURCE_REGEXP => _('Regular expression'),
			CAudit::RESOURCE_SCENARIO => _('Web scenario'),
			CAudit::RESOURCE_SCHEDULED_REPORT => _('Scheduled report'),
			CAudit::RESOURCE_SCRIPT => _('Script'),
			CAudit::RESOURCE_SETTINGS => _('Settings'),
			CAudit::RESOURCE_SLA => _('SLA'),
			CAudit::RESOURCE_TEMPLATE => _('Template'),
			CAudit::RESOURCE_TEMPLATE_DASHBOARD => _('Template dashboard'),
			CAudit::RESOURCE_TRIGGER => _('Trigger'),
			CAudit::RESOURCE_TRIGGER_PROTOTYPE => _('Trigger prototype'),
			CAudit::RESOURCE_USER => _('User'),
			CAudit::RESOURCE_USER_GROUP => _('User group'),
			CAudit::RESOURCE_USER_ROLE => _('User role'),
			CAudit::RESOURCE_VALUE_MAP => _('Value map')
		];
	}

	private function updateProfiles(): void {
		CProfile::updateArray('web.auditlog.filter.userids', $this->getInput('filter_userids', []), PROFILE_TYPE_ID);
		CProfile::updateArray('web.auditlog.filter.actions', $this->getInput('filter_actions', []), PROFILE_TYPE_INT);
		CProfile::update('web.auditlog.filter.resourcetype', $this->getInput('filter_resourcetype', -1),
			PROFILE_TYPE_INT
		);
		CProfile::update('web.auditlog.filter.resourceid', $this->getInput('filter_resourceid', ''), PROFILE_TYPE_STR);
		CProfile::update('web.auditlog.filter.recordsetid', $this->getInput('filter_recordsetid', ''),
			PROFILE_TYPE_STR
		);
	}

	private function deleteProfiles(): void {
		CProfile::deleteIdx('web.auditlog.filter.userids');
		CProfile::deleteIdx('web.auditlog.filter.actions');
		CProfile::delete('web.auditlog.filter.resourcetype');
		CProfile::delete('web.auditlog.filter.resourceid');
		CProfile::delete('web.auditlog.filter.recordsetid');
	}

	private function sanitizeUsersForMultiselect(array $users): array {
		$users = array_map(function(array $value): array {
			return ['id' => $value['userid'], 'name' => getUserFullname($value)];
		}, $users);

		CArrayHelper::sort($users, ['name']);

		return $users;
	}

	private function sanitizeDetails(array $auditlogs): array {
		foreach ($auditlogs as &$auditlog) {
			$auditlog['short_details'] = '';
			$auditlog['details_button'] = 0;

			if ($auditlog['resourcename'] != '') {
				$auditlog['short_details'] .= _('Description').': '.$auditlog['resourcename'];
			}

			if (!in_array($auditlog['action'], [CAudit::ACTION_ADD, CAudit::ACTION_UPDATE, CAudit::ACTION_EXECUTE])) {
				continue;
			}

			$details = json_decode($auditlog['details'], true);

			if (!is_array($details) || count($details) == 0) {
				$auditlog['details'] = '';
				continue;
			}

			// Add space after description string.
			if ($auditlog['short_details'] != '') {
				$auditlog['short_details'] .= "\n\n";
			}

			$details = $this->formatDetails($details);
			$short_details =  array_slice($details, 0, 2);

			// We cut string and show "Details" button if audit detail string more than 255 symbols.
			foreach ($short_details as &$detail) {
				// Remove all line breaks from short details.
				$detail = str_replace(["\r\n", "\n"], " ", $detail);

				if (mb_strlen($detail) > 255) {
					$detail = mb_substr($detail, 0, 252).'...';
					$auditlog['details_button'] = 1;
				}
			}
			unset($detail);

			$auditlog['details'] = implode("\n", $details);
			$auditlog['short_details'] .= implode("\n", $short_details);

			if (!$auditlog['details_button'] && count($details) > 2) {
				$auditlog['details_button'] = 1;
			}

			$details = json_decode($auditlog['details'], true);

			if (!is_array($details) || count($details) == 0) {
				continue;
			}

			$details = $this->formatDetails($details);

			$auditlog['details'] = implode("\n", $details);
		}
		unset($auditlog);

		return $auditlogs;
	}

	private function formatDetails(array $details): array {
		ksort($details);

		$new_details = [];
		foreach ($details as $key => $detail) {
			$new_details[] = $this->makeDetailString($key, $detail);
		}

		return $new_details;
	}

	private function makeDetailString(string $key, array $detail): string {
		switch ($detail[0]) {
			case CAudit::DETAILS_ACTION_ADD:
				return array_key_exists(1, $detail)
					? $key.': '.$detail[1]
					: $key.': '._('Added');

			case CAudit::DETAILS_ACTION_DELETE:
				return $key.': '._('Deleted');

			case CAudit::DETAILS_ACTION_UPDATE:
				return array_key_exists(1, $detail)
					? sprintf('%s: %s => %s', $key, $detail[2], $detail[1])
					: $key.': '._('Updated');
		}
	}
}
