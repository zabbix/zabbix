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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Actions new condition popup.
 */
class CControllerConditionsPopupValidate extends CController {

	protected function checkInput() {
		$fields =  [
			'type' =>				'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' =>				'required|in '.implode(',', [
				EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
				EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
			]),
			'condition_type' =>		'in '.implode(',', [
				CONDITION_TYPE_HOST_GROUP, CONDITION_TYPE_TEMPLATE, CONDITION_TYPE_HOST, CONDITION_TYPE_TRIGGER,
				CONDITION_TYPE_TRIGGER_NAME, CONDITION_TYPE_TRIGGER_SEVERITY, CONDITION_TYPE_TIME_PERIOD,
				CONDITION_TYPE_SUPPRESSED, CONDITION_TYPE_DRULE, CONDITION_TYPE_DCHECK, CONDITION_TYPE_DOBJECT,
				CONDITION_TYPE_PROXY, CONDITION_TYPE_DHOST_IP, CONDITION_TYPE_DSERVICE_TYPE,
				CONDITION_TYPE_DSERVICE_PORT, CONDITION_TYPE_DSTATUS, CONDITION_TYPE_DUPTIME, CONDITION_TYPE_DVALUE,
				CONDITION_TYPE_EVENT_ACKNOWLEDGED, CONDITION_TYPE_HOST_NAME, CONDITION_TYPE_EVENT_TYPE,
				CONDITION_TYPE_HOST_METADATA, CONDITION_TYPE_EVENT_TAG, CONDITION_TYPE_EVENT_TAG_VALUE,
				CONDITION_TYPE_SERVICE, CONDITION_TYPE_SERVICE_NAME
			]),
			'trigger_context' =>	'in '.implode(',', ['host', 'template']),
			'operator' =>			'in '.implode(',', [
				CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_IN, CONDITION_OPERATOR_MORE_EQUAL,
				CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_NOT_IN, CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO,
				CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP
				]),
			'value' =>				'',
			'value2' =>				''
		];

		$ret = $this->validateInput($fields) && $this->validateCondition();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
//		$ret = $this->validateInput($fields);

//		if (!$ret) {
//			$this->setResponse(new CControllerResponseFatal());
//		}

		//return true;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function validateCondition() {
		$validator = new CActionCondValidator();
		$is_valid = $validator->validate([
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->getInput('value'),
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null,
			'operator' => $this->getInput('operator')
		]);

		if (!$is_valid) {
			error($validator->getError());
		}

		return $is_valid;
	}

	protected function doAction() {

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([])]));
	}
}
