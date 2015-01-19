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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$itemsWidget = new CWidget();

$discoveryRule = $this->data['discovery_rule'];

// create new item button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$createForm->addItem(new CSubmit('form', _('Create host prototype')));
$itemsWidget->addPageHeader(_('CONFIGURATION OF HOST PROTOTYPES'), $createForm);

// header
$itemsWidget->addHeader(array(_('Host prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'parent-discovery')));
$itemsWidget->addHeaderRowNumber();
$itemsWidget->addItem(get_header_host_table('hosts', $discoveryRule['hostid'], $this->data['parent_discoveryid']));

// create form
$itemForm = new CForm();
$itemForm->setName('hosts');
$itemForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$hostTable = new CTableInfo(_('No host prototypes found.'));

$hostTable->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Templates'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
));

foreach ($this->data['hostPrototypes'] as $hostPrototype) {
	// name
	$name = array();
	if ($hostPrototype['templateid']) {
		$sourceTemplate = $hostPrototype['sourceTemplate'];
		$name[] = new CLink($sourceTemplate['name'], '?parent_discoveryid='.$hostPrototype['sourceDiscoveryRuleId'], 'unknown');
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($hostPrototype['name'], '?form=update&parent_discoveryid='.$discoveryRule['itemid'].'&hostid='.$hostPrototype['hostid']);

	// template list
	if (empty($hostPrototype['templates'])) {
		$hostTemplates = '-';
	}
	else {
		$hostTemplates = array();
		order_result($hostPrototype['templates'], 'name');

		foreach ($hostPrototype['templates'] as $template) {

			$caption = array();
			$caption[] = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid'], 'unknown');

			$linkedTemplates = $this->data['linkedTemplates'][$template['templateid']]['parentTemplates'];
			if ($linkedTemplates) {
				order_result($linkedTemplates, 'name');

				$caption[] = ' (';
				foreach ($linkedTemplates as $tpl) {
					$caption[] = new CLink($tpl['name'],'templates.php?form=update&templateid='.$tpl['templateid'], 'unknown');
					$caption[] = ', ';
				}
				array_pop($caption);

				$caption[] = ')';
			}

			$hostTemplates[] = $caption;
			$hostTemplates[] = ', ';
		}

		if ($hostTemplates) {
			array_pop($hostTemplates);
		}
	}

	// status
	$status = new CLink(item_status2str($hostPrototype['status']),
		'?group_hostid='.$hostPrototype['hostid'].
			'&parent_discoveryid='.$discoveryRule['itemid'].
			'&action='.($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED
				? 'hostprototype.massenable'
				: 'hostprototype.massdisable'
			),
		itemIndicatorStyle($hostPrototype['status'])
	);

	$hostTable->addRow(array(
		new CCheckBox('group_hostid['.$hostPrototype['hostid'].']', null, null, $hostPrototype['hostid']),
		$name,
		new CCol($hostTemplates, 'wraptext'),
		$status
	));
}

zbx_add_post_js('cookie.prefix = "'.$discoveryRule['itemid'].'";');

// append table to form
$itemForm->addItem(array(
	$this->data['paging'],
	$hostTable,
	$this->data['paging'],
	get_table_header(
	new CActionButtonList('action', 'group_hostid',
		array(
			'hostprototype.massenable' => array('name' => _('Enable'),
				'confirm' => _('Enable selected host prototypes?')
			),
			'hostprototype.massdisable' => array('name' => _('Disable'),
				'confirm' => _('Disable selected host prototypes?')
			),
			'hostprototype.massdelete' => array('name' => _('Delete'),
				'confirm' => _('Delete selected host prototypes?')
			)
		),
		$discoveryRule['itemid']
	))
));

// append form to widget
$itemsWidget->addItem($itemForm);

return $itemsWidget;
