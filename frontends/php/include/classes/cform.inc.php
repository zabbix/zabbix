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
	class CForm extends CTag
	{
/* public */
		function CForm($action=NULL, $method='get', $enctype=NULL)
		{
			parent::CTag("form","yes");
			$this->SetMethod($method);
			$this->SetAction($action);
			$this->SetEnctype($enctype);
		}
		function SetMethod($value='post')
		{
			return $this->options['method'] = $value;
		}
		function SetAction($value)
		{
			global $page;

			if(is_null($value))
			{
				if(isset($page['file']))
				{
					$value = $page['file'];
				}
				else
				{
					$value = "#";
				}
			}
			return $this->options['action'] = $value;
		}
		function SetEnctype($value=NULL)
		{
			if(is_null($value)){
				return $this->DelOption("enctype");
			}elseif(!is_string($value)){
				return $this->error("Incorrect value for SetEnctype [$value]");
			}
			return $this->AddOption("enctype",$value);
		}

		function AddVar($name, $value)
		{
			if(empty($value) && $value != 0)	return $value;

			return $this->AddItem(new CVar($name, $value));
		}
	}
?>
