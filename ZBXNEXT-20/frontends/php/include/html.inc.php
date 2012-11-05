<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	$pos = zbx_stripos($haystack, $needle);
	if ($pos !== false) {
		$start = zbx_substring($haystack, 0, $pos);
		$end = zbx_substring($haystack, $pos + zbx_strlen($needle));
		$found = zbx_substring($haystack, $pos, $pos + zbx_strlen($needle));
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

function prepare_url(&$var, $varname = null) {
	$result = '';
	if (is_array($var)) {
		foreach ($var as $id => $par)
					$result .= prepare_url($par, isset($varname) ? $varname.'['.$id.']' : $id);
				}
	else {
		$result = '&'.$varname.'='.urlencode($var);
	}
	return $result;
}

function url_param($param, $isRequest = true, $name = null) {
	$result = '';
	if (!is_array($param)) {
		if (is_null($name)) {
			if (!$isRequest) {
				fatal_error(_('Not request variable require.'));
			}
			$name = $param;
		}
	}

	if ($isRequest) {
		$var =& $_REQUEST[$param];
	}
	else {
		$var =& $param;
	}

	if (isset($var)) {
		$result = prepare_url($var, $name);
	}
	return $result;
}

function url_params($params) {
	$result = '';
	foreach ($params as $param) {
		$result .= url_param($param);
	}
	return $result;
}

function BR() {
	return new CTag('br', 'no');
}

function create_hat($caption, $items, $addicons = null, $id = null, $state = null) {
	if (is_null($id)) {
		list($usec, $sec) = explode(' ', microtime());
		$id = 'hat_'.((int)($sec % 10)).((int)($usec * 1000));
	}
	$td_l = new CCol(SPACE);
	$td_l->setAttribute('width', '100%');

	$icons_row = array($td_l);
	if (!is_null($addicons)) {
		if (!is_array($addicons)) {
			$addicons = array($addicons);
		}
		foreach ($addicons as $value) {
			$icons_row[] = $value;
		}
	}

	if (!is_null($state)) {
		$icon = new CIcon(_('Show').'/'._('Hide'), $state ? 'arrowup' : 'arrowdown', "change_hat_state(this,'".$id."');");
		$icon->setAttribute('id', $id.'_icon');
		$icons_row[] = $icon;
	}
	else {
		$state = true;
	}

	$icon_tab = new CTable();
	$icon_tab->setAttribute('width', '100%');
	$icon_tab->addRow($icons_row);

	$table = new CTable();
	$table->setAttribute('width', '100%');
	$table->setCellPadding(0);
	$table->setCellSpacing(0);
	$table->addRow(get_table_header($caption, $icon_tab));

	$div = new CDiv($items);
	$div->setAttribute('id', $id);
	if (!$state) {
		$div->setAttribute('style', 'display: none;');
	}
	$table->addRow($div);
	return $table;
}

// searches items/objects for form tags like "<input"/form classes like CForm, and makes it empty
function hide_form_items(&$obj) {
	if (is_array($obj)) {
		foreach ($obj as $id => $item) {
			hide_form_items($obj[$id]); // attention recursion
		}
	}
	elseif (is_object($obj)) {
		$formObjects = array('cform', 'ccheckbox', 'cselect', 'cbutton', 'csubmit', 'cbuttonqmessage', 'cbuttondelete', 'cbuttoncancel');
		if (is_object($obj) && str_in_array(zbx_strtolower(get_class($obj)), $formObjects)) {
			$obj = SPACE;
		}
		if (isset($obj->items) && !empty($obj->items)) {
			foreach ($obj->items as $id => $item) {
				hide_form_items($obj->items[$id]); // attention recursion
			}
		}
	}
	else {
		foreach (array('<form', '<input', '<select') as $item) {
			if (zbx_strpos($obj, $item) !== false) {
				$obj = SPACE;
			}
		}
	}
}

function get_table_header($col1, $col2 = SPACE) {
	if (isset($_REQUEST['print'])) {
		hide_form_items($col1);
		hide_form_items($col2);

		// if empty header than do not show it
		if ($col1 == SPACE && $col2 == SPACE) {
			return new CJSscript('');
		}
	}
	$td_l = new CCol(SPACE, 'header_r');
	$td_l->setAttribute('width', '100%');
	$right_row = array($td_l);

	if (!is_null($col2)) {
		if (!is_array($col2)) {
			$col2 = array($col2);
		}

		foreach ($col2 as $r_item) {
			$right_row[] = new CCol($r_item, 'header_r');
		}
	}

	$right_tab = new CTable(null, 'nowrap');
	$right_tab->setAttribute('width', '100%');
	$right_tab->addRow($right_row);

	$table = new CTable(null, 'header maxwidth ui-widget-header ui-corner-all');
	$table->setCellSpacing(0);
	$table->setCellPadding(1);

	$td_r = new CCol($right_tab, 'header_r right');
	$td_r->setAttribute('align', 'right');

	$table->addRow(array(new CCol($col1, 'header_l left'), $td_r));
	return $table;
}

function show_table_header($col1, $col2 = SPACE){
	$table = get_table_header($col1, $col2);
	$table->show();
}

function get_icon($name, $params = array()) {
	switch ($name) {
		case 'favourite':
			if (CFavorite::exists($params['fav'], $params['elid'], $params['elname'])) {
				$icon = new CIcon(
					_('Remove from favourites'),
					'iconminus',
					'rm4favorites("'.$params['elname'].'", "'.$params['elid'].'", 0);'
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
			break;
		case 'fullscreen':
			$url = new Curl();
			$url->setArgument('fullscreen', $params['fullscreen'] ? '0' : '1');
			$icon = new CIcon(
				$_REQUEST['fullscreen'] ? _('Normal view') : _('Fullscreen'),
				'fullscreen',
				"document.location = '".$url->getUrl()."';"
			);
			break;
		case 'menu':
			$icon = new CIcon(_('Menu'), 'iconmenu', 'create_page_menu(event, "'.$params['menu'].'");');
			break;
		case 'reset':
			$icon = new CIcon(_('Reset'), 'iconreset', 'timeControl.objectReset();');
			break;
	}
	return $icon;
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
	$elements = array(
		'items' => 'items',
		'triggers' => 'triggers',
		'graphs' => 'graphs',
		'applications' => 'applications',
		'screens' => 'screens',
		'discoveries' => 'discoveries',
		'web' => 'web'
	);
	if (!empty($discoveryid)) {
		unset($elements['applications'], $elements['screens'], $elements['discoveries']);
	}

	$options = array(
		'hostids' => $hostid,
		'output' => API_OUTPUT_EXTEND,
		'templated_hosts' => true
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

	// get hosts
	$dbHost = API::Host()->get($options);
	$dbHost = reset($dbHost);

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
			'output' => API_OUTPUT_SHORTEN,
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
	$description = '';
	if ($dbHost['proxy_hostid']) {
		$proxy = get_host_by_hostid($dbHost['proxy_hostid']);
		$description .= $proxy['host'].': ';
	}
	$description .= $dbHost['name'];

	if ($dbHost['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array(bold(_('Template').': '), new CLink($description, 'templates.php?form=update&templateid='.$dbHost['hostid'])));
	}
	else {
		switch ($dbHost['status']) {
			case HOST_STATUS_MONITORED:
				$status = new CSpan(_('Monitored'), 'off');
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status = new CSpan(_('Not monitored'), 'on');
				break;
			default:
				$status = _('Unknown');
				break;
		}

		$list->addItem(array(bold(_('Host').': '), new CLink($description, 'hosts.php?form=update&hostid='.$dbHost['hostid'])));
		$list->addItem($status);
		$list->addItem(getAvailabilityTable($dbHost));
	}

	if (!empty($dbDiscovery)) {
		$list->addItem(array('&laquo; ', new CLink(_('Discovery list'), 'host_discovery.php?hostid='.$dbHost['hostid'].url_param('groupid'))));
		$list->addItem(array(
			bold(_('Discovery').': '),
			new CLink($dbDiscovery['name'], 'host_discovery.php?form=update&itemid='.$dbDiscovery['itemid'])
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

function makeFormFooter($main, $others = null) {
	if (!is_array($main)) {
		$main = array($main);
	}
	if (!empty($others) && !is_array($others)) {
		$others = array($others);
	}

	$mainButtons = new CDiv();
	foreach ($main as $button) {
		$button->useJQueryStyle('main');
		$mainButtons->addItem($button);
	}
	$othersButtons = new CDiv($others);
	$othersButtons->useJQueryStyle();

	return new CDiv(
		new CDiv(
			array(
				empty($others) ? new CDiv($mainButtons, 'dt right') : new CDiv($mainButtons, 'dt floatleft right'),
				new CDiv(array($othersButtons), 'dd left')
			),
			'formrow'
		),
		'objectgroup footer min-width ui-widget-content ui-corner-all'
	);
}

/**
 * Create control for trigger severities.
 * It's mostly the same as usual button set, but background color is changed based on selected severiity.
 * Js file "/include/views/js/configuration.triggers.edit.js.php" is needed to make it work correcty.
 *
 * @param $selectedSeverity
 *
 * @return CDiv
 */
function getSeverityControl($selectedSeverity = TRIGGER_SEVERITY_NOT_CLASSIFIED) {
	$controls = array();
	foreach (getSeverityCaption() as $severity => $caption) {
		$controls[] = new CRadioButton('priority', $severity, null, 'severity_'.$severity, $selectedSeverity == $severity);

		$label = new CLabel($caption, 'severity_'.$severity, 'severity_label_'.$severity);
		$label->attr('data-severity', $severity);
		$label->attr('data-severity-style', getSeverityStyle($severity));
		$controls[] = $label;

	}

	return new CDiv($controls, 'jqueryinputset control-severity');
}

/**
 * Returns zbx, snmp, jmx, ipmi availability status icons.
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

	return $ad;
}

/**
 * Create array with all inputs required for date selection and calendar.
 *
 * @param string      $name
 * @param int         $date
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

	$day = new CNumericBox($name.'_day', $date > 0 ? date('d', $date) : '', 2);
	$day->attr('placeholder', _('dd'));
	$month = new CNumericBox($name.'_month', $date > 0 ? date('m', $date) : '', 2);
	$month->attr('placeholder', _('mm'));
	$year = new CNumericBox($name.'_year', $date > 0 ? date('Y', $date) : '', 4);
	$year->attr('placeholder', _('yyyy'));
	$hour = new CNumericBox($name.'_hour', $date > 0 ? date('H', $date) : '', 2);
	$hour->attr('placeholder', _('hh'));
	$minute = new CNumericBox($name.'_minute', $date > 0 ? date('i', $date) : '', 2);
	$minute->attr('placeholder', _('mm'));

	$fields = array($day, '/', $month, '/', $year, SPACE, $hour, ':', $minute, $calendarIcon);

	zbx_add_post_js('create_calendar(null,'.
		'["'.$name.'_day","'.$name.'_month","'.$name.'_year","'.$name.'_hour","'.$name.'_minute"],'.
		'"'.$name.'_calendar",'.
		'"'.$name.'");'
	);

	return $fields;
}
