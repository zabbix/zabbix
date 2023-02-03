<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$form_action = (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.ldap.test.send')
	->getUrl();

$formgrid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Login'), 'test_username'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('test_username', $data['test_username']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('User password'), 'test_password'))->setAsteriskMark(),
		new CFormField(
			(new CPassBox('test_password'))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
				->setAriaRequired()
		)
	]);

if ($data['ldap_config']['provision_status'] == JIT_PROVISIONING_ENABLED) {
	$formgrid
		->addItem([
			new CLabel(_('User role')),
			(new CFormField((new CSpan(_('No value')))->addClass(ZBX_STYLE_DISABLED)))->setId('provisioning_role')
		])
		->addItem([
			new CLabel(_('User groups')),
			(new CFormField((new CSpan(_('No value')))->addClass(ZBX_STYLE_DISABLED)))->setId('provisioning_groups')
		])
		->addItem([
			new CLabel(_('Media type')),
			(new CFormField((new CSpan(_('No value')))->addClass(ZBX_STYLE_DISABLED)))->setId('provisioning_medias')
		]);
}

$form = (new CForm('post', $form_action))
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ldap')))->removeId())
	->addItem((new CInput('submit'))->addStyle('display: none;'))
	->addItem($formgrid)
	->addItem((new CScriptTag('ldap_test_edit_popup.init();'))->setOnDocumentReady());

foreach ($data['ldap_config'] as $field => $value) {
	$form->addVar($field, $value);
}

$output = [
	'header' => _('Test authentication'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'ldap_test_edit_popup.submit();'
		]
	],
	'script_inline' => $this->readJsFile('popup.ldap.test.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
