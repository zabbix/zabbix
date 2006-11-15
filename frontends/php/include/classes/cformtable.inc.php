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
/* private *//*
		var $align;
		var $title;
		var $help;*/
/* protected *//*
		var $top_items = array();
		var $center_items = array();
		var $bottom_items = array();*/
/* public */
		function CFormTable($title=null, $action=null, $method=null, $enctype=null, $form_variable=null)
		{
			global  $_REQUEST;

			$this->top_items = array();
			$this->center_items = array();
			$this->bottom_items = array();

			if( null == $method )
			{
				$method = 'get';
			}

			if( null == $form_variable )
			{
				$form_variable = 'form';
			}

			parent::CForm($action,$method,$enctype);
			$this->SetTitle($title);
			$this->SetAlign('center');
			$this->SetHelp();

			$frm_link = new CLink();
//			$frm_link->SetName("formtable");
			$this->AddItemToTopRow($frm_link);
			
			$this->AddVar($form_variable, get_request($form_variable, 1));
			$this->AddVar('form_refresh',get_request('form_refresh',0)+1);

			$this->bottom_items = new CCol(SPACE,'form_row_last');
		        $this->bottom_items->SetColSpan(2);
		}
		function SetAction($value)
		{
			
			if(is_string($value))
				return parent::SetAction($value);
			elseif(is_null($value))
				return parent::SetAction($value);
			else
				return $this->error("Incorrect value for SetAction [$value]");
		}
		function SetName($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]");
			}
			return $this->AddOption('name',$value);
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
			} elseif(strtolower(get_class($value)) == 'chelp') {
				$this->help = $value;
			} elseif(is_string($value)) {
				$this->help = new CHelp($value);
				if($this->GetName()==NULL)
					$this->SetName($value);
			} else
			{
				return $this->error("Incorrect value for SetHelp [$value]");
			}
			return 0;
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

			if(is_null($item1)) $item1 = SPACE;
			if(is_null($item2)) $item2 = SPACE;

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
			if(is_string($value))
				$item1=nbsp($value);

			if(is_null($value)) $value = SPACE;
			if(is_null($class)) $class = 'form_row_c';

			$col = new CCol($value,$class);
		        $col->SetColSpan(2);
			array_push($this->center_items,new CRow($col));
		}
		function AddItemToBottomRow($value)
		{
			$this->bottom_items->AddItem($value);
		}
/* protected */
		function BodyToString()
		{
			parent::BodyToString();

			$tbl = new CTable(NULL,'formtable');

			$tbl->SetOddRowClass('form_odd_row');
			$tbl->SetEvenRowClass('form_even_row');
			$tbl->SetCellSpacing(0);
			$tbl->SetCellPadding(1);
			$tbl->SetAlign($this->align);
# add first row
			$col = new CCol(NULL,'form_row_first');
		        $col->SetColSpan(2);
			if(isset($this->help))			$col->AddItem($this->help);
			if(isset($this->title))		 	$col->AddItem($this->title);
			foreach($this->top_items as $item)	$col->AddItem($item);
		        $tbl->SetHeader($col);
# add last row
		        $tbl->SetFooter($this->bottom_items);
# add center rows
			foreach($this->center_items as $item)
			        $tbl->AddRow($item);

			return $tbl->ToString();
		}
	}
?>
