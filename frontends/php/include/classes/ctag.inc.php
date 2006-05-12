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
	function unpack_object(&$item)
	{
		$res = "";

		if(is_object($item))
		{
			$res = $item->ToString();
		}
		elseif(is_array($item))
		{
			foreach($item as $i)	
				$res .= unpack_object($i); // Attention, recursion !!!
		}
		elseif(!is_null($item))
		{
			$res = strval($item);
		}
		return $res;
	}

	class CTag
	{
/* private */
		var $tagname;
		var $options = array();
		var $paired;
/* protected */
		var $items = array();

		var $tag_body_start;
		var $tag_body_end;
		var $tag_start;
		var $tag_end;

/* public */
		function CTag($tagname=NULL, $paired='no', $body=NULL)
		{
			if(!is_string($tagname))
			{
				return $this->error('Incorrect tagname for CTag ['.$tagname.']');
			}
			$this->tagname = $tagname;
			$this->paired = $paired;

			$this->tag_start = $this->tag_end = $this->tag_body_start = $this->tag_body_end = '';

			if(is_null($body))
			{
				$this->tag_end = $this->tag_body_start = "\n";
			}
			else
			{
				CTag::AddItem($body);
			}

		}
		function ShowStart()	{	echo $this->StartToString();	}
		function ShowBody()	{	echo $this->BodyToString();	}
		function ShowEnd()	{	echo $this->EndToString();	}
		function Show()		{	echo $this->ToString();		}

		function StartToString()
		{
			$res = $this->tag_start.'<'.$this->tagname;
			foreach($this->options as $key => $value)
				$res .= ' '.$key.'="'.$value.'"';
			$res .= ($this->paired=='yes') ? '>' : '/>';
			return $res;
		}
		function BodyToString()
		{
			$res = $this->tag_body_start;
			foreach($this->items as $item)
				$res .= $item;
			return $res;
		}
		function EndToString()
		{
			$res = ($this->paired=='yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
			$res .= $this->tag_end;
			return $res;
		}
		function ToString()
		{
			$res  = $this->StartToString();
			$res .= $this->BodyToString();
			$res .= $this->EndToString();
			return $res;
		}
		function SetName($value)
		{
			$this->options['name'] = $value;
		}
		function GetName()
		{
			if(isset($this->options['name']))
				return $this->options['name'];
			return NULL;
		}
		function SetClass($value)		
		{
			return $this->options['class'] = $value;
		}
		function DelOption($name)
		{
			unset($this->options[$name]);
		}
		function &GetOption($name)
		{
			$ret = NULL;
			if(isset($this->options[$name]))
				$ret =& $this->options[$name];
			return $ret;
		}
		function AddOption($name, $value)
		{
			$this->options[$name] = htmlspecialchars(strval($value)); 
		}
		function CleanItems()
		{
			$this->items = array();
		}
		function ItemsCount()
		{
			return count($this->items);
		}
		function AddItem($value)
		{
			if(is_array($value))
			{
				foreach($value as $item)
				{
					array_push($this->items,unpack_object($item));
				}
			}
			elseif(!is_null($value))
			{
				array_push($this->items,unpack_object($value));
			}
		}
		function SetEnabled($value='yes')
		{
			if((is_string($value) && ($value == 'yes' || $value == 'enabled' || $value=='on') || $value=='1')
			|| (is_int($value) && $value<>0))
			{
				unset($this->options['disabled']);
			}
			elseif((is_string($value) && ($value == 'no' || $value == 'disabled' || $value=='off') || $value=='0')
			|| (is_int($value) && $value==0))
			{
				$this->options['disabled'] = 'disabled';
			}
		}
		function error($value)
		{
			error('class('.get_class($this).') - '.$value);
			return 1;
		}

	}
?>
