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

private $headers;
private $flicker;
private $items;

	public function __construct($id=null,$body=null,$state=null){
		if(is_null($id)){
			list($usec, $sec) = explode(' ',microtime());
			$id = 'widget_'.((int)($sec % 10)).((int)($usec * 1000));
		}

		$this->domid = $id;
		$this->state = $state;		// 0 - closed, 1 - opened

		$this->flicker_state = 0;	// 0 - closed, 1 - opened

		$this->headers = null;

		$this->flicker = array();
		$this->body = array();

		$this->addItem($body);
	}

	public function addHeader($header=null, $headerright=null){
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
		$widget = new CTable();
		$widget->setAttribute('width','100%');
		$widget->setCellPadding(0);
		$widget->setCellSpacing(0);

		foreach($this->headers as $num => $header){
			$right_tab = null;
			if(!is_null($header['right']) || !is_null($this->state)){
				$td_l = new CCol(SPACE);
				$td_l->setAttribute('width','100%');

				$right_row = array($td_l);

				if(!is_null($header['right'])){
					foreach($header['right'] as $num => $r_item)
						$right_row[] = new CCol($r_item);
				}

				if(!is_null($this->state)){
					$icon = new CDiv(SPACE, $this->state?'arrowup':'arrowdown');
					$icon->setAttribute('id',$this->domid.'_icon');
					$icon->setAttribute('title',S_SHOW.'/'.S_HIDE);
					$icon->addAction('onclick',new CScript("javascript: change_hat_state(this,'".$this->domid."');"));
					$right_row[] = new CCol($icon);
				}

				$right_tab = new CTable(null,'textwhite');
				$right_tab->setAttribute('width','100%');

				$right_tab->addRow($right_row);
			}

			$header_tab = new CTable(null,'nowrap');
			$header_tab->setAttribute('width','100%');
//			$header_tab->setAttribute('border','1');
			$header_tab->setCellPadding(0);
			$header_tab->setCellSpacing(0);

			$header_tab->addRow($this->createHeader($header['left'],$right_tab));

			$widget->addRow($header_tab);
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
			$flicker_tab->setAttribute('border',0);

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

			$icons_row = new CTable(null,'whitetext');
			$icons_row->addRow(array($icon_l,SPACE,S_FILTER,SPACE,$icon_r));

			$thin_tab = $this->createFlicker($icons_row);
			$thin_tab->setAttribute('id','filter_icon');
			$thin_tab->addAction('onclick',new CScript("javascript: change_flicker_state('".$flicker_domid."');"));

			$flicker_tab->addRow($thin_tab,'textcolorstyles link pointer');
			
			$flicker_tab->addRow($div);

			$widget->addRow($flicker_tab);
		}

		$div = new CDiv($this->body);
		$div->setAttribute('id',$this->domid);

		if(!$this->state) $div->setAttribute('style','display: none;');

		$widget->addRow($div);
	return $widget;
	}

	public function show(){
		print($this->toString());
	}

	public function toString(){
		$tab = $this->get();
	return $tab->toString();
	}

	private function createHeader($col1, $col2=null){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
		//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CScript('');
		}

		$table = new CTable(NULL,'header');
//		$table->setAttribute('border',1);
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		if(!is_null($col2)){
			$td_r = new CCol($col2,'header_r');
			$td_r->setAttribute('align','right');
			$table->addRow(array(new CCol($col1,'header_l'), $td_r));
		}
		else{
			$td_c = new CCol($col1,'header_c');
			$td_c->setAttribute('align','center');

			$table->addRow($td_c);
		}

	return $table;
	}

	private function createFlicker($col1, $col2=NULL){

		$table = new CTable(NULL,'flicker');
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
