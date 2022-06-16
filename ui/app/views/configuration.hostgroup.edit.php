<?php declare(strict_types = 0);
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
 * @var array $data
 */

$this->includeJsFile('configuration.hostgroup.edit.js.php');

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'hostgroup.list')))
	->addClass('js-cancel');

$data += [
	'buttons' => ($data['groupid'] == 0)
		? [
			(new CSubmit('add', _('Add')))->addClass('js-create-hostgroup'),
			$cancel_button
		]
		: [
			(new CSubmit('update', _('Update')))->addClass('js-update-hostgroup'),
			(new CSimpleButton(_('Clone')))
				->addClass('js-clone-hostgroup')
				->setEnabled(CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			(new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected host group?'))
				->addClass('js-delete-hostgroup'),
			$cancel_button
		]
];

(new CWidget())
	->setTitle(($data['groupid'] == 0) ? _('New host group') : _('Host group'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_HOSTGROUPS_EDIT))
	->addItem(new CPartial('configuration.hostgroup.edit.html', $data))
	->show();

(new CScriptTag('view.init('.json_encode([
	'groupid' => $data['groupid'],
	'name' => $data['name']
]). ');'))
	->setOnDocumentReady()
	->show();
