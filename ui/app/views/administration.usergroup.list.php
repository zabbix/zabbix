<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

if ($data['uncheck']) {
	uncheckTableRows('usergroup');
}

$widget = (new CWidget())
	->setTitle(_('User groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_USERGROUP_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('Create user group'),
					(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.edit')
				))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'usergroup.list'))
		->addVar('action', 'usergroup.list')
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Status'),
				(new CRadioButtonList('filter_user_status', (int) $data['filter']['user_status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), GROUP_STATUS_ENABLED)
					->addValue(_('Disabled'), GROUP_STATUS_DISABLED)
					->setModern(true)
			)
		])
	);

$form = (new CForm())
	->setName('usergroups_form')
	->setId('usergroups');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader((new CCheckBox('all_groups'))->onClick(sprintf(
			'checkAll(\'%s\',\'all_groups\',\'usrgrpids\');', $form->getName()
		))))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.list')
				->getUrl()
		),
		'#',
		_('Members'),
		_('Frontend access'),
		_('Debug mode'),
		_('Status')
	]);

foreach ($this->data['usergroups'] as $usergroup) {
	$debug_mode = ($usergroup['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
		? (new CLink(_('Enabled'), (new CUrl('zabbix.php'))
			->setArgument('action', 'usergroup.massupdate')
			->setArgument('debug_mode', GROUP_DEBUG_MODE_DISABLED)
			->setArgument('usrgrpids', [$usergroup['usrgrpid']])
			->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_ORANGE)
			->addSID()
		: (new CLink(_('Disabled'), (new CUrl('zabbix.php'))
			->setArgument('action', 'usergroup.massupdate')
			->setArgument('debug_mode', GROUP_DEBUG_MODE_ENABLED)
			->setArgument('usrgrpids', [$usergroup['usrgrpid']])
			->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addSID();

	$gui_access = user_auth_type2str($usergroup['gui_access']);

	if (granted2update_group($usergroup['usrgrpid'])) {
		$next_gui_auth = ($usergroup['gui_access'] + 1 > GROUP_GUI_ACCESS_DISABLED)
			? GROUP_GUI_ACCESS_SYSTEM
			: $usergroup['gui_access'] + 1;

		$gui_access = (new CLink(
			$gui_access,
			(new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('gui_access', $next_gui_auth)
				->setArgument('usrgrpids', [$usergroup['usrgrpid']])
				->getUrl()
			))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addSID();

		$user_status = ($usergroup['users_status'] == GROUP_STATUS_ENABLED)
			? (new CLink(_('Enabled'), (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('users_status', GROUP_STATUS_DISABLED)
				->setArgument('usrgrpids', [$usergroup['usrgrpid']])
				->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID()
			: (new CLink(_('Disabled'), (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('users_status', GROUP_STATUS_ENABLED)
				->setArgument('usrgrpids', [$usergroup['usrgrpid']])
				->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
	}
	else {
		$gui_access = new CSpan($gui_access);
		$user_status = ($usergroup['users_status'] == GROUP_STATUS_ENABLED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}

	if ($usergroup['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
		$gui_access->addClass(ZBX_STYLE_ORANGE);
	}
	elseif ($usergroup['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
		$gui_access->addClass(ZBX_STYLE_RED);
	}
	else {
		$gui_access->addClass(ZBX_STYLE_GREEN);
	}

	$users = [];
	foreach ($usergroup['users'] as $user) {
		if ($users) {
			$users[] = ', ';
		}

		$user_has_access = ($user['gui_access'] != GROUP_GUI_ACCESS_DISABLED
			&& $user['users_status'] != GROUP_STATUS_DISABLED
		);

		$user = $data['allowed_ui_users']
			? (new CLink(getUserFullname($user), (new CUrl('zabbix.php'))
				->setArgument('action', 'user.edit')
				->setArgument('userid', $user['userid'])
				->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ALT)
			: new CSpan(getUserFullname($user));

		$users[] = $user->addClass($user_has_access ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
	}

	if (count($usergroup['users']) != $usergroup['user_cnt']) {
		$users[] = ' &hellip;';
	}

	$name = new CLink($usergroup['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'usergroup.edit')
		->setArgument('usrgrpid', $usergroup['usrgrpid'])
		->getUrl()
	);

	$table->addRow([
		new CCheckBox('usrgrpids['.$usergroup['usrgrpid'].']', $usergroup['usrgrpid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			$data['allowed_ui_users']
				? new CLink(_('Users'), (new CUrl('zabbix.php'))
					->setArgument('action', 'user.list')
					->setArgument('filter_usrgrpid', $usergroup['usrgrpid'])
					->getUrl()
				)
				: _('Users'),
			CViewHelper::showNum($usergroup['user_cnt'])
		],
		$users,
		$gui_access,
		$debug_mode,
		$user_status
	]);
}

$form->addItem([
	$table,
	$this->data['paging'],
	new CActionButtonList('action', 'usrgrpids', [
		['name' => _('Enable'), 'confirm' => _('Enable selected groups?'),
			'redirect' => (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('users_status', GROUP_STATUS_ENABLED)
				->getUrl()
		],
		['name' => _('Disable'), 'confirm' => _('Disable selected groups?'),
			'redirect' => (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('users_status', GROUP_STATUS_DISABLED)
				->getUrl()
		],
		['name' => _('Enable debug mode'), 'confirm' => _('Enable debug mode in selected groups?'),
			'redirect' => (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('debug_mode', GROUP_DEBUG_MODE_ENABLED)
				->getUrl()
		],
		['name' => _('Disable debug mode'), 'confirm' => _('Disable debug mode in selected groups?'),
			'redirect' => (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.massupdate')
				->setArgument('debug_mode', GROUP_DEBUG_MODE_DISABLED)
				->getUrl()
		],
		'usergroup.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected groups?')]
	], 'usergroup')
]);

$widget->addItem($form);
$widget->show();
