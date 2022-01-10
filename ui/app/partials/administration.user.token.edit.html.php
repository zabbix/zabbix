<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 * @var array    $data
 */

$this->includeJsFile('administration.user.token.edit.html.js.php');

$token_form = (new CForm())
	->setId('token_form')
	->setName('token')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('userid', CWebUser::$data['userid'])
	->addVar('action_src', 'user.token.edit')
	->addVar('action_dst', ($data['tokenid'] == 0) ? 'user.token.view' : 'user.token.list')
	->addVar('action', ($data['tokenid'] == 0) ? 'token.create' : 'token.update')
	->addVar('tokenid', $data['tokenid']);

$token_from_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name'], false, DB::getFieldLength('token', 'name')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		(new CTextArea('description', $data['description']))
			->addClass(ZBX_STYLE_MONOSPACE_FONT)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxLength(DB::getFieldLength('token', 'description'))
			->setAriaRequired()
	])
	->addItem([
		new CLabel(_('Set expiration date and time'), 'expires_state'),
		new CFormField(
			(new CCheckBox('expires_state', '1'))
				->setChecked($data['expires_state'])
				->setUncheckedValue('0')
				->onChange('window.user_token_edit.showHide();')
		)
	])
	->addItem([
		(new CLabel(_('Expires at')))->setAsteriskMark(),
		(new CDateSelector('expires_at', $data['expires_at']))
			->setDateFormat(DATE_TIME_FORMAT_SECONDS)
			->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			->setAriaRequired()
			->setId('expires-at-row')
		])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', ZBX_AUTH_TOKEN_ENABLED))
				->setChecked($data['status'] == ZBX_AUTH_TOKEN_ENABLED)
				->setUncheckedValue(ZBX_AUTH_TOKEN_DISABLED)
		)

	]);

$token_view = (new CTabView())->addTab('token', '', $token_from_grid);

$token_form
	->addItem($token_view)
	->show();
