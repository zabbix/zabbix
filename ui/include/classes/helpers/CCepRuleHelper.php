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
	public const WINDOW_NONE =			0;
	public const WINDOW_SIMPLE =		1;
	public const WINDOW_CAUSE_SYMPTOM = 2;
	public const WINDOW_TAG_MATCH =		3;
	public const WINDOW_PATTERN_MATCH = 4;

	public const STATUS_ENABLED =	0;
	public const STATUS_DISABLED =	1;

	public const EXECUTION_CONTINUE =	0;
	public const EXECUTION_STOP =		1;

	public const CONDITION_EVENT_NAME = 	ZBX_CONDITION_TYPE_EVENT_NAME;
	public const CONDITION_TAG_NAME =		ZBX_CONDITION_TYPE_EVENT_TAG;
	public const CONDITION_TAG_VALUE =		ZBX_CONDITION_TYPE_EVENT_TAG_VALUE;
	public const CONDITION_SEVERITY =		ZBX_CONDITION_TYPE_TRIGGER_SEVERITY;
	public const CONDITION_HOST =			ZBX_CONDITION_TYPE_HOST;
	public const CONDITION_HOST_GROUP =		ZBX_CONDITION_TYPE_HOST_GROUP;
	public const CONDITION_TIME_PERIOD =	ZBX_CONDITION_TYPE_TIME_PERIOD;

	public const OP_SET_NAME =				1;
	public const OP_CLOSE =					2;
	public const OP_DISCARD =				3;
	public const OP_SET_SEVERITY =			4;
	public const OP_INCREASE_SEVERITY = 	5;
	public const OP_DECREASE_SEVERITY = 	6;
	public const OP_SUPPRESS =				7;
	public const OP_COPY_FIRST =			8;
	public const OP_COPY_LAST =				9;
	public const OP_ADD_TAG =				10;
	public const OP_SET_TAG =				11;
	public const OP_SET_TAG_VALUE =			12;
	public const OP_INCREASE_TAG_VALUE =	13;
	public const OP_DECREASE_TAG_VALUE =	14;
	public const OP_RENAME_TAG =			15;
	public const OP_REMOVE_TAG =			16;

	public const WHEN_EVENT_OCCURRED =	0;
	public const WHEN_EVENT_EVICTED =	1;
	public const WHEN_WINDOW_CLOSED =	2;
	public const WHEN_TAGS_CORRELATED =	3;
	public const WHEN_PATTERN_MATCHED =	4;

	public const WINDOW_CONDITION_TAG_PAIR =		0;
	public const WINDOW_CONDITION_OLD_TAG =			1;
	public const WINDOW_CONDITION_OLD_TAG_VALUE =	2;

	public const GROUP_BY_NO =	0;
	public const GROUP_BY_YES =	1;

	public const FILTER_SHOW_ALL =		0;
	public const FILTER_SHOW_LEGACY =	1;
	public const FILTER_SHOW_CEP =		2;

	public const EXECUTE_WHEN_BY_WINDOW_TYPE = [
		self::WINDOW_NONE => [
			self::WHEN_EVENT_OCCURRED
		],
		self::WINDOW_SIMPLE => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_EVICTED
		],
		self::WINDOW_CAUSE_SYMPTOM => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_WINDOW_CLOSED
		],
		self::WINDOW_TAG_MATCH => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_TAGS_CORRELATED
		],
		self::WINDOW_PATTERN_MATCH => [
			self::WHEN_EVENT_OCCURRED,
			self::WHEN_EVENT_EVICTED,
			self::WHEN_PATTERN_MATCHED
		]
	];

	public const OPERATION_TYPES_BY_EXECUTE_WHEN = [
		self::WHEN_EVENT_OCCURRED => [
			self::OP_SET_NAME,
			self::OP_CLOSE,
			self::OP_DISCARD,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG
		],
		self::WHEN_EVENT_EVICTED => [
			self::OP_SET_NAME,
			self::OP_CLOSE,
			self::OP_DISCARD,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_COPY_FIRST,
			self::OP_COPY_LAST,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG
		],
		self::WHEN_WINDOW_CLOSED => [
			self::OP_SET_NAME,
			self::OP_CLOSE,
			self::OP_DISCARD,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_ADD_TAG,
			self::OP_SET_TAG,
			self::OP_SET_TAG_VALUE,
			self::OP_INCREASE_TAG_VALUE,
			self::OP_DECREASE_TAG_VALUE,
			self::OP_RENAME_TAG,
			self::OP_REMOVE_TAG
		],
		self::WHEN_TAGS_CORRELATED => [
			self::OP_SET_NAME,
			self::OP_CLOSE,
			self::OP_SET_SEVERITY,
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
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
			self::OP_COPY_FIRST,
			self::OP_COPY_LAST
		]
	];

	public static function getConditionLabelStrings(): array {
		return [
			self::CONDITION_EVENT_NAME => _('Event name'),
			self::CONDITION_TAG_NAME => _('Tag name'),
			self::CONDITION_TAG_VALUE => _('Tag value'),
			self::CONDITION_SEVERITY => _('Severity'),
			self::CONDITION_HOST => _('Host'),
			self::CONDITION_HOST_GROUP => _('Host group'),
			self::CONDITION_TIME_PERIOD => _('Time period')
		];
	}

	public static function getConditionTypes(): array {
		return [
			self::CONDITION_EVENT_NAME => _('Event name'),
			self::CONDITION_TAG_NAME => _('Tag'),
			self::CONDITION_TAG_VALUE => _('Tag value'),
			self::CONDITION_SEVERITY => _('Severity'),
			self::CONDITION_HOST => _('Host'),
			self::CONDITION_HOST_GROUP => _('Host group'),
			self::CONDITION_TIME_PERIOD => _('Time period')
		];
	}

	public static function getConditionLabelString(array $ceprule_condition): string {
		return self::getConditionLabelStrings()[$ceprule_condition['type']];
	}

	public static function getConditionOperatorStrings(): array {
		return [
			CONDITION_OPERATOR_IN => _('In'),
			CONDITION_OPERATOR_NOT_IN => _('Not in'),
			CONDITION_OPERATOR_EQUAL => _('Equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('Does not equal'),
			CONDITION_OPERATOR_LIKE => _('Contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('Does not contain'),
			CONDITION_OPERATOR_MORE_EQUAL => _('Is more than or equal'),
			CONDITION_OPERATOR_LESS_EQUAL => _('Is less than or equal'),
			CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist')
		];
	}

	public static function getConditionOperatorString(array $ceprule_condition): string {
		return static::getConditionOperatorStrings()[$ceprule_condition['operator']];
	}

	public static function getConditionArgumentsString(array $ceprule_condition): string {
		return match((int) $ceprule_condition['type']) {
			self::CONDITION_EVENT_NAME => $ceprule_condition['event_name'],
			self::CONDITION_TAG_NAME => $ceprule_condition['tag'],
			self::CONDITION_TAG_VALUE => $ceprule_condition['tag'].':'.$ceprule_condition['tag_value'],
			self::CONDITION_SEVERITY => CSeverityHelper::getName($ceprule_condition['severity']),
			self::CONDITION_HOST => $ceprule_condition['host'],
			self::CONDITION_HOST_GROUP => $ceprule_condition['host_group'],
			self::CONDITION_TIME_PERIOD => $ceprule_condition['time_period']
		};
	}

	public static function getConditionDescription(array $ceprule_condition): array {
		return [
			CCepRuleHelper::getConditionLabelString($ceprule_condition),
			' ',
			italic(CCepRuleHelper::getConditionOperatorString($ceprule_condition)),
			' ',
			CCepRuleHelper::getConditionArgumentsString($ceprule_condition)
		];
	}

	public static function getOperationExecuteWhenStrings(): array {
		return [
			self::WHEN_EVENT_OCCURRED => _('Event occured'),
			self::WHEN_EVENT_EVICTED => _('Event evicted'),
			self::WHEN_WINDOW_CLOSED => _('Window closed'),
			self::WHEN_TAGS_CORRELATED => _('Tags correlated'),
			self::WHEN_PATTERN_MATCHED => _('Event pattern matched')
		];
	}

	public static function getOperationExecuteWhenString(array $ceprule_operation): string {
		return self::getOperationExecuteWhenStrings()[$ceprule_operation['execute_when']];
	}

	public static function getOperationLabelStrings(): array {
		return [
			self::OP_SET_NAME => _('Set event name'),
			self::OP_CLOSE => _('Close'),
			self::OP_DISCARD => _('Discard'),
			self::OP_SET_SEVERITY => _('Set severity'),
			self::OP_INCREASE_SEVERITY => _('Increase severity'),
			self::OP_DECREASE_SEVERITY => _('Decrease severity'),
			self::OP_SUPPRESS => _('Suppress'),
			self::OP_COPY_FIRST => _('Copy first'),
			self::OP_COPY_LAST => _('Copy last'),
			self::OP_ADD_TAG => _('Add tag'),
			self::OP_SET_TAG => _('Set tag'),
			self::OP_SET_TAG_VALUE => _('Set tag value'),
			self::OP_INCREASE_TAG_VALUE => _('Increase tag value'),
			self::OP_DECREASE_TAG_VALUE => _('Decrease tag value'),
			self::OP_RENAME_TAG => _('Rename tag'),
			self::OP_REMOVE_TAG => _('Remove tag')
		];
	}

	public static function getOperationTypes(): array {
		return [
			self::OP_SET_NAME => _('Set name'),
			self::OP_CLOSE => _('Close event'),
			self::OP_DISCARD => _('Discard event'),
			self::OP_SET_SEVERITY => _('Set severity'),
			self::OP_INCREASE_SEVERITY => _('Increase severity'),
			self::OP_DECREASE_SEVERITY => _('Decrease severity'),
			self::OP_SUPPRESS => _('Suppress')
		];
	}

	public static function getOperationLabelString(array $ceprule_operation): string {
		return self::getOperationLabelStrings()[$ceprule_operation['type']];
	}

	public static function getOperationArgumentsString(array $ceprule_operation): string {
		return match((int) $ceprule_operation['type']) {
			self::OP_INCREASE_SEVERITY,
			self::OP_DECREASE_SEVERITY,
			self::OP_SUPPRESS,
			self::OP_COPY_FIRST,
			self::OP_COPY_LAST,
			self::OP_DISCARD,
			self::OP_CLOSE => '',

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

	public static function getWindowConditionLabelStrings(): array {
		return [
			self::WINDOW_CONDITION_TAG_PAIR => _('Event tag pair'),
			self::WINDOW_CONDITION_OLD_TAG => _('Past event tag name'),
			self::WINDOW_CONDITION_OLD_TAG_VALUE => _('Past event tag value')
		];
	}
}
