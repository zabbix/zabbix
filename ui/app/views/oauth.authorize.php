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

$body = new CDiv();

if (array_key_exists('tokens', $data) && array_key_exists('access_token', $data['tokens'])) {
	$body->addItem(
		new CScriptTag('window.opener.postMessage('.json_encode($data['tokens']).', window.location.origin);')
	);
}
else {
	$body->addItem(getMessages(false, _('Cannot get authentication tokens'), false));
}

$body->show();
