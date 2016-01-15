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


$graphWidget = new CWidget();

// create new graph button
$createForm = new CForm('get');
$createForm->cleanItems();
if (!empty($this->data['parent_discoveryid'])) {
	$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
	$createForm->addItem(new CSubmit('form', _('Create graph prototype')));

	$graphWidget->addPageHeader(_('CONFIGURATION OF GRAPH PROTOTYPES'), $createForm);
	$graphWidget->addHeader(array(_('Graph prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'parent-discovery')));

	if (!empty($this->data['hostid'])) {
		$graphWidget->addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
	}
}
else {
	$createForm->addVar('hostid', $this->data['hostid']);

	if (!empty($this->data['hostid'])) {
		$createForm->addItem(new CSubmit('form', _('Create graph')));
	}
	else {
		$createGraphButton = new CSubmit('form', _('Create graph (select host first)'));
		$createGraphButton->setEnabled(false);
		$createForm->addItem($createGraphButton);
	}

	$graphWidget->addPageHeader(_('CONFIGURATION OF GRAPHS'), $createForm);

	$filterForm = new CForm('get');
	$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB(true)));
	$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB(true)));

	$graphWidget->addHeader(_('Graphs'), $filterForm);

	if (!empty($this->data['hostid'])) {
		$graphWidget->addItem(get_header_host_table('graphs', $this->data['hostid']));
	}
}
$graphWidget->addHeaderRowNumber();

// create form
$graphForm = new CForm();
$graphForm->setName('graphForm');
$graphForm->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create table
$graphTable = new CTableInfo(!empty($this->data['parent_discoveryid']) ? _('No graph prototypes found.') : _('No graphs found.'));
$graphTable->setHeader(array(
	new CCheckBox('all_graphs', null, "checkAll('".$graphForm->getName()."', 'all_graphs', 'group_graphid');"),
	$this->data['displayNodes'] ? _('Node') : null,
	!empty($this->data['hostid']) ? null : _('Hosts'),
	make_sorting_header(_('Name'), 'name'),
	_('Width'),
	_('Height'),
	make_sorting_header(_('Graph type'), 'graphtype')
));

foreach ($this->data['graphs'] as $graph) {
	$graphid = $graph['graphid'];

	$hostList = null;
	if (empty($this->data['hostid'])) {
		$hostList = array();
		foreach ($graph['hosts'] as $host) {
			$hostList[$host['name']] = $host['name'];
		}

		foreach ($graph['templates'] as $template) {
			$hostList[$template['name']] = $template['name'];
		}
		$hostList = implode(', ', $hostList);
	}

	$isCheckboxEnabled = true;
	$name = array();
	if (!empty($graph['templateid'])) {
		$realHosts = get_realhosts_by_graphid($graph['templateid']);
		$realHosts = DBfetch($realHosts);
		$name[] = new CLink($realHosts['name'], 'graphs.php?hostid='.$realHosts['hostid'], 'unknown');
		$name[] = NAME_DELIMITER;
		$name[] = new CLink(
			$graph['name'],
			'graphs.php?'.
				'form=update'.
				'&graphid='.$graphid.url_param('parent_discoveryid').
				'&hostid='.$this->data['hostid']
		);

		if ($graph['discoveryRule']) {
			$isCheckboxEnabled = false;
		}
	}
	elseif (!empty($graph['discoveryRule']) && empty($this->data['parent_discoveryid'])) {
		$name[] = new CLink(
			$graph['discoveryRule']['name'],
			'host_discovery.php?form=update&itemid='.$graph['discoveryRule']['itemid'],
			'parent-discovery'
		);
		$name[] = NAME_DELIMITER;
		$name[] = new CSpan($graph['name']);

		$isCheckboxEnabled = false;
	}
	else {
		$name[] = new CLink(
			$graph['name'],
			'graphs.php?'.
				'form=update'.
				'&graphid='.$graphid.url_param('parent_discoveryid').
				'&hostid='.$this->data['hostid']
		);
	}

	$checkBox = new CCheckBox('group_graphid['.$graphid.']', null, null, $graphid);
	$checkBox->setEnabled($isCheckboxEnabled);

	$graphTable->addRow(array(
		$checkBox,
		$this->data['displayNodes'] ? $graph['nodename'] : null,
		$hostList,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype']
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
if (!$this->data['parent_discoveryid']) {
	$goComboBox->addItem('copy_to', _('Copy selected to ...'));
}

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute(
	'confirm',
	$this->data['parent_discoveryid'] ? _('Delete selected graph prototypes?') : _('Delete selected graphs?')
);
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->attr('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "group_graphid";');
if ($this->data['parent_discoveryid']) {
	zbx_add_post_js('chkbxRange.prefix = "'.$this->data['parent_discoveryid'].'";');
	zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');
}
else {
	zbx_add_post_js('chkbxRange.prefix = "'.$this->data['hostid'].'";');
	zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');
}

// append table to form
$graphForm->addItem(array($this->data['paging'], $graphTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$graphWidget->addItem($graphForm);

return $graphWidget;
