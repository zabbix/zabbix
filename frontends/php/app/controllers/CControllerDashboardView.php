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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerDashboardView extends CController {

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
			'fullscreen'  => 'in 0,1',
			'dashboardid' => 'not_empty|db dashboard.dashboardid'
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
		if ($this->hasInput('dashboardid')) {
			$dashboards = API::Dashboard()->get([
				'output' => [],
				'dashboardids' => [$this->getInput('dashboardid')]
			]);
			if (!$dashboards) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Action to view dashboard by given dashboard ID
	 *
	 * @return void
	 */
	protected function doAction() {
		$dashboard = $this->getDashboard();
		// redirect to list action when no dashboard or user persisted to list page
		if (empty($dashboard)) {
			$curl = (new CUrl('zabbix.php'))->setArgument('action', 'dashboard.list');
			$this->setResponse((new CControllerResponseRedirect($curl->getUrl())));
			return;
		}

		$fullscreen = $this->getInput('fullscreen', '0');
		$dashboard['link'] = $this->getDashboardLink($dashboard, $fullscreen);

		$show_discovery_widget = ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN && (bool) API::DRule()->get([
			'output' => [],
			'filter' => ['status' => DRULE_STATUS_ACTIVE],
			'limit' => 1
		]));

		$data = [
			'dashboard'             => $dashboard,
			'fullscreen'            => $fullscreen,
			'filter_enabled'        => CProfile::get('web.dashconf.filter.enable', 0),
			'show_status_widget'    => ($this->getUserType() == USER_TYPE_SUPER_ADMIN),
			'show_discovery_widget' => $show_discovery_widget
		];
		$data['grid_widgets'] = $this->getWidgets($data['show_status_widget'], $data['show_discovery_widget']);

		CProfile::update('web.dashbrd.dashboardid', $dashboard['dashboardid'], PROFILE_TYPE_ID);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboard'));
		$this->setResponse($response);
	}

	/**
	 * Get dashboard data from API
	 *
	 * @return array
	 */
	private function getDashboard() {
		$dashboard_id = null;
		if (!$this->hasInput('dashboardid')) {
			$dashboard_id = CProfile::get('web.dashbrd.dashboardid');
			if ($dashboard_id === null) {
				if (CProfile::get('web.dashbrd.list_was_opened') !== '1') {
					$dashboard_id = DASHBOARD_DEFAULT_ID;
				}
			}
		}
		else {
			$dashboard_id = $this->getInput('dashboardid', null);
		}

		$dashboard = [];
		if ($dashboard_id !== null) {
			$dashboards = API::Dashboard()->get(
				['output' => ['dashboardid', 'name'], 'dashboardids' => [$dashboard_id]]
			);
			$dashboard = $dashboards[0];
		}


		return $dashboard;
	}

	/**
	 * Get clean dashboard link
	 *
	 * @param array   $dashboard                dashboard data
	 * @param string  $dashboard['dashboardid'] dashboard ID
	 * @param string  $fullscreen               fullscreen mode on/off
	 * @return string
	 */
	private function getDashboardLink($dashboard, $fullscreen) {
		// remove from current url not needed params and return clean dashboard link
		$url_builder = (CUrlFactory::getContextUrl())
			->clearArguments(['action'])
			->setArgument('dashboardid', $dashboard['dashboardid']);
		if ($fullscreen) {
			$url_builder->setArgument('fullscreen', $fullscreen);
		}

		return $url_builder->getUrl();
	}

	/**
	 * Get default widgets
	 * @TODO should be refactored in ZBXNEXT-3789
	 *
	 * @param boolean $show_status_widget
	 * @param boolean $show_discovery_widget
	 * @return array
	 */
	private function getWidgets($show_status_widget, $show_discovery_widget) {
		$widgets = [
			WIDGET_FAVOURITE_GRAPHS => [
				'header' => _('Favourite graphs'),
				'pos' => ['row' => 0, 'col' => 0, 'height' => 3, 'width' => 2],
				'rf_rate' => 15 * SEC_PER_MIN
			],
			WIDGET_FAVOURITE_SCREENS => [
				'header' => _('Favourite screens'),
				'pos' => ['row' => 0, 'col' => 2, 'height' => 3, 'width' => 2],
				'rf_rate' => 15 * SEC_PER_MIN
			],
			WIDGET_FAVOURITE_MAPS => [
				'header' => _('Favourite maps'),
				'pos' => ['row' => 0, 'col' => 4, 'height' => 3, 'width' => 2],
				'rf_rate' => 15 * SEC_PER_MIN
			],
			WIDGET_LAST_ISSUES => [
				'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
				'pos' => ['row' => 3, 'col' => 0, 'height' => 6, 'width' => 6],
				'rf_rate' => SEC_PER_MIN
			],
			WIDGET_WEB_OVERVIEW => [
				'header' => _('Web monitoring'),
				'pos' => ['row' => 9, 'col' => 0, 'height' => 4, 'width' => 3],
				'rf_rate' => SEC_PER_MIN
			],
			WIDGET_HOST_STATUS => [
				'header' => _('Host status'),
				'pos' => ['row' => 0, 'col' => 6, 'height' => 4, 'width' => 6],
				'rf_rate' => SEC_PER_MIN
			],
			WIDGET_SYSTEM_STATUS => [
				'header' => _('System status'),
				'pos' => ['row' => 4, 'col' => 6, 'height' => 4, 'width' => 6],
				'rf_rate' => SEC_PER_MIN
			]
		];

		if ($show_status_widget) {
			$widgets[WIDGET_ZABBIX_STATUS] = [
				'header' => _('Status of Zabbix'),
				'pos' => ['row' => 8, 'col' => 6, 'height' => 5, 'width' => 6],
				'rf_rate' => 15 * SEC_PER_MIN
			];
		}
		if ($show_discovery_widget) {
			$widgets[WIDGET_DISCOVERY_STATUS] = [
				'header' => _('Discovery status'),
				'pos' => ['row' => 9, 'col' => 3, 'height' => 4, 'width' => 3],
				'rf_rate' => SEC_PER_MIN
			];
		}

		$grid_widgets = [];

		foreach ($widgets as $widgetid => $widget) {
			$grid_widgets[] = [
				'widgetid' => $widgetid,
				'header' => $widget['header'],
				'pos' => [
					'col' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.col', $widget['pos']['col']),
					'row' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.row', $widget['pos']['row']),
					'height' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.height', $widget['pos']['height']),
					'width' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.width', $widget['pos']['width'])
				],
				'rf_rate' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.rf_rate', $widget['rf_rate'])
			];
		}
		return $grid_widgets;
	}
}
