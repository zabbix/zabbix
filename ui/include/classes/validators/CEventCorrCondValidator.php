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


class CEventCorrCondValidator extends CValidator {

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @param array $condition
	 *
	 * @return bool
	 */
	public function validate($condition) {
		$operator = array_key_exists('operator', $condition) ? $condition['operator'] : null;
		$tag = array_key_exists('tag', $condition) ? $condition['tag'] : null;
		$oldtag = array_key_exists('oldtag', $condition) ? $condition['oldtag'] : null;
		$newtag = array_key_exists('newtag', $condition) ? $condition['newtag'] : null;
		$value = array_key_exists('value', $condition) ? $condition['value'] : null;
		$groupids = array_key_exists('groupids', $condition) ? $condition['groupids'] : null;

		$condition_type_validator = new CLimitedSetValidator([
			'values' => [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,	ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,	ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]
		]);

		if (!$condition_type_validator->validate($condition['type'])) {
			$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'type', _('incorrect condition type')));
		}

		// Validate condition values depending on condition type.
		switch ($condition['type']) {
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
				if (zbx_empty($tag)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty')));
				}
				break;

			case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
				if (!is_array($groupids) || zbx_empty($groupids)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'groupid', _('cannot be empty')));
				}
				else {
					foreach ($groupids as $groupid) {
						if ($groupid == 0) {
							$this->setError(
								_s('Incorrect value for field "%1$s": %2$s.', 'groupid', _('cannot be empty'))
							);
							break;
						}
					}
				}
				break;

			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				if (zbx_empty($oldtag)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'oldtag', _('cannot be empty')));
				}
				elseif (zbx_empty($newtag)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'newtag', _('cannot be empty')));
				}
				break;

			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
				if (zbx_empty($tag)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'tag', _('cannot be empty')));
				}
				elseif (!is_string($value)) {
					$this->setError(
						_s('Incorrect value for field "%1$s": %2$s.', 'value', _('a character string is expected'))
					);
				}
				elseif (($operator == CONDITION_OPERATOR_LIKE || $operator == CONDITION_OPERATOR_NOT_LIKE)
						&& $value === '') {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				break;
		}

		// If no error is not set, return true.
		return !(bool) $this->getError();
	}
}
