<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @on-before enableUserGroup
 * @on-after disableUserGroup
 */
class testAuditLog extends CAPITest {

	public static function getAuditLogData() {
		return [
			// API request with all possible values.
			[
				'api_request' => [
					'userids' => '1',
					'time_from' => '1582268400', // 2020-02-21 09:00
					'time_till' => '1582270200', // 2020-02-21 09:30
					'selectDetails' => ['field_name', 'oldvalue', 'newvalue'],
					'output' => ['auditid', 'userid', 'clock', 'action', 'resourcetype', 'ip', 'resourceid', 'resourcename', 'note'],
					'filter' => [
						'auditid' => ['9000', '9001'],
						'userid' => ['1', '3'],
						'clock' => ['1582269000', '1582270260'],
						'action' => ['1', '0'],
						'resourcetype' => ['4', '6'],
						'ip' => ['::1', '127.0.0.1'],
						'resourceid' => ['10054', '0'],
						'resourcename' => 'H1 updated',
						'note' => '',
						'table_name' => 'hosts',
						'field_name' => 'status'
					],
					'search' => [
						'ip' => '1',
						'resourcename' => 'updated',
						'oldvalue' => '0',
						'newvalue' => '1'
					],
					'sortfield' => ['userid', 'auditid'],
					'sortorder' => 'DESC',
					'limit' => '5'
				],
				'expected_result' => [
					[
						'auditid' => '9000',
						'userid' => '1',
						'clock' => '1582269000',
						'action' => '1',
						'resourcetype' => '4',
						'ip' => '127.0.0.1',
						'resourceid' => '10054',
						'resourcename' => 'H1 updated',
						'note' => '',
						'details' => [
							[
								'field_name' => 'status',
								'oldvalue' => '0',
								'newvalue' => '1'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Extend selectDetails and output.
			[
				'api_request' => [
					'userids' => '1',
					'time_from' => '1582268400', // 2020-02-21 09:00
					'time_till' => '1582270200', // 2020-02-21 09:30
					'output' => 'extend',
					'selectDetails' => 'extend',
					'filter' => [
						'resourcetype' => '14'
					]
				],
				'expected_result' => [
					[
						'auditid' => '9005',
						'userid' => '1',
						'clock' => '1582269240',
						'action' => '1',
						'resourcetype' => '14',
						'note' => '',
						'ip' => '192.168.3.32',
						'resourceid' => '6',
						'resourcename' => 'HG1 updated',
						'details' => [
							[
								'table_name' => 'groups',
								'field_name' => 'name',
								'oldvalue' => 'HG1',
								'newvalue' => 'HG1 updated'
							]
						]
					]
				],
				'expected_error' => null
			],
			// selectDetails and output with one value.
			[
				'api_request' => [
					'userids' => '1',
					'time_from' => '1582268400', // 2020-02-21 09:00
					'time_till' => '1582270200', // 2020-02-21 09:30
					'output' => ['resourcename'],
					'selectDetails' => ['newvalue'],
					'filter' => [
						'table_name' => ['groups', 'hosts'],
						'field_name' => 'name'
					]
				],
				'expected_result' => [
					[
						'resourcename' => 'HG1 updated',
						'details' => [
							[
								'newvalue' => 'HG1 updated'
							]
						]
					]
				],
				'expected_error' => null
			],
			// selectDetails empty array.
			[
				'api_request' => [
					'userids' => '4',
					'time_from' => '1582268400', // 2020-02-21 09:00
					'time_till' => '1582270200', // 2020-02-21 09:30
					'output' => ['clock'],
					'selectDetails' => 'extend',
					'filter' => [
						'resourcetype' => '19'
					]
				],
				'expected_result' => [
					[
						'clock' => '1582269180',
						'details' => []
					]
				],
				'expected_error' => null
			],
			// Two userids.
			[
				'api_request' => [
					'userids' => ['1', '4'],
					'time_from' => '1582268400', // 2020-02-21 09:00
					'time_till' => '1582270200', // 2020-02-21 09:30
					'output' => ['auditid'],
					'filter' => [
						'action' => '0'
					]
				],
				'expected_result' => [
					[
						'auditid' => '9003'
					],
					[
						'auditid' => '9004'
					]
				],
				'expected_error' => null
			],
			// search parameter.
			[
				'api_request' => [
					'output' => ['auditid'],
					'search' => [
						'note' => ['[']
					],
				],
				'expected_result' => [
					[
						'auditid' => '9003'
					],
					[
						'auditid' => '9004'
					]
				],
				'expected_error' => null
			],
			// note parameter.
			[
				'api_request' => [
					'output' => ['auditid'],
					'filter' => [
						'note' => ['Graph [graph1]', 'Name [Audit Map]']
					],
					'search' => [
						'note' => ['Graph', '[graph1]']
					]
				],
				'expected_result' => [
					[
						'auditid' => '9003'
					]
				],
				'expected_error' => null
			],
			// Validation or empty result.
			[
				'api_request' => [
					'output' => [],
					'selectDetails' => [],
					'limit' => 1
				],
				'$expected_result' => [
					[
						'details' => []
					]
				],
				'expected_error' => null
			],
			// Unsupported type of value in filter request.
			[
				'api_request' => [
					'editable' => true
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/": unexpected parameter "editable".'
			],
			// userid not exist.
			[
				'api_request' => [
					'userids' => ['1234567890']
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			[
				'api_request' => [
					'userids' => ['12345', '12345']
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// userids validation.
			[
				'api_request' => [
					'userids' => []
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			[
				'api_request' => [
					'userids' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/userids": an array is expected.'
			],
			[
				'api_request' => [
					'userids' => [[]]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/userids/1": a number is expected.'
			],
			[
				'api_request' => [
					'userids' => ['☺']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/userids/1": a number is expected.'
			],
			[
				'api_request' => [
					'userids' => ['a']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/userids/1": a number is expected.'
			],
			[
				'api_request' => [
					'userids' => ['5.5']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/userids/1": a number is expected.'
			],
			// time_from is in future.
			[
				'api_request' => [
					'userids' => '1',
					"time_from" => '1897721585'
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// time_from validation.
			[
				'api_request' => [
					'time_from' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": an integer is expected.'
			],
			[
				'api_request' => [
					'time_from' => []
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": an integer is expected.'
			],
			[
				'api_request' => [
					'time_from' => 'null'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": an integer is expected.'
			],
			[
				'api_request' => [
					'time_from' => '☺'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": an integer is expected.'
			],
			[
				'api_request' => [
					'userids' => '1',
					'time_from' => '5.0'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": an integer is expected.'
			],
			[
				'api_request' => [
					'userids' => '1',
					'time_from' => '15820974175'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_from": a number is too large.'
			],
			// time_till is 20 years ago.
			[
				'api_request' => [
					'time_till' => '951900785'
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// time_till validation.
			[
				'api_request' => [
					'time_till' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": an integer is expected.'
			],
			[
				'api_request' => [
					'time_till' => []
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": an integer is expected.'
			],
			[
				'api_request' => [
					'time_till' => 'null'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": an integer is expected.'
			],
			[
				'api_request' => [
					'time_till' => '☺'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": an integer is expected.'
			],
			[
				'api_request' => [
					'time_till' => '5.0'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": an integer is expected.'
			],
			[
				'api_request' => [
					'time_till' => '15820974175'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/time_till": a number is too large.'
			],
			// Time from more than till.
			[
				'api_request' => [
					'time_from' => '1580201585',
					'time_till' => '1548665585'
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// selectDetails validation.
			[
				'api_request' => [
					'selectDetails' => true
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails": an array or a character string is expected.'
			],
			[
				'api_request' => [
					'selectDetails' => [[]]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails/1": a character string is expected.'
			],
			[
				'api_request' => [
					'selectDetails' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails": value must be one of extend.'
			],
			[
				'api_request' => [
					'selectDetails' => 'oldvalue'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails": value must be one of extend.'
			],
			[
				'api_request' => [
					'selectDetails' => ['auditid']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails/1": value must be one of table_name, field_name, oldvalue, newvalue.'
			],
			[
				'api_request' => [
					'selectDetails' => ['oldvalue', 'auditid']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails/2": value must be one of table_name, field_name, oldvalue, newvalue.'
			],
			[
				'api_request' => [
					'selectDetails' => ['oldvalue', 'oldvalue']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/selectDetails/2": value (oldvalue) already exists.'
			],
			// output validation.
			[
				'api_request' => [
					'output' => true
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output": an array or a character string is expected.'
			],
			[
				'api_request' => [
					'output' => [[]]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output/1": a character string is expected.'
			],
			[
				'api_request' => [
					'output' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output": value must be one of extend.'
			],
			[
				'api_request' => [
					'output' => 'userid'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output": value must be one of extend.'
			],
			[
				'api_request' => [
					'output' => ['']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output/1": value must be one of auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename.'
			],
			[
				'api_request' => [
					'output' => ['table_name']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output/1": value must be one of auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename.'
			],
			[
				'api_request' => [
					'output' => ['userid', 'table_name']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output/2": value must be one of auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename.'
			],
			[
				'api_request' => [
					'output' => ['userid', 'userid']
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/output/2": value (userid) already exists.'
			],
			// Filter.
			[
				'api_request' => [
					'filter' => [
						'auditid' => ['13245']
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation.
			[
				'api_request' => [
					'filter' => [
						'port' => ['13245']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "port".'
			],
			// Filter validation - field_name.
			[
				'api_request' => [
					'filter' => [
						'field_name' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/field_name": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'field_name' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/field_name/1": a character string is expected.'
			],
			// Filter validation - table_name.
			[
				'api_request' => [
					'filter' => [
						'table_name' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/table_name": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'table_name' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/table_name/1": a character string is expected.'
			],
			// Filter validation - auditid.
			[
				'api_request' => [
					'filter' => [
						'auditid' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/auditid": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'auditid' => ['']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/auditid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'auditid' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/auditid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'auditid' => ['abc']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/auditid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'auditid' => ['123456']
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation - userid.
			[
				'api_request' => [
					'filter' => [
						'userid' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/userid": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'userid' => ['']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/userid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'userid' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/userid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'userid' => ['abc']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/userid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'userid' => ['12345678']
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation - clock.
			[
				'api_request' => [
					'filter' => [
						'clock' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/clock": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'clock' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/clock/1": an integer is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'clock' => ['15820974175']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/clock/1": a number is too large.'
			],
			// Filter validation - action.
			[
				'api_request' => [
					'filter' => [
						'action' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/action": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'action' => ['abc']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/action/1": an integer is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'action' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/action/1": an integer is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'action' => '132456'
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/action/1": value must be one of 0, 1, 2, 3, 4, 5, 6, 7.'
			],
			// Filter validation - resourcetype.
			[
				'api_request' => [
					'filter' => [
						'resourcetype' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcetype": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcetype' => 'abc'
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcetype": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcetype' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcetype": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcetype' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcetype/1": an integer is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcetype' => '1'
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcetype/1": value must be one of 0, 2, 3, 4, 5,'.
					' 6, 7, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33,'.
					' 34, 35, 36, 37, 38, 39, 40, 41, 42, 43.'
			],
			// Filter validation - ip.
			[
				'api_request' => [
					'filter' => [
						'ip' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/ip/1": a character string is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'ip' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/ip": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'ip' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation - resourceid.
			[
				'api_request' => [
					'filter' => [
						'resourceid' => ''
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourceid": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourceid' => ['']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourceid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourceid' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourceid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourceid' => ['abc']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourceid/1": a number is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourceid' => ['123456']
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation - resourcename.
			[
				'api_request' => [
					'filter' => [
						'resourcename' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcename/1": a character string is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcename' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/resourcename": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'resourcename' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Filter validation - note.
			[
				'api_request' => [
					'filter' => [
						'note' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/note/1": a character string is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'note' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/note": an array is expected.'
			],
			[
				'api_request' => [
					'filter' => [
						'note' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Search validation.
			[
				'api_request' => [
					'search' => [
						'table_name' => ['13245']
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search": unexpected parameter "table_name".'
			],
			// Search validation - note.
			[
				'api_request' => [
					'search' => [
						'note' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/note/1": a character string is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'note' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/note": an array is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'note' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Search validation - ip.
			[
				'api_request' => [
					'search' => [
						'ip' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/ip/1": a character string is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'ip' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/ip": an array is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'ip' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Search validation - resourcename.
			[
				'api_request' => [
					'search' => [
						'resourcename' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/resourcename/1": a character string is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'resourcename' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/resourcename": an array is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'resourcename' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Search validation - oldvalue.
			[
				'api_request' => [
					'search' => [
						'oldvalue' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/oldvalue/1": a character string is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'oldvalue' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/oldvalue": an array is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'oldvalue' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// Search validation - newvalue.
			[
				'api_request' => [
					'search' => [
						'newvalue' => [[]]
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/newvalue/1": a character string is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'newvalue' => true
					]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/search/newvalue": an array is expected.'
			],
			[
				'api_request' => [
					'search' => [
						'newvalue' => 'утф'
					]
				],
				'$expected_result' => [],
				'expected_error' => null
			],
			// sortfield validation.
			[
				'api_request' => [
					'sortfield' => ''
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of auditid, userid, clock.'
			],
			[
				'api_request' => [
					'sortfield' => 'утф'
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of auditid, userid, clock.'
			],
			[
				'api_request' => [
					'sortfield' => [[]]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/sortfield/1": a character string is expected.'
			],
			[
				'api_request' => [
					'sortfield' => ["123"]
				],
				'$expected_result' => null,
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of auditid, userid, clock.'
			]
		];
	}

	/**
	 * @dataProvider getAuditLogData
	 */
	public function testAuditLog_Get($api_request, $expected_result, $expected_error) {
		$result = $this->call('auditlog.get', $api_request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($result['result'], $expected_result);
		}
	}

	public static function getUserPermissionData() {
		return [
			[
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'api_request' => [
					'userids' => '1'
				],
				'expected_error' => 'No permissions to call "auditlog.get".'
			],
			[
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'api_request' => [
					'userids' => '1'
				],
				'expected_error' => 'No permissions to call "auditlog.get".'
			],
			[
				'user' => ['user' => 'guest', 'password' => ''],
				'api_request' => [
					'userids' => '1'
				],
				'expected_error' => 'No permissions to call "auditlog.get".'
			]
		];
	}

	/**
	 * @dataProvider getUserPermissionData
	 */
	public function testAuditLog_UserPermissions($user, $api_request, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call('auditlog.get', $api_request, $expected_error);
	}

	/**
	 * Change status of "Disabled" user group.
	 */
	public static function setUserGroupStatus($value) {
		DBexecute('UPDATE usrgrp SET users_status='.zbx_dbstr($value).' WHERE usrgrpid=9');
	}

	public function enableUserGroup() {
		$this->setUserGroupStatus(0);
	}

	public static function disableUserGroup() {
		self::setUserGroupStatus(1);
	}
}
