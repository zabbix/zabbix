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
