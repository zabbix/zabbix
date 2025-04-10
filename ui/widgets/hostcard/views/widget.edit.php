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
 * Host card widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\HostCard\Includes\CWidgetFieldHostSectionsView;

(new CWidgetFormView($data))
	->addField(array_key_exists('hostid', $data['fields'])
		? new CWidgetFieldMultiSelectHostView($data['fields']['hostid'])
		: null
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['show_suppressed'])
	)
	->addField(
		new CWidgetFieldHostSectionsView($data['fields']['sections'])
	)
	->addField(
		(new CWidgetFieldMultiSelectHostInventoryView($data['fields']['inventory']))
			->addRowClass('js-row-inventory-fields')
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_hostcard_form.init();')
	->show();
