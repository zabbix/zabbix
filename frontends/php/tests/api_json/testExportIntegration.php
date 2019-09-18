<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * [ ] Admin user is added to debug group. To assert hidden undefined indexes etc..
 * [ ] ESC Easy setup case - "create lld such and such"
 * [ ] Autoassertions on given setup case.
 *   [ ] 1. export ESC -> XML
 *   [ ] 2. delete ESC
 *   [ ] 3. import XML
 *   [ ] 4. Assert: ESC['select'] == ESC['expect']
 * 
 * No implicit assertions - all assertions are defined within this class for easy debug.
 * 
 * @backupStaticAttributes enabled
 * @backupGlobals disabled
 * @runTestsInSeparateProcesses
 */

class ClientError {

	/**
	 * @var string
	 */
	public $reason;

	/**
	 * @var array
	 */
	public $data;

	protected function __construct(string $reason, array $data = []) {
		$this->reason = $reason;
		$this->data = $data;
	}

	/**
	 * @param string $body
	 *
	 * @return ClientError
	 */
	public static function json(string $body) {
		return new self('Server returned not json.', ['actual_response' => $body]);
	}

	/**
	 * @param array $resp  Full actual server response.
	 *
	 * @return ClientError
	 */
	public static function from_resp(array $resp) {
		return new self($resp['error']['message'], $resp['error']);
	}

	/**
	 * @return ClientError
	 */
	public static function no_resp() {
		return new self('Server returned no body.');
	}

	/**
	 * @return string
	 */
	public function __toString() {
		$details = '';
		foreach ($this->data as $key => $value) {
			$details .= $key.':'.$value.PHP_EOL;
		}

		return $this->reason.PHP_EOL.$details;
	}
}

class Client {

	/**
	 * @var string
	 */
	public $auth;

	/**
	 * @param string $user
	 * @param array $password
	 */
	public function __construct(string $user, string $password) {
		list($result, $error) = $this->call('user.login', compact('user', 'password'));

		if ($error) {
			throw new Exception($error);
		}

 		$this->auth = $result;
	}

	/**
	 * @param string $method
	 * @param array $params
	 * 
	 * @return array
	 */
	public function call(string $method, array $params = []) {
		$error = null;
		$result = null;

		$auth = $this->auth;
		$http = [
			'method' => 'POST',
			'header' => 'Content-type: application/json',
			'content' => json_encode(compact('method', 'params', 'auth') + ['id' => 1, 'jsonrpc' => '2.0'])
		];

		$resp = file_get_contents(PHPUNIT_URL.'api_jsonrpc.php', false, stream_context_create(compact('http')));

		if (!$resp) {
			$error = ClientError::no_resp();

			return [$result, $error];
		}

		$resp_decoded = json_decode($resp, true);
		if (!$resp_decoded) {
			$error = ClientError::json($resp);

			return [$result, $error];
		}

		if (array_key_exists('error', $resp_decoded)) {
			$error = ClientError::from_resp($resp_decoded);

			return [$result, $error];
		}

		$result = $resp_decoded['result'];

		return [$result, $error];
	}
}

class CommitPromise {

	/**
	 * If promise is resolved, here is client resopnse.
	 *
	 * @var array
	 */
	private $inner;

	/**
	 * @var array
	 */
	public $params;

	/**
	 * @var string
	 */
	public $method;

	/**
	 * @var array
	 */
	public $client_resp;

	public function __construct(string $method, array $params) {
		$this->method = $method;
		$this->params = $params;
		$this->inner = [];
		$this->call_stack = [];
	}

	/**
	 * WIP!!
	 */
	public function delete(Client &$client) {
		$unique = $this->unique();

		if (!$unique) {
			return null;
		}

		switch ($this->method) {
			case 'template.create':
				list($result, $error) = $client->call('template.get', [
					'output' => ['templateid'],
					'filter' => ['host' => [$unique]]
				]);
				$primary = reset($result);
				$primary = $primary['templateid'];
				list($result, $error) = $client->call('template.delete', [$primary]);
				break;
			case 'hostgroup.create':
				list($result, $error) = $client->call('hostgroup.get', [
					'output' => ['groupid'],
					'filter' => ['name' => [$unique]]
				]);
				$primary = reset($result);
				$primary = $primary['groupid'];
				list($result, $error) = $client->call('hostgroup.delete', [$primary]);
				break;
		}

		foreach ($this->call_stack as $commit) {
			$commit->delete($client);
		}
	}

	/**
	 * Designed to work both for reimport and just creation scenarios.
	 * Thus always using "unique" field. No cashing - always values are API fetched.
	 */
	public function expand(Client &$client) {
		$expanded = [];
		$expanded['sdk'] = $this->method;
		$expanded['result'] = [];
		$expanded['call_stack'] = [];

		$unique = $this->unique();

		if (!$unique) {
			return $expanded;
		}

		switch ($this->method) {
			case 'template.create':
				list($result, $error) = $client->call('template.get', [
					'output' => API_OUTPUT_EXTEND,
					'filter' => ['host' => [$unique]],
					'selectGroups' => API_OUTPUT_EXTEND,
					'selectTags' => API_OUTPUT_EXTEND,
					'selectHosts' => API_OUTPUT_EXTEND,
					'selectTemplates' => API_OUTPUT_EXTEND,
					'selectParentTemplates' => API_OUTPUT_EXTEND,
					'selectHttpTests' => API_OUTPUT_EXTEND,
					'selectItems' => API_OUTPUT_EXTEND,
					'selectDiscoveries' => API_OUTPUT_EXTEND,
					'selectTriggers' => API_OUTPUT_EXTEND,
					'selectGraphs' => API_OUTPUT_EXTEND,
					'selectApplications' => API_OUTPUT_EXTEND,
					'selectMacros' => API_OUTPUT_EXTEND,
					'selectScreens' => API_OUTPUT_EXTEND
				]);
				$expanded['result'] = $result;
				break;
			case 'hostgroup.create':
				list($result, $error) = $client->call('hostgroup.get', [
					'output' => API_OUTPUT_EXTEND,
					'filter' => ['name' => [$unique]],
					'selectDiscoveryRule' => API_OUTPUT_EXTEND,
					'selectTemplates' => API_OUTPUT_EXTEND,
					'selectHosts' => API_OUTPUT_EXTEND,
					'selectGroupDiscovery' => API_OUTPUT_EXTEND
				]);
				$expanded['result'] = $result;
				break;
		}

		$expanded['call_stack'] = [];

		foreach ($this->call_stack as $commit) {
			$expanded['call_stack'][] = $commit->expand($client);
		}

		return $expanded;
	}

	/**
	 * Most common identifier based on subrequest method
	 */
	public function undo(Client &$client) {
		$primary = $this->primary();

		if (!$primary) {
			return null;
		}

		list($result, $error) = $this->client_resp;
		if ($error) {
			return null;
		}

		switch ($this->method) {
			case 'template.create':
				$r = $client->call('template.delete', [$primary]);
				// TODO: unset primary
				break;
			case 'hostgroup.create':
				$r = $client->call('hostgroup.delete', [$primary]);
				break;
		}

		foreach ($this->call_stack as $commit) {
			$commit->undo($client);
		}
	}

	/**
	 * Most common identifier based on subrequest method
	 */
	public function unique() {
		switch ($this->method) {
			case 'template.create':
				return $this->params['host'];
			case 'hostgroup.create':
				return $this->params['name'];
			default:
				return null;
		}
	}

	/**
	 * Most common identifier based on subrequest method
	 */
	public function primary() {
		if (!$this->client_resp) {
			return null;
		}

		list($result, $error) = $this->client_resp;
		if ($error) {
			return null;
		}

		switch ($this->method) {
			case 'template.create':
				return reset($result['templateids']);
			case 'hostgroup.create':
				return reset($result['groupids']);
			default:
				return null;
		}
	}

	public function resolve($value, Client &$client, &$error) {
		$resolved_params = [];

		if ($value instanceof CommitPromise) {
			list($result, $error) = $value($client);

			if ($result) {
				$this->call_stack[] =& $value;
			}

			$resolved_params = $value->primary();
		}
		else if (is_array($value)) {
			foreach ($value as $key => $vvalue) {
				$resolved_params[$key] = $this->resolve($vvalue, $client, $error);
			}
		}
		else {
			$resolved_params = $value;
		}

		return $resolved_params;
	}

	public function __invoke(Client &$client) {
		if ($this->client_resp) {
			return $this->client_resp;
		}

		$this->resolved_params = [];

		foreach ($this->params as $key => $value) {
			$this->resolved_params[$key] = $this->resolve($value, $client, $error);

			if ($error) {
				$this->undo($client);
				return [null, $error];
			}
		}

		$this->client_resp = $client->call($this->method, $this->resolved_params);

		return $this->client_resp;
	}
}


class SDK {

	/**
	 * @var array
	 */
	public $inner;

	/**
	 * @var array
	 */
	public $rollback = [];

	/**
	 * @return CommitPromise
	 */
	public function hostgroupCreate(array $params) {
		$this->rollback[] = 'hostgroup.create';
		return new CommitPromise('hostgroup.create', $params);
	}

	/**
	 * @return CommitPromise
	 */
	public function templateCreate(array $params) {
		$request = new CommitPromise('template.create', $params);

		$this->rollback[] = new CommitPromise('template.delete', ['hostid' => $request]);

		return $request;
	}

	/**
	 * @return CommitPromise
	 */
	public function configurationImport(array $params) {
		$request = new CommitPromise('configuration.import', $params);

		return $request;
	}

	/**
	 * @return CommitPromise
	 */
	public function configurationExport(array $params) {
		$request = new CommitPromise('configuration.export', $params);

		return $request;
	}

	public function undo(Client &$client) {
		foreach($this->rollback as $commit) {
			$commit($client);
		}
	}
}

class testExportIntegration extends PHPUnit_Framework_TestCase {

	/**
	 * @var $sdk SDK
	 */
	static $sdk;

	/**
	 * @var $client Client
	 */
	static $client;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		static::$client = new Client('Admin', 'zabbix');
	}

	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
		static::$client->call('user.logout');
	}

	public function getExpandedSignature(array $data) {
		$identifier_keys = ['groupid', 'templateid', 'itemid'];
		array_walk_recursive($data, function(&$value, $key) use (&$identifier_keys) {
			if (in_array($key, $identifier_keys)) {
				$value = '{MASKED}';
			}
		});

		return $this->getExportSignature($data);
	}

	public function getExportSignature(array $export) {
		unset($export['zabbix_export']['date']);

		return md5(json_encode($export));
	}

	/**
	 * This testCase serves as Tutorial introducing capabilities of "API Integration Tests Framework".
	 * Each and every one comment line here is of most importance, please read it, to get up to speed.
	 * Please provide feedback on how this framework behaves in real life examples, what problems it does not solve yet.
	 * There is space for abstractions to be created over things like export serialization and more. But real-life experience is needed to feel for the right abstractions to be implemented.
	 *
	 * This one serves as tutorial and describes multiple test-cases - actual tests should not take more than 20 lines (including data creation).
	 */
	public function testTemplates() {
		$sdk = new SDK();

		/*
		 * A request to create hostgroup.
		 */
		$hg3 = $sdk->hostgroupCreate([
			'name' => 'hg3'
		]);

		/*
		 * A request is performed like so.
		 */
		list($result, $error) = $hg3(self::$client);

		/*
		 * This creates promise to execute these api calls in such order:
		 * hg1 -> hg2 -> hg3 (will not execute again) -> h1, meanwhile yielding the "primary" value up the chain.
		 */
		$template = $sdk->templateCreate([
			'host' => 'h1',
			'groups' => [
				/*
				 * A request object translates into it's corresponding identifier.
				 */
				['groupid' => $sdk->hostgroupCreate([
					'name' => 'hg1'
				])],
				['groupid' => $sdk->hostgroupCreate([
					'name' => 'hg2'
				])],
				/*
				 * Use reference. This request may have been performed before hand or not.
				 */
				['groupid' => &$hg3]
			]
		]);
		
		list($result, $error) = $template(self::$client);
		if ($error) {
			/*
			 * The first error in chain of calls is returned.
			 * Any further API calls are ditched.
			 * Any succesful API calls are reverted.
			 *
			 * If error happens now - the test just is wrong (in this moment we do not test for error, fail early, please do not assert the $error is null)
			 * TODO: implement Throwable or extend on Exception so we could just do a ` throw $error `
			 */
			throw new Exception($error);
		}

		/*
		 * ==
		 * Tutorial end.
		 */

		$export = $sdk->configurationExport([
			'format' => 'json',
			'options' => [
				/*
				 * You can imagine putting whole $template declaration (or multiple ones) inline into this call,
				 * if we only need export. This time we must undo $template explicitly, for that reason object is stored in variable.
				 *
				 * $template->primary() is called under the hood here.
				 */
				'templates' => [&$template]
			]
		]);

		/*
		 * For easy editing and inspection, we use json format.
		 */
		list($result, $error) = $export(self::$client);
		$export_file = json_decode($result, true);

		/*
		 * ==
		 * End of data provider section.
		 */

		/*
		 * ##
		 * Current test case spcific assertions:
		 * 1. assert export format for minimal template.
		 * 2. assert reimport produces the objects as expected.
		 * 3. assert modified reimport without mandatory fields produces explicit error.
		 * 4. assert modified reimport with prefilled optional fields produces the objects as expected.
		 *
		 * In reality assertions for each reimports should be placed is separated tests for easier debug and problem isolation.
		 * A test should test for one thing.
		 */

		/*
		 * The test developer, now manually inspects if export matches expectations (according with "creation" requests).
		 *
		 * If that is the case, this result then could be fixed into assertion.
		 * ` var_dump($result); `
		 * Obtain snapshot (copy it).
		 * ` var_dump($this->getExportSignature($export_file)) `
		 * Only when export is as expected, signature into assertion
		 */
		$this->assertEquals('1e7bc304d91ac215ed9870bb823e38ae', $this->getExportSignature($export_file),
			'Signature mismatch at initial export.');

		/*
		 * For export testing we revert any subsequent calls from $template. Note: this could be done also from $export object.
		 *
		 * FYI: A creation happened "bottom up" - teardown happens "top bottom", to avoid dependency issues.
		 */
		$template->undo(self::$client);

		/*
		 * Now we perform import from the exoprt file.
		 */
		$import = $sdk->configurationImport([
			'format' => 'json',
			'source' => json_encode($export_file),
			'rules' => [
				'groups' => [
					'createMissing' => true
				],
				'templates' => [
					'createMissing' => true
				]
			]
		]);

		/*
		 * FYI: it is possible even to assert how import rules behave by:
		 * Instead of ` $template->undo(self::$client); ` we could have done ` $hg3->undo(self::$client); ` and assert, that $hg3 is createdMissing or updatedExisting.
		 *
		 * Now we call import and do assert the error is null, because that is expected.
		 */
		list($result, $error) = $import(self::$client);
		$this->assertTrue($result, 'This reimport had to successful.');
		$this->assertNull($error, 'Reimport should not return error.');

		/*
		 * Then we may want to assert that APP state is as expected. Assert using queries, or by inspecting, "expanded" object queries.
		 * In similar fassion it is possible to assert using snapshot approach, or rather assert each field explicitly to be more declarative about edge cases, that are being tested here.
		 * Maybe it is more appropriate to assert on whole snapshot.
		 *
		 * Expaded selection is obtained like so: ` $template->expand() `
		 * Under the hood API will be queried for each object using its expanded state (may involve get parameters like "selectGroups", etc.).
 		 * ` $data = $template->expand() ` $data will be formatted in the same structure as request object was build.
		 *
		 * Comparing signature in this level may be breaking very often (if any API method gets somewhat updated over time). Try to do explicit assertions where possible.
		 */
		$this->assertEquals('9c12005489e55d77fe71a6cb9ca00c60',
			$this->getExpandedSignature($template->expand(self::$client)),
			'Initial reimport did not created objects as expected.'
		);

		/*
		 * Is a bit more complicated to clean out what we just imported: ` $import->undo(self::$client); ` would take no effect.
		 * If the "creation/manifest" object is still around it is possible to undo this calls by using ->unique(), instead of ->primary() like so:
		 * ` $template->delete(self::$client) ` under the hood it will fetch object primary first, then only delete by it (be careful, currently unstable).
		 */
		$template->delete(self::$client);

		/*
		 * Now we perform import from a modified exoprt file. Remember to copy $export_file if needed.
		 *
		 * Assert that we should not be able to import template without groups.
		 */
		unset($export_file['zabbix_export']['groups']);
		$import = $sdk->configurationImport([
			'format' => 'json',
			'source' => json_encode($export_file),
			'rules' => [
				'groups' => [
					'createMissing' => true
				],
				'templates' => [
					'createMissing' => true
				]
			]
		]);

		/*
		 * FYI: because each API executor accepts self::$client, it is possible to keep multiple $clients (sessions) and assert how permissions should work with API.
		 * For example we could assert that 'updatedExisting' during import will not modify object that user has no access to.
		 */
		list($result, $error) = $import(self::$client);

		$template->delete(self::$client);
		
		$this->assertNotNull($error, 'Modded reimport had to return error.');
		$this->assertEquals($error->data['data'], 'Group "hg1" does not exist.', 'Expecting precise API error message.'); // TODO: this must be streamlinded for API errors ->data['data'], what is this!?
	}
}
