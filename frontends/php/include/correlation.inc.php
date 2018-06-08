<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Convert a correlation condition operator to string.
 *
 * @param int $operator
 *
 * @return string
 */
function corrConditionOperatorToString($operator) {
	$operators = [
		CONDITION_OPERATOR_EQUAL => '=',
		CONDITION_OPERATOR_NOT_EQUAL => '<>',
		CONDITION_OPERATOR_LIKE => _('like'),
		CONDITION_OPERATOR_NOT_LIKE => _('not like')
	];

	return $operators[$operator];
}

/**
 * Returns correlation condition types or one type depending on input.
 *
 * @param int $type			Default: null. Returns all condition types.
 *
 * @return mixed			Returns condition type and it's string translation as array key => value pair.
 */
function corrConditionTypes($type = null) {
	$types = [
		ZBX_CORR_CONDITION_OLD_EVENT_TAG => _('Old event tag'),
		ZBX_CORR_CONDITION_NEW_EVENT_TAG => _('New event tag'),
		ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP => _('New event host group'),
		ZBX_CORR_CONDITION_EVENT_TAG_PAIR => _('Event tag pair'),
		ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE => _('Old event tag value'),
		ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE => _('New event tag value')
	];

	return ($type === null) ? $types : $types[$type];
}

/**
 * Returns correlation operation types or one type depending on input.
 *
 * @param int $type			Default: null. Returns all operation types.
 *
 * @return mixed			Returns operation type and it's string translation as array key => value pair.
 */
function corrOperationTypes($type = null) {
	$types = [
		ZBX_CORR_OPERATION_CLOSE_OLD => _('Close old events'),
		ZBX_CORR_OPERATION_CLOSE_NEW => _('Close new event')
	];

	return ($type === null) ? $types : $types[$type];
}

/**
 * Return an array of operators supported by the given correlation condition.
 *
 * @param int $type						Correlation condition type.
 *
 * @return array						Returns array of supported operators.
 */
function getOperatorsByCorrConditionType($type) {
	switch ($type) {
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			return [CONDITION_OPERATOR_EQUAL];

		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			return [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			];

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			return [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL];
	}
}

/**
 * Correlation condition type "host group" contains IDs. IDs are collected and then validated. For other
 * condition types, values are returned as they are.
 *
 * @param array $correlations							An array of correlations.
 * @param array $correlations['filter']					An array containing arrays of correlation conditions.
 * @param array $correlations['filter']['conditions']	An array of correlation conditions.
 *
 * @return array										Returns an array of correlation condition string values.
 */
function corrConditionValueToString(array $correlations) {
	$result = [];

	$groupids = [];

	foreach ($correlations as $i => $correlation) {
		$result[$i] = [];

		foreach ($correlation['filter']['conditions'] as $j => $condition) {
			switch ($condition['type']) {
				case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
					$result[$i][$j] = ['tag' => $condition['tag']];
					break;

				case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
					$result[$i][$j] = ['group' => _('Unknown')];
					$groupids[$condition['groupid']] = true;
					break;

				case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
					$result[$i][$j] = ['oldtag' => $condition['oldtag'], 'newtag' => $condition['newtag']];
					break;

				case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
					$result[$i][$j] = ['tag' => $condition['tag'], 'value' => $condition['value']];
					break;
			}
		}
	}

	if ($groupids) {
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => array_keys($groupids),
			'preservekeys' => true
		]);

		if ($groups) {
			foreach ($correlations as $i => $correlation) {
				foreach ($correlation['filter']['conditions'] as $j => $condition) {
					if ($condition['type'] == ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP
							&& array_key_exists($condition['groupid'], $groups)) {
						$result[$i][$j] = ['group' => $groups[$condition['groupid']]['name']];
					}
				}
			}
		}
	}

	return $result;
}

/**
 * Return the HTML representation of correlation operation type.
 *
 * @param array $operation					An array of correlation operation data.
 * @param int   $operation['type']			Correlation operation type.
 *
 * @return string
 */
function getCorrOperationDescription(array $operation) {
	return corrOperationTypes($operation['type']);
}

/**
 * Returns the HTML representation of a correlation condition.
 *
 * @param array  $condition					Array of correlation condition data.
 * @param int	 $condition['type']			Condition type.
 * @param int	 $condition['operator']		Condition operator.
 * @param array  $values					Array of condition values.
 * @param string $values['tag']				Condition event tag.
 * @param string $values['group']			Condition host group name.
 * @param string $values['oldtag']			Condition event old tag.
 * @param string $values['newtag']			Condition event new tag.
 * @param string $values['value']			Condition event tag value.
 *
 * @return array
 */
function getCorrConditionDescription(array $condition, array $values) {
	$description = [];

	switch ($condition['type']) {
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			$description[] = _('Old event tag').' '.corrConditionOperatorToString($condition['operator']).' ';
			$description[] = italic(CHtml::encode($values['tag']));
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			$description[] = _('New event tag').' '.corrConditionOperatorToString($condition['operator']).' ';
			$description[] = italic(CHtml::encode($values['tag']));
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			$description[] = _('New event host group').' '.corrConditionOperatorToString($condition['operator']).' ';
			$description[] = italic(CHtml::encode($values['group']));
			break;

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			$description[] = _('Old event tag').' ';
			$description[] = italic(CHtml::encode($values['oldtag']));
			$description[] = ' '.corrConditionOperatorToString($condition['operator']).' '._('new event tag').' ';
			$description[] = italic(CHtml::encode($values['newtag']));
			break;

		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			$description[] = _('Old event tag').' ';
			$description[] = italic(CHtml::encode($values['tag']));
			$description[] = ' '.corrConditionOperatorToString($condition['operator']).' ';
			$description[] = italic(CHtml::encode($values['value']));
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			$description[] = _('New event tag').' ';
			$description[] = italic(CHtml::encode($values['tag']));
			$description[] = ' '.corrConditionOperatorToString($condition['operator']).' ';
			$description[] = italic(CHtml::encode($values['value']));
			break;
	}

	return $description;
}
