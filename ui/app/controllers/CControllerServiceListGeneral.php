<?php
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


abstract class CControllerServiceListGeneral extends CController {

	protected const WITHOUT_PARENTS_SERVICEID = 0;

	protected const FILTER_DEFAULT_NAME = '';
	protected const FILTER_DEFAULT_STATUS = SERVICE_STATUS_ANY;
	protected const FILTER_DEFAULT_WITHOUT_CHILDREN = false;
	protected const FILTER_DEFAULT_WITHOUT_PROBLEM_TAGS = false;
	protected const FILTER_DEFAULT_TAG_SOURCE = ZBX_SERVICE_FILTER_TAGS_SERVICE;
	protected const FILTER_DEFAULT_EVALTYPE = TAG_EVAL_TYPE_AND_OR;

	protected $is_filtered = false;

	protected $service;

	protected function doAction(): void {
		if ($this->hasInput('serviceid')) {
			$db_service = API::Service()->get([
				'output' => ['serviceid', 'name', 'status', 'goodsla', 'showsla'],
				'serviceids' => $this->getInput('serviceid'),
				'selectParents' => ['serviceid'],
				'selectTags' => ['tag', 'value']
			]);

			if (!$db_service) {
				$this->setResponse(new CControllerResponseData([
					'error' => _('No permissions to referred object or it does not exist!')
				]));

				return;
			}

			$this->service = reset($db_service);
			$this->service['tags'] = makeTags([$this->service], true, 'serviceid', ZBX_TAG_COUNT_DEFAULT);
			$this->service['parents'] = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => array_column($this->service['parents'], 'serviceid'),
				'selectChildren' => API_OUTPUT_COUNT
			]);
		}
	}

	protected function isDefaultFilter(array $filter): bool {
		return ($filter['name'] == self::FILTER_DEFAULT_NAME
			&& $filter['status'] == self::FILTER_DEFAULT_STATUS
			&& $filter['without_children'] == self::FILTER_DEFAULT_WITHOUT_CHILDREN
			&& $filter['without_problem_tags'] == self::FILTER_DEFAULT_WITHOUT_PROBLEM_TAGS
			&& !$filter['tags']
		);
	}

	/**
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function getPath(): array {
		if ($this->service === null) {
			return [];
		}

		$path = [];
		$db_service = $this->service;

		while (true) {
			if ($this->hasInput('path')) {
				$path_serviceids = $this->getInput('path', []);

				$db_services = API::Service()->get([
					'output' => [],
					'serviceids' => $path_serviceids,
					'preservekeys' => true
				]);

				foreach (array_reverse($path_serviceids) as $serviceid) {
					if (array_key_exists($serviceid, $db_services)) {
						$path[] = $serviceid;
					}
				}

				break;
			}

			if (!$db_service['parents']) {
				break;
			}

			$db_service = API::Service()->get([
				'output' => ['serviceid'],
				'serviceids' => $db_service['parents'][0]['serviceid'],
				'selectParents' => ['serviceid']
			]);

			if (!$db_service) {
				break;
			}

			$db_service = reset($db_service);

			$path[] = $db_service['serviceid'];
		}

		return array_reverse($path);
	}

	/**
	 * @param array  $path
	 * @param bool   $is_filtered
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function getBreadcrumbs(array $path, bool $is_filtered): array {
		$breadcrumbs = [[
			'name' => _('All services'),
			'curl' => (new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		]];

		$db_services = API::Service()->get([
			'output' => ['name'],
			'serviceids' => $path,
			'preservekeys' => true
		]);

		$parent_serviceids = [];

		foreach ($path as $serviceid) {
			$breadcrumbs[] = [
				'name' => $db_services[$serviceid]['name'],
				'curl' => (new CUrl('zabbix.php'))
					->setArgument('action', $this->getAction())
					->setArgument('path', $parent_serviceids)
					->setArgument('serviceid', $serviceid)
			];

			$parent_serviceids[] = $serviceid;
		}

		if ($this->service !== null) {
			$breadcrumbs[] = [
				'name' => $this->service['name'],
				'curl' => (new CUrl('zabbix.php'))
					->setArgument('action', $this->getAction())
					->setArgument('path', $parent_serviceids)
					->setArgument('serviceid', $this->service['serviceid'])
			];
		}

		if ($is_filtered) {
			$breadcrumbs[] = [
				'name' => _('Filter results')
			];
		}

		return $breadcrumbs;
	}

	/**
	 * @param array $filter
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function prepareData(array $filter): array {
		if ($filter['status'] == SERVICE_STATUS_OK) {
			$filter_status = TRIGGER_SEVERITY_NOT_CLASSIFIED;
		}
		elseif ($filter['status'] == SERVICE_STATUS_PROBLEM) {
			$filter_status = array_column(getSeverities(TRIGGER_SEVERITY_INFORMATION), 'value');
		}
		else {
			$filter_status = null;
		}

		$options = [
			'output' => [],
			'selectParents' => ($filter['serviceid'] == self::WITHOUT_PARENTS_SERVICEID && !$this->is_filtered)
				? null
				: ['serviceid'],
			'parentids' => !$this->is_filtered ? $filter['serviceid'] : null,
			'childids' => $filter['without_children'] ? 0 : null,
			'without_problem_tags' => $filter['without_problem_tags'],
			'search' => ($filter['name'] === '')
				? null
				: ['name' => $filter['name']],
			'filter' => [
				'status' => $filter_status
			],
			'evaltype' => $filter['evaltype'],
			'sortfield' => ['sortorder', 'name'],
			'sortorder' => ZBX_SORT_UP,
			'preservekeys' => true
		];

		$db_services = [];

		if ($filter['tags']) {
			if (in_array($filter['tag_source'], [ZBX_SERVICE_FILTER_TAGS_ANY, ZBX_SERVICE_FILTER_TAGS_SERVICE])) {
				$db_services += API::Service()->get($options + ['tags' => $filter['tags']]);
			}

			if (!$filter['without_problem_tags']
					&& ($filter['tag_source'] == ZBX_SERVICE_FILTER_TAGS_ANY
						|| $filter['tag_source'] == ZBX_SERVICE_FILTER_TAGS_PROBLEM
					)) {
				$db_services += API::Service()->get($options + ['problem_tags' => $filter['tags']]);
			}
		}
		else {
			$db_services += API::Service()->get($options);
		}

		if (!$db_services || !$this->is_filtered || $filter['serviceid'] == self::WITHOUT_PARENTS_SERVICEID) {
			return array_keys($db_services);
		}

		$filtered_serviceids = [];

		do {
			$parentids = [];

			foreach ($db_services as $db_serviceid => $db_service) {
				$service_parentids = array_column($db_service['parents'], 'serviceid', 'serviceid');

				if (array_key_exists($filter['serviceid'], $service_parentids)) {
					$filtered_serviceids[$db_serviceid] = true;
					unset($db_services[$db_serviceid]);
				}
				else {
					$parentids += $service_parentids;
				}
			}

			$db_parent_services = API::Service()->get([
				'output' => [],
				'selectParents' => ['serviceid'],
				'serviceids' => $parentids,
				'preservekeys' => true
			]);

			if (!$db_parent_services) {
				break;
			}

			foreach ($db_services as &$db_service) {
				$service_parentids = array_column($db_service['parents'], 'serviceid', 'serviceid');

				$parentids = [];
				foreach ($service_parentids as $service_parentid) {
					$parentids += array_column($db_parent_services[$service_parentid]['parents'], null, 'serviceid');
				}

				$db_service['parents'] = $parentids;
			}
			unset($db_service);
		} while (true);

		return array_keys($filtered_serviceids);
	}
}
