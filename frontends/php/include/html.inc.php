<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$start = CHtml::encode(mb_substr($haystack, 0, $pos));
		$end = CHtml::encode(mb_substr($haystack, $pos + mb_strlen($needle)));
		$found = CHtml::encode(mb_substr($haystack, $pos, mb_strlen($needle)));

		if (is_null($class)) {
			$result = [$start, bold($found), $end];
		}
		else {
			$result = [$start, (new CSpan($found))->addClass($class), $end];
		}
	}

	return $result;
}

function nbsp($str) {
	return str_replace(' ', SPACE, $str);
}

function prepareUrlParam($value, $name = null) {
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
 * @param mixed  $param				param name or array with data depend from $getFromRequest
 * @param bool   $getFromRequest	detect data source - input array or $_REQUEST variable
 * @param string $name				if $_REQUEST variable is used this variable not used
 *
 * @return string
 */
function url_param($param, $getFromRequest = true, $name = null) {
	if (is_array($param)) {
		if ($getFromRequest) {
			fatal_error(_('URL parameter cannot be array.'));
		}
	}
	else {
		if (is_null($name)) {
			if (!$getFromRequest) {
				fatal_error(_('URL parameter name is empty.'));
			}

			$name = $param;
		}
	}

	if ($getFromRequest) {
		$value =& $_REQUEST[$param];
	}
	else {
		$value =& $param;
	}

	return isset($value) ? prepareUrlParam($value, $name) : '';
}

function url_params(array $params) {
	$result = '';

	foreach ($params as $param) {
		$result .= url_param($param);
	}

	return $result;
}

function BR() {
	return new CTag('br');
}

function get_icon($type, $params = []) {
	switch ($type) {
		case 'favourite':
			if (CFavorite::exists($params['fav'], $params['elid'], $params['elname'])) {
				$icon = (new CRedirectButton(SPACE, null))
					->addClass(ZBX_STYLE_BTN_REMOVE_FAV)
					->setTitle(_('Remove from favourites'))
					->onClick('rm4favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			else {
				$icon = (new CRedirectButton(SPACE, null))
					->addClass(ZBX_STYLE_BTN_ADD_FAV)
					->setTitle(_('Add to favourites'))
					->onClick('add2favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			$icon->setId('addrm_fav');

			return $icon;

		case 'fullscreen':
			$url = new CUrl();

			if ($params['fullscreen'] == 0) {
				$url->setArgument('fullscreen', '1');

				$icon = (new CRedirectButton(SPACE, $url->getUrl()))
					->setTitle(_('Fullscreen'))
					->addClass(ZBX_STYLE_BTN_MAX);
			}
			else {
				$url->setArgument('fullscreen', '0');

				$icon = (new CRedirectButton(SPACE, $url->getUrl()))
					->setTitle(_('Normal view'))
					->addClass(ZBX_STYLE_BTN_MIN);
			}

			return $icon;

		case 'screenconf':
			return (new CRedirectButton(SPACE, null))
				->addClass(ZBX_STYLE_BTN_CONF)
				->setTitle(_('Refresh interval'));

		case 'overviewhelp':
			return (new CRedirectButton(SPACE, null))
				->addClass(ZBX_STYLE_BTN_INFO);

		case 'reset':
			return (new CRedirectButton(SPACE, null))
				->addClass(ZBX_STYLE_BTN_RESET)
				->setTitle(_('Reset'))
				->onClick('timeControl.objectReset();');
	}
}

/**
 * Create CDiv with host/template information and references to it's elements
 *
 * @param string $currentElement
 * @param int $hostid
 * @param int $lld_ruleid
 *
 * @return object
 */
function get_header_host_table($current_element, $hostid, $lld_ruleid = 0) {
	$options = [
		'output' => [
			'hostid', 'status', 'proxy_hostid', 'name', 'maintenance_status', 'flags', 'available', 'snmp_available',
			'jmx_available', 'ipmi_available', 'error', 'snmp_error', 'jmx_error', 'ipmi_error'
		],
		'selectHostDiscovery' => ['ts_delete'],
		'hostids' => [$hostid],
		'editable' => true
	];
	if ($lld_ruleid == 0) {
		$options['selectApplications'] = API_OUTPUT_COUNT;
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
			$options['selectApplications'] = API_OUTPUT_COUNT;
			$options['selectItems'] = API_OUTPUT_COUNT;
			$options['selectTriggers'] = API_OUTPUT_COUNT;
			$options['selectGraphs'] = API_OUTPUT_COUNT;
			$options['selectScreens'] = API_OUTPUT_COUNT;
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

	/*
	 * list and host (template) name
	 */
	$list = (new CList())->addClass(ZBX_STYLE_OBJECT_GROUP);
	$breadcrumbs = (new CListItem(null))
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _('Breadcrumbs'));

	if ($is_template) {
		$template = new CSpan(
			new CLink($db_host['name'], 'templates.php?form=update&templateid='.$db_host['templateid'])
		);

		if ($current_element === '') {
			$template->addClass(ZBX_STYLE_SELECTED);
		}

		$breadcrumbs->addItem([
			new CSpan(
				new CLink(_('All templates'), 'templates.php?templateid='.$db_host['templateid'].url_param('groupid'))
			),
			'/',
			$template
		]);

		$db_host['hostid'] = $db_host['templateid'];
		$list->addItem($breadcrumbs);
	}
	else {
		$proxy_name = '';

		if ($db_host['proxy_hostid'] != 0) {
			$db_proxies = API::Proxy()->get([
				'output' => ['host'],
				'proxyids' => [$db_host['proxy_hostid']]
			]);

			$proxy_name = CHtml::encode($db_proxies[0]['host']).NAME_DELIMITER;
		}

		$name = $proxy_name.CHtml::encode($db_host['name']);

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
				$status = _('Unknown');
				break;
		}

		$host = new CSpan(new CLink($name, 'hosts.php?form=update&hostid='.$db_host['hostid']));

		if ($current_element === '') {
			$host->addClass(ZBX_STYLE_SELECTED);
		}

		$breadcrumbs->addItem([
			new CSpan(new CLink(_('All hosts'), 'hosts.php?hostid='.$db_host['hostid'].url_param('groupid'))),
			'/',
			$host
		]);
		$list->addItem($breadcrumbs);
		$list->addItem($status);
		$list->addItem(getHostAvailabilityTable($db_host));

		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $db_host['hostDiscovery']['ts_delete'] != 0) {
			$info_icons = [getHostLifetimeIndicator(time(), $db_host['hostDiscovery']['ts_delete'])];
			$list->addItem(makeInformationList($info_icons));
		}
	}

	$content_menu = (new CList())
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _('Content menu'));
	/*
	 * the count of rows
	 */
	if ($lld_ruleid == 0) {
		// applications
		$applications = new CSpan([
			new CLink(_('Applications'), 'applications.php?hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['applications'])
		]);
		if ($current_element == 'applications') {
			$applications->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($applications);

		// items
		$items = new CSpan([
			new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['items'])
		]);
		if ($current_element == 'items') {
			$items->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($items);

		// triggers
		$triggers = new CSpan([
			new CLink(_('Triggers'), 'triggers.php?hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['triggers'])
		]);
		if ($current_element == 'triggers') {
			$triggers->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($triggers);

		// graphs
		$graphs = new CSpan([
			new CLink(_('Graphs'), 'graphs.php?hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['graphs'])
		]);
		if ($current_element == 'graphs') {
			$graphs->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graphs);

		// screens
		if ($is_template) {
			$screens = new CSpan([
				new CLink(_('Screens'), 'screenconf.php?templateid='.$db_host['hostid']),
				CViewHelper::showNum($db_host['screens'])
			]);
			if ($current_element == 'screens') {
				$screens->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($screens);
		}

		// discovery rules
		$lld_rules = new CSpan([
			new CLink(_('Discovery rules'), 'host_discovery.php?hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['discoveries'])
		]);
		if ($current_element == 'discoveries') {
			$lld_rules->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($lld_rules);

		// web scenarios
		$http_tests = new CSpan([
			new CLink(_('Web scenarios'), 'httpconf.php?hostid='.$db_host['hostid']),
			CViewHelper::showNum($db_host['httpTests'])
		]);
		if ($current_element == 'web') {
			$http_tests->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($http_tests);
	}
	else {
		$discovery_rule = (new CSpan())->addItem(
			new CLink(
				CHtml::encode($db_discovery_rule['name']),
				'host_discovery.php?form=update&itemid='.$db_discovery_rule['itemid']
			)
		);

		if ($current_element == 'discoveries') {
			$discovery_rule->addClass(ZBX_STYLE_SELECTED);
		}

		$list->addItem([
			(new CSpan())->addItem(
				new CLink(_('Discovery list'), 'host_discovery.php?hostid='.$db_host['hostid'].url_param('groupid'))
			),
			'/',
			$discovery_rule
		]);

		// item prototypes
		$item_prototypes = new CSpan([
			new CLink(_('Item prototypes'), 'disc_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']),
			CViewHelper::showNum($db_discovery_rule['items'])
		]);
		if ($current_element == 'items') {
			$item_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($item_prototypes);

		// trigger prototypes
		$trigger_prototypes = new CSpan([
			new CLink(_('Trigger prototypes'),
				'trigger_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']
			),
			CViewHelper::showNum($db_discovery_rule['triggers'])
		]);
		if ($current_element == 'triggers') {
			$trigger_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($trigger_prototypes);

		// graph prototypes
		$graph_prototypes = new CSpan([
			new CLink(_('Graph prototypes'), 'graphs.php?parent_discoveryid='.$db_discovery_rule['itemid']),
			CViewHelper::showNum($db_discovery_rule['graphs'])
		]);
		if ($current_element == 'graphs') {
			$graph_prototypes->addClass(ZBX_STYLE_SELECTED);
		}
		$content_menu->addItem($graph_prototypes);

		// host prototypes
		if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
			$host_prototypes = new CSpan([
				new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$db_discovery_rule['itemid']),
				CViewHelper::showNum($db_discovery_rule['hostPrototypes'])
			]);
			if ($current_element == 'hosts') {
				$host_prototypes->addClass(ZBX_STYLE_SELECTED);
			}
			$content_menu->addItem($host_prototypes);
		}
	}

	$list->addItem($content_menu);

	return $list;
}

/**
 * Create CDiv with sysmap information
 *
 * @param int    $sysmapid
 * @param string $name
 *
 * @return object
 */
function get_header_sysmap_table($sysmapid, $name, $fullscreen, $severity_min) {
	$list = (new CList())
		->setAttribute('role', 'navigation')
		->setAttribute('aria-label', _('Breadcrumbs'))
		->addClass(ZBX_STYLE_OBJECT_GROUP)
		->addItem([
			(new CSpan())->addItem(new CLink(_('All maps'), 'sysmaps.php')),
			'/',
			(new CSpan())
				->addClass(ZBX_STYLE_SELECTED)
				->addItem(
					new CLink($name, 'zabbix.php?action=map.view&sysmapid='.$sysmapid.'&fullscreen='.$fullscreen.
						'&severity_min='.$severity_min
					)
				)
		]);

	// get map parent maps
	$parent_sysmaps = get_parent_sysmaps($sysmapid);
	if ($parent_sysmaps) {
		$hor_list = new CHorList();

		foreach ($parent_sysmaps as $parent_sysmap) {
			$hor_list->addItem(
				new CLink($parent_sysmap['name'], 'zabbix.php?action=map.view'.
					'&sysmapid='.$parent_sysmap['sysmapid'].'&fullscreen='.$fullscreen.'&severity_min='.$severity_min
				)
			);
		}

		$list->addItem(new CSpan(_('Upper level maps').':'));
		$list->addItem($hor_list);
	}

	return $list;
}

/**
 * Renders a form footer with the given buttons.
 *
 * @param CButtonInterface 		$main_button	main button that will be displayed on the left
 * @param CButtonInterface[] 	$other_buttons
 *
 * @return CDiv
 *
 * @throws InvalidArgumentException	if an element of $other_buttons contain something other than CButtonInterface
 */
function makeFormFooter(CButtonInterface $main_button = null, array $other_buttons = []) {
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
 * Returns zbx, snmp, jmx, ipmi availability status icons and the discovered host lifetime indicator.
 *
 * @param array $host		an array of host data
 *
 * @return CDiv
 */
function getHostAvailabilityTable($host) {
	$container = (new CDiv())->addClass(ZBX_STYLE_STATUS_CONTAINER);

	foreach (['zbx' => '', 'snmp' => 'snmp_', 'jmx' => 'jmx_', 'ipmi' => 'ipmi_'] as $type => $prefix) {
		switch ($host[$prefix.'available']) {
			case HOST_AVAILABLE_TRUE:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;
			case HOST_AVAILABLE_FALSE:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_RED);

				if ($host[$prefix.'error'] !== '') {
					$ai
						->addClass(ZBX_STYLE_CURSOR_POINTER)
						->setHint($host[$prefix.'error'], ZBX_STYLE_RED);
				}

				break;
			case HOST_AVAILABLE_UNKNOWN:
				$ai = (new CSpan($type))->addClass(ZBX_STYLE_STATUS_GREY);
				break;
		}
		$container->addItem($ai);
	}

	return $container;
}

/**
 * Returns the discovered host group lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the host group
 *
 * @return CDiv
 */
function getHostGroupLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The host group is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The host group is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered host lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the host
 *
 * @return CDiv
 */
function getHostLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The host is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The host is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered application lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the application
 *
 * @return CDiv
 */
function getApplicationLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The application is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The application is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Returns the discovered item lifetime indicator.
 *
 * @param string $current_time	current Unix timestamp
 * @param array  $ts_delete		deletion timestamp of the item
 *
 * @return CDiv
 */
function getItemLifetimeIndicator($current_time, $ts_delete) {
	// Check if the element should've been deleted in the past.
	if ($current_time > $ts_delete) {
		$warning = _(
			'The item is not discovered anymore and will be deleted the next time discovery rule is processed.'
		);
	}
	else {
		$warning = _s(
			'The item is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($ts_delete),
			zbx_date2str(DATE_FORMAT, $ts_delete),
			zbx_date2str(TIME_FORMAT, $ts_delete)
		);
	}

	return makeWarningIcon($warning);
}

/**
 * Create array with all inputs required for date selection and calendar.
 *
 * @param string      $name
 * @param int|array   $date unix timestamp/date array(Y,m,d,H,i)
 * @param string|null $relatedCalendar name of the calendar which must be closed when this calendar opens
 *
 * @return array
 */
function createDateSelector($name, $date, $relatedCalendar = null) {
	$onClick = 'var pos = getPosition(this); pos.top += 10; pos.left += 16; CLNDR["'.$name.
		'_calendar"].clndr.clndrshow(pos.top, pos.left);';
	if ($relatedCalendar) {
		$onClick .= ' CLNDR["'.$relatedCalendar.'_calendar"].clndr.clndrhide();';
	}

	if (is_array($date)) {
		$y = $date['y'];
		$m = $date['m'];
		$d = $date['d'];
		$h = $date['h'];
		$i = $date['i'];
	}
	else {
		$y = date('Y', $date);
		$m = date('m', $date);
		$d = date('d', $date);
		$h = date('H', $date);
		$i = date('i', $date);
	}

	$fields = [
		(new CNumericBox($name.'_year', $y, 4))
			->setWidth(ZBX_TEXTAREA_4DIGITS_WIDTH)
			->setAttribute('placeholder', _('yyyy')),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		'-',
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox($name.'_month', $m, false, 2))
			->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
			->addStyle('text-align: right;')
			->setAttribute('placeholder', _('mm'))
			->onChange('validateDatePartBox(this, 1, 12, 2);'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		'-',
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox($name.'_day', $d, false, 2))
			->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
			->addStyle('text-align: right;')
			->setAttribute('placeholder', _('dd'))
			->onChange('validateDatePartBox(this, 1, 31, 2);'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox($name.'_hour', $h, false, 2))
			->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
			->addStyle('text-align: right;')
			->setAttribute('placeholder', _('hh'))
			->onChange('validateDatePartBox(this, 0, 23, 2);'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		':',
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CTextBox($name.'_minute', $i, false, 2))
			->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
			->addStyle('text-align: right;')
			->setAttribute('placeholder', _('mm'))
			->onChange('validateDatePartBox(this, 0, 59, 2);'),
		(new CButton())
			->addClass(ZBX_STYLE_ICON_CAL)
			->onClick($onClick)
	];

	zbx_add_post_js('create_calendar(null,'.
		'["'.$name.'_day","'.$name.'_month","'.$name.'_year","'.$name.'_hour","'.$name.'_minute"],'.
		'"'.$name.'_calendar",'.
		'"'.$name.'");'
	);

	return $fields;
}

/**
 * Renders a page footer.
 *
 * @param bool $with_logo
 * @param bool $with_version
 *
 * @return CDiv
 */
function makePageFooter($with_version = true)
{
	return (new CTag('footer', true, [
		$with_version ? 'Zabbix '.ZABBIX_VERSION.'. ' : null,
		'&copy; '.ZABBIX_COPYRIGHT_FROM.'&ndash;'.ZABBIX_COPYRIGHT_TO.', ',
		(new CLink('Zabbix SIA', 'http://www.zabbix.com/'))
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT)
			->setAttribute('target', '_blank')
	]))
	->setAttribute('role', 'contentinfo');
}

/**
 * Renders a drop-down menu for the Administration->General section.
 *
 * @param string $selected
 *
 * @return CComboBox
 */
function makeAdministrationGeneralMenu($selected)
{
	return new CComboBox('configDropDown', $selected, 'redirect(this.options[this.selectedIndex].value);', [
		'adm.gui.php' => _('GUI'),
		'adm.housekeeper.php' => _('Housekeeping'),
		'adm.images.php' => _('Images'),
		'adm.iconmapping.php' => _('Icon mapping'),
		'adm.regexps.php' => _('Regular expressions'),
		'adm.macros.php' => _('Macros'),
		'adm.valuemapping.php' => _('Value mapping'),
		'adm.workingtime.php' => _('Working time'),
		'adm.triggerseverities.php' => _('Trigger severities'),
		'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
		'adm.other.php' => _('Other')
	]);
}

/**
 * Renders an icon list
 *
 * @param array $info_icons  The list of information icons
 *
 * @return CSpan
 */
function makeInformationList($info_icons)
{
	return $info_icons ? (new CDiv($info_icons))->addClass(ZBX_STYLE_REL_CONTAINER) : '';
}

/**
 * Renders an information icon like green [i] with message
 *
 * @param string $message
 *
 * @return CSpan
 */
function makeInformationIcon($message)
{
	return (new CSpan())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_GREEN)
		->setHint($message);
}

/**
 * Renders an error icon like red [i] with error message
 *
 * @param string $error
 *
 * @return CSpan
 */
function makeErrorIcon($error)
{
	return (new CSpan())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_RED)
		->setHint($error, ZBX_STYLE_RED);
}

/**
 * Renders an unknown icon like grey [i] with error message
 *
 * @param string $error
 *
 * @return CSpan
 */
function makeUnknownIcon($error)
{
	return (new CSpan())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_DARK_GREY)
		->setHint($error, ZBX_STYLE_RED);
}

/**
 * Renders a warning icon like yellow [i] with error message
 *
 * @param string $error
 *
 * @return CSpan
 */
function makeWarningIcon($error)
{
	return (new CSpan())
		->addClass(ZBX_STYLE_ICON_INFO)
		->addClass(ZBX_STYLE_STATUS_YELLOW)
		->setHint($error);
}

/**
 * Renders a debug button
 *
 * @return CButton
 */
function makeDebugButton()
{
	return (new CDiv(
		(new CLink(_('Debug'), '#debug'))
			->onClick("javascript: if (!isset('state', this)) { this.state = 'none'; }".
				"this.state = (this.state == 'none' ? 'block' : 'none');".
				"jQuery(this)".
					".text(this.state == 'none' ? ".CJs::encodeJson(_('Debug'))." : ".CJs::encodeJson(_('Hide debug')).")".
					".blur();".
				"showHideByName('zbx_debug_info', this.state);"
			)
	))->addClass(ZBX_STYLE_BTN_DEBUG);
}

/**
 * Returns css for trigger severity backgrounds
 *
 * @param array $config
 * @param array $config[severity_color_0]
 * @param array $config[severity_color_1]
 * @param array $config[severity_color_2]
 * @param array $config[severity_color_3]
 * @param array $config[severity_color_4]
 * @param array $config[severity_color_5]
 *
 * @return string
 */
function getTriggerSeverityCss($config)
{
	$severities = [
		ZBX_STYLE_NA_BG => $config['severity_color_0'],
		ZBX_STYLE_INFO_BG => $config['severity_color_1'],
		ZBX_STYLE_WARNING_BG => $config['severity_color_2'],
		ZBX_STYLE_AVERAGE_BG => $config['severity_color_3'],
		ZBX_STYLE_HIGH_BG => $config['severity_color_4'],
		ZBX_STYLE_DISASTER_BG => $config['severity_color_5']
	];

	$css = '';

	foreach ($severities as $class => $color) {
		$css .= '.'.$class.', .'.$class.' input[type="radio"]:checked + label, .'.$class.':before { background-color: #'.$color.' }'."\n";
	}

	return $css;
}
