<?php declare(strict_types = 0);
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


/**
 * Controller for the "Host->Monitoring" asynchronous refresh page.
 */
class CControllerHostViewRefresh extends CControllerHostView {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function doAction(): void {
		$filter = static::FILTER_FIELDS_DEFAULT;

		if ($this->getInput('filter_counters', 0)) {
			$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
			$filters = $this->hasInput('counter_index')
				? [$profile->getTabFilter($this->getInput('counter_index'))]
				: $profile->getTabsWithDefaults();
			$filter_counters = [];

			foreach ($filters as $index => $tabfilter) {
				$tabfilter = self::sanitizeFilter($tabfilter);

				$filter_counters[$index] = $tabfilter['filter_show_counter'] ? $this->getCount($tabfilter) : 0;
			}

			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['filter_counters' => $filter_counters])
				]))->disableView()
			);
		}
		else {
			$this->getInputs($filter, array_keys($filter));
			$filter = $this->cleanInput($filter);
			$filter = self::sanitizeFilter($filter);

			$view_url = (new CUrl())
				->setArgument('action', 'host.view')
				->removeArgument('page');

			$data = [
				'filter' => $filter,
				'view_curl' => $view_url,
				'sort' => $filter['sort'],
				'sortorder' => $filter['sortorder'],
				'allowed_ui_latest_data' => $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
				'allowed_ui_problems' => $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
			] + $this->getData($filter);

			$response = new CControllerResponseData($data);
			$this->setResponse($response);
		}
	}
}
