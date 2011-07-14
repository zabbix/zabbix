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

class CTree{

/*public*/
var $tree='';
var $fields='';
var $treename='';

/*private*/
var $size=0;
var $maxlevel=0;


/*public*/
function CTree($value=array(),$fields=array()){
//	parent::CTable();
	$this->tree = $value;
	$this->fields = $fields;
	$this->treename = $this->fields['caption'];
	
	$this->size = count($value);
	unset($value);
	unset($fields);

	if(!$this->CheckTree()){
		$this->Destroy();
		return false;
	} else {
		$this->CountDepth();
	}
}

function GetTree(){
	return $this->tree;
}

/*private*/
function MakeHeaders(){
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

function SimpleHTML(){
	$table = new CTableInfo('','tabletree');
	
	$table->SetCellSpacing(0);
	$table->SetCellPadding(0);

	$table->oddRowClass = 'odd_row';
	$table->evenRowClass = 'even_row';
	$table->headerClass = 'header';
	$table->footerClass = 'footer';
	
	$table->AddOption('border','0');
	$table->AddRow($this->MakeHeaders());
//	$table->AddRow();
	foreach($this->tree as $id => $rows){
		$table->AddRow($this->MakeSHTMLRow($id));
	}
return $table->ToString();
}


function MakeSHTMLRow($id){
	$table = new CTable();
	$table->SetCellSpacing(0);
	$table->SetCellPadding(0);
	$table->AddOption('width','200');
	
	$tr = $this->MakeSImgStr($id);
	
	$td = new CCol($this->tree[$id]['caption']);
	$td->SetAlign('left');


	$tr->AddItem($td);
	$table->AddRow($tr);
	
	$tr = new CRow();
	$tr->AddItem($table);
	$tr->AddOption('id',$id);
	$tr->AddOption('style',($this->tree[$id]['parentid'] != '0')?('display: none;'):(''));
	$tr->AddOption('valign','top');

	foreach($this->fields as $key => $value){
		$td = new CCol($this->tree[$id][$value]);
		$tr->AddItem($td);
	}
return $tr;
}

function MakeSImgStr($id){
	$tr = new CRow();
	$tr->AddOption('height',18);
//	$tr->AddOption('valign','top');
	
	$count=(isset($this->tree[$id]['nodeimg']))?(strlen($this->tree[$id]['nodeimg'])):(0);
	for($i=0; $i<$count; $i++){
		switch($this->tree[$id]['nodeimg'][$i]){
			case 'O':
				$img= new CImg('images/general/tree/O.gif','o','22','18');
				break;
			case 'I':
				$img= new CImg('images/general/tree/I.gif','i','22','18');
				break;
			case 'L':
				if($this->tree[$id]['nodetype'] == 2){
					$img= new CImg('images/general/tree/Yc.gif','y','22','18');
					$img->AddOption('OnClick','javascript: tree.closeSNodeX('.$id.',this);');
					$img->AddOption('id',$id.'I');
					$img->SetClass('imgnode');
					
				} else {
					$img= new CImg('images/general/tree/L.gif','l','22','18');
				}
				break;
			case 'T':
				if($this->tree[$id]['nodetype'] == 2){
					$img= new CImg('images/general/tree/Xc.gif','x','22','18');
					$img->AddOption('OnClick','javascript: tree.closeSNodeX('.$id.',this);');
					$img->AddOption('id',$id.'I');
					$img->SetClass('imgnode');
				} else {
					$img= new CImg('images/general/tree/T.gif','t','22','18');
				}
				break;
		}
		$td = new CCol($img,'tdtree');
		$tr->AddItem($td);
	}
//	echo $txt.' '.$this->tree[$id]['Name'].'<br />';
return $tr;
}

function CountDepth(){
	foreach($this->tree as $id => $rows){
		
		if($id == '0'){
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


function CreateJS(){
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
return $js.'window.onload = function(){tree.init()};
</script>';
}

function GetImg($id,$img){
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

function CheckTree(){
	if(!is_array($this->tree)){
		return false;
	}
	foreach($this->tree as $id => $cell){
		$this->tree[$id]['nodetype'] = 0;
		
		$parentid=$cell['parentid'];
		$this->tree[$parentid]['childnodes'][] = $id;

		$this->tree[$id]['nodelist'] = '';
//		echo $id.BR;
	}
	
return true;
}

function Destroy(){
	unset($this->tree);
}

function getUserAlias(){
global $USER_DETAILS;
return $USER_DETAILS["alias"];
}
}

?>