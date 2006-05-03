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
	class CTableInfo extends CTable
	{
/* public */
		var $sortby;

		function CTableInfo($message='...',$class='tableinfo')
		{
			parent::CTable($message,$class);
			$this->SetOddRowClass('odd_row');
			$this->SetEvenRowClass('even_row');
			$this->SetCellSpacing(1);
			$this->SetCellPadding(3);
			$this->SetHeader();
			$this->SortBy();
		}
		function SetHeader($value=NULL,$class='header')
		{
			parent::SetHeader($value,$class);
		}
		function SetFooter($value=NULL,$class='footer')
		{
			parent::SetFooter($value,$class);
		}
		function SortBy($value = NULL)
		{
			if(!is_numeric($value) && !is_null($value))
			{
				return $this->error("Incorrect value for SortBy [$value]"); 
			}
			$this->sortby = $value;
		}
		function ShowHeader()
		{
			if(isset($this->header))
			{
				// create a copy of real header
				$header = $this->header;

				if(!is_null($this->sortby)) 
				{
					$i = 1;
					foreach($header->items as $id => $col)
					{
						$sort = new CSpan();
						$down = "_off";
						$up = "_off";

						if(($i-$this->sortby) == 0) $down = "";
						if(($i+$this->sortby) == 0) $up = "";

						$sort->AddItem(array(
							new CImg("images/general/sortup$up.gif"),
							new CImg("images/general/sortdown$down.gif"),
							SPACE));
						$sort->AddOption("style","float:left");

						$header->items[$id]->AddItem($sort);
						$i++;
					}
				}
				$header->Show();
			}
		}
	}
?>
