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
	class CCol extends CTag
	{
/* public */
		function CCol($item=NULL,$class=NULL)
		{
			parent::CTag("td","yes");
			$this->AddItem($item);
			$this->SetClass($class);
		}
		function SetAlign($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]"); 
			}
			return $this->AddOption("align",$value);
		}
		function SetRowSpan($value)
		{
			if(!is_int($value) && !is_numeric($value))
			{
				return $this->error("Incorrect value for SetRowSpan [$value]"); 
			}
			return $this->AddOption("rowspan",strval($value));
		}
		function SetColSpan($value)
		{
			if(!is_int($value) && !is_numeric($value))
			{
				return $this->error("Incorrect value for SetColSpan[$value]"); 
			}
			return $this->AddOption("colspan",strval($value));
		}
	}
	
	class CRow extends CTag
	{	
/* public */
		function CRow($item=NULL,$class=NULL)
		{
			parent::CTag("tr","yes");
			$this->AddItem($item);
			$this->SetClass($class);
		}
		function SetAlign($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]"); 
			}
			return $this->AddOption("align",$value);
		}
		function AddItem($item=NULL)
		{
			if(is_null($item))
				return 0;
                        elseif(is_a($item,'ccol'))
			{
                        	return parent::AddItem($item);
			}
			elseif(is_array($item))
			{
				$ret = 0;
				foreach($item as $el)
				{
                        		if(is_a($el,'ccol'))
					{
                		        	$ret |= parent::AddItem($el);
					} elseif(!is_null($el)) {
						$ret |= parent::AddItem(new CCol($el));
					}
				}
				return $ret;
			}
			return parent::AddItem(new CCol($item));
		}
	}

	class CTable extends CTag
	{
/* protected */
		var $oddRowClass;
		var $evenRowClass;
		var $header;
		var $footer;
/* public */
		function CTable($message=NULL,$class=NULL)
		{
			parent::CTag("table","yes");
			$this->SetClass($class);
			$this->message = $message;
			$this->SetOddRowClass();
			$this->SetEvenRowClass();
			$this->SetHeader();
			$this->SetFooter();
		}
		function SetHeader($value=NULL,$class=NULL)
		{
			if(is_null($value)){
				$this->header = NULL;
			}elseif(is_a($value,'crow'))
			{
				if(isset($class))
					$value->SetClass($class);
				$this->header = $value;
			}else{
				$this->header = new CRow($value,$class);
			}
		}
		function SetFooter($value=NULL,$class=NULL)
		{
			if(is_null($value)){
				$this->footer = NULL;
			}elseif(is_a($value,'ccol'))
			{
				if(isset($this->header) && $value->GetOption('colspan')==NULL)
					$value->SetColSpan(count($this->header->items));

				$this->footer = new CRow($value,$class);
			}elseif(is_a($value,'crow'))
			{
				if(isset($class))
					$value->SetClass($class);
				$this->footer = $value;
			}else{
				$this->footer = new CRow($value,$class);
			}
		}
		function SetOddRowClass($value=NULL)
		{
			if(!is_string($value) && !is_null($value))
			{
				return $this->error("Incorrect value for SetOddRowClass [$value]"); 
			}
			$this->oddRowClass = $value;
		}
		function SetEvenRowClass($value=NULL)
		{
			if(!is_string($value) && !is_null($value))
			{
				return $this->error("Incorrect value for SetEvenRowClass [$value]"); 
			}
			$this->evenRowClass = $value;
		}
		function SetAlign($value)
		{
			if(!is_string($value))
			{
				return $this->error("Incorrect value for SetAlign [$value]"); 
			}
			return $this->AddOption("align",$value);
		}
		function SetCellPadding($value)
		{
			if(!is_int($value) && !is_numeric($value))
			{
				return $this->error("Incorrect value for SetCellpadding [$value]"); 
			}
			return $this->AddOption("cellpadding",strval($value));
		}
		function SetCellSpacing($value)
		{
			if(!is_int($value) && !is_numeric($value))
			{
				return $this->error("Incorrect value for SetCellSpacing [$value]"); 
			}
			return $this->AddOption("cellspacing",strval($value));
		}
		function AddRow($item,$rowClass=NULL)
		{
			if(is_null($item)){
				return 0;
			}elseif(is_a($item,'ccol'))
			{
				if(isset($this->header) && $item->GetOption('colspan')==NULL)
					$item->SetColSpan(count($this->header->items));

				return $this->AddItem(new CRow($item,$rowClass));
			}elseif(is_a($item,'crow'))
			{
				if(isset($rowClass))
					$item->SetClass($rowClass);

				return $this->AddItem($item);
			}else{
				return $this->AddItem(new CRow($item,$rowClass));
			}
		}
/* protected */
		function ShowTagBody()
		{
			if(is_a($this->header,'crow'))
				$this->header->Show();

			if(count($this->items)==0)
			{
				if(isset($this->message)) 
					$this->AddRow(new CCol($this->message,'message'),$this->evenRowClass);
			} 

			parent::ShowTagBody();

			if(is_a($this->footer,'crow'))
				$this->footer->Show();
		}
		function AddItem($value)
		{
			$cname="crow";
                        if(!is_a($value,$cname))
			{
				return $this->error("Incorrect value for AddItem [$value]"); 
			}

			if($value->GetOption('class')==NULL)
			{
				$value->SetClass(
					((count($this->items)+1)%2==1) ?
					 $this->evenRowClass : $this->oddRowClass
				);
			}

                        return parent::AddItem($value);
		}
/*		function Show()
		{
			ob_start();
			parent::Show();
			ob_end_flush();
		}
*/
	}
?>
