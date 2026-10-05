<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CLldRulePrototypeHelper extends CItemGeneralHelper {

	/**
	 * Get lld rule protype fields default values.
	 */
	public static function getDefaults(): array {
		$item = parent::getDefaults();

		$item['delay'] = ZBX_LLD_RULE_DELAY_DEFAULT;
		$item['overrides'] = [];
		$item['lld_macro_paths'] = [];
		$item['filter'] = [
			'evaltype' => DB::getDefault('items', 'evaltype'),
			'formula' => DB::getDefault('items', 'formula'),
			'conditions'=> []
		];
		$item['discovered_lld'] = false;
		$item['lifetime_type'] = DB::getDefault('items', 'lifetime_type');
		$item['lifetime'] = DB::getDefault('items', 'lifetime');
		$item['enabled_lifetime_type'] = DB::getDefault('items', 'enabled_lifetime_type');
		$item['enabled_lifetime'] = DB::getDefault('items', 'enabled_lifetime');
		$item['discover'] = DB::getDefault('items', 'discover');

		return $item;
	}

	/**
	 * Convert API data to be ready to use for edit or create form.
	 *
	 * @param array $item  Array of API fields data.
	 */
	public static function convertApiInputForForm(array $item): array {
		$item = parent::convertApiInputForForm($item);
		$item['parent_items'] = makeItemTemplatesHtml(
			$item['itemid'],
			getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL),
			ZBX_FLAG_DISCOVERY_NORMAL,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => false
		]);

		if ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item = static::addDelayWithFlexibleIntervals($update_interval_parser, $item);
		}
		else {
			$item['delay'] = ZBX_LLD_RULE_DELAY_DEFAULT;
			$item['delay_flex'] = [];
		}

		$item['discovered_lld'] = $item['flags'] & ZBX_FLAG_DISCOVERY_CREATED
			&& ($item['flags'] & ZBX_FLAG_DISCOVERY_PROTOTYPE || $item['flags'] & ZBX_FLAG_DISCOVERY_RULE);

		if ($item['master_itemid']) {
			$master_item = API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $item['master_itemid'],
				'webitems' => true
			]);
			$item['master_item'] = $master_item ? reset($master_item) : [];
		}

		return $item;
	}

	/**
	 * @param array $src_options
	 * @param array $dst_hosts
	 * @param array $dst_itemids
	 *
	 * @return bool
	 */
	public static function copy(array $src_options, array $dst_hosts, array $dst_itemids): bool {
		$src_items = CLldRuleHelper::getSourceLldRules(true, $src_options);

		if (!$src_items) {
			return true;
		}

		try {
			$dst_interfaceids = self::getDestinationHostInterfaces($src_items, $dst_hosts);
			$dst_master_itemids = self::getDestinationMasterItems($src_items, $dst_hosts,
				ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE
			);
		}
		catch (Exception $e) {
			return false;
		}

		$dst_items = [];

		foreach ($dst_hosts as $dst_hostid => $dst_host) {
			foreach ($src_items as $src_item) {
				$dst_item = array_diff_key($src_item, array_flip(['itemid', 'hosts']));

				if (array_key_exists($src_item['itemid'], $dst_interfaceids)) {
					$dst_item['interfaceid'] = $dst_interfaceids[$src_item['itemid']][$dst_hostid];
				}

				if (array_key_exists($src_item['itemid'], $dst_master_itemids)) {
					$dst_item['master_itemid'] = $dst_master_itemids[$src_item['itemid']][$dst_hostid];
				}

				$parent_lld = $src_item['discoveryRule'] ?: $src_item['discoveryRulePrototype'];

				$dst_items[] = [
					'hostid' => $dst_hostid,
					'ruleid' => $dst_itemids[$parent_lld['itemid']][$dst_hostid]
				] + getSanitizedItemFields([
					'templateid' => 0,
					'flags' => ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE,
					'hosts' => [$dst_host]
				] + $dst_item);
			}
		}

		$response = API::DiscoveryRulePrototype()->create($dst_items);

		if ($response === false) {
			return false;
		}

		$dst_itemids = [];

		foreach ($dst_hosts as $dst_hostid => $foo) {
			foreach ($src_items as $src_item) {
				$dst_itemids[$src_item['itemid']][$dst_hostid] = array_shift($response['itemids']);
			}
		}

		$src_options = ['discoveryids' => array_keys($src_items)];
		$dst_options = reset($dst_hosts)['status'] == HOST_STATUS_TEMPLATE
			? ['templateids' => array_keys($dst_hosts)]
			: ['hostids' => array_keys($dst_hosts)];

		return CItemPrototypeHelper::copy($dst_itemids, $dst_hosts)
			&& CTriggerPrototypeHelper::copy($src_options, $dst_options)
			&& CGraphPrototypeHelper::copy($src_options, $dst_options)
			&& CHostPrototypeHelper::copy($src_options, $dst_options, $dst_itemids)
			&& self::copy($src_options, $dst_hosts, $dst_itemids);
	}

	public static function convertFormInputForApi(array $input): array {
		$input['filter'] = prepareLldFilter($input['filter']);
		$input['lld_macro_paths'] = prepareLldMacroPaths($input['lld_macro_paths']);
		$input['overrides'] = prepareLldOverrides($input['overrides']);

		return parent::convertFormInputForApi($input);
	}

	public static function normalizeFormData(array $input): array {
		if (array_key_exists('conditions', $input)) {
			$input['filter'] = [
				'evaltype' => array_key_exists('evaltype', $input)
					? $input['evaltype']
					: DB::getDefault('items', 'evaltype'),
				'formula' => array_key_exists('formula', $input)
					? $input['formula']
					: DB::getDefault('items', 'formula'),
				'conditions' => array_key_exists('conditions', $input) ? $input['conditions'] : []
			];
		}

		unset($input['evaltype']);
		unset($input['formula']);
		unset($input['conditions']);

		return parent::normalizeFormData($input);
	}
}
