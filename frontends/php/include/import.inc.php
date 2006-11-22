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
	class CZabbixXMLImport
	{
		function CZabbixXMLImport()
		{
			$this->main_node	= null;
			$this->sub_node		= null;
			$this->data		= null;
		}
		
		function StartElement($parser, $name, $attrs) 
		{
			$data = &$this->data[$name];
			
			foreach($attrs as $id => $val)
				$attrs[$id] = html_entity_decode($val);

			switch($name)
			{
				case XML_TAG_HOST:
					$this->main_node= array($name);
					$this->sub_node	= null;
					$data		= $attrs;
					$data['groups'] = array();
					$data['hostid'] = add_host(
						$data['name'],
						10050,
						HOST_STATUS_TEMPLATE,
						'no',
						0,
						array(),
						null,
						array());
					break;
				case XML_TAG_GRAPH:
					$data		= $attrs;
					if(isset($this->data[XML_TAG_HOST]['hostid']) && $this->data[XML_TAG_HOST]['hostid'])
					{
						$this->sub_node	= null;
						array_push($this->main_node, $name);
						$data['graphid'] = add_graph(
							$data['name'],
							$data['width'],
							$data['height'],
							0,0,0,1,1,0);
					}
					else
					{
						error('Graph ['.$data['name'].'] skipped');
					}
					break;
				case XML_TAG_ITEM:
				case XML_TAG_TRIGGER:
				case XML_TAG_GRAPH_ELEMENT:
				/*case XML_TAG_SCREEN:
				case XML_TAG_SCREEN_ELEMENT:*/
					$data		= $attrs;
					$this->sub_node	= null;
					array_push($this->main_node, $name);
					break;
				case XML_TAG_HOSTS:
				case XML_TAG_ZABBIX_EXPORT:
				case XML_TAG_ITEMS:
				case XML_TAG_TRIGGERS:
				case XML_TAG_GRAPHS:
				/* case XML_TAG_SCREENS:*/
					$this->sub_node = null;
					break;
				default:
					$this->sub_node = $name;
					break;
			}
			$this->element_data = '';
		}

		function CharacterData($parser, $data) 
		{
			$this->element_data .= html_entity_decode($data);

		}

		function EndElement($parser, $name) 
		{
			$data = &$this->data[$name];

			switch($name)
			{
				case XML_TAG_HOST:
					if(isset($this->data[XML_TAG_HOST]['hostid']) && $this->data[XML_TAG_HOST]['hostid'])
					{
						if(!isset($data['port']))	$data['port']	= 10050;
						if(!isset($data['status']))	$data['status']	= 0;
						if(!isset($data['ip']))
						{
							$data['useip']	= 'no';
							$data['ip']	= 0;
						}
						else
						{
							$data['useip']	= 'yes';
						}

						update_host(
							$data['hostid'],
							$data['name'],
							$data['port'],
							$data['status'],
							$data['useip'],
							$data['ip'],
							array(),
							null,
							$data['groups']);
					}
					break;
				case XML_TAG_GROUP:
					if(isset($this->data[XML_TAG_HOST]['hostid']) && $this->data[XML_TAG_HOST]['hostid'])
					{
						global $USER_DETAILS;
						global $ZBX_CURNODEID;
						
						if($group = DBfetch(DBselect('select groupid, name from groups'.
							' where '.DBid2nodeid('groupid').'='.$ZBX_CURNODEID.
							' and name='.zbx_dbstr($this->element_data))))
						{
							if(in_array($group["groupid"], 
								get_accessible_groups_by_user(
									$USER_DETAILS,
									PERM_READ_WRITE,
									null,
						                        PERM_RES_IDS_ARRAY,
									$ZBX_CURNODEID)))
							{
								array_push($this->data[XML_TAG_HOST]['groups'], $group["groupid"]);
							}
							else
							{
								error('Skipped group ['.$this->element_data.'] - Access deny.');
							}
						}
						else
						{
							error('Missed group ['.$this->element_data.']');
						}
					}
					break;
				case XML_TAG_ITEM:
					if(isset($this->data[XML_TAG_HOST]['hostid']) && $this->data[XML_TAG_HOST]['hostid'])
					{
						if(!isset($data['description']))		$data['description']		= "";
						if(!isset($data['delay']))			$data['delay']			= 30;
						if(!isset($data['history']))			$data['history']		= 90;
						if(!isset($data['trends']))			$data['trends']			= 365;
						if(!isset($data['status']))			$data['status']			= 0;
						if(!isset($data['units']))			$data['units']			= '';
						if(!isset($data['multiplier']))			$data['multiplier']		= 0;
						if(!isset($data['delta']))			$data['delta']			= 0;
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
						
						$data['realid'] = add_item(
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
							0,
							$data['delay_flex'],
							array());
					}
					else
					{
						error('Item ['.$data['description'].'] skipped');
					}
					break;
				case XML_TAG_TRIGGER:
					if(isset($this->data[XML_TAG_HOST]['hostid']) && $this->data[XML_TAG_HOST]['hostid'])
					{
						if(!isset($data['expression']))		$data['expression']	= '';
						if(!isset($data['description']))	$data['description']	= '';
						if(!isset($data['priority']))		$data['priority']	= 0;
						if(!isset($data['status']))		$data['status']		= 0;
						if(!isset($data['comments']))		$data['comments']	= '';
						if(!isset($data['url']))		$data['url']		= '';

						

						add_trigger(
							str_replace('{HOSTNAME}',$this->data[XML_TAG_HOST]['name'],$data['expression']),
							$data['description'],
							$data['priority'],
							$data['status'],
							$data['comments'],
							$data['url']);
					}
					else
					{
						error('Trigger ['.$data['description'].'] skipped');
					}
					break;
				case XML_TAG_GRAPH:
					if(isset($data['graphid']))
					{
						if(!isset($data['yaxistype']))		$data['yaxistype']		= 0;
						if(!isset($data['show_work_period']))	$data['show_work_period']	= 1;
						if(!isset($data['show_triggers']))	$data['show_triggers']		= 1;
						if(!isset($data['graphtype']))		$data['graphtype']		= 0;
						if(!isset($data['yaxismin']))		$data['yaxismin']		= 0;
						if(!isset($data['yaxismax']))		$data['yaxismax']		= 0;
						
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
							$data['graphtype']);
					}
					break;
				case XML_TAG_GRAPH_ELEMENT:
					if(isset($this->data[XML_TAG_GRAPH]['graphid']))
					{
						$data['key'] = explode(':', $data['item']);
						if(count($data['key']) >= 2)
						{
							$data['host']	= array_shift($data['key']);
							$data['key']	= implode(':', $data['key']);
						}
						else
						{
							error('Incorrect element for graph ['.$data['name'].']');
							break;
						}

						if(isset($this->data[XML_TAG_HOST]['name']))
						{
							$data['host'] = str_replace('{HOSTNAME}',$this->data[XML_TAG_HOST]['name'],$data['host']);
						}

						if(!isset($data['drawtype']))		$data['drawtype']	= 0;
						if(!isset($data['sortorder']))		$data['sortorder']	= 0;
						if(!isset($data['color']))		$data['color']		= 'Dark Green';
						if(!isset($data['yaxisside']))		$data['yaxisside']	= 1;
						if(!isset($data['calc_fnc']))		$data['calc_fnc']	= 2;
						if(!isset($data['type']))		$data['type']		= 0;
						if(!isset($data['periods_cnt']))	$data['periods_cnt']	= 5;

						if($item = DBfetch(DBselect('select i.itemid from items i,hosts h'.
							' where h.hostid=i.hostid and i.key_='.zbx_dbstr($data['key']).
							' and h.host='.zbx_dbstr($data['host']))))
						{
							add_item_to_graph(
								$this->data[XML_TAG_GRAPH]['graphid'],
								$item['itemid'],
								$data['color'],
								$data['drawtype'],
								$data['sortorder'],
								$data['yaxisside'],
								$data['calc_fnc'],
								$data['type'],
								$data['periods_cnt']);
						}
						else
						{
							error('Missed item ['.$data['key'].'] for host ['.$data['host'].']');
							break;
						}
					}
					break;
				/*case XML_TAG_SCREEN:
				case XML_TAG_SCREEN_ELEMENT:
					break;*/
				default:
					if(isset($this->sub_node) && isset($this->main_node))
					{
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

		function Parse($file)
		{
			$this->main_node	= null;
			$this->sub_node		= null;
			$this->data		= null;
			
			$xml_parser = xml_parser_create();
			
			xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
			
			xml_set_element_handler($xml_parser, array(&$this, "StartElement"), array(&$this, "EndElement"));
			
			xml_set_character_data_handler($xml_parser, array(&$this, "characterData"));

			if (!($fp = fopen($file, "r")))
			{
				error("could not open XML input");
				xml_parser_free($xml_parser);
				return false;
			}
			else
			{
				while ($data = fread($fp, 4096))
				{
					if (!xml_parse($xml_parser, $data, feof($fp)))
					{
						error(	sprintf("XML error: %s at line %d",
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
	}

	class CZabbixHostImport
	{
		function CZabbixHostImport()
		{
		}
		
		function Parse($file)
		{
			if (!($fp = fopen($file, "r")))
			{
				error("could not open XML input");
				xml_parser_free($xml_parser);
				return false;
			}
			else
			{
				while(!feof($fp))
				{
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
