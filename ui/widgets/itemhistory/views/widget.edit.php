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
 * Item history widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\ItemHistory\Includes\CWidgetFieldColumnsListView;

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['layout'])
	)
	->addField(
		(new CWidgetFieldColumnsListView($data['fields']['columns']))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addField(
		new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
	)
	->addField($data['templateid'] === null
		? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
		: null
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addField(
				new CWidgetFieldRadioButtonListView($data['fields']['sortorder'])
			)
			->addField(
				new CWidgetFieldCheckBoxView($data['fields']['show_timestamp'])
			)
			->addField(
				new CWidgetFieldRadioButtonListView($data['fields']['show_column_header'])
			)
			->addField(
				(new CWidgetFieldTimePeriodView($data['fields']['time_period']))
					->setDateFormat(ZBX_FULL_DATE_TIME)
					->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
					->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			)
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_itemhistory_form.init('.json_encode([
			'templateid' => $data['templateid']
		], JSON_THROW_ON_ERROR).');')
	->show();
