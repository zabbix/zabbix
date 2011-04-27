<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
class CView{
	private $file;
	private $data;
	private $form;
	private $scripts;

	/**
	 * Creates a new view based on provided template file.
	 * @param string $file name of a view, located under include/views
	 * @param array $data deprecated parameter, use set() and get() methods for passing variables to views
	 */
	public function __construct($file, $data=array()){
		$this->assign($file, $data);
	}

	public function assign($file, $data){
		if(!preg_match("/[a-z\.]+/", $file)){
			throw new Exception(_s('Invalid view name given \'%s\'', $file));
		}

		$this->file = './include/views/'.$file.'.php';
		$this->data = $data;
	}

	/**
	 * Assign value to a named variable.
	 * @param string $var variable name
	 * @param any $value variable value
	 * @example set('hostName','Host ABC')
	 */
	public function set($var, $value){
		$this->data[$var] = $value;
	}

	/**
	 * Get value by variable name.
	 * @param string $var name of the variable.
	 * @return string variable value. Returns empty string if the variable is not defined.
	 * @example get('hostName')
	 */
	public function get($var){
		return isset($this->data[$var]) ? $this->data[$var] : '';
	}

	/**
	 * Get variable of type array by variable name.
	 * @param string $var name of the variable.
	 * @return array variable value. Returns empty array if the variable is not defined or not an array.
	 * @example getArray('hosts')
	 */
	public function getArray($var){
		return isset($this->data[$var]) && is_array($this->data[$var]) ? $this->data[$var] : array();
	}

	/**
	 * Load and execute view.
	 * TODO It outputs JavaScript code immediately, should be done in show() or processed separately.
	 * @return object GUI object.
	 */
	public function render(){
		$data = $this->data;

		ob_start();
		$this->form = include($this->file);
		if(FALSE === $this->form){
			throw new Exception(_s('Cannot include view file \'%s\'', $this->file));
		}
		$this->scripts = ob_get_clean();

		/* TODO It is for output of JS code. Should be moved to show() method. */
		print($this->scripts);
		return $this->form;
	}

	/**
	 * The method outputs HTML code based on rendered template.
	 * It calls render() if not called already.
	 * @return NULL
	 */
	public function show(){
		if(!isset($this->form)){
			throw new Exception(_('View is not rendered'));
		}
		$this->form->show();
	}
}
?>