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
class CAccordion extends CDiv{
	protected $id = 'accordion';
	protected $multiView = false;

	public function __construct($data){
		if(isset($data['id'])) $this->id = $data['id'];
		if(isset($data['multiview'])) $this->setMultiView($data['multiview']);

		$this->headers = array();
		$this->tabs = array();

		parent::__construct();
		$this->setAttribute('id',$this->id);
		$this->setAttribute('class','accordion');
	}

	public function setMultiView($multiview){
		$this->multiView = $multiview;
	}

	public function addTab($header, $body){
		$head = new CTag('h3', 'yes', new CLink($header, '#'));
		$head->setAttribute('class', 'head');

		parent::addItem($head);
		parent::addItem(new CDiv($body));
	}

	public function toString($destroy=true){
		if($this->multiView){
			zbx_add_post_js("jQuery('.accordion .head').click(function() { $(this).next().toggle('slow'); return false;}).next().hide();");
		}
		else{
			zbx_add_post_js('jQuery("#'.$this->id.'").accordion();');
			zbx_add_post_js('setTimeout(function(){jQuery("#'.$this->id.'").accordion("resize"); }, 1);');
		}
		return parent::toString($destroy);
	}
}
?>