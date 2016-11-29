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


$action = 'screenedit.php?form=update&screenid='.get_request('screenid');
if (isset($_REQUEST['screenitemid'])) {
	$action .= '&screenitemid='.get_request('screenitemid');
}

// create screen form
$screenForm = new CForm('post', $action);
$screenForm->setName('screen_item_form');

// create screen form list
$screenFormList = new CFormList('screenFormList');
$screenFormList->addVar('screenid', $_REQUEST['screenid']);

if (isset($_REQUEST['screenitemid'])) {
	$screenFormList->addVar('screenitemid', $_REQUEST['screenitemid']);
	$screenItems = zbx_toHash($this->data['screen']['screenitems'], 'screenitemid');
}
else {
	$screenFormList->addVar('x', $_REQUEST['x']);
	$screenFormList->addVar('y', $_REQUEST['y']);
}

if (isset($_REQUEST['screenitemid']) && !isset($_REQUEST['form_refresh'])) {
	$screenItem		= $screenItems[$_REQUEST['screenitemid']];
	$resourcetype	= $screenItem['resourcetype'];
	$resourceid		= $screenItem['resourceid'];
	$width			= $screenItem['width'];
	$height			= $screenItem['height'];
	$colspan		= $screenItem['colspan'];
	$rowspan		= $screenItem['rowspan'];
	$elements		= $screenItem['elements'];
	$valign			= $screenItem['valign'];
	$halign			= $screenItem['halign'];
	$style			= $screenItem['style'];
	$url			= $screenItem['url'];
	$dynamic		= $screenItem['dynamic'];
	$sort_triggers	= $screenItem['sort_triggers'];
}
else {
	$resourcetype	= get_request('resourcetype', 0);
	$resourceid		= get_request('resourceid', 0);
	$width			= get_request('width', 500);
	$height			= get_request('height', 100);
	$colspan		= get_request('colspan', 1);
	$rowspan		= get_request('rowspan', 1);
	$elements		= get_request('elements', 25);
	$valign			= get_request('valign', VALIGN_DEFAULT);
	$halign			= get_request('halign', HALIGN_DEFAULT);
	$style			= get_request('style', 0);
	$url			= get_request('url', '');
	$dynamic		= get_request('dynamic', SCREEN_SIMPLE_ITEM);
	$sort_triggers	= get_request('sort_triggers', SCREEN_SORT_TRIGGERS_DATE_DESC);
}

// append resource types to form list
$resourceTypeComboBox = new CComboBox('resourcetype', $resourcetype, 'submit()');
$screenResources = screen_resources();
if ($this->data['screen']['templateid']) {
	unset(
		$screenResources[SCREEN_RESOURCE_DATA_OVERVIEW], $screenResources[SCREEN_RESOURCE_ACTIONS],
		$screenResources[SCREEN_RESOURCE_EVENTS], $screenResources[SCREEN_RESOURCE_HOSTS_INFO],
		$screenResources[SCREEN_RESOURCE_MAP], $screenResources[SCREEN_RESOURCE_SCREEN],
		$screenResources[SCREEN_RESOURCE_SERVER_INFO], $screenResources[SCREEN_RESOURCE_HOSTGROUP_TRIGGERS],
		$screenResources[SCREEN_RESOURCE_HOST_TRIGGERS], $screenResources[SCREEN_RESOURCE_SYSTEM_STATUS],
		$screenResources[SCREEN_RESOURCE_TRIGGERS_INFO], $screenResources[SCREEN_RESOURCE_TRIGGERS_OVERVIEW]
	);
}
$resourceTypeComboBox->addItems($screenResources);
$screenFormList->addRow(_('Resource'), $resourceTypeComboBox);

/*
 * Graph
 */
if ($resourcetype == SCREEN_RESOURCE_GRAPH) {
	$caption = '';
	$id = 0;

	$graphs = API::Graph()->get(array(
		'graphids' => $resourceid,
		'selectHosts' => array('hostid', 'name', 'status'),
		'output' => API_OUTPUT_EXTEND
	));
	if (!empty($graphs)) {
		$id = $resourceid;
		$graph = reset($graphs);

		order_result($graph['hosts'], 'name');
		$graph['host'] = reset($graph['hosts']);

		$caption = ($graph['host']['status'] != HOST_STATUS_TEMPLATE)
			? $graph['host']['name'].':'.$graph['name']
			: $graph['name'];

		$nodeName = get_node_name_by_elid($graph['host']['hostid']);
		if (!zbx_empty($nodeName)) {
			$caption = '('.$nodeName.') '.$caption;
		}
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=graphs&srcfld1=graphid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&templated_hosts=1&only_hostid='.$this->data['screen']['templateid'].
				'&writeonly=1&simpleName=1", 800, 450);',
			'formlist'
		);
	}
	else {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=graphs&srcfld1=graphid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&real_hosts=1&with_graphs=1&writeonly=1", 800, 450);',
			'formlist'
		);
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Graph name'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		$selectButton
	));
}

/*
 * Simple graph
 */
elseif ($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH) {
	$caption = '';
	$id = 0;

	$items = API::Item()->get(array(
		'itemids' => $resourceid,
		'selectHosts' => array('hostid', 'name', 'status'),
		'output' => API_OUTPUT_EXTEND
	));
	if (!empty($items)) {
		$id = $resourceid;
		$item = reset($items);
		$item['host'] = reset($item['hosts']);

		$caption = $item['host']['status'] != HOST_STATUS_TEMPLATE
			? $item['host']['name'].':'.itemName($item)
			: itemName($item);

		$nodeName = get_node_name_by_elid($item['itemid']);
		if (!zbx_empty($nodeName)) {
			$caption = '('.$nodeName.') '.$caption;
		}
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=items&srcfld1=itemid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&templated_hosts=1&only_hostid='.$this->data['screen']['templateid'].
				'&templated=1&writeonly=1&numeric=1", 800, 450);', 'formlist'
		);
	}
	else {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=items&srcfld1=itemid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&real_hosts=1&with_simple_graph_items=1&writeonly=1&templated=0&numeric=1", 800, 450);',
			'formlist'
		);
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Parameter'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		$selectButton
	));
}

/*
 * Map
 */
elseif ($resourcetype == SCREEN_RESOURCE_MAP) {
	$caption = '';
	$id = 0;

	$maps = API::Map()->get(array(
		'sysmapids' => $resourceid,
		'output' => API_OUTPUT_EXTEND
	));
	if (!empty($maps)) {
		$id = $resourceid;
		$map = reset($maps);
		$caption = $map['name'];
		$nodeName = get_node_name_by_elid($map['sysmapid']);
		if (!zbx_empty($nodeName)) {
			$caption = '('.$nodeName.') '.$caption;
		}
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Parameter'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&writeonly=1", 400, 450);',
			'formlist'
		)
	));
}

/*
 * Plain text
 */
elseif ($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT) {
	$caption = '';
	$id = 0;

	$items = API::Item()->get(array(
		'itemids' => $resourceid,
		'selectHosts' => array('hostid', 'name'),
		'output' => API_OUTPUT_EXTEND
	));
	if (!empty($items)) {
		$id = $resourceid;
		$item = reset($items);
		$item['host'] = reset($item['hosts']);
		$caption = $item['host']['name'].':'.itemName($item);

		$nodeName = get_node_name_by_elid($item['itemid']);
		if (!zbx_empty($nodeName)) {
			$caption = '('.$nodeName.') '.$caption;
		}
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=items&srcfld1=itemid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&templated_hosts=1&only_hostid='.$this->data['screen']['templateid'].
				'&writeonly=1", 800, 450);',
			'formlist'
		);
	}
	else {
		$selectButton = new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=items&srcfld1=itemid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&real_hosts=1&writeonly=1&templated=0", 800, 450);',
			'formlist'
		);
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Parameter'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		$selectButton
	));
	$screenFormList->addRow(_('Show lines'), new CNumericBox('elements', $elements, 2));
	$screenFormList->addRow(_('Show text as HTML'), new CCheckBox('style', $style, null, 1));
}

/*
 * Status of triggers
 */
else if(in_array($resourcetype, array(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS, SCREEN_RESOURCE_HOST_TRIGGERS))) {
	$caption = '';
	$id = 0;

	if (SCREEN_RESOURCE_HOSTGROUP_TRIGGERS == $resourcetype) {
		if ($resourceid > 0) {
			$groups = API::HostGroup()->get(array(
				'groupids' => $resourceid,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));
			foreach ($groups as $group) {
				$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
				$id = $resourceid;
			}
		}

		$screenFormList->addVar('resourceid', $id);
		$screenFormList->addRow(_('Group'), array(
			new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
			new CButton('select', _('Select'),
				'javascript: return PopUp("popup.php?srctbl=host_group&srcfld1=groupid&srcfld2=name'.
					'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
					'&writeonly=1", 800, 450);',
				'formlist'
			)
		));
	}
	else {
		if ($resourceid > 0) {
			$hosts = API::Host()->get(array(
				'hostids' => $resourceid,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));
			foreach ($hosts as $host) {
				$caption = get_node_name_by_elid($host['hostid'], true, ':').$host['name'];
				$id = $resourceid;
			}
		}

		$screenFormList->addVar('resourceid', $id);
		$screenFormList->addRow(_('Host'), array(
			new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
			new CButton('select', _('Select'),
				'javascript: return PopUp("popup.php?srctbl=hosts&srcfld1=hostid&srcfld2=name'.
					'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
					'&real_hosts=1&writeonly=1", 800, 450);',
				'formlist'
			)
		));
	}

	$screenFormList->addRow(_('Show lines'), new CNumericBox('elements', $elements, 2));
	$screenFormList->addRow(
		_('Sort triggers by'),
		new CComboBox('sort_triggers', $sort_triggers, null, array(
			SCREEN_SORT_TRIGGERS_DATE_DESC => _('Last change (descending)'),
			SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity (descending)'),
			SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host (ascending)')
		))
	);
}

/*
 * History of actions
 */
elseif ($resourcetype == SCREEN_RESOURCE_ACTIONS) {
	$screenFormList->addRow(_('Show lines'), new CNumericBox('elements', $elements, 2));
	$screenFormList->addRow(
		_('Sort triggers by'),
		new CComboBox('sort_triggers', $sort_triggers, null, array(
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time (descending)'),
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time (ascending)'),
			SCREEN_SORT_TRIGGERS_TYPE_DESC => _('Type (descending)'),
			SCREEN_SORT_TRIGGERS_TYPE_ASC => _('Type (ascending)'),
			SCREEN_SORT_TRIGGERS_STATUS_DESC => _('Status (descending)'),
			SCREEN_SORT_TRIGGERS_STATUS_ASC => _('Status (ascending)'),
			SCREEN_SORT_TRIGGERS_RETRIES_LEFT_DESC => _('Retries left (descending)'),
			SCREEN_SORT_TRIGGERS_RETRIES_LEFT_ASC => _('Retries left (ascending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_DESC => _('Recipient (descending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_ASC => _('Recipient (ascending)')
		))
	);
	$screenFormList->addVar('resourceid', 0);
}

/*
 * History of events
 */
elseif ($resourcetype == SCREEN_RESOURCE_EVENTS) {
	$screenFormList->addRow(_('Show lines'), new CNumericBox('elements', $elements, 2));
	$screenFormList->addVar('resourceid', 0);
}

/*
 * Overviews
 */
elseif (in_array($resourcetype, array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW, SCREEN_RESOURCE_DATA_OVERVIEW))) {
	$caption = '';
	$id = 0;

	if ($resourceid > 0) {
		$groups = API::HostGroup()->get(array(
			'groupids' => $resourceid,
			'output' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		foreach ($groups as $group) {
			$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
			$id = $resourceid;
		}
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Group'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=overview&srcfld1=groupid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&writeonly=1", 800, 450);',
			'formlist'
		)
	));
}

/*
 * Screens
 */
elseif ($resourcetype == SCREEN_RESOURCE_SCREEN) {
	$caption = '';
	$id = 0;

	if ($resourceid > 0) {
		$db_screens = DBselect(
			'SELECT DISTINCT n.name AS node_name,s.screenid,s.name'.
			' FROM screens s'.
				' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.screenid').
			' WHERE s.screenid='.zbx_dbstr($resourceid)
		);
		while ($row = DBfetch($db_screens)) {
			$screen = API::Screen()->get(array(
				'screenids' => $row['screenid'],
				'output' => API_OUTPUT_SHORTEN
			));
			if (empty($screen)) {
				continue;
			}
			if (check_screen_recursion($_REQUEST['screenid'], $row['screenid'])) {
				continue;
			}

			$row['node_name'] = !empty($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$caption = $row['node_name'].$row['name'];
			$id = $resourceid;
		}
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Parameter'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=screens2&srcfld1=screenid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&writeonly=1&screenid='.$_REQUEST['screenid'].'", 800, 450);',
			'formlist'
		)
	));
}

/*
 * Hosts info
 */
elseif ($resourcetype == SCREEN_RESOURCE_HOSTS_INFO || $resourcetype == SCREEN_RESOURCE_TRIGGERS_INFO) {
	$caption = '';
	$id = 0;

	if (remove_nodes_from_id($resourceid) > 0) {
		$groups = API::HostGroup()->get(array(
			'groupids' => $resourceid,
			'nodeids' => get_current_nodeid(true),
			'output' => array('name'),
			'preservekeys' => true
		));
		if ($group = reset($groups)) {
			$caption = get_node_name_by_elid($resourceid, true, ': ').$group['name'];
			$id = $resourceid;
		}
	}
	elseif (remove_nodes_from_id($resourceid) == 0) {
		if ($nodeName = get_node_name_by_elid($resourceid, true, ': ')) {
			$caption = $nodeName._('- all groups -');
			$id = $resourceid;
		}
	}

	$screenFormList->addVar('resourceid', $id);
	$screenFormList->addRow(_('Group'), array(
		new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('select', _('Select'),
			'javascript: return PopUp("popup.php?srctbl=host_group_scr&srcfld1=groupid&srcfld2=name'.
				'&dstfrm='.$screenForm->getName().'&dstfld1=resourceid&dstfld2=caption'.
				'&writeonly=1", 480, 450);',
			'formlist'
		)
	));
}

/*
 * Clock
 */
elseif ($resourcetype == SCREEN_RESOURCE_CLOCK) {
	$caption = get_request('caption', '');
	if (zbx_empty($caption) && TIME_TYPE_HOST == $style && $resourceid > 0) {
		$items = API::Item()->get(array(
			'itemids' => $resourceid,
			'selectHosts' => array('name'),
			'output' => API_OUTPUT_EXTEND
		));
		$item = reset($items);
		$host = reset($item['hosts']);
		$caption = $host['name'].':'.$item['name'];
	}

	$screenFormList->addVar('resourceid', $resourceid);

	$styleComboBox = new CComboBox('style', $style, 'javascript: submit();');
	$styleComboBox->addItem(TIME_TYPE_LOCAL, _('Local time'));
	$styleComboBox->addItem(TIME_TYPE_SERVER, _('Server time'));
	$styleComboBox->addItem(TIME_TYPE_HOST, _('Host time'));
	$screenFormList->addRow(_('Time type'), $styleComboBox);

	if (TIME_TYPE_HOST == $style) {
		if ($this->data['screen']['templateid']) {
			$selectButton = new CButton('select', _('Select'),
				"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$screenForm->getName().
					'&dstfld1=resourceid&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&templated_hosts=1'.
					'&only_hostid='.$this->data['screen']['templateid']."', 800, 450);", 'formlist'
			);
		}
		else {
			$selectButton = new CButton('select', _('Select'),
				"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$screenForm->getName().'&dstfld1=resourceid'.
					"&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&real_hosts=1', 800, 450);", 'formlist'
			);
		}
		$screenFormList->addRow(_('Parameter'), array(
			new CTextBox('caption', $caption, ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
			$selectButton
		));
	}
	else {
		$screenFormList->addVar('caption', $caption);
	}
}
else {
	$screenFormList->addVar('resourceid', 0);
}

if (in_array($resourcetype, array(SCREEN_RESOURCE_HOSTS_INFO, SCREEN_RESOURCE_TRIGGERS_INFO))) {
	$styleRadioButton = array(
		new CRadioButton('style', STYLE_HORISONTAL, null, 'style_'.STYLE_HORISONTAL, $style == STYLE_HORISONTAL),
		new CLabel(_('Horizontal'), 'style_'.STYLE_HORISONTAL),
		new CRadioButton('style', STYLE_VERTICAL, null, 'style_'.STYLE_VERTICAL, $style == STYLE_VERTICAL),
		new CLabel(_('Vertical'), 'style_'.STYLE_VERTICAL)
	);
	$screenFormList->addRow(_('Style'), new CDiv($styleRadioButton, 'jqueryinputset'));
}
elseif (in_array($resourcetype, array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))) {
	$styleRadioButton = array(
		new CRadioButton('style', STYLE_LEFT, null, 'style_'.STYLE_LEFT, $style == STYLE_LEFT),
		new CLabel(_('Left'), 'style_'.STYLE_LEFT),
		new CRadioButton('style', STYLE_TOP, null, 'style_'.STYLE_TOP, $style == STYLE_TOP),
		new CLabel(_('Top'), 'style_'.STYLE_TOP)
	);
	$screenFormList->addRow(_('Hosts location'), new CDiv($styleRadioButton, 'jqueryinputset'));
}
else {
	$screenFormList->addVar('style', 0);
}

if (in_array($resourcetype, array(SCREEN_RESOURCE_URL))) {
	$screenFormList->addRow(_('Url'), new CTextBox('url', $url, ZBX_TEXTBOX_STANDARD_SIZE));
}
else {
	$screenFormList->addVar('url', '');
}

if (in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_CLOCK, SCREEN_RESOURCE_URL))) {
	$screenFormList->addRow(_('Width'), new CNumericBox('width', $width, 5));
	$screenFormList->addRow(_('Height'), new CNumericBox('height', $height, 5));
}
else {
	$screenFormList->addVar('width', 500);
	$screenFormList->addVar('height', 100);
}

if (in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_MAP, SCREEN_RESOURCE_CLOCK, SCREEN_RESOURCE_URL))) {
	$hightAlignRadioButton = array(
		new CRadioButton('halign', HALIGN_LEFT, null, 'halign_'.HALIGN_LEFT, $halign == HALIGN_LEFT),
		new CLabel(_('Left'), 'halign_'.HALIGN_LEFT),
		new CRadioButton('halign', HALIGN_CENTER, null, 'halign_'.HALIGN_CENTER, $halign == HALIGN_CENTER),
		new CLabel(_('Center'), 'halign_'.HALIGN_CENTER),
		new CRadioButton('halign', HALIGN_RIGHT, null, 'halign_'.HALIGN_RIGHT, $halign == HALIGN_RIGHT),
		new CLabel(_('Right'), 'halign_'.HALIGN_RIGHT)
	);
	$screenFormList->addRow(_('Horizontal align'), new CDiv($hightAlignRadioButton, 'jqueryinputset'));
}
else {
	$screenFormList->addVar('halign', 0);
}

$verticalAlignRadioButton = array(
	new CRadioButton('valign', VALIGN_TOP, null, 'valign_'.VALIGN_TOP, $valign == VALIGN_TOP),
	new CLabel(_('Top'), 'valign_'.VALIGN_TOP),
	new CRadioButton('valign', VALIGN_MIDDLE, null, 'valign_'.VALIGN_MIDDLE, $valign == VALIGN_MIDDLE),
	new CLabel(_('Middle'), 'valign_'.VALIGN_MIDDLE),
	new CRadioButton('valign', VALIGN_BOTTOM, null, 'valign_'.VALIGN_BOTTOM, $valign == VALIGN_BOTTOM),
	new CLabel(_('Bottom'), 'valign_'.VALIGN_BOTTOM)
);
$screenFormList->addRow(_('Vertical align'), new CDiv($verticalAlignRadioButton, 'jqueryinputset'));
$screenFormList->addRow(_('Column span'), new CNumericBox('colspan', $colspan, 3));
$screenFormList->addRow(_('Row span'), new CNumericBox('rowspan', $rowspan, 3));

// dynamic addon
if ($this->data['screen']['templateid'] == 0 && in_array($resourcetype, array(SCREEN_RESOURCE_GRAPH, SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT))) {
	$screenFormList->addRow(_('Dynamic item'), new CCheckBox('dynamic', $dynamic, null, 1));
}

// append tabs to form
$screenTab = new CTabView();
$screenTab->setAttribute('style', 'text-align: left;');
$screenTab->addTab('screenTab', _('Screen cell configuration'), $screenFormList);
$screenForm->addItem($screenTab);

// append buttons to form
$buttons = array();
if (isset($_REQUEST['screenitemid'])) {
	array_push($buttons, new CButtonDelete(null, url_param('form').url_param('screenid').url_param('screenitemid')));
}
array_push($buttons, new CButtonCancel(url_param('screenid')));

$screenForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $buttons));

return $screenForm;
