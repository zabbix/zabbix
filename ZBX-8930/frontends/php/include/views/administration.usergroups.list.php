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


$userGroupsWidget = new CWidget();

// append page header to widget
$createForm = new CForm('get');
$createForm->cleanItems();
$configurationComboBox = new CComboBox('config', 'usergrps.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configurationComboBox->addItem('usergrps.php', _('User groups'));
$configurationComboBox->addItem('users.php', _('Users'));
$createForm->addItem(array($configurationComboBox, new CSubmit('form', _('Create user group'))));
$userGroupsWidget->addPageHeader(_('CONFIGURATION OF USER GROUPS'), $createForm);

// append header to widget
$userGroupsWidget->addHeader(_('User groups'));
$userGroupsWidget->addHeaderRowNumber();

// create form
$userGroupsForm = new CForm();
$userGroupsForm->setName('userGroupsForm');

// create user group table
$userGroupTable = new CTableInfo(_('No user groups found.'));
$userGroupTable->setHeader(array(
	new CCheckBox('all_groups', null, "checkAll('".$userGroupsForm->getName()."','all_groups','group_groupid');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	'#',
	_('Members'),
	_('Frontend access'),
	_('Debug mode'),
	_('Status')
));

foreach ($this->data['usergroups'] as $usrgrp) {
	$userGroupId = $usrgrp['usrgrpid'];

	$debugMode = ($usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		? new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisabledebug&usrgrpid='.$userGroupId, 'orange')
		: new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenabledebug&usrgrpid='.$userGroupId, 'enabled');

	// gui access
	$guiAccess = user_auth_type2str($usrgrp['gui_access']);
	$guiAccessStyle = 'enabled';
	if ($usrgrp['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
		$guiAccessStyle = 'orange';
	}
	if ($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
		$guiAccessStyle = 'disabled';
	}

	if (granted2update_group($userGroupId)) {
		$nextGuiAuth = ($usrgrp['gui_access'] + 1 > GROUP_GUI_ACCESS_DISABLED)
			? GROUP_GUI_ACCESS_SYSTEM
			: $usrgrp['gui_access'] + 1;

		$guiAccess = new CLink(
			$guiAccess,
			'usergrps.php?action=usergroup.set_gui_access&set_gui_access='.$nextGuiAuth.'&usrgrpid='.$userGroupId,
			$guiAccessStyle
		);

		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)
			? new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisable&usrgrpid='.$userGroupId, 'enabled')
			: new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenable&usrgrpid='.$userGroupId, 'disabled');
	}
	else {
		$guiAccess = new CSpan($guiAccess, $guiAccessStyle);
		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED) ? new CSpan(_('Enabled'), 'enabled') : new CSpan(_('Disabled'), 'disabled');
	}

	if (isset($usrgrp['users'])) {
		$userGroupUsers = $usrgrp['users'];
		order_result($userGroupUsers, 'alias');

		$users = array();
		$i = 0;

		foreach ($userGroupUsers as $user) {
			$i++;

			if ($i > $this->data['config']['max_in_table']) {
				$users[] = ' &hellip;';

				break;
			}

			if ($users) {
				$users[] = ', ';
			}

			$users[] = new CLink(getUserFullname($user),
				'users.php?form=update&userid='.$user['userid'],
				($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED || $user['users_status'] == GROUP_STATUS_DISABLED)
					? 'disabled' : 'enabled'
			);
		}
	}

	$userGroupTable->addRow(array(
		new CCheckBox('group_groupid['.$userGroupId.']', null, null, $userGroupId),
		new CLink($usrgrp['name'], 'usergrps.php?form=update&usrgrpid='.$userGroupId),
		array(new CLink(_('Users'), 'users.php?filter_usrgrpid='.$userGroupId), ' (', count($usrgrp['users']), ')'),
		new CCol($users, 'wraptext'),
		$guiAccess,
		$debugMode,
		$usersStatus
	));
}

// append GO buttons
$goComboBox = new CComboBox('action');

$goOption = new CComboItem('usergroup.massenable', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('usergroup.massdisable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('usergroup.massenabledebug', _('Enable debug mode'));
$goOption->setAttribute('confirm', _('Enable debug mode in selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('usergroup.massdisabledebug', _('Disable debug mode'));
$goOption->setAttribute('confirm', _('Disable debug mode in selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('usergroup.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected groups?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_groupid";');

// append table to form
$userGroupsForm->addItem(array($this->data['paging'], $userGroupTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$userGroupsWidget->addItem($userGroupsForm);

return $userGroupsWidget;
