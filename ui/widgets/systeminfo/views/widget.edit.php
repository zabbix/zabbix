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
 * System information widget form view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['info_type'])
	)
	->addField(CSettingsHelper::isSoftwareUpdateCheckEnabled()
		? (new CWidgetFieldCheckBoxView($data['fields']['show_software_update_check_details']))
			->addRowClass('js-show-software-update-check-details')
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_systeminfo_form.init();')
	->show();
