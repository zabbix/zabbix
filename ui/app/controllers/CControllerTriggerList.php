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


class CControllerTriggerList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>				'required|in '.implode(',', ['host', 'template']),
			'filter_evaltype' =>		'in '.implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]),
			'filter_dependent' =>		'in '.implode(',', [-1, 0, 1]),
			'filter_discovered' =>		'in '.implode(',', [-1, 0, 1]),
			'filter_groupids' =>		'array_id',
			'filter_hostids' =>			'array_id',
			'filter_inherited' =>		'in '.implode(',', [-1, 0, 1]),
			'filter_name' =>			'string',
			'filter_priority' =>		'array',
			'filter_set' =>				'in 1',
			'filter_state' =>			'in '.implode(',', [-1, TRIGGER_STATE_NORMAL, TRIGGER_STATE_UNKNOWN]),
			'filter_status' =>			'in '.implode(',', [-1, TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED]),
			'filter_rst' =>				'in 1',
			'filter_tags' =>			'array',
			'filter_value' =>			'in '.implode(',', [-1, TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE]),
			'sort' =>					'in '.implode(',', ['description', 'priority', 'status']),
			'sortorder' =>				'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>					'ge 1',
			'uncheck' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction() {
		$data = [
			'context' => $this->getInput('context'),
			'uncheck' => $this->hasInput('uncheck')
		];
		$prefix = ($data['context'] === 'host') ? 'web.hosts.' : 'web.templates.';
		$filter_hostids_ms = [];

		if ($this->hasInput('filter_set') && $this->getInput('filter_set')) {
			$filter_inherited = $this->getInput('filter_inherited', -1);
			$filter_discovered = $this->getInput('filter_discovered', -1);
			$filter_dependent = $this->getInput('filter_dependent', -1);
			$filter_name = $this->getInput('filter_name', '');
			$filter_priority = $this->getInput('filter_priority', []);
			$filter_groupids = $this->getInput('filter_groupids', []);
			$filter_hostids = $this->getInput('filter_hostids', []);
			$filter_state = $this->getInput('filter_state', -1);
			$filter_status = $this->getInput('filter_status', -1);
			$filter_value = $this->getInput('filter_value', -1);
			$filter_evaltype = $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
			$filter_tags = $this->getInput('filter_tags', []);
		}
		elseif ($this->hasInput('filter_rst') && $this->getInput('filter_rst')) {
			$filter_inherited = -1;
			$filter_discovered = -1;
			$filter_dependent = -1;
			$filter_name = '';
			$filter_priority = [];
			$filter_groupids = [];
			$filter_state = -1;
			$filter_status = -1;
			$filter_value = -1;
			$filter_evaltype = TAG_EVAL_TYPE_AND_OR;
			$filter_tags = [];
			$filter_hostids = $this->getInput('filter_hostids', CProfile::getArray($prefix.'trigger.list.filter_hostids', []));

			if (count($filter_hostids) != 1) {
				$filter_hostids = [];
			}
		}
		else {
			$filter_inherited = CProfile::get($prefix.'trigger.list.filter_inherited', -1);
			$filter_discovered = CProfile::get($prefix.'trigger.list.filter_discovered', -1);
			$filter_dependent = CProfile::get($prefix.'trigger.list.filter_dependent', -1);
			$filter_name = CProfile::get($prefix.'trigger.list.filter_name', '');
			$filter_priority = CProfile::getArray($prefix.'trigger.list.filter_priority', []);
			$filter_groupids = CProfile::getArray($prefix.'trigger.list.filter_groupids', []);
			$filter_hostids = CProfile::getArray($prefix.'trigger.list.filter_hostids', []);
			$filter_state = CProfile::get($prefix.'trigger.list.filter_state', -1);
			$filter_status = CProfile::get($prefix.'trigger.list.filter_status', -1);
			$filter_value = CProfile::get($prefix.'trigger.list.filter_value', -1);
			$filter_evaltype = CProfile::get($prefix.'trigger.list.filter.evaltype', TAG_EVAL_TYPE_AND_OR);
			$filter_tags = [];

			foreach (CProfile::getArray($prefix.'trigger.list.filter.tags.tag', []) as $i => $tag) {
				$filter_tags[] = [
					'tag' => $tag,
					'value' => CProfile::get($prefix.'trigger.list.filter.tags.value', null, $i),
					'operator' => CProfile::get($prefix.'trigger.list.filter.tags.operator', null, $i)
				];
			}
		}

		$checkbox_hash = crc32(implode('', $filter_hostids));
		$ms_groups = [];
		$filter_groupids_enriched = getSubGroups($filter_groupids, $ms_groups, $data['context']);

		if ($filter_hostids) {
			if ($data['context'] === 'host') {
				$filter_hostids = API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $filter_hostids,
					'editable' => true,
					'preservekeys' => true
				]);

				$filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['hostid' => 'id']);
			}
			else {
				$filter_hostids = API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $filter_hostids,
					'editable' => true,
					'preservekeys' => true
				]);

				$filter_hostids_ms = CArrayHelper::renameObjectsKeys($filter_hostids, ['templateid' => 'id']);
			}

			$filter_hostids = array_keys($filter_hostids_ms);
		}

		// Skip empty tags.
		$filter_tags = array_filter($filter_tags, function ($v) {
			return (bool) strlen($v['tag']);
		});

		$sort = $this->getInput('sort', CProfile::get($prefix.'trigger.list.sort', 'description'));
		$sort_order = $this->getInput('sortorder', CProfile::get($prefix.'trigger.list.sortorder', ZBX_SORT_UP));
		$active_tab = CProfile::get($prefix.'trigger.list.filter.active', 1);

		// Get triggers (build options).
		$options = [
			'output' => ['triggerid', $sort],
			'hostids' => $filter_hostids ?: null,
			'groupids' => $filter_groupids ? $filter_groupids_enriched : null,
			'editable' => true,
			'dependent' => ($filter_dependent != -1) ? $filter_dependent : null,
			'templated' => ($filter_value == -1) ? ($data['context'] === 'template') : false,
			'inherited' => ($filter_inherited != -1) ? $filter_inherited : null,
			'preservekeys' => true,
			'sortfield' => $sort,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		];

		if ($sort === 'status') {
			$options['output'][] = 'state';
		}

		if ($filter_discovered != -1) {
			$options['filter']['flags'] = ($filter_discovered == 1)
				? ZBX_FLAG_DISCOVERY_CREATED
				: ZBX_FLAG_DISCOVERY_NORMAL;
		}

		if ($filter_value != -1) {
			$options['filter']['value'] = $filter_value;
		}

		if ($filter_name !== '') {
			$options['search']['description'] = $filter_name;
		}

		if ($filter_priority) {
			$options['filter']['priority'] = $filter_priority;
		}

		switch ($filter_state) {
			case TRIGGER_STATE_NORMAL:
				$options['filter']['state'] = TRIGGER_STATE_NORMAL;
				$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
				break;

			case TRIGGER_STATE_UNKNOWN:
				$options['filter']['state'] = TRIGGER_STATE_UNKNOWN;
				$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
				break;

			default:
				if ($filter_status != -1) {
					$options['filter']['status'] = $filter_status;
				}
		}

		if ($filter_tags) {
			$options['evaltype'] = $filter_evaltype;
			$options['tags'] = $filter_tags;
		}

		$prefetched_triggers = API::Trigger()->get($options);

		if ($sort === 'status') {
			orderTriggersByStatus($prefetched_triggers, $sort_order);
		}
		else {
			order_result($prefetched_triggers, $sort, $sort_order);
		}

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('trigger.list', $page_num);
		$paging = CPagerHelper::paginate($page_num, $prefetched_triggers, $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'trigger.list')
			->setArgument('context', $data['context'])
		);

		// fetch triggers
		$triggers = [];
		if ($prefetched_triggers) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'error', 'templateid', 'state',
					'recovery_mode', 'recovery_expression', 'value', 'opdata', $sort
				],
				'selectHosts' => ['hostid', 'host', 'name', 'status'],
				'selectDependencies' => ['triggerid', 'description'],
				'selectDiscoveryRule' => ['itemid', 'name', 'lifetime_type', 'enabled_lifetime_type'],
				'selectTriggerDiscovery' => ['status', 'ts_delete', 'ts_disable', 'disable_source'],
				'selectTags' => ['tag', 'value'],
				'triggerids' => array_keys($prefetched_triggers),
				'preservekeys' => true,
				'nopermissions' => true
			]);

			foreach ($triggers as &$trigger) {
				CArrayHelper::sort($trigger['hosts'], ['name']);
			}
			unset($trigger);

			// We must maintain sort order that is applied on prefetched_triggers array.
			foreach ($triggers as $triggerid => $trigger) {
				$prefetched_triggers[$triggerid] = $trigger;
			}
			$triggers = $prefetched_triggers;
		}

		$show_info_column = false;
		$show_value_column = false;

		if ($data['context'] === 'host') {
			foreach ($triggers as $trigger) {
				foreach ($trigger['hosts'] as $trigger_host) {
					if (in_array($trigger_host['status'], [HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED])) {
						$show_value_column = true;
						$show_info_column = true;
						break 2;
					}
				}
			}
		}

		$dep_triggerids = [];
		foreach ($triggers as $trigger) {
			foreach ($trigger['dependencies'] as $dep_trigger) {
				$dep_triggerids[$dep_trigger['triggerid']] = true;
			}
		}

		$dep_triggers = [];
		if ($dep_triggerids) {
			$dep_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'status', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => array_keys($dep_triggerids),
				'templated' => ($filter_value != -1) ? false : null,
				'preservekeys' => true
			]);

			foreach ($triggers as &$trigger) {
				order_result($trigger['dependencies'], 'description', ZBX_SORT_UP);
			}
			unset($trigger);

			foreach ($dep_triggers as &$dependencyTrigger) {
				order_result($dependencyTrigger['hosts'], 'name', ZBX_SORT_UP);
			}
			unset($dependencyTrigger);
		}

		$options = [
			'output' => [],
			'triggerids' => array_keys($triggers),
			'editable' => true,
			'preservekeys' => true
		];
		$editable_hosts = $data['context'] === 'host' ? API::Host()->get($options) : API::Template()->get($options);
		$data['editable_hosts'] = array_keys($editable_hosts);

		CProfile::update($prefix.'trigger.list.sort', $sort, PROFILE_TYPE_STR);
		CProfile::update($prefix.'trigger.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set') && $this->getInput('filter_set')) {
			CProfile::update($prefix.'trigger.list.filter_inherited', $filter_inherited, PROFILE_TYPE_INT);
			CProfile::update($prefix.'trigger.list.filter_discovered', $filter_discovered, PROFILE_TYPE_INT);
			CProfile::update($prefix.'trigger.list.filter_dependent', $filter_dependent, PROFILE_TYPE_INT);
			CProfile::update($prefix.'trigger.list.filter_name', $filter_name, PROFILE_TYPE_STR);
			CProfile::updateArray($prefix.'trigger.list.filter_priority', $filter_priority, PROFILE_TYPE_INT);
			CProfile::updateArray($prefix.'trigger.list.filter_groupids', $filter_groupids, PROFILE_TYPE_ID);
			CProfile::updateArray($prefix.'trigger.list.filter_hostids', $filter_hostids, PROFILE_TYPE_ID);
			CProfile::update($prefix.'trigger.list.filter_state', $filter_state, PROFILE_TYPE_INT);
			CProfile::update($prefix.'trigger.list.filter_status', $filter_status, PROFILE_TYPE_INT);
			CProfile::update($prefix.'trigger.list.filter.evaltype', $filter_evaltype, PROFILE_TYPE_INT);

			$filter_tags_fmt = ['tags' => [], 'values' => [], 'operators' => []];

			foreach ($filter_tags as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags_fmt['tags'][] = $filter_tag['tag'];
				$filter_tags_fmt['values'][] = $filter_tag['value'];
				$filter_tags_fmt['operators'][] = $filter_tag['operator'];
			}

			CProfile::updateArray($prefix.'trigger.list.filter.tags.tag', $filter_tags_fmt['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray($prefix.'trigger.list.filter.tags.value', $filter_tags_fmt['values'],
				PROFILE_TYPE_STR
			);
			CProfile::updateArray($prefix.'trigger.list.filter.tags.operator', $filter_tags_fmt['operators'],
				PROFILE_TYPE_INT
			);

			if ($show_value_column) {
				CProfile::update($prefix.'trigger.list.filter_value', $filter_value, PROFILE_TYPE_INT);
			}
		}
		elseif (getRequest('filter_rst')) {
			CProfile::deleteIdx($prefix.'trigger.list.filter_inherited');
			CProfile::deleteIdx($prefix.'trigger.list.filter_discovered');
			CProfile::deleteIdx($prefix.'trigger.list.filter_dependent');
			CProfile::deleteIdx($prefix.'trigger.list.filter_name');
			CProfile::deleteIdx($prefix.'trigger.list.filter_priority');
			CProfile::deleteIdx($prefix.'trigger.list.filter_groupids');

			if (count($filter_hostids) != 1) {
				CProfile::deleteIdx($prefix.'trigger.list.filter_hostids');
			}

			CProfile::deleteIdx($prefix.'trigger.list.filter_state');
			CProfile::deleteIdx($prefix.'trigger.list.filter_status');
			CProfile::deleteIdx($prefix.'trigger.list.filter.evaltype');
			CProfile::deleteIdx($prefix.'trigger.list.filter.tags.tag');
			CProfile::deleteIdx($prefix.'trigger.list.filter.tags.value');
			CProfile::deleteIdx($prefix.'trigger.list.filter.tags.operator');

			if ($show_value_column) {
				CProfile::deleteIdx($prefix.'trigger.list.filter_value');
			}
		}

		$single_selected_hostid = 0;
		if (count($filter_hostids) == 1) {
			$single_selected_hostid = reset($filter_hostids);
		}

		sort($filter_hostids);

		$data += [
			'triggers' => $triggers,
			'profileIdx' => $prefix.'trigger.list.filter',
			'active_tab' => $active_tab,
			'sort' => $sort,
			'sortorder' => $sort_order,
			'filter_groupids_ms' => $ms_groups,
			'filter_hostids_ms' => $filter_hostids_ms,
			'filter_name' => $filter_name,
			'filter_priority' => $filter_priority,
			'filter_state' => $filter_state,
			'filter_status' => $filter_status,
			'filter_value' => $filter_value,
			'filter_tags' => $filter_tags,
			'filter_evaltype' => $filter_evaltype,
			'filter_inherited' => $filter_inherited,
			'filter_discovered' => $filter_discovered,
			'filter_dependent' => $filter_dependent,
			'checkbox_hash' => $checkbox_hash,
			'show_info_column' => $show_info_column,
			'show_value_column' => $show_value_column,
			'single_selected_hostid' => $single_selected_hostid,
			'parent_templates' => getTriggerParentTemplates($triggers, ZBX_FLAG_DISCOVERY_NORMAL),
			'paging' => $paging,
			'dep_triggers' => $dep_triggers,
			'tags' => makeTags($triggers, true, 'triggerid', ZBX_TAG_COUNT_DEFAULT, $filter_tags),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of triggers'));
		$this->setResponse($response);
	}
}
