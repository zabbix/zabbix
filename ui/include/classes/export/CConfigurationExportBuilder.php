<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CConfigurationExportBuilder {

	/**
	 * @var array
	 */
	protected $data = [];

	/**
	 * @param $version  current export version
	 */
	public function __construct() {
		$this->data['version'] = ZABBIX_EXPORT_VERSION;
	}

	/**
	 * Get array with formatted export data.
	 *
	 * @return array
	 */
	public function getExport() {
		return ['zabbix_export' => $this->data];
	}

	/**
	 * Build row structure.
	 *
	 * @param array  $rule      Validation rule for selected tag in 3rd parameter.
	 * @param array  $row       Export row.
	 * @param string $tag       Tag name.
	 * @param string $main_tag  Main element (for error reporting).
	 *
	 * @throws Exception  if row is invalid.
	 *
	 * @return mixed
	 */
	private static function buildArrayRow(array $rule, array $row, string $tag, string $main_tag) {
		if (array_key_exists('ex_rules', $rule)) {
			$parent_rule = array_intersect_key($rule, array_flip(['ex_default', 'default', 'rule']));
			$rule = call_user_func($rule['ex_rules'], $row);
			$rule = $parent_rule + $rule;
		}

		$is_required = (bool) ($rule['type'] & XML_REQUIRED);
		$is_string = (bool) ($rule['type'] & XML_STRING);
		$is_array = (bool) ($rule['type'] & XML_ARRAY);
		$is_indexed_array = (bool) ($rule['type'] & XML_INDEXED_ARRAY);
		$has_data = array_key_exists($tag, $row);

		if (array_key_exists('ex_default', $rule)) {
			$default_value = (string) call_user_func($rule['ex_default'], $row);
		}
		elseif (array_key_exists('default', $rule)) {
			$default_value = (string) $rule['default'];
		}
		else {
			$default_value = null;
		}

		if (!$default_value && !$has_data) {
			if ($is_required) {
				throw new Exception(_s('Invalid tag "%1$s": %2$s.', $main_tag, _s('the tag "%1$s" is missing', $tag)));
			}
			return null;
		}

		$value = $has_data ? $row[$tag] : $default_value;

		if (!$is_required && $default_value == $value) {
			return null;
		}

		if (($is_indexed_array || $is_array) && $has_data) {
			$temp_store = self::build($rule, $is_array ? [$value] : $value, $tag);

			return ($is_required || $temp_store) ? $temp_store : null;
		}

		if ($is_string && $value !== null) {
			$value = str_replace("\r\n", "\n", $value);
		}

		if (array_key_exists('in', $rule)) {
			if (!array_key_exists($value, $rule['in'])) {
				throw new Exception(_s('Invalid tag "%1$s": %2$s.', $tag,
					_s('unexpected constant value "%1$s"', $value)
				));
			}

			return $rule['in'][$value];
		}

		return $value;
	}

	/**
	 * Build data structure.
	 *
	 * @param array  $schema    Tag schema from validation class.
	 * @param array  $data      Export data.
	 * @param string $main_tag  Main element (for error reporting).
	 *
	 * @return array
	 */
	private static function build(array $schema, array $data, string $main_tag) {
		$n = 0;
		$result = [];

		if ($schema['type'] & XML_INDEXED_ARRAY) {
			$type = $schema['rules'][$schema['prefix']]['type'] & (XML_ARRAY | XML_STRING);
			$rules = $type == XML_ARRAY
				? $schema['rules'][$schema['prefix']]['rules']
				: $schema['rules'][$schema['prefix']];
		}
		else {
			$type = XML_ARRAY;
			$rules = $schema['rules'];
		}

		foreach ($data as $row) {
			$store = [];

			if ($type == XML_ARRAY) {
				foreach ($rules as $tag => $tag_rules) {
					while ($tag_rules['type'] & XML_MULTIPLE) {
						$matched_multiple_rule = null;

						foreach ($tag_rules['rules'] as $multiple_rule) {
							if (self::multipleRuleMatched($multiple_rule, $row)) {
								$multiple_rule['type'] = ($tag_rules['type'] & XML_REQUIRED) | $multiple_rule['type'];
								$matched_multiple_rule =
									$multiple_rule + array_intersect_key($tag_rules, array_flip(['default']));
								break;
							}
						}

						if ($matched_multiple_rule === null) {
							// For use by developers. Do not translate.
							throw new Exception('Incorrect XML_MULTIPLE validation rules.');
						}

						$tag_rules = $matched_multiple_rule;
					}

					if ($tag_rules['type'] & XML_IGNORE_TAG) {
						continue;
					}

					$value = self::buildArrayRow($tag_rules, $row, $tag, $main_tag);

					if ($value !== null) {
						$store[$tag] = $value;
					}
				}
			}
			else {
				$store = str_replace("\r\n", "\n", $row);
			}

			if ($schema['type'] & XML_INDEXED_ARRAY) {
				$result[$n++] = $store;
			}
			else {
				$result = $store;
			}
		}

		return $result;
	}

	private static function multipleRuleMatched(array $multiple_rule, array $data): bool {
		if (array_key_exists('else', $multiple_rule)) {
			return true;
		}
		elseif (is_array($multiple_rule['if'])) {
			$field_name = $multiple_rule['if']['tag'];

			return array_key_exists($data[$field_name], $multiple_rule['if']['in']);
		}
		elseif ($multiple_rule['if'] instanceof Closure) {
			return call_user_func($multiple_rule['if'], $data);
		}

		return false;
	}

	/**
	 * Format template groups.
	 *
	 * @param array $schema  Tag schema from validation class.
	 * @param array $groups  Export data.
	 */
	public function buildTemplateGroups(array $schema, array $groups) {
		$groups = $this->formatGroups($groups);

		$this->data['template_groups'] = self::build($schema, $groups, 'template_groups');
	}

	/**
	 * Format host groups.
	 *
	 * @param array $schema  Tag schema from validation class.
	 * @param array $groups  Export data.
	 */
	public function buildHostGroups(array $schema, array $groups) {
		$groups = $this->formatGroups($groups);

		$this->data['host_groups'] = self::build($schema, $groups, 'host_groups');
	}

	/**
	 * Format templates.
	 *
	 * @param array $schema           Tag schema from validation class.
	 * @param array $templates        Export data.
	 * @param array $simple_triggers  Simple triggers.
	 */
	public function buildTemplates(array $schema, array $templates, array $simple_triggers) {
		$templates = $this->formatTemplates($templates, $simple_triggers);

		$this->data['templates'] = self::build($schema, $templates, 'templates');
	}

	/**
	 * Format hosts.
	 *
	 * @param array $schema           Tag schema from validation class.
	 * @param array $hosts            Export data.
	 * @param array $simple_triggers  Simple triggers.
	 */
	public function buildHosts(array $schema, array $hosts, array $simple_triggers) {
		$hosts = $this->formatHosts($hosts, $simple_triggers);

		$this->data['hosts'] = self::build($schema, $hosts, 'hosts');
	}

	/**
	 * Format triggers.
	 *
	 * @param array $schema    Tag schema from validation class.
	 * @param array $triggers  Export data.
	 */
	public function buildTriggers(array $schema, array $triggers) {
		$triggers = $this->formatTriggers($triggers);

		$this->data['triggers'] = self::build($schema, $triggers, 'triggers');
	}

	/**
	 * Format graphs.
	 *
	 * @param array $schema  Tag schema from validation class.
	 * @param array $graphs  Export data.
	 */
	public function buildGraphs(array $schema, array $graphs) {
		$graphs = $this->formatGraphs($graphs);

		$this->data['graphs'] = self::build($schema, $graphs, 'graphs');
	}

	/**
	 * Format media types.
	 *
	 * @param array $schema       Tag schema from validation class.
	 * @param array $media_types  Export data.
	 */
	public function buildMediaTypes(array $schema, array $media_types) {
		$media_types = $this->formatMediaTypes($media_types);

		$this->data['media_types'] = self::build($schema, $media_types, 'media_types');
	}

	/**
	 * Separate simple triggers.
	 *
	 * @param array $triggers
	 * @param array $unlink_itemids
	 *
	 * @return array
	 */
	public function extractSimpleTriggers(array &$triggers, array $unlink_itemids) {
		$simple_triggers = [];

		foreach ($triggers as $triggerid => $trigger) {
			if (count($trigger['items']) == 1 && $trigger['items'][0]['type'] != ITEM_TYPE_HTTPTEST
					&& ($trigger['items'][0]['templateid'] == 0
						|| in_array($trigger['items'][0]['templateid'], $unlink_itemids))) {
				$simple_triggers[] = $trigger;
				unset($triggers[$triggerid]);
			}
		}

		return $simple_triggers;
	}

	/**
	 * Format templates.
	 *
	 * @param array $templates
	 * @param array $simple_triggers
	 */
	protected function formatTemplates(array $templates, array $simple_triggers = null) {
		$result = [];

		CArrayHelper::sort($templates, ['host']);

		foreach ($templates as $template) {
			$vendor = [];

			if ($template['vendor_name'] !== '' && $template['vendor_version'] !== '') {
				$vendor = [
					'name' => $template['vendor_name'],
					'version' => $template['vendor_version']
				];
			}

			$result[] = [
				'uuid' => $template['uuid'],
				'template' => $template['host'],
				'name' => $template['name'],
				'description' => $template['description'],
				'vendor' => $vendor,
				'groups' => $this->formatGroups($template['templategroups']),
				'items' => $this->formatItems($template['items'], $simple_triggers),
				'discovery_rules' => $this->formatDiscoveryRules($template['discoveryRules']),
				'httptests' => $this->formatHttpTests($template['httptests']),
				'macros' => $this->formatMacros($template['macros']),
				'templates' => $this->formatTemplateLinkage($template['parentTemplates']),
				'dashboards' => $this->formatDashboards($template['dashboards']),
				'tags' => $this->formatTags($template['tags']),
				'valuemaps' => $this->formatValueMaps($template['valuemaps'])
			];
		}

		return $result;
	}

	/**
	 * Format hosts.
	 *
	 * @param array $hosts
	 * @param array $simple_triggers
	 */
	protected function formatHosts(array $hosts, array $simple_triggers = null) {
		$result = [];

		CArrayHelper::sort($hosts, ['host']);

		foreach ($hosts as $host) {
			$host = $this->createInterfaceReferences($host);

			$result[] = [
				'host' => $host['host'],
				'name' => $host['name'],
				'description' => $host['description'],
				'monitored_by' => $host['monitored_by'],
				'proxy' => $host['proxy'],
				'proxy_group' => $host['proxy_group'],
				'status' => $host['status'],
				'ipmi_authtype' => $host['ipmi_authtype'],
				'ipmi_privilege' => $host['ipmi_privilege'],
				'ipmi_username' => $host['ipmi_username'],
				'ipmi_password' => $host['ipmi_password'],
				'templates' => $this->formatTemplateLinkage($host['parentTemplates']),
				'groups' => $this->formatGroups($host['hostgroups']),
				'interfaces' => $this->formatHostInterfaces($host['interfaces']),
				'items' => $this->formatItems($host['items'], $simple_triggers),
				'discovery_rules' => $this->formatDiscoveryRules($host['discoveryRules']),
				'httptests' => $this->formatHttpTests($host['httptests']),
				'macros' => $this->formatMacros($host['macros']),
				'inventory_mode' => $host['inventory_mode'],
				'inventory' => $this->formatHostInventory($host['inventory']),
				'tags' => $this->formatTags($host['tags']),
				'valuemaps' => $this->formatValueMaps($host['valuemaps'])
			];
		}

		return $result;
	}

	/**
	 * Format images.
	 *
	 * @param array $images
	 */
	public function buildImages(array $images) {
		$this->data['images'] = [];

		foreach ($images as $image) {
			$this->data['images'][] = [
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['encodedImage']
			];
		}
	}

	/**
	 * Format maps.
	 *
	 * @param array $schema  Tag schema from validation class.
	 * @param array $maps    Export data.
	 */
	public function buildMaps(array $schema, array $maps) {
		$this->data['maps'] = [];

		CArrayHelper::sort($maps, ['name']);

		foreach ($maps as $map) {
			$tmpSelements = $this->formatMapElements($map['selements']);
			$this->data['maps'][] = [
				'name' => $map['name'],
				'width' => $map['width'],
				'height' => $map['height'],
				'label_type' => $map['label_type'],
				'label_location' => $map['label_location'],
				'highlight' => $map['highlight'],
				'expandproblem' => $map['expandproblem'],
				'markelements' => $map['markelements'],
				'show_unack' => $map['show_unack'],
				'severity_min' => $map['severity_min'],
				'show_suppressed' => $map['show_suppressed'],
				'grid_size' => $map['grid_size'],
				'grid_show' => $map['grid_show'],
				'grid_align' => $map['grid_align'],
				'label_format' => $map['label_format'],
				'label_type_host' => $map['label_type_host'],
				'label_type_hostgroup' => $map['label_type_hostgroup'],
				'label_type_trigger' => $map['label_type_trigger'],
				'label_type_map' => $map['label_type_map'],
				'label_type_image' => $map['label_type_image'],
				'label_string_host' => $map['label_string_host'],
				'label_string_hostgroup' => $map['label_string_hostgroup'],
				'label_string_trigger' => $map['label_string_trigger'],
				'label_string_map' => $map['label_string_map'],
				'label_string_image' => $map['label_string_image'],
				'expand_macros' => $map['expand_macros'],
				'background' => $map['backgroundid'],
				'iconmap' => $map['iconmap'],
				'urls' => $this->formatMapUrls($map['urls']),
				'selements' => $tmpSelements,
				'shapes' => $map['shapes'],
				'lines' => $map['lines'],
				'links' => $this->formatMapLinks($map['links'], $tmpSelements)
			];
		}

		$this->data['maps'] = self::build($schema, $this->data['maps'], 'maps');
	}

	/**
	 * Format media types.
	 *
	 * @param array $media_types
	 */
	protected function formatMediaTypes(array $media_types) {
		$result = [];

		CArrayHelper::sort($media_types, ['name']);

		foreach ($media_types as $i => $media_type) {
			$result[$i] = [
				'name' => $media_type['name'],
				'type' => $media_type['type'],
				'smtp_server' => $media_type['smtp_server'],
				'smtp_port' => $media_type['smtp_port'],
				'smtp_helo' => $media_type['smtp_helo'],
				'smtp_email' => $media_type['smtp_email'],
				'smtp_security' => $media_type['smtp_security'],
				'smtp_verify_host' => $media_type['smtp_verify_host'],
				'smtp_verify_peer' => $media_type['smtp_verify_peer'],
				'smtp_authentication' => $media_type['smtp_authentication'],
				'username' => $media_type['username'],
				'password' => $media_type['passwd'],
				'message_format' => $media_type['message_format'],
				'script_name' => $media_type['exec_path'],
				'parameters' => self::formatMediaTypeParameters($media_type),
				'gsm_modem' => $media_type['gsm_modem'],
				'status' => $media_type['status'],
				'max_sessions' => $media_type['maxsessions'],
				'attempts' => $media_type['maxattempts'],
				'attempt_interval' => $media_type['attempt_interval'],
				'script' => $media_type['script'],
				'timeout' => $media_type['timeout'],
				'process_tags' => $media_type['process_tags'],
				'show_event_menu' => $media_type['show_event_menu'],
				'event_menu_url' => $media_type['event_menu_url'],
				'event_menu_name' => $media_type['event_menu_name'],
				'description' => $media_type['description'],
				'message_templates' => self::formatMediaTypeMessageTemplates($media_type['message_templates'])
			];

			if ($media_type['type'] == MEDIA_TYPE_EMAIL) {
				$result[$i] += ['provider' => $media_type['provider']];
			}
		}

		return $result;
	}

	/**
	 * Format media type parameters.
	 *
	 * @param array $media_type
	 *
	 * @return array|string
	 */
	private static function formatMediaTypeParameters(array $media_type) {
		switch ($media_type['type']) {
			case MEDIA_TYPE_EXEC:
				CArrayHelper::sort($media_type['parameters'], ['sortorder']);

				return array_values($media_type['parameters']);

			case MEDIA_TYPE_WEBHOOK:
				CArrayHelper::sort($media_type['parameters'], ['name']);

				return array_values($media_type['parameters']);
		}

		return [];
	}

	/**
	 * Format media type message templates.
	 *
	 * @param array $message_templates
	 *
	 * @return array
	 */
	private static function formatMediaTypeMessageTemplates(array $message_templates): array {
		$result = [];

		CArrayHelper::sort($message_templates, ['eventsource', 'recovery']);

		foreach ($message_templates as $message_template) {
			$result[] = [
				'event_source' => $message_template['eventsource'],
				'operation_mode' => $message_template['recovery'],
				'subject' => $message_template['subject'],
				'message' => $message_template['message']
			];
		}

		return $result;
	}

	/**
	 * Format value maps.
	 *
	 * @param array $valuemaps
	 */
	protected function formatValueMaps(array $valuemaps) {
		CArrayHelper::sort($valuemaps, ['name']);

		foreach ($valuemaps as &$valuemap) {
			foreach ($valuemap['mappings'] as &$mapping) {
				if ($mapping['type'] == VALUEMAP_MAPPING_TYPE_EQUAL) {
					unset($mapping['type']);
				}
				elseif ($mapping['type'] == VALUEMAP_MAPPING_TYPE_DEFAULT) {
					unset($mapping['value']);
				}
			}
			unset($mapping);
		}
		unset($valuemap);

		return $valuemaps;
	}

	/**
	 * For each host interface an unique reference must be created and then added for all items, discovery rules
	 * and item prototypes that use the interface.
	 *
	 * @param array $host
	 *
	 * @return array
	 */
	protected function createInterfaceReferences(array $host) {
		$references = [
			'num' => 1,
			'refs' => []
		];

		// create interface references
		foreach ($host['interfaces'] as &$interface) {
			$refNum = $references['num']++;
			$referenceKey = 'if'.$refNum;
			$interface['interface_ref'] = $referenceKey;
			$references['refs'][$interface['interfaceid']] = $referenceKey;
		}
		unset($interface);

		foreach ($host['items'] as &$item) {
			if ($item['interfaceid']) {
				$item['interface_ref'] = $references['refs'][$item['interfaceid']];
			}
		}
		unset($item);

		foreach ($host['discoveryRules'] as &$discoveryRule) {
			if ($discoveryRule['interfaceid']) {
				$discoveryRule['interface_ref'] = $references['refs'][$discoveryRule['interfaceid']];
			}

			foreach ($discoveryRule['itemPrototypes'] as &$prototype) {
				if ($prototype['interfaceid']) {
					$prototype['interface_ref'] = $references['refs'][$prototype['interfaceid']];
				}
			}
			unset($prototype);
		}
		unset($discoveryRule);

		return $host;
	}

	/**
	 * Format discovery rules.
	 *
	 * @param array $discoveryRules
	 *
	 * @return array
	 */
	protected function formatDiscoveryRules(array $discoveryRules) {
		$result = [];

		CArrayHelper::sort($discoveryRules, ['key_']);

		$simple_trigger_prototypes = [];

		foreach ($discoveryRules as $discoveryRule) {
			foreach ($discoveryRule['triggerPrototypes'] as $i => $trigger_prototype) {
				if (count($trigger_prototype['items']) == 1) {
					$simple_trigger_prototypes[] = $trigger_prototype;
					unset($discoveryRule['triggerPrototypes'][$i]);
				}
			}

			$data = [
				'uuid' => $discoveryRule['uuid'],
				'name' => $discoveryRule['name'],
				'type' => $discoveryRule['type'],
				'snmp_oid' => $discoveryRule['snmp_oid'],
				'key' => $discoveryRule['key_'],
				'delay' => $discoveryRule['delay'],
				'status' => $discoveryRule['status'],
				'allowed_hosts' => $discoveryRule['trapper_hosts'],
				'params' => $discoveryRule['params'],
				'ipmi_sensor' => $discoveryRule['ipmi_sensor'],
				'authtype' => $discoveryRule['authtype'],
				'username' => $discoveryRule['username'],
				'password' => $discoveryRule['password'],
				'publickey' => $discoveryRule['publickey'],
				'privatekey' => $discoveryRule['privatekey'],
				'filter' => $discoveryRule['filter'],
				'lifetime_type' => $discoveryRule['lifetime_type'],
				'lifetime' => $discoveryRule['lifetime'],
				'enabled_lifetime_type' => $discoveryRule['enabled_lifetime_type'],
				'enabled_lifetime' => $discoveryRule['enabled_lifetime'],
				'description' => $discoveryRule['description'],
				'item_prototypes' => $this->formatItems($discoveryRule['itemPrototypes'], $simple_trigger_prototypes),
				'trigger_prototypes' => $this->formatTriggers($discoveryRule['triggerPrototypes']),
				'graph_prototypes' => $this->formatGraphs($discoveryRule['graphPrototypes']),
				'host_prototypes' => $this->formatHostPrototypes($discoveryRule['hostPrototypes']),
				'jmx_endpoint' => $discoveryRule['jmx_endpoint'],
				'timeout' => $discoveryRule['timeout'],
				'url' => $discoveryRule['url'],
				'query_fields' => $discoveryRule['query_fields'],
				'parameters' => self::formatItemParameters($discoveryRule['parameters']),
				'posts' => $discoveryRule['posts'],
				'status_codes' => $discoveryRule['status_codes'],
				'follow_redirects' => $discoveryRule['follow_redirects'],
				'post_type' => $discoveryRule['post_type'],
				'http_proxy' => $discoveryRule['http_proxy'],
				'headers' => $discoveryRule['headers'],
				'retrieve_mode' => $discoveryRule['retrieve_mode'],
				'request_method' => $discoveryRule['request_method'],
				'allow_traps' => $discoveryRule['allow_traps'],
				'ssl_cert_file' => $discoveryRule['ssl_cert_file'],
				'ssl_key_file' => $discoveryRule['ssl_key_file'],
				'ssl_key_password' => $discoveryRule['ssl_key_password'],
				'verify_peer' => $discoveryRule['verify_peer'],
				'verify_host' => $discoveryRule['verify_host'],
				'lld_macro_paths' => self::formatLldMacroPaths($discoveryRule['lld_macro_paths']),
				'preprocessing' => self::formatPreprocessingSteps($discoveryRule['preprocessing']),
				'overrides' => self::formatLldOverrides($discoveryRule['overrides'])
			];

			if (!$data['filter']['conditions']) {
				unset($data['filter']);
			}

			if (isset($discoveryRule['interface_ref'])) {
				$data['interface_ref'] = $discoveryRule['interface_ref'];
			}

			$data['master_item'] = ($discoveryRule['type'] == ITEM_TYPE_DEPENDENT)
				? ['key' => $discoveryRule['master_item']['key_']]
				: [];

			$result[] = $data;
		}

		return $result;
	}

	/**
	 * Format the LLD macro paths.
	 *
	 * @param array $lld_macro_paths
	 *
	 * @return array
	 */
	private static function formatLldMacroPaths(array $lld_macro_paths): array {
		CArrayHelper::sort($lld_macro_paths, ['lld_macro']);

		return array_values($lld_macro_paths);
	}


	/**
	 * Format the LLD overrides contained in a discovery rule.
	 *
	 * @param array $overrides
	 *
	 * @return array
	 */
	private static function formatLldOverrides(array $overrides): array {
		CArrayHelper::sort($overrides, ['step']);

		foreach ($overrides as &$override) {
			unset($override['filter']['eval_formula']);

			if (!$override['filter']['conditions']) {
				unset($override['filter']);
			}

			CArrayHelper::sort($override['operations'], ['operationobject', 'operator', 'value']);

			foreach ($override['operations'] as &$operation) {
				if (array_key_exists('tags', $operation)) {
					CArrayHelper::sort($operation['tags'], ['tag', 'value']);
				}

				if (array_key_exists('templates', $operation)) {
					CArrayHelper::sort($operation['templates'], ['name']);
				}
			}
			unset($operation);
		}
		unset($override);

		return array_values($overrides);
	}

	/**
	 * Format preprocessing steps.
	 *
	 * @param array $preprocessing_steps
	 *
	 * @return array
	 */
	private static function formatPreprocessingSteps(array $preprocessing_steps) {
		foreach ($preprocessing_steps as &$preprocessing_step) {
			$preprocessing_step['parameters'] = ($preprocessing_step['type'] == ZBX_PREPROC_SCRIPT)
				? [$preprocessing_step['params']]
				: explode("\n", $preprocessing_step['params']);
			unset($preprocessing_step['params']);
		}
		unset($preprocessing_step);

		return $preprocessing_steps;
	}

	/**
	 * Format web scenarios.
	 *
	 * @param array $httptests
	 *
	 * @return array
	 */
	protected function formatHttpTests(array $httptests) {
		$result = [];

		order_result($httptests, 'name');

		foreach ($httptests as $httptest) {
			CArrayHelper::sort($httptest['variables'], ['name']);

			$result[] = [
				'uuid' => $httptest['uuid'],
				'name' => $httptest['name'],
				'delay' => $httptest['delay'],
				'attempts' => $httptest['retries'],
				'agent' => $httptest['agent'],
				'http_proxy' => $httptest['http_proxy'],
				'variables' => $httptest['variables'],
				'headers' => $httptest['headers'],
				'status' => $httptest['status'],
				'authentication' => $httptest['authentication'],
				'http_user' => $httptest['http_user'],
				'http_password' => $httptest['http_password'],
				'verify_peer' => $httptest['verify_peer'],
				'verify_host' => $httptest['verify_host'],
				'ssl_cert_file' => $httptest['ssl_cert_file'],
				'ssl_key_file' => $httptest['ssl_key_file'],
				'ssl_key_password' => $httptest['ssl_key_password'],
				'steps' => $this->formatHttpSteps($httptest['steps']),
				'tags' => $this->formatTags($httptest['tags'])
			];
		}

		return $result;
	}

	/**
	 * Format web scenario steps.
	 *
	 * @param array $httpsteps
	 *
	 * @return array
	 */
	protected function formatHttpSteps(array $httpsteps) {
		$result = [];

		order_result($httpsteps, 'no');

		foreach ($httpsteps as $httpstep) {
			CArrayHelper::sort($httpstep['variables'], ['name']);

			$result[] = [
				'name' => $httpstep['name'],
				'url' => $httpstep['url'],
				'query_fields' => $httpstep['query_fields'],
				'posts' => $httpstep['posts'],
				'variables' => $httpstep['variables'],
				'headers' => $httpstep['headers'],
				'follow_redirects' => $httpstep['follow_redirects'],
				'retrieve_mode' => $httpstep['retrieve_mode'],
				'timeout' => $httpstep['timeout'],
				'required' => $httpstep['required'],
				'status_codes' => $httpstep['status_codes']
			];
		}

		return $result;
	}

	/**
	 * Format host inventory.
	 *
	 * @param array $inventory
	 *
	 * @return array
	 */
	protected function formatHostInventory(array $inventory) {
		unset($inventory['hostid']);

		return $inventory;
	}

	/**
	 * Format graphs.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function formatGraphs(array $graphs) {
		$result = [];

		usort($graphs, static function (array $graph_a, array $graph_b): int {
			$comparison = strnatcasecmp($graph_a['name'], $graph_b['name']);

			if ($comparison != 0) {
				return $comparison;
			}

			$graph_a_items = [];
			$graph_b_items = [];

			foreach ($graph_a['gitems'] as $gitem) {
				$graph_a_items[] = $gitem['itemid'];
			}

			foreach ($graph_b['gitems'] as $gitem) {
				$graph_b_items[] = $gitem['itemid'];
			}

			CArrayHelper::sort($graph_a_items, ['host']);
			CArrayHelper::sort($graph_b_items, ['host']);

			return strnatcasecmp(reset($graph_a_items)['host'], reset($graph_b_items)['host']);
		});

		foreach ($graphs as $graph) {
			$data = [
				'name' => $graph['name'],
				'width' => $graph['width'],
				'height' => $graph['height'],
				'yaxismin' => $graph['yaxismin'],
				'yaxismax' => $graph['yaxismax'],
				'show_work_period' => $graph['show_work_period'],
				'show_triggers' => $graph['show_triggers'],
				'type' => $graph['graphtype'],
				'show_legend' => $graph['show_legend'],
				'show_3d' => $graph['show_3d'],
				'percent_left' => $graph['percent_left'],
				'percent_right' => $graph['percent_right'],
				'ymin_type_1' => $graph['ymin_type'],
				'ymax_type_1' => $graph['ymax_type'],
				'ymin_item_1' => $graph['ymin_itemid'],
				'ymax_item_1' => $graph['ymax_itemid'],
				'graph_items' => $this->formatGraphItems($graph['gitems'])
			];

			if (array_key_exists('uuid', $graph)) {
				$data['uuid'] = $graph['uuid'];
			}

			if ($graph['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$data['discover'] = $graph['discover'];
			}

			$result[] = $data;
		}

		return $result;
	}

	/**
	 * Format host prototypes.
	 *
	 * @param array $hostPrototypes
	 *
	 * @return array
	 */
	protected function formatHostPrototypes(array $hostPrototypes) {
		$result = [];

		CArrayHelper::sort($hostPrototypes, ['host']);

		foreach ($hostPrototypes as $hostPrototype) {
			$result[] = [
				'uuid' => $hostPrototype['uuid'],
				'host' => $hostPrototype['host'],
				'name' => $hostPrototype['name'],
				'status' => $hostPrototype['status'],
				'discover' => $hostPrototype['discover'],
				'group_links' => $this->formatGroupLinks($hostPrototype['groupLinks']),
				'group_prototypes' => $this->formatGroupPrototypes($hostPrototype['groupPrototypes']),
				'macros' => $this->formatMacros($hostPrototype['macros']),
				'tags' => $this->formatTags($hostPrototype['tags']),
				'templates' => $this->formatTemplateLinkage($hostPrototype['templates']),
				'inventory_mode' => $hostPrototype['inventory_mode'],
				'custom_interfaces' => $hostPrototype['custom_interfaces'],
				'interfaces' => $this->formatHostPrototypeInterfaces($hostPrototype['interfaces'])
			];
		}

		return $result;
	}

	/**
	 * Format group links.
	 *
	 * @param array $groupLinks
	 *
	 * @return array
	 */
	protected function formatGroupLinks(array $group_links) {
		$result = [];

		CArrayHelper::sort($group_links, ['name']);

		foreach ($group_links as $group_link) {
			$result[] = [
				'group' => $group_link
			];
		}

		return $result;
	}

	/**
	 * Format group prototypes.
	 *
	 * @param array $groupPrototypes
	 *
	 * @return array
	 */
	protected function formatGroupPrototypes(array $groupPrototypes) {
		$result = [];

		CArrayHelper::sort($groupPrototypes, ['name']);

		foreach ($groupPrototypes as $groupPrototype) {
			$result[] = [
				'name' => $groupPrototype['name']
			];
		}

		return $result;
	}

	/**
	 * Format template linkage.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function formatTemplateLinkage(array $templates) {
		$result = [];

		CArrayHelper::sort($templates, ['host']);

		foreach ($templates as $template) {
			$result[] = [
				'name' => $template['host']
			];
		}

		return $result;
	}

	/**
	 * Format triggers.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function formatTriggers(array $triggers) {
		$result = [];

		CArrayHelper::sort($triggers, ['description', 'expression', 'recovery_expression']);

		foreach ($triggers as $trigger) {
			$data = [
				'expression' => $trigger['expression'],
				'recovery_mode' => $trigger['recovery_mode'],
				'recovery_expression' => $trigger['recovery_expression'],
				'name' => $trigger['description'],
				'event_name' => $trigger['event_name'],
				'opdata' => $trigger['opdata'],
				'correlation_mode' => $trigger['correlation_mode'],
				'correlation_tag' => $trigger['correlation_tag'],
				'url_name' => $trigger['url_name'],
				'url' => $trigger['url'],
				'status' => $trigger['status'],
				'priority' => $trigger['priority'],
				'description' => $trigger['comments'],
				'type' => $trigger['type'],
				'manual_close' => $trigger['manual_close'],
				'dependencies' => $this->formatDependencies($trigger['dependencies']),
				'tags' => $this->formatTags($trigger['tags'])
			];

			if (array_key_exists('uuid', $trigger)) {
				$data['uuid'] = $trigger['uuid'];
			}

			if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$data['discover'] = $trigger['discover'];
			}

			$result[] = $data;
		}

		return $result;
	}

	/**
	 * Format host interfaces.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function formatHostInterfaces(array $interfaces) {
		$result = [];

		CArrayHelper::sort($interfaces, ['type', 'ip', 'dns', 'port']);

		foreach ($interfaces as $interface) {
			$result[] = [
				'default' => $interface['main'],
				'type' => $interface['type'],
				'useip' => $interface['useip'],
				'ip' => $interface['ip'],
				'dns' => $interface['dns'],
				'port' => $interface['port'],
				'details' => $interface['details'],
				'interface_ref' => $interface['interface_ref']
			];
		}

		return $result;
	}

	/**
	 * Format host prototype interfaces.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function formatHostPrototypeInterfaces(array $interfaces): array {
		$result = [];

		CArrayHelper::sort($interfaces, ['type', 'ip', 'dns', 'port']);

		foreach ($interfaces as $num => $interface) {
			$result[$num] = [
				'default' => $interface['main'],
				'type' => $interface['type'],
				'useip' => $interface['useip'],
				'ip' => $interface['ip'],
				'dns' => $interface['dns'],
				'port' => $interface['port']
			];

			if ($interface['type'] == INTERFACE_TYPE_SNMP) {
				$result[$num]['details'] = $interface['details'];
			}
		}

		return $result;
	}

	/**
	 * Format groups.
	 *
	 * @param array $groups
	 *
	 * @return array
	 */
	protected function formatGroups(array $groups) {
		$result = [];

		CArrayHelper::sort($groups, ['name']);

		foreach ($groups as $group) {
			$result[] = [
				'uuid' => $group['uuid'],
				'name' => $group['name']
			];
		}

		return $result;
	}

	/**
	 * Format items.
	 *
	 * @param array $items
	 * @param array $simple_triggers
	 *
	 * @return array
	 */
	protected function formatItems(array $items, array $simple_triggers) {
		$result = [];

		CArrayHelper::sort($items, ['key_']);

		foreach ($items as $item) {
			$data = [
				'uuid' => $item['uuid'],
				'name' => $item['name'],
				'type' => $item['type'],
				'snmp_oid' => $item['snmp_oid'],
				'key' => $item['key_'],
				'delay' => $item['delay'],
				'history' => $item['history'],
				'trends' => $item['trends'],
				'status' => $item['status'],
				'value_type' => $item['value_type'],
				'allowed_hosts' => $item['trapper_hosts'],
				'units' => $item['units'],
				'params' => $item['params'],
				'ipmi_sensor' => $item['ipmi_sensor'],
				'authtype' => $item['authtype'],
				'username' => $item['username'],
				'password' => $item['password'],
				'publickey' => $item['publickey'],
				'privatekey' => $item['privatekey'],
				'description' => $item['description'],
				'inventory_link' => $item['inventory_link'],
				'valuemap' => $item['valuemap'],
				'logtimefmt' => $item['logtimefmt'],
				'preprocessing' => self::formatPreprocessingSteps($item['preprocessing']),
				'jmx_endpoint' => $item['jmx_endpoint'],
				'timeout' => $item['timeout'],
				'url' => $item['url'],
				'query_fields' => $item['query_fields'],
				'parameters' => self::formatItemParameters($item['parameters']),
				'posts' => $item['posts'],
				'status_codes' => $item['status_codes'],
				'follow_redirects' => $item['follow_redirects'],
				'post_type' => $item['post_type'],
				'http_proxy' => $item['http_proxy'],
				'headers' => $item['headers'],
				'retrieve_mode' => $item['retrieve_mode'],
				'request_method' => $item['request_method'],
				'output_format' => $item['output_format'],
				'allow_traps' => $item['allow_traps'],
				'ssl_cert_file' => $item['ssl_cert_file'],
				'ssl_key_file' => $item['ssl_key_file'],
				'ssl_key_password' => $item['ssl_key_password'],
				'tags' => $this->formatTags($item['tags']),
				'verify_peer' => $item['verify_peer'],
				'verify_host' => $item['verify_host']
			];

			$master_item = ($item['type'] == ITEM_TYPE_DEPENDENT) ? ['key' => $item['master_item']['key_']] : [];

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$data['discover'] = $item['discover'];
			}

			$data['master_item'] = $master_item;

			if (isset($item['interface_ref'])) {
				$data['interface_ref'] = $item['interface_ref'];
			}

			if ($simple_triggers) {
				$triggers = [];
				foreach ($simple_triggers as $simple_trigger) {
					if (bccomp($item['itemid'], $simple_trigger['items'][0]['itemid']) == 0) {
						$triggers[] = $simple_trigger;
					}
				}

				if ($triggers) {
					$key = array_key_exists('discoveryRule', $item) ? 'trigger_prototypes' : 'triggers';
					$data[$key] = $this->formatTriggers($triggers);
				}
			}

			$result[] = $data;
		}

		return $result;
	}

	/**
	 * Format item parameters.
	 *
	 * @param array $parameters
	 *
	 * @return array
	 */
	private static function formatItemParameters(array $parameters): array {
		CArrayHelper::sort($parameters, ['name']);

		return array_values($parameters);
	}

	/**
	 * Format macros.
	 *
	 * @param array $macros
	 *
	 * @return array
	 */
	protected function formatMacros(array $macros) {
		$result = [];

		$macros = order_macros($macros, 'macro');

		foreach ($macros as $macro) {
			$result[] = [
				'macro' => $macro['macro'],
				'type' => $macro['type'],
				'value' => array_key_exists('value', $macro) ? $macro['value'] : '',
				'description' => $macro['description']
			];
		}

		return $result;
	}

	/**
	 * Format trigger dependencies.
	 *
	 * @param array $dependencies
	 *
	 * @return array
	 */
	protected function formatDependencies(array $dependencies) {
		$result = [];

		CArrayHelper::sort($dependencies, ['description', 'expression', 'recovery_expression']);

		foreach ($dependencies as $dependency) {
			$result[] = [
				'name' => $dependency['description'],
				'expression' => $dependency['expression'],
				'recovery_expression' => $dependency['recovery_expression']
			];
		}

		return $result;
	}

	/**
	 * Format tags.
	 *
	 * @param array $tags
	 *
	 * @return array
	 */
	protected function formatTags(array $tags) {
		$result = [];
		$fields = [
			'tag' => true,
			'value' => true,
			'operator' => true
		];

		CArrayHelper::sort($tags, ['tag', 'value']);

		foreach ($tags as $tag) {
			$result[] = array_intersect_key($tag, $fields);
		}

		return $result;
	}

	/**
	 * Format dashboards.
	 *
	 * @param array $dashboards
	 *
	 * @return array
	 */
	protected function formatDashboards(array $dashboards) {
		$result = [];

		CArrayHelper::sort($dashboards, ['name']);

		foreach ($dashboards as $dashboard) {
			$result[] = [
				'uuid' => $dashboard['uuid'],
				'name' => $dashboard['name'],
				'display_period' => $dashboard['display_period'],
				'auto_start' => $dashboard['auto_start'],
				'pages' => $this->formatDashboardPages($dashboard['pages'])
			];
		}

		return $result;
	}

	/**
	 * Format dashboard pages.
	 *
	 * @param array $dashboard_pages
	 *
	 * @return array
	 */
	protected function formatDashboardPages(array $dashboard_pages) {
		$result = [];

		foreach ($dashboard_pages as $dashboard_page) {
			$result[] = [
				'name' => $dashboard_page['name'],
				'display_period' => $dashboard_page['display_period'],
				'widgets' => $this->formatWidgets($dashboard_page['widgets'])
			];
		}

		return $result;
	}

	/**
	 * Format widgets.
	 *
	 * @param array $widgets
	 *
	 * @return array
	 */
	protected function formatWidgets(array $widgets) {
		$result = [];

		CArrayHelper::sort($widgets, ['x', 'y']);

		foreach ($widgets as $widget) {
			$result[] = [
				'type' => $widget['type'],
				'name' => $widget['name'],
				'x' => $widget['x'],
				'y' => $widget['y'],
				'width' => $widget['width'],
				'height' => $widget['height'],
				'hide_header' => $widget['view_mode'],
				'fields' => $this->formatWidgetFields($widget['fields'])
			];
		}

		return $result;
	}

	/**
	 * Format widget fields.
	 *
	 * @param array $widgets
	 *
	 * @return array
	 */
	protected function formatWidgetFields(array $fields) {
		$result = [];

		self::sortWidgetFields($fields);

		foreach ($fields as $field) {
			$result[] = [
				'type' => $field['type'],
				'name' => $field['name'],
				'value' => $field['value']
			];
		}

		return $result;
	}

	/**
	 * Sorts widget fields taking into account expanded objects.
	 *
	 * @param array $fields
	 */
	private static function sortWidgetFields(array &$fields): void {
		usort($fields, static function(array $widget_field_a, array $widget_field_b): int {
			$comparison = strnatcasecmp($widget_field_a['name'], $widget_field_b['name']);

			if ($comparison != 0) {
				return $comparison;
			}

			$comparison = strnatcasecmp($widget_field_a['type'], $widget_field_b['type']);

			if ($comparison != 0) {
				return $comparison;
			}

			switch ($widget_field_a['type']) {
				case ZBX_WIDGET_FIELD_TYPE_HOST:
					$value_fields = ['host'];
					break;

				case ZBX_WIDGET_FIELD_TYPE_ITEM:
				case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
					$value_fields = ['host', 'key'];
					break;

				case ZBX_WIDGET_FIELD_TYPE_GRAPH:
				case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
					$value_fields = ['host', 'name'];
					break;

				default:
					return strnatcasecmp($widget_field_a['value'], $widget_field_b['value']);
			}

			foreach ($value_fields as $value_field) {
				$comparison = strnatcasecmp($widget_field_a['value'][$value_field],
					$widget_field_b['value'][$value_field]
				);

				if ($comparison != 0) {
					return $comparison;
				}
			}

			return 0;
		});
	}

	/**
	 * Format graph items.
	 *
	 * @param array $graphItems
	 *
	 * @return array
	 */
	protected function formatGraphItems(array $graphItems) {
		$result = [];

		CArrayHelper::sort($graphItems, ['sortorder']);

		foreach ($graphItems as $graphItem) {
			$result[] = [
				'sortorder'=> $graphItem['sortorder'],
				'drawtype'=> $graphItem['drawtype'],
				'color'=> $graphItem['color'],
				'yaxisside'=> $graphItem['yaxisside'],
				'calc_fnc'=> $graphItem['calc_fnc'],
				'type'=> $graphItem['type'],
				'item'=> $graphItem['itemid']
			];
		}

		return $result;
	}

	/**
	 * Format map urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapUrls(array $urls) {
		$result = [];

		CArrayHelper::sort($urls, ['name', 'url']);

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url'],
				'elementtype' => $url['elementtype']
			];
		}

		return $result;
	}

	/**
	 * Format map element urls.
	 *
	 * @param array $urls
	 *
	 * @return array
	 */
	protected function formatMapElementUrls(array $urls) {
		$result = [];

		CArrayHelper::sort($urls, ['name', 'url']);

		foreach ($urls as $url) {
			$result[] = [
				'name' => $url['name'],
				'url' => $url['url']
			];
		}

		return $result;
	}

	/**
	 * Format map links.
	 *
	 * @param array $links			Map links
	 * @param array $selements		Map elements
	 *
	 * @return array
	 */
	protected function formatMapLinks(array $links, array $selements) {
		$result = [];

		// Get array where key is selementid and value is sort position.
		$flipped_selements = [];
		$selements = array_values($selements);

		foreach ($selements as $key => $item) {
			if (array_key_exists('selementid', $item)) {
				$flipped_selements[$item['selementid']] = $key;
			}
		}

		foreach ($links as &$link) {
			$link['selementpos1'] = $flipped_selements[$link['selementid1']];
			$link['selementpos2'] = $flipped_selements[$link['selementid2']];

			// Sort selements by position asc.
			if ($link['selementpos2'] < $link['selementpos1']) {
				[$link['selementpos1'], $link['selementpos2']] = [$link['selementpos2'], $link['selementpos1']];
			}
		}
		unset($link);

		CArrayHelper::sort($links, ['selementpos1', 'selementpos2']);

		foreach ($links as $link) {
			$result[] = [
				'drawtype' => $link['drawtype'],
				'color' => $link['color'],
				'label' => $link['label'],
				'selementid1' => $link['selementid1'],
				'selementid2' => $link['selementid2'],
				'linktriggers' => $this->formatMapLinkTriggers($link['linktriggers'])
			];
		}

		return $result;
	}

	/**
	 * Format map link triggers.
	 *
	 * @param array $linktriggers
	 *
	 * @return array
	 */
	protected function formatMapLinkTriggers(array $linktriggers) {
		$result = [];

		foreach ($linktriggers as &$linktrigger) {
			$linktrigger['description'] = $linktrigger['triggerid']['description'];
			$linktrigger['expression'] = $linktrigger['triggerid']['expression'];
			$linktrigger['recovery_expression'] = $linktrigger['triggerid']['recovery_expression'];
		}
		unset($linktrigger);

		CArrayHelper::sort($linktriggers, ['description', 'expression', 'recovery_expression']);

		foreach ($linktriggers as $linktrigger) {
			$result[] = [
				'drawtype' => $linktrigger['drawtype'],
				'color' => $linktrigger['color'],
				'trigger' => $linktrigger['triggerid']
			];
		}

		return $result;
	}

	/**
	 * Format map elements.
	 *
	 * @param array $elements
	 *
	 * @return array
	 */
	protected function formatMapElements(array $elements) {
		$result = [];

		CArrayHelper::sort($elements, ['y', 'x']);

		foreach ($elements as $element) {
			$result[] = [
				'elementtype' => $element['elementtype'],
				'label' => $element['label'],
				'label_location' => $element['label_location'],
				'x' => $element['x'],
				'y' => $element['y'],
				'elementsubtype' => $element['elementsubtype'],
				'areatype' => $element['areatype'],
				'width' => $element['width'],
				'height' => $element['height'],
				'viewtype' => $element['viewtype'],
				'use_iconmap' => $element['use_iconmap'],
				'selementid' => $element['selementid'],
				'elements' => $element['elements'],
				'icon_off' => $element['iconid_off'],
				'icon_on' => $element['iconid_on'],
				'icon_disabled' => $element['iconid_disabled'],
				'icon_maintenance' => $element['iconid_maintenance'],
				'urls' => $this->formatMapElementUrls($element['urls']),
				'evaltype' => $element['evaltype'],
				'tags' => $this->formatTags($element['tags'])
			];
		}

		return $result;
	}
}
