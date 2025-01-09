<?php
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
 * Helper class that simplifies working with CMacrosResolver class.
 */
class CMacrosResolverHelper {

	/**
	 * Resolve macros.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public static function resolve(array $options) {
		return CMacrosResolver::resolve($options);
	}

	/**
	 * Resolve macros in http test name.
	 *
	 * @param int    $hostId
	 * @param string $name
	 *
	 * @return string
	 */
	public static function resolveHttpTestName($hostId, $name) {
		$macros = CMacrosResolver::resolve([
			'config' => 'httpTestName',
			'data' => [$hostId => [$name]]
		]);

		return $macros[$hostId][0];
	}

	/**
	 * Resolve macros in host interfaces.
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
		// agent primary ip and dns
		$data = [];
		foreach ($interfaces as $interface) {
			if ($interface['type'] == INTERFACE_TYPE_AGENT && $interface['main'] == INTERFACE_PRIMARY) {
				$data[$interface['hostid']][] = $interface['ip'];
				$data[$interface['hostid']][] = $interface['dns'];
			}
		}

		$resolvedData = CMacrosResolver::resolve([
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

		$resolvedData = CMacrosResolver::resolve([
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

		$resolvedData = CMacrosResolver::resolve([
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

		$resolved_securityname = CMacrosResolver::resolve([
			'config' => 'hostInterfaceDetailsSecurityname',
			'data' => $data['securityname']
		]);

		$resolved_authpassphrase = CMacrosResolver::resolve([
			'config' => 'hostInterfaceDetailsAuthPassphrase',
			'data' => $data['authpassphrase']
		]);

		$resolved_privpassphrase = CMacrosResolver::resolve([
			'config' => 'hostInterfaceDetailsPrivPassphrase',
			'data' => $data['privpassphrase']
		]);

		$resolved_contextname = CMacrosResolver::resolve([
			'config' => 'hostInterfaceDetailsContextName',
			'data' => $data['contextname']
		]);

		$resolved_community = CMacrosResolver::resolve([
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
	 * @param array $trigger
	 *
	 * @return string
	 */
	public static function resolveTriggerName(array $trigger) {
		return self::resolveTriggerNames([$trigger['triggerid'] => $trigger])[$trigger['triggerid']]['description'];
	}

	/**
	 * Resolve macros in trigger names.
	 *
	 * @param array $triggers
	 * @param bool  $references_only
	 *
	 * @return array
	 */
	public static function resolveTriggerNames(array $triggers, $references_only = false) {
		return CMacrosResolver::resolveTriggerNames($triggers, ['references_only' => $references_only]);
	}

	/**
	 * Resolve macros in trigger operational data.
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
		return self::resolveTriggerDescriptions([$trigger['triggerid'] => $trigger],
			$options + ['sources' => ['opdata']]
		)[$trigger['triggerid']]['opdata'];
	}

	/**
	 * Resolve macros in trigger description.
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
		return self::resolveTriggerDescriptions([$trigger['triggerid'] => $trigger],
			$options + ['sources' => ['comments']]
		)[$trigger['triggerid']]['comments'];
	}

	/**
	 * Resolve macros in trigger descriptions and operational data.
	 *
	 * @param array  $triggers
	 * @param string $triggers[<triggerid>]['expression']
	 * @param string $triggers[<triggerid>][<sources>]     See $options['sources'].
	 * @param int    $triggers[<triggerid>]['clock']       (optional)
	 * @param int    $triggers[<triggerid>]['ns']          (optional)
	 * @param array  $options
	 * @param bool   $options['events']                   (optional) Resolve {ITEM.VALUE} macro using 'clock' and 'ns'
	 *                                                    fields. Default: false.
	 * @param bool   $options['html']                     (optional) Default: false.
	 * @param array  $options['sources']                  An array of trigger field names: 'comments', 'opdata'.
	 *
	 * @return array
	 */
	public static function resolveTriggerDescriptions(array $triggers, array $options): array {
		$options += [
			'events' => false,
			'html' => false
		];

		return CMacrosResolver::resolveTriggerDescriptions($triggers, $options);
	}

	/**
	 * Resolve macros in trigger url.
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
		return CMacrosResolver::resolveTriggerUrl($trigger, $url, ['source' => 'url']);
	}

	/**
	 * Resolve macros in trigger url name.
	 *
	 * @param array  $trigger
	 * @param string $trigger['triggerid']
	 * @param string $trigger['expression']
	 * @param string $trigger['url_name']
	 * @param string $trigger['eventid']
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function resolveTriggerUrlName(array $trigger, &$url_name) {
		return CMacrosResolver::resolveTriggerUrl($trigger, $url_name, ['source' => 'url_name']);
	}

	/**
	 * Resolve macros in trigger expression.
	 *
	 * @param string $expression
	 * @param array  $options     See CMacrosResolver::resolveTriggerExpressions() for more details.
	 *                            'sources' is not supported here.
	 *
	 * @return string
	 */
	public static function resolveTriggerExpression($expression, array $options = []) {
		return CMacrosResolver::resolveTriggerExpressions([['expression' => $expression]], $options)[0]['expression'];
	}

	/**
	 * Resolve macros in trigger expressions.
	 *
	 * @param array $triggers
	 * @param array $options   See CMacrosResolver::resolveTriggerExpressions() for more details.
	 *
	 * @return array
	 */
	public static function resolveTriggerExpressions(array $triggers, array $options = []) {
		return CMacrosResolver::resolveTriggerExpressions($triggers, $options);
	}

	/**
	 * Resolve expression macros. For example, {?func(/host/key, param)} or {?func(/{HOST.HOST1}/key, param)}.
	 *
	 * @param string $name
	 * @param array  $items
	 * @param string $items[]['hostid']
	 * @param string $items[]['host']
	 *
	 * @return string  A graph name with resolved macros.
	 */
	public static function resolveGraphName($name, array $items) {
		return CMacrosResolver::resolveGraphNames([['name' => $name, 'items' => $items]])[0]['name'];
	}

	/**
	 * Resolve expression macros. For example, {?func(/host/key, param)} or {?func(/{HOST.HOST1}/key, param)}.
	 *
	 * @param array  $graphs
	 * @param string $graphs[]['graphid']
	 * @param string $graphs[]['name']
	 *
	 * @return array	Inputted data with resolved graph name.
	 */
	public static function resolveGraphNameByIds(array $graphs) {
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

		$_graphs = CMacrosResolver::resolveGraphNames($_graphs);

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
	 * @param array  $items
	 * @param string $items[<itemid>]['hostid']
	 * @param string $items[<itemid>]['key_']
	 *
	 * @return array
	 */
	public static function resolveItemKeys(array $items) {
		return CMacrosResolver::resolveItemKeys($items);
	}

	/**
	 * Resolve item description macros to "description_expanded" field.
	 *
	 * @param array	 $items
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['description']
	 *
	 * @return array
	 */
	public static function resolveItemDescriptions(array $items): array {
		return CMacrosResolver::resolveItemDescriptions($items);
	}

	/**
	 * Resolve macros in fields of item-based widgets.
	 *
	 * @param array  $items
	 *        string $items[<itemid>]['hostid']
	 *        string $items[<itemid>][<source_field>]  Particular source field, as referred by $fields.
	 *
	 * @param array  $fields                           Fields to resolve as [<source_field> => <resolved_field>].
	 *
	 * @return array
	 */
	public static function resolveItemBasedWidgetMacros(array $items, array $fields): array {
		return CMacrosResolver::resolveItemBasedWidgetMacros($items, $fields);
	}

	/**
	 * Resolve text-type column macros for top-hosts widget.
	 *
	 * @param array $columns
	 * @param array $hostids
	 *
	 * @return array
	 */
	public static function resolveWidgetTopHostsTextColumns(array $columns, array $hostids): array {
		return CMacrosResolver::resolveWidgetTopHostsTextColumns($columns, $hostids);
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
		return CMacrosResolver::resolveMapLinkLabelMacros($links, $fields);
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
		return CMacrosResolver::resolveMapShapeLabelMacros($map_name, $shapes, $fields);
	}

	/**
	 * Resolve macros in dashboard widget URL.
	 *
	 * @param array $widget
	 *
	 * @return string
	 */
	public static function resolveWidgetURL(array $widget) {
		$macros = CMacrosResolver::resolve([
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
	 * @param array $data
	 * @param array $field_names
	 *
	 * @return array
	 */
	public static function resolveTimeUnitMacros(array $data, array $field_names) {
		return CMacrosResolver::resolveTimeUnitMacros($data, ['sources' => $field_names]);
	}

	/**
	 * Resolve supported macros used in map element label as well as in URL names and values.
	 *
	 * @param array  $selements[]
	 * @param int    $selements[]['elementtype']        Map element type.
	 * @param int    $selements[]['elementsubtype']     Map element subtype.
	 * @param array  $selements[]['elements']           List of objects with element IDs.
	 * @param string $selements[]['label']              Map element label.
	 * @param array  $selements[]['urls']               Map element urls.
	 * @param string $selements[]['urls'][]['name']     Map element url name.
	 * @param string $selements[]['urls'][]['url']      Map element url value.
	 * @param array  $options
	 * @param bool   $options['resolve_element_urls']   Resolve macros in map element url name and value.
	 * @param bool   $options['resolve_element_label']  Resolve macros in map element label.
	 *
	 * @return array
	 */
	public static function resolveMacrosInMapElements(array $selements, array $options) {
		return CMacrosResolver::resolveMacrosInMapElements($selements, $options);
	}

	/**
	 * Set every trigger items array elements order by item usage order in trigger expression and recovery expression.
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
		return CMacrosResolver::sortItemsByExpressionOrder($triggers);
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
		return CMacrosResolver::extractItemTestMacros($data);
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
		return CMacrosResolver::resolveMediaTypeUrls($events, $urls);
	}

	/**
	 * Resolve macros for manual host action scripts. Resolves host macros, interface macros, inventory, user macros
	 * and user data macros.
	 *
	 * @param array  $data                          Array of unresolved macros.
	 * @param array  $data[<hostid>]                Array of scripts. Contains script ID as keys.
	 * @param array  $data[<hostid>][<scriptid>]    Script fields to resolve macros for.
	 * @param array  $manualinput_values
	 * @param string $manualinput_values[<hostid>]  Value for resolving {MANUALINPUT} macros.
	 *
	 * @return array
	 */
	public static function resolveManualHostActionScripts(array $data, array $manualinput_values = []): array {
		return CMacrosResolver::resolveManualHostActionScripts($data, $manualinput_values);
	}

	/**
	 * Resolve macros for manual event action scripts. Resolves host<1-9> macros, interface<1-9> macros,
	 * inventory<1-9> macros, user macros, event macros and user data macros.
	 *
	 * @param array  $data                                  Array of unresolved macros.
	 * @param array  $data[<eventid>]                       Array of scripts. Contains script ID as keys.
	 * @param array  $data[<eventid>][<scriptid>]           Script fields to resolve macros for.
	 * @param array  $events                                Array of events.
	 * @param array  $events[<eventid>]                     Event fields.
	 * @param array  $events[<eventid>][hosts]              Array of hosts that created the event.
	 * @param array  $events[<eventid>][hosts][][<hostid>]  Host ID.
	 * @param array  $events[<eventid>][objectid]           Trigger ID.
	 * @param array  $manualinput_values
	 * @param string $manualinput_values[<eventid>]         Value for resolving {MANUALINPUT} macros.
	 * @return array
	 */
	public static function resolveManualEventActionScripts(array $data, array $events,
			array $manualinput_values = []): array {
		return CMacrosResolver::resolveManualEventActionScripts($data, $events, $manualinput_values);
	}
}
