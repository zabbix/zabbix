<?php
/* 
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
	class CHatFormTable extends CForm{
/* private *//*
		var $align;
		var $title;
		var $help;*/
/* protected *//*
		var $top_items = array();
		var $center_items = array();
		var $bottom_items = array();*/
/* public */

		function CHatFormTable($title=null, $action=null, $method=null, $enctype=null, $form_variable=null){

			$this->top_items = array();
			$this->center_items = array();
			$this->bottom_items = array();
			$this->tableclass = 'formtable';
			$this->addOption('name',$title);

			if( null == $method ){
				$method = 'post';
			}

			if( null == $form_variable ){
				$form_variable = 'form';
			}

			parent::CForm($action,$method,$enctype);
			$this->setAlign('center');
			
			$this->addVar($form_variable, get_request($form_variable, 1));
			$this->addVar('form_refresh',get_request('form_refresh',0)+1);

			$this->bottom_items = new CCol(SPACE,'form_row_last');
		        $this->bottom_items->setColSpan(2);
		}
		
		function setAction($value){
			
			if(is_string($value))
				return parent::setAction($value);
			elseif(is_null($value))
				return parent::setAction($value);
			else
				return $this->error("Incorrect value for setAction [$value]");
		}
		
		function setName($value){
			if(!is_string($value)){
				return $this->error("Incorrect value for setAlign [$value]");
			}
			$this->addOption('name',$value);
			$this->addOption('id',$value);
		return true;
		}
		
		function setAlign($value){
			if(!is_string($value)){
				return $this->error("Incorrect value for setAlign [$value]");
			}
			return $this->align = $value;
		}

		
		function addVar($name, $value){
			$this->addItemToTopRow(new CVar($name, $value));
		}
		
		function addItemToTopRow($value){
			array_push($this->top_items, $value);
		}
		
		function addRow($item1, $item2=NULL, $class=NULL){
			if(strtolower(get_class($item1)) == 'crow'){
			
			} 
			else if(strtolower(get_class($item1)) == 'ctable'){
				$td = new CCol($item1,'form_row_c');
				$td->setColSpan(2);
				
				$item1 = new CRow($td);
			} 
			else{
				$tmp = $item1;
				if(is_string($item1)){
					$item1=nbsp($item1);
				}
				
				if(empty($item1)) $item1 = SPACE;
				if(empty($item2)) $item2 = SPACE;
				
				$item1 = new CRow(
								array(
									new CCol($item1,'form_row_l'),
									new CCol($item2,'form_row_r')
								),
								$class);
			}

			array_push($this->center_items, $item1);
		}
		
		function addSpanRow($value, $class=NULL){
			if(is_string($value))
				$item1=nbsp($value);

			if(is_null($value)) $value = SPACE;
			if(is_null($class)) $class = 'form_row_c';

			$col = new CCol($value,$class);
		        $col->setColSpan(2);
			array_push($this->center_items,new CRow($col));
		}
		
		
		function addItemToBottomRow($value){
			$this->bottom_items->addItem($value);
		}

		function setTableClass($class){
			if(is_string($class)){
				$this->tableclass = $class;
			}
		}
		
/* protected */
		function BodyToString(){
			parent::BodyToString();

			$tbl = new CTableInfo();
			$tbl->addOption('style','width: auto;');
			
			$tmp_tbl = new CTable('','nowrap');
// add center rows			
			foreach($this->center_items as $id => $item){
				$tmp_tbl->addRow($item);
			}

			$tbl->addRow($tmp_tbl);
			
//	PHP4 fix
			$footer = $this->bottom_items;
			$footer->addOption('style','text-align: right;');
//---
			$tbl->setFooter($footer);

		return $tbl->ToString();
		}
	}
?>