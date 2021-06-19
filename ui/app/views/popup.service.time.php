<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */

$form = (new CForm())
	->cleanItems()
	->setId('time-form')
	->setName('time-form')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addItem(
		(new CInput('submit', 'submit'))
			->addStyle('display: none;')
			->removeId()
	);

if ($data['edit']) {
	$form->addVar('edit', $data['edit']);
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Period type'), 'label-period-type')),
		(new CSelect('type'))
			->setId('period-type')
			->setFocusableElementId('label-period-type')
			->setValue($data['type'])
			->addOptions(CSelect::createOptionsFromArray([
				SERVICE_TIME_TYPE_UPTIME => _('Uptime'),
				SERVICE_TIME_TYPE_DOWNTIME => _('Downtime'),
				SERVICE_TIME_TYPE_ONETIME_DOWNTIME => _('One-time downtime')
			]))
	]);

if ($data['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
	$form_grid
		->addItem([
			new CLabel(_('Note')),
			(new CTextBox('note'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('short description'))
		])
		->addItem([
			(new CLabel(_('From'), 'time_from'))->setAsteriskMark(),
			(new CDateSelector('time_from', $data['time_from']))
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
		])
		->addItem([
			(new CLabel(_('Till'), 'time_till'))->setAsteriskMark(),
			(new CDateSelector('time_till', $data['time_till']))
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
		]);
}

$form->addItem($form_grid);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'script_inline' => $this->readJsFile('popup.service.time.js.php'),
	'buttons' => [
		[
			'title' => $data['edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitServiceTime(overlay);'
		]
	]
];

if (($messages = getMessages()) !== null) {
	$output['errors'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
