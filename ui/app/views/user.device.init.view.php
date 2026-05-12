<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('user')))->removeId())
	->setId('user-device-form')
	->setName('user_device_form')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid());

if ($data['admin_mode']) {
	$form_grid = (new CFormGrid())
		->addItem([
			(new CLabel(_('User'), 'userid_ms'))->setAsteriskMark(),
			new CFormField(
				(new CMultiSelect([
					'name' => 'userid',
					'object_name' => 'users',
					'multiple' => false,
					'data' => $data['ms_user'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'userid',
							'has_devices_access' => 1
						]
					]
				]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		]);
}
else {
	$form->addVar('userid', CWebUser::$data['userid']);
	$form_grid->addStyle('display: none');
}

$form->addItem($form_grid);

$form->addItem(
	(new CDiv([
		(new CDiv([
			(new CDiv([
				(new CSpan())->addClass('is-loading')
			])),
			new CDiv(_('Generating secure QR code...'))
		]))
			->addClass('js-qr-code-loading')
			->addStyle($data['admin_mode'] ? 'display: none' : ''),
		(new CDiv([
			(new CDiv())->addClass('qr-code'),
			new CDiv(_('Scan this QR code to add your device and setup your notifications.')),
			(new CDiv())->addClass('qr-code-expiration'),
			(new CTemplateTag('qr-expires-in-tmpl'))
				->addItem(_('QR code will expire in'))
				->addItem((new CTag('b'))->addItem(' #{expires_in}.')),
			(new CTemplateTag('qr-expired-tmpl'))->addItem(_('QR code has expired.'))
		]))
			->addClass('js-qr-code-wrapper')
			->addStyle('display: none')
	]))
		->addClass('qr-code-container')
);

$title = $data['admin_mode'] ? _('Select user to add device') : _('Add device');
$buttons = [];

if ($data['admin_mode']) {
	$buttons[] = [
		'title' => _('Add device'),
		'class' => 'js-submit',
		'keepOpen' => true,
		'isSubmit' => true
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::USERS_DEVICE),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('../../../js/vendors/qrcode/qrcode.js').
		$this->readJsFile('user.device.init.view.js.php').
		'user_device_create_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'admin_mode' => $data['admin_mode']
		]).');',
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
