<?php declare(strict_types = 0);
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

$form = (new CForm())
	->setId('media-form')
	->setName('media_form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addVar('userid', $data['userid'])
	->addVar('provisioned', $data['provisioned'])
	->addStyle('display: none;')
	->addVar('mediaid', $data['mediaid'])
	->addVar('mediatype_type', 0)
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$mediatype_select = (new CSelect('mediatypeid'))
	->setId('mediatypeid')
	->setFocusableElementId('label-mediatypeid')
	->setValue($data['form']['mediatypeid'])
	->setReadonly($data['provisioned'] == CUser::PROVISION_STATUS_YES);

foreach ($data['mediatypes'] as $mediatypeid => $mediatype) {
	if ($mediatype['status'] == MEDIA_TYPE_STATUS_ACTIVE || $data['form']['mediatypeid'] == $mediatypeid) {
		$mediatype_select->addOption(
			(new CSelectOption($mediatypeid, $mediatype['name']))
				->addClass($mediatype['status'] == MEDIA_TYPE_STATUS_DISABLED ? ZBX_STYLE_COLOR_NEGATIVE : null)
		);
	}
}

$disabled_mediatypes_message = null;

if (!in_array(MEDIA_TYPE_STATUS_ACTIVE, array_column($data['mediatypes'], 'status'))) {
	$mediatype_select->addStyle('display: none;');

	$disabled_mediatypes_message = (new CDiv(_('Media types disabled in Alerts.')))->addClass(ZBX_STYLE_COLOR_NEGATIVE);
}

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Type'), $mediatype_select->getFocusableElementId()),
		new CFormField([
			$mediatype_select,
			$disabled_mediatypes_message
		])
	])
	->addItem([
		(new CLabel(_('Send to'), 'sendto'))
			->setAsteriskMark()
			->addClass('js-field-sendto'),
		(new CFormField(
			(new CTextAreaFlexible('sendto', $data['form']['sendto']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('media', 'sendto'))
				->setReadonly($data['provisioned'] == CUser::PROVISION_STATUS_YES)
				->setAriaRequired()
		))->addClass('js-field-sendto')
	])
	->addItem([
		(new CLabel(_('Send to')))
			->setAsteriskMark()
			->addClass('js-field-sendto-list'),
		(new CFormField([
			(new CTable())
				->setId('sendto_list')
				->setAttribute('data-field-type', 'array')
				->setAttribute('data-field-name', 'sendto_list')
				->addClass(ZBX_STYLE_TABLE_INITIAL_WIDTH)
				->setFooter(
					(new CCol(
						(new CButtonLink(_('Add')))
							->addClass('element-table-add')
							->setEnabled($data['provisioned'] == CUser::PROVISION_STATUS_NO)
					))->setColspan(2),
					'dynamic-row-control'
				),
			(new CTemplateTag('sendto-list-row-tmpl'))->addItem(
				(new CRow([
					(new CTextAreaFlexible('sendto_list[#{rowNum}]', '#{value}'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setReadonly($data['provisioned'] == CUser::PROVISION_STATUS_YES)
						->setAriaRequired(),
					(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						->setEnabled($data['provisioned'] == CUser::PROVISION_STATUS_NO)
				]))->addClass('form_row')
			)
		]))->addClass('js-field-sendto-list')
	])
	->addItem([
		(new CLabel(_('Send to')))
			->setAsteriskMark()
			->addClass('js-field-sendto-devices'),
		(new CFormField([
			(new CRadioButtonList('sendto_active_devices', $data['form']['sendto_active_devices']))
				->addValue(_('Active devices'), 1)
				->addValue(_('Selected devices'), 0)
				->setReadonly($data['provisioned'] == CUser::PROVISION_STATUS_YES)
				->setModern()
		]))->addClass('js-field-sendto-devices')
	])
	->addItem([
		'',
		(new CFormField([
			(new CMultiSelect([
				'name' => 'sendto_deviceuuids[]',
				'object_name' => 'devices',
				'readonly' => $data['provisioned'] == CUser::PROVISION_STATUS_YES,
				'data' => $data['form']['sendto_device_data'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'devices',
						'srcfld1' => 'uuid',
						'dstfrm' => 'media-form',
						'dstfld1' => 'sendto_deviceuuids_',
						'userid' => $data['userid']
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		]))->addClass('js-field-sendto-devices')
	])
	->addItem([
		(new CLabel(_('When active'), 'period'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('period', $data['form']['period'], false, DB::getFieldLength('media', 'period')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Use if severity')),
		new CFormField(
			(new CCheckBoxList('severities'))
				->setOptions(CSeverityHelper::getSeverities())
				->setChecked($data['form']['severities'])
				->setVertical()
				->showTitles()
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'active'),
		new CFormField(
			(new CCheckBox('active', MEDIA_STATUS_ACTIVE))
				->setUncheckedValue(MEDIA_STATUS_DISABLED)
				->setChecked($data['form']['active'] == MEDIA_STATUS_ACTIVE)
		)
	]);

$form->addItem($form_grid);

$output = [
	'header' => $data['is_edit'] ? _('Media') : _('New media'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'media_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().$this->readJsFile('popup.media.edit.js.php').
		'media_edit_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'mediatypes' => $data['mediatypes'],
			'sendto_list' => $data['form']['sendto_list']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
