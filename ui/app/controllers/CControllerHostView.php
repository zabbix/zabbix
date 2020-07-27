<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerHostView extends CControllerHostViewRefresh {

	protected function doAction(): void {
		$data['filter_defaults'] = static::FILTER_FIELDS_DEFAULT;
		$profile = (new CTabFilterProfile(static::FILTER_IDX))->read();
		$profile->setFilterDefaults($data['filter_defaults']);
		$filter = $profile->getTabFilter($profile->selected);
		$filter_tabs = $profile->getTabsWithDefaults();

		$refresh_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'host.view.refresh')
			->setArgument('name', $filter['name'])
			->setArgument('groupids', $filter['groupids'])
			->setArgument('ip', $filter['ip'])
			->setArgument('dns', $filter['dns'])
			->setArgument('status', $filter['status'])
			->setArgument('evaltype', $filter['evaltype'])
			->setArgument('tags', $filter['tags'])
			->setArgument('severities', $filter['severities'])
			->setArgument('show_suppressed', $filter['show_suppressed'])
			->setArgument('maintenance_status', $filter['maintenance_status'])
			->setArgument('sort', $filter['sort'])
			->setArgument('sortorder', $filter['sortorder'])
			->setArgument('page', $filter['page']);

		$prepared_data = $this->getData($filter);

		foreach ($filter_tabs as &$filter_tab) {
			$filter_tab['filter'] += $this->getAdditionalData($filter_tab['filter']);
		}
		unset($filter_tab);

		$data = [
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,

			'filter_template' => 'monitoring.host.filter',
			'filter_defaults' => $data['filter_defaults'],
			'from' => $filter['from'],
			'to' => $filter['to'],
			'filter_tabs' => $filter_tabs,
			'tab_selected' => $profile->selected,
			'tab_expanded' => $profile->expanded
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Hosts'));
		$this->setResponse($response);
	}
}
