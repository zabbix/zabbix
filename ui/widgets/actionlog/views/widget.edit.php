<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Action log widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectUserView($data['fields']['userids'], $data['captions']['ms']['users']['userids'])
	)
	->addField(
		new CWidgetFieldMultiSelectActionView($data['fields']['actionids'],
			$data['captions']['ms']['actions']['actionids']
		)
	)
	->addField(
		new CWidgetFieldMultiSelectMediaTypeView($data['fields']['mediatypeids'],
			$data['captions']['ms']['media_types']['mediatypeids']
		)
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['statuses']))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass(ZBX_STYLE_COLUMNS_3)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['message']))->setPlaceholder(_('subject or body text'))
	)
	->addField(
		new CWidgetFieldSelectView($data['fields']['sort_triggers'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
	)
	->show();
