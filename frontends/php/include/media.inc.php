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
?>
<?php

	function	media_severity2str($severity)
	{

		insert_showhint_javascript();
		$mapping = array(
			0 => array('letter' => 'N', 'style' => (($severity & 1)  ? 'enabled' : NULL)),
			1 => array('letter' => 'I', 'style' => (($severity & 2)  ? 'enabled' : NULL)),
			2 => array('letter' => 'W', 'style' => (($severity & 4)  ? 'enabled' : NULL)),
			3 => array('letter' => 'A', 'style' => (($severity & 8)  ? 'enabled' : NULL)),
			4 => array('letter' => 'H', 'style' => (($severity & 16) ? 'enabled' : NULL)),
			5 => array('letter' => 'D', 'style' => (($severity & 32) ? 'enabled' : NULL))
		);

		foreach($mapping as $id => $map)
		{
			$result[$id] = new CSpan($map['letter'], $map['style']);
			$result[$id]->options['onMouseOver'] = 
				"show_hint(this,'".get_severity_description($id)." (".(isset($map['style']) ? "on" : "off").")"."');";
		}

		return unpack_object($result);
	}

	function	get_media_by_mediaid($mediaid)
	{
		$sql="select * from media where mediaid=$mediaid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		else
		{
			error("No media with mediaid=[$mediaid]");
		}
		return	$result;
	}
?>
