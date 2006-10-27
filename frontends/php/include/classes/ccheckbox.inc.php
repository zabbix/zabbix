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
	class CCheckBox extends CTag
	{
/* public */
		function CCheckBox($name='checkbox',$checked='no',$action=NULL,$value='yes')
		{
			parent::CTag('input','no');
			$this->tag_body_start = '';
			$this->options['type'] = 'checkbox';
			$this->options['value'] = $value;
			$this->options['name'] = $name;
			$this->options['onClick'] = $action;
			$this->SetChecked($checked);
		}
		function SetEnabled($value='yes')
		{
			if($value=='yes' || $value == true || $value === 1)
				return $this->DelOption('disabled');

			return $this->options['disabled'] = 'yes';
		}
		function SetChecked($value="yes")
		{
			if((is_string($value)&& ($value=="yes" || $value=="checked" || $value=="on") || $value=="1")
			|| (is_int($value)&&$value<>0))
				return $this->options['checked'] = 'checked';

			$this->DelOption("checked");
		}
		function SetAction($value='submit()', $event='onClick')
		{
			$this->options[$event] = $value;
		}
	}
?>
