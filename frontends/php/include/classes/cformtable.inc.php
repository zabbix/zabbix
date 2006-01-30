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
	class CFormTable extends CForm
	{
/* private */
		var $align;
		var $title;
		var $help;
/* protected */
		var $top_items = array();
		var $center_items = array();
		var $bottom_items = array();
/* public */
		function CFormTable($title=NULL, $action=NULL, $method='get', $enctype=NULL)
		{
			global  $_REQUEST;

			parent::CForm($action,$method,$enctype);
			$this->SetTitle($title);
			$this->SetAlign('center');
			$this->SetHelp();

			$this->AddItemToTopRow("<a name=\"form\"></a>");
			
			$this->AddVar("form","1");

			$this->bottom_items = new CCol(NULL,'form_row_last');
		        $this->bottom_items->SetColSpan(2);
		}
		function SetAction($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]");
			}
			parent::SetAction($value."#form");
		}
		function SetAlign($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]");
			}
			return $this->align = $value;
		}
		function SetTitle($value=NULL)
		{
			if(is_null($value))
			{
				unset($this->title);
				return 0;
			}
			elseif(!is_string($value))
			{
				return $this->error("Incorrect value for SetTitle [$value]");
			}
			$this->title = nbsp($value);
		}
		function SetHelp($value=NULL)
		{
			if(is_null($value)) {
				$this->help = new CHelp();
				$this->AddOption("name",'form');
			} elseif(is_a($value,'chelp')) {
				$this->help = $value;
				$this->AddOption("name",'form');
			} elseif(is_string($value)) {
				$this->help = new CHelp($value);
				$this->AddOption("name",$value);
			} else
			{
				return $this->error("Incorrect value for SetHelp [$value]");
			}
			return 0;
		}
		function GetName()
		{
			return $this->GetOption("name");
		}
		function AddVar($name, $value)
		{
			$this->AddItemToTopRow(new CVar($name, $value));
		}
		function AddItemToTopRow($value)
		{
			array_push($this->top_items, $value);
		}
		function AddRow($item1, $item2=NULL, $class=NULL)
		{
			if(is_string($item1))
				$item1=nbsp($item1);

			$row = new CRow(array(
					new CCol($item1,'form_row_l'),
					new CCol($item2,'form_row_r')
					),
					$class
				);
			array_push($this->center_items, $row);
		}
		function AddSpanRow($value, $class=NULL)
		{
			$col = new CCol($value,$class);
		        $col->SetColSpan(2);
			array_push($this->center_items,new CRow($col,$class));
		}
		function AddItemToBottomRow($value)
		{
			$this->bottom_items->AddItem($value);
		}
/* protected */
		function ShowTagBody()
		{
			parent::ShowTagBody();

			$tbl = new CTable(NULL,'form');
			$tbl->SetOddRowClass('form_odd_row');
			$tbl->SetEvenRowClass('form_even_row');
			$tbl->SetCellSpacing(0);
			$tbl->SetCellPadding(1);
			$tbl->SetAlign($this->align);
# add center rows
			foreach($this->center_items as $item)
			        $tbl->AddRow($item);
# add first row
			$col = new CCol(NULL,'form_row_first');
		        $col->SetColSpan(2);
			if(isset($this->help))			$col->AddItem($this->help);
			if(isset($this->title))		 	$col->AddItem($this->title);
			foreach($this->top_items as $item)	$col->AddItem($item);
		        $tbl->SetHeader($col);
# add last row
		        $tbl->SetFooter($this->bottom_items);
			$tbl->Show();
		}
	}
?>
