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
	public static function getConditionLabelStrings(): array {
		return [
			ZBX_CEP_CONDITION_EVENT_NAME => _('Event name'),
			ZBX_CEP_CONDITION_TAG_NAME => _('Tag name'),
			ZBX_CEP_CONDITION_TAG_VALUE => _('Tag value'),
			ZBX_CEP_CONDITION_SEVERITY => _('Severity'),
			ZBX_CEP_CONDITION_HOST => _('Host'),
			ZBX_CEP_CONDITION_HOST_GROUP => _('Host group'),
			ZBX_CEP_CONDITION_TIME_PERIOD => _('Time period')
		];
	}

	public static function getConditionTypes(): array {
		return [
			ZBX_CEP_CONDITION_EVENT_NAME => _('Event name'),
			ZBX_CEP_CONDITION_TAG_NAME => _('Tag'),
			ZBX_CEP_CONDITION_TAG_VALUE => _('Tag value'),
			ZBX_CEP_CONDITION_SEVERITY => _('Severity'),
			ZBX_CEP_CONDITION_HOST => _('Host'),
			ZBX_CEP_CONDITION_HOST_GROUP => _('Host group'),
			ZBX_CEP_CONDITION_TIME_PERIOD => _('Time period')
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
			CONDITION_OPERATOR_EXISTS => _('Exists'),
			CONDITION_OPERATOR_NOT_EXISTS => _('Does not exist')
		];
	}

	public static function getConditionOperatorString(array $ceprule_condition): string {
		return static::getConditionOperatorStrings()[$ceprule_condition['operator']];
	}

	public static function getConditionArgumentsString(array $ceprule_condition): string {
		return match((int) $ceprule_condition['type']) {
			ZBX_CEP_CONDITION_EVENT_NAME => $ceprule_condition['event_name'],
			ZBX_CEP_CONDITION_TAG_NAME => $ceprule_condition['tag'],
			ZBX_CEP_CONDITION_TAG_VALUE => $ceprule_condition['tag'].':'.$ceprule_condition['tag_value'],
			ZBX_CEP_CONDITION_SEVERITY => CSeverityHelper::getName($ceprule_condition['severity']),
			ZBX_CEP_CONDITION_HOST => $ceprule_condition['host'],
			ZBX_CEP_CONDITION_HOST_GROUP => $ceprule_condition['host_group'],
			ZBX_CEP_CONDITION_TIME_PERIOD => $ceprule_condition['time_period']
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
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED => _('Event occured'),
			ZBX_CEP_OP_WHEN_EVENT_EVICTED => _('Event evicted'),
			ZBX_CEP_OP_WHEN_WINDOW_CLOSED => _('Window closed'),
			ZBX_CEP_OP_WHEN_TAGS_CORRELATED => _('Tags correlated'),
			ZBX_CEP_OP_WHEN_PATTERN_MATCHED => _('Event pattern matched')
		];
	}

	public static function getOperationExecuteWhenString(array $ceprule_operation): string {
		return self::getOperationExecuteWhenStrings()[$ceprule_operation['execute_when']];
	}

	public static function getOperationLabelStrings(): array {
		return [
			ZBX_CEP_OP_SET_NAME => _('Set event name'),
			ZBX_CEP_OP_CLOSE => _('Close'),
			ZBX_CEP_OP_DISCARD => _('Discard'),
			ZBX_CEP_OP_SET_SEVERITY => _('Set severity'),
			ZBX_CEP_OP_INCREASE_SEVERITY => _('Increase severity'),
			ZBX_CEP_OP_DECREASE_SEVERITY => _('Decrease severity'),
			ZBX_CEP_OP_SUPPRESS => _('Suppress'),
			ZBX_CEP_OP_COPY_FIRST => _('Copy first'),
			ZBX_CEP_OP_COPY_LAST => _('Copy last'),
			ZBX_CEP_OP_ADD_TAG => _('Add tag'),
			ZBX_CEP_OP_SET_TAG => _('Set tag'),
			ZBX_CEP_OP_SET_TAG_VALUE => _('Set tag value'),
			ZBX_CEP_OP_INCREASE_TAG_VALUE => _('Increase tag value'),
			ZBX_CEP_OP_DECREASE_TAG_VALUE => _('Decrease tag value'),
			ZBX_CEP_OP_RENAME_TAG => _('Rename tag'),
			ZBX_CEP_OP_REMOVE_TAG => _('Remove tag')
		];
	}

	public static function getOperationTypes(): array {
		return [
			ZBX_CEP_OP_SET_NAME => _('Set name'),
			ZBX_CEP_OP_CLOSE => _('Close event'),
			ZBX_CEP_OP_DISCARD => _('Discard event'),
			ZBX_CEP_OP_SET_SEVERITY => _('Set severity'),
			ZBX_CEP_OP_INCREASE_SEVERITY => _('Increase severity'),
			ZBX_CEP_OP_DECREASE_SEVERITY => _('Decrease severity'),
			ZBX_CEP_OP_SUPPRESS => _('Suppress')
		];
	}

	public static function getOperationLabelString(array $ceprule_operation): string {
		return self::getOperationLabelStrings()[$ceprule_operation['type']];
	}

	public static function getOperationArgumentsString(array $ceprule_operation): string {
		return match((int) $ceprule_operation['type']) {
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_COPY_FIRST,
			ZBX_CEP_OP_COPY_LAST,
			ZBX_CEP_OP_DISCARD,
			ZBX_CEP_OP_CLOSE => '',

			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_REMOVE_TAG => $ceprule_operation['tag'],

			ZBX_CEP_OP_SET_NAME => $ceprule_operation['event_name'],

			ZBX_CEP_OP_SET_SEVERITY => CSeverityHelper::getName($ceprule_operation['severity']),

			ZBX_CEP_OP_RENAME_TAG => $ceprule_operation['tag'].':'.$ceprule_operation['new_tag'],

			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_ADD_TAG => $ceprule_operation['tag'].':'.$ceprule_operation['tag_value'],
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
			ZBX_CEP_WINDOW_NONE => _('None'),
			ZBX_CEP_WINDOW_SIMPLE => _('Simple'),
			ZBX_CEP_WINDOW_CAUSE_SYMPTOM => _('Cause and symptoms grouping'),
			ZBX_CEP_WINDOW_TAG_MATCH => _('Tag correlation'),
			ZBX_CEP_WINDOW_PATTERN_MATCH => _('Event pattern match')
		];
	}

	public static function getWindowLabelString(array $ceprule): string {
		return self::getWindowLabelStrings()[$ceprule['window_type']];
	}
}
