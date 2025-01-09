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


$output = [];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if (array_key_exists('actions', $data)) {
	$foot_note = $data['foot_note']
		? (new CDiv(
			(new CDiv(
				(new CDiv($data['foot_note']))
					->addClass(ZBX_STYLE_TABLE_STATS)
			))->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
		))->addClass(ZBX_STYLE_TABLE_PAGING)
		: null;

	$output['data'] = (new CObject([
		makeEventActionsTable($data['actions'], $data['users'], $data['mediatypes']), $foot_note
	]))->toString();
}

echo json_encode($output);
