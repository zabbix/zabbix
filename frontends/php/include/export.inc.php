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
?>
<?php
	global $ZBX_EXPORT_MAP;
	
	$ZBX_EXPORT_MAP = array(
		XML_TAG_HOST => array(
			'attribures'	=> array(
				'host'		=> 'name'),
			'elements'	=> array(
				'useip'		=> '',
				'ip'		=> '',
				'port'		=> '',
				'status'	=> '')
			),
		XML_TAG_ITEM => array(
			'attribures'	=> array(
				'type'			=> '',
				'key_'			=> 'key',
				'value_type'		=> ''),
			'elements'	=> array(
				'description'		=> '',
				'delay'			=> '',
				'history'		=> '',
				'trends'		=> '',
				'status'		=> '',
				'units'			=> '',
				'multiplier'		=> '',
				'delta'			=> '',
				'formula'		=> '',
				'lastlogsize'		=> '',
				'logtimefmt'		=> '',
				'delay_flex'		=> '',
				'trapper_hosts'		=> '',
				'snmp_community'	=> '',
				'snmp_oid'		=> '',
				'snmp_port'		=> '',
				'snmpv3_securityname'	=> '',
				'snmpv3_securitylevel'	=> '',
				'snmpv3_authpassphrase'	=> '',
				'snmpv3_privpassphrase'	=> '')
			),
		XML_TAG_TRIGGER => array(
			'attribures'	=> array(),
			'elements'	=> array(
				'description'		=> '',
				'expression'		=> '',
				'url'			=> '',
				'status'		=> '',
				'priority'		=> '',
				'comments'		=> '')
			),
		XML_TAG_GRAPH => array(
			'attribures'	=> array(
				'name'			=> '',
				'width'			=> '',
				'height'		=> ''),
			'elements'	=> array(
				'yaxistype'		=> '',
				'show_work_period'	=> '',
				'show_triggers'		=> '',
				'graphtype'		=> '',
				'yaxismin'		=> '',
				'yaxismax'		=> '')
			),
		XML_TAG_GRAPH_ELEMENT => array(
			'attribures'	=> array(
				'item'	=> ''),
			'elements'	=> array(
				'drawtype'	=> '',
				'sortorder'	=> '',
				'color'		=> '',
				'yaxisside'	=> '',
				'calc_fnc'	=> '',
				'type'		=> '',
				'periods_cnt'	=> '')
			)
		);

	function zbx_xmlwriter_open_memory()
	{
		return array('tabs' => 0, 'tag'=>array());
	}
	
	function zbx_xmlwriter_set_indent($mem, $val)
	{
		return true;
	}
	
	function zbx_xmlwriter_start_document(&$mem, $ver)
	{
		echo '<?xml version="'.$ver.'"?';
		return true;
	}
	
	function zbx_xmlwriter_start_element(&$mem, $tag)
	{
		array_push($mem['tag'], $tag);
		echo '>'."\n".str_repeat("\t",$mem['tabs']).'<'.$tag;
		$mem['tabs']++;
		return true;
	}
	function zbx_xmlwriter_write_attribute(&$mem, $name, $val)
	{
		echo ' '.$name.'="'.htmlspecialchars($val).'"';
		return true;
	}
	
	function zbx_xmlwriter_end_element(&$mem)
	{
		$teg = array_pop($mem['tag']);
		$mem['tabs']--;
		echo '>'."\n".str_repeat("\t",$mem['tabs']).'</'.$teg;
	}
	
	function zbx_xmlwriter_output_memory(&$mem, $val)
	{ /* NOTE: use this function only in the end of xml file creation */
		echo '>';
	}
	
	function zbx_xmlwriter_write_element(&$mem, $name, $val)
	{
		echo '>'."\n".str_repeat("\t",$mem['tabs']).'<'.$name.'>'.htmlspecialchars($val).'</'.$name;
	}
	
	class CZabbixXMLExport
	{
		function CZabbixXMLExport(){}
		
		function export_item(&$memory, $itemid)
		{
			global $ZBX_EXPORT_MAP;

			$data = DBfetch(DBselect('select * from items where itemid='.$itemid));
			if(!$data) return false;
			
			zbx_xmlwriter_start_element ($memory,XML_TAG_ITEM);

			$map =& $ZBX_EXPORT_MAP[XML_TAG_ITEM];
			
			foreach($map['attribures'] as $db_name => $xml_name)
			{
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_attribute($memory, $xml_name, $data[$db_name]);
			}
			foreach($map['elements'] as $db_name => $xml_name)
			{
				if(empty($data[$db_name])) continue;
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_element ($memory, $xml_name, $data[$db_name]);
			}
			zbx_xmlwriter_end_element($memory); // XML_TAG_ITEM
		}
		
		function export_trigger(&$memory, $triggerid)
		{
			global $ZBX_EXPORT_MAP;

			$data = DBfetch(DBselect('select * from triggers where triggerid='.$triggerid));
			if(!$data) return false;

			$data['expression'] = explode_exp($data["expression"],0,true);
			
			zbx_xmlwriter_start_element ($memory,XML_TAG_TRIGGER);
			
			$map =& $ZBX_EXPORT_MAP[XML_TAG_TRIGGER];
			
			foreach($map['attribures'] as $db_name => $xml_name)
			{
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_attribute($memory, $xml_name, $data[$db_name]);
			}
			foreach($map['elements'] as $db_name => $xml_name)
			{
				if(empty($data[$db_name])) continue;
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_element ($memory, $xml_name, $data[$db_name]);
			}
			zbx_xmlwriter_end_element($memory); // XML_TAG_TRIGGER
		}
		
		function export_graph_element(&$memory, $gitemid)
		{
			global $ZBX_EXPORT_MAP;

			$data = DBfetch(DBselect('select gi.*,i.key_,h.host from graphs_items gi, items i, hosts h'.
				' where h.hostid=i.hostid and i.itemid=gi.itemid and gi.gitemid='.$gitemid));
			if(!$data) return false;

			$data['item'] = '{HOSTNAME}:'.$data['key_'];
			
			zbx_xmlwriter_start_element ($memory,XML_TAG_GRAPH_ELEMENT);
			
			$map =& $ZBX_EXPORT_MAP[XML_TAG_GRAPH_ELEMENT];
			
			foreach($map['attribures'] as $db_name => $xml_name)
			{
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_attribute($memory, $xml_name, $data[$db_name]);
			}
			foreach($map['elements'] as $db_name => $xml_name)
			{
				if(empty($data[$db_name])) continue;
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_element ($memory, $xml_name, $data[$db_name]);
			}
			zbx_xmlwriter_end_element($memory); // XML_TAG_GRAPH_ELEMENT
		}

		function export_graph(&$memory, $graphid)
		{
			global $ZBX_EXPORT_MAP;

			$data = DBfetch(DBselect('select * from graphs where graphid='.$graphid));
			if(!$data) return false;
			
			zbx_xmlwriter_start_element ($memory,XML_TAG_GRAPH);
			
			$map =& $ZBX_EXPORT_MAP[XML_TAG_GRAPH];
			
			foreach($map['attribures'] as $db_name => $xml_name)
			{
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_attribute($memory, $xml_name, $data[$db_name]);
			}
			foreach($map['elements'] as $db_name => $xml_name)
			{
				if(empty($data[$db_name])) continue;
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_element ($memory, $xml_name, $data[$db_name]);
			}
			zbx_xmlwriter_start_element ($memory,XML_TAG_GRAPH_ELEMENTS);
			$db_elements = DBselect('select gitemid from graphs_items where graphid='.$graphid);
			while($element = DBfetch($db_elements))
			{
				$this->export_graph_element($memory, $element['gitemid']);
			}
				
			zbx_xmlwriter_end_element($memory); // XML_TAG_GRAPH_ELEMENTS
			zbx_xmlwriter_end_element($memory); // XML_TAG_GRAPH
		}
		
		function export_host(&$memory, $hostid, $export_items, $export_triggers, $export_graphs)
		{
			global $ZBX_EXPORT_MAP;

			$data = DBfetch(DBselect('select * from hosts where hostid='.$hostid));
			if(!$data) return false;
			
			zbx_xmlwriter_start_element ($memory,XML_TAG_HOST);
			
			$map =& $ZBX_EXPORT_MAP[XML_TAG_HOST];

			foreach($map['attribures'] as $db_name => $xml_name)
			{
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_attribute($memory, $xml_name, $data[$db_name]);
			}
			foreach($map['elements'] as $db_name => $xml_name)
			{
				if(empty($data[$db_name])) continue;
				if(empty($xml_name)) $xml_name = $db_name;
				zbx_xmlwriter_write_element ($memory, $xml_name, $data[$db_name]);
			}
				
			if($db_groups = DBselect('select g.name from groups g, hosts_groups hg'.
				' where g.groupid=hg.groupid and hg.hostid='.$hostid))
			{
				zbx_xmlwriter_start_element ($memory,XML_TAG_GROUPS);
				while($group = DBfetch($db_groups))
				{
					zbx_xmlwriter_write_element ($memory, XML_TAG_GROUP, $group['name']);
				}
				zbx_xmlwriter_end_element($memory); // XML_TAG_GROUP
			}

			if($export_items)
			{
				zbx_xmlwriter_start_element ($memory,XML_TAG_ITEMS);
				$db_items=DBselect('select itemid from items where hostid='.$hostid);
				while($item_id = DBfetch($db_items))
				{
					$this->export_item($memory, $item_id['itemid']);
				}
				zbx_xmlwriter_end_element($memory); // XML_TAG_ITEMS
			}
			if($export_triggers)
			{
				zbx_xmlwriter_start_element ($memory,XML_TAG_TRIGGERS);
				$db_triggers = DBselect('select f.triggerid, i.hostid, count(distinct i.hostid) as cnt '.
					' from functions f, items i '.
					' where f.itemid=i.itemid group by f.triggerid');
				while($trigger = DBfetch($db_triggers))
				{
					if($trigger['hostid'] != $hostid || $trigger['cnt']!=1) continue;
					$this->export_trigger($memory, $trigger['triggerid']);
				}
				zbx_xmlwriter_end_element($memory); // XML_TAG_TRIGGERS
			}
			if($export_graphs)
			{
				zbx_xmlwriter_start_element ($memory, XML_TAG_GRAPHS);
				$db_graphs = DBselect('select gi.graphid, i.hostid, count(distinct i.hostid) as cnt '.
					' from graphs_items gi, items i '.
					' where gi.itemid=i.itemid group by gi.graphid');
				while($graph = DBfetch($db_graphs))
				{
					if($graph['hostid'] != $hostid || $graph['cnt']!=1) continue;
					$this->export_graph($memory, $graph['graphid']);
				}
				zbx_xmlwriter_end_element($memory); // XML_TAG_GRAPHS
			}
			/* export screens */
			zbx_xmlwriter_end_element($memory); // XML_TAG_HOST
			return true;
		}

		function Export(&$hosts, &$items, &$triggers, &$graphs)
		{
			$memory = zbx_xmlwriter_open_memory();
			zbx_xmlwriter_set_indent($memory, true);
			zbx_xmlwriter_start_document($memory,'1.0');
			zbx_xmlwriter_start_element ($memory,XML_TAG_ZABBIX_EXPORT);
			zbx_xmlwriter_write_attribute($memory, 'version', '1.0');
			zbx_xmlwriter_write_attribute($memory, 'date', date('d.m.y'));
			zbx_xmlwriter_write_attribute($memory, 'time', date('h.i'));
				zbx_xmlwriter_start_element ($memory,XML_TAG_HOSTS);
				foreach(array_keys($hosts) as $hostid)
				{
					$this->export_host(
						$memory,
						$hostid,
						isset($items[$hostid]),
						isset($triggers[$hostid]),
						isset($graphs[$hostid])
						);
				}
				zbx_xmlwriter_end_element($memory); // XML_TAG_HOSTS
			zbx_xmlwriter_end_element($memory); // XML_TAG_ZABBIX_EXPORT
			die(zbx_xmlwriter_output_memory($memory,true));
		}
	}
?>
