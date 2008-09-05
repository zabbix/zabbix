<?php 
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/defines.inc.php";
	include_once "include/hosts.inc.php";
?>
<?php
	class CZabbixXMLImport{
		function CZabbixXMLImport(){
			global $USER_DETAILS;

			$this->main_node= null;
			$this->sub_node	= null;
			$this->data	= null;
			$this->host	= array('exist' => 0, 'missed' => 0);
			$this->template	= array('exist' => 0, 'missed' => 0);
			$this->item	= array('exist' => 0, 'missed' => 0);
			$this->trigger	= array('exist' => 0, 'missed' => 0);
			$this->graph	= array('exist' => 0, 'missed' => 0);

			$this->available_groups = get_accessible_groups_by_user($USER_DETAILS, PERM_READ_WRITE);

			$this->available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);
				
			$this->available_nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY);
		}
		
		function CharacterData($parser, $data) {
			$this->element_data .= html_entity_decode($data);
		}
		
		function StartElement($parser, $name, $attrs) {
			$this->element_data = '';

			if(!isset($this->root)){
				if($name == XML_TAG_ZABBIX_EXPORT)
					if(isset($attrs['version']))
						if($attrs['version'] == '1.0'){
							$this->root = true;
							return;
						}
						else{
							error(S_UNSUPPORTED_VERSION_OF_IMPORTED_DATA);
						}
				error(S_UNSUPPORTED_FILE_FORMAT);
				$this->root = false;
			}
			else if(!$this->root){
				return false;
			}

			$data = &$this->data[$name];
			
			foreach($attrs as $id => $val)
				$attrs[$id] = html_entity_decode($val);

			switch($name){
				case XML_TAG_HOST:
					$this->main_node= array($name);
					$this->sub_node	= null;
					$data		= $attrs;
					$data['groups'] = array();
					$data['skip']	= false;
					
					if($host_data = DBfetch(DBselect('SELECT hostid FROM hosts'.
						' WHERE host='.zbx_dbstr($data['name']).
							' AND '.DBin_node('hostid',get_current_nodeid(false)))))
					{ /* exist */
						if($this->host['exist']==1) /* skip */{
							$data['skip'] = true;
							info('Host ['.$data['name'].'] skipped - user rule');
							break; // case
						}
						if(!uint_in_array($host_data['hostid'], $this->available_hosts)){
							error('Host ['.$data['name'].'] skipped - Access deny.');
							break; // case
						}

						$data['hostid']		= $host_data['hostid'];
						$data['templates']	= get_templates_by_hostid($host_data['hostid']);
						$data['groups']		= get_groupids_by_host($host_data['hostid']);
					}
					else{ /* missed */
						if($this->host['missed']==1){ /* skip */
							$data['skip'] = true;
							info('Host ['.$data['name'].'] skipped - user rule');
							break; // case
						}
						
						if(!uint_in_array(get_current_nodeid(),$this->available_nodes)){
							error('Host ['.$data['name'].'] skipped - Access deny.');
							break; // case
						}

						$data['templates'] = array();
						$data['hostid'] = add_host(
							$data['name'],
							10050,
							HOST_STATUS_TEMPLATE,
							0, /* useip */
							'', /* dns */
							'', /* ip */
							0, /* proxy_hostid */
							array(),
							null,
							array());
							
					}
					break; // case
				case XML_TAG_GRAPH:
					$data		= $attrs;
					$data['items']	= array();
					$this->sub_node	= null;
					array_push($this->main_node, $name);
					break; // case
				case XML_TAG_DEPENDENCY:
					// checks if trigger has been skipped
					if(str_in_array($attrs['description'], $this->data[XML_TAG_DEPENDENCIES]['skip'])){
						info('Trigger ['.$attrs['description'].'] dependency update skipped - user rule');
						break;
					}
					
					// searches trigger by host name & trigger description
					if(!$trigger_down = get_trigger_by_description($attrs['description'])){
						error('Trigger ['.$attrs['description'].'] dependency update skipped - trigger not found');
						break;
					}
					
					$data['triggerid_down'] = $trigger_down['triggerid'];
					$data['triggerid_up']	= array();
					$this->sub_node	= null;
					array_push($this->main_node, $name);
					break;
				case XML_TAG_HOSTPROFILE:
				case XML_TAG_HOSTPROFILE_EXT:
				case XML_TAG_TEMPLATE:
				case XML_TAG_ITEM:
				case XML_TAG_TRIGGER:
				case XML_TAG_DEPENDS:
				case XML_TAG_GRAPH_ELEMENT:
				/*case XML_TAG_SCREEN:
				case XML_TAG_SCREEN_ELEMENT:*/
					$data		= $attrs;
					$this->sub_node	= null;
					array_push($this->main_node, $name);
					break; // case
				case XML_TAG_HOSTS:
					$this->data[XML_TAG_DEPENDENCIES]['skip'] = array();
					break;
				case XML_TAG_DEPENDENCIES:
				case XML_TAG_ZABBIX_EXPORT:
				case XML_TAG_GROUPS:
				case XML_TAG_APPLICATIONS:
				case XML_TAG_TEMPLATES:
				case XML_TAG_ITEMS:
				case XML_TAG_TRIGGERS:
				case XML_TAG_GRAPHS:
				/* case XML_TAG_SCREENS:*/
					$this->sub_node = null;
					break; // case
				case XML_TAG_GROUP:
				case XML_TAG_APPLICATION:
				default:
					$this->sub_node = $name;
					break; // case
			}
		}

		function EndElement($parser, $name) {
			if(!$this->root){
				return false;
			}

			global $USER_DETAILS;
			
			$data = &$this->data[$name];
			switch($name){
				case XML_TAG_HOST:
					if($data['skip'] || !isset($data['hostid']) || !$data['hostid'])
						break; // case
					
					if(!isset($data['port']))	$data['port']	= 10050;
					if(!isset($data['status']))	$data['status']	= 0;
					if(!isset($data['useip']))	$data['useip'] = 0;
					if(!isset($data['dns']))	$data['dns'] = '';
					if(!isset($data['ip']))		$data['ip'] = '';

					if(update_host($data['hostid'], $data['name'], $data['port'], $data['status'],
						$data['useip'], $data['dns'], $data['ip'], 0, $data['templates'], null, $data['groups']))
					{
						info('Host ['.$data['name'].'] updated');
					}
					
					break; // case
// based on  mod by scricca	
				case XML_TAG_HOSTPROFILE:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid'])
						break; //case

					if(!isset($data['devicetype']))		$data['devicetype'] = '';
					if(!isset($data['name']))			$data['name'] = '';
					if(!isset($data['os']))				$data['os'] = '';
					if(!isset($data['serialno']))		$data['serialno'] = '';
					if(!isset($data['tag']))			$data['tag'] = '';
					if(!isset($data['macaddress']))		$data['macaddress'] = '';
					if(!isset($data['hardware']))		$data['hardware'] = '';
					if(!isset($data['software']))		$data['software'] = '';
					if(!isset($data['contact']))		$data['contact'] = '';
					if(!isset($data['location']))		$data['location'] = '';
					if(!isset($data['notes']))			$data['notes'] = '';
					
					delete_host_profile($this->data[XML_TAG_HOST]['hostid']);
					
					if(add_host_profile($this->data[XML_TAG_HOST]['hostid'], $data['devicetype'], $data['name'], $data['os'],
						$data['serialno'], $data['tag'], $data['macaddress'], $data['hardware'], $data['software'],
						$data['contact'], $data['location'], $data['notes']))
					{
						info('Host Profile ['.$this->data[XML_TAG_HOST]['name'].'] updated');
					}
 					
 					break; // case
//---
// Extended profiles
				case XML_TAG_HOSTPROFILE_EXT:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid'])
						break; //case

					if(!isset($data['device_alias'])) $data['device_alias'] = '';
					if(!isset($data['device_type'])) $data['device_type'] = '';
					if(!isset($data['device_chassis'])) $data['device_chassis'] = '';
					if(!isset($data['device_os'])) $data['device_os'] = '';
					if(!isset($data['device_os_short'])) $data['device_os_short'] = '';
					
					if(!isset($data['device_hw_arch'])) $data['device_hw_arch'] = '';
					if(!isset($data['device_serial'])) $data['device_serial'] = '';
					if(!isset($data['device_model'])) $data['device_model'] = '';
					if(!isset($data['device_tag'])) $data['device_tag'] = '';
					if(!isset($data['device_vendor'])) $data['device_vendor'] = '';
					if(!isset($data['device_contract'])) $data['device_contract'] = '';
					
					if(!isset($data['device_who'])) $data['device_who'] = '';
					if(!isset($data['device_status'])) $data['device_status'] = '';
					if(!isset($data['device_app_01'])) $data['device_app_01'] = '';
					if(!isset($data['device_app_02'])) $data['device_app_02'] = '';
					if(!isset($data['device_app_03'])) $data['device_app_03'] = '';
					if(!isset($data['device_app_04'])) $data['device_app_04'] = '';
					
					if(!isset($data['device_app_05'])) $data['device_app_05'] = '';
					if(!isset($data['device_url_1'])) $data['device_url_1'] = '';
					if(!isset($data['device_url_2'])) $data['device_url_2'] = '';
					if(!isset($data['device_url_3'])) $data['device_url_3'] = '';
					if(!isset($data['device_networks'])) $data['device_networks'] = '';
					if(!isset($data['device_notes'])) $data['device_notes'] = '';
					
					if(!isset($data['device_hardware'])) $data['device_hardware'] = '';
					if(!isset($data['device_software'])) $data['device_software'] = '';
					if(!isset($data['ip_subnet_mask'])) $data['ip_subnet_mask'] = '';
					if(!isset($data['ip_router'])) $data['ip_router'] = '';
					if(!isset($data['ip_macaddress'])) $data['ip_macaddress'] = '';
					if(!isset($data['oob_ip'])) $data['oob_ip'] = '';
					
					if(!isset($data['oob_subnet_mask'])) $data['oob_subnet_mask'] = '';
					if(!isset($data['oob_router'])) $data['oob_router'] = '';
					if(!isset($data['date_hw_buy'])) $data['date_hw_buy'] = '';
					if(!isset($data['date_hw_install'])) $data['date_hw_install'] = '';
					if(!isset($data['date_hw_expiry'])) $data['date_hw_expiry'] = '';
					if(!isset($data['date_hw_decomm'])) $data['date_hw_decomm'] = '';
					if(!isset($data['site_street_1'])) $data['site_street_1'] = '';
					
					if(!isset($data['site_street_2'])) $data['site_street_2'] = '';
					if(!isset($data['site_street_3'])) $data['site_street_3'] = '';
					if(!isset($data['site_city'])) $data['site_city'] = '';
					if(!isset($data['site_state'])) $data['site_state'] = '';
					if(!isset($data['site_country'])) $data['site_country'] = '';
					if(!isset($data['site_zip'])) $data['site_zip'] = '';
					if(!isset($data['site_rack'])) $data['site_rack'] = '';
					if(!isset($data['site_notes'])) $data['site_notes'] = '';
					
					if(!isset($data['poc_1_name'])) $data['poc_1_name'] = '';
					if(!isset($data['poc_1_email'])) $data['poc_1_email'] = '';
					if(!isset($data['poc_1_phone_1'])) $data['poc_1_phone_1'] = '';
					if(!isset($data['poc_1_phone_2'])) $data['poc_1_phone_2'] = '';
					if(!isset($data['poc_1_cell'])) $data['poc_1_cell'] = '';
					if(!isset($data['poc_1_screen'])) $data['poc_1_screen'] = '';
					if(!isset($data['poc_1_notes'])) $data['poc_1_notes'] = '';
					if(!isset($data['poc_2_name'])) $data['poc_2_name'] = '';
					
					if(!isset($data['poc_2_email'])) $data['poc_2_email'] = '';
					if(!isset($data['poc_2_phone_1'])) $data['poc_2_phone_1'] = '';
					if(!isset($data['poc_2_phone_2'])) $data['poc_2_phone_2'] = '';
					if(!isset($data['poc_2_cell'])) $data['poc_2_cell'] = '';
					if(!isset($data['poc_2_screen'])) $data['poc_2_screen'] = '';
					if(!isset($data['poc_2_notes'])) $data['poc_2_notes'] = '';

					
					delete_host_profile_ext($this->data[XML_TAG_HOST]['hostid']);
					
					if(add_host_profile_ext($this->data[XML_TAG_HOST]['hostid'], $data)){
						info('Host Extended Profile ['.$this->data[XML_TAG_HOST]['name'].'] updated');
					}
 					
 					break; // case
//---
				case XML_TAG_GROUP:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid'])
						break; //case
						
					$sql = 'SELECT groupid, name '.
							' FROM groups'.
							' WHERE '.DBin_node('groupid',get_current_nodeid(false)).
								' AND name='.zbx_dbstr($this->element_data);
								
					if(!$group = DBfetch(DBselect($sql))){
						error('Missed group ['.$this->element_data.']');
						break; // case
					}
					
					if(!uint_in_array($group["groupid"], $this->available_groups)){
						error('Group ['.$this->element_data.'] skipped - Access deny.');
						break; // case
					}

					array_push($this->data[XML_TAG_HOST]['groups'], $group["groupid"]);

					break; // case
				case XML_TAG_DEPENDENCY:
					if(!isset($data['triggerid_down']) || !$data['triggerid_down'])
						break; // case
					
					update_trigger($data['triggerid_down'],
							null,null,null,
							null,null,null,null,
							$data['triggerid_up'], null);
												
					break; // case
				case XML_TAG_DEPENDS:
					if(!isset($this->data[XML_TAG_DEPENDENCY]['triggerid_down']) || !$this->data[XML_TAG_DEPENDENCY]['triggerid_down'])
						break; //case
					
					if(!$trigger_up = get_trigger_by_description($this->element_data)) break;
					
					array_push($this->data[XML_TAG_DEPENDENCY]['triggerid_up'], $trigger_up['triggerid']);

					break; // case
				case XML_TAG_APPLICATION:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid'])
						break; //case

					if(!isset($this->data[XML_TAG_ITEM]))
						break; //case

					$sql= 'SELECT applicationid '.
						' FROM applications'.
						' WHERE '.DBin_node('applicationid',get_current_nodeid(false)).
							' AND name='.zbx_dbstr($this->element_data).
							' AND hostid='.$this->data[XML_TAG_HOST]['hostid'];
					if(!$application = DBfetch(DBselect($sql))){
						$applicationid = add_application($this->element_data, $this->data[XML_TAG_HOST]['hostid']);
					}
					else{
						$applicationid = $application['applicationid'];
					}

					$this->data[XML_TAG_ITEM]['applications'][] = $applicationid;

					break; // case
				case XML_TAG_TEMPLATE:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid'])
						break; //case

					$sql= 'SELECT DISTINCT host, hostid '.
								' FROM hosts'.
								' WHERE '.DBin_node('hostid').
									' AND host='.zbx_dbstr($this->element_data);
					if(!$template = DBfetch(DBselect($sql))){
						error('Missed template ['.$this->element_data.']');
						break; // case
					}
					
					if(!uint_in_array($template["hostid"], $this->available_hosts)){
						error('Template ['.$this->element_data.'] skipped - Access deny.');
						break; // case
					}

					$this->data[XML_TAG_HOST]['templates'][$template["hostid"]] = $template['host'];
					break; // case
				case XML_TAG_ITEM:
					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid']){
						if(isset($this->data[XML_TAG_HOST]['skip']) && $this->data[XML_TAG_HOST]['skip']){
							info('Item ['.$data['description'].'] skipped - user rule for host');
							break; // case
						}

						error('Item ['.$data['description'].'] skipped - missed host');
						break; // case
					}

					if(!isset($data['description']))		$data['description']		= '';
					if(!isset($data['delay']))				$data['delay']			= 30;
					if(!isset($data['history']))			$data['history']		= 90;
					if(!isset($data['trends']))				$data['trends']			= 365;
					if(!isset($data['status']))				$data['status']			= 0;
					if(!isset($data['units']))				$data['units']			= '';
					if(!isset($data['multiplier']))			$data['multiplier']		= 0;
					if(!isset($data['delta']))				$data['delta']			= 0;
					if(!isset($data['formula']))			$data['formula']		= '';
					if(!isset($data['lastlogsize']))		$data['lastlogsize']		= 0;
					if(!isset($data['logtimefmt']))			$data['logtimefmt']		= '';
					if(!isset($data['delay_flex']))			$data['delay_flex']		= '';
					if(!isset($data['trapper_hosts']))		$data['trapper_hosts']		= '';
					if(!isset($data['snmp_community']))		$data['snmp_community']		= '';
					if(!isset($data['snmp_oid']))			$data['snmp_oid']		= '';
					if(!isset($data['snmp_port']))			$data['snmp_port']		= 161;
					if(!isset($data['snmpv3_securityname']))	$data['snmpv3_securityname']	= '';
					if(!isset($data['snmpv3_securitylevel']))	$data['snmpv3_securitylevel']	= 0;
					if(!isset($data['snmpv3_authpassphrase']))	$data['snmpv3_authpassphrase']	= '';
					if(!isset($data['snmpv3_privpassphrase']))	$data['snmpv3_privpassphrase']	= '';
					if(!isset($data['valuemap']))				$data['valuemap']		= '';
					if(!isset($data['params']))					$data['params']			= '';
					if(!isset($data['applications']))			$data['applications']		= array();

					if(!empty($data['valuemap'])){
						$sql = 'SELECT valuemapid '.
								' FROM valuemaps '.
								' WHERE '.DBin_node('valuemapid', get_current_nodeid(false)).
									' AND name='.zbx_dbstr($data['valuemap']);
										
						if( $valuemap = DBfetch(DBselect($sql))){
							$data['valuemapid'] = $valuemap['valuemapid'];
						}
						else{
							$data['valuemapid'] = add_valuemap($data['valuemap'],array());
						}
					}
					
					$sql = 'SELECT itemid,valuemapid,templateid '.
							' FROM items '.
							' WHERE key_='.zbx_dbstr($data['key']).
								' AND hostid='.$this->data[XML_TAG_HOST]['hostid'].
								' AND '.DBin_node('itemid', get_current_nodeid(false));
						
					if($item = DBfetch(DBselect($sql))){ /* exist */
						if($this->item['exist']==1) /* skip */{
							info('Item ['.$data['description'].'] skipped - user rule');
							break;
						}

						if(!isset($data['valuemapid']))
							$data['valuemapid'] = $item['valuemapid'];

						update_item(
							$item['itemid'],
							$data['description'],
							$data['key'],
							$this->data[XML_TAG_HOST]['hostid'],
							$data['delay'],
							$data['history'],
							$data['status'],
							$data['type'],
							$data['snmp_community'],
							$data['snmp_oid'],
							$data['value_type'],
							$data['trapper_hosts'],
							$data['snmp_port'],
							$data['units'],
							$data['multiplier'],
							$data['delta'],
							$data['snmpv3_securityname'],
							$data['snmpv3_securitylevel'],
							$data['snmpv3_authpassphrase'],
							$data['snmpv3_privpassphrase'],
							$data['formula'],
							$data['trends'],
							$data['logtimefmt'],
							$data['valuemapid'],
							$data['delay_flex'],
							$data['params'],
							array_unique(array_merge(
								$data['applications'],
								get_applications_by_itemid($item['itemid'])
								)),
							$item['templateid']);
					}
					else{ /* missed */
						if($this->item['missed']==1) /* skip */{
							info('Item ['.$data['description'].'] skipped - user rule');
							break; // case
						}

						if( !isset($data['valuemapid']) )
							$data['valuemapid'] = 0;

						add_item(
							$data['description'],
							$data['key'],
							$this->data[XML_TAG_HOST]['hostid'],
							$data['delay'],
							$data['history'],
							$data['status'],
							$data['type'],
							$data['snmp_community'],
							$data['snmp_oid'],
							$data['value_type'],
							$data['trapper_hosts'],
							$data['snmp_port'],
							$data['units'],
							$data['multiplier'],
							$data['delta'],
							$data['snmpv3_securityname'],
							$data['snmpv3_securitylevel'],
							$data['snmpv3_authpassphrase'],
							$data['snmpv3_privpassphrase'],
							$data['formula'],
							$data['trends'],
							$data['logtimefmt'],
							$data['valuemapid'],
							$data['delay_flex'],
							$data['params'],
							$data['applications']);
					}

					break; // case
				case XML_TAG_TRIGGER:
					if(!isset($data['expression']))		$data['expression']	= '';
					if(!isset($data['description']))	$data['description']	= '';
					if(!isset($data['type']))			$data['type']	= 0;
					if(!isset($data['priority']))		$data['priority']	= 0;
					if(!isset($data['status']))		$data['status']		= 0;
					if(!isset($data['comments']))		$data['comments']	= '';
					if(!isset($data['url']))		$data['url']		= '';

					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid']){
						if(isset($this->data[XML_TAG_HOST]['skip']) && $this->data[XML_TAG_HOST]['skip']){
						
// remember skipped triggers for dependencies
							$this->data[XML_TAG_DEPENDENCIES]['skip'][] = $this->data[XML_TAG_HOST]['name'].':'.$data['description'];
							
							info('Trigger ['.$data['description'].'] skipped - user rule for host');
							break; // case
						}
						if(zbx_strstr($data['expression'],'{HOSTNAME}')){
						
// remember skipped triggers for dependencies
							$this->data[XML_TAG_DEPENDENCIES]['skip'][] = $this->data[XML_TAG_HOST]['name'].':'.$data['description'];

							error('Trigger ['.$data['description'].'] skipped - missed host');
							break; // case
						}
					}
					else{
						$data['expression'] = str_replace('{HOSTNAME}',$this->data[XML_TAG_HOST]['name'],$data['expression']);
 
						$result = DBselect('SELECT DISTINCT t.triggerid,t.templateid,t.expression '.
 							' FROM triggers t,functions f,items i '.
 							' WHERE t.triggerid=f.triggerid '.
								' AND f.itemid=i.itemid'.
	 							' AND i.hostid='.$this->data[XML_TAG_HOST]['hostid'].
								' AND t.description='.zbx_dbstr($data['description']));

						while($trigger = DBfetch($result)){
							if(explode_exp($trigger['expression'],0) == $data['expression']){
								break; // while
							}
						}

						if(!empty($trigger)){ /* exist */
							if($this->trigger['exist']==1){ /* skip */
							
// remember skipped triggers for dependencies
								$this->data[XML_TAG_DEPENDENCIES]['skip'][] = $this->data[XML_TAG_HOST]['name'].':'.$data['description'];

								info('Trigger ['.$data['description'].'] skipped - user rule');
								break; // case
							}

							update_trigger(
								$trigger['triggerid'],
								$data['expression'],
								$data['description'],
								$data['type'],
								$data['priority'],
								$data['status'],
								$data['comments'],
								$data['url'],
								get_trigger_dependencies_by_triggerid($trigger['triggerid']),
								$trigger['templateid']);

							break; // case
						}
						else{ /* missed */
							// continue [add_trigger]
						}
					}
					
					if($this->trigger['missed']==1) /* skip */{
					
// remember skipped triggers for dependencies
						$this->data[XML_TAG_DEPENDENCIES]['skip'][] = $this->data[XML_TAG_HOST]['name'].':'.$data['description'];

						info('Trigger ['.$data['description'].'] skipped - user rule');
						break; // case
					}

					add_trigger(
						$data['expression'],
						$data['description'],
						$data['type'],
						$data['priority'],
						$data['status'],
						$data['comments'],
						$data['url']);

					break; // case
				case XML_TAG_GRAPH:
					if(isset($data['error'])){
						error('Graph ['.$data['name'].'] skipped - error occured');
						break; // case
					}

					if(!isset($data['yaxistype']))			$data['yaxistype']		= 0;
					if(!isset($data['show_work_period']))	$data['show_work_period']	= 1;
					if(!isset($data['show_triggers']))		$data['show_triggers']		= 1;
					if(!isset($data['graphtype']))			$data['graphtype']		= 0;
					if(!isset($data['yaxismin']))			$data['yaxismin']		= 0;
					if(!isset($data['yaxismax']))			$data['yaxismax']		= 0;
					if(!isset($data['show_legend']))		$data['show_legend']	= 0;
					if(!isset($data['show_3d']))			$data['show_3d']		= 0;
					if(!isset($data['percent_left']))		$data['percent_left']		= 0;
					if(!isset($data['percent_right']))		$data['percent_right']		= 0;
					if(!isset($data['items']))				$data['items']			= array();

					if(!isset($this->data[XML_TAG_HOST]['hostid']) || !$this->data[XML_TAG_HOST]['hostid']){
						if(isset($this->data[XML_TAG_HOST]['skip']) && $this->data[XML_TAG_HOST]['skip']){
							info('Graph ['.$data['name'].'] skipped - user rule for host');
							break; // case
						}
						foreach($data['items'] as $id)

						if(zbx_strstr($data['name'],'{HOSTNAME}')){
							error('Graph ['.$data['name'].'] skipped - missed host');
							break; // case
						}
					}
					else{
						if($graph = DBfetch(DBselect('SELECT DISTINCT g.graphid, g.templateid'.
							' FROM graphs g, graphs_items gi, items i'.
							' WHERE g.graphid=gi.graphid '.
								' AND gi.itemid=i.itemid'.
								' AND g.name='.zbx_dbstr($data['name']).
								' AND i.hostid='.$this->data[XML_TAG_HOST]['hostid'])))
						{ /* exist */
							if($this->graph['exist']==1){ /* skip */
								info('Graph ['.$data['name'].'] skipped - user rule');
								break; // case
							}

							$data['graphid'] = $graph['graphid'];

							update_graph(
								$data['graphid'],
								$data['name'],
								$data['width'],
								$data['height'],
								$data['yaxistype'],
								$data['yaxismin'],
								$data['yaxismax'],
								$data['show_work_period'],
								$data['show_triggers'],
								$data['graphtype'],
								$data['show_legend'],
								$data['show_3d'],
								$data['percent_left'],
								$data['percent_right'],
								$graph['templateid']);
							DBexecute('DELETE FROM graphs_items WHERE graphid='.$data['graphid']);
						}
						else{ /* missed */
							// continue [add_group]
						}
					}
					
					if(!isset($data['graphid'])){
						if($this->graph['missed']==1){ /* skip */
							info('Graph ['.$data['name'].'] skipped - user rule');
							break; // case
						}

						$data['graphid'] = add_graph(
							$data['name'],
							$data['width'],
							$data['height'],
							$data['yaxistype'],
							$data['yaxismin'],
							$data['yaxismax'],
							$data['show_work_period'],
							$data['show_triggers'],
							$data['graphtype'],
							$data['show_legend'],
							$data['show_3d'],
							$data['percent_left'],
							$data['percent_right']
							);
					}

					foreach($data['items'] as $item){
						add_item_to_graph(
							$data['graphid'],
							$item['itemid'],
							$item['color'],
							$item['drawtype'],
							$item['sortorder'],
							$item['yaxisside'],
							$item['calc_fnc'],
							$item['type'],
							$item['periods_cnt']);
					}
					break; // case
				case XML_TAG_GRAPH_ELEMENT:
					if(!isset($this->data[XML_TAG_GRAPH]))
						break; // case
						
					$data['key'] = explode(':', $data['item']);
					if(count($data['key']) < 2){
						$this->data[XML_TAG_GRAPH]['error'] = true;
						error('Incorrect element for graph ['.$data['name'].']');
						break; // case
					}

					$data['host']	= array_shift($data['key']);
					$data['key']	= implode(':', $data['key']);

					if(isset($this->data[XML_TAG_HOST]['name'])){
						$data['host'] = str_replace('{HOSTNAME}',$this->data[XML_TAG_HOST]['name'],$data['host']);
					}

					if(!isset($data['drawtype']))		$data['drawtype']	= 0;
					if(!isset($data['sortorder']))		$data['sortorder']	= 0;
					if(!isset($data['color']))		$data['color']		= 'Dark Green';
					if(!isset($data['yaxisside']))		$data['yaxisside']	= 1;
					if(!isset($data['calc_fnc']))		$data['calc_fnc']	= 2;
					if(!isset($data['type']))		$data['type']		= 0;
					if(!isset($data['periods_cnt']))	$data['periods_cnt']	= 5;

					if(!($item = DBfetch(DBselect('select i.itemid from items i,hosts h'.
						' where h.hostid=i.hostid and i.key_='.zbx_dbstr($data['key']).
						' and h.host='.zbx_dbstr($data['host'])))))
					{
						$this->data[XML_TAG_GRAPH]['error'] = true;

						error('Missed item ['.$data['key'].'] for host ['.$data['host'].']');
						break; // case
					}

					$data['itemid'] = $item['itemid'];

					array_push($this->data[XML_TAG_GRAPH]['items'], $data);

					break; // case
				/*case XML_TAG_SCREEN:
				case XML_TAG_SCREEN_ELEMENT:
					break; // case*/
				default:
					if(isset($this->sub_node) && isset($this->main_node)){
						$main_node = array_pop($this->main_node);
						$this->data[$main_node][$this->sub_node] = $this->element_data;
						array_push($this->main_node, $main_node);
					}
					$this->sub_node = null;
					return;
			}
			
			unset($this->data[$name], $data);
			
			array_pop($this->main_node);
		}

		function Parse($file){
			$this->main_node	= null;
			$this->sub_node		= null;
			$this->data		= null;
			
			$xml_parser = xml_parser_create();
			
			xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
			
			xml_set_element_handler($xml_parser, array(&$this, "StartElement"), array(&$this, "EndElement"));
			
			xml_set_character_data_handler($xml_parser, array(&$this, "characterData"));

			if(!$fp = fopen($file, "r")){
				error("could not open XML input");
				xml_parser_free($xml_parser);
				return false;
			}
			else{
				while($data = fread($fp, 4096)){
					if(!xml_parse($xml_parser, $data, feof($fp))){
						error(sprintf("XML error: %s at line %d",
							xml_error_string(xml_get_error_code($xml_parser)),
							xml_get_current_line_number($xml_parser))
							);
						fclose($fp);
						xml_parser_free($xml_parser);
						return false;
					}
				}
				fclose($fp);
			}
			xml_parser_free($xml_parser);
			
			$this->main_node	= null;
			$this->sub_node		= null;
			$this->data		= null;

			return true;
		}

		function SetRules($host, $template, $item, $trigger, $graph){
			$this->host	= $host;
			$this->template = $template;
			$this->item	= $item;
			$this->trigger	= $trigger;
			$this->graph	= $graph;
		}
	}

	class CZabbixHostImport{
		function CZabbixHostImport(){
		}
		
		function Parse($file){
			if (!($fp = fopen($file, "r"))){
				error("could not open XML input");
				xml_parser_free($xml_parser);
				return false;
			}
			else{
				while(!feof($fp)){
					$len = fgets($fp);
					echo $len.'<br/>'."\n";
				}
				fclose($fp);
			}

			info('Underconstructor!');
			return true;
		}
	}
?>
