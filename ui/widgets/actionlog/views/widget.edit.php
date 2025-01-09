<?php declare(strict_types = 0);
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
 * Action log widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldMultiSelectUserView($data['fields']['userids'])
	)
	->addField(
		new CWidgetFieldMultiSelectActionView($data['fields']['actionids'])
	)
	->addField(
		new CWidgetFieldMultiSelectMediaTypeView($data['fields']['mediatypeids'])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['statuses']))->setColumns(3)
	)
	->addField(
		(new CWidgetFieldTextBoxView($data['fields']['message']))->setPlaceholder(_('subject or body text'))
	)
	->addField(
		(new CWidgetFieldTimePeriodView($data['fields']['time_period']))
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	)
	->addField(
		new CWidgetFieldSelectView($data['fields']['sort_triggers'])
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
	)
	->show();
