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
	function	bold($str)
	{
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val))
					 $str[$key] = "<b>$val</b>";
		} elseif(is_string($str)) {
			$str = "<b>$str</b>";
		}
		return $str;
	}

	function	bfirst($str) // mark first symbol of string as bold
	{
		$res = bold($str[0]);
		for($i=1,$max=strlen($str); $i<$max; $i++)	$res .= $str[$i];
		$str = $res;
		return $str;	
	}

	function	nbsp($str)
	{
		return str_replace(" ",SPACE,$str);
	}

	function url1_param($parameter)
	{
		if(isset($_REQUEST[$parameter]))
		{
			return "$parameter=".$_REQUEST[$parameter];
		}
		else
		{
			return "";
		}
	}

	function	prepare_url(&$var, $varname)
	{
		$result = "";

		if(is_array($var))
		{
			foreach($var as $id => $par)
				$result .= prepare_url($par,
					isset($varname) ? $varname."[".$id."]": $id
					);
		}
		else
		{
			$result = "&".$varname."=".urlencode($var);
		}
		return $result;
	}

	function url_param($parameter,$request=true,$name=null)
	{
		$result = "";

		
		if(!is_array($parameter))
		{
			if(!isset($name))
			{
				if(!$request)
					fatal_error('not request variable require url name [url_param]');

				$name = $parameter;
			}
		}
		
		if($request)
		{
			global $_REQUEST;
			
			$var =& $_REQUEST[$parameter];
		}
		else
		{
			$var =& $parameter;
		}
		
		if(isset($var))
		{
			$result = prepare_url($var,$name);
		}
		return $result;
	}
?>
