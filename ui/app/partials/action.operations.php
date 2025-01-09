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
 * @var CPartial $this
 * @var array    $data
 */

$operations_table = (new CTable())
	->setId('op-table')
	->setAttribute('style', 'width: 100%;');

if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
	$operations_table->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Actions')]);
}
else {
	$operations_table->setHeader([_('Details'), _('Actions')]);
}

if (array_key_exists('descriptions', $data)) {
	if (array_key_exists('operation', $data['descriptions'])) {
		$data['descriptions'] = $data['descriptions']['operation'];
	}

	$details_column = getActionOperationDescriptions(
		$data['action']['operations'], $data['eventsource'], $data['descriptions']
	);
}

foreach ($data['action']['operations'] as $i => $operation) {
	if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_OPERATION])) {
		continue;
	}

	if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
		$simple_interval_parser = new CSimpleIntervalParser();

		$delays = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);

		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if ($operation['esc_step_from'] < 1) {
			$operation['esc_step_from'] = 1;
		}

		// display N-N as N
		$esc_steps_txt = ($operation['esc_step_from'] == $operation['esc_step_to'])
			? $operation['esc_step_from']
			: $operation['esc_step_from'].' - '.$operation['esc_step_to'];

		$esc_period_txt = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS
				&& timeUnitToSeconds($operation['esc_period']) == 0)
			? _('Default')
			: $operation['esc_period'];

		$esc_delay_txt = $delays[$operation['esc_step_from']] === null
			? _('Unknown')
			: ($delays[$operation['esc_step_from']] != 0
				? convertUnits(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
				: _('Immediately')
			);
	}

	// Create hidden input fields for each row.
	$hidden_data = array_filter($operation, function ($key) {
		return !in_array($key, [
			'row_index', 'duration', 'steps', 'details'
		]);
	}, ARRAY_FILTER_USE_KEY);

	$buttons = (new CHorList([
		(new CButtonLink(_('Edit')))
			->addClass('js-edit-operation')
			->setAttribute('data-operation', json_encode([
				'operationid' => $i,
				'actionid' => array_key_exists('actionid', $data) ? $data['actionid'] : 0,
				'eventsource' => $data['eventsource'],
				'operationtype' => $operation['operationtype'],
				'data' => $operation
			])),
		[
			(new CButton('remove', _('Remove')))
				->setAttribute('data-operationid', $i)
				->addClass('js-remove')
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId(),
			new CVar('operations['.$i.']', $hidden_data)
		]
	]))
		->setName('button-list')
		->addClass(ZBX_STYLE_NOWRAP);

	if (in_array($data['eventsource'], [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE])) {
		$operations_table->addRow([
			$esc_steps_txt,
			(new CCol($details_column[$i]))->addClass(ZBX_STYLE_WORDBREAK),
			$esc_delay_txt,
			$esc_period_txt,
			$buttons
		], null, 'operations_'.$i);
	}
	else {
		$operations_table->addRow([
			$details_column[$i],
			$buttons
		], null, 'operations_'.$i)->addClass(ZBX_STYLE_WORDBREAK);
	}
}

$operations_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CButtonLink(_('Add')))
					->addClass('js-operation-details')
					->setAttribute('data-actionid', array_key_exists('actionid', $data) ? $data['actionid'] : 0)
					->setAttribute('data-eventsource', $data['eventsource'])
					->setAttribute('operationtype', ACTION_OPERATION)
			))->setColSpan(4)
		)
);

$operations_table->show();
