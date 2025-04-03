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
 * @var CView $this
 * @var array $data
 */

$this->enableLayoutModes();
$this->setLayoutMode(ZBX_LAYOUT_KIOSKMODE);

$error_title = CMessageHelper::getTitle();
$errors = CMessageHelper::getMessages();
CMessageHelper::clear();

$body = new CDiv();

if ($errors || array_key_exists('raw_response', $data['tokens'])) {
	$show_details = true;

	if ($data && array_key_exists('raw_response', $data['tokens'])) {
		$errors[] = ['message' => [_('Response'), new CPre($data['tokens']['raw_response'])]];
		$show_details = false;
	}

	$body->addItem(makeMessageBox(ZBX_STYLE_MSG_BAD, $errors, $error_title, false, $show_details));
}
else {
	$body->addItem(new CScriptTag('window.opener.postMessage('.json_encode($data['tokens']).', window.location.origin);'));
}

$body->show();
