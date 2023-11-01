<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup items
 */
class testFormItemHttpAgent extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Host id used in test.
	 */
	const HOSTID = 50010;

	/*
	 * Check form fields after create or update item.
	 */
	private function checkFormFields($rows) {
		$this->query('link:'.$rows['Name'])->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('name'));

		foreach ($rows as $field_name => $value) {
			$form_field = $form->getField($field_name);

			if ($field_name === 'Value mapping') {
				$this->assertEquals($rows['Value mapping'], implode($form->getField('Value mapping')->asMultiselect()
						->getSelected())
				);
			}
			else {
			$this->assertEquals($value, $form_field->getValue());
			}
		}
	}

	/**
	 * Add, update, delete query or headers fields.
	 */
	private function processPairFields($rows, $id_part) {

	$element_id = ($id_part === 'query_fields') ? 'query-fields' : 'headers';

		foreach ($rows as $i => $field_pair) {
			$i++;

			switch (CTestArrayHelper::get($field_pair, 'action', 'add')) {
				case 'add':
					if (!$this->zbxTestElementPresentId($id_part.'_'.($i-1).'_name')) {
						COverlayDialogElement::find()->one()->waitUntilReady()
								->query('xpath://div[contains(@id, "'.$element_id.'")]//button[@class="btn-link element-table-add"]')
								->one()->click();
					}
					// break is not missing here.
				case 'update':
					if (array_key_exists('name', $field_pair)) {
						$this->zbxTestWaitUntilElementVisible(WebDriverBy::id($id_part.'_'.($i-1).'_name'));
						$this->zbxTestInputType($id_part.'_'.($i-1).'_name', $field_pair['name']);
					}
					if (array_key_exists('value', $field_pair)) {
						$this->zbxTestWaitUntilElementVisible(WebDriverBy::id($id_part.'_'.($i-1).'_value'));
						$this->zbxTestInputType($id_part.'_'.($i-1).'_value', $field_pair['value']);
					}
					break;

				case 'remove':
					$this->query('xpath://table[@id="'.$element_id.'-table"]/tbody/tr['.$i.']/td[5]/button')->one()->click();
					break;
			}
		}
	}

	/**
	 * Parse url and check result in query fields.
	 */
	private function parseUrlAndCheckQuery($rows, $parsed_url = false) {
		COverlayDialogElement::find()->one()->waitUntilready()->query('button:Parse')->one()->click();

		foreach ($rows as $i => $parsed_query) {
			$i += (!$this->zbxTestElementPresentId('query_fields_0_name') ? 1 : 0);
			$name = $this->zbxTestGetValue("//input[@id='query_fields_".$i."_name']");
			$this->assertEquals($parsed_query['name'], $name);

			if (array_key_exists('value', $parsed_query)) {
				$value = $this->zbxTestGetValue("//input[@id='query_fields_".$i."_value']");
				$this->assertEquals($value, $parsed_query['value']);
			}
		}

		// Check url after parse.
		if ($parsed_url) {
			$url = $this->zbxTestGetValue("//input[@id='url']");
			$this->assertEquals($parsed_url, $url);
		}
	}

	public static function getUrlParseData() {
		$url = 'https://intranet.zabbix.com/secure/admin.jspa';

		return [
			// Simple parse with name only.
			[
				[
					'url' => $url.'?login',
					'parsed_query' => [
						['name' => 'login']
					],
					'parsed_url' => $url
				]
			],
			[
				[
					'url' => $url.'?login',
					'query' => [
						['name' => 'login']
					],
					'parsed_query' => [
						['name' => 'login'],
						['name' => 'login']
					],
					'parsed_url' => $url
				]
			],
			[
				[
					'url' => $url.'?login',
					'query' => [
						['name' => 'login', 'value' => 'admin']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'login']
					],
					'parsed_url' => $url
				]
			],
			// Simple parse with name and value.
			[
				[
					'url' => $url.'?login=admin&password=s00p3r%24ecr3%26',
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'parsed_url' => $url
				]
			],
			// After parse added new query fields.
			[
				[
					'url' => $url.'?password=s00p3r%24ecr3%26',
					'query' => [
						['name' => 'login', 'value' => 'admin']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'parsed_url' => $url
				]
			],
			[
				[
					'url' => $url.'?login=admin&password=s00p3r%24ecr3%26',
					'query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&'],
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'parsed_url' => $url
				]
			],
			[
				[
					'url' => $url.'?login=user&password=a123%24bcd4%26',
					'query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 'password']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 'password'],
						['name' => 'login', 'value' => 'user'],
						['name' => 'password', 'value' => 'a123$bcd4&']
					],
					'parsed_url' => $url
				]
			],
			// URL fragment part ignored.
			[
				[
					'step_name' => 'Step URL fragment part ignored',
					'url' => 'https://www.zabbix.com/enterprise_ready#test',
					'parsed_query' => [
						['name' => '', 'value' => '']
					],
					'parsed_url' => 'https://www.zabbix.com/enterprise_ready'
				]
			],
			// User macro in url.
			[
				[
					'step_name' => 'Step URL fragment part ignored',
					'url' => $url.'?{$LOGIN}={$USER}&{$PASSWORD}={$MYPASSWORD}',
					'parsed_query' => [
						['name' => '{$LOGIN}', 'value' => '{$USER}'],
						['name' => '{$PASSWORD}', 'value' => '{$MYPASSWORD}']
					],
					'parsed_url' => $url
				]
			],
			// Host and item macro in url.
			[
				[
					'step_name' => 'Step URL fragment part ignored',
					'url' => $url.'?{HOST.HOST}={HOST.IP}&{ITEM.ID}={ITEM.KEY}',
					'parsed_query' => [
						['name' => '{HOST.HOST}', 'value' => '{HOST.IP}'],
						['name' => '{ITEM.ID}', 'value' => '{ITEM.KEY}']
					],
					'parsed_url' => $url
				]
			],
			// Call to Prometheus API.
			[
				[
					'step_name' => 'Step call to Prometheus API',
					'url' => 'http://localhost:9090/api/v1/query?query=irate(node_network_transmit_bytes_total\{device!="lo",instance="192.168.150.101"}[1m])',
					'parsed_query' => [
						['name' => 'query', 'value' => 'irate(node_network_transmit_bytes_total\{device!="lo",instance="192.168.150.101"}[1m])']
					],
					'parsed_url' => 'http://localhost:9090/api/v1/query'
				]
			],
			// URL parse failed.
			[
				[
					'url' => 'http://localhost/zabbix/index.php?test=%11',
					'error' => 'Failed to parse URL. URL is not properly encoded.'
				]
			]
		];
	}

	/**
	 * Test URL parsing.
	 *
	 * @dataProvider getUrlParseData
	 */
	public function testFormItemHttpAgent_UrlParse($data) {
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->query('button:Create item')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilready();
		$form = $dialog->asForm();
		$form->getField('Type')->asDropdown()->select('HTTP agent');
		$form->getField('URL')->type($data['url']);

		// Type query fields.
		if (array_key_exists('query', $data)) {
			$this->processPairFields($data['query'], 'query_fields');
		}

		// Check query fields and new url after parse.
		if (array_key_exists('parsed_query', $data)) {
			$this->parseUrlAndCheckQuery($data['parsed_query'], $data['parsed_url']);
		}

		// Check that URL parse failed.
		if (array_key_exists('error', $data)) {
			$dialog->query('button:Parse')->one()->click();
			$get_text = $this->zbxTestGetText("//div[@class='overlay-dialogue-body']/span");
			$result = trim(preg_replace('/\s\s+/', ' ', $get_text));
			$this->assertEquals($result, $data['error']);
		}
	}

	/*
	 * Test form validation.
	 */
	private function executeValidation($data, $action) {
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);

		switch ($action) {
			case 'create':
				$this->zbxTestContentControlButtonClickTextWait('Create item');
				$dialog = COverlayDialogElement::find()->one()->waitUntilready();
				$form = $dialog->asForm();
				$form->getField('Type')->asDropdown()->select('HTTP agent');
				break;

			case 'update':
				$update_item = 'Http agent item for update';
				$sql_hash = 'SELECT * FROM items ORDER BY itemid';
				$old_hash = CDBHelper::getHash($sql_hash);
				$this->zbxTestClickLinkTextWait($update_item);
				$dialog = COverlayDialogElement::find()->one()->waitUntilready();
				$form = $dialog->asForm();
				break;

			case 'clone':
				$clone_item = 'Http agent item form';
				$sql_hash = 'SELECT * FROM items ORDER BY itemid';
				$old_hash = CDBHelper::getHash($sql_hash);
				$this->zbxTestClickLinkTextWait($clone_item);
				COverlayDialogElement::find()->one()->waitUntilready()->getFooter()->query('button:Clone')->one()->click();
				$dialog = COverlayDialogElement::find()->one()->waitUntilready();
				$form = $dialog->asForm();
				break;
		}

		// Fill in fields.
		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}
		if (array_key_exists('request_type', $data)) {
			$this->zbxTestClickXpathWait("//ul[@id='post_type']//label[text()='".$data['request_type']."']");
		}
		if (array_key_exists('query', $data)) {
			$this->processPairFields($data['query'], 'query_fields');
		}
		if (array_key_exists('headers', $data)) {
			$this->processPairFields($data['headers'], 'headers');
		}

		// Press action button and check the result in DB.
		switch ($action) {
			case 'create':
				$dialog->getFooter()->query('button:Add')->one()->click();
				if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
					$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM items WHERE name='.zbx_dbstr($data['fields']['Name'])));
				}
				break;

			case 'update':
				$dialog->getFooter()->query('button:Update')->one()->click();
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
				if (!array_key_exists('error', $data)) {
					$data['error'] = 'Cannot update item';
				}
				break;

			case 'clone':
				$dialog->getFooter()->query('button:Add')->one()->click();
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
				if (!array_key_exists('error', $data)) {
					$data['error'] = 'Cannot add item';
				}
				break;
		}

		// Check error message on posting the form.
		$this->assertMessage(TEST_BAD, $data['error'], $data['error_details']);
	}

	public static function getCreateValidationData() {
		return [
			// Check error message on posting the form with empty values.
			[
				[
					'error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "key": cannot be empty.'
					],
					'check_db' => false,
					'error' => 'Cannot add item'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item without url',
						'Key' => 'item-without-url'
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/url": cannot be empty.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with space in url',
						'Key' => 'item-space-url',
						'URL' => ' '
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/url": cannot be empty.'
					]
				]
			],
			// Check query fields.
			[
				[
					'fields' => [
						'Name' => 'item without query field name',
						'Key' => 'item-without-query-name',
						'URL' => 'zabbix.com'
					],
					'query' => [
						['value' => 'admin']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/query_fields": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item without second query field name',
						'Key' => 'item-without-second-query-name',
						'URL' => 'zabbix.com'
					],
					'query' => [
						['name' => 'user', 'value' => 'admin'],
						['value' => 'admin']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/query_fields": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with space in query field name',
						'Key' => 'item-space-query-name',
						'URL' => 'zabbix.com'
					],
					'query' => [
						['name' => ' ', 'value' => 'admin']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/query_fields": nonempty key and value pair expected.'
					]
				]
			],
			// Check header fields.
			[
				[
					'fields' => [
						'Name' => 'item without header name',
						'Key' => 'item-without-header-name',
						'URL' => 'zabbix.com'
					],
					'headers' => [
						['value' => 'admin']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item without second header name',
						'Key' => 'item-without-second-header-name',
						'URL' => 'zabbix.com'
					],
					'headers' => [
						['name' => 'user', 'value' => 'admin'],
						['value' => 'zabbix']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item without second header value',
						'Key' => 'item-without-second-header-value',
						'URL' => 'zabbix.com'
					],
					'headers' => [
						['name' => 'user', 'value' => 'admin'],
						['name' => 'password']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with space in header field name',
						'Key' => 'item-space-header-name',
						'URL' => 'zabbix.com'
					],
					'headers' => [
						['name' => ' ', 'value' => 'admin']
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			// Check request body.
			[
				[
					'fields' => [
						'Name' => 'item with empty JSON request body',
						'Key' => 'check-empty-json',
						'URL' => 'zabbix.com'
					],
					'request_type' => 'JSON data',
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/posts": cannot be empty.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with wrong JSON request body',
						'Key' => 'check-json',
						'URL' => 'zabbix.com',
						'Request body' => '{"<key>": "<value>"'
					],
					'request_type' => 'JSON data',
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/posts": JSON is expected.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with XML empty request body',
						'Key' => 'check-empty-xml',
						'URL' => 'zabbix.com'
					],
					'request_type' => 'XML data',
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/posts": cannot be empty.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with wrong XML request body',
						'Key' => 'check-wrong-xml',
						'URL' => 'zabbix.com',
						'Request body' => 'test'
					],
					'request_type' => 'XML data',
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/posts": (4) Start tag expected, \'<\' not found'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with wrong XML request body',
						'Key' => 'check-xml',
						'URL' => 'zabbix.com',
						'Request body' => '<foo>bar</foo'
					],
					'request_type' => 'XML data',
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/posts": (73) expected \'>\''
					]
				]
			],
			// Check required status codes.
			[
				[
					'fields' => [
						'Name' => 'item with symbol in status code',
						'Key' => 'check-code',
						'URL' => 'zabbix.com',
						'Required status codes' => '*'
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/status_codes": invalid range expression.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'item with string in status code',
						'Key' => 'check-code-string',
						'URL' => 'zabbix.com',
						'Required status codes' => 'test'
					],
					'error' => 'Cannot add item',
					'error_details' => [
						'Invalid parameter "/1/status_codes": invalid range expression.'
					]
				]
			]
		];
	}

	/**
	 * Test item form validation when creating.
	 *
	 * @dataProvider getCreateValidationData
	 */
	public function testFormItemHttpAgent_CreateValidation($data) {
		$this->executeValidation($data, 'create');
	}

	public static function getValidationData() {
		return [
			// Check error message on posting the form with empty values.
			[
				[
					'fields' => [
						'Name' => '',
						'Key' => '',
						'URL' => ''
					],
					'error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "key": cannot be empty.'
					]
				]
			],
			// Check query fields.
			[
				[
					'query' => [
						['name' => '', 'value' => 'admin', 'action' => 'update']
					],
					'error_details' => [
						'Invalid parameter "/1/query_fields": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'query' => [
						['name' => 'user update', 'value' => 'admin update', 'action' => 'update'],
						['value' => 'admin']
					],
					'error_details' => [
						'Invalid parameter "/1/query_fields": nonempty key and value pair expected.'
					]
				]
			],
			// Check header fields.
			[
				[
					'headers' => [
						['name' => 'user update', 'value' => '', 'action' => 'update']
					],
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'headers' => [
						['name' => '', 'value' => 'admin update', 'action' => 'update']
					],
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'headers' => [
						['name' => 'user update', 'value' => 'admin update', 'action' => 'update'],
						['value' => 'admin']
					],
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			[
				[
					'headers' => [
						['name' => 'user update', 'value' => 'admin update', 'action' => 'update'],
						['name' => 'user']
					],
					'error_details' => [
						'Invalid parameter "/1/headers": nonempty key and value pair expected.'
					]
				]
			],
			// Check request body.
			[
				[
					'request_type' => 'JSON data',
					'error_details' => [
						'Invalid parameter "/1/posts": cannot be empty.'
					]
				]
			],
			[
				[
					'request_type' => 'XML data',
					'error_details' => [
						'Invalid parameter "/1/posts": cannot be empty.'
					]
				]
			],
			[
				[
					'fields' => [
						'Request body' => 'test'
					],
					'request_type' => 'XML data',
					'error_details' => [
						'Invalid parameter "/1/posts": (4) Start tag expected, \'<\' not found [Line: 1 | Column: 1].'
					]
				]
			],
			// Check required status codes.
			[
				[
					'fields' => [
						'Required status codes' => '*'
					],
					'error_details' => [
						'Invalid parameter "/1/status_codes": invalid range expression.'
					]
				]
			],
			[
				[
					'fields' => [
						'Required status codes' => 'test'
					],
					'error_details' => [
						'Invalid parameter "/1/status_codes": invalid range expression.'
					]
				]
			]
		];
	}

	/**
	 * Test item form validation when updating.
	 *
	 * @dataProvider getValidationData
	 */
	public function testFormItemHttpAgent_UpdateValidation($data) {
		$this->executeValidation($data, 'update');
	}

	/**
	 * Test item form validation when cloning.
	 *
	 * @dataProvider getValidationData
	 */
	public function testFormItemHttpAgent_CloneValidation($data) {
		$this->executeValidation($data, 'clone');
	}

	public static function getCreataData() {
		return [
			// Fill required fields and check default values.
			[
				[
					'fields' => [
						'Name' => 'item with minimum fields',
						'Key' => 'http.item',
						'URL' => 'zabbix.com'
					],
					'check_form' => true
				]
			],
			// Macro in fields.
			[
				[
					'fields' => [
						'Name' => 'item with macro in url',
						'Key' => 'http.url',
						'URL' => '{$MACRO}',
						'Request body' => '{$MACRO}',
						'Required status codes' => '{$MACRO}'
					],
					'check_form' => true
				]
			],
			// Symbols in query and headers field
			[
				[
					'fields' => [
						'Name' => 'item with symbols in fields',
						'Key' => 'http.symbols',
						'URL' => 'zabbix.com'
					],
					'query' => [
						['name' => 'user'],
						['name' => '!\'(foo);:@&=+$,/?#[]', 'value' => '!\'(foo);:@&=+$,/?#[]']
					],
					'headers' => [
						['name' => 'user', 'value' => 'admin'],
						['name' => '!\'(foo);:@&=+$,/?#[]', 'value' => '!\'(foo);:@&=+$,/?#[]']
					]
				]
			],
			// JSON body
			[
				[
					'fields' => [
						'Name' => 'item with macro in JSON body',
						'Key' => 'http.json',
						'URL' => 'zabbix.com',
						'Request body' => '{"{$MACRO}":"{ITEM.KEY}"}'
					],
					'request_type' => 'JSON data'
				]
			],
			// XML body
			[
				[
					'fields' => [
						'Name' => 'item with XML body',
						'Key' => 'http.xml',
						'URL' => 'zabbix.com',
						'Request body' => '<data><macro>{$MACRO}</macro><![CDATA[{$MACRO}<foo></bar>]]></data>'
					],
					'request_type' => 'XML data'
				]
			],
			// All possible fields.
			[
				[
					'fields' => [
						'Name' => 'item with all fields',
						'Key' => 'all.fields',
						'Type of information' => 'Character',
						'URL' => 'zabbix.com',
						'Request type' => 'PUT',
						'Request body' => '{"key":"value"}',
						'Required status codes' => '0, 100-500',
						'Follow redirects' => true,
						'Convert to JSON' => true,
						'HTTP proxy' => '[protocol://][user[:password]@]proxy.example.com[:port]',
						'HTTP authentication' => 'Basic',
						'User name' => 'admin',
						'Password' => 'zabbix',
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'SSL certificate file' => 'ssl_file',
						'SSL key file' => 'ssl_key',
						'SSL key password' => 'ssl_password',
						'id:timeout' => '1m',
						'Value mapping' => 'Service state',
						'Enable trapping' => true,
						'Description' => 'awesome item',
						'Enabled' => false
					],
					'query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'headers' => [
						['name' => 'Content-Type', 'value' => 'application/json'],
						['name' => 'Content-Type', 'value' => 'application/xml']
					],
					'request_type' => 'JSON data',
					'override_timeout' => true,
					'check_form' => true,
					'screenshot' => true
				]
			],
			// Empty Basic authentication user/password
			[
				[
					'fields' => [
						'Name' => 'Empty Basic User/Password',
						'Key' => 'basic.empty.user.pass',
						'URL' => 'zabbix.com',
						'HTTP authentication' => 'Basic'
					],
					'check_form' => true
				]
			],
			// Empty NTLM authentication user/password
			[
				[
					'fields' => [
						'Name' => 'Empty NTLM User/Password',
						'Key' => 'ntlm.empty.user.pass',
						'URL' => 'zabbix.com',
						'HTTP authentication' => 'NTLM'
					],
					'check_form' => true
				]
			],
			// Empty Kerberos authentication user/password.
			[
				[
					'fields' => [
						'Name' => 'Empty Kerberos',
						'Key' => 'kerberos.empty',
						'URL' => 'zabbix.com',
						'HTTP authentication' => 'Kerberos'
					],
					'check_form' => true
				]
			],
			// Kerberos authentication with user/password.
			[
				[
					'fields' => [
						'Name' => 'Empty Kerberos User/Password',
						'Key' => 'kerberos.empty.user.pass',
						'URL' => 'zabbix.com',
						'HTTP authentication' => 'Kerberos',
						'User name' => 'admin',
						'Password' => 'zabbix'
					],
					'check_form' => true
				]
			]
		];
	}

	/**
	 * Test creation of a HTTP agent item.
	 *
	 * @dataProvider getCreataData
	 */
	public function testFormItemHttpAgent_Create($data) {
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->query('button:Create item')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilready();
		$form = $dialog->asForm();
		$form->getField('Type')->asDropdown()->select('HTTP agent');

		if (array_key_exists('query', $data)) {
			$this->processPairFields($data['query'], 'query_fields');
		}

		if (array_key_exists('headers', $data)) {
			$this->processPairFields($data['headers'], 'headers');
		}

		// Take a screenshot to test draggable object position of query and headers fields.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			$this->assertScreenshot($this->query('id:query-fields-table')->one(), 'Query fields');
			$this->assertScreenshot($this->query('id:headers-table')->one(), 'Headers fields');
		}

		if (array_key_exists('request_type', $data)) {
			$form->getField('id:post_type')->asSegmentedRadio()->select($data['request_type']);
		}

		if (array_key_exists('override_timeout', $data)) {
			$form->getField('id:custom_timeout')->asSegmentedRadio()->select('Override');
		}

		$form->fill($data['fields']);

		if (array_key_exists('HTTP authentication', $data['fields']) && $data['fields']['HTTP authentication'] != 'None') {
			$this->zbxTestAssertVisibleId('http_username');
			$this->zbxTestAssertVisibleId('http_password');
		}
		else {
			$this->zbxTestAssertNotVisibleId('http_username');
			$this->zbxTestAssertNotVisibleId('http_password');
		}

		$check = (array_key_exists('Request type', $data['fields']) && $data['fields']['Request type'] === 'HEAD')
				? 'zbxTestAssertElementPresentXpath'
				: 'zbxTestAssertElementNotPresentXpath';

		// 4 is retrieve mode count.
		for ($i = 0; $i < 4; $i++) {
			$this->$check("//input[@id='retrieve_mode_".$i."'][@disabled]");
		}

		// Check query fields after url parse.
		if (array_key_exists('parsed_query', $data)) {
			$this->parseUrlAndCheckQuery($data['parsed_query'], $data['parsed_url']);
		}

		$dialog->getFooter()->query('button:Add')->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');
		$this->zbxTestTextPresent($data['fields']['Name']);

		// Check the results in DB.
		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE name='.zbx_dbstr($data['fields']['Name'])));
		}

		// Check the results in form after creation.
		if (array_key_exists('check_form', $data) && $data['check_form'] === true) {
			$defaults = [
				'Request type' => 'GET',
				'Timeout' => '3s',
				'Required status codes' => '200',
				'Follow redirects' => true,
				'Convert to JSON' => false,
				'HTTP authentication' => 'None',
				'SSL verify peer' => false,
				'SSL verify host' => false,
				'Type of information' => 'Numeric (unsigned)',
				'Update interval' => '1m',
				'Value mapping' => '',
				'Enable trapping' => false,
				'Populates host inventory field' => '-None-',
				'Enabled' => true
			];

			foreach ($defaults as $field => $value) {
				if (!array_key_exists($field, $data['fields'])) {
					$data['fields'][$field] = $value;
				}
			}

			$this->checkFormFields($data['fields']);
		}
	}

	public static function getUpdateData() {
		return [
			// Symbols and macro in fields.
			[
				[
					'fields' => [
						'URL' => '{$MACRO}',
						'Request body' => '{$MACRO}'
					],
					'query' => [
						['action' => 'remove'],
						['name' => '!\'(foo);:@&=+$,/?#[]', 'value' => '!\'(foo);:@&=+$,/?#[]', 'action' => 'add']
					],
					'headers' => [
						['action' => 'remove'],
						['name' => '!\'(foo);:@&=+$,/?#[]', 'value' => '!\'(foo);:@&=+$,/?#[]', 'action' => 'add']
					],
					'check_form' => true
				]
			],
			// JSON body with macro.
			[
				[
					'fields' => [
						'Request body' => '{"{$MACRO}":"{ITEM.KEY}"}'
					],
					'request_type' => 'JSON data',
					'check_form' => true
				]
			],
			// XML body with macro.
			[
				[
					'fields' => [
						'Request body' => '<data><macro>{$MACRO}</macro><![CDATA[{$MACRO}<foo></bar>]]></data>'
					],
					'request_type' => 'XML data',
					'check_form' => true
				]
			],
			// Empty Basic authentication user/password
			[
				[
					'fields' => [
						'HTTP authentication' => 'Basic'
					],
					'check_form' => true
				]
			],
			// Empty NTLM authentication user/password
			[
				[
					'fields' => [
						'HTTP authentication' => 'NTLM'
					],
					'check_form' => true
				]
			],
			// Empty Kerberos authentication user/password.
			[
				[
					'fields' => [
						'HTTP authentication' => 'Kerberos'
					],
					'check_form' => true
				]
			],
			// Kerberos authentication with user/password.
			[
				[
					'fields' => [
						'HTTP authentication' => 'Kerberos',
						'User name' => 'k_admin',
						'Password' => 'zabbix_k'
					],
					'check_form' => true
				]
			],
			// All possible fields.
			[
				[
					'fields' => [
						'Name' => 'Http agent item updated',
						'Key' => 'update.all.fields',
						'URL' => 'updatezabbix.com',
						'Type of information' => 'Character',
						'Request type' => 'PUT',
						'Request body' => '{"key":"value"}',
						'Follow redirects' => true,
						'Convert to JSON' => true,
						'HTTP proxy' => '[protocol://][user[:password]@]proxy.example.com[:port]',
						'HTTP authentication' => 'Basic',
						'User name' => 'admin',
						'Password' => 'zabbix',
						'SSL certificate file' => 'ssl_file_update',
						'SSL key file' => 'ssl_key_update',
						'SSL key password' => 'ssl_password_update',
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'id:timeout' => '1m',
						'Value mapping' => 'Service state',
						'Enable trapping' => true,
						'Enabled' => false
					],
					'query' => [
						['action' => 'remove']
					],
					'headers' => [
						['action' => 'remove']
					],
					'request_type' => 'JSON data',
					'override_timeout' => true,
					'check_form' => true
				]
			]
		];
	}

	/**
	 * Update HTTP agent item.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormItemHttpAgent_Update($data) {
		$update_item = 'Http agent item for update';
		// Get item name for update if it is not set.
		if (!array_key_exists('Name', $data['fields'])) {
			$data['fields']['Name'] = $update_item;
		}

		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->zbxTestClickLinkTextWait($update_item);
		$dialog = COverlayDialogElement::find()->one()->waitUntilready();
		$form = $dialog->asForm();

		if (array_key_exists('query', $data)) {
			$this->processPairFields($data['query'], 'query_fields');
		}

		if (array_key_exists('headers', $data)) {
			$this->processPairFields($data['headers'], 'headers');
		}

		if (array_key_exists('request_type', $data)) {
			$this->zbxTestClickXpath("//ul[@id='post_type']//label[text()='".$data['request_type']."']");
		}

		if (array_key_exists('override_timeout', $data)) {
			$form->getField('id:custom_timeout')->asSegmentedRadio()->select('Override');
		}

		$form->fill($data['fields']);
		$dialog->getFooter()->query('button:Update')->one()->click();

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item updated');
		$this->zbxTestTextPresent($data['fields']['Name']);

		// Check the results in DB.
		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE name='.zbx_dbstr($data['fields']['Name'])));
		}

		// Check the results in form after update.
		if (array_key_exists('check_form', $data) && $data['check_form'] === true) {
			$this->checkFormFields($data['fields']);
		}
	}

	/**
	 * Update without any modification of HTTP agent item.
	 */
	public function testFormItemHttpAgent_SimpleUpdate() {
		$sql_hash = 'SELECT * FROM items ORDER BY itemid';
		$old_hash = CDBHelper::getHash($sql_hash);
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);

		$sql = 'SELECT name'.
				' FROM items'.
				' WHERE type='.ITEM_TYPE_HTTPAGENT.' AND hostid='.self::HOSTID.
				' ORDER BY itemid'.
				' LIMIT 3';

		foreach (CDBHelper::getAll($sql) as $item) {
			$this->zbxTestClickLinkText($item['name']);
			$this->zbxTestWaitForPageToLoad();
			$dialog = COverlayDialogElement::find()->one()->waitUntilready();
			$dialog->getFooter()->query('button:Update')->one()->click();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item updated');
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Test cloning of HTTP Agent item.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormItemHttpAgent_Clone($data) {
		$clone_item = 'Http agent item form';
		$data['fields']['Name'] = 'Test cloned HTTP agent item '.microtime(true);
		$data['fields']['Key'] = 'http.cloned.item.'.microtime(true);

		$sql_hash = 'SELECT * FROM items WHERE name='.zbx_dbstr($clone_item);
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->zbxTestClickLinkTextWait($clone_item);
		$dialog = COverlayDialogElement::find()->one()->waitUntilready();
		$dialog->getFooter()->query('button:Clone')->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$cloned_form = $overlay->asForm();

		if (array_key_exists('request_type', $data)) {
			$this->zbxTestClickXpath("//ul[@id='post_type']//label[text()='".$data['request_type']."']");
		}

		if (array_key_exists('query', $data)) {
			$this->processPairFields($data['query'], 'query_fields');
		}

		if (array_key_exists('headers', $data)) {
			$this->processPairFields($data['headers'], 'headers');
		}

		if (array_key_exists('override_timeout', $data)) {
			$cloned_form->getField('id:custom_timeout')->asSegmentedRadio()->select('Override');
		}

		$cloned_form->fill($data['fields']);
		$overlay->getFooter()->query('button:Add')->one()->click();

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');
		$this->zbxTestTextPresent($data['fields']['Name']);

		// Check the results in DB.
		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE name='.zbx_dbstr($data['fields']['Name'])));
			$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
		}

		// Check the results in form after clone.
		if (array_key_exists('check_form', $data) && $data['check_form'] === true) {
			$this->checkFormFields($data['fields']);
		}
	}

	/**
	 * Test deleting of HTTP agent item.
	 */
	public function testFormItemHttpAgent_Delete() {
		$name = 'Http agent item for delete';

		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->zbxTestClickLinkTextWait($name);
		COverlayDialogElement::find()->one()->waitUntilReady()->getFooter()->query('button:Delete')->one()->click();
		$this->page->acceptAlert();

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item deleted');

		// Check the results in DB.
		$sql = 'SELECT * FROM items WHERE name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Cancel creation of HTTP agent item.
	 */
	public function testFormItemHttpAgent_CancelCreation() {
		$data = [
			'Type' => 'HTTP agent',
			'Name' => 'Cancel creation',
			'Key' => 'http.cancel',
			'URL' => 'zabbix.com'
		];
		$sql_hash = 'SELECT * FROM items WHERE type='.ITEM_TYPE_HTTPAGENT.' ORDER BY itemid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);
		$this->query('button:Create item')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilready();
		$form = $dialog->asForm();
		$form->fill($data);
		$dialog->getFooter()->query('button:Cancel')->one()->click();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');
		$this->zbxTestTextNotPresent($data['Name']);

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Cancel updating, cloning or deleting of HTTP agent item.
	 */
	private function executeCancelAction($action) {
		$sql_hash = 'SELECT * FROM items ORDER BY itemid';
		$old_hash = CDBHelper::getHash($sql_hash);
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.self::HOSTID);

		foreach (CDBHelper::getRandom('SELECT name FROM items WHERE type='.ITEM_TYPE_HTTPAGENT.
				' AND hostid='.self::HOSTID , 1) as $item) {
			$name = $item['name'];
			$this->zbxTestClickLinkText($name);
			$dialog = COverlayDialogElement::find()->one()->waitUntilready();
			$form = $dialog->asForm();
			switch ($action) {
				case 'update':
					$name .= ' (updated)';
					$form->getField('Name')->fill('$name');
					$dialog->getFooter()->query('button:Cancel')->one()->click();
					break;

				case 'clone':
					$name .= ' (cloned)';
					$form->getField('Name')->fill('$name');
					$dialog->getFooter()->query('button:Clone')->one()->click();
					$dialog->getFooter()->query('button:Cancel')->one()->click();
					break;

				case 'delete':
					$dialog->getFooter()->query('button:Delete')->one()->click();
					$this->page->dismissAlert();
					$dialog->close();
					break;
			}

			$this->zbxTestCheckTitle('Configuration of items');
			$this->zbxTestCheckHeader('Items');

			if ($action !== 'delete') {
				$this->zbxTestTextNotPresent($name);
			}
			else {
				$this->zbxTestTextPresent($name);
			}
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Cancel update of HTTP agent item.
	 */
	public function testFormItemHttpAgent_CancelUpdating() {
		$this->executeCancelAction('update');
	}

	/**
	 * Cancel cloning of HTTP agent item.
	 */
	public function testFormItemHttpAgent_CancelCloning() {
		$this->executeCancelAction('clone');
	}

	/**
	 * Cancel deleting of HTTP agent item.
	 */
	public function testFormItemHttpAgent_CancelDelete() {
		$this->executeCancelAction('delete');
	}
}
