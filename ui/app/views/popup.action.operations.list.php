<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @var array $data
 */

$partials = [
	ACTION_OPERATION => 'action.operations',
	ACTION_RECOVERY_OPERATION => 'action.recovery.operations',
	ACTION_UPDATE_OPERATION => 'action.update.operations'
];

$output = [
	'body' => (new CPartial($partials[$data['recovery']], $data))->getOutput()
];

$output['messages'] = [];
$messages = CMessageHelper::getMessages();
if (count($messages) !== 0) {
	$output['messages'] = [$messages[0]['message']];
}

echo json_encode($output);
