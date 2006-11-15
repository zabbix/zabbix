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
		function CListBox($name='combobox',$value=NULL,$size=5,$action=NULL)
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

?>
