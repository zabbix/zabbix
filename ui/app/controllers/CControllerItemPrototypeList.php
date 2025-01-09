<?php declare(strict_types=0);
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


class CControllerItemPrototypeList extends CControllerItemPrototype {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'context'				=> 'required|in host,template',
			'parent_discoveryid'	=> 'required|id',
			'sort'					=> 'in '.implode(',', ['delay', 'history', 'key_', 'name', 'status', 'trends', 'type', 'discover']),
			'sortorder'				=> 'in '.implode(',', [ZBX_SORT_DOWN.','.ZBX_SORT_UP]),
			'page'					=> 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$ret = (bool) API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'itemids' => $this->getInput('parent_discoveryid'),
				'editable' => true
			]);

			if (!$ret) {
				error(_s('Incorrect value for "%1$s" field.', 'parent_discoveryid'));
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	public function doAction() {
		$profile = $this->getProfiles();

		if ($this->getInput('sort', $profile['sort']) !== $profile['sort']
				|| $this->getInput('sortorder', $profile['sortorder']) !== $profile['sortorder']) {
			$this->getInputs($profile, ['sort', 'sortorder']);
			$this->updateProfileSort();
		}

		[$lld] = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'selectHosts' => ['status'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		]);
		$data = [
			'action' => $this->getAction(),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'context' => $this->getInput('context'),
			'hostid' => $lld['hostid'],
			'items' => [],
			'parent_discoveryid' => $this->getInput('parent_discoveryid'),
			'sort' => $profile['sort'],
			'sortorder' => $profile['sortorder']
		];
		$context = $this->getInput('context');
		$is_template_lld = $lld['hosts'][0]['status'] == HOST_STATUS_TEMPLATE;

		if (($context === 'template' && $is_template_lld) || ($context === 'host' && !$is_template_lld)) {
			$data['items'] = $this->getItems($profile);
		}

		$data['paging'] = CPagerHelper::paginate($this->getInput('page', 1), $data['items'], $profile['sort'],
			(new CUrl('zabbix.php'))
				->setArgument('action', $data['action'])
				->setArgument('parent_discoveryid', $data['parent_discoveryid'])
				->setArgument('context', $data['context'])
		);
		$data['parent_templates'] = getItemParentTemplates($data['items'], ZBX_FLAG_DISCOVERY_PROTOTYPE);
		$data['tags'] = makeTags($data['items'], true, 'itemid', ZBX_TAG_COUNT_DEFAULT);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of item prototypes'));
		$this->setResponse($response);
	}

	protected function getItems(array $profile): array {
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$items = API::ItemPrototype()->get([
			'output' => [
				'delay', 'history', 'key_', 'name', 'status', 'trends', 'type', 'discover',
				'itemid', 'templateid', 'value_type', 'master_itemid'
			],
			'discoveryids' => [$this->getInput('parent_discoveryid')],
			'selectTags' => ['tag', 'value'],
			'editable' => true,
			'sortfield' => $profile['sort'],
			'limit' => $limit
		]);

		$items = expandItemNamesWithMasterItems($items, 'itemprototypes');

		switch ($profile['sort']) {
			case 'delay':
				orderItemsByDelay($items, $profile['sortorder'], ['usermacros' => true, 'lldmacros' => true]);
				break;

			case 'history':
				orderItemsByHistory($items, $profile['sortorder']);
				break;

			case 'trends':
				orderItemsByTrends($items, $profile['sortorder']);
				break;

			default:
				order_result($items, $profile['sort'], $profile['sortorder']);
		}

		$interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);

		foreach ($items as &$item) {
			if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT,
					ITEM_VALUE_TYPE_BINARY])) {
				$item['trends'] = '';
			}

			// Hide zeros for trapper, SNMP trap and dependent items.
			if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
					|| $item['type'] == ITEM_TYPE_DEPENDENT
					|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) == 0)) {
				$item['delay'] = '';
			}
			elseif ($interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
				$item['delay'] = $interval_parser->getDelay();
			}
		}
		unset($item);

		return $items;
	}

	protected function getProfiles(): array {
		$prefix = $this->getInput('context') === 'host'
			? 'web.hosts.items.prototypes.list.' : 'web.templates.items.prototypes.list.';
		$profile = [
			'sort'				=> CProfile::get($prefix.'sort', 'name'),
			'sortorder' 		=> CProfile::get($prefix.'sortorder', ZBX_SORT_UP)
		];

		return $profile;
	}

	protected function updateProfileSort() {
		$prefix = $this->getInput('context') === 'host'
			? 'web.hosts.items.prototypes.list.' : 'web.templates.items.prototypes.list.';

		if ($this->hasInput('sort')) {
			CProfile::update($prefix.'sort', $this->getInput('sort'), PROFILE_TYPE_STR);
		}

		if ($this->hasInput('sortorder')) {
			CProfile::update($prefix.'sortorder', $this->getInput('sortorder'), PROFILE_TYPE_STR);
		}
	}
}
