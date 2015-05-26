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

$userGroupsWidget = (new CWidget())->setTitle(_('User groups'));

// append page header to widget
$createForm = new CForm('get');
$createForm->cleanItems();
$controls = new CList();
$controls->addItem(new CSubmit('form', _('Create user group')));
$createForm->addItem($controls);
$userGroupsWidget->setControls($createForm);

// create form
$userGroupsForm = new CForm();
$userGroupsForm->setName('userGroupsForm');

// create user group table
$userGroupTable = new CTableInfo();
$userGroupTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_groups', null, "checkAll('".$userGroupsForm->getName()."','all_groups','group_groupid');")))->
		addClass('cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	'#',
	_('Members'),
	_('Frontend access'),
	_('Debug mode'),
	_('Status')
]);

foreach ($this->data['usergroups'] as $usrgrp) {
	$userGroupId = $usrgrp['usrgrpid'];

	$debugMode = ($usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		? new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisabledebug&usrgrpid='.$userGroupId, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_ORANGE)
		: new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenabledebug&usrgrpid='.$userGroupId, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN);

	// gui access
	$guiAccess = user_auth_type2str($usrgrp['gui_access']);
	$guiAccessStyle = ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN;
	if ($usrgrp['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
		$guiAccessStyle = ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_ORANGE;
	}
	if ($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
		$guiAccessStyle = ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_RED;
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
			? new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisable&usrgrpid='.$userGroupId, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN)
			: new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenable&usrgrpid='.$userGroupId, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_RED);
	}
	else {
		$guiAccess = new CSpan($guiAccess, $guiAccessStyle);
		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED) ? new CSpan(_('Enabled'), ZBX_STYLE_GREEN) : new CSpan(_('Disabled'), ZBX_STYLE_RED);
	}

	if (isset($usrgrp['users'])) {
		$userGroupUsers = $usrgrp['users'];
		order_result($userGroupUsers, 'alias');

		$users = [];
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
					? ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_RED : ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREEN
			);
		}
	}

	$name = new CLink($usrgrp['name'], 'usergrps.php?form=update&usrgrpid='.$userGroupId);

	$userGroupTable->addRow([
		new CCheckBox('group_groupid['.$userGroupId.']', null, null, $userGroupId),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[new CLink(_('Users'), 'users.php?filter_usrgrpid='.$userGroupId), CViewHelper::showNum(count($usrgrp['users']))],
		$users,
		$guiAccess,
		$debugMode,
		$usersStatus
	]);
}

// append table to form
$userGroupsForm->addItem([
	$userGroupTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_groupid', [
		'usergroup.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected groups?')],
		'usergroup.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected groups?')],
		'usergroup.massenabledebug' => ['name' => _('Enable debug mode'),
			'confirm' => _('Enable debug mode in selected groups?')
		],
		'usergroup.massdisabledebug' => ['name' => _('Disable debug mode'),
			'confirm' => _('Disable debug mode in selected groups?')
		],
		'usergroup.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected groups?')]
	])
]);

// append form to widget
$userGroupsWidget->addItem($userGroupsForm);

return $userGroupsWidget;
