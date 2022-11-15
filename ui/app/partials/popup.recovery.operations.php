<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var CPartial $this
 * @var array $data
 */

$operations_table = (new CTable())
	->setId('rec-table')
	->setAttribute('style', 'width: 100%;');

$operations = $data['action']['recovery_operations'];
$operations_table->setHeader([_('Details'), _('Action')]);

$i = 0;
foreach ($operations as $operationid => $operation) {
	if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_RECOVERY_OPERATION])) {
		continue;
	}
	if (!isset($operation['opconditions'])) {
		$operation['opconditions'] = [];
	}
	if (!array_key_exists('opmessage', $operation)) {
		$operation['opmessage'] = [];
	}

	$operation['opmessage'] += [
		'mediatypeid' => '0',
		'message' => '',
		'subject' => '',
		'default_msg' => '1'
	];

	$operation_for_popup = array_merge($operation, ['id' => $operationid]);

	foreach (['opcommand_grp' => 'groupid', 'opcommand_hst' => 'hostid'] as $var => $field) {
		if (array_key_exists($var, $operation_for_popup)) {
			$operation_for_popup[$var] = zbx_objectValues($operation_for_popup[$var], $field);
		}
	}

	$details = [];
	if (count($operation['details']['type']) > 1) {
		// Create row for script with 3 types of details: current host, hosts and host groups.
		if (array_key_exists('opcommand_hst', $operation)) {
			if ($operation['opcommand_hst'][0]['hostid'] == 0) {
				$details[] = [
					new CTag('b', true, $operation['details']['type'][0]), BR()
				];

				if (array_key_exists('1',  $operation['opcommand_hst'])) {
					$details[] = [
						new CTag('b', true, $operation['details']['type'][1]),
						implode(' ', $operation['details']['data'][0]), BR()
					];
				}
				if (array_key_exists('opcommand_grp', $operation)) {
					if (count($operation['opcommand_grp']) > 0) {
						$details[] = [
							new CTag('b', true, $operation['details']['type'][2]),
							implode(' ', $operation['details']['data'][1]), BR()
						];
					}
				}
			}
			// Create row for script with 2 types of details:hosts and host groups.
			else {
				foreach ($operation['details']['type'] as $id => $type) {
					$details[] = [
						new CTag('b', true, $type), implode(' ', $operation['details']['data'][$id]), BR()
					];
				}
			}
			$details_column = $details;
		}
		// Create row for operation with more than 1 type
		else {
			foreach ($operation['details']['type'] as $id => $type) {
				$details[] = [
					new CTag('b', true, $type), implode(' ', $operation['details']['data'][$id]), BR()
				];
			}
		}
		$details_column = $details;
	}
	// Create row for operation with 1 type
	else {
		$details_column = array_key_exists('data', $operation['details'])
			? new CCol([
				new CTag('b', true, $operation['details']['type'][0]),
				implode(' ', $operation['details']['data'][0])
			])
			: new CCol([new CTag('b', true, $operation['details']['type'][0])]);
	}

	// Create hidden input fields for each row.
	$hidden_data = array_filter($operation, function ($key) {
		return !in_array($key, [
			'row_index', 'duration', 'steps', 'details'
		]);
	}, ARRAY_FILTER_USE_KEY );

	$operations_table->addRow([
		$details_column,
		(new CCol(
			new CHorList([
				(new CSimpleButton(_('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('js-edit-operation')
					->setAttribute('data_operation', json_encode([
						'operationid' => $i,
						'actionid' => array_key_exists('actionid', $data) ? $data['actionid'] : 0,
						'eventsource' => array_key_exists('eventsource', $data)
							? $data['eventsource']
							: $operation['eventsource'],
						'operationtype' => ACTION_RECOVERY_OPERATION,
						'data' => $operation
					])),
				[
					(new CButton('remove', _('Remove')))
						->setAttribute('data_operationid', $i)
						->addClass('js-remove')
						->addClass(ZBX_STYLE_BTN_LINK)
						->removeId(),
					new CVar('recovery_operations['.$i.']', $hidden_data)
				]
			])
		))->addClass(ZBX_STYLE_NOWRAP)
	], null, 'recovery_operations_'.$i)->addClass(ZBX_STYLE_WORDBREAK);

	$i ++;
}

$operations_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->setAttribute('operationtype', ACTION_RECOVERY_OPERATION)
					->setAttribute('data-actionid', array_key_exists('actionid', $data) ? $data['actionid'] : 0)
					->setAttribute('data-eventsource', array_key_exists('eventsource', $data)
						? $data['eventsource']
						: $operation['eventsource']
					)
					->addClass('js-recovery-operations-create')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->setColSpan(4)
		)
);
$operations_table->show();
