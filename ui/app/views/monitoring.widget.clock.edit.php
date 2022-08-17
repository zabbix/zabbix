<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * System information widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldSelectView($data['fields']['time_type'])
	)
	->addField(array_key_exists('itemid', $data['fields'])
		? new CWidgetFieldMultiSelectItemView($data['fields']['itemid'], $data['captions']['ms']['items']['itemid'])
		: null
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['clock_type'])
	)
	->addField(
		new CWidgetFieldCheckBoxListView($data['fields']['show']),
		'js-row-show'
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['adv_conf']),
		'js-row-adv-conf'
	)
	->addField(
		new CWidgetFieldColorView($data['fields']['bg_color']),
		'js-row-bg-color'
	)
	->addFieldGroup(_('Date'), [
		new CWidgetFieldColorView($data['fields']['tzone_color']),
		new CWidgetFieldColorView($data['fields']['time_color'])
	], 'fields-group-date')
	->includeJsFile('js/monitoring.widget.clock.edit.js.php')
	->addJavaScript('
		widget_clock_form.init();
	')
	->show();
