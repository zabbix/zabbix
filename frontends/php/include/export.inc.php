<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

class zbxXML{

	private static $ZBX_EXPORT_MAP = array(
		XML_TAG_HOST => array(
			'attributes' => array(
				'host' 				=> 'name'
			),
			'elements' => array(
				'proxy'				=> '',
				'useip'				=> '',
				'dns'				=> '',
				'ip'				=> '',
				'port'				=> '',
				'status'			=> ''
			)
		),
		XML_TAG_MACRO => array(
			'attributes' => array(),
			'elements' => array(
				'value' 			=> '',
				'macro' 			=> 'name'
			)
		),
		XML_TAG_HOSTPROFILE => array(
			'attributes' => array(),
			'elements' => array(
				'devicetype'		=> '',
				'name'				=> '',
				'os'				=> '',
				'serialno'			=> '',
				'tag'				=> '',
				'macaddress'		=> '',
				'hardware'			=> '',
				'software'			=> '',
				'contact'			=> '',
				'location'			=> '',
				'notes'				=> ''
			)
		),
		XML_TAG_HOSTPROFILE_EXT => array(
			'attributes' => array(),
			'elements' => array(
				'device_alias'		=> '',
				'device_type'		=> '',
				'device_chassis'	=> '',
				'device_os'			=> '',
				'device_os_short'	=> '',
				'device_hw_arch'	=> '',
				'device_serial'		=> '',
				'device_model'		=> '',
				'device_tag'		=> '',
				'device_vendor'		=> '',
				'device_contract'	=> '',
				'device_who'		=> '',
				'device_status'		=> '',
				'device_app_01'		=> '',
				'device_app_02'		=> '',
				'device_app_03'		=> '',
				'device_app_04'		=> '',
				'device_app_05'		=> '',
				'device_url_1'		=> '',
				'device_url_2'		=> '',
				'device_url_3'		=> '',
				'device_networks'	=> '',
				'device_notes'		=> '',
				'device_hardware'	=> '',
				'device_software'	=> '',
				'ip_subnet_mask'	=> '',
				'ip_router'			=> '',
				'ip_macaddress'		=> '',
				'oob_ip'			=> '',
				'oob_subnet_mask'	=> '',
				'oob_router'		=> '',
				'date_hw_buy'		=> '',
				'date_hw_install'	=> '',
				'date_hw_expiry'	=> '',
				'date_hw_decomm'	=> '',
				'site_street_1'		=> '',
				'site_street_2'		=> '',
				'site_street_3'		=> '',
				'site_city'			=> '',
				'site_state'		=> '',
				'site_country'		=> '',
				'site_zip'			=> '',
				'site_rack'			=> '',
				'site_notes'		=> '',
				'poc_1_name'		=> '',
				'poc_1_email'		=> '',
				'poc_1_phone_1'		=> '',
				'poc_1_phone_2'		=> '',
				'poc_1_cell'		=> '',
				'poc_1_screen'		=> '',
				'poc_1_notes'		=> '',
				'poc_2_name'		=> '',
				'poc_2_email'		=> '',
				'poc_2_phone_1'		=> '',
				'poc_2_phone_2'		=> '',
				'poc_2_cell'		=> '',
				'poc_2_screen'		=> '',
				'poc_2_notes'		=> ''
			)
		),
		XML_TAG_DEPENDENCY => array(
			'attributes' => array(
				'host_trigger'		=> 'description'),
			'elements' => array(
				'depends'			=> ''
			)
		),
		XML_TAG_ITEM => array(
			'attributes' => array(
				'type'				=> '',
				'key_'				=> 'key',
				'value_type'		=> ''
			),
			'elements' => array(
				'description'		=> '',
				'ipmi_sensor'		=> '',
				'delay'				=> '',
				'history'			=> '',
				'trends'			=> '',
				'status'			=> '',
				'data_type'			=> '',
				'units'				=> '',
				'multiplier'		=> '',
				'delta'				=> '',
				'formula'			=> '',
				'lastlogsize'		=> '',
				'logtimefmt'		=> '',
				'delay_flex'		=> '',
				'authtype'		=> '',
				'username'		=> '',
				'password'		=> '',
				'publickey'		=> '',
				'privatekey'		=> '',
				'params'			=> '',
				'trapper_hosts'		=> '',
				'snmp_community'	=> '',
				'snmp_oid'			=> '',
				'snmp_port'			=> '',
				'snmpv3_securityname'	=> '',
				'snmpv3_securitylevel'	=> '',
				'snmpv3_authpassphrase'	=> '',
				'snmpv3_privpassphrase'	=> ''
			)
		),
		XML_TAG_TRIGGER => array(
			'attributes' => array(),
			'elements' => array(
				'description'		=> '',
				'type'				=> '',
				'expression'		=> '',
				'url'				=> '',
				'status'			=> '',
				'priority'			=> '',
				'comments'			=> ''
			)
		),
		XML_TAG_GRAPH => array(
			'attributes' => array(
				'name'				=> '',
				'width'				=> '',
				'height'			=> ''
			),
			'elements' => array(
				'ymin_type'			=> '',
				'ymax_type'			=> '',
				'ymin_item_key'		=> '',
				'ymax_item_key'		=> '',
				'show_work_period'	=> '',
				'show_triggers'		=> '',
				'graphtype'			=> '',
				'yaxismin'			=> '',
				'yaxismax'			=> '',
				'show_legend'		=> '',
				'show_3d'			=> '',
				'percent_left'		=> '',
				'percent_right'		=> ''
			)
		),
		XML_TAG_GRAPH_ELEMENT => array(
			'attributes' => array(
				'host_key_'			=> 'item'
			),
			'elements' => array(
				'drawtype'			=> '',
				'sortorder'			=> '',
				'color'				=> '',
				'yaxisside'			=> '',
				'calc_fnc'			=> '',
				'type'				=> '',
				'periods_cnt'		=> ''
			)
		)
	);

	private static function space2tab($matches){
		return str_repeat("\t", strlen($matches[0]) / 2 );
	}

	public static function arrayToXML($array, $root = 'root', $xml = null){

		if($xml == null){
			$xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$root />");
		}

 		foreach($array as $key => $value){

			if(is_numeric($key)){
				$key = 'node_'. $key;
			}
			if(is_array($value)){
				$node = $xml->addChild($key);
				self::toXml($value, $root, $node);
			}
			else{
				$value = htmlentities($value);
				$xml->addChild($key, $value);
			}
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xml->asXML());
		$doc->formatOutput = true;

		return preg_replace_callback('/^( {2,})/m', 'self::callback', $doc->saveXML());
	}

	private static function addChildData($node, $child_name, $data){
		$child_node = $node->addChild($child_name);

		foreach(self::$ZBX_EXPORT_MAP[$child_name]['attributes'] as $attr => $name){
			if($name == '') $name = $attr;
			$child_node->addAttribute($name, $data[$attr]);
		}
		foreach(self::$ZBX_EXPORT_MAP[$child_name]['elements'] as $el => $name){
			if($name == '') $name = $el;
			$child_node->addChild($name, $data[$el]);
		}

		return $child_node;
	}

	public static function export($data){

		$xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><zabbix_export />");
		$xml->addAttribute('version', '1.0');
		$xml->addAttribute('date', date('d.m.y'));
		$xml->addAttribute('time', date('H.i'));

		$hosts_node = $xml->addChild(XML_TAG_HOSTS);
		foreach($data['hosts'] as $host){
// HOST
			$host_node = self::addChildData($hosts_node, XML_TAG_HOST, $host);
// HOST PROFILE
			self::addChildData($host_node, XML_TAG_HOSTPROFILE, $host['profile']);
			self::addChildData($host_node, XML_TAG_HOSTPROFILE_EXT, $host['profile_ext']);
// GROUPS
			if(isset($data['hosts_groups'])){
				$groups_node = $host_node->addChild(XML_TAG_GROUPS);
				foreach($data['hosts_groups'] as $group){
					if(isset($group['hostids'][$host['hostid']])){
						$groups_node->addChild(XML_TAG_GROUP, $group['name']);
					}
				}
			}
// TRIGGERS
			if(isset($data['triggers'])){
				$triggers_node = $host_node->addChild(XML_TAG_TRIGGERS);
				foreach($data['triggers'] as $trigger){
					if(isset($trigger['hostids'][$host['hostid']])){
						self::addChildData($triggers_node, XML_TAG_TRIGGER, $trigger);
					}
				}
			}
// ITEMS
			if(isset($data['items'])){
				$items_node = $host_node->addChild(XML_TAG_ITEMS);
				foreach($data['items'] as $item){
					if(isset($item['hostids'][$host['hostid']])){
						$item_node = self::addChildData($items_node, XML_TAG_ITEM, $item);
//sdi('Item: '. date('H i s u'));
						if(isset($data['items_applications'])){
							$applications_node = $item_node->addChild(XML_TAG_APPLICATIONS);
							foreach($data['items_applications'] as $application){
								if(isset($application['itemids'][$item['itemid']])){
									$applications_node->addChild(XML_TAG_APPLICATION, $application['name']);
								}
							}
						}
					}
				}
			}
// TEMPLATES
			if(isset($data['templates'])){
				$templates_node = $host_node->addChild(XML_TAG_TEMPLATES);
				foreach($data['templates'] as $template){
					if(isset($template['hostids'][$host['hostid']])){
						$templates_node->addChild(XML_TAG_TEMPLATE, $template['host']);
					}
				}
			}

// GRAPHS

			if(isset($data['graphs'])){
				$graphs_node = $host_node->addChild(XML_TAG_GRAPHS);
				foreach($data['graphs'] as $graph){
					if(isset($graph['hostids'][$host['hostid']])){
						$graph_node = self::addChildData($graphs_node, XML_TAG_GRAPH, $graph);

						if(isset($data['graphs_items'])){
							$graph_elements_node = $graph_node->addChild(XML_TAG_GRAPH_ELEMENTS);
							foreach($data['graphs_items'] as $gitem){
								if(isset($gitem['graphids'][$graph['graphid']])){
									self::addChildData($graph_elements_node, XML_TAG_GRAPH_ELEMENT, $gitem);
								}
							}
						}
					}
				}
			}

// MACROS
			if(isset($data['macros'])){
				$macros_node = $host_node->addChild(XML_TAG_MACROS);
				foreach($data['macros'] as $macro){
					if(isset($macro['hostids'][$host['hostid']])){
						self::addChildData($macros_node, XML_TAG_MACRO, $macro);
					}
				}
			}

		}
// DEPENDENCIES
			if(isset($data['dependencies'])){
				$dependencies_node = $xml->addChild(XML_TAG_DEPENDENCIES);
				foreach($data['dependencies'] as $dep_data){
					$dependeny_node = $dependencies_node->addChild(XML_TAG_DEPENDENCY);
					$dependeny_node->addAttribute('description', $dep_data['trigger']['host_description']);
					foreach($dep_data['depends_on'] as $dep_trigger){
						$dependeny_node->addChild('depends', $dep_trigger['host_description']);
					};
				}
			}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xml->asXML());
		$doc->formatOutput = true;

		return preg_replace_callback('/^( {2,})/m', array('self', 'space2tab'), $doc->saveXML());
	}

	private static function mapXML2arr($xml, $tag){

		$array = array();
		foreach(self::$ZBX_EXPORT_MAP[$tag]['attributes'] as $attr => $value){
			if($value == '') $value = $attr;

			if(!(((string) $xml[$value]) == '')){
				$array[$attr] = (string) $xml[$value];
			}
		}
		foreach(self::$ZBX_EXPORT_MAP[$tag]['elements'] as $el => $value){
			if($value == '') $value = $el;

			if(!(((string) $xml->$value) == '')){
				$array[$el] = (string) $xml->$value;
			}
		}

		return $array;
	}

	public static function import($rules, $file){

		$result = true;

		libxml_use_internal_errors(true);
		$xml = @simplexml_load_file($file);

		if(!$xml){
			foreach(libxml_get_errors() as $error){
				$text = '';

				switch($error->level){
					case LIBXML_ERR_WARNING:
						$text .= "Warning $error->code: ";
					break;
					case LIBXML_ERR_ERROR:
						$text .= "Error $error->code: ";
					break;
					case LIBXML_ERR_FATAL:
						$text .= "Fatal Error $error->code: ";
					break;
				}

				$text .= trim($error->message) . " [ Line: $error->line | Column: $error->column ]";
				error($text);
			}

			libxml_clear_errors();
			return false;
		}

		$triggers_for_dependencies = array();

		if(isset($rules['host']['exist']) || isset($rules['host']['missed'])){
			$hosts = $xml->xpath('hosts/host');

			foreach($hosts as $host){

// IMPORT RULES
				$host_db = self::mapXML2arr($host, XML_TAG_HOST);
				$current_hostid = CHost::getId(array('host' => $host_db['host']));

				if(!$current_hostid && !isset($rules['host']['missed'])) continue; // break if update nonexist
				if($current_hostid && !isset($rules['host']['exist'])) continue; // break if not update exist


// HOST GROUPS
				$groups = $host->xpath('groups/group');
				$host_groupids = array();
				$new_groupids = array();

				if(empty($groups)){
					$current_groupid = CHostGroup::getId(array('name' => ZBX_DEFAULT_IMPORT_HOST_GROUP));
					if($current_groupid){
						$host_groupids[] = $current_groupid;
					}
					else{
						$host_groupids = CHostGroup::add(array('name' => ZBX_DEFAULT_IMPORT_HOST_GROUP));
						if($host_groupids === false){
							error(CHostGroup::resetErrors());
							$result = false;
							break;
						}
					}
				}
				else{
					$groups_to_add = array();
					foreach($groups as $group){
						$group_name = (string) $group;
						$current_groupid = CHostGroup::getId(array('name' => $group_name));
//sdi('group: '.$group_name.' | GroupID: '. $current_groupid);
						if(!$current_groupid){
							$groups_to_add[] = $group_name;
						}
						else{
							$host_groupids[] = $current_groupid;
						}
					}
					if(!empty($groups_to_add)){
						$groups_to_add = zbx_toObject($groups_to_add, 'name');
						$new_groupids = CHostGroup::add($groups_to_add);

						if($new_groupids === false){
							error(CHostGroup::resetErrors());
							$result = false;
							break;
						}
					}
				}

				if($new_groupids)
					$host_groupids = array_merge($new_groupids, $host_groupids);
				else
					$new_groupids = array();

// HOSTS
//sdi('Host: '.$host_db['host'].' | HostID: '. $current_hostid);
				if($current_hostid && isset($rules['host']['exist'])){
					$host_db['hostid'] = $current_hostid;
					$r = CHost::update(array($host_db));
					if($r === false){
						error(CHost::resetErrors());
						$result = false;
						break;
					}
					if(!empty($new_groupids)){
						$r = CHostGroup::addHosts(array('hostids' => $current_hostid, 'groupids' => $new_groupids));
						if($r === false){
							error(CHostGroup::resetErrors());
							$result = false;
							break;
						}
					}
				}

				if(!$current_hostid && isset($rules['host']['missed'])){
					$host_db['groupids'] = $host_groupids;
					$current_hostid = CHost::add(array($host_db));
					$current_hostid = reset($current_hostid);
				}

// HOST PROFILES
				$profile = $host->xpath('host_profile');
				if($profile){
					$profile = $profile[0];

					delete_host_profile($current_hostid);
					add_host_profile($current_hostid,
						(string) $profile->devicetype,
						(string) $profile->name,
						(string) $profile->os,
						(string) $profile->serialno,
						(string) $profile->tag,
						(string) $profile->macaddress,
						(string) $profile->hardware,
						(string) $profile->software,
						(string) $profile->contact,
						(string) $profile->location,
						(string) $profile->notes
					);
				}

				$profile_ext = $host->xpath('host_profiles_ext');
				if($profile_ext){
					$profile_ext = $profile_ext[0];
					$profile_ext_db = self::mapXML2arr($profile_ext, XML_TAG_HOSTPROFILE_EXT);
					delete_host_profile_ext($current_hostid);
					add_host_profile_ext($current_hostid, $profile_ext_db);
				}

// MACROS
				$macros = $host->xpath('macros/macro');
				if(!empty($macros)){
					$macros_to_add = array();
					$macros_to_add['macros'] = array();
					$macros_to_upd = array();
					$macros_to_upd['macros'] = array();
					foreach($macros as $macro){
						$macro_db = self::mapXML2arr($macro, XML_TAG_MACRO);
						$current_macroid = CUserMacro::getHostMacroId(array('macro' => $macro_db['macro'], 'hostid' => $current_hostid));

						$macros_to_upd['hostid'] = $macros_to_add['hostid'] = $current_hostid;
						if($current_macroid){
							$macros_to_upd['macros'][] = $macro_db;
						}
						else{
							$macros_to_add['macros'][] =  $macro_db;
						}
					}
//sdii($macros_to_upd);

					$r = CUserMacro::add($macros_to_add);
					if($r === false){
						error(CUserMacro::resetErrors());
						$result = false;
						break;
					}
					$r = CUserMacro::updateValue($macros_to_upd);
					if($r === false){
						error(CUserMacro::resetErrors());
						$result = false;
						break;
					}
				}
// ITEMS
				if(isset($rules['item']['exist']) || isset($rules['item']['missed'])){
					$items = $host->xpath('items/item');

					$items_to_add = array();
					$items_to_upd = array();
					foreach($items as $item){
						$item_db = self::mapXML2arr($item, XML_TAG_ITEM);
						$current_itemid = CItem::getId(array('key_' => $item_db['key_'], 'host' => $host_db['host']));
// sdii(array('key_' => $item_db['key_'], 'host' => $host_db['host']));
						if(!$current_itemid && !isset($rules['item']['missed'])) continue; // break if update nonexist
						if($current_itemid && !isset($rules['item']['exist'])) continue; // break if not update exist


						$applications = $item->xpath('applications/application');
						$item_applicationids = array();
						$new_applicationids = array();
						if(!empty($applications)){
							$applications_to_add = array();

							foreach($applications as $application){
								$application_name = (string) $application;
								$current_applicationid = CApplication::getId(array('name' => $application_name, 'hostid' => $current_hostid));
//sdi('application: '.$application.' | applicationID: '. $current_applicationid);
								if(!$current_applicationid){
									$applications_to_add[] = array('name' => $application_name, 'hostid' => $current_hostid);
								}
								else{
									$item_applicationids[] = $current_applicationid;
								}
							}
							if(!empty($applications_to_add)){
								$new_applicationids = CApplication::add($applications_to_add);
								if($new_applicationids === false){
									error(CApplication::resetErrors());
									$result = false;
									break 2;
								}
							}
						}

						$item_db['applications'] = zbx_array_merge($item_applicationids, $new_applicationids);

//sdi('item: '.$item.' | itemID: '. $current_itemid);
						if($current_itemid && isset($rules['item']['exist'])){
							$item_db['itemid'] = $current_itemid;
							$items_to_upd[] = $item_db;
						}
						if(!$current_itemid && isset($rules['item']['missed'])){
							$item_db['hostid'] = $current_hostid;
							$items_to_add[] = $item_db;
						}
					}
// sdii($items_to_upd);
// sdii($items_to_add);
					$r = CItem::add($items_to_add);
					if($r === false){
						error(CItem::resetErrors());
						$result = false;
						break;
					}
					$r = CItem::update($items_to_upd);
					if($r === false){
						error(CItem::resetErrors());
						$result = false;
						break;
					}
				}

// TRIGGERS

				if(isset($rules['trigger']['exist']) || isset($rules['trigger']['missed'])){
					$triggers = $host->xpath('triggers/trigger');

					$added_triggers = array();
					$triggers_to_add = array();
					$triggers_to_upd = array();
					foreach($triggers as $trigger){
						$trigger_db = self::mapXML2arr($trigger, XML_TAG_TRIGGER);
						$trigger_db['expression'] = str_replace('{{HOSTNAME}:', '{'.$host_db['host'].':', $trigger_db['expression']);

						$current_triggerid = CTrigger::getId(array('description' => $trigger_db['description'], 'host' => $host_db['host'], 'expression' => $trigger_db['expression']));
						$current_triggerid = reset($current_triggerid);
						$current_triggerid = $current_triggerid ? $current_triggerid['triggerid'] : false;

// sdi('trigger: '.$trigger_db['description'].' | triggerID: '. $current_triggerid);
// sdi(isset($rules['trigger']['missed']));
						if(!$current_triggerid && !isset($rules['trigger']['missed'])) continue; // break if update nonexist
						if($current_triggerid && !isset($rules['trigger']['exist'])) continue; // break if not update exist


						if($current_triggerid && isset($rules['trigger']['exist'])){
							$trigger_db['triggerid'] = $current_triggerid;
							$triggers_for_dependencies[$current_triggerid] = $current_triggerid;
							$triggers_to_upd[] = $trigger_db;
						}
						if(!$current_triggerid && isset($rules['trigger']['missed'])){
							$trigger_db['hostid'] = $current_hostid;
							$triggers_to_add[] = $trigger_db;
						}
					}
// sdii($triggers_to_add);
// sdii($triggers_to_upd);
					if(!empty($triggers_to_add)){
						$added_triggers = CTrigger::add($triggers_to_add);
						if($added_triggers === false){
							error(CTrigger::resetErrors());
							$result = false;
							break;
						}
					}

					if(!empty($triggers_to_upd)){
						$r = CTrigger::update($triggers_to_upd);
						if($r === false){
							error(CTrigger::resetErrors());
							$result = false;
							break;
						}
					}

					$triggers_for_dependencies = array_merge($triggers_for_dependencies, $added_triggers);
				}
// TEMPLATES
				if(isset($rules['template']['exist'])){
					$templates = $host->xpath('templates/template');
					$host_templates = CTemplate::get(array('hostids' => $current_hostid));
					$host_templateids = array_flip(zbx_objectValues($host_templates,'templateid'));

					$templates_to_upd = array();
					foreach($templates as $template){
						$current_templateid = CTemplate::getId(array('name' => (string) $template));

						if(!$current_templateid && !isset($rules['template']['missed'])) continue; // break if update nonexist
						if($current_templateid && !isset($rules['template']['exist'])) continue; // break if not update exist

//sdi('template: '.$template.' | TemplateID: '. $current_templateid);
						if($current_templateid && !isset($host_templateids[$current_templateid])){
							$templates_to_upd[] = $current_templateid;
						}
					}
					$r = CTemplate::linkTemplates(array('hostid' => $current_hostid, 'templateids' => $templates_to_upd));
					if($r === false){
						error(CTemplate::resetErrors());
						$result = false;
						break;
					}
				}

// GRAPHS
				if(isset($rules['graph']['exist']) || isset($rules['graph']['missed'])){
					$graphs = $host->xpath('graphs/graph');

					$graphs_to_add = array();
					foreach($graphs as $graph){
						$graph_db = self::mapXML2arr($graph, XML_TAG_GRAPH);
						$current_graphid = CGraph::getId(array('name' => $graph_db['name'], 'hostid' => $current_hostid));

						if(!$current_graphid && !isset($rules['graph']['missed'])) continue; // break if update nonexist
						if($current_graphid && !isset($rules['graph']['exist'])) continue; // break if not update exist
//sdi('graph: '.$graph_db['name'].' | graphID: '. $current_graphid);
						if($current_graphid){ // if exists, delete graph to add then new
							CGraph::delete(array('graphids' => $current_graphid));
						}
//sdii($graph_db);
						$gitems = $graph->xpath('graph_elements/graph_element');

						$gitems_to_add = array();
						foreach($gitems as $gitem){
							$gitem_db = self::mapXML2arr($gitem, XML_TAG_GRAPH_ELEMENT);

							$data = explode(':', $gitem_db['host_key_']);
							$gitem_host = array_shift($data);
							if($gitem_host == '{HOSTNAME}'){
								$gitem_host = $host_db['host'];
							}
							$gitem_key = implode(':', $data);


//sdi('gitem_host: '.$gitem_host.' | gitem_key: '. $gitem_key);

							$itemid = CItem::getId(array('host' => $gitem_host, 'key_' => $gitem_key));
							if($itemid){ // if item exists, add graph item to graph
								$gitem_db['itemid'] = $itemid;
								$graph_db['gitems'][$itemid] = $gitem_db;
							}
						}

						$graphs_to_add[] = $graph_db;
					}
//sdii($graphs_to_add);
					$r = CGraph::add($graphs_to_add);
					if($r === false){
						error(CGraph::resetErrors());
						$result = false;
						break;
					}
				}
			}

			if(!$result) return false;
// DEPENDENCIES
			$dependencies = $xml->xpath('dependencies/dependency');
			if(!empty($dependencies)){
				foreach($dependencies as $dependency){
					$triggers_to_add_dep = array();

					$current_triggerid = get_trigger_by_description($dependency['description']);

//sdi('<b><u>Trigger Description: </u></b>'.$dependency['description'].' | <b>Current_triggerid: </b>'. $current_triggerid['triggerid']);
					if($current_triggerid && isset($triggers_for_dependencies[$current_triggerid['triggerid']])){
						foreach($dependency as $depends_on){
							$depends_triggerid = get_trigger_by_description( (string) $depends_on);;
//sdi('<b>depends on description: </b>'.$depends_on.' | <b>depends_triggerid: </b>'. $depends_triggerid['triggerid']);
							if($depends_triggerid['triggerid']){
								$triggers_to_add_dep[] = $depends_triggerid['triggerid'];
								//CTrigger::addDependency(array('triggerid' => $current_triggerid['triggerid'], 'depends_on_triggerid' => $depends_triggerid['triggerid']));
							}
						}
						$r = update_trigger($current_triggerid['triggerid'],null,null,null,null,null,null,null,$triggers_to_add_dep,null);
						if($r === false){
							$result = false;
							break;
						}
					}
				}
			}

			if(!$result) return false;
			else return true;
		}
	}

}

?>
