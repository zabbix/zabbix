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
	->setId('operation-table')
	->setAttribute('style', 'width: 100%;');

// todo : add correct table based on eventsource and operation type!
$operations_table->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Action')]);
$operations = $data['action']['operations'];

// todo : pass operations data from js
if ($operations == null) {
	$operations = $data['operations'];
}

foreach ($operations as $operationid => $operation) {

	// Create steps column
	if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
		$simple_interval_parser = new CSimpleIntervalParser();

		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if ($operation['esc_step_from'] < 1) {
			$operation['esc_step_from'] = 1;
		}

		// display N-N as N
		$esc_steps_txt = ($operation['esc_step_from'] == $operation['esc_step_to'] || $operation['esc_step_to'] == 0)
			? $operation['esc_step_from']
			: $operation['esc_step_from'].' - '.$operation['esc_step_to'];

		$esc_period_txt = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS
			&& timeUnitToSeconds($operation['esc_period']) == 0)
			? _('Default')
			: $operation['esc_period'];

		$delays = $data['action']
			? count_operations_delay($data['action']['operations'], $data['action']['esc_period'])
			: count_operations_delay($operations, $data['esc_period']);

		$esc_delay_txt = ($delays[$operation['esc_step_from']] === null)
			? _('Unknown')
			: ($delays[$operation['esc_step_from']] != 0
				? convertUnits(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
				: _('Immediately')
			);
	}

	// todo : remove details from hidden inputs

	$buttons =
		(new CHorList([
			(new CSimpleButton(_('Edit')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('js-edit-operation')
				->setAttribute('data_operation', json_encode([
					'operationid' => $operationid,
					'actionid' => $data['actionid'],
					'eventsource' => $data['eventsource'],
					'operationtype' => $operation['operationtype'],
					'data' => $operation
				])),
			[
				(new CButton('remove', _('Remove')))
					->setAttribute('data_operationid', $operationid)
					->addClass('js-remove')
					->addClass(ZBX_STYLE_BTN_LINK)
					->removeId(),
				// todo : add prefix
				new CVar('operations['.$operationid.']', $operation),
				//	new CVar('operations_for_popup['.ACTION_UPDATE_OPERATION.']['.$operationid.']',
				//		json_encode($operation_for_popup)
				//	)
			]
		]))
			->setName('button-list')
			->addClass(ZBX_STYLE_NOWRAP);


	// add all data to rows
	$details_column = new CCol([
		// todo : fix if two types of data
		new CTag('b', true, $operation['details']['type'][0]),
		implode(' ', $operation['details']['data'][0])

	]);

	// todo : add rows if small table.
	$operations_table->addRow([
		$esc_steps_txt,
		$details_column,
		$esc_delay_txt,
		$operation['esc_period'] == 0 ? 'Default' : $operation['esc_period'],
		$buttons
	]);
}


$operations_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CSimpleButton(_('Add')))
					->setAttribute('data-actionid', $data['actionid'])
					->setAttribute('data-eventsource', $data['eventsource'])
					->setAttribute('operationtype', ACTION_OPERATION)
					->addClass('js-operation-details')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->setColSpan(4)
		)
);

$operations_table->show();
