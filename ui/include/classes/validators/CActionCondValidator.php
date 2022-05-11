<?php
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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CActionCondValidator extends CValidator {

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @param array $condition
	 *
	 * @return bool
	 */
	public function validate($condition) {
		// Build validators.
		$discoveryCheckTypeValidator = new CLimitedSetValidator([
			'values' => array_keys(discovery_check_type2str())
		]);
		$discoveryObjectStatusValidator = new CLimitedSetValidator([
			'values' => array_keys(discovery_object_status2str())
		]);
		$triggerSeverityValidator = new CLimitedSetValidator([
			'values' => [
				TRIGGER_SEVERITY_NOT_CLASSIFIED,
				TRIGGER_SEVERITY_INFORMATION,
				TRIGGER_SEVERITY_WARNING,
				TRIGGER_SEVERITY_AVERAGE,
				TRIGGER_SEVERITY_HIGH,
				TRIGGER_SEVERITY_DISASTER
			]
		]);
		$discoveryObjectValidator = new CLimitedSetValidator([
			'values' => array_keys(discovery_object2str())
		]);
		$eventTypeValidator = new CLimitedSetValidator([
			'values' => array_keys(eventType())
		]);

		// Validate condition values depending on condition type.
		switch ($condition['conditiontype']) {
			case CONDITION_TYPE_HOST_GROUP:
			case CONDITION_TYPE_TEMPLATE:
			case CONDITION_TYPE_TRIGGER:
			case CONDITION_TYPE_HOST:
			case CONDITION_TYPE_DRULE:
			case CONDITION_TYPE_PROXY:
			case CONDITION_TYPE_SERVICE:
				if (zbx_empty($condition['value']) || $condition['value'] == 0) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (is_array($condition['value'])) {
					foreach ($condition['value'] as $value) {
						if (zbx_empty($value) || $value == 0) {
							$this->setError(
								_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty'))
							);
							break;
						}
					}
				}
				break;

			case CONDITION_TYPE_DCHECK:
				if (!$condition['value']) {
					$this->setError(
						_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty'))
					);
				}
				break;

			case CONDITION_TYPE_DOBJECT:
				if (zbx_empty($condition['value'])) {
					$this->setError(
						_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty'))
					);
				}
				elseif (!$discoveryObjectValidator->validate($condition['value'])) {
					$this->setError(_('Incorrect action condition discovery object.'));
				}
				break;

			case CONDITION_TYPE_TIME_PERIOD:
				$time_period_parser = new CTimePeriodsParser(['usermacros' => true]);

				if ($time_period_parser->parse($condition['value']) != CParser::PARSE_SUCCESS) {
					$this->setError(_('Invalid time period.'));
				}
				break;

			case CONDITION_TYPE_DHOST_IP:
				$ip_range_parser = new CIPRangeParser(['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30]);
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!$ip_range_parser->parse($condition['value'])) {
					$this->setError(_s('Invalid action condition: %1$s.', $ip_range_parser->getError()));
				}
				break;

			case CONDITION_TYPE_DSERVICE_TYPE:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!$discoveryCheckTypeValidator->validate($condition['value'])) {
					$this->setError(_('Incorrect action condition discovery check.'));
				}
				break;

			case CONDITION_TYPE_DSERVICE_PORT:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!validate_port_list($condition['value'])) {
					$this->setError(_s('Incorrect action condition port "%1$s".', $condition['value']));
				}
				break;

			case CONDITION_TYPE_DSTATUS:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!$discoveryObjectStatusValidator->validate($condition['value'])) {
					$this->setError(_('Incorrect action condition discovery status.'));
				}
				break;

			case CONDITION_TYPE_SUPPRESSED:
				if (!zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('should be empty')));
				}
				break;

			case CONDITION_TYPE_TRIGGER_SEVERITY:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!$triggerSeverityValidator->validate($condition['value'])) {
					$this->setError(_('Incorrect action condition trigger severity.'));
				}
				break;

			case CONDITION_TYPE_EVENT_TYPE:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				elseif (!$eventTypeValidator->validate($condition['value'])) {
					$this->setError(_('Incorrect action condition event type.'));
				}
				break;

			case CONDITION_TYPE_DUPTIME:
				if ($condition['value'] < 0 || $condition['value'] > SEC_PER_MONTH) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value',
						_s('value must be between "%1$s" and "%2$s"', 0, SEC_PER_MONTH)
					));
				}
				break;

			case CONDITION_TYPE_DVALUE:
				if (array_key_exists('operator', $condition) && $condition['value'] === ''
						&& ($condition['operator'] == CONDITION_OPERATOR_EQUAL
							|| $condition['operator'] == CONDITION_OPERATOR_NOT_EQUAL)) {
					break;
				}
				// break; is not missing here

			case CONDITION_TYPE_TRIGGER_NAME:
			case CONDITION_TYPE_HOST_NAME:
			case CONDITION_TYPE_HOST_METADATA:
			case CONDITION_TYPE_EVENT_TAG:
			case CONDITION_TYPE_SERVICE_NAME:
				if (zbx_empty($condition['value'])) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				break;

			case CONDITION_TYPE_EVENT_TAG_VALUE:
				if (!is_string($condition['value2']) || $condition['value2'] === '') {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value2', _('cannot be empty')));
				}
				elseif (!is_string($condition['value'])) {
					$this->setError(
						_s('Incorrect value for field "%1$s": %2$s.', 'value', _('a character string is expected'))
					);
				}
				elseif (array_key_exists('operator', $condition) && $condition['value'] === ''
						&& ($condition['operator'] == CONDITION_OPERATOR_LIKE
							|| $condition['operator'] == CONDITION_OPERATOR_NOT_LIKE)) {
					$this->setError(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('cannot be empty')));
				}
				break;

			default:
				$this->setError(_('Incorrect action condition type.'));
		}

		// If no error is not set, return true.
		return !(bool) $this->getError();
	}
}
