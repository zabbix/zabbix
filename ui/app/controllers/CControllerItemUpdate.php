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


class CControllerItemUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid'			=> 'required|id',
			'itemid'			=> 'required|id',
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
			&& API::Item()->get([
				'itemids' => [$this->getInput('itemid')]
			]);
	}

	public function doAction() {
		$output = [];
		$result = API::Item()->update($this->getFormData());
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Item updated');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update item'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function getFormData(): array {
		$input = $this->getInputAll();
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
		$item = ['itemid' => $this->getInput('itemid')];
		[$db_item] = API::Item()->get([
			'output' => ['templateid', 'flags', 'type', 'key_', 'value_type', 'authtype', 'allow_traps'],
			'selectHosts' => ['status'],
			'itemids' => [$item['itemid']]
		]);
		$item += getSanitizedItemFields($input + $db_item);

		return $item;
	}
}
