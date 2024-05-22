<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 */

$this->addJsFile('class.calendar.js');

(new CHtmlPage())->show();

(new CScriptTag(
	'PopUp("'.$data['popup']['action'].'", '.json_encode($data['popup']['options']).');'.

	'$.subscribe("acknowledge.create", function(event, response, overlay) {'.
		'clearMessages();'.
		'addMessage(makeMessageBox("good", [], response.success.title, true, false));'.
	'});'
))
	->setOnDocumentReady()
	->show();
