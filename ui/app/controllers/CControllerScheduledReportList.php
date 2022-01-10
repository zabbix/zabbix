<?php declare(strict_types = 1);
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


class CControllerScheduledReportList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>		'in 1',
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_show' =>	'in '.ZBX_REPORT_FILTER_SHOW_ALL.','.ZBX_REPORT_FILTER_SHOW_MY,
			'filter_status' =>	'in -1,'.implode(',', [ZBX_REPORT_STATUS_ENABLED, ZBX_REPORT_STATUS_DISABLED, ZBX_REPORT_STATUS_EXPIRED]),
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.scheduledreport.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.scheduledreport.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.scheduledreport.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.scheduledreport.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.scheduledreport.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.scheduledreport.filter_show',
				$this->getInput('filter_show', ZBX_REPORT_FILTER_SHOW_ALL), PROFILE_TYPE_INT
			);
			CProfile::update('web.scheduledreport.filter_status', $this->getInput('filter_status', -1),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.scheduledreport.filter_name');
			CProfile::delete('web.scheduledreport.filter_show');
			CProfile::delete('web.scheduledreport.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.scheduledreport.filter_name', ''),
			'show' => CProfile::get('web.scheduledreport.filter_show', ZBX_REPORT_FILTER_SHOW_ALL),
			'status' => CProfile::get('web.scheduledreport.filter_status', -1)
		];

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.scheduledreport.filter',
			'active_tab' => CProfile::get('web.scheduledreport.filter.active', 1),
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS)
		];

		$expired = null;
		$status = null;
		if ($filter['status'] == ZBX_REPORT_STATUS_ENABLED) {
			$expired = false;
			$status = ZBX_REPORT_STATUS_ENABLED;
		}
		elseif ($filter['status'] == ZBX_REPORT_STATUS_DISABLED) {
			$status = ZBX_REPORT_STATUS_DISABLED;
		}
		elseif ($filter['status'] == ZBX_REPORT_STATUS_EXPIRED) {
			$expired = true;
			$status = ZBX_REPORT_STATUS_ENABLED;
		}

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$data['reports'] = API::Report()->get([
			'output' => ['reportid', 'userid', 'name', 'status', 'period', 'cycle', 'active_till', 'state', 'lastsent',
				'info'
			],
			'expired' => $expired,
			'search' => [
				'name' => ($filter['name'] !== '') ? $filter['name'] : null
			],
			'sortfield' => $sort_field,
			'filter' => [
				'userid' => ($filter['show'] == ZBX_REPORT_FILTER_SHOW_MY) ? CWebUser::$data['userid'] : null,
				'status' => $status
			],
			'limit' => $limit
		]);

		if ($data['reports']) {
			CArrayHelper::sort($data['reports'], [['field' => $sort_field, 'order' => $sort_order]]);

			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_column($data['reports'], 'userid'),
				'preservekeys' => true
			]);

			foreach ($data['reports'] as &$report) {
				$report['owner'] = array_key_exists($report['userid'], $users)
					? getUserFullname($users[$report['userid']])
					: _('Inaccessible user');
			}
			unset($report);
		}

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('scheduledreport.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['reports'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Scheduled reports'));
		$this->setResponse($response);
	}
}
