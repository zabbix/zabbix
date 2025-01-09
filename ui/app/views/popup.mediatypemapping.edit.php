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

$form_action = (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.mediatypemapping.check')
	->getUrl();

$form = (new CForm('post', $form_action))
	->setId('media-type-mapping-edit-form')
	->setName('media-type-mapping-edit-form');

if (array_key_exists('userdirectory_mediaid', $data)) {
	$form->addVar('userdirectory_mediaid', $data['userdirectory_mediaid']);
}

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$media_type_select = (new CSelect('mediatypeid'))
	->setId('mediatypeid')
	->setFocusableElementId('label-mediatypeid')
	->addOptions(CSelect::createOptionsFromArray($data['db_mediatypes']))
	->setValue($data['mediatypeid']);

$form
	->addItem((new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'media-type-mapping-name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['name'], false, DB::getFieldLength('userdirectory_media', 'name')))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('media-type-mapping-name')
			)
		])
		->addItem([
			(new CLabel(_('Media type'), $media_type_select->getFocusableElementId()))->setAsteriskMark(),
			new CFormField($media_type_select)
		])
		->addItem([
			(new CLabel(_('Attribute'), 'attribute'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('attribute', $data['attribute'], false,
					DB::getFieldLength('userdirectory_media', 'attribute')
				))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setId('attribute')
			)
		])
		->addItem([
			new CLabel(_('User media')),
			new CFormField((new CFormGrid([
				[
					(new CLabel(_('When active'), 'period'))->setAsteriskMark(),
					new CFormField((new CTextBox('period', $data['period'], false,
							DB::getFieldLength('userdirectory_media', 'period')
						))
							->setAriaRequired()
							->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
					)
				],
				[
					new CLabel(_('Use if severity')),
					new CFormField(
						(new CCheckBoxList('severity'))
							->setOptions(CSeverityHelper::getSeverities())
							->setChecked($data['severities'])
							->setVertical(true)
					)
				],
				[
					new CLabel(_('Create enabled'), 'active'),
					new CFormField(
						(new CCheckBox('active', MEDIA_STATUS_ACTIVE))
							->setChecked($data['active'] == MEDIA_STATUS_ACTIVE)
					)
				]
			]))->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP))
		])
	)
	->addItem(
		(new CScriptTag('
			media_type_mapping_edit_popup.init();
		'))->setOnDocumentReady()
	);

if ($data['add_media_type_mapping']) {
	$title = _('New media type mapping');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'media_type_mapping_edit_popup.submit();'
		]
	];
}
else {
	$title = _('Media type mapping');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'media_type_mapping_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'script_inline' => $this->readJsFile('popup.mediatypemapping.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => $buttons
];

if (($messages = getMessages()) !== null) {
	$output['errors'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
