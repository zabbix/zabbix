<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
				$em = new CTag('em', 'yes');
				$em->addItem($val);
				$str[$key] = $em;
			}
		}
	}
	elseif (is_string($str)) {
		$em = new CTag('em', 'yes', '');
		$em->addItem($str);
		$str = $em;
	}
	return $str;
}

function bold($str) {
	if (is_array($str)) {
		foreach ($str as $key => $val) {
			if (is_string($val)) {
				$b = new CTag('strong', 'yes');
				$b->addItem($val);
				$str[$key] = $b;
			}
		}
	}
	else {
		$b = new CTag('strong', 'yes', '');
		$b->addItem($str);
		$str = $b;
	}
	return $str;
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
			$result = array($start, bold($found), $end);
		}
		else {
			$result = array($start, new CSpan($found, $class), $end);
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
	return new CTag('br', 'no');
}

function get_table_header($columnLeft, $columnRights = SPACE) {
	$rights = array();

	if ($columnRights) {
		if (!is_array($columnRights)) {
			$columnRights = array($columnRights);
		}

		foreach ($columnRights as $columnRight) {
			$rights[] = new CDiv($columnRight, 'floatright');
		}

		$rights = array_reverse($rights);
	}

	$table = new CTable(null, 'ui-widget-header ui-corner-all header maxwidth');
	$table->setCellSpacing(0);
	$table->setCellPadding(1);
	$table->addRow(array(new CCol($columnLeft, 'header_l left'), new CCol($rights, 'header_r right')));

	return $table;
}

function show_table_header($columnLeft, $columnRights = SPACE){
	$table = get_table_header($columnLeft, $columnRights);
	$table->show();
}

function get_icon($type, $params = array()) {
	switch ($type) {
		case 'favourite':
			if (CFavorite::exists($params['fav'], $params['elid'], $params['elname'])) {
				$icon = new CIcon(
					_('Remove from favourites'),
					'iconminus',
					'rm4favorites("'.$params['elname'].'", "'.$params['elid'].'");'
				);
			}
			else {
				$icon = new CIcon(
					_('Add to favourites'),
					'iconplus',
					'add2favorites("'.$params['elname'].'", "'.$params['elid'].'");'
				);
			}
			$icon->setAttribute('id', 'addrm_fav');

			return $icon;

		case 'fullscreen':
			$url = new CUrl();
			$url->setArgument('fullscreen', $params['fullscreen'] ? '0' : '1');

			return new CIcon(
				$params['fullscreen'] ? _('Normal view') : _('Fullscreen'),
				'fullscreen',
				"document.location = '".$url->getUrl()."';"
			);

		case 'reset':
			return new CIcon(_('Reset'), 'iconreset', 'timeControl.objectReset();');
	}

	return null;
}

/**
 * Create CDiv with host/template information and references to it's elements
 *
 * @param string $currentElement
 * @param int $hostid
 * @param int $discoveryid
 *
 * @return object
 */
function get_header_host_table($currentElement, $hostid, $discoveryid = null) {
	// LLD rule header
	if ($discoveryid) {
		$elements = array(
			'items' => 'items',
			'triggers' => 'triggers',
			'graphs' => 'graphs',
			'hosts' => 'hosts'
		);
	}
	// host header
	else {
		$elements = array(
			'items' => 'items',
			'triggers' => 'triggers',
			'graphs' => 'graphs',
			'applications' => 'applications',
			'screens' => 'screens',
			'discoveries' => 'discoveries',
			'web' => 'web'
		);
	}

	$options = array(
		'hostids' => $hostid,
		'output' => API_OUTPUT_EXTEND,
		'templated_hosts' => true,
		'selectHostDiscovery' => array('ts_delete')
	);
	if (isset($elements['items'])) {
		$options['selectItems'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['triggers'])) {
		$options['selectTriggers'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['graphs'])) {
		$options['selectGraphs'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['applications'])) {
		$options['selectApplications'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['discoveries'])) {
		$options['selectDiscoveries'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['web'])) {
		$options['selectHttpTests'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['hosts'])) {
		$options['selectHostPrototypes'] = API_OUTPUT_COUNT;
	}

	// get hosts
	$dbHost = API::Host()->get($options);
	$dbHost = reset($dbHost);
	if (!$dbHost) {
		return null;
	}
	// get discoveries
	if (!empty($discoveryid)) {
		$options['itemids'] = $discoveryid;
		$options['output'] = array('name');
		unset($options['hostids'], $options['templated_hosts']);

		$dbDiscovery = API::DiscoveryRule()->get($options);
		$dbDiscovery = reset($dbDiscovery);
	}

	/*
	 * Back
	 */
	$list = new CList(null, 'objectlist');
	if ($dbHost['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array('&laquo; ', new CLink(_('Template list'), 'templates.php?templateid='.$dbHost['hostid'].url_param('groupid'))));

		$dbHost['screens'] = API::TemplateScreen()->get(array(
			'editable' => true,
			'countOutput' => true,
			'groupCount' => true,
			'templateids' => $dbHost['hostid']
		));
		$dbHost['screens'] = isset($dbHost['screens'][0]['rowscount']) ? $dbHost['screens'][0]['rowscount'] : 0;
	}
	else {
		$list->addItem(array('&laquo; ', new CLink(_('Host list'), 'hosts.php?hostid='.$dbHost['hostid'].url_param('groupid'))));
	}

	/*
	 * Name
	 */
	$proxyName = '';
	if ($dbHost['proxy_hostid']) {
		$proxy = get_host_by_hostid($dbHost['proxy_hostid']);

		$proxyName = CHtml::encode($proxy['host']).NAME_DELIMITER;
	}

	$name = $proxyName.CHtml::encode($dbHost['name']);

	if ($dbHost['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array(bold(_('Template').NAME_DELIMITER), new CLink($name, 'templates.php?form=update&templateid='.$dbHost['hostid'])));
	}
	else {
		switch ($dbHost['status']) {
			case HOST_STATUS_MONITORED:
				if ($dbHost['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$status = new CSpan(_('In maintenance'), 'orange');
				}
				else {
					$status = new CSpan(_('Enabled'), 'enabled');
				}
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status = new CSpan(_('Disabled'), 'on');
				break;
			default:
				$status = _('Unknown');
				break;
		}

		$list->addItem(array(bold(_('Host').NAME_DELIMITER), new CLink($name, 'hosts.php?form=update&hostid='.$dbHost['hostid'])));
		$list->addItem($status);
		$list->addItem(getAvailabilityTable($dbHost));
	}

	if (!empty($dbDiscovery)) {
		$list->addItem(array('&laquo; ', new CLink(_('Discovery list'), 'host_discovery.php?hostid='.$dbHost['hostid'].url_param('groupid'))));
		$list->addItem(array(
			bold(_('Discovery').NAME_DELIMITER),
			new CLink(CHtml::encode($dbDiscovery['name']), 'host_discovery.php?form=update&itemid='.$dbDiscovery['itemid'])
		));
	}

	/*
	 * Rowcount
	 */
	if (isset($elements['applications'])) {
		if ($currentElement == 'applications') {
			$list->addItem(_('Applications').' ('.$dbHost['applications'].')');
		}
		else {
			$list->addItem(array(
				new CLink(_('Applications'), 'applications.php?hostid='.$dbHost['hostid']),
				' ('.$dbHost['applications'].')'
			));
		}
	}

	if (isset($elements['items'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'items') {
				$list->addItem(_('Item prototypes').' ('.$dbDiscovery['items'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Item prototypes'), 'disc_prototypes.php?hostid='.$dbHost['hostid'].'&parent_discoveryid='.$dbDiscovery['itemid']),
					' ('.$dbDiscovery['items'].')'
				));
			}
		}
		else {
			if ($currentElement == 'items') {
				$list->addItem(_('Items').' ('.$dbHost['items'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$dbHost['hostid']),
					' ('.$dbHost['items'].')'
				));
			}
		}
	}

	if (isset($elements['triggers'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'triggers') {
				$list->addItem(_('Trigger prototypes').' ('.$dbDiscovery['triggers'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Trigger prototypes'), 'trigger_prototypes.php?hostid='.$dbHost['hostid'].'&parent_discoveryid='.$dbDiscovery['itemid']),
					' ('.$dbDiscovery['triggers'].')'
				));
			}
		}
		else {
			if ($currentElement == 'triggers') {
				$list->addItem(_('Triggers').' ('.$dbHost['triggers'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Triggers'), 'triggers.php?hostid='.$dbHost['hostid']),
					' ('.$dbHost['triggers'].')'
				));
			}
		}
	}

	if (isset($elements['graphs'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'graphs') {
				$list->addItem(_('Graph prototypes').' ('.$dbDiscovery['graphs'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Graph prototypes'), 'graphs.php?hostid='.$dbHost['hostid'].'&parent_discoveryid='.$dbDiscovery['itemid']),
					' ('.$dbDiscovery['graphs'].')'
				));
			}
		}
		else {
			if ($currentElement == 'graphs') {
				$list->addItem(_('Graphs').' ('.$dbHost['graphs'].')');
			}
			else {
				$list->addItem(array(
					new CLink(_('Graphs'), 'graphs.php?hostid='.$dbHost['hostid']),
					' ('.$dbHost['graphs'].')'
				));
			}
		}
	}

	if (isset($elements['hosts']) && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		if ($currentElement == 'hosts') {
			$list->addItem(_('Host prototypes').' ('.$dbDiscovery['hostPrototypes'].')');
		}
		else {
			$list->addItem(array(
				new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$dbDiscovery['itemid']),
				' ('.$dbDiscovery['hostPrototypes'].')'
			));
		}
	}

	if (isset($elements['screens']) && $dbHost['status'] == HOST_STATUS_TEMPLATE) {
		if ($currentElement == 'screens') {
			$list->addItem(_('Screens').' ('.$dbHost['screens'].')');
		}
		else {
			$list->addItem(array(
				new CLink(_('Screens'), 'screenconf.php?templateid='.$dbHost['hostid']),
				' ('.$dbHost['screens'].')'
			));
		}
	}

	if (isset($elements['discoveries'])) {
		if ($currentElement == 'discoveries') {
			$list->addItem(_('Discovery rules').' ('.$dbHost['discoveries'].')');
		}
		else {
			$list->addItem(array(
				new CLink(_('Discovery rules'), 'host_discovery.php?hostid='.$dbHost['hostid']),
				' ('.$dbHost['discoveries'].')'
			));
		}
	}

	if (isset($elements['web'])) {
		if ($currentElement == 'web') {
			$list->addItem(_('Web scenarios').' ('.$dbHost['httpTests'].')');
		}
		else {
			$list->addItem(array(
				new CLink(_('Web scenarios'), 'httpconf.php?hostid='.$dbHost['hostid']),
				' ('.$dbHost['httpTests'].')'
			));
		}
	}

	return new CDiv($list, 'objectgroup top ui-widget-content ui-corner-all');
}

function makeFormFooter($main = null, $others = null) {
	if ($main) {
		$main->useJQueryStyle('main');
	}

	$othersButtons = new CDiv($others);
	$othersButtons->useJQueryStyle();

	return new CDiv(
		new CDiv(
			new CDiv(
				array(
					new CDiv($main, 'dt right'),
					new CDiv($othersButtons, 'dd left')
				),
				'formrow'
			),
			'formtable'
		),
		'objectgroup footer ui-widget-content ui-corner-all'
	);
}

/**
 * Returns zbx, snmp, jmx, ipmi availability status icons and the discovered host lifetime indicator.
 *
 * @param type $host
 *
 * @return CDiv
 */
function getAvailabilityTable($host) {
	$arr = array('zbx', 'snmp', 'jmx', 'ipmi');

	// for consistency in foreach loop
	$host['zbx_available'] = $host['available'];
	$host['zbx_error'] = $host['error'];

	$ad = new CDiv(null, 'invisible');

	foreach ($arr as $val) {
		switch ($host[$val.'_available']) {
			case HOST_AVAILABLE_TRUE:
				$ai = new CDiv(SPACE, 'status_icon status_icon_extra icon'.$val.'available');
				break;
			case HOST_AVAILABLE_FALSE:
				$ai = new CDiv(SPACE, 'status_icon status_icon_extra icon'.$val.'unavailable');
				$ai->setHint($host[$val.'_error'], '', 'on');
				break;
			case HOST_AVAILABLE_UNKNOWN:
				$ai = new CDiv(SPACE, 'status_icon status_icon_extra icon'.$val.'unknown');
				break;
		}
		$ad->addItem($ai);
	}

	// discovered host lifetime indicator
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $host['hostDiscovery']['ts_delete']) {
		$deleteError = new CDiv(SPACE, 'status_icon status_icon_extra iconwarning');
		$deleteError->setHint(_s(
			'The host is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
			zbx_date2age($host['hostDiscovery']['ts_delete']),
			zbx_date2str(DATE_FORMAT, $host['hostDiscovery']['ts_delete']),
			zbx_date2str(TIME_FORMAT, $host['hostDiscovery']['ts_delete'])
		));
		$ad->addItem($deleteError);
	}

	return $ad;
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
	$calendarIcon = new CImg('images/general/bar/cal.gif', 'calendar', 16, 12, 'pointer');
	$onClick = 'var pos = getPosition(this); pos.top += 10; pos.left += 16; CLNDR["'.$name.
		'_calendar"].clndr.clndrshow(pos.top, pos.left);';
	if ($relatedCalendar) {
		$onClick .= ' CLNDR["'.$relatedCalendar.'_calendar"].clndr.clndrhide();';
	}

	$calendarIcon->onClick($onClick);

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

	$day = new CNumericBox($name.'_day', $d, 2);
	$day->attr('placeholder', _('dd'));
	$month = new CNumericBox($name.'_month', $m, 2);
	$month->attr('placeholder', _('mm'));
	$year = new CNumericBox($name.'_year', $y, 4);
	$year->attr('placeholder', _('yyyy'));
	$hour = new CNumericBox($name.'_hour', $h, 2);
	$hour->attr('placeholder', _('hh'));
	$minute = new CNumericBox($name.'_minute', $i, 2);
	$minute->attr('placeholder', _('mm'));

	$fields = array($year, '-', $month, '-', $day, SPACE, $hour, ':', $minute, $calendarIcon);

	zbx_add_post_js('create_calendar(null,'.
		'["'.$name.'_day","'.$name.'_month","'.$name.'_year","'.$name.'_hour","'.$name.'_minute"],'.
		'"'.$name.'_calendar",'.
		'"'.$name.'");'
	);

	return $fields;
}
