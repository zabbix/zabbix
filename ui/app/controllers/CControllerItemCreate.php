<?php declare(strict_types=0);
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


class CControllerItemCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid'			=> 'required|id',
			'context'			=> 'required|in host,template',
			'name'				=> 'db items.name',
			'key'				=> 'db items.key_',
			'type'				=> 'db items.type',
			'value_type'		=> 'db items.value_type',
			'units'				=> 'db items.units',
			'history_mode'		=> 'int32',
			'history'			=> 'db items.history',
			'trends_mode'		=> 'int32',
			'trends'			=> 'db items.trends',
			'valuemapid'		=> 'id',
			'inventory_link'	=> 'db items.inventory_link',
			'logtimefmt'		=> 'db items.logtimefmt',
			'description'		=> 'db items.description',
			'status'			=> 'db items.status',
			'interfaceid'		=> 'id',
			'authtype'			=> 'db items.authtype',
			'username'			=> 'db items.username',
			'password'			=> 'db items.password',
			'params'			=> 'db items.params',
			'timeout'			=> 'db items.timeout',
			'delay'				=> 'db items.delay',
			'trapper_hosts'		=> 'db items.trapper_hosts',
			'master_itemid'		=> 'id',
			'url'				=> 'db items.url',
			'request_method'	=> 'db items.request_method',
			'post_type'			=> 'db items.post_type',
			'posts'				=> 'db items.posts',
			'status_codes'		=> 'db items.status_codes',
			'follow_redirects'	=> 'db items.follow_redirects',
			'retrieve_mode'		=> 'db items.retrieve_mode',
			'output_format'		=> 'db items.output_format',
			'http_proxy'		=> 'db items.http_proxy',
			'verify_peer'		=> 'db items.verify_peer',
			'verify_host'		=> 'db items.verify_host',
			'ssl_cert_file'		=> 'db items.ssl_cert_file',
			'ssl_key_file'		=> 'db items.ssl_key_file',
			'ssl_key_password'	=> 'db items.ssl_key_password',
			'allow_traps'		=> 'db items.allow_traps',
			'ipmi_sensor'		=> 'db items.ipmi_sensor',
			'jmx_endpoint'		=> 'db items.jmx_endpoint',
			'snmp_oid'			=> 'db items.snmp_oid',
			'publickey'			=> 'db items.publickey',
			'privatekey'		=> 'db items.privatekey',
			'headers'			=> 'array',
			'parameters'		=> 'array',
			'preprocessing'		=> 'array',
			'tags'				=> 'array',
			'query_fields'		=> 'array'
		];

		$ret = $this->validateInput($fields);

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
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN
			&& API::Host()->get([
				'itemids' => $this->getInput('hostid'),
				'editable' => true
			]);
	}

	public function doAction() {
		$output = [];
		$item = $this->getFormData();
		$result = API::Item()->create($item);
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Item created');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create item'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function getFormData(): array {
		$input = [
			'interfaceid' => 0,
			'hostid' => 0,
			'name' => '',
			'type' => DB::getDefault('items', 'type'),
			'key' => '',
			'value_type' => DB::getDefault('items', 'value_type'),
			'url' => '',
			'script' => '',
			'request_method' => DB::getDefault('items', 'request_method'),
			'timeout' => DB::getDefault('items', 'timeout'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'output_format' => DB::getDefault('items', 'output_format'),
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'http_authtype' => ZBX_HTTP_AUTH_NONE,
			'http_username' => '',
			'http_password' => '',
			'verify_peer' => DB::getDefault('items', 'verify_peer'),
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
			'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
			'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
			'master_itemid' => 0,
			'snmp_oid' => DB::getDefault('items', 'snmp_oid'),
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),
			'authtype' => DB::getDefault('items', 'authtype'),
			'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),
			'username' => DB::getDefault('items', 'username'),
			'password' => DB::getDefault('items', 'password'),
			'publickey' => DB::getDefault('items', 'publickey'),
			'privatekey' => DB::getDefault('items', 'privatekey'),
			'params' => DB::getDefault('items', 'params'),
			'units' => DB::getDefault('items', 'units'),
			'delay' => ZBX_ITEM_DELAY_DEFAULT,
			'history' => DB::getDefault('items', 'history'),
			'trends' => DB::getDefault('items', 'trends'),
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'valuemapid' => 0,
			'allow_traps' => DB::getDefault('items', 'allow_traps'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),
			'inventory_link' => 0,
			'description' => DB::getDefault('items', 'description'),
			'status' => DB::getDefault('items', 'status'),
			'tags' => [],
			'preprocessing' => [],
			'headers' => [],
			'delay_flex' => [],
			'query_fields' => [],
			'parameters' => [],
		];
		$this->getInputs($input, array_keys($input));
		$field_map = [];

		if ($this->hasInput('key')) {
			$field_map['key'] = 'key_';
		}

		if ($this->getInput('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['history'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['trends'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('type') == ITEM_TYPE_HTTPAGENT) {
			$field_map['http_authtype'] = 'authtype';
			$field_map['http_username'] = 'username';
			$field_map['http_password'] = 'password';
		}

		if ($this->hasInput('tags')) {
			$tags = [];

			foreach ($input['tags'] as $tag) {
				if ($tag['tag'] !== '' || $tag['value'] !== '') {
					$tags[] = $tag;
				}
			}

			$input['tags'] = $tags;
		}

		$input = CArrayHelper::renameKeys($input, $field_map);
		$hosts = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => $this->getInput('hostid'),
			'templated_hosts' => true,
			'editable' => true
		]);
		$item = ['hostid' => $input['hostid']];
		$item += getSanitizedItemFields($input + [
			'templateid' => '0',
			'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
			'hosts' => $hosts
		]);

		return $item;
	}
}
