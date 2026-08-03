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


use PHPUnit\Framework\TestCase;

class CAuditTest extends TestCase {

	public static function dataProviderHandleObjectDiff() {
		yield 'item preprocessing step new step added' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					],
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135684',
						'step' => 2,
						'type' => ZBX_PREPROC_REGSUB,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135684]' => [CAudit::DETAILS_ACTION_ADD],
				'item.preprocessing[135684].params' => [CAudit::DETAILS_ACTION_ADD, "match\nreplace"],
				'item.preprocessing[135684].item_preprocid' => [CAudit::DETAILS_ACTION_ADD, '135684'],
				'item.preprocessing[135684].step' => [CAudit::DETAILS_ACTION_ADD, '2'],
				'item.preprocessing[135684].type' => [CAudit::DETAILS_ACTION_ADD, '5']
			]
		];
		yield 'item preprocessing step param value change from "replace\nreplace" to "match\nreplace"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "replace\nreplace",
						'item_preprocid' => '135683',
						'type' => ITEM_TYPE_INTERNAL,
						'step' => 1,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135683]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.preprocessing[135683].params' => [CAudit::DETAILS_ACTION_UPDATE, "replace\nreplace", "match\nreplace"]
			]
		];
		yield 'item preprocessing step deleted' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					],
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135684',
						'step' => '2',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135684]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];

		yield '"query_fields" add new query field' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2'],
					['sortorder' => 3, 'name' => 'field-3', 'value' => 'value-3']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[3]' => ['add'],
				'item.query_fields[3].sortorder' => ['add', '3'],
				'item.query_fields[3].name' => ['add', 'field-3'],
				'item.query_fields[3].value' => ['add', 'value-3']
			]
		];
		yield '"query_fields" update value from "field-2" to "field-2-updated"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2-updated', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[2]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.query_fields[2].name' => [CAudit::DETAILS_ACTION_UPDATE, 'field-2', 'field-2-updated']
			]
		];
		yield '"query_fields" query field deleted' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2-updated', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[2]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];

		yield '"item.password" change from "123" to "passwordpassword"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'password' => 'passwordpassword',
				'type' => ITEM_TYPE_SIMPLE,
			],
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => '123',
			],
			[
				'item.password' => ['update', ZBX_SECRET_MASK, ZBX_SECRET_MASK],
			]
		];
		yield '"item.password" change from empty value to "password"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'password' => 'password',
				'type' => ITEM_TYPE_SIMPLE,
			],
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => '',
			],
			[
				'item.password' => ['update', ZBX_SECRET_MASK, ZBX_SECRET_MASK],
			]
		];
		yield '"item.password" change "password" to empty value' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => '',
			],
			[
				'password' => 'password',
				'type' => ITEM_TYPE_SIMPLE,
			],
			[
				'item.password' => ['update', ZBX_SECRET_MASK, ZBX_SECRET_MASK],
			]
		];
	}

	/**
	 * @dataProvider dataProviderHandleObjectDiff
	 */
	public function testHandleObjectDiff(int $resource, int $action, array $object, array $db_object, array $expected) {
		static $closure = Closure::bind(
			fn(int $resource, int $action, array $object, array $db_object): array => self::handleObjectDiff(
				$resource, $action, $object, $db_object
			),
			new CAudit(),
			CAudit::class
		);

		$this->assertSame($expected, $closure($resource, $action, $object, $db_object));
	}
}
