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
	/* private */ class CVarTag extends CTag
	{
/* public */
		function CVarTag($name="",$value="0")
		{
			parent::CTag('input','no');
			$this->options['type'] = 'hidden';
			$this->options['name'] = $name;
			$this->options['value'] = $value;
		}
		function SetValue($value)
		{ 
			$this->options['value'] = $value;
		}
	}

	/* public */ class CVar
	{
		/*
		var $var_container = array();
		var $var_name;*/

		function CVar($name,$value=null)
		{
			$this->var_container = array();
			$this->var_name = $name;

			$this->SetValue($value);
		}
		
		function SetValue($value)
		{
			$this->var_container = array();

			if(is_null($value)) return;

			$this->ParseValue($this->var_name, $value);
		}

		function ParseValue($name, $value)
		{
			if(is_array($value))
			{
				foreach($value as $itemid => $item)
				{
					if( null == $item ) continue;
					$this->ParseValue($name.'['.$itemid.']', $item);
				}
				return;
			}

			array_push($this->var_container, new CVarTag($name, $value));
		}

		function ToString()
		{
			$res = "";

			foreach($this->var_container as $item)
			{
				$res .= $item->ToString();
			}
			return $res;
		}
	}
?>
