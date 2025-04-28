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
 * @var CView $this
 * @var array $data
 */

$form = (new CForm('post'))
	->addVar('mediatypeid', $data['mediatypeid'])
	->addVar('advanced_form', $data['advanced_form'])
	->addVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('oauth'))
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$buttons = [
	[
		'title' => $data['update'] ? _('Update') : _('Add'),
		'class' => 'js-add',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'oauth_edit_popup.submit();'
	]
];

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel([
			_('Redirection endpoint'),
			makeHelpIcon([
				_('Destination URL where successful authorization redirects.'),
				BR(),
				_('The URL must comply with the OAuth provider\'s policy.')
			])
		], 'redirection_url'))
			->setId('oauth-redirection-label')
			->setAsteriskMark(),
		(new CFormField([
			(new CTextBox('redirection_url', $data['redirection_url']))
				->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'redirection_url'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton())
				->removeId()
				->setTitle(_('Copy to clipboard'))
				->addClass(ZBX_ICON_COPY)
				->addClass(ZBX_STYLE_BTN_GREY_ICON)
				->addClass('js-copy-button')
		]))->setId('oauth-redirection-field')
	])
	->addItem([
		(new CLabel([
			_('Client ID'),
			makeHelpIcon(_('The client identifier registered within the authorization server.'))
		], 'client_id'))
			->setAsteriskMark()
			->setId('oauth-clientid-label'),
		(new CFormField(
			(new CTextBox('client_id', $data['client_id']))
				->disableAutocomplete()
				->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'client_id'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('oauth-clientid-field')
	]);

if (array_key_exists('client_secret', $data)) {
	$client_secrect = [
		(new CTextBox('client_secret', $data['client_secret']))
			->disableAutocomplete()
			->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'client_secret'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	];
}
else {
	$client_secrect = [
		(new CSimpleButton(_('Change client secret')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->setName('client_secret_button'),
		(new CTextBox('client_secret', ''))
			->disableAutocomplete()
			->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'client_secret'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->addStyle('display: none;')
			->setEnabled(false)
	];
}

$form_grid->addItem([
	(new CLabel([
		_('Client secret'),
		makeHelpIcon(_('The client secret registered within the authorization server.'))
	], 'client_secret'))
		->setAsteriskMark()
		->setId('oauth-client-secret-label'),
	(new CFormField($client_secrect))->setId('oauth-client-secret-field')
]);

if ($data['advanced_form']) {
	$form->addItem(
		new CTemplateTag('oauth-parameter-row-tmpl',
			(new CRow([
				(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CTextBox('#{input_name}_parameters[#{rowNum}][name]', '#{name}', false))
					->setAttribute('style', 'width: 100%;')
					->removeId(),
				RARR(),
				(new CTextBox('#{input_name}_parameters[#{rowNum}][value]', '#{value}', false))
					->setAttribute('style', 'width: 100%;')
					->removeId(),
				(new CButtonLink(_('Remove')))
					->addClass('element-table-remove')
			]))->addClass('form_row')->addStyle('')
		)
	);
	$form_grid->addItem([
		(new CLabel([
			_('Authorization endpoint'),
			makeHelpIcon(_('Authorization server URL for requesting user authorization.'))
		], 'authorization_url'))
			->setAsteriskMark()
			->setId('oauth-auth-endpoint-label'),
		(new CFormField([
			(new CTextBox('authorization_url', $data['authorization_url']))
				->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'authorization_url'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		]))->setId('oauth-auth-endpoint-field')
	])
	->addItem([
		(new CLabel(_('Authorization parameters'), 'oauth-auth-parameters-table'))
			->setId('oauth-auth-parameters-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setHeader(['', _('Name'), '', _('Value'), ''])
					->setId('oauth-auth-parameters-table')
					->setFooter((new CCol(
						(new CButtonLink(_('Add')))
							->addClass('element-table-add')
						))->setColSpan(5)
					)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px; vertical-align: top;')
		))->setId('oauth-auth-parameters-field')
	])
	->addItem([
		(new CLabel([
			_('Authorization code'),
			makeHelpIcon([
				_('Temporary token to exchange for an access token.'),
				BR(),
				_('Select retrieval method: automatically through a redirection page or manually if automatic retrieval fails.')
			])
		], 'authorization_mode'))->setId('oauth-authorization-code-label'),
		(new CFormField([
			(new CRadioButtonList('authorization_mode', 'auto'))
				->addValue(_('Automatic'), 'auto')
				->addValue(_('Manual'), 'manual')
				->setModern(),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('code', ''))
				->disableAutocomplete()
				->setAttribute('placeholder', _('Authorization code'))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		]))->setId('oauth-authorization-code-field')
	])
	->addItem([
		(new CLabel([
			_('Token endpoint'),
			makeHelpIcon(_('Authorization server URL to exchange the authorization code for an access token.'))
		], 'token_url'))
			->setAsteriskMark()
			->setId('oauth-token-endpoint-label'),
		(new CFormField(
			(new CTextBox('token_url', $data['token_url']))
				->setAttribute('maxlength', DB::getFieldLength('media_type_oauth', 'token_url'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('oauth-token-endpoint-field')
	])
	->addItem([
		(new CLabel(_('Token parameters'), 'oauth-token-parameters-table'))->setId('oauth-token-parameters-label'),
		(new CFormField(
			(new CDiv([
				(new CTable())
					->setHeader(['', _('Name'), '', _('Value'), ''])
					->setId('oauth-token-parameters-table')
					->setFooter((new CCol(
						(new CButtonLink(_('Add')))
							->addClass('element-table-add')
						))->setColSpan(5)
					)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px; vertical-align: top;')
		))->setId('oauth-token-parameters-field')
	]);
}
else {
	$form->addVar('authorization_url', $data['authorization_url']);
	$form->addVar('token_url', $data['token_url']);
}

$form_grid->addItem(
	(new CScriptTag('
		oauth_edit_popup.init('.json_encode([
			'is_advanced_form' => $data['advanced_form'] == 1,
			'messages' => [
				'popup_closed' => _('Complete authentication to get tokens.'),
				'popup_blocked_error' => _('Cannot open authorization popup window.'),
				'authorization_error' => _('Cannot get authorization code.')
			]
		]) .');
	'))->setOnDocumentReady()
);

$output = [
	'header' => $data['update'] ? _('OAuth') : _('New oauth'),
	'body' => $form->addItem($form_grid)->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('oauth.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
