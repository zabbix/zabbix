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
	class CListItem extends CTag
	{
/* public */
		function CListItem($value)
		{
			parent::CTag('li','yes');

			$this->AddItem($value);
		}
	}

	class CList extends CTag
	{
/* public */
		function CList($value=NULL,$class=NULL)
		{
			parent::CTag('ul','yes');
			$this->tag_end = '';
			$this->AddItem($value);
			$this->SetClass($class);
		}
		function PrepareItem($value=NULL)
		{
			if(!is_null($value))
			{
				$value = new CListItem($value);
			}
			return $value;
		}
		
		function AddItem($value)
		{
			if(is_array($value))
			{
				foreach($value as $el)
					parent::AddItem($this->PrepareItem($el));
			}
			else
			{
				parent::AddItem($this->PrepareItem($value));
			}
		}
	}

?>
