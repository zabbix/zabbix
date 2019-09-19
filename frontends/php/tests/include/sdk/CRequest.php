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
 * Resource request builder object. Instance can resolve dependent resource API calls.
 * Capable to undo all requests.
 */
class CRequest {

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
		$this->call_stack = [];
	}

	/**
	 * WIP!!
	 */
	public function delete(CClient &$client) {
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
	public function expand(CClient &$client) {
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
	public function undo(CClient &$client) {
		$primary = $this->primary();

		if (!$primary) {
			foreach ($this->call_stack as $commit) {
				$commit->undo($client);
			}
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

	public function resolve($value, CClient &$client, &$error) {
		$resolved_params = [];

		if ($value instanceof CRequest) {
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

	public function __invoke(CClient &$client) {
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

