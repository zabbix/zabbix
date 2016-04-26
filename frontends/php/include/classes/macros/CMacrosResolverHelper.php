<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


/**
 * Helper class that simplifies working with CMacrosResolver class.
 */
class CMacrosResolverHelper {

	/**
	 * @var CMacrosResolver
	 */
	private static $macrosResolver;

	/**
	 * Create CMacrosResolver object and store in static variable.
	 *
	 * @static
	 */
	private static function init() {
		if (self::$macrosResolver === null) {
			self::$macrosResolver = new CMacrosResolver();
		}
	}

	/**
	 * Resolve macros.
	 *
	 * @static
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public static function resolve(array $options) {
		self::init();

		return self::$macrosResolver->resolve($options);
	}

	/**
	 * Resolve macros in http test name.
	 *
	 * @static
	 *
	 * @param int    $hostId
	 * @param string $name
	 *
	 * @return string
	 */
	public static function resolveHttpTestName($hostId, $name) {
		self::init();

		$macros = self::$macrosResolver->resolve([
			'config' => 'httpTestName',
			'data' => [$hostId => [$name]]
		]);

		return $macros[$hostId][0];
	}

	/**
	 * Resolve macros in host interfaces.
	 *
	 * @static
	 *
	 * @param array  $interfaces
	 * @param string $interfaces[n]['hostid']
	 * @param string $interfaces[n]['type']
	 * @param string $interfaces[n]['main']
	 * @param string $interfaces[n]['ip']
	 * @param string $interfaces[n]['dns']
	 *
	 * @return array
	 */
	public static function resolveHostInterfaces(array $interfaces) {
		self::init();

		// agent primary ip and dns
		$data = [];
		foreach ($interfaces as $interface) {
			if ($interface['type'] == INTERFACE_TYPE_AGENT && $interface['main'] == INTERFACE_PRIMARY) {
				$data[$interface['hostid']][] = $interface['ip'];
				$data[$interface['hostid']][] = $interface['dns'];
			}
		}

		$resolvedData = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceIpDnsAgentPrimary',
			'data' => $data
		]);

		foreach ($resolvedData as $hostId => $texts) {
			$n = 0;

			foreach ($interfaces as &$interface) {
				if ($interface['type'] == INTERFACE_TYPE_AGENT && $interface['main'] == INTERFACE_PRIMARY
						&& $interface['hostid'] == $hostId) {
					$interface['ip'] = $texts[$n];
					$n++;
					$interface['dns'] = $texts[$n];
					$n++;
				}
			}
			unset($interface);
		}

		// others ip and dns
		$data = [];
		foreach ($interfaces as $interface) {
			if (!($interface['type'] == INTERFACE_TYPE_AGENT && $interface['main'] == INTERFACE_PRIMARY)) {
				$data[$interface['hostid']][] = $interface['ip'];
				$data[$interface['hostid']][] = $interface['dns'];
			}
		}

		$resolvedData = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceIpDns',
			'data' => $data
		]);

		foreach ($resolvedData as $hostId => $texts) {
			$n = 0;

			foreach ($interfaces as &$interface) {
				if (!($interface['type'] == INTERFACE_TYPE_AGENT && $interface['main'] == INTERFACE_PRIMARY)
						&& $interface['hostid'] == $hostId) {
					$interface['ip'] = $texts[$n];
					$n++;
					$interface['dns'] = $texts[$n];
					$n++;
				}
			}
			unset($interface);
		}

		// port
		$data = [];
		foreach ($interfaces as $interface) {
			$data[$interface['hostid']][] = $interface['port'];
		}

		$resolvedData = self::$macrosResolver->resolve([
			'config' => 'hostInterfacePort',
			'data' => $data
		]);

		foreach ($resolvedData as $hostId => $texts) {
			$n = 0;

			foreach ($interfaces as &$interface) {
				if ($interface['hostid'] == $hostId) {
					$interface['port'] = $texts[$n];
					$n++;
				}
			}
			unset($interface);
		}

		return $interfaces;
	}

	/**
	 * Resolve macros in trigger name.
	 *
	 * @static
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function resolveTriggerName(array $trigger) {
		$triggers = self::resolveTriggerNames([$trigger['triggerid'] => $trigger]);

		return $triggers[$trigger['triggerid']]['description'];
	}

	/**
	 * Resolve macros in trigger names.
	 *
	 * @static
	 *
	 * @param array $triggers
	 * @param bool  $references_only
	 *
	 * @return array
	 */
	public static function resolveTriggerNames(array $triggers, $references_only = false) {
		self::init();

		return self::$macrosResolver->resolveTriggerNames($triggers, [
			'references_only' => $references_only,
			'events' => false
		]);
	}

	/**
	 * Resolve macros in trigger description.
	 *
	 * @static
	 *
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function resolveTriggerDescription(array $trigger) {
		$triggers = self::resolveTriggerDescriptions([$trigger['triggerid'] => $trigger]);

		return $triggers[$trigger['triggerid']]['comments'];
	}

	/**
	 * Resolve macros in trigger descriptions.
	 *
	 * @static
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	public static function resolveTriggerDescriptions(array $triggers) {
		self::init();

		return self::$macrosResolver->resolveTriggerDescriptions($triggers);
	}

	/**
	 * Resolve macros in trigger url.
	 *
	 * @static
	 *
	 * @param array $triggers
	 * @param string $triggers[triggerid]['expression']
	 * @param string $triggers[triggerid]['url']
	 *
	 * @return array
	 */
	public static function resolveTriggerUrls(array $triggers) {
		self::init();

		return self::$macrosResolver->resolveTriggerUrls($triggers);
	}

	/**
	 * Get trigger by id and resolve macros in trigger name.
	 *
	 * @static
	 *
	 * @param int $triggerId
	 *
	 * @return string
	 */
	public static function resolveTriggerNameById($triggerId) {
		$macros = self::resolveTriggerNameByIds([$triggerId]);
		$macros = reset($macros);

		return $macros['description'];
	}

	/**
	 * Get triggers by ids and resolve macros in trigger names.
	 *
	 * @static
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	public static function resolveTriggerNameByIds(array $triggerIds) {
		self::init();

		$triggers = DBfetchArray(DBselect(
			'SELECT DISTINCT t.description,t.expression,t.triggerid'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.triggerid', $triggerIds)
		));

		return self::$macrosResolver->resolveTriggerNames(zbx_toHash($triggers, 'triggerid'), [
			'references_only' => false,
			'events' => false
		]);
	}

	/**
	 * Resolve macros in trigger expression.
	 *
	 * @static
	 *
	 * @param string $expression
	 * @param array  $options		see resolveTriggerExpressions() for more details ('sources' is not supported here)
	 *
	 * @return string
	 */
	public static function resolveTriggerExpression($expression, array $options = []) {
		self::init();

		return self::$macrosResolver->resolveTriggerExpressions([['expression' => $expression]], [
			'html' => array_key_exists('html', $options) && $options['html'],
			'resolve_usermacros' => array_key_exists('resolve_usermacros', $options) && $options['resolve_usermacros'],
			'resolve_macros' => array_key_exists('resolve_macros', $options) && $options['resolve_macros'],
			'sources' => ['expression']
		])[0]['expression'];
	}

	/**
	 * Resolve macros in trigger expressions.
	 *
	 * @static
	 *
	 * @param array  $triggers
	 * @param string $triggers[]['expression']
	 * @param array  $options
	 * @param bool   $options['html']				(optional) returns formatted trigger expression
	 * @param bool   $options['resolve_usermacros']	(optional) resolve user macros
	 * @param bool   $options['resolve_macros']		(optional) resolve macros in item keys and functions
	 * @param array  $options['sources']			(optional) an array of the field names; default ['expression']
	 *
	 * @return array
	 */
	public static function resolveTriggerExpressions(array $triggers, array $options = []) {
		self::init();

		return self::$macrosResolver->resolveTriggerExpressions($triggers, [
			'html' => array_key_exists('html', $options) && $options['html'],
			'resolve_usermacros' => array_key_exists('resolve_usermacros', $options) && $options['resolve_usermacros'],
			'resolve_macros' => array_key_exists('resolve_macros', $options) && $options['resolve_macros'],
			'sources' => array_key_exists('sources', $options) ? $options['sources'] : ['expression']
		]);
	}

	/**
	 * Resolve user macros in trigger expression.
	 *
	 * @static
	 *
	 * @param array $trigger
	 * @param array $trigger['triggerid']
	 * @param array $trigger['expression']
	 *
	 * @return string
	 */
	public static function resolveTriggerExpressionUserMacro(array $trigger) {
		self::init();

		$triggers = self::$macrosResolver->resolveTriggerExpressionUserMacro(zbx_toHash([$trigger], 'triggerid'));
		$trigger = reset($triggers);

		return $trigger['expression'];
	}

	/**
	 * Resolve macros in event description.
	 *
	 * @static
	 *
	 * @param array $event
	 *
	 * @return string
	 */
	public static function resolveEventDescription(array $event) {
		self::init();

		$events = self::$macrosResolver->resolveTriggerNames([$event['triggerid'] => $event], [
			'references_only' => false,
			'events' => true
		]);

		return $events[$event['triggerid']]['description'];
	}

	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @static
	 *
	 * @param type   $name					string in which macros should be resolved
	 * @param array  $items					list of graph items
	 * @param int    $items[n]['hostid']	graph n-th item corresponding host Id
	 * @param string $items[n]['host']		graph n-th item corresponding host name
	 *
	 * @return string	string with macros replaced with corresponding values
	 */
	public static function resolveGraphName($name, array $items) {
		self::init();

		$graph = self::$macrosResolver->resolve([
			'config' => 'graphName',
			'data' => [['name' => $name, 'items' => $items]]
		]);
		$graph = reset($graph);

		return $graph['name'];
	}

	/**
	 * Resolve positional macros and functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 * ! if same graph will be passed more than once only name for first entry will be resolved.
	 *
	 * @static
	 *
	 * @param array  $data					list or hashmap of graphs
	 * @param int    $data[n]['graphid']	id of graph
	 * @param string $data[n]['name']		name of graph
	 *
	 * @return array	inputted data with resolved names
	 */
	public static function resolveGraphNameByIds(array $data) {
		self::init();

		$graphIds = [];
		$graphMap = [];
		foreach ($data as $graph) {
			// skip graphs without macros
			if (strpos($graph['name'], '{') !== false) {
				$graphMap[$graph['graphid']] = [
					'graphid' => $graph['graphid'],
					'name' => $graph['name'],
					'items' => []
				];
				$graphIds[$graph['graphid']] = $graph['graphid'];
			}
		}

		$items = DBfetchArray(DBselect(
			'SELECT i.hostid,gi.graphid,h.host'.
			' FROM graphs_items gi,items i,hosts h'.
			' WHERE gi.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionInt('gi.graphid', $graphIds).
			' ORDER BY gi.sortorder'
		));

		foreach ($items as $item) {
			$graphMap[$item['graphid']]['items'][] = ['hostid' => $item['hostid'], 'host' => $item['host']];
		}

		$graphMap = self::$macrosResolver->resolve([
			'config' => 'graphName',
			'data' => $graphMap
		]);

		$resolvedGraph = reset($graphMap);
		foreach ($data as &$graph) {
			if ($graph['graphid'] === $resolvedGraph['graphid']) {
				$graph['name'] = $resolvedGraph['name'];
				$resolvedGraph = next($graphMap);
			}
		}
		unset($graph);

		return $data;
	}

	/**
	 * Resolve item name macros to "name_expanded" field.
	 *
	 * @static
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['name']
	 * @param string $items[n]['key_']				item key (optional)
	 *												but is (mandatory) if macros exist and "key_expanded" is not present
	 * @param string $items[n]['key_expanded']		expanded item key (optional)
	 *
	 * @return array
	 */
	public static function resolveItemNames(array $items) {
		self::init();

		return self::$macrosResolver->resolveItemNames($items);
	}

	/**
	 * Resolve item key macros to "key_expanded" field.
	 *
	 * @static
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['key_']
	 *
	 * @return array
	 */
	public static function resolveItemKeys(array $items) {
		self::init();

		return self::$macrosResolver->resolveItemKeys($items);
	}

	/**
	 * Resolve function parameter macros to "parameter_expanded" field.
	 *
	 * @static
	 *
	 * @param array  $data
	 * @param string $data[n]['hostid']
	 * @param string $data[n]['parameter']
	 *
	 * @return array
	 */
	public static function resolveFunctionParameters(array $data) {
		self::init();

		return self::$macrosResolver->resolveFunctionParameters($data);
	}

	/**
	 * Expand functional macros in given map label.
	 *
	 * @param string $label			label to expand
	 * @param array  $replaceHosts	list of hosts in order which they appear in trigger expression if trigger label is
	 * given, or single host when host label is given
	 *
	 * @return string
	 */
	public static function resolveMapLabelMacros($label, $replaceHosts = null) {
		self::init();

		return self::$macrosResolver->resolveMapLabelMacros($label, $replaceHosts);
	}

	/**
	 * Resolve all kinds of macros in map labels.
	 *
	 * @static
	 *
	 * @param array  $selement
	 * @param string $selement['label']						label to expand
	 * @param int    $selement['elementtype']				element type
	 * @param int    $selement['elementid']					element id
	 * @param string $selement['elementExpressionTrigger']	if type is trigger, then trigger expression
	 *
	 * @return string
	 */
	public static function resolveMapLabelMacrosAll(array $selement) {
		self::init();

		return self::$macrosResolver->resolveMapLabelMacrosAll($selement);
	}

	/**
	 * Resolve macros in screen element URL.
	 *
	 * @static
	 *
	 * @param array $screenElement
	 *
	 * @return string
	 */
	public static function resolveScreenElementURL(array $screenElement) {
		self::init();

		$macros = self::$macrosResolver->resolve([
			'config' => $screenElement['config'],
			'data' => [
				$screenElement['hostid'] => [
					'url' => $screenElement['url']
				]
			]
		]);
		$macros = reset($macros);

		return $macros['url'];
	}
}
