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
	->addItem(getMessages())
	->addVar('admin_mode', $data['admin_mode']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['admin_mode']) {
	$form_grid = (new CFormGrid())
		->addItem([
			(new CLabel(_('Username'), 'username'))->setAsteriskMark(),
			new CFormField(
				(new CMultiSelect([
					'name' => 'userid',
					'object_name' => 'users',
					'multiple' => false,
					'popup' => [
						'parameters' => [
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => $form->getName(),
							'dstfld1' => 'userid'
						]
					]
				]))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		]);

	$form->addItem($form_grid);
}

$form->addItem(
	(new CDiv())->addClass('qr-code')
);

$title = _('Select user to link a device');
$buttons = [
	[
		'title' => _('Link device'),
		'class' => 'js-submit',
		'keepOpen' => true,
		'isSubmit' => true
	]
];

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_CONNECTOR_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('../../../js/vendors/qrcode/qrcode.js').
		$this->readJsFile('user.device.create.js.php').
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
