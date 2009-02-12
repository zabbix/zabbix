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
?>
<?php
	class CButton extends CTag{
/* public */
		function CButton($name='button', $caption='', $action=NULL, $accesskey=NULL){
			parent::CTag('input','no');
			$this->tag_body_start = '';
			$this->options['type'] = 'submit';
			$this->addOption('value', $caption);
			$this->options['class'] = 'button';
			$this->setName($name);
			$this->setAction($action);
			$this->setAccessKey($accesskey);
		}
		
		function setAction($value=null){
			$this->addAction('onclick', $value);
		}
		
		function setTitle($value='button title'){
			$this->addOption('title', $value);
		}
		
		function setAccessKey($value='B'){
			if(isset($value))
				if(!isset($this->options['title']))
					$this->setTitle($this->options['value'].' [Alt+'.$value.']');

			return $this->addOption('accessKey', $value);
		}
		
		function setType($type='button'){
			$this->addOption('type',$type);
		}
	}

	class CButtonCancel extends CButton{
		function CButtonCancel($vars=NULL,$action=NULL){
			parent::CButton('cancel',S_CANCEL);
			$this->options['type'] = 'button';
			$this->setVars($vars);
			$this->setAction($action);
		}
		function setVars($value=NULL){
			global $page;

			$url = '?cancel=1';
			if(!is_null($value)) $url.= $value;

			$uri = new Curl($url);
			$url = $uri->getUrl();
			
			return parent::setAction("javascript: return redirect('".$url."');");
		}
	}

	class CButtonQMessage extends CButton{
		/*
		var $vars;
		var $msg;
		var $name;*/

		function CButtonQMessage($name, $caption, $msg=NULL, $vars=NULL){
			$this->vars = null;
			$this->msg = null;
			$this->name = $name;
			
			parent::CButton($name,$caption);

			$this->setMessage($msg);
			$this->setVars($vars);
			$this->setAction(NULL);
		}
		
		function setVars($value=NULL){
			if(!is_string($value) && !is_null($value)){
				return $this->error('Incorrect value for setVars ['.$value.']');
			}
			$this->vars = $value;
			$this->setAction(NULL);
		}
		
		function setMessage($value=NULL){
			if(is_null($value))
				$value = 'Are You Sure?';

			if(!is_string($value)){
				return $this->error('Incorrect value for setMessage ['.$value.']');
			}
			$this->msg = $value;
			$this->setAction(NULL);
		}
		
		function setAction($value=null){
			if(!is_null($value))
				return parent::setAction($value);

			global $page;

			$confirmation = "Confirm('".$this->msg."')";
			
			if(isset($this->vars)){
				$link = $page['file'].'?'.$this->name.'=1'.$this->vars;
				$url = new Curl($link);
				
				$action = "redirect('".$url->getUrl()."')";
			}
			else{
				$action = 'true';
			}
			
			return parent::setAction('if('.$confirmation.') return '.$action.'; else return false;');
		}
	}

	class CButtonDelete extends CButtonQMessage{
		function CButtonDelete($msg=NULL, $vars=NULL){
			parent::CButtonQMessage('delete',S_DELETE,$msg,$vars);
		}
	}
?>
