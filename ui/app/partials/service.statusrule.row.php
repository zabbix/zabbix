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

(new CRow([
	[
		CServiceHelper::getRuleByCondition((int) $data['new_status'], (int) $data['type'], (int) $data['limit_value'],
			(int) $data['limit_status']
		),
		new CVar('status_rules['.$data['row_index'].'][new_status]', $data['new_status']),
		new CVar('status_rules['.$data['row_index'].'][type]', $data['type']),
		new CVar('status_rules['.$data['row_index'].'][limit_value]', $data['limit_value']),
		new CVar('status_rules['.$data['row_index'].'][limit_status]', $data['limit_status'])
	],
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
