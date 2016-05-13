<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Web monitoring'))
	->setControls((new CForm('get'))
		->addVar('fullscreen', $data['fullscreen'])
		->addVar('action', 'web.view')
		->addItem((new CList())
			->addItem([_('Group'), SPACE, $data['pageFilter']->getGroupsCB()])
			->addItem([_('Host'), SPACE, $data['pageFilter']->getHostsCB()])
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	);

$table = (new CTableInfo())
	->setHeader([
		$data['hostid'] == 0 ? make_sorting_header(_('Host'), 'hostname', $data['sort'], $data['sortorder']) : null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder']),
		_('Number of steps'),
		_('Last check'),
		_('Status')
	]);

foreach ($data['httptests'] as $httptest) {
	if ($data['hostid'] == 0) {
		$hostname = $httptest['hostname'];
		if ($httptest['host']['status'] == HOST_STATUS_NOT_MONITORED) {
			$hostname = (new CSpan($hostname))->addClass(ZBX_STYLE_RED);
		};
	}
	else {
		$hostname = null;
	}

	if (isset($httptest['lastfailedstep']) && $httptest['lastfailedstep'] !== null) {
		$lastcheck = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $httptest['lastcheck']);

		if ($httptest['lastfailedstep'] != 0) {
			$httpstep = get_httpstep_by_no($httptest['httptestid'], $httptest['lastfailedstep']);
			$error = ($httptest['error'] === null) ? _('Unknown error') : $httptest['error'];

			if ($httpstep) {
				$status = new CSpan(_s(
					'Step "%1$s" [%2$s of %3$s] failed: %4$s',
					$httpstep['name'], $httptest['lastfailedstep'], $httptest['steps'], $error
				));
			}
			else {
				$status = new CSpan(_s('Unknown step failed: %1$s', $error));
			}
			$status->addClass(ZBX_STYLE_RED);
		}
		else {
			$status = (new CSpan(_('OK')))->addClass(ZBX_STYLE_GREEN);
		}
	}
	// no history data exists
	else {
		$lastcheck = (new CSpan(_('Never')))->addClass(ZBX_STYLE_RED);
		$status = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_GREY);
	}

	$table->addRow(new CRow([
		$hostname,
		new CLink($httptest['name'], 'httpdetails.php?httptestid='.$httptest['httptestid']),
		$httptest['steps'],
		$lastcheck,
		$status
	]));
}

$widget->addItem([$table, $data['paging']])->show();
