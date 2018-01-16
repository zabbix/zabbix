<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

$widget = (new CWidget())
	->setTitle(_('User groups'))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create user group'), '?form=create'))
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter('web.usergroup.filter.state'))
		->addColumn((new CFormList())->addRow(_('Name'),
			(new CTextBox('filter_name', $data['filter']['name']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		))
		->addColumn((new CFormList())->addRow(_('Status'),
			(new CRadioButtonList('filter_users_status', (int) $data['filter']['users_status']))
				->addValue(_('Any'), -1)
				->addValue(_('Enabled'), GROUP_STATUS_ENABLED)
				->addValue(_('Disabled'), GROUP_STATUS_DISABLED)
				->setModern(true)
		))
	);

// create form
$userGroupsForm = (new CForm())->setName('userGroupsForm');

// create user group table
$userGroupTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_groups'))->onClick("checkAll('".$userGroupsForm->getName()."','all_groups','group_groupid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
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
		? (new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisabledebug&usrgrpid='.$userGroupId))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_ORANGE)
			->addSID()
		: (new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenabledebug&usrgrpid='.$userGroupId))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addSID();

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

		$guiAccess = (new CLink(
			$guiAccess,
			'usergrps.php?action=usergroup.set_gui_access&set_gui_access='.$nextGuiAuth.'&usrgrpid='.$userGroupId
		))
			->addClass($guiAccessStyle)
			->addSID();

		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)
			? (new CLink(_('Enabled'), 'usergrps.php?action=usergroup.massdisable&usrgrpid='.$userGroupId))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID()
			: (new CLink(_('Disabled'), 'usergrps.php?action=usergroup.massenable&usrgrpid='.$userGroupId))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
	}
	else {
		$guiAccess = (new CSpan($guiAccess))->addClass($guiAccessStyle);
		$usersStatus = ($usrgrp['users_status'] == GROUP_STATUS_ENABLED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
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

			$users[] = (new CLink(getUserFullname($user), 'users.php?form=update&userid='.$user['userid']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED || $user['users_status'] == GROUP_STATUS_DISABLED
						? ZBX_STYLE_RED
						: ZBX_STYLE_GREEN);
		}
	}

	$name = new CLink($usrgrp['name'], 'usergrps.php?form=update&usrgrpid='.$userGroupId);

	$userGroupTable->addRow([
		new CCheckBox('group_groupid['.$userGroupId.']', $userGroupId),
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
$widget->addItem($userGroupsForm);

return $widget;
