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
	$this->data['displayNodes'] ? _('Node') : null,
	make_sorting_header(_('Name'), 'name'),
	'#',
	_('Members'),
	_('Status'),
	_('Frontend access'),
	_('Debug mode')
));

foreach ($this->data['usergroups'] as $usrgrp) {
	$userGroupId = $usrgrp['usrgrpid'];

	$debugMode = ($usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		? new CLink(_('Enabled'), 'usergrps.php?go=disable_debug&usrgrpid='.$userGroupId, 'orange')
		: new CLink(_('Disabled'), 'usergrps.php?go=enable_debug&usrgrpid='.$userGroupId, 'enabled');

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
			'usergrps.php?go=set_gui_access&set_gui_access='.$nextGuiAuth.'&usrgrpid='.$userGroupId,
			$guiAccessStyle
		);

		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)
			? new CLink(_('Enabled'), 'usergrps.php?go=disable_status&usrgrpid='.$userGroupId, 'enabled')
			: new CLink(_('Disabled'), 'usergrps.php?go=enable_status&usrgrpid='.$userGroupId, 'disabled');
	}
	else {
		$guiAccess = new CSpan($guiAccess, $guiAccessStyle);
		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED) ? new CSpan(_('Enabled'), 'enabled') : new CSpan(_('Disabled'), 'disabled');
	}

	if (isset($usrgrp['users'])) {
		$userGroupUsers = $usrgrp['users'];
		order_result($userGroupUsers, 'alias');

		$users = array();
		foreach ($userGroupUsers as $user) {
			$userTypeStyle = 'enabled';
			if ($user['type'] == USER_TYPE_ZABBIX_ADMIN) {
				$userTypeStyle = 'orange';
			}
			if ($user['type'] == USER_TYPE_SUPER_ADMIN) {
				$userTypeStyle = 'disabled';
			}

			$userStatusStyle = 'enabled';
			if ($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
				$userStatusStyle = 'disabled';
			}
			if ($user['users_status'] == GROUP_STATUS_DISABLED) {
				$userStatusStyle = 'disabled';
			}

			$users[] = new CLink(getUserFullname($user),
				'users.php?form=update&userid='.$user['userid'],
				$userStatusStyle
			);
			$users[] = ', ';
		}
		array_pop($users);
	}

	$userGroupTable->addRow(array(
		new CCheckBox('group_groupid['.$userGroupId.']', null, null, $userGroupId),
		$this->data['displayNodes'] ? $usrgrp['nodename'] : null,
		new CLink($usrgrp['name'], 'usergrps.php?form=update&usrgrpid='.$userGroupId),
		array(new CLink(_('Users'), 'users.php?&filter_usrgrpid='.$userGroupId), ' (', count($usrgrp['users']), ')'),
		new CCol($users, 'wraptext'),
		$usersStatus,
		$guiAccess,
		$debugMode
	));
}

// append GO buttons
$goComboBox = new CComboBox('go');

$goOption = new CComboItem('enable_status', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable_status', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('enable_debug', _('Enable debug mode'));
$goOption->setAttribute('confirm', _('Enable debug mode in selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable_debug', _('Disable debug mode'));
$goOption->setAttribute('confirm', _('Disable debug mode in selected groups?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
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
