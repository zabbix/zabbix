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


abstract class CControllerItemPrototype extends CControllerItem {

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
	}

	protected function validateFormInput(array $required_fields): bool {
		$fields = [
			'allow_traps'			=> 'in 0,1',
			'authtype'				=> 'db items.authtype',
			'context'				=> 'required|in host,template',
			'delay'					=> 'db items.delay',
			'delay_flex'			=> 'array',
			'description'			=> 'db items.description',
			'follow_redirects'		=> 'in 0,1',
			'form_refresh'			=> 'in 1',
			'headers'				=> 'array',
			'history'				=> 'db items.history',
			'history_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'hostid'				=> 'id',
			'http_authtype'			=> 'db items.authtype',
			'http_password'			=> 'db items.password',
			'http_proxy'			=> 'string',
			'http_username'			=> 'db items.username',
			'interfaceid'			=> 'id',
			'inventory_link'		=> 'db items.inventory_link',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'itemid'				=> 'id',
			'jmx_endpoint'			=> 'db items.jmx_endpoint',
			'key'					=> 'db items.key_',
			'logtimefmt'			=> 'db items.logtimefmt',
			'master_itemid'			=> 'id',
			'name'					=> 'db items.name',
			'output_format'			=> 'in 0,1',
			'parameters'			=> 'array',
			'params_ap'				=> 'db items.params',
			'params_es'				=> 'db items.params',
			'params_f'				=> 'db items.params',
			'parent_discoveryid'	=> 'id',
			'password'				=> 'db items.password',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'db items.posts',
			'preprocessing'			=> 'array',
			'privatekey'			=> 'db items.privatekey',
			'publickey'				=> 'db items.publickey',
			'query_fields'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'script'				=> 'db items.params',
			'show_inherited_tags'	=> 'in 0,1',
			'show_inherited_tags'	=> 'in 0,1',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ssl_cert_file'			=> 'db items.ssl_cert_file',
			'ssl_key_file'			=> 'db items.ssl_key_file',
			'ssl_key_password'		=> 'db items.ssl_key_password',
			'status'				=> 'db items.status',
			'status_codes'			=> 'db items.status_codes',
			'tags'					=> 'array',
			'templateid'			=> 'id',
			'timeout'				=> 'db items.timeout',
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'trends'				=> 'db items.trends',
			'trends_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'type'					=> 'db items.type',
			'units'					=> 'db items.units',
			'url'					=> 'db items.url',
			'username'				=> 'db items.username',
			'value_type'			=> 'db items.value_type',
			'valuemapid'			=> 'id',
			'verify_host'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'verify_peer'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])
		];

		foreach ($required_fields as $field) {
			$fields[$field] = 'required|'.$fields[$field];
		}

		$field = '';
		$ret = $this->validateInput($fields);

		return $ret;
	}
}
