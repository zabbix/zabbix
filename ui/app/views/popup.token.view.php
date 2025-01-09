<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CPartial $this
 * @var array $data
 */

$token_form = (new CForm())
	->setId('token_form')
	->setName('token')
	->addItem(makeMessageBox(ZBX_STYLE_MSG_GOOD, [], $data['message']));

$token_from_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Name').':'),
		new CFormField($data['name'])
	])
	->addItem($data['admin_mode'] === '1'
		? [
			new CLabel(_('User').':'),
			new CFormField($data['user_name'])
		]
		: null
	)
	->addItem([
		new CLabel(_('Auth token').':'),
		new CFormField([
			new CSpan($data['auth_token']),
			makeWarningIcon(
				_("Make sure to copy the auth token as you won't be able to view it after the page is closed.")
			),
			(new CButtonLink(_('Copy to clipboard')))
				->onClick('writeTextClipboard(this.dataset.auth_token);')
				->setAttribute('data-auth_token', $data['auth_token'])
				->setAttribute('autofocus', 'autofocus')
		])
	])
	->addItem([
		new CLabel(_('Expires at').':'),
		new CFormField([
			new CSpan($data['expires_at'] == 0 ? '-' : date(DATE_TIME_FORMAT_SECONDS, (int) $data['expires_at'])),
			$data['expires_at'] != 0 && time() > $data['expires_at']
				? makeErrorIcon(_('The token has expired. Please update the expiry date to use the token.'))
				: null
		])
	])
	->addItem([
		new CLabel(_('Description').':'),
		(new CFormField($data['description']))->addClass(ZBX_STYLE_WORDBREAK)
	])
	->addItem([
		new CLabel(_('Enabled').':'),
		new CFormField((new CCheckBox('enabled'))
			->setChecked($data['status'] == ZBX_AUTH_TOKEN_ENABLED)
			->setEnabled(false)
		)
	]);

$token_form->addItem($token_from_grid);

$output = [
	'title' => ('API token'),
	'content' => $token_form->toString(),
	'buttons' => [[
		'title' => _('Close'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'token_edit_popup.close();'
	]]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
