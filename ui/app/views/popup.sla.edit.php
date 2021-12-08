<?php declare(strict_types = 1);
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
 * @var array $data
 */

$popup_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'services.sla.edit');

if (!array_key_exists('slaid', $data['form'])) {
	$title = _('New SLA');

	if ($data['clone'] !== null) {
		$popup_url->setArgument('clone', $data['clone']);
	}
}
else {
	$title = _('SLA');
	$popup_url->setArgument('id', $data['form']['slaid']);
}

$output = [
	'header' => $title,
	'body' => (new CPartial('services.sla.edit.partial', $data))->getOutput(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('services.sla.edit.js.php').
		'sla_edit.init('.json_encode([
			'service_tags' => $data['form']['service_tags'],
			'excluded_downtimes' => $data['form']['excluded_downtimes']
		]).');',
	'buttons' => $data['buttons']
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
