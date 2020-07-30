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
		$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))
			->read()
			->setInput($this->getInputAll());
		$filter = $profile->getTabFilter($profile->selected);
		$this->getInputs($filter, ['page', 'sort', 'sortorder']);
		$filter_tabs = $profile->getTabsWithDefaults();

		foreach ($filter_tabs as &$filter_tab) {
			$filter_tab += $this->getAdditionalData($filter_tab);
		}
		unset($filter_tab);

		$refresh_curl = (new CUrl('zabbix.php'));
		$filter['action'] = 'host.view.refresh';
		array_map([$refresh_curl, 'setArgument'], array_keys($filter), $filter);

		$data = [
			'tabfilter_idx' => static::FILTER_IDX,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'filter_view' => 'monitoring.host.filter',
			'filter_defaults' => $profile->filter_defaults,
			'from' => $filter['from'],
			'to' => $filter['to'],
			'filter_tabs' => $filter_tabs,
			'tab_selected' => $profile->selected,
			'tab_expanded' => $profile->expanded
		] + $this->getData($filter);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Hosts'));
		$this->setResponse($response);
	}
}
