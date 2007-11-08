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
	function destroy_objects()
	{
		global $GLOBALS;

		if(isset($GLOBALS)) foreach($GLOBALS as $name => $value)
		{
			if(!is_object($GLOBALS[$name])) continue;
			unset($GLOBALS[$name]);
		}
	}
	
	function unpack_object(&$item)
	{
		$res = "";

		if(is_object($item))
		{
			$res = $item->ToString();
		}
		elseif(is_array($item))
		{
			foreach($item as $id => $dat)	
				$res .= unpack_object($item[$id]); // Attention, recursion !!!
		}
		elseif(!is_null($item))
		{
			$res = strval($item);
			unset($item);
		}
		return $res;
	}

	function implode_objects($glue, &$pieces)
	{
		if( !is_array($pieces) )	return unpack_object($pieces);

		foreach($pieces as $id => $piece)
			$pieces[$id] = unpack_object($piece);

		return implode($glue, $pieces);
	}

	class CObject
	{
		function CObject($items=null)
		{
			$this->items = array();
			if(isset($items))
			{
				$this->AddItems($items);
			}
		}
		
		function ToString($destroy=true)
		{
			$res = implode('',$this->items);
			if($destroy) $this->Destroy();
			return $res;
		}

		function Show($destroy=true)	{	echo $this->ToString($destroy);			}

		function Destroy()
		{
### TODO Problem under PHP 5.0  "Fatal error: Cannot re-assign $this in ..."
#			$this = null;
			$this->CleanItems();
		}

		function CleanItems()		{	$this->items = array();				}
		function ItemsCount()		{	return count($this->items);			}
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
	}

	class CTag extends CObject
	{
/* private *//*
		var $tagname;
		var $options = array();
		var $paired;*/
/* protected *//*
		var $items = array();

		var $tag_body_start;
		var $tag_body_end;
		var $tag_start;
		var $tag_end;*/

/* public */
		function CTag($tagname=NULL, $paired='no', $body=NULL, $class=null)
		{
			parent::CObject();

			$this->options = array();

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

			$this->SetClass($class);

		}
		function ShowStart()	{	echo $this->StartToString();	}
		function ShowBody()	{	echo $this->BodyToString();	}
		function ShowEnd()	{	echo $this->EndToString();	}

		function StartToString()
		{
			$res = $this->tag_start.'<'.$this->tagname;
			foreach($this->options as $key => $value)
			{
				$res .= ' '.$key.'="'.$value.'"';
			}
			$res .= ($this->paired=='yes') ? '>' : '/>';
			return $res;
		}
		function BodyToString()
		{
			$res = $this->tag_body_start;
			return $res.parent::ToString(false);
			
			/*foreach($this->items as $item)
				$res .= $item;
			return $res;*/
		}
		function EndToString()
		{
			$res = ($this->paired=='yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
			$res .= $this->tag_end;
			return $res;
		}
		function ToString($destroy=true)
		{
			$res  = $this->StartToString();
			$res .= $this->BodyToString();
			$res .= $this->EndToString();

			if($destroy) $this->Destroy();

			return $res;
		}
		function SetName($value)
		{
			if(is_null($value)) return $value;

			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetName [$value]");
			}
			return $this->AddOption("name",$value);
		}
		function GetName()
		{
			if(isset($this->options['name']))
				return $this->options['name'];
			return NULL;
		}
		function SetClass($value)		
		{
			if(isset($value))
				$this->options['class'] = $value;
			else
				unset($this->options['class']);

			return $value;
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

		function SetHint($text, $width='', $class='')
		{
			if(empty($text)) return false;

			insert_showhint_javascript();

			$text = unpack_object($text);
			if($width != '' || $class!= '')
			{
				$code = "show_hint_ext(this,event,'".$text."','".$width."','".$class."');";
			}
			else
			{
				$code = "show_hint(this,event,'".$text."');";
			}

			$this->AddAction('onMouseOver',	$code);
			$this->AddAction('onMouseMove',	'update_hint(this,event);');
		}

		function OnClick($handle_code)
		{
			$this->AddAction('onClick', $handle_code);
		}

		function AddAction($name, $value)
		{
			if(!empty($value))
				$this->options[$name] = htmlentities(str_replace(array("\r", "\n"), '', strval($value)),ENT_COMPAT,S_HTML_CHARSET); 
		}

		function AddOption($name, $value)
		{
			if(isset($value))
				$this->options[$name] = htmlspecialchars(strval($value)); 
			else
				unset($this->options[$name]);
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
