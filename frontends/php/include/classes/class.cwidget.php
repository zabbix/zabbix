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

class CWidget{

public $domid;
public $state;
public $flicker_state;

private $css_class;
private $pageHeaders;
private $headers;
private $flicker;

	public function __construct($id=null,$body=null,$state=null){
		if(is_null($id)){
			list($usec, $sec) = explode(' ',microtime());
			$id = 'widget_'.((int)($sec % 10)).((int)($usec * 1000));
		}

		$this->domid = $id;
		$this->state = $state;		// 0 - closed, 1 - opened

		$this->flicker_state = 1;	// 0 - closed, 1 - opened

		$this->css_class = is_null($this->state)?'header_wide':'header';

		$this->pageHeaders = null;
		$this->headers = null;

		$this->flicker = array();
		$this->body = array();

		$this->addItem($body);
	}

	public function setClass($class=null){
		if(is_string($class))
			$this->css_class = $class;
	}

	public function addPageHeader($header, $headerright=SPACE){
		zbx_value2array($headerright);

		if(is_null($header) && !is_null($headerright)) $header = SPACE;

		$this->pageHeaders[] = array('left'=> $header, 'right'=>$headerright);
	}

	public function addHeader($header=null, $headerright = SPACE){
		zbx_value2array($headerright);

		if(is_null($header) && !is_null($headerright)) $header = SPACE;

		$this->headers[] = array('left'=> $header, 'right'=>$headerright);
	}

	public function addFlicker($items=null, $state = 0){
		if(!is_null($items)) $this->flicker[] = $items;
		$this->flicker_state = $state;
	}

	public function addItem($items=null){
		if(!is_null($items)) $this->body[] = $items;
	}

	public function get(){
		$widget = array();

		if(!empty($this->pageHeaders)){
			$widget[] = $this->createPageHeader();
		}

		if(!empty($this->headers)){
			$widget[] = $this->createHeader();
		}

		if(is_null($this->state)){
			$this->state = true;
		}

		if(!empty($this->flicker)){
			$flicker_domid = 'flicker_'.$this->domid;
			$flicker_tab = new CTable();
			$flicker_tab->setAttribute('width','100%');
			$flicker_tab->setCellPadding(0);
			$flicker_tab->setCellSpacing(0);

			$div = new CDiv($this->flicker);
			$div->setAttribute('id',$flicker_domid);
			if(!$this->flicker_state) $div->setAttribute('style','display: none;');

//			$flicker_tab->addRow($div);

			$icon_l = new CDiv(SPACE.SPACE, $this->flicker_state?'dbl_arrow_up':'dbl_arrow_down');
			$icon_l->setAttribute('title',S_MAXIMIZE.'/'.S_MINIMIZE);
			$icon_l->setAttribute('id','flicker_icon_l');

			$icon_r = new CDiv(SPACE.SPACE, $this->flicker_state?'dbl_arrow_up':'dbl_arrow_down');
			$icon_r->setAttribute('title',S_MAXIMIZE.'/'.S_MINIMIZE);
			$icon_r->setAttribute('id','flicker_icon_r');

			$icons_row = new CTable(null,'textwhite');
			$icons_row->addRow(array($icon_l,new CSpan(SPACE.S_FILTER.SPACE),$icon_r));

			$thin_tab = $this->createFlicker($icons_row);
			$thin_tab->setAttribute('id','filter_icon');
			$thin_tab->addAction('onclick', "javascript: change_flicker_state('".$flicker_domid."');");

			$flicker_tab->addRow($thin_tab,'textcolorstyles pointer');

			$flicker_tab->addRow($div);

			$widget[] = $flicker_tab;
		}

		$div = new CDiv($this->body, 'mainwidget');
		$div->setAttribute('id',$this->domid);

		if(!$this->state) $div->setAttribute('style','display: none;');

		$widget[] = $div;

	return $widget;
	}

	public function show(){
		print($this->toString());
	}

	public function toString(){
		$tab = $this->get();

	return unpack_object($tab);
	}

	private function createPageHeader(){
		$pageHeader = array();

		foreach($this->pageHeaders as $num => $header){
			$pageHeader[] = $this->createPageHeaderRow($header['left'], $header['right']);
		}

	return new CDiv($pageHeader, 'ui-widget-header ui-corner-all');
	}

	private function createPageHeaderRow($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CJSscript('');
		}

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width','100%');

		$right_row = array($td_l);

		if(!is_null($col2)){
			if(!is_array($col2)) $col2 = array($col2);

			foreach($col2 as $num => $r_item)
				$right_row[] = new CCol($r_item);
		}

		$right_tab = new CTable(null,'nowrap');
		$right_tab->setAttribute('width','100%');

		$right_tab->addRow($right_row);

		$table = new CTable(NULL,'header maxwidth');
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		$td_r = new CCol($right_tab,'header_r right');
		$table->addRow(array(new CCol($col1,'header_l left'), $td_r));

	return $table;
	}

	private function createHeader(){
		$header = reset($this->headers);
		//$header = array_shift($this->headers);

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width','100%');

		$right_row = array($td_l);

		if(!is_null($header['right'])){
			foreach($header['right'] as $num => $r_item)
				$right_row[] = new CCol($r_item);
		}

		if(!is_null($this->state)){
			$icon = new CIcon(S_SHOW.'/'.S_HIDE, $this->state?'arrowup':'arrowdown',
					"change_hat_state(this,'".$this->domid."');");
			$icon->setAttribute('id',$this->domid.'_icon');
			$right_row[] = new CCol($icon);
		}

		$right_tab = new CTable(null,'nowrap');
		$right_tab->setAttribute('width','100%');

		$right_tab->addRow($right_row, 'textblackwhite');

		$header['right'] = $right_tab;

		$header_tab = new CTable(null,$this->css_class.' maxwidth');
		$header_tab->setCellSpacing(0);
		$header_tab->setCellPadding(1);

		if(!empty($this->flicker)){
//			$header_tab->setAttribute('style','border-bottom: 0px;');
		}

		$header_tab->addRow($this->createHeaderRow($header['left'],$right_tab),'first');

		foreach($this->headers as $num => $header){
			if($num == 0) continue;
			$header_tab->addRow($this->createHeaderRow($header['left'],$header['right']), 'next');
		}

		if($this->css_class == 'header_wide')
			return new CDiv($header_tab);
		else
			return new CDiv($header_tab, 'ui-widget-header ui-corner-all');
	}

	private function createHeaderRow($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
//if empty header than do not show it
			if(($col1 === SPACE) && ($col2 === SPACE)) return new CJSscript('');
		}

		$td_r = new CCol($col2,'header_r right');
		$row = array(new CCol($col1,'header_l left'), $td_r);

	return $row;
	}

	private function createFlicker($col1, $col2=NULL){

		$table = new CTable(NULL,'textwhite maxwidth middle flicker');
//		$table->setAttribute('border',1);
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		if(!is_null($col2)){
			$td_r = new CCol($col2,'flicker_r');
			$td_r->setAttribute('align','right');
			$table->addRow(array(new CCol($col1,'flicker_l'), $td_r));
		}
		else{
			$td_c = new CCol($col1,'flicker_c');
			$td_c->setAttribute('align','center');

			$table->addRow($td_c);
		}

	return $table;
	}
}

class CUIWidget extends CDiv{

public $domid;
public $state;
public $css_class;

private $header;
private $body;
private $footer;

	public function __construct($id, $body=null, $state=null){
		$this->domid = $id;
		$this->state = $state;		// 0 - closed, 1 - opened

		$this->css_class = 'header';

		$this->header = null;
		$this->body = array($body);
		$this->footer = null;

		parent::__construct(null, 'ui-widget ui-widget-content ui-helper-clearfix ui-corner-all widget');
		$this->setAttribute('id', $id.'_widget');
	}

	public function addItem($item){
		if(!is_null($item)) $this->body[] = $item;
	}

	public function setHeader($caption=null, $icons = SPACE){
		zbx_value2array($icons);

		if(is_null($caption) && !is_null($icons)) $caption = SPACE;

		$this->header = new CDiv(null, 'nowrap ui-corner-all ui-widget-header '.$this->css_class);

		if(!is_null($this->state)){
			$icon = new CIcon(
				S_SHOW.'/'.S_HIDE,
				$this->state?'arrowup':'arrowdown',
				"changeHatStateUI(this,'".$this->domid."');"
			);
			$icon->setAttribute('id',$this->domid.'_icon');
			$this->header->addItem($icon);
		}

		$this->header->addItem($icons);
		$this->header->addItem($caption);

	return $this->header;
	}

	public function setFooter($footer, $right=false){
		$this->footer = new CDiv($footer, 'nowrap ui-corner-all ui-widget-header footer '.($right?' right':' left'));

	return $this->footer;
	}

	public function get(){
		$this->cleanItems();
		parent::addItem($this->header);

		if(is_null($this->state)){
			$this->state = true;
		}

		$div = new CDiv($this->body, 'body');
		$div->setAttribute('id',$this->domid);

		if(!$this->state){
			$div->setAttribute('style','display: none;');
			$this->footer->setAttribute('style','display: none;');
		}

		parent::addItem($div);
		parent::addItem($this->footer);

	return $this;
	}

	public function toString($destroy=true){
		$this->get();
	return parent::toString($destroy);
	}
}
?>