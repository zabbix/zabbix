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


$triggersWidget = new CWidget();

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	if (!empty($this->data['parent_discoveryid'])) {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));
	}
	else {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid']));
	}
}

// create new application button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['parent_discoveryid'])) {
	$createForm->addItem(new CSubmit('form', _('Create trigger prototype')));
	$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGER PROTOTYPES'), $createForm);
}
else {
	$createForm->addItem(new CSubmit('form', _('Create trigger')));
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGERS'), $createForm);
}

// create widget header
if (!empty($this->data['parent_discoveryid'])) {
	$triggersWidget->addHeader(array(_('Trigger prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'gold')));
	$triggersWidget->addHeaderRowNumber(array(
		'[ ',
		new CLink(
			$this->data['showdisabled'] ? _('Hide disabled triggers') : _('Show disabled triggers'),
			'trigger_prototypes.php?showdisabled='.($this->data['showdisabled'] ? 0 : 1).'&hostid='.$this->data['hostid'].'&parent_discoveryid='.$this->data['parent_discoveryid']
		),
		' ]'
	));
}
else {
	$filterForm = new CForm('get');
	$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
	$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()));

	$triggersWidget->addHeader(_('Triggers'), $filterForm);
	$triggersWidget->addHeaderRowNumber(array(
		'[ ',
		new CLink(
			$this->data['showdisabled'] ? _('Hide disabled triggers') : _('Show disabled triggers'),
			'triggers.php?hostid='.$this->data['hostid'].'&showdisabled='.($this->data['showdisabled'] ? 0 : 1)
		),
		' ]'
	));
}

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$triggersForm->addVar('hostid', $this->data['hostid']);

// create table
$link = new Curl();
if (!empty($this->data['parent_discoveryid'])) {
	$link->setArgument('parent_discoveryid', $this->data['parent_discoveryid']);
}
$link->setArgument('hostid', $this->data['hostid']);
$link = $link->getUrl();

$triggersTable = new CTableInfo(_('No triggers defined.'));
$triggersTable->setHeader(array(
	new CCheckBox('all_triggers', null, "checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');"),
	make_sorting_header(_('Severity'), 'priority', $link),
	empty($this->data['hostid']) ? _('Host') : null,
	make_sorting_header(_('Name'), 'description', $link),
	_('Expression'),
	make_sorting_header(_('Status'), 'status', $link),
	empty($this->data['parent_discoveryid']) ? _('Error') : null
));
foreach ($this->data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $this->data['parent_discoveryid'];
	$description = array();

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
	$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
	$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = new CSpan(empty($this->data['parent_discoveryid']) ? _('Host') : _('Template'), 'unknown');
			$description[] = ':'.SPACE;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			if (!empty($this->data['parent_discoveryid'])) {
				$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'], $real_host['hostid']);
				$description[] = new CLink($real_host['name'], 'trigger_prototypes.php?hostid='.$real_host['hostid'].'&parent_discoveryid='.$tpl_disc_ruleid, 'unknown');
			}
			else {
				$description[] = new CLink($real_host['name'], 'triggers.php?hostid='.$real_host['hostid'], 'unknown');
			}
			$description[] = ':'.SPACE;
		}
	}

	if (empty($this->data['parent_discoveryid'])) {
		if (!empty($trigger['discoveryRule'])) {
			$description[] = new CLink($trigger['discoveryRule']['name'], 'trigger_prototypes.php?hostid='.$this->data['hostid'].'&parent_discoveryid='.$trigger['discoveryRule']['itemid'], 'gold');
			$description[] = ':'.SPACE.$trigger['description'];
		}
		else {
			$description[] = new CLink($trigger['description'], 'triggers.php?form=update&hostid='.$this->data['hostid'].'&triggerid='.$triggerid);
		}

		$dependencies = $trigger['dependencies'];
		if (count($dependencies) > 0) {
			$description[] = array(BR(), bold(_('Depends on').' : '));
			foreach ($dependencies as $dep_trigger) {
				$description[] = BR();

				$db_hosts = get_hosts_by_triggerid($dep_trigger['triggerid']);
				while ($host = DBfetch($db_hosts)) {
					$description[] = $host['name'];
					$description[] = ', ';
				}
				array_pop($description);
				$description[] = ' : ';
				$description[] = $dep_trigger['description'];
			}
		}
	}
	else {
		$description[] = new CLink($trigger['description'], 'trigger_prototypes.php?form=update&hostid='.$this->data['hostid'].'&parent_discoveryid='.$this->data['parent_discoveryid'].'&triggerid='.$triggerid);
	}

	if ($trigger['value_flags'] == TRIGGER_VALUE_FLAG_NORMAL) {
		$trigger['error'] = '';
	}

	$templated = false;
	foreach ($trigger['hosts'] as $hostid => $host) {
		$templated |= (HOST_STATUS_TEMPLATE == $host['status']);
	}

	if (empty($this->data['parent_discoveryid'])) {
		if (!zbx_empty($trigger['error']) && !$templated) {
			$error = new CDiv(SPACE, 'status_icon iconerror');
			$error->setHint($trigger['error'], '', 'on');
		}
		else {
			$error = new CDiv(SPACE, 'status_icon iconok');
		}
	}
	else {
		$error = null;
	}

	$status = '';
	if (!empty($this->data['parent_discoveryid'])) {
		$status_link = 'trigger_prototypes.php?go='.($trigger['status'] == TRIGGER_STATUS_DISABLED ? 'activate' : 'disable').'&hostid='.$this->data['hostid'].'&g_triggerid='.$triggerid.'&parent_discoveryid='.$this->data['parent_discoveryid'];
	}
	else {
		$status_link = 'triggers.php?go='.($trigger['status'] == TRIGGER_STATUS_DISABLED ? 'activate' : 'disable').'&hostid='.$this->data['hostid'].'&g_triggerid='.$triggerid;
	}
	if ($trigger['status'] == TRIGGER_STATUS_DISABLED) {
		$status = new CLink(_('Disabled'), $status_link, 'disabled');
	}
	elseif ($trigger['status'] == TRIGGER_STATUS_ENABLED) {
		$status = new CLink(_('Enabled'), $status_link, 'enabled');
	}

	$hosts = null;
	if (empty($this->data['hostid'])) {
		foreach ($trigger['hosts'] as $hostid => $host) {
			if (!empty($hosts)) {
				$hosts[] = ', ';
			}
			$hosts[] = $host['name'];
		}
	}

	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', null, null, $triggerid);
	$checkBox->setEnabled(empty($trigger['discoveryRule']));

	$expressionColumn = new CCol(triggerExpression($trigger, true));
	$expressionColumn->setAttribute('style', 'white-space: normal;');

	$triggersTable->addRow(array(
		$checkBox,
		getSeverityCell($trigger['priority']),
		$hosts,
		$description,
		$expressionColumn,
		$status,
		$error
	));
	$triggers[$tnum] = $trigger;
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected triggers?'));
$goComboBox->addItem($goOption);
$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected triggers?'));
$goComboBox->addItem($goOption);
$goOption = new CComboItem('massupdate', _('Mass update'));
$goComboBox->addItem($goOption);
if (empty($this->data['parent_discoveryid'])) {
	$goOption = new CComboItem('copy_to', _('Copy selected to ...'));
	$goComboBox->addItem($goOption);
}
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected triggers?'));
$goComboBox->addItem($goOption);
$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');

// append table to form
$triggersForm->addItem(array($this->data['paging'], $triggersTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
