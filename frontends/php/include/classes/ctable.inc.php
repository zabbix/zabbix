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
			return $this->options['align'] = $value;
		}
		function SetRowSpan($value)
		{
			return $this->options['rowspan'] = strval($value);
		}
		function SetColSpan($value)
		{
			return $this->options['colspan'] =strval($value);
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
			return $this->options['align'] = $value;
		}
		function AddItem($item)
		{
			if(strtolower(get_class($item))=='ccol') {
				parent::AddItem($item);
			}
			elseif(is_array($item))
			{
				foreach($item as $el)
				{
                        		if(strtolower(get_class($el))=='ccol') {
                		        	parent::AddItem($el);
					} elseif(!is_null($el)) {
						parent::AddItem('<td>'.unpack_object($el).'</td>');
					}
				}
			}
			elseif(!is_null($item))
			{
				parent::AddItem('<td>'.unpack_object($item).'</td>');
			}
		}
	}

	class CTable extends CTag
	{
/* protected *//*
		var $oddRowClass;
		var $evenRowClass;
		var $header;
		var $headerClass;
		var $colnum;
		var $rownum;
		var $footer;
		var $footerClass;
		var $message;*/
/* public */
		function CTable($message=NULL,$class=NULL)
		{
			parent::CTag("table","yes");
			$this->SetClass($class);
				
			$this->rownum = 0;
			$this->oddRowClass = NULL;
			$this->evenRowClass = NULL;


			$this->header = '';
			$this->headerClass = NULL;
			$this->footer = '';
			$this->footerClass = NULL;
			$this->colnum = 0;

			$this->message = $message;
		}
		function SetOddRowClass($value=NULL)
		{
			$this->oddRowClass = $value;
		}
		function SetEvenRowClass($value=NULL)
		{
			$this->evenRowClass = $value;
		}
		function SetAlign($value)
		{
			return $this->options['align'] = $value;
		}
		function SetCellPadding($value)
		{
			return $this->options['cellpadding'] = strval($value);
		}
		function SetCellSpacing($value)
		{
			return $this->options['cellspacing'] = strval($value);
		}

		function PrepareRow($item,$rowClass=NULL)
		{
			if(is_null($item)) return NULL;

			if(strtolower(get_class($item))=='ccol') {
				if(isset($this->header) && !isset($item->options['colspan']))
					$item->options['colspan'] = $this->colnum;

				$item = new CRow($item,$rowClass);
			}
			if(strtolower(get_class($item))=='crow') {
				if(isset($rowClass))
					$item->SetClass($rowClass);
			}
			else
			{
				$item = new CRow($item,$rowClass);
			}
			if(!isset($item->options['class']))
			{
				$item->SetClass(($this->rownum % 2) ?
                                                $this->oddRowClass:
                                                $this->evenRowClass);
			}/**/
			return $item->ToString();
		}
		function SetHeader($value=NULL,$class=NULL)
		{
			if(is_null($class)) $class = $this->headerClass;

			if(strtolower(get_class($value))=='crow') {
				if(!is_null($class))	$value->SetClass($class);
			}else{
				$value = new CRow($value,$class);
			}
			$this->colnum = $value->ItemsCount();
			$this->header = $value->ToString();
		}
		function SetFooter($value=NULL,$class=NULL)
		{
			if(is_null($class)) $class = $this->footerClass;

			$this->footer = $this->PrepareRow($value,$class);;
		}
		function AddRow($item,$rowClass=NULL)
		{
			$item = $this->AddItem($this->PrepareRow($item,$rowClass));
			++$this->rownum;
			return $item;
		}
		function ShowRow($item,$rowClass=NULL)
		{
			echo $this->PrepareRow($item,$rowClass);
			++$this->rownum;
		}
/* protected */
		function GetNumRows()
		{
			return $this->rownum;
		}

		function StartToString()
		{
			$ret = parent::StartToString();
			$ret .= $this->header;
			return $ret;
		}
		function EndToString()
		{
			$ret = "";
			if($this->rownum == 0 && isset($this->message)) 
			{
				$ret = $this->PrepareRow(new CCol($this->message,'message'));
			}
			$ret .= $this->footer;
			$ret .= parent::EndToString();
			return $ret;
		}
	}
?>
