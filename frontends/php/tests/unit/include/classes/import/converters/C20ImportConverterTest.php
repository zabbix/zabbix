<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class C20ImportConverterTest extends CImportConverterTest {

	public function testConvertItems() {
		$source = $this->createSource(array(
			'items' => array(
				array(),
				array(
					'status' => ITEM_STATUS_NOTSUPPORTED
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'items' => array(
				array(),
				array(
					'status' => ITEM_STATUS_ACTIVE
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggers() {
		$source = $this->createSource(array(
			'triggers' => array(
				array(
					'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'triggers' => array(
				array(
					'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertDiscoveryRules() {
		$source = $this->createSource(array(
			'discovery_rules' => array(
				array(),
				array(
					'status' => ITEM_STATUS_NOTSUPPORTED,
					'filter' => array()
				),
				array(
					'filter' => ':'
				),
				array(
					'filter' => '{#MACRO}:regex'
				),
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'discovery_rules' => array(
				array(),
				array(
					'status' => ITEM_STATUS_ACTIVE,
					'filter' => array()
				),
				array(),
				array(
					'filter' => array(
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => array(
							array(
								'macro' => '{#MACRO}',
								'value' => 'regex',
								'operator' => CONDITION_OPERATOR_REGEXP,
							)
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggerPrototypes() {
		$source = $this->createSource(array(
			'discovery_rules' => array(
				array(),
				array(
					'trigger_prototypes' => array(
						array(
							'expression' => '{host:item.last(0)}#0|{host:item.last(0)}#1'
						)
					)
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'discovery_rules' => array(
				array(),
				array(
					'trigger_prototypes' => array(
						array(
							'expression' => '{host:item.last(0)}<>0 or {host:item.last(0)}<>1'
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	protected function createSource(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '2.0',
				'date' => '2014-11-19T12:19:00Z'
			), $data)
		);
	}

	protected function createExpectedResult(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '3.0',
				'date' => '2014-11-19T12:19:00Z'
			), $data)
		);
	}

	protected function assertConvert(array $expectedResult, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expectedResult, $result);
	}


	protected function createConverter() {
		return new C20ImportConverter(
			new C20TriggerConverter(new CFunctionMacroParser(), new CMacroParser('#'))
		);
	}

}
