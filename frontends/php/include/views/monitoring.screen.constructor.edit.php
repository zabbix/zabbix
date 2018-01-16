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


$action = 'screenedit.php?form=update&screenid='.getRequest('screenid');
if (isset($_REQUEST['screenitemid'])) {
	$action .= '&screenitemid='.getRequest('screenitemid');
}

$form = (new CForm('post', $action))
	->setName('screen_item_form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('screenid', getRequest('screenid'));

if ($data['screen']['templateid'] != 0) {
	$form->addVar('templateid', $data['screen']['templateid']);
}

if (hasRequest('screenitemid')) {
	$form->addVar('screenitemid', getRequest('screenitemid'));
	$screenItems = zbx_toHash($this->data['screen']['screenitems'], 'screenitemid');
}
else {
	$form
		->addVar('x', getRequest('x'))
		->addVar('y', getRequest('y'));
}

if (isset($_REQUEST['screenitemid']) && !isset($_REQUEST['form_refresh'])) {
	$screenItem		= $screenItems[$_REQUEST['screenitemid']];
	$resourceType	= $screenItem['resourcetype'];
	$resourceId		= $screenItem['resourceid'];
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
	$sortTriggers	= $screenItem['sort_triggers'];
	$application	= $screenItem['application'];
	$maxColumns		= $screenItem['max_columns'];
}
else {
	$resourceType	= getRequest('resourcetype', 0);
	$resourceId		= getRequest('resourceid', 0);
	$width			= getRequest('width', 500);
	$height			= getRequest('height', 100);
	$colspan		= getRequest('colspan', 1);
	$rowspan		= getRequest('rowspan', 1);
	$elements		= getRequest('elements', 25);
	$valign			= getRequest('valign', VALIGN_DEFAULT);
	$halign			= getRequest('halign', HALIGN_DEFAULT);
	$style			= getRequest('style', 0);
	$url			= getRequest('url', '');
	$dynamic		= getRequest('dynamic', SCREEN_SIMPLE_ITEM);
	$sortTriggers	= getRequest('sort_triggers', SCREEN_SORT_TRIGGERS_DATE_DESC);
	$application	= getRequest('application', '');
	$maxColumns		= getRequest('max_columns', 3);
}

// append resource types to form list
$screenResources = screen_resources();
if ($this->data['screen']['templateid']) {
	unset(
		$screenResources[SCREEN_RESOURCE_DATA_OVERVIEW], $screenResources[SCREEN_RESOURCE_ACTIONS],
		$screenResources[SCREEN_RESOURCE_EVENTS], $screenResources[SCREEN_RESOURCE_HOST_INFO],
		$screenResources[SCREEN_RESOURCE_MAP], $screenResources[SCREEN_RESOURCE_SCREEN],
		$screenResources[SCREEN_RESOURCE_SERVER_INFO], $screenResources[SCREEN_RESOURCE_HOSTGROUP_TRIGGERS],
		$screenResources[SCREEN_RESOURCE_HOST_TRIGGERS], $screenResources[SCREEN_RESOURCE_SYSTEM_STATUS],
		$screenResources[SCREEN_RESOURCE_TRIGGER_INFO], $screenResources[SCREEN_RESOURCE_TRIGGER_OVERVIEW]
	);
}

$screenFormList = (new CFormList())
	->addRow(_('Resource'), new CComboBox('resourcetype', $resourceType, 'submit()', $screenResources));

/*
 * Screen item: Graph
 */
if ($resourceType == SCREEN_RESOURCE_GRAPH) {
	$caption = '';
	$id = 0;

	$graphs = API::Graph()->get([
		'graphids' => $resourceId,
		'selectHosts' => ['hostid', 'name', 'status'],
		'output' => API_OUTPUT_EXTEND
	]);
	if (!empty($graphs)) {
		$id = $resourceId;
		$graph = reset($graphs);

		order_result($graph['hosts'], 'name');
		$graph['host'] = reset($graph['hosts']);

		$caption = $graph['host']['name'].NAME_DELIMITER.$graph['name'];
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'graphs',
					'srcfld1' => 'graphid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'templated_hosts' => '1',
					'only_hostid' => $data['screen']['templateid']
				]).');'
			);
	}
	else {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'graphs',
					'srcfld1' => 'graphid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'real_hosts' => '1',
					'with_graphs' => '1'
				]).');'
			);
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Graph'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$selectButton
	]);
}

/*
 * Screen item: Graph prototype
 */
elseif ($resourceType == SCREEN_RESOURCE_LLD_GRAPH) {
	$caption = '';
	$id = 0;

	$graphPrototypes = API::GraphPrototype()->get([
		'output' => ['name'],
		'graphids' => $resourceId,
		'selectHosts' => ['name']
	]);

	if ($graphPrototypes) {
		$id = $resourceId;
		$graphPrototype = reset($graphPrototypes);

		order_result($graphPrototype['hosts'], 'name');
		$graphPrototype['host'] = reset($graphPrototype['hosts']);

		$caption = $graphPrototype['host']['name'].NAME_DELIMITER.$graphPrototype['name'];
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'graph_prototypes',
					'srcfld1' => 'graphid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'templated_hosts' => '1',
					'only_hostid' => $data['screen']['templateid']
				]).');'
			);
	}
	else {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'graph_prototypes',
					'srcfld1' => 'graphid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'real_hosts' => '1'
				]).');'
			);
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Graph prototype'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$selectButton
	]);

	$screenFormList->addRow(_('Max columns'),
		(new CNumericBox('max_columns', $maxColumns, 3, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
}

/*
 * Screen item: Simple graph
 */
elseif ($resourceType == SCREEN_RESOURCE_SIMPLE_GRAPH) {
	$caption = '';
	$id = 0;

	$items = API::Item()->get([
		'itemids' => $resourceId,
		'selectHosts' => ['name'],
		'output' => ['itemid', 'hostid', 'key_', 'name']
	]);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);

		$id = $resourceId;
		$item = reset($items);
		$item['host'] = reset($item['hosts']);

		$caption = $item['host']['name'].NAME_DELIMITER.$item['name_expanded'];
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'templated_hosts' => '1',
					'only_hostid' => $data['screen']['templateid'],
					'numeric' => '1'
				]).');'
			);
	}
	else {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'real_hosts' => '1',
					'with_simple_graph_items' => '1',
					'numeric' => '1'
				]).');'
			);
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Item'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$selectButton
	]);
}

/*
 * Screen item: Simple graph prototype
 */
elseif ($resourceType == SCREEN_RESOURCE_LLD_SIMPLE_GRAPH) {
	$caption = '';
	$id = 0;

	$items = API::ItemPrototype()->get([
		'output' => ['hostid', 'key_', 'name'],
		'itemids' => $resourceId,
		'selectHosts' => ['name']
	]);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);

		$id = $resourceId;
		$item = reset($items);
		$item['host'] = reset($item['hosts']);

		$caption = $item['host']['name'].NAME_DELIMITER.$item['name_expanded'];
	}

	if ($this->data['screen']['templateid']) {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'item_prototypes',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'templated_hosts' => '1',
					'only_hostid' => $data['screen']['templateid'],
					'numeric' => '1'
				]).');'
			);
	}
	else {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'item_prototypes',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'real_hosts' => '1',
					'with_discovery_rule' => '1',
					'items' => '1',
					'numeric' => '1'
				]).');'
			);
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Item prototype'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$selectButton
	]);

	$screenFormList->addRow(_('Max columns'),
		(new CNumericBox('max_columns', $maxColumns, 3, false, false, false))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
}

/*
 * Screen item: Map
 */
elseif ($resourceType == SCREEN_RESOURCE_MAP) {
	$caption = '';
	$id = 0;

	$maps = API::Map()->get([
		'sysmapids' => $resourceId,
		'output' => API_OUTPUT_EXTEND
	]);
	if (!empty($maps)) {
		$id = $resourceId;
		$map = reset($maps);
		$caption = $map['name'];
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Map'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'sysmaps',
					'srcfld1' => 'sysmapid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption'
				]).');'
			)
	]);
}

/*
 * Screen item: Plain text
 */
elseif ($resourceType == SCREEN_RESOURCE_PLAIN_TEXT) {
	$caption = '';
	$id = 0;

	$items = API::Item()->get([
		'itemids' => $resourceId,
		'selectHosts' => ['name'],
		'output' => ['itemid', 'hostid', 'key_', 'name']
	]);

	if ($items) {
		$items = CMacrosResolverHelper::resolveItemNames($items);

		$id = $resourceId;
		$item = reset($items);
		$item['host'] = reset($item['hosts']);
		$caption = $item['host']['name'].NAME_DELIMITER.$item['name_expanded'];
	}

	$form->addVar('resourceid', $id);

	if ($this->data['screen']['templateid']) {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'templated_hosts' => '1',
					'only_hostid' => $data['screen']['templateid']
				]).');'
			);
	}
	else {
		$selectButton = (new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'items',
					'srcfld1' => 'itemid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'real_hosts' => '1'
				]).');'
			);
	}

	$screenFormList
		->addRow(_('Item'), [
			(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$selectButton
		])
		->addRow(_('Show lines'),
			(new CNumericBox('elements', $elements, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		)
		->addRow(_('Show text as HTML'), (new CCheckBox('style'))->setChecked($style == 1));
}
elseif (in_array($resourceType, [SCREEN_RESOURCE_HOSTGROUP_TRIGGERS, SCREEN_RESOURCE_HOST_TRIGGERS])) {
	// Screen item: Triggers

	$data = [];

	if ($resourceType == SCREEN_RESOURCE_HOSTGROUP_TRIGGERS) {
		if ($resourceId > 0) {
			$data = API::HostGroup()->get([
				'groupids' => $resourceId,
				'output' => ['groupid', 'name']
			]);

			if ($data) {
				$data = reset($data);
			}
		}

		$screenFormList->addRow(_('Group'),
			(new CMultiSelect([
				'name' => 'resourceid',
				'objectName' => 'hostGroup',
				'data' => $data ? [['id' => $data['groupid'], 'name' => $data['name']]] : null,
				'defaultValue' => 0,
				'selectedLimit' => 1,
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'resourceid',
						'srcfld1' => 'groupid'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);
	}
	else {
		if ($resourceId > 0) {
			$data = API::Host()->get([
				'hostids' => $resourceId,
				'output' => ['hostid', 'name']
			]);

			if ($data) {
				$data = reset($data);
			}
		}

		$screenFormList->addRow(_('Host'),
			(new CMultiSelect([
				'name' => 'resourceid',
				'objectName' => 'hosts',
				'data' => $data ? [['id' => $data['hostid'], 'name' => $data['name']]] : null,
				'defaultValue' => 0,
				'selectedLimit' => 1,
				'popup' => [
					'parameters' => [
						'srctbl' => 'hosts',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'resourceid',
						'srcfld1' => 'hostid'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);
	}

	$screenFormList->addRow(_('Show lines'),
		(new CNumericBox('elements', $elements, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$screenFormList->addRow(
		_('Sort triggers by'),
		new CComboBox('sort_triggers', $sortTriggers, null, [
			SCREEN_SORT_TRIGGERS_DATE_DESC => _('Last change (descending)'),
			SCREEN_SORT_TRIGGERS_SEVERITY_DESC => _('Severity (descending)'),
			SCREEN_SORT_TRIGGERS_HOST_NAME_ASC => _('Host (ascending)')
		])
	);
}

/*
 * Screen item: Action log
 */
elseif ($resourceType == SCREEN_RESOURCE_ACTIONS) {
	$screenFormList->addRow(_('Show lines'),
		(new CNumericBox('elements', $elements, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$screenFormList->addRow(
		_('Sort entries by'),
		new CComboBox('sort_triggers', $sortTriggers, null, [
			SCREEN_SORT_TRIGGERS_TIME_DESC => _('Time (descending)'),
			SCREEN_SORT_TRIGGERS_TIME_ASC => _('Time (ascending)'),
			SCREEN_SORT_TRIGGERS_TYPE_DESC => _('Type (descending)'),
			SCREEN_SORT_TRIGGERS_TYPE_ASC => _('Type (ascending)'),
			SCREEN_SORT_TRIGGERS_STATUS_DESC => _('Status (descending)'),
			SCREEN_SORT_TRIGGERS_STATUS_ASC => _('Status (ascending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_DESC => _('Recipient (descending)'),
			SCREEN_SORT_TRIGGERS_RECIPIENT_ASC => _('Recipient (ascending)')
		])
	);
}

/*
 * Screen item: History of events
 */
elseif ($resourceType == SCREEN_RESOURCE_EVENTS) {
	$screenFormList->addRow(_('Show lines'),
		(new CNumericBox('elements', $elements, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$form->addVar('resourceid', 0);
}

/*
 * Screen item: Overviews
 */
elseif (in_array($resourceType, [SCREEN_RESOURCE_TRIGGER_OVERVIEW, SCREEN_RESOURCE_DATA_OVERVIEW])) {
	$data = [];

	if ($resourceId > 0) {
		$data = API::HostGroup()->get([
			'groupids' => $resourceId,
			'output' => ['groupid', 'name']
		]);

		if ($data) {
			$data = reset($data);
		}
	}

	$screenFormList->addRow(_('Group'),
		(new CMultiSelect([
			'name' => 'resourceid',
			'objectName' => 'hostGroup',
			'data' => $data ? [['id' => $data['groupid'], 'name' => $data['name']]] : null,
			'selectedLimit' => 1,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'srcfld1' => 'groupid'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$screenFormList->addRow(_('Application'),
		(new CTextBox('application', $application, false, 255))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

/*
 * Screen item: Screens
 */
elseif ($resourceType == SCREEN_RESOURCE_SCREEN) {
	$caption = '';
	$id = 0;

	if ($resourceId > 0) {
		$db_screens = DBselect('SELECT s.screenid,s.name FROM screens s WHERE s.screenid='.zbx_dbstr($resourceId));

		while ($row = DBfetch($db_screens)) {
			$screen = API::Screen()->get([
				'screenids' => $row['screenid'],
				'output' => ['screenid']
			]);
			if (empty($screen)) {
				continue;
			}
			if (check_screen_recursion($_REQUEST['screenid'], $row['screenid'])) {
				continue;
			}

			$caption = $row['name'];
			$id = $resourceId;
		}
	}

	$form->addVar('resourceid', $id);
	$screenFormList->addRow(_('Screen'), [
		(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CButton('select', _('Select')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick('return PopUp("popup.generic",'.
				CJs::encodeJson([
					'srctbl' => 'screens2',
					'srcfld1' => 'screenid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'dstfld2' => 'caption',
					'screenid' => getRequest('screenid')
				]).');'
			)
	]);
}
elseif ($resourceType == SCREEN_RESOURCE_HOST_INFO || $resourceType == SCREEN_RESOURCE_TRIGGER_INFO) {
	// Screen item: Host info

	$data = [];

	if ($resourceId > 0) {
		$data = API::HostGroup()->get([
			'groupids' => $resourceId,
			'output' => ['groupid', 'name']
		]);

		if ($data) {
			$data = reset($data);
		}
	}

	$screenFormList->addRow(_('Group'),
		(new CMultiSelect([
			'name' => 'resourceid',
			'objectName' => 'hostGroup',
			'data' => $data ? [['id' => $data['groupid'], 'name' => $data['name']]] : null,
			'defaultValue' => 0,
			'selectedLimit' => 1,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'resourceid',
					'srcfld1' => 'groupid'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

/*
 * Screen item: Clock
 */
elseif ($resourceType == SCREEN_RESOURCE_CLOCK) {
	$caption = getRequest('caption', '');

	if (zbx_empty($caption) && TIME_TYPE_HOST == $style && $resourceId > 0) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_', 'name'],
			'selectHosts' => ['name'],
			'itemids' => $resourceId,
			'webitems' => true
		]);

		if ($items) {
			$items = CMacrosResolverHelper::resolveItemNames($items);

			$item = reset($items);
			$host = reset($item['hosts']);
			$caption = $host['name'].NAME_DELIMITER.$item['name_expanded'];
		}
	}

	$screenFormList->addRow(_('Time type'), new CComboBox('style', $style, 'submit()', [
		TIME_TYPE_LOCAL => _('Local time'),
		TIME_TYPE_SERVER => _('Server time'),
		TIME_TYPE_HOST => _('Host time')
	]));

	if (TIME_TYPE_HOST == $style) {
		$form->addVar('resourceid', $resourceId);

		if ($this->data['screen']['templateid']) {
			$selectButton = (new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'resourceid',
						'dstfld2' => 'caption',
						'templated_hosts' => '1',
						'only_hostid' => $data['screen']['templateid']
					]).');'
				);
		}
		else {
			$selectButton = (new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'items',
						'srcfld1' => 'itemid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'resourceid',
						'dstfld2' => 'caption',
						'real_hosts' => '1'
					]).');'
				);
		}
		$screenFormList->addRow(_('Item'), [
			(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$selectButton
		]);
	}
	else {
		$form->addVar('caption', $caption);
	}
}

/*
 * Append common fields
 */
if (in_array($resourceType, [SCREEN_RESOURCE_HOST_INFO, SCREEN_RESOURCE_TRIGGER_INFO])) {
	$screenFormList->addRow(_('Style'),
		(new CRadioButtonList('style', (int) $style))
			->addValue(_('Horizontal'), STYLE_HORIZONTAL)
			->addValue(_('Vertical'), STYLE_VERTICAL)
			->setModern(true)
	);
}
elseif (in_array($resourceType, [SCREEN_RESOURCE_TRIGGER_OVERVIEW, SCREEN_RESOURCE_DATA_OVERVIEW])) {
	$screenFormList->addRow(_('Hosts location'),
		(new CRadioButtonList('style', (int) $style))
			->addValue(_('Left'), STYLE_LEFT)
			->addValue(_('Top'), STYLE_TOP)
			->setModern(true)
	);
}
elseif ($resourceType != SCREEN_RESOURCE_CLOCK) {
	$form->addVar('style', 0);
}

if (in_array($resourceType, [SCREEN_RESOURCE_URL])) {
	$screenFormList->addRow(_('URL'), (new CTextBox('url', $url))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
}
else {
	$form->addVar('url', '');
}

$resourcesWithWidthAndHeight = [
	SCREEN_RESOURCE_GRAPH,
	SCREEN_RESOURCE_SIMPLE_GRAPH,
	SCREEN_RESOURCE_CLOCK,
	SCREEN_RESOURCE_URL,
	SCREEN_RESOURCE_LLD_GRAPH,
	SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
];
if (in_array($resourceType, $resourcesWithWidthAndHeight)) {
	$screenFormList->addRow(_('Width'),
		(new CNumericBox('width', $width, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$screenFormList->addRow(_('Height'),
		(new CNumericBox('height', $height, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
}
else {
	$form
		->addVar('width', 500)
		->addVar('height', 100);
}

$resourcesWithHAlign = [
	SCREEN_RESOURCE_GRAPH,
	SCREEN_RESOURCE_SIMPLE_GRAPH,
	SCREEN_RESOURCE_MAP,
	SCREEN_RESOURCE_CLOCK,
	SCREEN_RESOURCE_URL,
	SCREEN_RESOURCE_LLD_GRAPH,
	SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
];
if (in_array($resourceType, $resourcesWithHAlign)) {
	$screenFormList->addRow(_('Horizontal align'),
		(new CRadioButtonList('halign', (int) $halign))
			->addValue(_('Left'), HALIGN_LEFT)
			->addValue(_('Center'), HALIGN_CENTER)
			->addValue(_('Right'), HALIGN_RIGHT)
			->setModern(true)
	);
}
else {
	$form->addVar('halign', 0);
}

$screenFormList->addRow(_('Vertical align'),
	(new CRadioButtonList('valign', (int) $valign))
		->addValue(_('Top'), VALIGN_TOP)
		->addValue(_('Middle'), VALIGN_MIDDLE)
		->addValue(_('Bottom'), VALIGN_BOTTOM)
		->setModern(true)
);
$screenFormList->addRow(_('Column span'),
	(new CNumericBox('colspan', $colspan, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
);
$screenFormList->addRow(_('Row span'),
	(new CNumericBox('rowspan', $rowspan, 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
);

// dynamic addon
$resourcesWithDynamic = [
	SCREEN_RESOURCE_GRAPH,
	SCREEN_RESOURCE_SIMPLE_GRAPH,
	SCREEN_RESOURCE_PLAIN_TEXT,
	SCREEN_RESOURCE_URL,
	SCREEN_RESOURCE_LLD_GRAPH,
	SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
];
if ($this->data['screen']['templateid'] == 0 && in_array($resourceType, $resourcesWithDynamic)) {
	$screenFormList->addRow(_('Dynamic item'), (new CCheckBox('dynamic'))->setChecked($dynamic == 1));
}

// append list to form
$form->addItem($screenFormList);

// append buttons to form
if (isset($_REQUEST['screenitemid'])) {
	$form->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete(null, url_params(['form', 'screenid', 'templateid', 'screenitemid'])),
			new CButtonCancel(url_params(['screenid', 'templateid']))
		]
	));
}
else {
	$form->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_params(['screenid', 'templateid']))]
	));
}

return $form;
