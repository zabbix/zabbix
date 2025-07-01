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

$media_table_info = (new CTable())
	->setId('media-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), _('Status'), _('Actions')]);

$media_add_button = (new CButtonLink(_('Add')))
	->setEnabled($data['can_edit_media'])
	->addClass('js-add');

$media_form_list->addRow(_('Media'),
	(new CDiv([$media_table_info, $media_add_button]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: ' . ZBX_TEXTAREA_BIG_WIDTH . 'px;')
);

$media_severity = [];

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$severity_name = CSeverityHelper::getName($severity);
	$media_severity[$severity] = (new CSpan(mb_substr($severity_name, 0, 1)))
		->setHint($severity_name, '', false)
		->addClass(CSeverityHelper::getStatusStyle($severity));
}

$media_table_info_template = new CTemplateTag('media-row-tmpl',
	(new CRow([
		[
			new CSpan('#{mediatype_name}'),
			makeWarningIcon(_('Media type disabled by Administration.'))
		],
		(new CSpan('#{sendto_short}')),
		(new CDiv('#{period}'))
			->setAttribute('style', 'max-width: ' . ZBX_TEXTAREA_STANDARD_WIDTH . 'px;')
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
		(new CDiv($media_severity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
		[
			($data['can_edit_media'] ? new CButtonLink(_('Enabled')) : new CSpan(_('Enabled')))
				->addClass(ZBX_STYLE_COLOR_POSITIVE)
				->addClass('js-status'),
			(new CSpan(_('Disabled')))->addClass(ZBX_STYLE_COLOR_NEGATIVE)
		],
		(new CHorList([
			(new CButtonLink(_('Edit')))
				->setEnabled($data['can_edit_media'])
				->addClass('js-edit'),
			(new CButtonLink(_('Remove')))
				->setEnabled($data['can_edit_media'])
				->addClass('js-remove'),
			(new CDiv([
				new CInput('hidden', 'medias[#{row_index}][mediaid]', '#{mediaid}'),
				new CInput('hidden', 'medias[#{row_index}][mediatypeid]', '#{mediatypeid}'),
				new CInput('hidden', 'medias[#{row_index}][period]', '#{period}'),
				new CInput('hidden', 'medias[#{row_index}][sendto]', '#{sendto}'),
				new CInput('hidden', 'medias[#{row_index}][severity]', '#{severity}'),
				new CInput('hidden', 'medias[#{row_index}][active]', '#{active}'),
				new CInput('hidden', 'medias[#{row_index}][provisioned]', '#{provisioned}')
			]))
		]))
	]))->setAttribute('data-row_index', '#{row_index}')
		->setId('medias_#{row_index}')
);

(new CScriptTag('media_tab.init('.json_encode([
	'userid' => $data['userid'],
	'medias' => $data['medias']
]).');'))
	->setOnDocumentReady()
	->show();

echo (new CDiv([$media_form_list, $media_table_info_template]));
