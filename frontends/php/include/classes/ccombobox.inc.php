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
		function CComboItem($value,$caption=NULL,$selected='no')
		{
			parent::CTag('option','yes');
			$this->tag_body_start = "";
			$this->SetCaption($caption);
			$this->SetValue($value);
			$this->SetSelected($selected);
		}
		function SetValue($value)
		{
			return parent::AddOption('value',$value);
		}
		function SetCaption($value=NULL)
		{
			if(is_null($value))
				return 0;
			elseif(is_string($value)){
				parent::AddItem(nbsp($value));
				return 0;
			}
			return $this->error("Incorrect value for SetCaption [$value]");
		}
		function SetEnable($value='yes')
		{
			if($value == 'yes' || $value == 'enabled' || $value=='on')
				return $this->DelOption('disabled');
			elseif($value == 'no' || $value == 'disabled' || $value=='off' || $value == NULL)
				return $this->AddOption('disabled','disabled');
			return $this->error("Incorrect value for SetEnable [$value]");
		}
		function SetSelected($value='yes')
		{
			if($value == 'yes' || $value == "selected" || $value=='on')
				return $this->AddOption('selected','selected');
			elseif($value == 'no' || $value=='off' || $value == NULL)
				return $this->DelOption('selected');
			return $this->error("Incorrect value for SetSelected [$value]");
		}
	}

	class CComboBox extends CTag
	{
/* private */
		var $caption;
		var $value;

/* public */
		function CComboBox($name='combobox',$value=NULL,$action=NULL)
		{
			parent::CTag("select","yes");
			$this->tag_end = "";
			$this->SetClass("biginput");
			$this->SetName($name);
			$this->SetValue($value);
			$this->AddOption("size",1);
			$this->SetAction($action);
		}
		function SetAction($value='submit()', $event='onChange')
		{
			if(is_null($value))
				return 1;
			if(!is_string($value))
				return $this->error("Incorrect value for SetAction [$value]");
			if(!is_string($event))
				return $this->error("Incorrect event for SetAction [$event]");
			return $this->AddOption($event,$value);
		}
		function SetName($value='combobox')
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetName [$value]");
			}
			return $this->AddOption("name",$value);
		}
		function SetCaption($value=NULL)
		{
			if(is_null($value))
				unset($this->caption);
			elseif(is_string($value))	
				$this->caption = $value;
			else
			{
				return $this->error("Incorrect value for SetCaption [$value]");
			}
			return 0;
		}
		function SetValue($value=NULL)
		{
			$this->value = $value;
		}
		function AddItem($value, $caption, $selected=NULL, $enabled='yes')
		{
			if(is_null($selected))
			{
				$selected = 'no'; 
				if(!is_null($this->value))
					if($this->value==$value)
						$selected = 'yes';
			}

//			if($enabled=='no') return;	/* disable item method 1 */

			$cmbItem = new CComboItem($value,$caption,$selected);

			$cmbItem->SetEnable($enabled);	/* disable item method 2 */

			return parent::AddItem($cmbItem);
		}
		function Show()
		{
			if(isset($this->caption))
				print ($this->caption." ");
			parent::Show();
		}
	}
?>
