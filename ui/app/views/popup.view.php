<?php
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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('class.calendar.js');
$this->addJsFile('multilineinput.js');
$this->addJsFile('class.form.fieldset.collapsible.js');
$this->includeJsFile('popup.view.js.php');
$this->addJsFile('items.js');
$this->addJsFile('class.tagfilteritem.js');

$html_page = (new CHtmlPage())->show();

(new CScriptTag('
	view.init('.json_encode([
		'action' => $data['popup']['action'],
		'options' => $data['popup']['options']
	]).');
'))
	->setOnDocumentReady()
	->show();
