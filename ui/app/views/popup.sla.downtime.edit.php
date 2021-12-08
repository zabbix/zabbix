<?php declare(strict_types=1);
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
	->setId('downtime-form')
	->addVar('action', 'sla.downtime.edit')
	->addVar('row_index', $data['form']['row_index']);

if (array_key_exists('sla_excluded_downtimeid', $data['form'])) {
	$title = _('Excluded downtime');
}
else {
	$title = _('New excluded downtime');
}

$fields = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('name', $data['form']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
	])
	->addItem([
		(new CLabel(_('Start time'), 'start_time'))->setAsteriskMark(),
		new CFormField(
			(new CDateSelector('start_time', $data['form']['start_time']))
				->setDateFormat(DATE_TIME_FORMAT_SECONDS)
				->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Duration'), 'duration_days'))->setAsteriskMark(),
		(new CFormField([
			(new CTextBox('duration_days', $data['form']['duration_days']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
			' ',
			(new CLabel(_('Days'), 'duration_days')),
			' &nbsp; ',
			(new CSelect('duration_hours'))
				->addOptions(CSelect::createOptionsFromArray(range(0, 23)))
				->setValue($data['form']['duration_hours']),
			' ',
			(new CLabel(_('Hours'), 'duration_hours')),
			' &nbsp; ',
			(new CSelect('duration_minutes'))
				->addOptions(CSelect::createOptionsFromArray(range(0, 59)))
				->setValue($data['form']['duration_minutes']),
			' ',
			(new CLabel(_('Minutes'), 'duration_minutes'))
		]))
	]);

$form->addItem([
	$fields,
	(new CInput('submit', 'submit'))->addStyle('display: none;')
]);

$output = [
	'header' => $title,
	'body' =>  (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => $data['buttons'],
	'script_inline' => 'sla_edit.downtime.init('.json_encode([
		'update_url' => (new CUrl('zabbix.php'))->setArgument('action', 'popup.sla.downtime.update')->getUrl()
	]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
