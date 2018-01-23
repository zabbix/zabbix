<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	->setTitle(_('Working time'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.workingtime.php')))
	);

$workingTimeView = (new CTabView())
	->addTab('workingTime', _('Working time'),
		(new CFormList())
			->addRow((new CLabel(_('Working time'), 'work_period'))->setAsteriskMark(),
				(new CTextBox('work_period', $data['work_period']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
	)
	->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$workingTimeForm = (new CForm())
	->addItem($workingTimeView);

$widget->addItem($workingTimeForm);

return $widget;
