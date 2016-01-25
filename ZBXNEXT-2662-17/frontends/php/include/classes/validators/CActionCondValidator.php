<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 *
	 * @param $condition
	 *
	 * @return bool
	 */
	public function validate($condition) {
		// build validators
		$timePeriodValidator = new CTimePeriodValidator();
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
		$triggerValueValidator = new CLimitedSetValidator([
			'values' => array_keys(trigger_value2str())
		]);
		$eventTypeValidator = new CLimitedSetValidator([
			'values' => array_keys(eventType())
		]);

		$conditionValue = $condition['value'];
		// validate condition values depending on condition type
		switch ($condition['conditiontype']) {
			case CONDITION_TYPE_HOST_GROUP:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_TEMPLATE:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_TRIGGER:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_HOST:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_DRULE:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_DCHECK:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_PROXY:
				if (!$conditionValue) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			case CONDITION_TYPE_DOBJECT:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$discoveryObjectValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition discovery object.'));
				}
				break;

			case CONDITION_TYPE_TIME_PERIOD:
				if (!$timePeriodValidator->validate($conditionValue)) {
					$this->setError($timePeriodValidator->getError());
				}
				break;

			case CONDITION_TYPE_DHOST_IP:
				$ipRangeValidator = new CIPRangeValidator();
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$ipRangeValidator->validate($conditionValue)) {
					$this->setError($ipRangeValidator->getError());
				}
				break;

			case CONDITION_TYPE_DSERVICE_TYPE:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$discoveryCheckTypeValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition discovery check.'));
				}
				break;

			case CONDITION_TYPE_DSERVICE_PORT:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!validate_port_list($conditionValue)) {
					$this->setError(_s('Incorrect action condition port "%1$s".', $conditionValue));
				}
				break;

			case CONDITION_TYPE_DSTATUS:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$discoveryObjectStatusValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition discovery status.'));
				}
				break;

			case CONDITION_TYPE_MAINTENANCE:
				if (!zbx_empty($conditionValue)) {
					$this->setError(_('Maintenance action condition value must be empty.'));
				}
				break;

			case CONDITION_TYPE_TRIGGER_SEVERITY:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$triggerSeverityValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition trigger severity.'));
				}
				break;

			case CONDITION_TYPE_TRIGGER_VALUE:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$triggerValueValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition trigger value.'));
				}
				break;

			case CONDITION_TYPE_EVENT_TYPE:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				elseif (!$eventTypeValidator->validate($conditionValue)) {
					$this->setError(_('Incorrect action condition event type.'));
				}
				break;

			case CONDITION_TYPE_TRIGGER_NAME:
			case CONDITION_TYPE_DUPTIME:
			case CONDITION_TYPE_DVALUE:
			case CONDITION_TYPE_APPLICATION:
			case CONDITION_TYPE_HOST_NAME:
			case CONDITION_TYPE_HOST_METADATA:
				if (zbx_empty($conditionValue)) {
					$this->setError(_('Empty action condition.'));
				}
				break;

			default:
				$this->setError(_('Incorrect action condition type.'));
		}

		// If no error is not set, return true.
		return !(bool) $this->getError();
	}
}
