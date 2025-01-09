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


use Widgets\TrigOver\Includes\WidgetForm;

/**
 * Trigger overview widget view.
 *
 * @var CView $this
 * @var array $data
 */

if ($data['error'] !== null) {
	$table = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	$table = $data['layout'] == WidgetForm::LAYOUT_VERTICAL
		? (new CPartial('table.top', $data))->getOutput()
		: (new CPartial('table.left', $data))->getOutput();
}

(new CWidgetView($data))
	->addItem($table)
	->show();
