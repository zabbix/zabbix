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

$this->includeJsFile('configuration.templategroup.edit.js.php');

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'templategroup.list')))
	->addClass('js-cancel');

$data += [
	'buttons' => ($data['groupid'] == 0)
		? [
			(new CSubmit('add', _('Add')))->addClass('js-create-templategroup'),
			$cancel_button
		]
		: [
			(new CSubmit('update', _('Update')))->addClass('js-update-templategroup'),
			(new CSimpleButton(_('Clone')))
				->addClass('js-clone-templategroup')
				->setEnabled(CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			(new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected template group?'))
				->addClass('js-delete-templategroup'),
			$cancel_button
		]
];

(new CWidget())
	->setTitle(($data['groupid'] == 0) ? _('New template group') : _('Template group'))
	->addItem(new CPartial('configuration.templategroup.edit.html', $data))
	->show();

(new CScriptTag('view.init('.json_encode([
	'groupid' => $data['groupid'],
	'name' => $data['name']
]).');'))
	->setOnDocumentReady()
	->show();
