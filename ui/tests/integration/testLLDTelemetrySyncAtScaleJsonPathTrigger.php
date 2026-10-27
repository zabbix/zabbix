<?php declare(strict_types = 1);
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

require_once dirname(__FILE__).'/testLLDTelemetrySyncAtScale.php';

/**
 * Variant of testLLDTelemetrySyncAtScale where every dependent item keeps its own portion of the query result, the
 * rows of its device as they are, instead of the extracted status, and the trigger extracts the status from the
 * stored rows with the jsonpath() function in the trigger expression.
 *
 * can be tested with parent as (testLLDTelemetrySyncAtScale|testLLDTelemetrySyncAtScaleJsonPathTrigger)
 *
 * @required-components server, proxy
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDTelemetrySyncAtScaleJsonPathTrigger extends testLLDTelemetrySyncAtScale {

	/**
	 * Each dependent item keeps only rows of its own device, the status is left in them for the trigger.
	 */
	protected static function deviceItemPrototype(): array {
		return [
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_JSONPATH,
					'params' => '$[?(@["ResourceAttributes.host.name"] == "'.self::LLD_MACRO.'")]',
					'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
					'error_handler_params' => ''
				]
			]
		];
	}

	/**
	 * Status is extracted from the first row of the device, as the parent does with first() in preprocessing.
	 */
	protected static function deviceTriggerExpression(): string {
		return 'jsonpath(last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']),'.
				'"$[0].value")=1';
	}
}
