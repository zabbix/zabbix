<?php
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
 * @var CView $this
 */

$this->addJsFile('multiselect.js');
$this->addJsFile('inputsecret.js');
$this->addJsFile('macrovalue.js');
$this->addJsFile('textareaflexible.js');
$this->addJsFile('class.cviewswitcher.js');
$this->addJsFile('class.cverticalaccordion.js');
$this->addJsFile('class.tab-indicators.js');
$this->addJsFile('hostinterfacemanager.js');
$this->addJsFile('hostmacrosmanager.js');

$data += [
	'buttons' => ($data['hostid'] == 0)
		? [
			new CSubmit('add', _('Add')),
			new CButtonCancel()
		]
		: [
			new CSubmit('update', _('Update')),
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid')),
			new CButtonCancel()
		]
];

//'script_inline' => getPagePostJs().
//		$this->readJsFile('popup.service.edit.js.php')

(new CWidget())
	->setTitle(_('Host'))
	->addItem(new CPartial('configuration.host.edit.html', $data))
	->show();
