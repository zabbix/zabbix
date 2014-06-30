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


$discoveryWidget = new CWidget();

// create new discovery rule button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);
$createForm->addItem(new CSubmit('form', _('Create discovery rule')));
$discoveryWidget->addPageHeader(_('CONFIGURATION OF DISCOVERY RULES'), $createForm);

// header
$discoveryWidget->addHeader(_('Discovery rules'));
$discoveryWidget->addHeaderRowNumber();
$discoveryWidget->addItem(get_header_host_table('discoveries', $this->data['hostid']));

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('discovery');
$discoveryForm->addVar('hostid', $this->data['hostid']);

// create table
$discoveryTable = new CTableInfo(_('No discovery rules found.'));

$discoveryTable->setHeader(array(
	new CCheckBox('all_items', null, "checkAll('".$discoveryForm->getName()."', 'all_items', 'g_hostdruleid');"),
	make_sorting_header(_('Name'), 'name'),
	_('Items'),
	_('Triggers'),
	_('Graphs'),
	($data['host']['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) ? _('Hosts') : null,
	make_sorting_header(_('Key'), 'key_'),
	make_sorting_header(_('Interval'), 'delay'),
	make_sorting_header(_('Type'), 'type'),
	make_sorting_header(_('Status'), 'status'),
	$data['showInfoColumn'] ? _('Info') : null
));

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = array();

	if ($discovery['templateid']) {
		$dbTemplate = get_realhost_by_itemid($discovery['templateid']);

		$description[] = new CLink($dbTemplate['name'], '?hostid='.$dbTemplate['hostid'], 'unknown');
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink($discovery['name_expanded'], '?form=update&itemid='.$discovery['itemid']);

	// status
	$status = new CLink(
		itemIndicator($discovery['status'], $discovery['state']),
		'?hostid='.$_REQUEST['hostid'].'&g_hostdruleid='.$discovery['itemid'].'&go='.($discovery['status'] ? 'activate' : 'disable'),
		itemIndicatorStyle($discovery['status'], $discovery['state'])
	);

	// info
	if ($data['showInfoColumn']) {
		if ($discovery['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($discovery['error'])) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($discovery['error'], 'on');
		}
		else {
			$info = '';
		}
	}
	else {
		$info = null;
	}

	// host prototype link
	$hostPrototypeLink = null;
	if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$hostPrototypeLink = array(
			new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$discovery['itemid']),
			' ('.$discovery['hostPrototypes'].')'
		);
	}

	$discoveryTable->addRow(array(
		new CCheckBox('g_hostdruleid['.$discovery['itemid'].']', null, null, $discovery['itemid']),
		$description,
		array(
			new CLink(
				_('Item prototypes'),
				'disc_prototypes.php?hostid='.get_request('hostid').'&parent_discoveryid='.$discovery['itemid']
			),
			' ('.$discovery['items'].')'
		),
		array(
			new CLink(
				_('Trigger prototypes'),
				'trigger_prototypes.php?hostid='.get_request('hostid').'&parent_discoveryid='.$discovery['itemid']
			),
			' ('.$discovery['triggers'].')'
		),
		array(
			new CLink(
				_('Graph prototypes'),
				'graphs.php?hostid='.get_request('hostid').'&parent_discoveryid='.$discovery['itemid']
			),
			' ('.$discovery['graphs'].')'
		),
		$hostPrototypeLink,
		$discovery['key_'],
		$discovery['delay'],
		item_type2str($discovery['type']),
		$status,
		$info
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected discovery rules?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected discovery rules?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected discovery rules?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "g_hostdruleid";');
zbx_add_post_js('chkbxRange.prefix = "'.$this->data['hostid'].'";');
zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$discoveryForm->addItem(array($this->data['paging'], $discoveryTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$discoveryWidget->addItem($discoveryForm);

return $discoveryWidget;
