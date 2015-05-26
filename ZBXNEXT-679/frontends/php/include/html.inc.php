<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
				$icon = new CRedirectButton(SPACE, null);
				$icon->addClass(ZBX_STYLE_BTN_REMOVE_FAV);
				$icon->setTitle(_('Remove from favourites'));
				$icon->addAction('onclick', 'rm4favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			else {
				$icon = new CRedirectButton(SPACE, null);
				$icon->addClass(ZBX_STYLE_BTN_ADD_FAV);
				$icon->setTitle(_('Add to favourites'));
				$icon->addAction('onclick', 'add2favorites("'.$params['elname'].'", "'.$params['elid'].'");');
			}
			$icon->setAttribute('id', 'addrm_fav');

			return $icon;

		case 'fullscreen':
			$url = new CUrl();

			if ($params['fullscreen'] == 0) {
				$url->setArgument('fullscreen', '1');

				$icon = new CRedirectButton(SPACE, $url->getUrl());
				$icon->setTitle(_('Fullscreen'));
				$icon->addClass(ZBX_STYLE_BTN_MAX);
			}
			else {
				$url->setArgument('fullscreen', '0');

				$icon = new CRedirectButton(SPACE, $url->getUrl());
				$icon->setTitle(_('Normal view'));
				$icon->addClass(ZBX_STYLE_BTN_MIN);
			}

			return $icon;

		case 'dashconf':

			$icon = new CRedirectButton(SPACE, 'dashconf.php');
			$icon->addClass(ZBX_STYLE_BTN_CONF);
			$icon->setTitle(_('Configure'));

			return $icon;

		case 'screenconf':

			$icon = new CRedirectButton(SPACE, null);
			$icon->addClass(ZBX_STYLE_BTN_CONF);
			$icon->setTitle(_('Refresh time'));

			return $icon;

		case 'overviewhelp':

			$icon = new CRedirectButton(SPACE, null);
			$icon->addClass(ZBX_STYLE_BTN_INFO);

			return $icon;

		case 'reset':
			$icon = new CRedirectButton(SPACE, null);
			$icon->addClass(ZBX_STYLE_BTN_RESET);
			$icon->setTitle(_('Reset'));
			$icon->addAction('onclick', 'timeControl.objectReset();');

			return $icon;
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
	$list = new CList([], 'object-group');
	if ($dbHost['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array(new CLink(_('All templates'), '&gt;', 'templates.php?templateid='.$dbHost['hostid'].url_param('groupid'))));

		$dbHost['screens'] = API::TemplateScreen()->get(array(
			'editable' => true,
			'countOutput' => true,
			'groupCount' => true,
			'templateids' => $dbHost['hostid']
		));
		$dbHost['screens'] = isset($dbHost['screens'][0]['rowscount']) ? $dbHost['screens'][0]['rowscount'] : 0;
	}
	else {
		$list->addItem(array(new CLink(_('All hosts'), 'hosts.php?hostid='.$dbHost['hostid'].url_param('groupid'))));
	}

	$list->addItem(new CSpan(null, 'arrow-right'));

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
					$status = new CSpan(_('In maintenance'), ZBX_STYLE_ORANGE);
				}
				else {
					$status = new CSpan(_('Enabled'), ZBX_STYLE_GREEN);
				}
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status = new CSpan(_('Disabled'), ZBX_STYLE_RED);
				break;
			default:
				$status = _('Unknown');
				break;
		}

		$list->addItem(array(bold(_('Host').NAME_DELIMITER), new CLink($name, 'hosts.php?form=update&hostid='.$dbHost['hostid'])));
		$list->addItem($status);
		$list->addItem(getAvailabilityTable($dbHost, time()));
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
			$list->addItem(array(
				_('Applications'),
				CViewHelper::showNum($dbHost['applications'])
			));
		}
		else {
			$list->addItem(array(
				new CLink(_('Applications'), 'applications.php?hostid='.$dbHost['hostid']),
				CViewHelper::showNum($dbHost['applications'])
			));
		}
	}

	if (isset($elements['items'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'items') {
				$list->addItem(array(
					_('Item prototypes'),
					CViewHelper::showNum($dbDiscovery['items'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Item prototypes'), 'disc_prototypes.php?parent_discoveryid='.$dbDiscovery['itemid']),
					CViewHelper::showNum($dbDiscovery['items'])
				));
			}
		}
		else {
			if ($currentElement == 'items') {
				$list->addItem(array(
					_('Items'),
					CViewHelper::showNum($dbHost['items'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$dbHost['hostid']),
					CViewHelper::showNum($dbHost['items'])
				));
			}
		}
	}

	if (isset($elements['triggers'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'triggers') {
				$list->addItem(array(
					_('Trigger prototypes'),
					CViewHelper::showNum($dbDiscovery['triggers'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Trigger prototypes'), 'trigger_prototypes.php?parent_discoveryid='.$dbDiscovery['itemid']),
					CViewHelper::showNum($dbDiscovery['triggers'])
				));
			}
		}
		else {
			if ($currentElement == 'triggers') {
				$list->addItem(array(
					_('Triggers'),
					CViewHelper::showNum($dbHost['triggers'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Triggers'), 'triggers.php?hostid='.$dbHost['hostid']),
					CViewHelper::showNum($dbHost['triggers'])
				));
			}
		}
	}

	if (isset($elements['graphs'])) {
		if (!empty($dbDiscovery)) {
			if ($currentElement == 'graphs') {
				$list->addItem(array(
					_('Graph prototypes'),
					CViewHelper::showNum($dbDiscovery['graphs'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Graph prototypes'), 'graphs.php?parent_discoveryid='.$dbDiscovery['itemid']),
					CViewHelper::showNum($dbDiscovery['graphs'])
				));
			}
		}
		else {
			if ($currentElement == 'graphs') {
				$list->addItem(array(
					_('Graphs'),
					CViewHelper::showNum($dbHost['graphs'])
				));
			}
			else {
				$list->addItem(array(
					new CLink(_('Graphs'), 'graphs.php?hostid='.$dbHost['hostid']),
					CViewHelper::showNum($dbHost['graphs'])
				));
			}
		}
	}

	if (isset($elements['hosts']) && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		if ($currentElement == 'hosts') {
			$list->addItem(array(
				_('Host prototypes'),
				CViewHelper::showNum($dbDiscovery['hostPrototypes'])
			));
		}
		else {
			$list->addItem(array(
				new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$dbDiscovery['itemid']),
				CViewHelper::showNum($dbDiscovery['hostPrototypes'])
			));
		}
	}

	if (isset($elements['screens']) && $dbHost['status'] == HOST_STATUS_TEMPLATE) {
		if ($currentElement == 'screens') {
			$list->addItem(array(
				_('Screens'),
				CViewHelper::showNum($dbHost['screens'])
			));
		}
		else {
			$list->addItem(array(
				new CLink(_('Screens'), 'screenconf.php?templateid='.$dbHost['hostid']),
				CViewHelper::showNum($dbHost['screens'])
			));
		}
	}

	if (isset($elements['discoveries'])) {
		if ($currentElement == 'discoveries') {
			$list->addItem(array(
				_('Discovery rules'),
				CViewHelper::showNum($dbHost['discoveries'])
			));
		}
		else {
			$list->addItem(array(
				new CLink(_('Discovery rules'), 'host_discovery.php?hostid='.$dbHost['hostid']),
				CViewHelper::showNum($dbHost['discoveries'])
			));
		}
	}

	if (isset($elements['web'])) {
		if ($currentElement == 'web') {
			$list->addItem(array(
				_('Web scenarios'),
				CViewHelper::showNum($dbHost['httpTests'])
			));
		}
		else {
			$list->addItem(array(
				new CLink(_('Web scenarios'), 'httpconf.php?hostid='.$dbHost['hostid']),
				CViewHelper::showNum($dbHost['httpTests'])
			));
		}
	}

	return $list;
}

/**
 * Renders a form footer with the given buttons.
 *
 * @param CButtonInterface 		$mainButton	main button that will be displayed on the left
 * @param CButtonInterface[] 	$otherButtons
 *
 * @return CDiv
 *
 * @throws InvalidArgumentException	if an element of $otherButtons contain something other than CButtonInterface
 */
function makeFormFooter(CButtonInterface $mainButton = null, array $otherButtons = array()) {
	foreach ($otherButtons as $button) {
		$button->addClass('btn-alt');
	}

	$buttons = new CList([], 'table-forms');

	if ($mainButton !== null) {
		$buttons->addItem(array(
			new CDiv($mainButton, ZBX_STYLE_TABLE_FORMS_TD_LEFT),
			new CDiv($otherButtons, ZBX_STYLE_TABLE_FORMS_TD_RIGHT))
		);
	}
	else {
		$buttons->addItem(array(
			new CDiv(SPACE, ZBX_STYLE_TABLE_FORMS_TD_LEFT),
			new CDiv($otherButtons, ZBX_STYLE_TABLE_FORMS_TD_RIGHT))
		);
	}

//	return new CDiv($buttons, 'form-btns');
	return $buttons;
}

/**
 * Returns zbx, snmp, jmx, ipmi availability status icons and the discovered host lifetime indicator.
 *
 * @param array  $host			an array of host data
 * @param string $currentTime	current Unix timestamp
 *
 * @return CDiv
 */
function getAvailabilityTable($host, $currentTime) {
	$arr = array('zbx', 'snmp', 'jmx', 'ipmi');

	// for consistency in foreach loop
	$host['zbx_available'] = $host['available'];
	$host['zbx_error'] = $host['error'];

	$ad = array();

	foreach ($arr as $val) {
		switch ($host[$val.'_available']) {
			case HOST_AVAILABLE_TRUE:
				$ai = new CSpan($val, 'status-green');
				break;
			case HOST_AVAILABLE_FALSE:
				$ai = new CSpan($val, 'status-red');
				$ai->setHint($host[$val.'_error'], ZBX_STYLE_RED);
				break;
			case HOST_AVAILABLE_UNKNOWN:
				$ai = new CSpan($val, 'status-grey');
				break;
		}
		$ad[] = $ai;
		$ad[] = ' ';
	}

	// discovered host lifetime indicator
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $host['hostDiscovery']['ts_delete']) {
		$deleteError = new CSpan(SPACE);

		// Check if host should've been deleted in the past.
		if ($currentTime > $host['hostDiscovery']['ts_delete']) {
			$deleteError->setHint(_s(
				'The host is not discovered anymore and will be deleted the next time discovery rule is processed.'
			));
		}
		else {
			$deleteError->setHint(_s(
				'The host is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
				zbx_date2age($host['hostDiscovery']['ts_delete']),
				zbx_date2str(DATE_FORMAT, $host['hostDiscovery']['ts_delete']),
				zbx_date2str(TIME_FORMAT, $host['hostDiscovery']['ts_delete'])
			));
		}

		$ad[] = $deleteError;
		$ad[] = ' ';
	}

	array_pop($ad);

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

	$day = new CTextBox($name.'_day', $d, 2, false, 2);
	$day->attr('style', 'text-align: right;');
	$day->attr('placeholder', _('dd'));
	$day->addAction('onchange', 'validateDatePartBox(this, 1, 31, 2);');

	$month = new CTextBox($name.'_month', $m, 2, false, 2);
	$month->attr('style', 'text-align: right;');
	$month->attr('placeholder', _('mm'));
	$month->addAction('onchange', 'validateDatePartBox(this, 1, 12, 2);');

	$year = new CNumericBox($name.'_year', $y, 4);
	$year->attr('placeholder', _('yyyy'));

	$hour = new CTextBox($name.'_hour', $h, 2, false, 2);
	$hour->attr('style', 'text-align: right;');
	$hour->attr('placeholder', _('hh'));
	$hour->addAction('onchange', 'validateDatePartBox(this, 0, 23, 2);');

	$minute = new CTextBox($name.'_minute', $i, 2, false, 2);
	$minute->attr('style', 'text-align: right;');
	$minute->attr('placeholder', _('mm'));
	$minute->addAction('onchange', 'validateDatePartBox(this, 0, 59, 2);');

	$fields = array($year, '-', $month, '-', $day, ' ', $hour, ':', $minute, $calendarIcon);

	zbx_add_post_js('create_calendar(null,'.
		'["'.$name.'_day","'.$name.'_month","'.$name.'_year","'.$name.'_hour","'.$name.'_minute"],'.
		'"'.$name.'_calendar",'.
		'"'.$name.'");'
	);

	return $fields;
}
