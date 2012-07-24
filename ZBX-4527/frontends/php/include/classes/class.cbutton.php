<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
class CButton extends CTag{

	/**
	 * For inputs of type "button", the "&" symbol should not be encoded.
	 *
	 * @var int
	 */
	protected $valueEncStrategy = self::ENC_NOAMP;

	public function __construct($name='button', $caption='', $action=NULL, $submit=true){
		parent::__construct('input','no');
		$this->tag_body_start = '';
		
		$this->attributes['type'] = $submit?'submit':'button';

		$this->setAttribute('value', $caption);
		$this->attributes['class'] = 'button';
		$this->attributes['id'] = $name;
		$this->setName($name);
		$this->setAction($action);
	}

	public function setAction($value=null){
		$this->addAction('onclick', $value);
	}

	public function setTitle($value='button title'){
		$this->setAttribute('title', $value);
	}

	public function setAccessKey($value='B'){
		if(isset($value))
			if(!isset($this->attributes['title']))
				$this->setTitle($this->attributes['value'].' [Alt+'.$value.']');

		return $this->setAttribute('accessKey', $value);
	}

	public function setType($type='button'){
		$this->setAttribute('type',$type);
	}
}
?>
