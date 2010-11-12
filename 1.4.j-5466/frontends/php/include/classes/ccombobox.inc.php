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
	require_once("include/classes/ctag.inc.php");

	class CComboItem extends CTag
	{
/* public */
		function CComboItem($value,$caption=NULL,$selected=NULL, $enabled=NULL)
		{
			parent::CTag('option','yes');
			$this->tag_body_start = "";
			$this->options['value'] = $value;

			$this->AddItem($caption);

			$this->SetSelected($selected);
			$this->SetEnabled($enabled);

		}
		function SetValue($value)
		{
			return $this->options['value'] = $value;
		}
		function GetValue()
		{
			return $this->GetOption('value');
		}
		function SetCaption($value=NULL)
		{
			$this->AddItem(nbsp($value));
		}
		function SetSelected($value='yes')
		{
			if((is_string($value) && ($value == 'yes' || $value == "selected" || $value=='on'))
				|| (is_int($value) && $value<>0))
				return $this->options['selected'] = 'selected';

			$this->DelOption('selected');
		}
	}

	class CComboBox extends CTag
	{
/* private */
		//var $value;

/* public */
		function CComboBox($name='combobox',$value=NULL,$action=NULL)
		{
			parent::CTag('select','yes');
			$this->tag_end = '';
			$this->options['class'] = 'biginput';
			$this->options['name'] = $name;
			$this->value = $value;
			$this->options['size'] = 1;
			$this->SetAction($action);
		}
		function SetAction($value='submit()', $event='onChange')
		{
			$this->AddOption($event,$value);
		}
		function SetValue($value=NULL)
		{
			$this->value = $value;
		}
		function AddItem($value, $caption='', $selected=NULL, $enabled='yes')
		{
//			if($enabled=='no') return;	/* disable item method 1 */

			if(is_null($selected))
			{
				$selected = 'no';
				if($value == $this->value || (is_array($this->value) && in_array($value, $this->value)))
					$selected = 'yes';
			}

			parent::AddItem(new CComboItem($value,$caption,$selected,$enabled));
		}
	}

	class CListBox extends CComboBox
	{
/* public */
		function CListBox($name='listbox',$value=NULL,$size=5,$action=NULL)
		{
			parent::CComboBox($name,NULL,$action);
			$this->options['multiple'] = 'multiple';
			$this->options['size'] = $size;
			$this->SetValue($value);
		}
		function SetSize($value)
		{
			$this->options['size'] = $value;
		}
	}

	function	inseret_javascript_for_editable_combobox()
	{
		if(defined('EDITABLE_COMBOBOX_SCRIPT_INSERTTED')) return;

		define('EDITABLE_COMBOBOX_SCRIPT_INSERTTED', 1);
?>
<script language="JavaScript" type="text/javascript">
<!--
		function CEditableComboBoxInit(obj)
		{
			var opt = obj.options;

			if(obj.value) obj.oldValue = obj.value;

			for (var i = 0; i < opt.length; i++)
				if (-1 == opt.item(i).value)
					return;

			opt = document.createElement("option");
			opt.value = -1;
			opt.text = "(other ...)";

			if(!obj.options.add)
				obj.insertBefore(opt, obj.options.item(0));
			else
				obj.options.add(opt, 0);

			return;
		}

		function CEditableComboBoxOnChange(obj,size)
		{
			if(-1 != obj.value)
			{
				obj.oldValue = obj.value;
			}
			else
			{
				var new_obj = document.createElement("input");
				new_obj.type = "text";
				new_obj.name = obj.name;
				if(size && size > 0)
				{
					new_obj.size = size;
				}
				new_obj.className = obj.className;
				if(obj.oldValue) new_obj.value = obj.oldValue;
				obj.parentNode.replaceChild(new_obj, obj);
				new_obj.focus();
				new_obj.select();
			}
		}
-->
</script>
<?php
	}

	class CEditableComboBox extends CComboBox
	{
		function CEditableComboBox($name='editablecombobox',$value=NULL,$size=0,$action=NULL)
		{
			inseret_javascript_for_editable_combobox();

			parent::CComboBox($name,$value,$action);
			parent::AddAction('onfocus','CEditableComboBoxInit(this);');
			parent::AddAction('onchange','CEditableComboBoxOnChange(this,'.$size.');');
		}

		function AddItem($value, $caption='', $selected=NULL, $enabled='yes')
		{
			if(is_null($selected))
			{
				if($value == $this->value || (is_array($this->value) && in_array($value, $this->value)))
					$this->value_exist = 1;
			}

			parent::AddItem($value,$caption,$selected,$enabled);
		}

		function ToString($destroy=true)
		{
			if(!isset($this->value_exist) && !empty($this->value))
			{
				$this->AddItem($this->value, $this->value, 'yes');
			}
			return parent::ToString($destroy);
		}
	}
?>
