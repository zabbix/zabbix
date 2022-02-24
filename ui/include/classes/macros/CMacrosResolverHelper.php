<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * @param string $interfaces[n]['port']
	 * @param array  $interfaces[n]['details']                    (optional)
	 * @param string $interfaces[n]['details']['securityname']    (optional)
	 * @param string $interfaces[n]['details']['authpassphrase']  (optional)
	 * @param string $interfaces[n]['details']['privpassphrase']  (optional)
	 * @param string $interfaces[n]['details']['contextname']     (optional)
	 * @param string $interfaces[n]['details']['community']       (optional)
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

		// Interface details.
		$data = [
			'securityname' => [],
			'authpassphrase' => [],
			'privpassphrase' => [],
			'contextname' => [],
			'community' => []
		];

		foreach ($interfaces as $index => $interface) {
			$hostid = $interface['hostid'];

			if (array_key_exists('details', $interface)) {
				if (array_key_exists('securityname', $interface['details'])) {
					$data['securityname'][$hostid][$index] = $interface['details']['securityname'];
				}

				if (array_key_exists('authpassphrase', $interface['details'])) {
					$data['authpassphrase'][$hostid][$index] = $interface['details']['authpassphrase'];
				}

				if (array_key_exists('privpassphrase', $interface['details'])) {
					$data['privpassphrase'][$hostid][$index] = $interface['details']['privpassphrase'];
				}

				if (array_key_exists('contextname', $interface['details'])) {
					$data['contextname'][$hostid][$index] = $interface['details']['contextname'];
				}

				if (array_key_exists('community', $interface['details'])) {
					$data['community'][$hostid][$index] = $interface['details']['community'];
				}
			}
		}

		$resolved_securityname = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceDetailsSecurityname',
			'data' => $data['securityname']
		]);

		$resolved_authpassphrase = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceDetailsAuthPassphrase',
			'data' => $data['authpassphrase']
		]);

		$resolved_privpassphrase = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceDetailsPrivPassphrase',
			'data' => $data['privpassphrase']
		]);

		$resolved_contextname = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceDetailsContextName',
			'data' => $data['contextname']
		]);

		$resolved_community = self::$macrosResolver->resolve([
			'config' => 'hostInterfaceDetailsCommunity',
			'data' => $data['community']
		]);

		foreach ($interfaces as $index => $interface) {
			$hostid = $interface['hostid'];

			if (array_key_exists('details', $interface)) {
				if (array_key_exists('securityname', $interface['details'])) {
					$interfaces[$index]['details']['securityname'] = $resolved_securityname[$hostid][$index];
				}

				if (array_key_exists('authpassphrase', $interface['details'])) {
					$interfaces[$index]['details']['authpassphrase'] = $resolved_authpassphrase[$hostid][$index];
				}

				if (array_key_exists('privpassphrase', $interface['details'])) {
					$interfaces[$index]['details']['privpassphrase'] = $resolved_privpassphrase[$hostid][$index];
				}

				if (array_key_exists('contextname', $interface['details'])) {
					$interfaces[$index]['details']['contextname'] = $resolved_contextname[$hostid][$index];
				}

				if (array_key_exists('community', $interface['details'])) {
					$interfaces[$index]['details']['community'] = $resolved_community[$hostid][$index];
				}
			}
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
			'references_only' => $references_only
		]);
	}

	/**
	 * Resolve macros in trigger operational data.
	 *
	 * @static
	 *
	 * @param array  $trigger
	 * @param string $trigger['expression']
	 * @param string $trigger['opdata']
	 * @param int    $trigger['clock']       (optional)
	 * @param int    $trigger['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']      (optional) Resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields.
	 *                                       Default: false.
	 * @param bool   $options['html']        (optional) Default: false.
	 *
	 * @return string
	 */
	public static function resolveTriggerOpdata(array $trigger, array $options = []) {
		$triggers = self::resolveTriggerDescriptions([$trigger['triggerid'] => $trigger],
			$options + ['sources' => ['opdata']]
		);

		return $triggers[$trigger['triggerid']]['opdata'];
	}

	/**
	 * Resolve macros in trigger description.
	 *
	 * @static
	 *
	 * @param array  $trigger
	 * @param string $trigger['expression']
	 * @param string $trigger['comments']
	 * @param int    $trigger['clock']       (optional)
	 * @param int    $trigger['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']      (optional) Resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields.
	 *                                       Default: false.
	 * @param bool   $options['html']        (optional) Default: false.
	 *
	 * @return string
	 */
	public static function resolveTriggerDescription(array $trigger, array $options = []) {
		$triggers = self::resolveTriggerDescriptions([$trigger['triggerid'] => $trigger],
			$options + ['sources' => ['comments']]
		);

		return $triggers[$trigger['triggerid']]['comments'];
	}

	/**
	 * Resolve macros in trigger descriptions and operational data.
	 *
	 * @static
	 *
	 * @param array  $triggers
	 * @param string $triggers[$triggerid]['expression']
	 * @param string $triggers[$triggerid][<sources>]     See $options['sources'].
	 * @param int    $triggers[$triggerid]['clock']       (optional)
	 * @param int    $triggers[$triggerid]['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']                   (optional) Resolve {ITEM.VALUE} macro using 'clock' and 'ns'
	 *                                                    fields. Default: false.
	 * @param bool   $options['html']                     (optional) Default: false.
	 * @param array  $options['sources']                  An array of trigger field names: 'comments', 'opdata'.
	 *
	 * @return array
	 */
	public static function resolveTriggerDescriptions(array $triggers, array $options = []) {
		self::init();

		$options += [
			'events' => false,
			'html' => false
		];

		return self::$macrosResolver->resolveTriggerDescriptions($triggers, $options);
	}

	/**
	 * Resolve macros in trigger url.
	 *
	 * @static
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']
	 * @param string $trigger['expression']
	 * @param string $trigger['url']
	 * @param string $trigger['eventid']
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function resolveTriggerUrl(array $trigger, &$url) {
		self::init();

		return self::$macrosResolver->resolveTriggerUrl($trigger, $url);
	}

	/**
	 * Resolve macros in trigger expression.
	 *
	 * @static
	 *
	 * @param string $expression
	 * @param array  $options     See CMacrosResolver::resolveTriggerExpressions() for more details.
	 *                            'sources' is not supported here.
	 *
	 * @return string
	 */
	public static function resolveTriggerExpression($expression, array $options = []) {
		self::init();

		return self::$macrosResolver->resolveTriggerExpressions(
			[['expression' => $expression]], $options
		)[0]['expression'];
	}

	/**
	 * Resolve macros in trigger expressions.
	 *
	 * @static
	 *
	 * @param array $triggers
	 * @param array $options   See CMacrosResolver::resolveTriggerExpressions() for more details.
	 *
	 * @return array
	 */
	public static function resolveTriggerExpressions(array $triggers, array $options = []) {
		self::init();

		return self::$macrosResolver->resolveTriggerExpressions($triggers, $options);
	}

	/**
	 * Resolve expression macros. For example, {?func(/host/key, param)} or {?func(/{HOST.HOST1}/key, param)}.
	 *
	 * @static
	 *
	 * @param string $name
	 * @param array  $items
	 * @param string $items[]['hostid']
	 * @param string $items[]['host']
	 *
	 * @return string  A graph name with resolved macros.
	 */
	public static function resolveGraphName($name, array $items) {
		self::init();

		return self::$macrosResolver->resolveGraphNames([['name' => $name, 'items' => $items]])[0]['name'];
	}

	/**
	 * Resolve expression macros. For example, {?func(/host/key, param)} or {?func(/{HOST.HOST1}/key, param)}.
	 *
	 * @static
	 *
	 * @param array  $graphs
	 * @param string $graphs[]['graphid']
	 * @param string $graphs[]['name']
	 *
	 * @return array	Inputted data with resolved graph name.
	 */
	public static function resolveGraphNameByIds(array $graphs) {
		self::init();

		$_graphs = [];

		foreach ($graphs as $graph) {
			// Skip graphs without expression macros.
			if (strpos($graph['name'], '{?') !== false) {
				$_graphs[$graph['graphid']] = [
					'graphid' => $graph['graphid'],
					'name' => $graph['name'],
					'items' => []
				];
			}
		}

		if (!$_graphs) {
			return $graphs;
		}

		$items = DBfetchArray(DBselect(
			'SELECT gi.graphid,h.host'.
			' FROM graphs_items gi,items i,hosts h'.
			' WHERE gi.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionInt('gi.graphid', array_keys($_graphs)).
			' ORDER BY gi.sortorder'
		));

		foreach ($items as $item) {
			$_graphs[$item['graphid']]['items'][] = ['host' => $item['host']];
		}

		$_graphs = self::$macrosResolver->resolveGraphNames($_graphs);

		foreach ($graphs as &$graph) {
			if (array_key_exists($graph['graphid'], $_graphs)) {
				$graph['name'] = $_graphs[$graph['graphid']]['name'];
			}
		}
		unset($graph);

		return $graphs;
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
	 * Resolve item description macros to "description_expanded" field.
	 *
	 * @static
	 *
	 * @param array	 $items
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['description']
	 *
	 * @return array
	 */
	public static function resolveItemDescriptions(array $items): array {
		self::init();

		return self::$macrosResolver->resolveItemDescriptions($items);
	}

	/**
	 * Resolve single item widget description macros.
	 *
	 * @param array  $items
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['name']    Field to resolve. Required.
	 *
	 * @return array                      Returns array of items with macros resolved.
	 */
	public static function resolveWidgetItemNames(array $items) {
		self::init();

		return self::$macrosResolver->resolveWidgetItemNames($items);
	}

	/**
	 * Resolve text-type column macros for top-hosts widget.
	 *
	 * @param array $columns
	 * @param array $items
	 *
	 * @return array
	 */
	public static function resolveWidgetTopHostsTextColumns(array $columns, array $items): array {
		self::init();

		return self::$macrosResolver->resolveWidgetTopHostsTextColumns($columns, $items);
	}

	/**
	 * Expand functional macros in given map link labels.
	 *
	 * @param array  $links
	 * @param string $links[]['label']
	 * @param array  $fields            A mapping between source and destination fields.
	 *
	 * @return array
	 */
	public static function resolveMapLinkLabelMacros(array $links, array $fields = ['label' => 'label']): array {
		self::init();

		return self::$macrosResolver->resolveMapLinkLabelMacros($links, $fields);
	}

	/**
	 * Expand functional macros in given map shape labels.
	 *
	 * @param string $map_name
	 * @param array  $shapes
	 * @param string $shapes[]['text']
	 * @param array  $fields            A mapping between source and destination fields.
	 *
	 * @return array
	 */
	public static function resolveMapShapeLabelMacros(string $map_name, array $shapes,
			array $fields = ['text' => 'text']): array {
		self::init();

		return self::$macrosResolver->resolveMapShapeLabelMacros($map_name, $shapes, $fields);
	}

	/**
	 * Resolve macros in dashboard widget URL.
	 *
	 * @static
	 *
	 * @param array $widget
	 *
	 * @return string
	 */
	public static function resolveWidgetURL(array $widget) {
		self::init();

		$macros = self::$macrosResolver->resolve([
			'config' => $widget['config'],
			'data' => [
				$widget['hostid'] => [
					'url' => $widget['url']
				]
			]
		]);
		$macros = reset($macros);

		return $macros['url'];
	}

	/**
	 * Resolve time unit macros.
	 *
	 * @static
	 *
	 * @param array $data
	 * @param array $field_names
	 *
	 * @return array
	 */
	public static function resolveTimeUnitMacros(array $data, array $field_names) {
		self::init();

		return self::$macrosResolver->resolveTimeUnitMacros($data, ['sources' => $field_names]);
	}

	/**
	 * Resolve supported macros used in map element label as well as in URL names and values.
	 *
	 * @static
	 *
	 * @param array        $selements[]
	 * @param int          $selements[]['elementtype']          Map element type.
	 * @param int          $selements[]['elementsubtype']       Map element subtype.
	 * @param string       $selements[]['label']                Map element label.
	 * @param array        $selements[]['urls']                 Map element urls.
	 * @param string       $selements[]['urls'][]['name']       Map element url name.
	 * @param string       $selements[]['urls'][]['url']        Map element url value.
	 * @param int | array  $selements[]['elementid']            Element id linked to map element.
	 * @param array        $options
	 * @param bool         $options['resolve_element_urls']     Resolve macros in map element url name and value.
	 * @param bool         $options['resolve_element_label']    Resolve macros in map element label.
	 *
	 * @return array
	 */
	public static function resolveMacrosInMapElements(array $selements, array $options) {
		self::init();

		return self::$macrosResolver->resolveMacrosInMapElements($selements, $options);
	}

	/**
	 * Set every trigger items array elements order by item usage order in trigger expression and recovery expression.
	 *
	 * @static
	 *
	 * @param array  $triggers                            Array of triggers.
	 * @param string $triggers[]['expression']            Trigger expression used to define order of trigger items.
	 * @param string $triggers[]['recovery_expression']   Trigger expression used to define order of trigger items.
	 * @param array  $triggers[]['items]                  Items to be sorted.
	 * @param string $triggers[]['items][]['itemid']      Item id.
	 *
	 * @return array
	 */
	public static function sortItemsByExpressionOrder(array $triggers) {
		self::init();

		return self::$macrosResolver->sortItemsByExpressionOrder($triggers);
	}

	/**
	 * Extract macros from properties used for preprocessing step test and find effective values.
	 *
	 * @param array  $data
	 * @param string $data['steps']                              Preprocessing steps details.
	 * @param string $data['steps'][]['params']                  Preprocessing step parameters.
	 * @param string $data['steps'][]['error_handler_params]     Preprocessing steps error handle parameters.
	 * @param string $data['delay']                              Update interval value.
	 * @param array  $data['supported_macros']                   Supported macros.
	 * @param bool   $data['support_lldmacros']                  Either LLD macros need to be extracted.
	 * @param array  $data['texts_support_macros']               List of texts potentially could contain macros.
	 * @param array  $data['texts_support_user_macros']          List of texts potentially could contain user macros.
	 * @param array  $data['texts_support_lld_macros']           List of texts potentially could contain LLD macros.
	 * @param int    $data['hostid']                             Hostid for which tested item belongs to.
	 * @param array  $data['macros_values']                      Values for supported macros.
	 *
	 * @return array
	 */
	public static function extractItemTestMacros(array $data) {
		self::init();

		return self::$macrosResolver->extractItemTestMacros($data);
	}

	/**
	 * Return associative array of urls with resolved {EVENT.TAGS.*} macro in form
	 * [<eventid> => ['urls' => [['url' => .. 'name' => ..], ..]]].
	 *
	 * @param array  $events                                Array of event tags.
	 * @param string $events[<eventid>]['tags'][]['tag']    Event tag tag field value.
	 * @param string $events[<eventid>]['tags'][]['value']  Event tag value field value.
	 * @param array  $urls                                  Array of mediatype urls.
	 * @param string $urls[]['event_menu_url']              Media type url field value.
	 * @param string $urls[]['event_menu_name']             Media type url_name field value.
	 *
	 * @return array
	 */
	public static function resolveMediaTypeUrls(array $events, array $urls) {
		self::init();

		return self::$macrosResolver->resolveMediaTypeUrls($events, $urls);
	}
}
