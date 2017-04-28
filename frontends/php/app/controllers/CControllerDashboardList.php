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

/**
 * controller dashboard list
 *
 */
class CControllerDashboardList extends CController {

	const CHECKBOX_COOKIE_SUFFIX = 'selected_dashboard_ids';

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		$this->disableSIDValidation();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function checkInput() {
		$fields = [
			'uncheck'        => 'in 1,0',
			'sort'           => 'in name',
			'sortorder'      => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
		];
		$ret = $this->validateInput($fields);
		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	/**
	 * Prepare and render list of dashboards
	 *
	 * @return void
	 */
	protected function doAction() {

		if ($this->getInput('uncheck', false)) {
			foreach ($_COOKIE as $key => $value) {
				if (strpos($key, self::CHECKBOX_COOKIE_SUFFIX) !== false) {
					zbx_unsetcookie($key);
				}
			}
		}

		CProfile::delete('web.dashbrd.dashboardid');
		CProfile::update('web.dashbrd.list_was_opened', 1, PROFILE_TYPE_INT);

		$sortField = $this->getInput('sort', CProfile::get('web.dashbrd.sort', 'name'));
		CProfile::update('web.dashbrd.sort', $sortField, PROFILE_TYPE_STR);

		$sortOrder = $this->getInput('sortorder', CProfile::get('web.dashbrd.sortorder', ZBX_SORT_UP));
		CProfile::update('web.dashbrd.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$config = select_config();

		$dashboards = API::Dashboard()->get(
			[
				'output'       => ['dashboardid', 'name'],
				'preservekeys' => true,
				'limit'        => $config['search_limit'] + 1,
			]
		);

		if (count($dashboards)) {
			$write_dashboards = API::Dashboard()->get(['output' => ['dashboardid'], 'editable' => true, 'preservekeys' => true]);

			$curl = (new CUrl('zabbix.php'))->setArgument('action', 'dashboard.view');
			foreach ($dashboards as $id => &$dashboard) {
				if (array_key_exists($id, $write_dashboards)) {
					$dashboard['editable'] = true;
				}
				else {
					$dashboard['editable'] = false;
				}
				$dashboard['view_link'] = $curl->setArgument('dashboardid', $id)->getUrl();
			}
		}

		order_result($dashboards, $sortField, $sortOrder);
		$data = [
			'dashboards'             => $dashboards,
			'sort'                   => $sortField,
			'sort_order'             => $sortOrder,
			'checkbox_cookie_suffix' => self::CHECKBOX_COOKIE_SUFFIX
		];
		$url = (new CUrl('zabbix.php'))->setArgument('action', 'dashboard.list');
		$data['paging'] = getPagingLine($data['dashboards'], $sortOrder, $url);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboards'));
		$this->setResponse($response);
	}
}
