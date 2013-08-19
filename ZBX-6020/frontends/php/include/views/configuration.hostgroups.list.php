<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$hostGroupWidget = new CWidget();

// create new hostgroup button
$createForm = new CForm('get');
$createForm->cleanItems();
if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$tmpItem = new CSubmit('form', _('Create host group'));
}
else {
	$tmpItem = new CSubmit('form', _('Create host group').SPACE._('(Only super admins can create groups)'));
	$tmpItem->setEnabled(false);
}
$createForm->addItem($tmpItem);

$hostGroupWidget->addPageHeader(_('CONFIGURATION OF HOST GROUPS'), $createForm);

// header
$hostGroupWidget->addHeader(_('Host groups'));
$hostGroupWidget->addHeaderRowNumber();

// create form
$hostGroupForm = new CForm();
$hostGroupForm->setName('hostgroupForm');

// create table
$hostGroupTable = new CTableInfo(_('No host groups defined.'));
$hostGroupTable->setHeader(array(
	new CCheckBox('all_groups', null, "checkAll('".$hostGroupForm->getName()."', 'all_groups', 'groups');"),
	$this->data['displayNodes'] ? _('Node') : null,
	make_sorting_header(_('Name'), 'name'),
	' # ',
	_('Members')
));

foreach ($this->data['groups'] as $group) {
	$hostsOutput = array();
	$i = 0;

	foreach ($group['templates'] as $template) {
		$i++;

		if ($i > $this->data['config']['max_in_table']) {
			$hostsOutput[] = '...';
			$hostsOutput[] = '//empty for array_pop';
			break;
		}

		$url = 'templates.php?form=update&templateid='.$template['templateid'].'&groupid='.$group['groupid'];

		$hostsOutput[] = new CLink($template['name'], $url, 'unknown');
		$hostsOutput[] = ', ';
	}

	if ($hostsOutput) {
		array_pop($hostsOutput);

		$hostsOutput[] = BR();
		$hostsOutput[] = BR();
	}

	foreach ($group['hosts'] as $host) {
		$i++;

		if ($i > $this->data['config']['max_in_table']) {
			$hostsOutput[] = '...';
			$hostsOutput[] = '//empty for array_pop';
			break;
		}

		switch ($host['status']) {
			case HOST_STATUS_NOT_MONITORED:
				$style = 'on';
				$url = 'hosts.php?form=update&hostid='.$host['hostid'].'&groupid='.$group['groupid'];
				break;

			default:
				$style = null;
				$url = 'hosts.php?form=update&hostid='.$host['hostid'].'&groupid='.$group['groupid'];
			break;
		}

		$hostsOutput[] = new CLink($host['name'], $url, $style);
		$hostsOutput[] = ', ';
	}
	array_pop($hostsOutput);

	$hostCount = $this->data['groupCounts'][$group['groupid']]['hosts'];
	$templateCount = $this->data['groupCounts'][$group['groupid']]['templates'];

	$hostGroupTable->addRow(array(
		new CCheckBox('groups['.$group['groupid'].']', null, null, $group['groupid']),
		$this->data['displayNodes'] ? $group['nodename'] : null,
		new CLink($group['name'], 'hostgroups.php?form=update&groupid='.$group['groupid']),
		array(
			array(new CLink(_('Templates'), 'templates.php?groupid='.$group['groupid'], 'unknown'), ' ('.$templateCount.')'),
			BR(),
			array(new CLink(_('Hosts'), 'hosts.php?groupid='.$group['groupid']), ' ('.$hostCount.')')
		),
		new CCol(empty($hostsOutput) ? '-' : $hostsOutput, 'wraptext')
	));
}

// create go button
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected hosts?'));
$goComboBox->addItem($goOption);
$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable hosts in the selected host groups?'));
$goComboBox->addItem($goOption);
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected host groups?'));
$goComboBox->addItem($goOption);
$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "groups";');

// append table to form
$hostGroupForm->addItem(array($this->data['paging'], $hostGroupTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$hostGroupWidget->addItem($hostGroupForm);

return $hostGroupWidget;
