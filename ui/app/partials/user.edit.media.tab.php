<?php
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

$this->includeJsFile('user.edit.media.tab.js.php');

$form = $this->data['form'];

$media_form_list = new CFormList('userMediaFormList');
$form->addVar('medias', $data['medias']);

$media_table_info = (new CTable())
	->setId('media-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), _('Status'), _('Actions')]);

foreach ($data['medias'] as $index => $media) {
	if (!array_key_exists($media['mediatypeid'], $data['mediatypes'])) {
		$media_name = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_DISABLED);
		$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}
	elseif ($data['mediatypes'][$media['mediatypeid']]['status'] == MEDIA_TYPE_STATUS_ACTIVE) {
		$media_name = $media['name'];

		if ($media['active'] == MEDIA_STATUS_ACTIVE) {
			$status = (new CButtonLink(_('Enabled')))
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-status')
				->setAttribute('data-status_type', 'disable_media');
		}
		else {
			$status = (new CButtonLink(_('Disabled')))
				->addClass(ZBX_STYLE_RED)
				->addClass('js-status')
				->setAttribute('data-status_type', 'enable_media');
		}
	}
	else {
		$media_name = [
			new CSpan($media['name']),
			makeWarningIcon(_('Media type disabled by Administration.'))
		];
		$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}

	$parameters = [
		'dstfrm' => $form->getName(),
		'media' => $index,
		'mediatypeid' => $media['mediatypeid'],
		'period' => $media['period'],
		'severity' => $media['severity'],
		'active' => $media['active'],
		'provisioned' => $media['provisioned']
	];

	if ($media['mediatype'] === MEDIA_TYPE_EMAIL) {
		$parameters['sendto_emails'] = $media['sendto'];
	}
	else {
		if (is_array($media['sendto'])) {
			$media['sendto'] = implode(', ', $media['sendto']);
		}
		$parameters['sendto'] = $media['sendto'];
	}

	$media_severity = [];

	if (array_key_exists('mediaid', $media)) {
		$parameters['mediaid'] = $media['mediaid'];
	}

	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$severity_name = CSeverityHelper::getName($severity);

		$media_active = ($media['severity'] & (1 << $severity));

		$media_severity[$severity] = (new CSpan(mb_substr($severity_name, 0, 1)))
			->setHint($severity_name . ' (' . ($media_active ? _('on') : _('off')) . ')', '', false)
			->addClass($media_active
				? CSeverityHelper::getStatusStyle($severity)
				: ZBX_STYLE_STATUS_DISABLED
			);
	}

	if (is_array($media['sendto'])) {
		$media['sendto'] = implode(', ', $media['sendto']);
	}

	if (mb_strlen($media['sendto']) > 50) {
		$media['sendto'] = (new CSpan(mb_substr($media['sendto'], 0, 50) . '...'))->setHint($media['sendto']);
	}

	$media_table_info->addRow(
		(new CRow([
			$media_name,
			$media['sendto'],
			(new CDiv($media['period']))
				->setAttribute('style', 'max-width: ' . ZBX_TEXTAREA_STANDARD_WIDTH . 'px;')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CDiv($media_severity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
			$status,
			(new CCol(
				new CHorList([
					(new CButtonLink(_('Edit')))
						->setAttribute('data-parameters', $parameters)
						->addClass('js-edit'),
					(new CButtonLink(_('Remove')))
						->setEnabled($parameters['provisioned'] == CUser::PROVISION_STATUS_NO)
						->addClass('js-remove')
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('medias_' . $index)
	);
}

$media_form_list->addRow(_('Media'),
	(new CDiv([
		$media_table_info,
		(new CButtonLink(_('Add')))
			->addClass('js-add')
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: ' . ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);



(new CScriptTag('mediaView.init('.json_encode([
		'form_name' => $form->getName(),
	]).');'))
	->setOnDocumentReady()
	->show();

echo $media_form_list;
