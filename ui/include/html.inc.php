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


function italic($str) {
	if (is_array($str)) {
		foreach ($str as $key => $val) {
			if (is_string($val)) {
				$em = new CTag('em', true);
				$em->addItem($val);
				$str[$key] = $em;
			}
		}
	}
	elseif (is_string($str)) {
		$em = new CTag('em', true, '');
		$em->addItem($str);
		$str = $em;
	}
	return $str;
}

function bold($str) {
	if (is_array($str)) {
		foreach ($str as $key => $val) {
			if (is_string($val)) {
				$str[$key] = new CTag('b', true, $val);
			}
		}

		return $str;
	}

	return new CTag('b', true, $str);
}

function make_decoration($haystack, $needle, $class = null) {
	$result = $haystack;

	$tmpHaystack = mb_strtolower($haystack);
	$tmpNeedle = mb_strtolower($needle);
	$pos = mb_strpos($tmpHaystack, $tmpNeedle);

	if ($pos !== false) {
		$start = mb_substr($haystack, 0, $pos);
		$end = mb_substr($haystack, $pos + mb_strlen($needle));
		$found = mb_substr($haystack, $pos, mb_strlen($needle));

		if (is_null($class)) {
			$result = [$start, bold($found), $end];
		}
		else {
			$result = [$start, (new CSpan($found))->addClass($class), $end];
		}
	}

	return $result;
}

function prepareUrlParam($value, $name = null): string {
	if (is_array($value)) {
		$result = '';

		foreach ($value as $key => $param) {
			$result .= prepareUrlParam($param, isset($name) ? $name.'['.$key.']' : $key);
		}
	}
	else {
		$result = '&'.$name.'='.urlencode($value);
	}

	return $result;
}

/**
 * Get ready for url params.
 *
 * @param mixed       $param           Param name or array with data depends from $getFromRequest.
 * @param bool        $getFromRequest  Detect data source - input array or $_REQUEST variable.
 * @param string|null $name            If $_REQUEST variable is used this variable not used.
 */
function url_param($param, bool $getFromRequest = true, string $name = null): string {
	if (is_array($param)) {
		if ($getFromRequest) {
			fatal_error(_('URL parameter cannot be array.'));
		}
	}
	elseif ($name === null) {
		if (!$getFromRequest) {
			fatal_error(_('URL parameter name is empty.'));
		}

		$name = $param;
	}

	if ($getFromRequest) {
		$value =& $_REQUEST[$param];
	}
	else {
		$value =& $param;
	}

	return isset($value) ? prepareUrlParam($value, $name) : '';
}

function url_params(array $params): string {
	$result = '';

	foreach ($params as $param) {
		$result .= url_param($param);
	}

	return $result;
}

function BR(): CTag {
	return new CTag('br');
}

function BULLET() {
	return new CHtmlEntity('&bullet;');
}

function COPYR() {
	return new CHtmlEntity('&copy;');
}

function HELLIP() {
	return new CHtmlEntity('&hellip;');
}

function LARR() {
	return new CHtmlEntity('&lArr;');
}

function NBSP() {
	return new CHtmlEntity('&nbsp;');
}

function NDASH() {
	return new CHtmlEntity('&ndash;');
}

function RARR() {
	return new CHtmlEntity('&rArr;');
}

function get_icon($type, $params = []): ?CSimpleButton {
	switch ($type) {
		case 'favorite':
			if (CFavorite::exists($params['fav'], $params['elid'], $params['elname'])) {
				$icon = (new CSimpleButton())
					->addClass(ZBX_ICON_STAR_FILLED)
					->setTitle(_('Remove from favorites'))
					->onClick('rm4favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			else {
				$icon = (new CSimpleButton())
					->addClass(ZBX_ICON_STAR)
					->setTitle(_('Add to favorites'))
					->onClick('add2favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			$icon->setId('addrm_fav');

			return $icon;

		case 'kioskmode':
			if ($params['mode'] == ZBX_LAYOUT_KIOSKMODE) {
				$icon = (new CSimpleButton())
					->addClass(ZBX_LAYOUT_MODE)
					->addClass(ZBX_ICON_MINIMIZE)
					->addClass(ZBX_STYLE_BTN_DASHBOARD_NORMAL)
					->setTitle(_('Normal view'))
					->setAttribute('data-layout-mode', ZBX_LAYOUT_NORMAL);
			}
			else {
				$icon = (new CSimpleButton())
					->addClass(ZBX_LAYOUT_MODE)
					->addClass(ZBX_ICON_FULLSCREEN)
					->addClass(ZBX_STYLE_BTN_KIOSK)
					->setTitle(_('Kiosk mode'))
					->setAttribute('data-layout-mode', ZBX_LAYOUT_KIOSKMODE);
			}

			return $icon;
	}

	return null;
}

/**
 * Get host/template configuration navigation.
 *
 * @param string $current_element
 * @param int    $hostid
 * @param int    $lld_ruleid
 *
 * @throws Exception
 */
function getHostNavigation(string $current_element, $hostid, $lld_ruleid = 0): ?CList {
	$options = [
		'output' => [
			'hostid', 'status', 'name', 'maintenance_status', 'flags', 'active_available'
		],
		'selectHostDiscovery' => ['status', 'ts_delete', 'ts_disable', 'disable_source'],
		'selectDiscoveryRule' => ['lifetime_type', 'enabled_lifetime_type'],
		'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'version', 'details', 'available', 'error'],
		'hostids' => [$hostid],
		'editable' => true
	];
	if ($lld_ruleid == 0) {
		$options['selectItems'] = API_OUTPUT_COUNT;
		$options['selectTriggers'] = API_OUTPUT_COUNT;
		$options['selectGraphs'] = API_OUTPUT_COUNT;
		$options['selectDiscoveries'] = API_OUTPUT_COUNT;
		$options['selectHttpTests'] = API_OUTPUT_COUNT;
	}

	// get hosts
	$db_host = API::Host()->get($options);

	if (!$db_host) {
		$options = [
			'output' => ['templateid', 'name', 'flags'],
			'templateids' => [$hostid],
			'editable' => true
		];
		if ($lld_ruleid == 0) {
			$options['selectItems'] = API_OUTPUT_COUNT;
			$options['selectTriggers'] = API_OUTPUT_COUNT;
			$options['selectGraphs'] = API_OUTPUT_COUNT;
			$options['selectDashboards'] = API_OUTPUT_COUNT;
			$options['selectDiscoveries'] = API_OUTPUT_COUNT;
			$options['selectHttpTests'] = API_OUTPUT_COUNT;
		}

		// get templates
		$db_host = API::Template()->get($options);

		$is_template = true;
	}
	else {
		$is_template = false;
	}

	if (!$db_host) {
		return null;
	}

	$db_host = reset($db_host);

	if (!$is_template) {
		if (getItemTypeCountByHostId(ITEM_TYPE_ZABBIX_ACTIVE, [$hostid])) {
			// Add active checks interface if host have items with type ITEM_TYPE_ZABBIX_ACTIVE (7).
			$db_host['interfaces'][] = [
				'type' => INTERFACE_TYPE_AGENT_ACTIVE,
				'available' => $db_host['active_available'],
				'error' => ''
			];
			unset($db_host['active_available']);
		}

		$db_host['has_passive_checks'] = (bool) getItemTypeCountByHostId(ITEM_TYPE_ZABBIX, [$hostid]);
	}

	// get lld-rules
	if ($lld_ruleid != 0) {
		$db_discovery_rule = API::DiscoveryRule()->get([
			'output' => ['name'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectHostPrototypes' => API_OUTPUT_COUNT,
			'itemids' => [$lld_ruleid],
			'editable' => true
		]);
		$db_discovery_rule = reset($db_discovery_rule);
	}

	$list = new CList();

	if ($is_template) {
		$template_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'template.edit')
			->setArgument('templateid', $db_host['templateid'])
			->getUrl();

		$template = new CSpan((new CLink($db_host['name'], $template_url)));

		if ($current_element === '') {
			$template->addClass(ZBX_STYLE_SELECTED);
		}

		$list->addItem(new CBreadcrumbs([
			new CSpan(new CLink(_('All templates'), (new CUrl('zabbix.php'))->setArgument('action', 'template.list'))),
			$template
		]));

		$db_host['hostid'] = $db_host['templateid'];
	}
	else {
		switch ($db_host['status']) {
			case HOST_STATUS_MONITORED:
				if ($db_host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$status = (new CSpan(_('In maintenance')))->addClass(ZBX_STYLE_ORANGE);
				}
				else {
					$status = (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN);
				}
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
				break;
			default:
				$status = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_GREY);
				break;
		}

		$host_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'host.edit')
			->setArgument('hostid', $db_host['hostid'])
			->getUrl();

		$host = new CSpan((new CLink($db_host['name'], $host_url)));

		if ($current_element === '') {
			$host->addClass(ZBX_STYLE_SELECTED);
		}

		$list
			->addItem(new CBreadcrumbs([new CSpan(new CLink(_('All hosts'),
				(new CUrl('zabbix.php'))->setArgument('action', 'host.list'))), $host
			]))
			->addItem($status)
			->addItem(getHostAvailabilityTable($db_host['interfaces'], $db_host['has_passive_checks']));

		$disable_source = $db_host['status'] == HOST_STATUS_NOT_MONITORED && $db_host['hostDiscovery']
			? $db_host['hostDiscovery']['disable_source']
			: '';

		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED
				&& $db_host['hostDiscovery']['status'] == ZBX_LLD_STATUS_LOST) {
			$info_icons = [getLldLostEntityIndicator(time(), $db_host['hostDiscovery']['ts_delete'],
				$db_host['hostDiscovery']['ts_disable'], $disable_source,
				$db_host['status'] == HOST_STATUS_NOT_MONITORED, _('host')
			)];

			$list->addItem(makeInformationList($info_icons));
		}
	}

	$content_menu = (new CList())
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _('Content menu'));

	$context = $is_template ? 'template' : 'host';

	/*
	 * the count of rows
	 */
	if ($lld_ruleid == 0) {
		// items
		$items = new CSpan([
			new CLink(_('Items'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'item.list')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_host['items'])
		]);
		if ($current_element === 'items') {
			$items->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($items);

		// triggers
		$triggers = new CSpan([
			new CLink(_('Triggers'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.list')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_host['triggers'])
		]);
		if ($current_element === 'triggers') {
			$triggers->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($triggers);

		// graphs
		$graphs = new CSpan([
			new CLink(_('Graphs'), (new CUrl('graphs.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$db_host['hostid']])
				->setArgument('context', $context)
			),
			CViewHelper::showNum($db_host['graphs'])
		]);
		if ($current_element === 'graphs') {
			$graphs->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graphs);

		// Dashboards
		if ($is_template) {
			$dashboards = new CSpan([
				new CLink(_('Dashboards'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'template.dashboard.list')
						->setArgument('templateid', $db_host['hostid'])
				),
				CViewHelper::showNum($db_host['dashboards'])
			]);
			if ($current_element === 'dashboards') {
				$dashboards->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($dashboards);
		}

		// discovery rules
		$lld_rules = new CSpan([
			new CLink(_('Discovery rules'), (new CUrl('host_discovery.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$db_host['hostid']])
				->setArgument('context', $context)
			),
			CViewHelper::showNum($db_host['discoveries'])
		]);
		if ($current_element === 'discoveries') {
			$lld_rules->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($lld_rules);

		// web scenarios
		$http_tests = new CSpan([
			new CLink(_('Web scenarios'),
				(new CUrl('httpconf.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_host['httpTests'])
		]);
		if ($current_element === 'web') {
			$http_tests->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($http_tests);
	}
	else {
		$discovery_rule = (new CSpan())->addItem(
			new CLink(
				$db_discovery_rule['name'],
				(new CUrl('host_discovery.php'))
					->setArgument('form', 'update')
					->setArgument('itemid', $db_discovery_rule['itemid'])
					->setArgument('context', $context)
			)
		);

		if ($current_element === 'discoveries') {
			$discovery_rule->addClass(ZBX_STYLE_SELECTED);
		}

		$list->addItem(new CBreadcrumbs([
			(new CSpan())->addItem(new CLink(_('Discovery list'),
				(new CUrl('host_discovery.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$db_host['hostid']])
					->setArgument('context', $context)
			)),
			$discovery_rule
		]));

		// item prototypes
		$item_prototypes = new CSpan([
			new CLink(_('Item prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'item.prototype.list')
					->setArgument('parent_discoveryid', $db_discovery_rule['itemid'])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_discovery_rule['items'])
		]);
		if ($current_element === 'items') {
			$item_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($item_prototypes);

		// trigger prototypes
		$trigger_prototypes = new CSpan([
			new CLink(_('Trigger prototypes'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.prototype.list')
					->setArgument('parent_discoveryid', $db_discovery_rule['itemid'])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_discovery_rule['triggers'])
		]);
		if ($current_element === 'triggers') {
			$trigger_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($trigger_prototypes);

		// graph prototypes
		$graph_prototypes = new CSpan([
			new CLink(_('Graph prototypes'),
				(new CUrl('graphs.php'))
					->setArgument('parent_discoveryid', $db_discovery_rule['itemid'])
					->setArgument('context', $context)
			),
			CViewHelper::showNum($db_discovery_rule['graphs'])
		]);
		if ($current_element === 'graphs') {
			$graph_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graph_prototypes);

		// host prototypes
		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			$host_prototypes = new CSpan([
				new CLink(_('Host prototypes'),
					(new CUrl('host_prototypes.php'))
						->setArgument('parent_discoveryid', $db_discovery_rule['itemid'])
						->setArgument('context', $context)
				),
				CViewHelper::showNum($db_discovery_rule['hostPrototypes'])
			]);
			if ($current_element === 'hosts') {
				$host_prototypes->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($host_prototypes);
		}
	}

	$list->addItem($content_menu);

	return $list;
}

/**
 * Get map navigation.
 *
 * @param int    $sysmapid      Used as value for sysmapid in map link generation.
 * @param string $name          Used as label for map link generation.
 * @param int    $severity_min  Used as value for severity_min in map link generation.
 */
function getSysmapNavigation($sysmapid, $name, $severity_min): CList {
	$list = (new CList())->addItem(new CBreadcrumbs([
		(new CSpan())->addItem(new CLink(_('All maps'), new CUrl('sysmaps.php'))),
		(new CSpan())
			->addClass(ZBX_STYLE_SELECTED)
			->addItem(new CLink($name,
				(new CUrl('zabbix.php'))
					->setArgument('action', 'map.view')
					->setArgument('sysmapid', $sysmapid)
					->setArgument('severity_min', $severity_min)
			))
	]));

	// get map parent maps
	$parent_sysmaps = get_parent_sysmaps($sysmapid);
	if ($parent_sysmaps) {
		$parent_maps = (new CList())
			->setAttribute('aria-label', _('Upper level maps'))
			->addItem((new CSpan())->addItem(_('Upper level maps').':'));

		foreach ($parent_sysmaps as $parent_sysmap) {
			$parent_maps->addItem((new CSpan())->addItem(new CLink($parent_sysmap['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'map.view')
					->setArgument('sysmapid', $parent_sysmap['sysmapid'])
					->setArgument('severity_min', $severity_min)
			)));
		}

		$list->addItem($parent_maps);
	}

	return $list;
}

/**
 * Renders a form footer with the given buttons.
 *
 * @param CButtonInterface|null $main_button  Main button that will be displayed on the left.
 * @param CButtonInterface[]    $other_buttons
 *
 * @throws InvalidArgumentException	if an element of $other_buttons contain something other than CButtonInterface
 */
function makeFormFooter(CButtonInterface $main_button = null, array $other_buttons = []): CList {
	foreach ($other_buttons as $other_button) {
		$other_button->addClass(ZBX_STYLE_BTN_ALT);
	}

	if ($main_button !== null) {
		array_unshift($other_buttons, $main_button);
	}

	return (new CList())
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addItem([
			(new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
			(new CDiv($other_buttons))
				->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
				->addClass('tfoot-buttons')
		]);
}

/**
 * Create HTML helper element for host interfaces availability.
 *
 * @param array $host_interfaces
 * @param bool $passive_checks
 *
 * @return CHostAvailability
 */
function getHostAvailabilityTable(array $host_interfaces, bool $passive_checks = true): CHostAvailability {
	$interfaces = [];

	foreach ($host_interfaces as $interface) {
		$description = null;

		if ($interface['type'] == INTERFACE_TYPE_SNMP) {
			$description = getSnmpInterfaceDescription($interface);
		}

		$interfaces[] = [
			'type' => $interface['type'],
			'available' => $interface['available'],
			'interface' => getHostInterface($interface),
			'description' => $description,
			'error' => ($interface['available'] == INTERFACE_AVAILABLE_TRUE) ? '' : $interface['error']
		];
	}

	return (new CHostAvailability())
		->setInterfaces($interfaces)
		->enablePassiveChecks($passive_checks);
}

/**
 * Returns the discovered host group lifetime indicator.
 *
 * @param int $current_time  Current Unix timestamp.
 * @param int $ts_delete     Deletion timestamp of the host group.
 *
 * @throws Exception
 */
function getHostGroupLifetimeIndicator(int $current_time, int $ts_delete): CSimpleButton {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _s('The %1$s is not discovered anymore and %2$s.', _('host group'),
			_('will be deleted the next time discovery rule is processed')
		);
	}
	else {
		$warning = _s('The %1$s is not discovered anymore and %2$s.', _('host group'),
			_s('will be deleted in %1$s', zbx_date2age($current_time, $ts_delete))
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the indicator for lost LLD entity.
 *
 * @param int     $current_time    Current Unix timestamp.
 * @param int     $ts_delete       Deletion timestamp of the entity.
 * @param int     $ts_disable      Disabling timestamp of the entity.
 * @param string  $disable_source  Indicator whether entity was disabled by an LLD rule or manually.
 * @param boolean $disabled        Indicator whether entity is disabled.
 * @param string  $entity          Type of entity.
 *
 * @throws Exception
 */
function getLldLostEntityIndicator(int $current_time, int $ts_delete, int $ts_disable, string $disable_source,
		bool $disabled, string $entity): ?CSimpleButton {
	$warning = '';

	if ($disable_source == ZBX_DISABLE_SOURCE_LLD) {
		if ($ts_delete > 0 && $current_time < $ts_delete) {
			$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity, _('has been disabled'),
				_s('will be deleted in %1$s', zbx_date2age($current_time, $ts_delete))
			);
		}
		elseif ($ts_delete == 0) {
			$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity, _('has been disabled'),
				_('will not be deleted')
			);
		}
		elseif ($current_time > $ts_delete) {
			$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity, _('has been disabled'),
				_('will be deleted the next time discovery rule is processed')
			);
		}
	}
	elseif ($disabled && $disable_source == ZBX_DISABLE_DEFAULT && $ts_delete > 0) {
		$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity, _('has been manually disabled'),
			_('will not be deleted')
		);
	}
	elseif (!$disabled && $ts_delete > 0) {
		$delete_msg = _s('will be deleted in %1$s', zbx_date2age($current_time, $ts_delete));

		switch (true) {
			case $current_time > $ts_delete:
				$warning = _s('The %1$s is not discovered anymore and %2$s.', $entity,
					_('will be deleted the next time discovery rule is processed')
				);
				break;

			case $ts_disable == 0:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_s('will not be disabled'), $delete_msg
				);
				break;

			case $ts_disable > 0 && $ts_disable > $current_time:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_s('will be disabled in %1$s', zbx_date2age($current_time, $ts_disable)), $delete_msg
				);
				break;

			case $ts_disable != 0 && $current_time > $ts_disable:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_('will be disabled the next time discovery rule is processed'), $delete_msg
				);
				break;
		}
	}
	elseif (!$disabled && $ts_delete == 0) {
		$delete_msg = _('will not be deleted');

		switch (true) {
			case $ts_disable != 0 && $current_time > $ts_disable:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_('will be disabled the next time discovery rule is processed'), $delete_msg
				);
				break;

			case $ts_disable > 0:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_s('will be disabled in %1$s', zbx_date2age($current_time, $ts_disable)), $delete_msg
				);
				break;

			case $ts_disable == 0:
				$warning = _s('The %1$s is not discovered anymore and %2$s, %3$s.', $entity,
					_('will not be disabled'), $delete_msg
				);
				break;
		}
	}

	return $warning === '' ? null : makeWarningIcon($warning);
}

/**
 * Returns the discovered graph lifetime indicator.
 *
 * @param int $current_time   Current Unix timestamp.
 * @param int $ts_delete      Deletion timestamp of the graph.
 *
 * @throws Exception
 */
function getGraphLifetimeIndicator(int $current_time, int $ts_delete): ?CSimpleButton {
	if ($ts_delete == 0) {
		$warning = _s('The %1$s is not discovered anymore and %2$s.', _('graph'),
			_('will not be deleted')
		);
	}
	elseif ($current_time > $ts_delete && $ts_delete != 0) {
		$warning = _s('The %1$s is not discovered anymore and %2$s.', _('graph'),
			_('will be deleted the next time discovery rule is processed')
		);
	}
	else {
		$warning = _s('The %1$s is not discovered anymore and %2$s.', _('graph'),
			_s('will be deleted in %1$s', zbx_date2age($current_time, $ts_delete))
		);
	}

	return makeWarningIcon($warning);
}

function makeServerStatusOutput(): CTag {
	return (new CTag('output', true))
		->setId('msg-global-footer')
		->addClass(ZBX_STYLE_MSG_GLOBAL_FOOTER)
		->addClass(ZBX_STYLE_MSG_WARNING);
}

/**
* Make logo of the specified type.
*
* @param int $type  LOGO_TYPE_NORMAL | LOGO_TYPE_SIDEBAR | LOGO_TYPE_SIDEBAR_COMPACT.
*/
function makeLogo(int $type): CTag {
	static $zabbix_logo_classes = [
		LOGO_TYPE_NORMAL => ZBX_STYLE_ZABBIX_LOGO,
		LOGO_TYPE_SIDEBAR => ZBX_STYLE_ZABBIX_LOGO_SIDEBAR,
		LOGO_TYPE_SIDEBAR_COMPACT => ZBX_STYLE_ZABBIX_LOGO_SIDEBAR_COMPACT
	];

	$brand_logo = CBrandHelper::getLogo($type);

	if ($brand_logo !== null) {
		return (new CImg($brand_logo))->addClass($zabbix_logo_classes[$type]);
	}

	return (new CDiv())->addClass($zabbix_logo_classes[$type]);
}

/**
 * Renders a page footer.
 */
function makePageFooter(bool $with_version = true): CTag {
	return (new CTag('footer', true, CBrandHelper::getFooterContent($with_version)))
		->setAttribute('role', 'contentinfo');
}

/**
 * Get drop-down submenu item list for the User settings section.
 *
 * @throws Exception
 *
 * @return array  Menu definition for CHtmlPage::setTitleSubmenu.
 */
function getUserSettingsSubmenu(): array {
	if (!CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS)) {
		return [];
	}

	$profile_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'userprofile.edit')
		->getUrl();

	$tokens_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'user.token.list')
		->getUrl();

	return [
		'main_section' => [
			'items' => array_filter([
				$profile_url => _('User profile'),
				$tokens_url  => _('API tokens')
			])
		]
	];
}

/**
 * Get drop-down submenu item list for the Administration->General section.
 *
 * @return array  Menu definition for CHtmlPage::setTitleSubmenu.
 */
function getAdministrationGeneralSubmenu(): array {
	$gui_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'gui.edit')
		->getUrl();

	$autoreg_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'autoreg.edit')
		->getUrl();

	$timeouts_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'timeouts.edit')
		->getUrl();

	$image_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'image.list')
		->getUrl();

	$iconmap_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.list')
		->getUrl();

	$regex_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'regex.list')
		->getUrl();

	$trigdisplay_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'trigdisplay.edit')
		->getUrl();

	$geomap_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'geomaps.edit')
		->getUrl();

	$modules_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'module.list')
		->getUrl();

	$connectors_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'connector.list')
		->getUrl();

	$miscconfig_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'miscconfig.edit')
		->getUrl();

	return [
		'main_section' => [
			'items' => array_filter([
				$gui_url            => _('GUI'),
				$autoreg_url        => _('Autoregistration'),
				$timeouts_url       => _('Timeouts'),
				$image_url          => _('Images'),
				$iconmap_url        => _('Icon mapping'),
				$regex_url          => _('Regular expressions'),
				$trigdisplay_url    => _('Trigger displaying options'),
				$geomap_url			=> _('Geographical maps'),
				$modules_url        => _('Modules'),
				$connectors_url     => _('Connectors'),
				$miscconfig_url     => _('Other')
			])
		]
	];
}

/**
 * Renders an icon list.
 *
 * @param array $info_icons  The list of information icons.
 *
 * @return CDiv|string
 */
function makeInformationList($info_icons) {
	return $info_icons ? (new CDiv($info_icons))->addClass(ZBX_STYLE_REL_CONTAINER) : '';
}

/**
 * Renders an icon for host in maintenance.
 *
 * @param int|string $type         Type of the maintenance.
 * @param string     $name         Name of the maintenance.
 * @param string     $description  Description of the maintenance.
 */
function makeMaintenanceIcon($type, string $name, string $description): CButtonIcon {
	$hint = $name.' ['.($type
		? _('Maintenance without data collection')
		: _('Maintenance with data collection')).']';

	if ($description !== '') {
		$hint .= "\n".$description;
	}

	return (new CButtonIcon(ZBX_ICON_WRENCH_ALT_SMALL))
		->addClass(ZBX_STYLE_COLOR_WARNING)
		->addClass(ZBX_STYLE_NO_INDENT)
		->setHint($hint);
}

/**
 * Renders an icon for suppressed problem.
 *
 * @param array  $icon_data
 *        string $icon_data[]['suppress_until']    Time until the problem is suppressed.
 *        string $icon_data[]['maintenance_name']  Name of the maintenance.
 *        string $icon_data[]['username']          User who created manual suppression.
 * @param bool   $blink                            Add 'blink' CSS class for jqBlink.
 *
 * @throws Exception
 */
function makeSuppressedProblemIcon(array $icon_data, bool $blink = false): CSimpleButton {
	$suppress_until_values = array_column($icon_data, 'suppress_until');

	if (in_array(ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE, $suppress_until_values)) {
		$suppressed_till = _s('Indefinitely');
	}
	else {
		$max_value = max($suppress_until_values);
		$suppressed_till = $max_value < strtotime('tomorrow')
			? zbx_date2str(TIME_FORMAT, $max_value)
			: zbx_date2str(DATE_TIME_FORMAT, $max_value);
	}

	CArrayHelper::sort($icon_data, ['maintenance_name']);

	$maintenance_names = [];
	$username = '';

	foreach ($icon_data as $suppression) {
		if (array_key_exists('maintenance_name', $suppression)) {
			$maintenance_names[] = $suppression['maintenance_name'];
		}
		elseif (array_key_exists('username', $suppression)) {
			$username = $suppression['username'];
		}
	}

	$maintenances = implode(', ', $maintenance_names);

	return (new CButtonIcon(ZBX_ICON_EYE_OFF))
		->addClass(ZBX_STYLE_COLOR_ICON)
		->addClass($blink ? 'js-blink' : null)
		->setHint(
			_s('Suppressed till: %1$s', $suppressed_till).
			($username !== '' ? "\n"._s('Manually by: %1$s', $username) : '').
			($maintenances !== '' ? "\n"._s('Maintenance: %1$s', $maintenances) : '')
		);
}

/**
 * Renders an icon with question mark and text in hint.
 *
 * @param string|array|CTag $help_text
 */
function makeHelpIcon($help_text): CSimpleButton {
	return (new CButtonIcon(ZBX_ICON_HELP_FILLED_SMALL))
		->setSmall()
		->setHint($help_text, ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders an icon for a description.
 */
function makeDescriptionIcon(string $description): CButtonIcon {
	return (new CButtonIcon(ZBX_ICON_ALERT_WITH_CONTENT))
		->setAttribute('data-content', '?')
		->setHint(zbx_str2links($description), ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders an information icon like green [i] with message.
 *
 * @param string|array|CTag $message
 */
function makeInformationIcon($message): CButtonIcon {
	return (new CButtonIcon(ZBX_ICON_I_POSITIVE))
		->setSmall()
		->setHint($message, ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders a warning icon like yellow [i] with error message.
 *
 * @param string|array|CTag $warning
 */
function makeWarningIcon($warning): CButtonIcon {
	return (new CButtonIcon(ZBX_ICON_I_WARNING))
		->setSmall()
		->setHint($warning, ZBX_STYLE_HINTBOX_WRAP);
}

/**
 * Renders an error icon like red [i] with error message.
 *
 * @param string|array|CTag $error
 */
function makeErrorIcon($error): CButtonIcon {
	return (new CButtonIcon(ZBX_ICON_I_NEGATIVE))
		->setSmall()
		->setHint($error, ZBX_STYLE_HINTBOX_WRAP.' '.ZBX_STYLE_RED);
}

/**
 * Returns css for trigger severity backgrounds.
 */
function getTriggerSeverityCss(): string {
	$css = '';

	$severities = [
		ZBX_STYLE_NA_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_0),
		ZBX_STYLE_INFO_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_1),
		ZBX_STYLE_WARNING_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_2),
		ZBX_STYLE_AVERAGE_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_3),
		ZBX_STYLE_HIGH_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_4),
		ZBX_STYLE_DISASTER_BG => CSettingsHelper::getPublic(CSettingsHelper::SEVERITY_COLOR_5)
	];

	$css .= ':root {'."\n";
	foreach ($severities as $class => $color) {
		$css .= '--severity-color-'.$class.': #'.$color.';'."\n";
	}
	$css .= '}'."\n";

	foreach ($severities as $class => $color) {
		$css .= '.'.$class.', .'.$class.' input[type="radio"]:checked + label, .'.$class.':before, .flh-'.$class.
			', .status-'.$class.', .status-'.$class.':before { background-color: #'.$color.' }'."\n";
	}

	return $css;
}

/**
 * Returns css for trigger status colors, if those are customized.
 */
function getTriggerStatusCss(): string {
	$css = '';

	if (CSettingsHelper::getPublic(CSettingsHelper::CUSTOM_COLOR) == EVENT_CUSTOM_COLOR_ENABLED) {
		$event_statuses = [
			ZBX_STYLE_PROBLEM_UNACK_FG => CSettingsHelper::getPublic(CSettingsHelper::PROBLEM_UNACK_COLOR),
			ZBX_STYLE_PROBLEM_ACK_FG => CSettingsHelper::getPublic(CSettingsHelper::PROBLEM_ACK_COLOR),
			ZBX_STYLE_OK_UNACK_FG => CSettingsHelper::getPublic(CSettingsHelper::OK_UNACK_COLOR),
			ZBX_STYLE_OK_ACK_FG => CSettingsHelper::getPublic(CSettingsHelper::OK_ACK_COLOR)
		];

		foreach ($event_statuses as $class => $color) {
			$css .= '.' . $class . ' {color: #' . $color . ';}' . "\n";
		}
	}

	return $css;
}
