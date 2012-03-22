<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
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

$graphWidget = new CWidget();

// create new graph button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CSubmit('form', _('Create graph')));
$graphWidget->addPageHeader(_('CONFIGURATION OF GRAPHS'), $createForm);

// create widget header
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()));

$graphWidget->addHeader(_('Graphs'), $filterForm);
$graphWidget->addHeaderRowNumber();

if (!empty($this->data['hostid'])) {
	$graphWidget->addItem(get_header_host_table('graphs', $this->data['hostid']));
}

// create form
$graphForm = new CForm('get');
$graphForm->setName('graphForm');
$graphForm->addVar('hostid', $this->data['hostid']);

// create table
$graphTable = new CTableInfo(_('No graphs defined.'));
$graphTable->setHeader(array(
	new CCheckBox('all_graphs', null, "checkAll('".$graphForm->getName()."', 'all_graphs', 'group_graphid');"),
	!empty($this->data['hostid']) ? null : _('Hosts'),
	make_sorting_header(_('Name'), 'name'),
	_('Width'),
	_('Height'),
	make_sorting_header(_('Graph type'), 'graphtype')
));

foreach ($data['graphs'] as $graph) {
	$graphid = $graph['graphid'];

	$hostList = null;
	if (empty($this->data['hostid'])) {
		$hostList = array();
		foreach ($graph['hosts'] as $host) {
			$hostList[$host['host']] = $host['host'];
		}

		foreach ($graph['templates'] as $template) {
			$hostList[$template['host']] = $template['host'];
		}
		$hostList = implode(', ', $hostList);
	}

	$name = array();
	if ($graph['templateid'] != 0) {
		$realHosts = get_realhosts_by_graphid($graph['templateid']);
		$realHosts = DBfetch($realHosts);
		$name[] = new CLink($realHosts['name'], 'graphs.php?hostid='.$realHosts['hostid'], 'unknown');
		$name[] = ':'.$graph['name'];
	}
	elseif (!empty($graph['discoveryRule'])) {
		$name[] = new CLink($graph['discoveryRule']['name'],
			'graph_prototypes.php?parent_discoveryid='.$graph['discoveryRule']['itemid'], 'gold');
		$name[] = ':'.$graph['name'];
	}
	else {
		$name[] = new CLink($graph['name'], 'graphs.php?graphid='.$graphid.'&form=update');
	}

	$checkBox = new CCheckBox('group_graphid['.$graphid.']', null, null, $graphid);
	if ($graph['templateid'] > 0 || !empty($graph['discoveryRule'])) {
		$checkBox->setEnabled(false);
	}

	$graphTable->addRow(array(
		$checkBox,
		$hostList,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype']
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goComboBox->addItem('copy_to', _('Copy selected to ...'));

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm',_('Delete selected graphs?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id','goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_graphid";');

// append table to form
$graphForm->addItem(array($this->data['paging'], $graphTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$graphWidget->addItem($graphForm);
return $graphWidget;
?>
