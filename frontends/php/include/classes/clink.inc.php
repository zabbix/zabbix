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
	class CLink extends CTag
	{
/* public */
		function CLink($item=NULL,$url=NULL,$class=NULL,$action=NULL){
			parent::CTag('a','yes');

			$this->tag_start= '';
			$this->tag_end = '';
			$this->tag_body_start = '';
			$this->tag_body_end = '';

			if(!is_null($class))	$this->SetClass($class);
			if(!is_null($item))	$this->AddItem($item);
			if(!is_null($url))	$this->SetUrl($url);
			if(!is_null($action))	$this->SetAction($action);
		}
		
		function SetAction($value=NULL){
			if(is_null($value))
				return $this->options['action'] = $page['file'];

			return parent::AddAction('onclick', $value);
		}
		
		function SetUrl($value){
			$this->AddOption('href', $value);
		}
		
		function getUrl(){
			if(isset($this->options['href']))
				return $this->options['href'];
			else
				return null;
		}
		
		function SetTarget($value=NULL){
			if(is_null($value)){
				unset($this->options['target']);
			}
			else{
				$this->options['target'] = $value;
			}
		}
	}
?>
