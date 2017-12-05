<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$options = $data['options'];
$severity_row = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

foreach ($data['severities'] as $severity => $severity_name) {
	$severity_row->addItem(
		(new CCheckBox('severity['.$severity.']', $severity))
			->setLabel($severity_name)
			->setChecked(str_in_array($severity, $options['severities']))
	);
}

$media_form = (new CFormList(_('Media')))
	->addRow(_('Type'), new CComboBox('mediatypeid', $options['mediatypeid'], null, $data['mediatypes']))
	->addRow(_('Send to'),
		(new CTextBox('sendto', $options['sendto'], false, 100))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('When active'),
		(new CTextBox('period', $options['period'], false, 1024))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Use if severity'), $severity_row)
	->addRow(_('Enabled'),
		(new CCheckBox('active', MEDIA_STATUS_ACTIVE))->setChecked($options['active'] == MEDIA_STATUS_ACTIVE)
	);

$body_html = (new CForm())
		->addVar('action', 'popup.media')
		->addVar('add', '1')
		->addVar('media', $options['media'])
		->addVar('dstfrm', $options['dstfrm'])
		->addItem(
			(new CTabView())->addTab('mediaTab', _('Media'), $media_form)
		)
		->setId('media_form')
		->toString();

$output = [
	'header' => $data['title'],
	'body' => $body_html,
	'buttons' => [
		[
			'title' => ($options['media'] !== -1) ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return validate_media("media_form");'
		]
	]
];

echo (new CJson())->encode($output);
