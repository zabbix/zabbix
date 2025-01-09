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


class CTriggerGeneralHelper {

	/**
	 * @param array  $src_hosts
	 * @param string $src_hosts[<src_master_triggerid>][<src_triggerid>]  Source host.
	 * @param array  $dst_hosts
	 * @param array  $dst_hosts[<dst_hostid>]                             Destination host.
	 *
	 * @return array [<src_master_triggerid>][<dst_hostid>] = <dst_master_triggerid>
	 *
	 * @throws Exception
	 */
	protected static function getDestinationMasterTriggers(array $src_hosts, array $dst_hosts): array {
		if (!$src_hosts) {
			return [];
		}

		$dst_hostids = array_keys($dst_hosts);

		$src_master_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_expression'],
			'selectHosts' => ['hostid', 'host'],
			'triggerids' => array_keys($src_hosts),
			'preservekeys' => true
		]);

		$src_master_triggers = CMacrosResolverHelper::resolveTriggerExpressions($src_master_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$src_descriptions = [];
		$dst_master_triggerids = [];

		foreach ($src_master_triggers as $src_master_trigger) {
			$src_master_trigger_hostids = array_column($src_master_trigger['hosts'], 'hostid');
			$src_hostids = [];

			foreach ($src_hosts[$src_master_trigger['triggerid']] as $src_host) {
				if (in_array($src_host['hostid'], $src_master_trigger_hostids)) {
					$src_descriptions[$src_master_trigger['description']] = true;

					$src_hostids[$src_host['hostid']] = true;
				}
			}

			if (count($src_hostids) == 1) {
				foreach ($dst_hostids as $dst_hostid) {
					$dst_master_triggerids[$src_master_trigger['triggerid']][$dst_hostid] = 0;
				}
			}
			else {
				foreach ($src_hostids as $src_hostid => $foo) {
					foreach ($dst_hostids as $dst_hostid) {
						$dst_master_triggerids[$src_master_trigger['triggerid']][$dst_hostid][$src_hostid] = 0;
					}
				}
			}
		}

		if (!$src_descriptions) {
			return [];
		}

		$dst_master_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'recovery_expression'],
			'selectHosts' => ['hostid', 'host'],
			'hostids' => $dst_hostids,
			'filter' => ['description' => array_keys($src_descriptions)],
			'preservekeys' => true
		]);

		$dst_master_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dst_master_triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$_dst_master_triggerids = [];

		foreach ($dst_master_triggers as &$dst_master_trigger) {
			$dst_master_trigger['hosts'] = array_column($dst_master_trigger['hosts'], null, 'hostid');

			$_dst_hostids = array_intersect(array_keys($dst_master_trigger['hosts']), $dst_hostids);

			foreach ($_dst_hostids as $_dst_hostid) {
				$_dst_master_triggerids[$dst_master_trigger['description']][$_dst_hostid][] =
					$dst_master_trigger['triggerid'];
			}
		}
		unset($dst_master_trigger);

		foreach ($dst_master_triggerids as $src_master_triggerid => &$dst_host_master_triggers) {
			$src_master_trigger = $src_master_triggers[$src_master_triggerid];

			$description = $src_master_trigger['description'];

			if (!array_key_exists($description, $_dst_master_triggerids)) {
				self::throwTriggerCopyException(
					key($src_hosts[$src_master_triggerid]), $description, reset($dst_hosts)
				);
			}

			foreach ($dst_host_master_triggers as $dst_hostid => &$dst_triggerid) {
				if (!array_key_exists($dst_hostid, $_dst_master_triggerids[$description])) {
					self::throwTriggerCopyException(
						key($src_hosts[$src_master_triggerid]), $description, $dst_hosts[$dst_hostid]
					);
				}

				foreach ($_dst_master_triggerids[$description][$dst_hostid] as $_dst_triggerid) {
					$dst_host = $dst_master_triggers[$_dst_triggerid]['hosts'][$dst_hostid];

					foreach ($src_hosts[$src_master_triggerid] as $src_host) {
						$expression = self::getExpressionWithReplacedHost(
							$dst_master_triggers[$_dst_triggerid]['expression'], $dst_host['host'], $src_host['host']
						);

						if ($expression !== $src_master_trigger['expression']) {
							continue;
						}

						$recovery_expression = self::getExpressionWithReplacedHost(
							$dst_master_triggers[$_dst_triggerid]['recovery_expression'], $dst_host['host'],
							$src_host['host']
						);

						if ($recovery_expression !== $src_master_trigger['recovery_expression']) {
							continue;
						}

						if (is_array($dst_triggerid)) {
							$dst_triggerid[$src_host['hostid']] = $_dst_triggerid;
						}
						else {
							$dst_triggerid = $_dst_triggerid;
						}
					}

					if ((is_array($dst_triggerid) && !in_array(0, $dst_triggerid))
							|| (!is_array($dst_triggerid) && $dst_triggerid != 0)) {
						break;
					}
				}

				$dst_triggerids = is_array($dst_triggerid) ? $dst_triggerid : [$dst_triggerid];

				foreach ($dst_triggerids as $_dst_triggerid) {
					if ($_dst_triggerid == 0) {
						self::throwTriggerCopyException(
							key($src_hosts[$src_master_triggerid]), $description, $dst_hosts[$dst_hostid]
						);
					}
				}
			}
			unset($dst_triggerid);
		}
		unset($dst_host_master_triggers);

		return $dst_master_triggerids;
	}

	/**
	 * @param string $src_triggerid
	 * @param string $src_master_description
	 * @param array  $dst_host
	 *
	 * @throws Exception
	 */
	private static function throwTriggerCopyException(string $src_triggerid, string $src_master_description,
			array $dst_host): void {
		$src_triggers = API::Trigger()->get([
			'output' => ['description'],
			'triggerids' => $src_triggerid
		]);

		$error = array_key_exists('status', $dst_host)
			? _('Cannot copy trigger "%1$s" without the trigger "%2$s", on which it depends, to the host "%3$s".')
			: _('Cannot copy trigger "%1$s" without the trigger "%2$s", on which it depends, to the template "%3$s".');

		error(sprintf($error, $src_triggers[0]['description'], $src_master_description, $dst_host['host']));

		throw new Exception();
	}

	/**
	 * Replaces a host in the trigger expression with the one provided.
	 * nodata(/localhost/agent.ping, 5m)  =>  nodata(/localhost6/agent.ping, 5m)
	 *
	 * @param string $expression  Full expression with host names and item keys.
	 * @param string $src_host
	 * @param string $dst_host
	 *
	 * @return string
	 */
	public static function getExpressionWithReplacedHost(string $expression, string $src_host,
			string $dst_host): string {
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);

		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			$hist_functions = $expression_parser->getResult()->getTokensOfTypes(
				[CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]
			);
			$hist_function = end($hist_functions);

			do {
				$query_parameter = $hist_function['data']['parameters'][0];

				if ($query_parameter['data']['host'] === $src_host) {
					$expression = substr_replace($expression, '/'.$dst_host.'/'.$query_parameter['data']['item'],
						$query_parameter['pos'], $query_parameter['length']
					);
				}
			}
			while ($hist_function = prev($hist_functions));
		}

		return $expression;
	}

	/**
	 * Collects information about the existing trigger.
	 *
	 * @param array $trigger
	 * @param array $input_data
	 * @return array
	 */
	public static function getAdditionalTriggerData(array $trigger, array $input_data): array {
		$flag = (array_key_exists('parent_discoveryid', $input_data))
			? ZBX_FLAG_DISCOVERY_PROTOTYPE
			: ZBX_FLAG_DISCOVERY_NORMAL;
		$resolved_triggers = CMacrosResolverHelper::resolveTriggerExpressions([$trigger],
			['sources' => ['expression', 'recovery_expression']]
		);

		if ($input_data['hostid'] == 0) {
			$input_data['hostid'] = $trigger['hosts'][0]['hostid'];
		}

		$data = array_merge($input_data, reset($resolved_triggers));

		// Get templates.
		$data['templates'] = makeTriggerTemplatesHtml($data['triggerid'],
			getTriggerParentTemplates([$data], $flag), $flag,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);

		if ($data['show_inherited_tags']) {
			$data['tags'] = self::getInheritedTags($data, $input_data['tags']);
		}

		$data['limited'] = ($data['templateid'] != 0);

		return $data;
	}

	/**
	 * Add trigger inherited tags to $data['tags'] array of trigger tags.
	 *
	 * @param array $data
	 * @param array $input_tags
	 */
	public static function getInheritedTags(array $data, array $input_tags): array {
		$items = [];
		$item_prototypes = [];

		foreach ($data['items'] as $item) {
			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$items[] = $item;
			}
			else {
				$item_prototypes[] = $item;
			}
		}

		$item_parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL)['templates']
			+ getItemParentTemplates($item_prototypes, ZBX_FLAG_DISCOVERY_PROTOTYPE)['templates'];

		unset($item_parent_templates[0]);

		$db_templates = $item_parent_templates
			? API::Template()->get([
				'output' => ['templateid'],
				'selectTags' => ['tag', 'value'],
				'templateids' => array_keys($item_parent_templates),
				'preservekeys' => true
			])
			: [];

		$inherited_tags = [];

		// Make list of parent template tags.
		foreach ($item_parent_templates as $templateid => $template) {
			if (array_key_exists($templateid, $db_templates)) {
				foreach ($db_templates[$templateid]['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $inherited_tags)
							&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
						$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
							$templateid => $template
						];
					}
					else {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
							'parent_templates' => [$templateid => $template],
							'type' => ZBX_PROPERTY_INHERITED
						];
					}
				}
			}
		}

		$db_hosts = API::Host()->get([
			'output' => [],
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['hostid'],
			'templated_hosts' => true
		]);

		// Overwrite and attach host level tags.
		if ($db_hosts) {
			foreach ($db_hosts[0]['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag;
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
			}
		}

		// Overwrite and attach trigger's own tags.
		foreach ($input_tags as $tag) {
			if (array_key_exists($tag['tag'], $inherited_tags)
					&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
				if (!array_key_exists('type', $tag) || $tag['type'] != ZBX_PROPERTY_INHERITED) {
					$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
				}
			}
			else {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
			}
		}

		$data['tags'] = [];

		foreach ($inherited_tags as $tag) {
			foreach ($tag as $value) {
				$data['tags'][] = $value;
			}
		}

		return $data['tags'];
	}

	/**
	 * Extracts trigger or trigger prototype dependent triggers or trigger prototypes.
	 *
	 * @param array $data
	 */
	public static function getDependencies(array &$data): void {
		if ($data['dependencies']) {
			$data['db_dependencies'] = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => array_column($data['dependencies'], 'triggerid'),
				'preservekeys' => true
			]);

			if (array_key_exists('parent_discoveryid', $data)) {
				$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
					'output' => ['triggerid', 'description', 'flags'],
					'selectHosts' => ['hostid', 'name'],
					'triggerids' => array_column($data['dependencies'], 'triggerid'),
					'preservekeys' => true
				]);

				$data['db_dependencies'] += $dependencyTriggerPrototypes;
			}
		}

		foreach ($data['db_dependencies'] as &$dependency) {
			order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
		}
		unset($dependency);

		order_result($data['db_dependencies'], 'description');

		$data['db_dependencies'] = array_values($data['db_dependencies']);
	}

	/**
	 * Takes data returned from API and transforms it to data that matches trigger edit form fields.
	 *
	 * @param array $db_trigger
	 * @return array
	 */
	public static function convertApiInputForForm(array $db_trigger): array {
		$triggers = CMacrosResolverHelper::resolveTriggerExpressions([$db_trigger],
			['sources' => ['expression', 'recovery_expression']]
		);
		$trigger = reset($triggers);

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			unset($trigger['hostid']);
		}
		else {
			$data['hostid'] = $trigger['hosts'][0]['hostid'];
		}

		if ($trigger['tags']) {
			CArrayHelper::sort($trigger['tags'], ['tag', 'value']);
			$trigger['tags'] = array_values($trigger['tags']);
		}

		$data = [
			'description' => $trigger['comments'],
			'name' => $trigger['description']
		];

		unset($trigger['comments'], $trigger['hosts'], $trigger['discoveryRule'], $trigger['flags'], $trigger['state'],
			$trigger['templateid'], $trigger['triggerDiscovery'], $trigger['dependencies'], $trigger['correlation_tag'],
			$trigger['items']
		);

		$data = array_merge($trigger, $data);

		return $data;
	}
}
