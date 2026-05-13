<?php
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


class CDataTableMacrosResolver extends CMacrosResolver {

	/**
	 * @param string $section
	 * @param array  $data
	 * @param array  $options
	 *
	 * @return array
	 */
	public static function resolveForSection(string $section, array $data, array $options = []): array {
		return match ($section) {
			'hosts' => self::resolveHostsSection($data, $options),
			'templates' => self::resolveTemplatesSection($data, $options),
			'latest_data' => self::resolveLatestDataSection($data, $options),
			'problems' => self::resolveProblemsSection($data, $options)
		};
	}

	/**
	 * @param array $data
	 * @param array $options
	 *
	 * @return array
	 */
	private static function resolveHostsSection(array $data, array $options = []): array {
		$macros = [
			'host' => [],
			'interface' => [],
			'inventory' => [],
			'usermacros' => []
		];

		$types = [
			'macros' => [
				'host' => self::HOST_MACROS,
				'interface' => self::INTERFACE_MACROS,
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap())
			],
			'usermacros' => true
		];

		$macro_values = [];

		foreach ($data as $hostid => $texts) {
			$matched_macros = self::extractMatchedMacros($texts, $types, $hostid, $macro_values, $macros);

			if (array_key_exists('usermacros', $matched_macros)) {
				$macros['usermacros'][$hostid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByHostId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByHostId($macros['interface'], $macro_values);
		$macro_values = self::getInventoryMacrosByHostId($macros['inventory'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		return self::applyMacroValues($data, $macro_values);
	}

	/**
	 * @param array $data
	 * @param array $options
	 *
	 * @return array
	 */
	private static function resolveTemplatesSection(array $data, array $options = []): array {
		$macros = ['usermacros' => []];

		$types = ['usermacros' => true];

		$macro_values = [];

		foreach ($data as $templateid => $texts) {
			$matched_macros = self::extractMatchedMacros($texts, $types, $templateid, $macro_values, $macros);

			if (array_key_exists('usermacros', $matched_macros)) {
				$macros['usermacros'][$templateid] = ['hostids' => [], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		return self::applyMacroValues($data, $macro_values);
	}

	/**
	 * @param array $data
	 * @param array $options
	 *
	 * @return array
	 */
	private static function resolveLatestDataSection(array $data, array $options = []): array {
		$macros = [
			'host' => [],
			'interface' => [],
			'inventory' => [],
			'item' => [],
			'itemvalue' => [],
			'log' => [],
			'usermacros' => []
		];

		$types = [
			'macros' => [
				'host' => self::HOST_MACROS,
				'interface' => self::INTERFACE_MACROS,
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap()),
				'item' => self::ITEM_MACROS,
				'itemvalue' => self::ITEM_VALUE_MACROS,
				'log' => self::ITEM_LOG_MACROS
			],
			'usermacros' => true
		];

		$macro_values = [];

		foreach ($data as $itemid => $texts) {
			$matched_macros = self::extractMatchedMacros($texts, $types, $itemid, $macro_values, $macros);

			if (array_key_exists('usermacros', $matched_macros)) {
				$hostid = $options['items'][$itemid]['hostid'];

				$macros['usermacros'][$itemid] = ['hostids' => [$hostid], 'macros' => $matched_macros['usermacros']];
			}
		}

		$macro_values = self::getHostMacrosByItemId($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacrosByItemId($macros['interface'], $macro_values);
		$macro_values = self::getItemMacrosByItemId($macros['item'], $macro_values);
		$macro_values = self::getItemValueMacrosByItemId($macros['itemvalue'], $macro_values);
		$macro_values = self::getItemLogMacrosByItemId($macros['log'], $macro_values);
		$macro_values = self::getInventoryMacrosByItemId($macros['inventory'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		return self::applyMacroValues($data, $macro_values);
	}

	/**
	 * @param array $data
	 * @param array $options
	 *
	 * @return array
	 */
	private static function resolveProblemsSection(array $data, array $options = []): array {
		$macros = [
			'host' => [],
			'interface' => [],
			'inventory' => [],
			'item' => [],
			'itemvalue' => [],
			'trigger' => [],
			'event' => [],
			'log' => [],
			'usermacros' => []
		];

		$types = [
			'macros' => [
				'trigger' => self::TRIGGER_MACROS,
				'event' => self::EVENT_MACROS
			],
			'macros_n' => [
				'host' => self::HOST_MACROS,
				'interface' => self::INTERFACE_MACROS,
				'inventory' => array_keys(self::getSupportedHostInventoryMacrosMap()),
				'item' => self::ITEM_MACROS,
				'itemvalue' => self::ITEM_VALUE_MACROS,
				'log' => self::ITEM_LOG_MACROS
			],
			'usermacros' => true
		];

		$macro_values = [];
		$db_triggers = $options['triggers'] ?? [];
		$db_items = [];

		if ($db_triggers) {
			$db_items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'name_resolved', 'key_', 'value_type', 'state', 'description'],
				'triggerids' => array_keys($db_triggers),
				'webitems' => true,
				'preservekeys' => true
			]);

			$descriptions = array_map(static fn (array $trigger) => $trigger['description'], $db_triggers);

			$db_triggers = self::resolveTriggerNames($db_triggers, ['references_only' => false]);
			$db_triggers = self::resolveTriggerDescriptions($db_triggers, ['sources' => 'comments', 'events' => false,
				'html' => false]);
			$db_triggers = self::resolveTriggerExpressions($db_triggers, ['resolve_usermacros' => true,
				'resolve_functionids' => false]);

			foreach ($descriptions as $triggerid => $description) {
				$db_triggers[$triggerid]['description_original'] = $description;
			}
		}

		foreach ($data as $triggerid => $texts) {
			$trigger = $db_triggers[$triggerid] ?? null;

			$functionids = ($trigger && array_key_exists('expression', $trigger))
				? self::findFunctions($trigger['expression'])
				: [];

			$matched_macros = self::extractMacros($texts, $types);

			foreach ($matched_macros['macros_n'] ?? [] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $mdata) {
					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;

					if (array_key_exists($mdata['f_num'], $functionids)) {
						$macros[$sub_type][$functionids[$mdata['f_num']]][$mdata['macro']][] =
							['token' => $token] + array_intersect_key($mdata, ['macrofunc' => null]);
					}
				}
			}

			foreach ($matched_macros['macros'] ?? [] as $sub_type => $macro_data) {
				foreach ($macro_data as $token => $mdata) {
					if ($sub_type === 'trigger' && $trigger) {
						$macro_values[$triggerid][$token] = self::resolveTriggerMacro($mdata['macro'],
							$triggerid, $trigger, $mdata);

						continue;
					}

					if ($sub_type === 'event') {
						$event = $options['events_data'][$triggerid] ?? null;
						$macro_values[$triggerid][$token] = $event
							? self::resolveEventMacro($mdata['macro'], $triggerid, $event, $mdata)
							: UNRESOLVED_MACRO_STRING;

						continue;
					}

					$macro_values[$triggerid][$token] = UNRESOLVED_MACRO_STRING;
					$macros[$sub_type][$triggerid][$mdata['macro']][] =
						['token' => $token] + array_intersect_key($mdata, ['macrofunc' => null]);
				}
			}

			if (array_key_exists('usermacros', $matched_macros)) {
				$macros['usermacros'][$triggerid] = [
					'hostids' => [],
					'macros' => $matched_macros['usermacros']
				];
			}
		}

		$item_options = array_key_exists('item_options', $options)
			? array_intersect_key($options['item_options'], array_flip(['events', 'html']))
			: [];

		$macro_values = self::resolveExpressionMacros($data, $macro_values);
		$macro_values = self::getHostMacros($macros['host'], $macro_values);
		$macro_values = self::getInterfaceMacros($macros['interface'], $macro_values);
		$macro_values = self::getItemMacros($macros['item'], $macro_values, $db_triggers, $db_items, $item_options);
		$macro_values = self::getItemValueMacros($macros['itemvalue'], $macro_values);
		$macro_values = self::getItemLogMacros($macros['log'], $macro_values);
		$macro_values = self::getInventoryMacros($macros['inventory'], $macro_values);
		$macro_values = self::getTriggerUserMacros($macros['usermacros'], $macro_values);
		$macro_values = self::getUserMacros($macros['usermacros'], $macro_values);

		return self::applyMacroValues($data, $macro_values);
	}

	/**
	 * @param array  $texts
	 * @param array  $types
	 * @param string $objectid
	 * @param array  $macro_values
	 * @param array  $macros
	 *
	 * @return array
	 */
	protected static function extractMatchedMacros(array $texts, array $types, string $objectid, array &$macro_values,
			array &$macros): array {
		$matched_macros = self::extractMacros($texts, $types);

		foreach ($matched_macros['macros'] ?? [] as $sub_type => $macro_data) {
			foreach ($macro_data as $token => $mdata) {
				$macro_values[$objectid][$token]                 = UNRESOLVED_MACRO_STRING;
				$macros[$sub_type][$objectid][$mdata['macro']][] =
					['token' => $token] + array_intersect_key($mdata, ['macrofunc' => null]);
			}
		}

		return $matched_macros;
	}

	/**
	 * @param array $data
	 * @param array $macro_values
	 *
	 * @return array
	 */
	private static function resolveExpressionMacros(array $data, array $macro_values): array {
		$types  = ['expr_macros' => true];

		$macros = ['expr_macros' => []];

		foreach ($data as $key => $texts) {
			$matched = self::extractMacros($texts, $types);

			foreach ($matched['expr_macros'] ?? [] as $macro => $mdata) {
				$macro_values[$key][$macro] = UNRESOLVED_MACRO_STRING;

				if (!array_key_exists($macro, $macros['expr_macros'])) {
					$macros['expr_macros'][$macro] = $mdata;
				}

				$macros['expr_macros'][$macro]['links'][$macro][] = $key;
			}
		}

		foreach (self::getExpressionMacros($macros['expr_macros'], []) as $macro => $value) {
			foreach ($macros['expr_macros'][$macro]['links'] as $orig_macro => $keys) {
				foreach ($keys as $key) {
					$macro_values[$key][$orig_macro] = $value;
				}
			}
		}

		return $macro_values;
	}

	private static function applyMacroValues(array $data, array $macro_values): array {
		foreach ($macro_values as $id => $values) {
			if (!array_key_exists($id, $data)) {
				continue;
			}

			if (is_array($data[$id])) {
				foreach ($data[$id] as &$text) {
					$text = strtr($text, $values);
				}
				unset($text);
			} else {
				$data[$id] = strtr($data[$id], $values);
			}
		}

		return $data;
	}
}
