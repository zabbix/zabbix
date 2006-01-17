<?php
/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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
	function	check_var($var,$checks)
	{
		global	$_REQUEST;

		$ret = 1;

		foreach($checks as $field=>$check)
		{
			if(is_int($key))
			{
				$op=$check[0];
				$val=$check[$op];

				echo "ZZZ";
				echo isset($check["min"]);
			}
			else
			{
				if(isset($_REQUEST[$var]))
				{
					if(($check == T_ZBX_INT)&&(!is_int($_REQUEST[$var])))
						break;
					if( ($check == T_ZBX_FLOAT)&&(!is_float($_REQUEST[$var])))
						break;
					if($check == T_ZBX_PERIOD)
						break;
					if( ($check == V_NOT_EMPTY)&&($_REQUEST[$var]==""))
						break;
				}
			}
		}

		return $ret;
	}

	function	check_fields($fields)
	{
		global	$_REQUEST;

		$ret = 1;

		foreach($fields as $field => $checks)
		{
			list($type,$opt,$table,$field,$validation,$exception)=$checks;
		}
		return $ret;
	}
?>
