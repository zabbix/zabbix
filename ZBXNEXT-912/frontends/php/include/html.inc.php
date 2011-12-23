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
?>
<?php
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
		foreach ($var as $id => $par )
			$result .= prepare_url($par, isset($varname) ? $varname.'['.$id.']' : $id);
	}
	else {
		$result = '&'.$varname.'='.urlencode($var);
	}
	return $result;
}

function url_param($parameter, $request = true, $name = null) {
	$result = '';
	if (!is_array($parameter)) {
		if (is_null($name)) {
			if (!$request) {
				fatal_error('not request variable require url name [url_param]');
			}
			$name = $parameter;
		}
	}

	if ($request) {
		$var =& $_REQUEST[$parameter];
	}
	else {
		$var =& $parameter;
	}

	if (isset($var)) {
		$result = prepare_url($var, $name);
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

/* Function:
 *	hide_form_items()
 *
 * Desc:
 *	Searches items/objects for Form tags like "<input"/Form classes like CForm, and makes it empty
 *
 * Author:
 *	Aly
 */
function hide_form_items(&$obj) {
	if (is_array($obj)) {
		foreach ($obj as $id => $item) {
			hide_form_items($obj[$id]); // Attention recursion;
		}
	}
	elseif (is_object($obj)) {
		$formObjects = array('cform', 'ccheckbox', 'cselect', 'cbutton', 'csubmit', 'cbuttonqmessage', 'cbuttondelete', 'cbuttoncancel');
		if (is_object($obj) && str_in_array(zbx_strtolower(get_class($obj)), $formObjects)) {
			$obj = SPACE;
		}
		if (isset($obj->items) && !empty($obj->items)) {
			foreach ($obj->items as $id => $item) {
				hide_form_items($obj->items[$id]); // Recursion
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
			if (infavorites($params['fav'], $params['elid'], $params['elname'])) {
				$icon = new CIcon(
					_('Remove from favourites'),
					'iconminus',
					'rm4favorites("'.$params['elname'].'", "'.$params['elid'].'", 0);'
				);
			}
			else{
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
			$icon = new CIcon(_('Reset'), 'iconreset', 'timeControl.objectReset("'.$params['id'].'");');
			break;
	}
	return $icon;
}

/**
 * Create CDiv with host/template information and references to it's elements
 *
 * @param string $hostid
 * @param string $current elements that reference should not be added to
 * @return object
 */
function get_header_host_table($hostid, $current = null) {
	$elements = array(
		'items' => 'items',
		'triggers' => 'triggers',
		'graphs' => 'graphs',
		'applications' => 'applications',
		'screens' => 'screens',
		'discoveries' => 'discoveries'
	);
	if (!is_null($current)) {
		unset($elements[$current]);
	}

	$header_host_opt = array(
		'hostids' => $hostid,
		'output' => array('hostid', 'name', 'status', 'proxy_hostid', 'available'),
		'templated_hosts' => true
	);
	if (isset($elements['items'])) {
		$header_host_opt['selectItems'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['triggers'])) {
		$header_host_opt['selectTriggers'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['graphs'])) {
		$header_host_opt['selectGraphs'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['applications'])) {
		$header_host_opt['selectApplications'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['screens'])) {
		$header_host_opt['selectScreens'] = API_OUTPUT_COUNT;
	}
	if (isset($elements['discoveries'])) {
		$header_host_opt['selectDiscoveries'] = API_OUTPUT_COUNT;
	}
	$header_hosts = API::Host()->get($header_host_opt);
	$header_host = reset($header_hosts);

	$list = new CList(null, 'objectlist');

	if ($header_host['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array('&laquo; ', new CLink(_('Template list'), 'templates.php?templateid='.$header_host['hostid'].url_param('groupid'))));
	}
	else {
		$list->addItem(array('&laquo; ', new CLink(_('Host list'), 'hosts.php?hostid='.$header_host['hostid'].url_param('groupid'))));
	}

	$description = '';
	if ($header_host['proxy_hostid']) {
		$proxy = get_host_by_hostid($header_host['proxy_hostid']);
		$description .= $proxy['host'].':';
	}
	$description .= $header_host['name'];

	if ($header_host['status'] == HOST_STATUS_TEMPLATE) {
		$list->addItem(array(bold(_('Template').': '), new CLink($description, 'templates.php?form=update&templateid='.$header_host['hostid'])));
	}
	else {
		switch ($header_host['status']) {
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

		if ($header_host['available'] == HOST_AVAILABLE_TRUE) {
			$available = new CSpan(_('Available'), 'off');
		}
		elseif ($header_host['available'] == HOST_AVAILABLE_FALSE) {
			$available = new CSpan(_('Not available'), 'on');
		}
		elseif ($header_host['available'] == HOST_AVAILABLE_UNKNOWN) {
			$available = new CSpan(_('Unknown'), 'unknown');
		}

		$list->addItem(array(bold(_('Host').': '), new CLink($description, 'hosts.php?form=update&hostid='.$header_host['hostid'])));
		$list->addItem($status);
		$list->addItem(array(_('Availability').': ', $available));
	}

	if (isset($elements['applications'])) {
		$list->addItem(array(new CLink(_('Applications'), 'applications.php?hostid='.$header_host['hostid']),
			' ('.$header_host['applications'].')'));
	}
	if (isset($elements['items'])) {
		$list->addItem(array(new CLink(_('Items'), 'items.php?hostid='.$header_host['hostid']),
			' ('.$header_host['items'].')'));
	}
	if (isset($elements['triggers'])) {
		$list->addItem(array(new CLink(_('Triggers'), 'triggers.php?hostid='.$header_host['hostid']),
			' ('.$header_host['triggers'] . ')'));
	}
	if (isset($elements['graphs'])) {
		$list->addItem(array(new CLink(_('Graphs'), 'graphs.php?hostid='.$header_host['hostid']),
			' ('.$header_host['graphs'] . ')'));
	}
	if (isset($elements['discoveries'])) {
		$list->addItem(array(new CLink(_('Discovery'), 'host_discovery.php?hostid='.$header_host['hostid']),
			' ('.$header_host['discoveries'].')'));
	}
	if ($header_host['status'] == HOST_STATUS_TEMPLATE && isset($elements['screens'])) {
		$list->addItem(array(new CLink(_('Screens'), 'screenconf.php?templateid='.$header_host['hostid']),
			' ('.$header_host['screens'] . ')'));
	}

	return new CDiv($list, 'objectgroup top ui-widget-content ui-corner-all');
}

function makeFormFooter($main, $other = array()) {
	$mainBttns = new CDiv();
	foreach ($main as $bttn) {
		$bttn->addClass('main');
		$bttn->useJQueryStyle();
		$mainBttns->addItem($bttn);
	}
	$otherBttns = new CDiv($other);
	$otherBttns->useJQueryStyle();

	if (empty($other)) {
		$space = new CDiv($mainBttns, 'dt right');
	}
	else {
		$space = new CDiv($mainBttns, 'dt floatleft right');
	}
	$buttons = new CDiv(array($otherBttns), 'dd');

	return new CDiv(new CDiv(array($space, $buttons), 'formrow'), 'objectgroup footer min-width ui-widget-content ui-corner-all');
}
?>
