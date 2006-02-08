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
	# Add Autoregistration rule

	function	add_autoregistration($pattern,$priority,$hostid)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="insert into autoreg (pattern,priority,hostid) values (".zbx_dbstr($pattern).",$priority,$hostid)";
		$result=DBexecute($sql);
		if($result)
		{
			$host=get_host_by_hostid($hostid);
			info("Added new autoregistration rule for $pattern");
		}
		return DBinsert_id($result,"autoreg","id");
	}

	# Update Autoregistration rule

	function	update_autoregistration($id,$pattern,$priority,$hostid)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
                        error("Insufficient permissions");
                        return 0;
		}

		$sql="update autoreg set pattern=".zbx_dbstr($pattern).",priority=$priority,hostid=$hostid where id=$id";

		return	DBexecute($sql);
	}

	# Delete Autoregistartion rule

	function	delete_autoregistration($id)
	{
		if(!check_right("Configuration of Zabbix","U",0))
		{
                        error("Insufficient permissions");
                        return 0;
		}

		$sql="delete from autoreg where id=$id";

		return	DBexecute($sql);
	}

?>
