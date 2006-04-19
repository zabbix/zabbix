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
	class CTag
	{
/* private */
		var $name;
		var $options = array();
		var $paired;
/* protected */
		var $items = array();
		var $items_max_count;

		var $tag_body_start;
		var $tag_body_end;
		var $tag_start;
		var $tag_end;

/* public */
		function CTag($name=NULL, $paired='no', $body=NULL)
		{
			$this->SetTagName($name);
			$this->SetPaired($paired);
			$this->SetMaxLength(0);

			$this->tag_start=$this->tag_end=$this->tag_body_start=$this->tag_body_end= "";

			if(is_null($body)) $this->tag_end = "\n";
			if(is_null($body)) $this->tag_body_start = "\n";

			CTag::AddItem($body);

		}
		function SetMaxLength($value)
		{
			if(!is_int($value))
				return $this->error("Incorrect value for SetMaxLength [$value]");

			$this->items_max_count = $value;
			return 0;
		}
		function Show()
		{
			$this->ShowTagStart();
			$this->ShowTagBody();
			$this->ShowTagEnd();
		}
		function SetTagName($value=NULL)
		{ 
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetTagName [$value]");
			}	
			$this->name=$value; 
			return 0;
		}
		function SetName($value=NULL)
		{
			if(is_null($value))
				return $this->DelOption("name");;

			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetClass [$value]");
			}
			return $this->AddOption("name",$value);
		}
		function GetName()
		{
			return $this->GetOption("name");
		}
		function SetClass($value)		
		{
			if(is_null($value))
				return 0;

			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetClass [$value]");
			}
			return $this->AddOption("class",$value);
		}
		function SetPaired($value='no')		
		{
			if($value == 'no')	$this->paired=$value;
			elseif($value == 'yes')	$this->paired=$value;
			else
			{
				return $this->error("Incorrect value for SetPaired [$value]");
			}
			return 0;
		}
		function DelOption($name)
		{
			if(!is_string($name))
			{
				return $this->error("Incorrect value for DelOption [$value]");
			}
			unset($this->options[$name]);
			return 0; 
		}
		function &GetOption($name)
		{
			$ret = NULL;
			if(is_string($name))
				if(isset($this->options[$name]))
					$ret =& $this->options[$name];
			return $ret;
		}
		function AddOption($name, $value)
		{
			if(!is_string($name))
			{
				return $this->error("Incorrect name for AddOption [$name]");
			}
			if(!is_string($value) && !is_int($value) && !is_float($value))
			{
				return $this->error("Incorrect value for AddOption [$name] [$value]");
			}
			
			$this->options[$name] = htmlspecialchars(strval($value)); 
			return 0;
		}
		function CleanItems()
		{
			$this->items = array();
		}
		function GetItemsCount()
		{
			return count($this->items);
		}
		function AddItem($value)
		{
			if(is_null($value))
			{
				return 1;
			}
			elseif(is_array($value))
			{
				foreach($value as $item)
				{
					if($this->items_max_count > 0)
						if(count($this->items) >= $this->items_max_count)
							return $this->error("Maximal tag lenght '".$this->items_max_count."' is achived");
					array_push($this->items,$item);
				}
			}
			else
			{
				if($this->items_max_count > 0)
					if(count($this->items) >= $this->items_max_count)
						return $this->error("Maximal tag lenght '".$this->items_max_count."' is achived");

				array_push($this->items,$value);
			}
			return 0;
		}
/* protected */
		function ShowTagStart()
		{
			echo $this->tag_start;
			echo "<".$this->name;
			foreach($this->options as $key => $value)
			{
				echo " $key=\"$value\"";
			}

			if($this->paired=='yes')
				echo ">";
			else	
				echo "/>";

			echo $this->tag_body_start;
		}
		function ShowTagItem(&$item)
		{
			if(is_null($item))	return;
			elseif(is_object($item))$item->Show();
			else			echo strval($item);
		}
		function ShowTagBody()
		{
			foreach($this->items as $item)
				$this->ShowTagItem($item);
		}
		function ShowTagEnd()
		{
			echo $this->tag_body_end;

			if($this->paired=='yes')
			{
				echo "</".$this->name.">";
				echo $this->tag_end;
			}
		}
		function SetEnabled($value='yes')
		{
			if(is_null($value))
				return 0;
			elseif((is_string($value) && 
					($value == 'yes' || $value == 'enabled' || $value=='on') || $value=='1')
				|| (is_int($value) && $value<>0))
				return $this->DelOption('disabled');
			elseif((is_string($value) && 
					($value == 'no' || $value == 'disabled' || $value=='off') || $value=='0')
				|| (is_int($value) && $value==0))
				return $this->AddOption('disabled','disabled');
			return $this->error("Incorrect value for SetEnabled [$value]");
		}
		function error($value)
		{
			error("class(".get_class($this).") - ".$value);
			return 1;
		}

	}
?>
