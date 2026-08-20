<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CCepRuleHelper {

	public const WINDOW_NONE = 0;
	public const WINDOW_SIMPLE = 1;
	public const WINDOW_CAUSE_SYMPTOM = 2;
	public const WINDOW_TAG_MATCH = 3;
	public const WINDOW_PATTERN_MATCH = 4;

	public const STATUS_ENABLED = 0;
	public const STATUS_DISABLED = 1;

	public const EXECUTION_CONTINUE = 0;
	public const EXECUTION_STOP = 1;

	public const CONDITION_EVENT_NAME = ZBX_CONDITION_TYPE_EVENT_NAME;
	public const CONDITION_TAG = ZBX_CONDITION_TYPE_EVENT_TAG;
	public const CONDITION_TAG_VALUE = ZBX_CONDITION_TYPE_EVENT_TAG_VALUE;
	public const CONDITION_SEVERITY = ZBX_CONDITION_TYPE_TRIGGER_SEVERITY;
	public const CONDITION_HOST = ZBX_CONDITION_TYPE_HOST;
	public const CONDITION_HOST_GROUP = ZBX_CONDITION_TYPE_HOST_GROUP;
	public const CONDITION_TIME_PERIOD = ZBX_CONDITION_TYPE_TIME_PERIOD;

	public const CONDITION_TYPES = [
		ZBX_CONDITION_TYPE_EVENT_NAME,
		ZBX_CONDITION_TYPE_EVENT_TAG,
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
		ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
		ZBX_CONDITION_TYPE_HOST,
		ZBX_CONDITION_TYPE_HOST_GROUP,
		ZBX_CONDITION_TYPE_TIME_PERIOD
	];

	public const OP_SET_NAME = 1;
	public const OP_CLOSE_EVENT = 2;
	public const OP_DISCARD = 3;
	public const OP_SET_SEVERITY = 4;
	public const OP_INCREASE_SEVERITY =  5;
	public const OP_DECREASE_SEVERITY =  6;
	public const OP_SUPPRESS = 7;
	public const OP_UNSUPPRESS = 8;
	public const OP_CLONE_FIRST = 9;
	public const OP_CLONE_LAST = 10;
	public const OP_ADD_TAG = 11;
	public const OP_SET_TAG = 12;
	public const OP_SET_TAG_VALUE = 13;
	public const OP_INCREASE_TAG_VALUE = 14;
	public const OP_DECREASE_TAG_VALUE = 15;
	public const OP_RENAME_TAG = 16;
	public const OP_REMOVE_TAG = 17;
	public const OP_CLOSE_WINDOW = 18;
	public const OP_SET_CAUSE = 19;

	public const WHEN_EVENT_OCCURRED = 0;
	public const WHEN_EVENT_ADDED = 1;
	public const WHEN_EVENT_EVICTED = 2;
	public const WHEN_WINDOW_CLOSED = 3;
	public const WHEN_PATTERN_MATCHED = 4;

	public const WINDOW_CONDITION_TAG_PAIR = 0;
	public const WINDOW_CONDITION_OLD_TAG = 1;
	public const WINDOW_CONDITION_OLD_TAG_VALUE = 2;

	public const GROUP_BY_NO = 0;
	public const GROUP_BY_YES = 1;

	public const FILTER_SHOW_ALL = 0;
	public const FILTER_SHOW_LEGACY = 1;
	public const FILTER_SHOW_CEP = 2;

	public const EXECUTE_WHEN_BY_WINDOW_TYPE = [
		self::WINDOW_NONE => [
			self::WHEN_EVENT_OCCURRED
		],
		self::WINDOW_SIMPLE => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_ADDED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_WINDOW_CLOSED
		],
		self::WINDOW_CAUSE_SYMPTOM => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_ADDED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_WINDOW_CLOSED
		],
		self::WINDOW_TAG_MATCH => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_ADDED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_WINDOW_CLOSED
		],
		self::WINDOW_PATTERN_MATCH => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_ADDED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_WINDOW_CLOSED,
			self::WHEN_PATTERN_MATCHED
		]
	];

	public const OPERATION_CONDITION_TYPES = [
		ZBX_CONDITION_TYPE_EVENT_TAG,
		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
		ZBX_CONDITION_TYPE_EVENT_OPEN,
		ZBX_CONDITION_TYPE_EVENT_FIRST,
		ZBX_CONDITION_TYPE_EVENT_LAST,
		ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
		ZBX_CONDITION_TYPE_EVENT_COPIED,
		ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
	];

	public const OPERATION_CONDITION_TYPES_BY_EXECUTE_WHEN = [
		self::WHEN_EVENT_OCCURRED => [
			ZBX_CONDITION_TYPE_EVENT_TAG,
			ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
			ZBX_CONDITION_TYPE_EVENT_OPEN,
			ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
			ZBX_CONDITION_TYPE_EVENT_COPIED,
			ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
		],
		self::WHEN_EVENT_ADDED => [
			ZBX_CONDITION_TYPE_EVENT_TAG,
			ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
			ZBX_CONDITION_TYPE_EVENT_OPEN,
			ZBX_CONDITION_TYPE_EVENT_FIRST,
			ZBX_CONDITION_TYPE_EVENT_LAST,
			ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
			ZBX_CONDITION_TYPE_EVENT_COPIED,
			ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
		],
		self::WHEN_EVENT_EVICTED => [
			ZBX_CONDITION_TYPE_EVENT_TAG,
			ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
			ZBX_CONDITION_TYPE_EVENT_OPEN,
			ZBX_CONDITION_TYPE_EVENT_FIRST,
			ZBX_CONDITION_TYPE_EVENT_LAST,
			ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
			ZBX_CONDITION_TYPE_EVENT_COPIED,
			ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
		],
		self::WHEN_WINDOW_CLOSED => [
			ZBX_CONDITION_TYPE_EVENT_TAG,
			ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
			ZBX_CONDITION_TYPE_EVENT_OPEN,
			ZBX_CONDITION_TYPE_EVENT_FIRST,
			ZBX_CONDITION_TYPE_EVENT_LAST,
			ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
			ZBX_CONDITION_TYPE_EVENT_COPIED,
			ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
		],
		self::WHEN_PATTERN_MATCHED => [
			ZBX_CONDITION_TYPE_EVENT_TAG,
			ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
			ZBX_CONDITION_TYPE_EVENT_OPEN,
			ZBX_CONDITION_TYPE_EVENT_FIRST,
			ZBX_CONDITION_TYPE_EVENT_LAST,
			ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
			ZBX_CONDITION_TYPE_EVENT_COPIED,
			ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
		]
	];

	public const OPERATION_TYPES_BY_EXECUTE_WHEN = [
		self::WHEN_EVENT_OCCURRED => [
			self::OP_SET_NAME,
			self::OP_CLOSE_EVENT,
			self::OP_DISCARD,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG
		],
		self::WHEN_EVENT_ADDED => [
			self::OP_SET_NAME,
			self::OP_CLOSE_EVENT,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG,
			self::OP_CLOSE_WINDOW
		],
		self::WHEN_EVENT_EVICTED => [
			self::OP_SET_NAME,
			self::OP_CLOSE_EVENT,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_CLONE_FIRST,
			self::OP_CLONE_LAST,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG,
			self::OP_CLOSE_WINDOW
		],
		self::WHEN_WINDOW_CLOSED => [
			self::OP_CLOSE_EVENT,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_CLONE_FIRST,
			self::OP_CLONE_LAST,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG
		],
		self::WHEN_PATTERN_MATCHED => [
			self::OP_DISCARD,
			self::OP_CLOSE_EVENT,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_CLONE_FIRST,
			self::OP_CLONE_LAST,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG,
			self::OP_CLOSE_WINDOW
		]
	];

	public static function getConditionLabels(): array {
		return [
			self::CONDITION_EVENT_NAME => _('Event name'),
			self::CONDITION_TAG => _('Tag'),
			self::CONDITION_SEVERITY => _('Severity'),
			self::CONDITION_HOST => _('Host'),
			self::CONDITION_HOST_GROUP => _('Host group'),
			self::CONDITION_TIME_PERIOD => _('Time period')
		];
	}

	private static function getConditionLabel(int $type): string {
		$labels = self::getConditionLabels();

		if (!array_key_exists($type, $labels)) {
			throw new LogicException("Unknown condition type $type.");
		}

		return $labels[$type];
	}

	public static function getTagOperators(): array {
		return [
			CONDITION_OPERATOR_EXISTS => _('Exists'),
			CONDITION_OPERATOR_EQUAL => _('Equals'),
			CONDITION_OPERATOR_LIKE => _('Contains'),
			CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist'),
			CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
			CONDITION_OPERATOR_NOT_LIKE => _('Does not contain'),
			CONDITION_OPERATOR_MORE_EQUAL => _('Is more than or equal'),
			CONDITION_OPERATOR_LESS_EQUAL => _('Is less than or equal')
		];
	}

	public static function getConditionOperatorLabels(): array {
		return [
			CONDITION_OPERATOR_IN => _('In'),
			CONDITION_OPERATOR_NOT_IN => _('Not in'),
			CONDITION_OPERATOR_EQUAL => _('Equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
			CONDITION_OPERATOR_LIKE => _('Contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('Does not contain'),
			CONDITION_OPERATOR_MORE_EQUAL => _('Is more than or equal'),
			CONDITION_OPERATOR_LESS_EQUAL => _('Is less than or equal'),
			CONDITION_OPERATOR_EXISTS => _('Exists'),
			CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist')
		];
	}

	private static function getConditionOperatorLabel(int $operator): string {
		$labels = self::getConditionOperatorLabels();

		if (!array_key_exists($operator, $labels)) {
			throw new LogicException("Unknown condition operator $operator.");
		}

		return static::getConditionOperatorLabels()[$operator];
	}

	public static function getConditionDescription(array $ceprule_condition): array {
		$type_name = CCepRuleHelper::getConditionLabel($ceprule_condition['type']);
		$operator_name = mb_strtolower(CCepRuleHelper::getConditionOperatorLabel($ceprule_condition['operator']));

		if (self::CONDITION_EVENT_NAME == $ceprule_condition['type']) {
			return [$type_name, ' ', $operator_name, ' ', italic($ceprule_condition['event_name'])];
		}
		elseif (self::CONDITION_SEVERITY == $ceprule_condition['type']) {
			return [$type_name, ' ', $operator_name, ' ',
				italic(CSeverityHelper::getName($ceprule_condition['severity']))
			];
		}
		elseif (self::CONDITION_HOST == $ceprule_condition['type']) {
			return [$type_name, ' ', $operator_name, ' ', italic($ceprule_condition['host'])];
		}
		elseif (self::CONDITION_HOST_GROUP == $ceprule_condition['type']) {
			return [$type_name, ' ', $operator_name, ' ', italic($ceprule_condition['host_group'])];
		}
		elseif (self::CONDITION_TIME_PERIOD == $ceprule_condition['type']) {
			return [$type_name, ' ', $operator_name, ' ', italic($ceprule_condition['time_period'])];
		}
		elseif (self::CONDITION_TAG == $ceprule_condition['type']) {
			if ($ceprule_condition['operator'] == CONDITION_OPERATOR_NOT_EXISTS
					|| $ceprule_condition['operator'] == CONDITION_OPERATOR_EXISTS) {
				return [$type_name, ' ', italic($ceprule_condition['tag']), ' ', $operator_name];
			}
			else {
				return [$type_name, ' ', italic($ceprule_condition['tag']), ' ', $operator_name, ' ',
					italic($ceprule_condition['tag_value'])
				];
			}
		}
		else {
			throw new Exception('Unknown condition type');
		}
	}

	public static function getOperationExecuteWhenStrings(): array {
		return [
			self::WHEN_EVENT_OCCURRED => _('Event occured'),
			self::WHEN_EVENT_EVICTED => _('Event evicted'),
			self::WHEN_WINDOW_CLOSED => _('Window closed'),
			self::WHEN_PATTERN_MATCHED => _('Event pattern matched'),
			self::WHEN_EVENT_ADDED => _('Event added to window')
		];
	}

	public static function getOperationExecuteWhenString(array $ceprule_operation): string {
		return self::getOperationExecuteWhenStrings()[$ceprule_operation['execute_when']];
	}

	public static function getOperationLabelStrings(): array {
		return [
			self::OP_SET_NAME => _('Set name'),
			self::OP_CLOSE_EVENT => _('Close'),
			self::OP_DISCARD => _('Discard'),
			self::OP_SET_SEVERITY => _('Set severity'),
			self::OP_INCREASE_SEVERITY => _('Increase severity'),
			self::OP_DECREASE_SEVERITY => _('Decrease severity'),
			self::OP_SUPPRESS => _('Suppress'),
			self::OP_UNSUPPRESS => _('Unsuppress'),
			self::OP_CLONE_FIRST => _('Clone first'),
			self::OP_CLONE_LAST => _('Clone last'),
			self::OP_ADD_TAG => _('Add'),
			self::OP_SET_TAG => _('Set'),
			self::OP_SET_TAG_VALUE => _('Set value'),
			self::OP_INCREASE_TAG_VALUE => _('Increase value'),
			self::OP_DECREASE_TAG_VALUE => _('Decrease value'),
			self::OP_RENAME_TAG => _('Rename'),
			self::OP_REMOVE_TAG => _('Remove'),
			self::OP_CLOSE_WINDOW => _('Close (window)')
		];
	}

	public static function getOperationLabelString(array $ceprule_operation): string {
		if ($ceprule_operation['type'] === self::OP_SET_CAUSE) {
			return _('Set cause');
		}

		return self::getOperationLabelStrings()[$ceprule_operation['type']];
	}

	public static function getOperationArgumentsString(array $ceprule_operation): string {
		return match((int) $ceprule_operation['type']) {
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_UNSUPPRESS,
			self::OP_CLONE_FIRST,
			self::OP_CLOSE_WINDOW,
			self::OP_CLONE_LAST,
			self::OP_DISCARD,
			self::OP_CLOSE_EVENT => '',

			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_REMOVE_TAG => $ceprule_operation['tag'],

			self::OP_SET_NAME => $ceprule_operation['event_name'],

			self::OP_SET_SEVERITY => CSeverityHelper::getName($ceprule_operation['severity']),

			self::OP_RENAME_TAG => $ceprule_operation['tag'].':'.$ceprule_operation['new_tag'],

			self::OP_SET_TAG_VALUE,
			self::OP_SET_TAG,
			self::OP_ADD_TAG => $ceprule_operation['tag'].':'.$ceprule_operation['tag_value'],
		};
	}

	public static function getOperationDescription(array $ceprule_operation): array {
		return [
			_('Execute when'),
			' ',
			italic(CCepRuleHelper::getOperationExecuteWhenString($ceprule_operation)),
			': ',
			CCepRuleHelper::getOperationLabelString($ceprule_operation),
			' ',
			italic(CCepRuleHelper::getOperationArgumentsString($ceprule_operation))
		];
	}

	public static function getWindowLabelStrings(): array {
		return [
			self::WINDOW_NONE => _('None'),
			self::WINDOW_SIMPLE => _('Simple'),
			self::WINDOW_CAUSE_SYMPTOM => _('Cause and symptoms grouping'),
			self::WINDOW_TAG_MATCH => _('Tag correlation'),
			self::WINDOW_PATTERN_MATCH => _('Event pattern match')
		];
	}

	public static function getWindowLabelString(array $ceprule): string {
		return self::getWindowLabelStrings()[$ceprule['window_type']];
	}

	public static function buildActionDetailsMessage(string $details): string {
		$result = [];
		$details = json_decode(json: $details, associative: true, flags: JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);

		foreach ($details['cep'] as $ceprule_operation) {
			try {
				$result[] = self::formatOperationDetails($ceprule_operation);
			} catch (Throwable $e) {
				error_log($e->getMessage());
				error_log($e->getTraceAsString());
				$result[] = '*UNKNOWN*';
			}
		}

		return implode(PHP_EOL, $result);
	}

	private static function formatOperationDetails(array $ceprule_operation): string {
		$operation = (int) $ceprule_operation['operation'];
		$target = match($operation) {
			self::OP_SET_NAME
				=> 'name',

			self::OP_DECREASE_SEVERITY, self::OP_INCREASE_SEVERITY, self::OP_SET_SEVERITY
				=> 'severity',

			self::OP_ADD_TAG, self::OP_SET_TAG, self::OP_SET_TAG_VALUE, self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE, self::OP_RENAME_TAG, self::OP_REMOVE_TAG
				=> 'tag',

			default => ''
		};

		$target_details = $target ? $ceprule_operation[$target] : [];
		$arguments = match($operation) {
			self::OP_SET_NAME
				=> array_key_exists('old', $target_details)
					? sprintf('%s > %s', $target_details['old'], $target_details['new'])
					: sprintf('> %s', $target_details['new']),

			self::OP_RENAME_TAG
				=> array_key_exists('old', $target_details['tag'])
					? sprintf('%s > %s', $target_details['tag']['old'], $target_details['tag']['new'])
					: sprintf('> %s', $target_details['tag']['new']),

			self::OP_SET_SEVERITY, self::OP_DECREASE_SEVERITY, self::OP_INCREASE_SEVERITY
				=> array_key_exists('old', $target_details)
					? sprintf('%s > %s', CSeverityHelper::getName($target_details['old']),
						CSeverityHelper::getName($target_details['new'])
					)
					: sprintf('> %s', CSeverityHelper::getName($target_details['new'])),

			self::OP_ADD_TAG, self::OP_REMOVE_TAG
				=> sprintf('%s:%s', $target_details['tag'], $target_details['value']),

			self::OP_DECREASE_TAG_VALUE, self::OP_INCREASE_TAG_VALUE, self::OP_SET_TAG_VALUE
				=> sprintf('%s:%s', $target_details['tag'],
					array_key_exists('old', $target_details['value'])
						? sprintf('%s > %s', $target_details['value']['old'], $target_details['value']['new'])
						: sprintf('> %s', $target_details['value']['new'])
					),

			self::OP_SET_TAG
				=> sprintf('%s:%s', $target_details['tag'], is_string($target_details['value'])
					? $target_details['value']
					: (array_key_exists('old', $target_details['value'])
						? sprintf('%s > %s', $target_details['value']['old'], $target_details['value']['new'])
						: sprintf('> %s', $target_details['value']['new']))
					),

			default => ''
		};

		$label = CCepRuleHelper::getOperationLabelString(['type' => $operation]);

		return $arguments !== '' ? "$label: $arguments." : "$label.";
	}
}
