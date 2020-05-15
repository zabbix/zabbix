<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerPopupActionRecovery extends CControllerPopupOperationCommon {

	protected function getCheckInputs() {
		return [
			'type' =>			'required|in '.ACTION_RECOVERY_OPERATION,
			'source' =>			'required|in '.implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL]),
			'operationtype' =>	'in '.implode(',', [OPERATION_TYPE_MESSAGE, OPERATION_TYPE_COMMAND, OPERATION_TYPE_RECOVERY_MESSAGE]),
			'actionid' =>		'string',
			'update' =>			'in 1',
			'validate' =>		'in 1',
			'operation' =>		'array'
		];
	}

	protected function getFormDetails() {
		return [
			'param' => 'add_recovery_operation',
			'input_name' => 'new_recovery_operation'
		];
	}
}
