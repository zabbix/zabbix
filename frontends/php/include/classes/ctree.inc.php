<?php
/* 
** ZABBIX
** Copyright (C) 2007 SIA ZABBIX
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

class CTree{

/*public*/
var $tree='';
var $fields='';
var $treename='';

/*private*/
var $size=0;
var $maxlevel=0;


	/*public*/
	/*public*/ function CTree($value=array(),$fields=array()){
		$this->tree = $value;
		$this->fields = $fields;
		$this->treename = $this->fields['caption'];
		
		$this->size = count($value);
		unset($value);
		unset($fields);
	
		if(!$this->CheckTree()){
			$this->Destroy();
			return false;
		} 
		else {
			$this->CountDepth();
		}
	}
	
	/*public*/ function GetTree(){
		return $this->tree;
	}
	
	/*private*/ function MakeHeaders(){
		$c=0;
		$tr = new CRow();
		$tr->AddItem($this->fields['caption']);
		$tr->SetClass('treeheader');
		unset($this->fields['caption']);
		foreach($this->fields as $id => $caption){
			$tr->AddItem($caption);
			$fields[$c] = $id;
			$c++;	
		}
		$this->fields = $fields;
	return $tr;
	}
	
	/*private*/ function SimpleHTML(){
		$table = new CTable('','tabletree');
		
		$table->SetCellSpacing(0);
		$table->SetCellPadding(0);
	
		$table->oddRowClass = 'odd_row';
		$table->evenRowClass = 'even_row';
		$table->headerClass = 'header';
		$table->footerClass = 'footer';
		
		$table->AddOption('border','0');
		$table->AddRow($this->MakeHeaders());
	
		foreach($this->tree as $id => $rows){
			$table->AddRow($this->MakeSHTMLRow($id));
		}
	return $table;
	}
	
	/*public */ function getHTML(){
		$html[] = $this->CreateJS();
		$html[] = $this->SimpleHTML();
	return $html;
	}
	
	/*private*/  function MakeSHTMLRow($id){
		
		$table = new CTable();
		$table->SetCellSpacing(0);
		$table->SetCellPadding(0);
	//	$table->AddOption('border','1');
		
		$tr = $this->MakeSImgStr($id);
		
		$td = new CCol($this->tree[$id]['caption']);
		$td->AddOption('style',' white-space: normal; height: 20px; padding-right: 10px; padding-left: 2px;');
		$tr->AddItem($td);
	
		$table->AddRow($tr);
		
		$tr = new CRow();
		$tr->AddItem($table);
		$tr->AddOption('id','id_'.$id);
		$tr->AddOption('style',($this->tree[$id]['parentid'] != '0')?('display: none;'):(''));
	
	
		foreach($this->fields as $key => $value){
			$td = new CCol($this->tree[$id][$value]);
			$td->AddOption('style','padding-right: 10px; padding-left: 2px;');
			$tr->AddItem($td);
		}
		
		
	return $tr;
	}
	
	/*private*/ function MakeSImgStr($id){
		$tr = new CRow();
		$td = new CCol();
	
		$count=(isset($this->tree[$id]['nodeimg']))?(strlen($this->tree[$id]['nodeimg'])):(0);
		for($i=0; $i<$count; $i++){
			switch($this->tree[$id]['nodeimg'][$i]){
				case 'O':
					$td->AddOption('style','width: 22px');
					$img= new CImg('images/general/tree/zero.gif','o','22','14');
					break;
				case 'I':
					$td->AddOption('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
					$img= new CImg('images/general/tree/zero.gif','i','22','14');
					break;
				case 'L':
					$td->AddOption('valign','top');
					if($this->tree[$id]['nodetype'] == 2){
						$div = new CTag('div','yes');
						$div->AddOption('style','height: 10px; width:22px; background-image:url(images/general/tree/pointc.gif);');
	
						$img= new CImg('images/general/tree/plus.gif','y','22','14');
						$img->AddOption('onclick','javascript: tree.closeSNodeX('.$id.',this);');
						$img->AddOption('id','idi_'.$id);
						$img->SetClass('imgnode');
						
						$div->AddItem($img);
					} 
					else {
						$div = new CTag('div','yes');
						$div->AddOption('style','height: 10px; width:22px; background-image:url(images/general/tree/pointc.gif);');
						$div->AddItem(new CImg('images/general/tree/pointl.gif','y','22','14'));
					}
					$img=$div;
					break;
				case 'T':
					$td->AddOption('valign','top');
					if($this->tree[$id]['nodetype'] == 2){
						$td->AddOption('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img= new CImg('images/general/tree/plus.gif','t','22','14');
						
						$img->AddOption('onclick','javascript: tree.closeSNodeX('.$id.',this);');
						$img->AddOption('id','idi_'.$id);
						$img->SetClass('imgnode');					
					} 
					else {
						$td->AddOption('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img= new CImg('images/general/tree/pointl.gif','t','22','14');
					}
					break;
			}
			$td->AddItem($img);
			$tr->AddItem($td);
	
			$td = new CCol();
		}
	//	echo $txt.' '.$this->tree[$id]['Name'].'<br />';
	return $tr;
	}
	
	/*private*/  function CountDepth(){
		foreach($this->tree as $id => $rows){
			
			if($rows['id'] == '0'){
				continue;
			}
			$parentid = $this->tree[$id]['parentid'];
			
			$this->tree[$id]['nodeimg'] = $this->GetImg($id,(isset($this->tree[$parentid]['nodeimg']))?($this->tree[$parentid]['nodeimg']):(''));
			//$this->tree[$parentid]['childs'] = ($this->tree[$parentid]['childs']+$this->tree[$id]['childs']+1);
			
			$this->tree[$parentid]['nodetype'] = 2;
			
			$this->tree[$id]['Level'] = (isset($this->tree[$parentid]['Level']))?($this->tree[$parentid]['Level']+1):(1);
			
			($this->maxlevel>$this->tree[$id]['Level'])?(''):($this->maxlevel = $this->tree[$id]['Level']);
		}
	
	}
	
	
	/*public*/ function CreateJS(){
	global $page;
		$js = '
		<script src="js/tree.js" type="text/javascript"></script>
		<script src="js/cookies.js" type="text/javascript"></script>	
		<script type="text/javascript"> 
				var treenode = new Array(0);
				var tree_name = "tree_'.$this->getUserAlias().'_'.$page["file"].'";
				';
				
		foreach($this->tree as $id => $rows){
			$parentid = $rows['parentid'];
			$this->tree[$parentid]['nodelist'].=$id.'.';
		}
		
		foreach($this->tree as $id => $rows){
			if($rows['nodetype'] == '2'){
				$js .= 'treenode['.$id.'] = { status: \'close\',  nodelist : \''.$rows['nodelist'].'\', parentid : \''.$rows['parentid'].'\'};';
			}
		}
		$js.='window.onload = function(){tree.init()}; </script>';
		
	return new CScript($js);
	}
	
	/*private*/ function GetImg($id,$img){

		$img=str_replace('T','I',$img);
		$img=str_replace('L','O',$img);
		$ch = 'L';
	
		$childs = $this->tree[$this->tree[$id]['parentid']]['childnodes'];
		$childs_last = count($this->tree[$this->tree[$id]['parentid']]['childnodes'])-1;
		
		if(isset($childs[$childs_last]) && ($childs[$childs_last] != $id)){
			$ch='T';
		}
		$img.=$ch;
		
	return $img;
	}
	
	/*private*/ function CheckTree(){
		if(!is_array($this->tree)){
			return false;
		}
		foreach($this->tree as $id => $cell){
			$this->tree[$id]['nodetype'] = 0;
			
			$parentid=$cell['parentid'];
			$this->tree[$parentid]['childnodes'][] = $id;//$cell['id'];
	
			$this->tree[$id]['nodelist'] = '';
//		echo $parentid.' : '.$id.'('.$cell['id'].')'.SBR;
		}
		
	return true;
	}
	
	/*private*/ function Destroy(){
		unset($this->tree);
	}
	
	/*private*/ function getUserAlias(){
	global $USER_DETAILS;
	return $USER_DETAILS["alias"];
	}
}

?>