<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
class CTree{

 public $tree;
 public $fields;
 public $treename;


 private $size;
 private $maxlevel;

/*public*/
	public function __construct($treename, $value=array(),$fields=array()){

		$this->maxlevel=0;

		$this->tree = $value;
		$this->fields = $fields;
		$this->treename = $treename;

		$this->size = count($value);
		unset($value);
		unset($fields);

		if(!$this->checkTree()){
			$this->destroy();
			return false;
		}
		else {
			$this->countDepth();
		}
	}

	public function getTree(){
		return $this->tree;
	}

	public function getHTML(){
		$html[] = $this->createJS();
		$html[] = $this->simpleHTML();
	return $html;
	}

/* private */
	private function makeHeaders(){
		$c=0;
		$tr = new CRow();
		$tr->addItem($this->fields['caption']);
		$tr->setClass('treeheader');
		unset($this->fields['caption']);
		foreach($this->fields as $id => $caption){
			$tr->addItem($caption);
			$fields[$c] = $id;
			$c++;
		}
		$this->fields = $fields;
	return $tr;
	}

	private function simpleHTML(){
		$table = new CTable('','tabletree');

		$table->setCellSpacing(0);
		$table->setCellPadding(0);

		$table->setOddRowClass('odd_row');
		$table->setEvenRowClass('even_row');
		$table->headerClass = 'header';
		$table->footerClass = 'footer';

		$table->setAttribute('valign','top');
//		$table->setAttribute('border','1');
		$table->addRow($this->makeHeaders());

		foreach($this->tree as $id => $rows){
			$table->addRow($this->makeRow($id));
		}
	return $table;
	}

	private function makeRow($id){

		$table = new CTable();
		$table->setCellSpacing(0);
		$table->setCellPadding(0);
		$table->setAttribute('border','0');
		$table->setAttribute('height','100%');

		$tr = $this->makeSImgStr($id);

		$td = new CCol($this->tree[$id]['caption']);
		$td->setAttribute('style','height: 100%; vertical-align: top; white-space: normal; padding-right: 10px; padding-left: 2px;');
		$tr->addItem($td);

		$table->addRow($tr);

		$tr = new CRow();
		$tr->addItem($table);
		$tr->setAttribute('id','id_'.$id);
		$tr->setAttribute('style',($this->tree[$id]['parentid'] != '0')?('display: none;'):(''));


		foreach($this->fields as $key => $value){
			$td = new CCol($this->tree[$id][$value]);
			$td->setAttribute('style',' padding-right: 10px; padding-left: 2px;');
			$tr->addItem($td);
		}

	return $tr;
	}

	private function makeSImgStr($id){
		$tr = new CRow();
		$td = new CCol();

		$count=(isset($this->tree[$id]['nodeimg']))?(zbx_strlen($this->tree[$id]['nodeimg'])):(0);
		for($i=0; $i<$count; $i++){
			switch($this->tree[$id]['nodeimg'][$i]){
				case 'O':
					$td->setAttribute('style','width: 22px');
					$img= new CImg('images/general/tree/zero.gif','o','22','14');
					break;
				case 'I':
					$td->setAttribute('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
					$img= new CImg('images/general/tree/zero.gif','i','22','14');
					break;
				case 'L':
					$td->setAttribute('valign','top');
//					$td->setAttribute('style','width:22px; background-image:url(images/general/tree/pointc.gif);');

					$div = new CTag('div','yes');
					$div->setAttribute('style','height: 10px; width:22px; background-image:url(images/general/tree/pointc.gif);');

					if($this->tree[$id]['nodetype'] == 2){
						$img= new CImg('images/general/tree/plus.gif','y','22','14');
						$img->setAttribute('onclick','javascript: '.
												$this->treename.'.closeSNodeX("'.$id.'",this);'.
												" showPopupDiv('div_node_tree','select_iframe');"); // IE6 Fix

						$img->setAttribute('id','idi_'.$id);
						$img->setClass('imgnode');
					}
					else {
						$img = new CImg('images/general/tree/pointl.gif','y','22','14');
					}
					$div->addItem($img);
					$img=$div;
					break;
				case 'T':
					$td->setAttribute('valign','top');
					if($this->tree[$id]['nodetype'] == 2){
						$td->setAttribute('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img= new CImg('images/general/tree/plus.gif','t','22','14');

						$img->setAttribute('onclick','javascript: '.
												$this->treename.'.closeSNodeX("'.$id.'",this);'.
												" showPopupDiv('div_node_tree','select_iframe');");	// IE6 Fix

						$img->setAttribute('id','idi_'.$id);
						$img->setClass('imgnode');
					}
					else {
						$td->setAttribute('style','width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img= new CImg('images/general/tree/pointl.gif','t','22','14');
					}
					break;
			}
			$td->addItem($img);
			$tr->addItem($td);

			$td = new CCol();
		}
	//	echo $txt.' '.$this->tree[$id]['Name'].'<br />';
	return $tr;
	}

	private  function countDepth(){
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


	public function createJS(){

		$js = '<script src="js/class.ctree.js" type="text/javascript"></script>'."\n".
				'<script type="text/javascript">  var '.$this->treename.'_tree = new Array(0);';

		foreach($this->tree as $id => $rows){
			$parentid = $rows['parentid'];
			$this->tree[$parentid]['nodelist'].=$id.',';
		}

		foreach($this->tree as $id => $rows){
			if($rows['nodetype'] == '2'){
				$js .= $this->treename.'_tree[\''.$id.'\'] = { status: \'close\',  nodelist : \''.$rows['nodelist'].'\', parentid : \''.$rows['parentid'].'\'};';
				$js .= "\n";
			}
		}

		$js.= 'var '.$this->treename.' = null';
		$js.= '</script>'."\n";

		zbx_add_post_js($this->treename.' = new CTree("tree_'.$this->getUserAlias().'_'.$this->treename.'", '.$this->treename.'_tree);');
	return new CJSscript($js);
	}

	private function getImg($id,$img){

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

	private function checkTree(){
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

	private function destroy(){
		unset($this->tree);
	}

	private function getUserAlias(){
		global $USER_DETAILS;
	return $USER_DETAILS['alias'];
	}
}
?>
