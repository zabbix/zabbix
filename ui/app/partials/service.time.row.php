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
 * @var CPartial $this
 */

switch ($data['type']) {
	case SERVICE_TIME_TYPE_UPTIME:
		$type = (new CSpan(_('Uptime')))->addClass('enabled');
		$from = dowHrMinToStr($data['ts_from']);
		$till = dowHrMinToStr($data['ts_to'], true);
		break;

	case SERVICE_TIME_TYPE_DOWNTIME:
		$type = (new CSpan(_('Downtime')))->addClass('disabled');
		$from = dowHrMinToStr($data['ts_from']);
		$till = dowHrMinToStr($data['ts_to'], true);
		break;

	case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
		$type = (new CSpan(_('One-time downtime')))->addClass('disabled');
		$from = zbx_date2str(DATE_TIME_FORMAT, $data['ts_from']);
		$till = zbx_date2str(DATE_TIME_FORMAT, $data['ts_to']);
		break;
}

(new CRow([
	[
		$type,
		new CVar('times['.$data['row_index'].'][type]', $data['type']),
		new CVar('times['.$data['row_index'].'][ts_from]', $data['ts_from']),
		new CVar('times['.$data['row_index'].'][ts_to]', $data['ts_to']),
		new CVar('times['.$data['row_index'].'][note]', $data['note'])
	],
	$from.' - '.$till,
	(new CCol($data['note']))
		->addClass(ZBX_STYLE_WORDWRAP)
		->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
	new CHorList([
		(new CSimpleButton(_('Edit')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('js-edit'),
		(new CSimpleButton(_('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('js-remove')
	])
]))
	->setAttribute('data-row_index', $data['row_index'])
	->show();
