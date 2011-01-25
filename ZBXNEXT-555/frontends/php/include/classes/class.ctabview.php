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
/**
 * Description of class
 * Produces ZABBIX object for more comfortable usage of jQuery tabed view
 * @author Aly
 */
class CTabView extends CDiv{
	protected $id = 'tabs';
	protected $tabs = array();
	protected $headers = array();

	protected $selectedTab = null;
	protected $rememberTab = false;

	public function __construct($data = array()){
		if(isset($data['id'])) $this->id = $data['id'];
		if(isset($data['remember'])) $this->setRemember($data['remember']);
		if(isset($data['selected'])) $this->setSelected($data['selected']);

		parent::__construct();
		$this->setAttribute('id', $this->id);
		$this->setAttribute('class', 'min-width hidden');
	}

	public function setRemember($remember){
		$this->rememberTab = $remember;
	}

	public function setSelected($selected){
		$this->selectedTab = $selected;
	}

	public function addTab($id, $header, $body){
		$this->headers[$id] = $header;

		$this->tabs[$id] = new CDiv($body);
		$this->tabs[$id]->setAttribute('id', $id);
	}

	public static function setFooter($main, $others){

	}

	public function toString($destroy=true){
		if(count($this->tabs) == 1){
			$this->setAttribute('class', 'min-width ui-tabs ui-widget ui-widget-content ui-corner-all widget');

			$header = reset($this->headers);
			$header = new CDiv($header);
			$header->addClass('ui-corner-all ui-widget-header header');
			$this->addItem($header);


			$tab = reset($this->tabs);
			$tab->addClass('ui-tabs ui-tabs-panel ui-widget ui-widget-content ui-corner-all widget');
			$this->addItem($tab);
		}
		else{
			$headersList = new CList();
			foreach($this->headers as $id => $header){
				$headersList->addItem(new CLink($header, '#'.$id, null, null, false));
			}

			$this->addItem($headersList);
			$this->addItem($this->tabs);

			$options = array();
			if(!is_null($this->selectedTab))
				$options['selected'] = $this->selectedTab;
			if(!is_null($this->rememberTab) && ($this->rememberTab > 0))
				$options['cookie'] = array('expires' => $this->rememberTab);

			zbx_add_post_js('jQuery(function() { jQuery( "#'.$this->id.'" ).tabs('.zbx_jsvalue($options, true).').show(); });');
		}

		return parent::toString($destroy);
	}
}
?>
