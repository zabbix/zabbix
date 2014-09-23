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


$triggersWidget = new CWidget(null, 'trigger-list');

// append host summary to widget header
$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));

// create new application button
$createForm = new CForm('get');
$createForm->cleanItems();

$createForm->addItem(new CSubmit('form', _('Create trigger prototype')));
$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGER PROTOTYPES'), $createForm);

// create widget header

$triggersWidget->addHeader(array(_('Trigger prototypes of').SPACE,
	new CSpan($this->data['discovery_rule']['name'], 'parent-discovery')
));
$triggersWidget->addHeaderRowNumber(array(
	'[ ',
	new CLink(
		$this->data['showdisabled'] ? _('Hide disabled trigger prototypes') : _('Show disabled trigger prototypes'),
		'trigger_prototypes.php?'.
			'showdisabled='.($this->data['showdisabled'] ? 0 : 1).
			'&parent_discoveryid='.$this->data['parent_discoveryid']
	),
	' ]'
));

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$triggersTable = new CTableInfo(_('No trigger prototypes found.'));
$triggersTable->setHeader(array(
	new CCheckBox('all_triggers', null, "checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');"),
	make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Name'), 'description', $this->data['sort'], $this->data['sortorder']),
	_('Expression'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
));

foreach ($this->data['triggers'] as $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $this->data['parent_discoveryid'];

	// description
	$description = array();

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
	$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
	$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = new CSpan(_('Template'), 'unknown');
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'],
				$real_host['hostid']
			);
			$description[] = new CLink(
				CHtml::encode($real_host['name']),
				'trigger_prototypes.php?parent_discoveryid='.$tpl_disc_ruleid,
				'unknown'
			);

			$description[] = NAME_DELIMITER;
		}
	}

	$description[] = new CLink(
		CHtml::encode($trigger['description']),
		'trigger_prototypes.php?'.
			'form=update'.
			'&parent_discoveryid='.$this->data['parent_discoveryid'].
			'&triggerid='.$triggerid
	);

	// status
	$status = new CLink(
		triggerIndicator($trigger['status']),
		'trigger_prototypes.php?'.
			'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
				? 'triggerprototype.massenable'
				: 'triggerprototype.massdisable'
			).
			'&g_triggerid='.$triggerid.
			'&parent_discoveryid='.$this->data['parent_discoveryid'],
		triggerIndicatorStyle($trigger['status'])
	);

	// checkbox
	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', null, null, $triggerid);

	$triggersTable->addRow(array(
		$checkBox,
		getSeverityCell($trigger['priority']),
		$description,
		new CCol(triggerExpression($trigger, true), 'trigger-expression'),
		$status
	));
}

// create go button
$goComboBox = new CComboBox('action');

$goOption = new CComboItem('triggerprototype.massenable', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected trigger prototypes?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('triggerprototype.massdisable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected trigger prototypes?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('triggerprototype.massupdateform', _('Mass update'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('triggerprototype.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected trigger prototypes?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');
zbx_add_post_js('chkbxRange.prefix = "'.$this->data['parent_discoveryid'].'";');
zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');

// append table to form
$triggersForm->addItem(array($this->data['paging'], $triggersTable, $this->data['paging'],
	get_table_header(array($goComboBox, $goButton))
));

// append form to widget
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
