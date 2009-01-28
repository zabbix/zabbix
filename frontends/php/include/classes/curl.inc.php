<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** $this program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** $this program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with $this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// Title: url manipulation class
// Author: Aly

class Curl{
/*
private $url = 			'';		//	actually, it's depricated/private variable 
private $port =			false;
private $host = 		'';
private $protocol = 	'';
private $username =		'';
private $password =		'';
private $filr =			'';
private $reference =	'';
private $path =			'';
private $query =		'';
private $arguments = 	array();
//*/

function curl($url=null){
	global $USER_DETAILS;
	
	$this->url = 		'';		//	actually, it's depricated/private variable 
	$this->port =		false;
	$this->host = 		'';
	$this->protocol = 	'';
	$this->username =	'';
	$this->password =	'';
	$this->filr =		'';
	$this->reference =	'';
	$this->path =		'';
	$this->query =		'';
	$this->arguments = 	array();

	if(empty($url)){
		$this->formatArguments();
		$this->url = $url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'].'?'.$this->getQuery();
	}
	else{
		$this->url=urldecode($url);

		$tmp_pos = strpos($this->url,'?');
		$this->query=($tmp_pos!==false)?(substr($this->url,$tmp_pos+1)):'';

		$tmp_pos = strpos($this->query,'#');
		if($tmp_pos!==false) $this->query=zbx_substring($this->query,0,$tmp_pos);

		$this->formatArguments($this->query);
	}

	$protocolSepIndex=strpos($this->url,'://');	
	if($protocolSepIndex!==false){
		$this->protocol= strtolower(zbx_substring($this->url,0,$protocolSepIndex));
		
		$this->host=substr($this->url, $protocolSepIndex+3);
		
		$tmp_pos = strpos($this->host,'/');
		if($tmp_pos!==false) $this->host=zbx_substring($this->host,0,$tmp_pos);
		
		$atIndex=strpos($this->host,'@');
		if($atIndex!==false){
			$credentials=zbx_substring($this->host,0,$atIndex);
			
			$colonIndex=strpos(credentials,':');
			if($colonIndex!==false){
				$this->username=zbx_substring($credentials,0,$colonIndex);
				$this->password=substr($credentials,$colonIndex);
			}
			else{
				$this->username=$credentials;
			}
			$this->host=substr($this->host,$atIndex+1);
		}
		
		$host_ipv6 = strpos($this->host,']');
		if($host_ipv6!==false){
			if($host_ipv6 < (zbx_strlen($this->host)-1)){
				$host_ipv6++;
				$host_less = substr($this->host,$host_ipv6);

				$portColonIndex=strpos($host_less,':');
				if($portColonIndex!==false){
					$this->host=zbx_substring($this->host,0,$host_ipv6);
					$this->port=substr($host_less,$portColonIndex+1);
				}
			}
		}
		else{
			$portColonIndex=strpos($this->host,':');
			if($portColonIndex!==false){
				$this->host=zbx_substring($this->host,0,$portColonIndex);
				$this->port=substr($this->host,$portColonIndex+1);
			}
		}
		
		$this->file = substr($this->url,$protocolSepIndex+3);
		$this->file = substr($this->file, strpos($this->file,'/'));
	}
	else{
		$this->file = $this->url;
	}
	
	$tmp_pos = strpos($this->file,'?');
	if($tmp_pos!==false) $this->file=zbx_substring($this->file, 0, $tmp_pos);

	$refSepIndex=strpos($url,'#');
	if($refSepIndex!==false){
		$this->file = zbx_substring($this->file,0,$refSepIndex);
		$this->reference = substr($url,strpos($url,'#')+1);
	}
	
	$this->path=$this->file;
	if(zbx_strlen($this->query)>0) 		$this->file.='?'.$this->query;
	if(zbx_strlen($this->reference)>0)	$this->file.='#'.$this->reference;
	
	if(isset($_COOKIE['zbx_sessionid']))
		$this->setArgument('sid', substr($_COOKIE['zbx_sessionid'],16,16));
}

function formatQuery(){
	$query = '';
	foreach($this->arguments as $key => $value){
		$query.= $key.'='.$value.'&';
	}
	$this->query = rtrim($query,'&');
}

function formatArguments($query=null){
	if(is_null($query)){
		$this->arguments = $_REQUEST;
	}
	else{
		$query=ltrim($query,'?');
		$args = explode('&',$query);
		foreach($args as $id => $arg){
			if(empty($arg)) continue;

			$tmp = explode('=',$arg);
			$this->arguments[$tmp[0]] = isset($tmp[1])?$tmp[1]:'';
		}
	}
	$this->formatQuery();
}

function getUrl(){
	$url = (zbx_strlen($this->protocol) > 0)?($this->protocol.'://'):'';
	$url .=  (zbx_strlen($this->username) > 0)?$this->username:'';
	$url .=  (zbx_strlen($this->password) > 0)?':'.$this->password:'';
	$url .=  (zbx_strlen($this->host) > 0)?$this->host:'';
	$url .=  $this->port?(':'.$this->port):'';
	$url .=  (zbx_strlen($this->path) > 0)?$this->path:'';
	$url .=  (zbx_strlen($this->query) > 0)?('?'.$this->query):'';
	$url .=  (zbx_strlen($this->reference) > 0)?('#'.urlencode($this->reference)):'';
	
//SDI($this->getProtocol().' : '.$this->getHost().' : '.$this->getPort().' : '.$this->getPath().' : '.$this->getQuery());
return $url;
}

function setPort($port){
	$this->port = $port;
}

function getPort(){ 
	return $this->port;
}

function setArgument($key,$value=''){
	$this->arguments[$key] = $value;
	$this->formatQuery();
}

function getArgument($key){
	if(isset($this->arguments[$key])) return $this->arguments[$key];
	else return NULL;
}

function setQuery($query){ 
	$this->query = $query;
	$this->formatArguments();
	$this->formatQuery();
}

function getQuery(){ 
	return $this->query;
}

function setProtocol($protocol){
	$this->protocol = $protocol;
}

/* Returns the protocol of $this URL, i.e. 'http' in the url 'http://server/' */
function getProtocol(){
	return $this->protocol;
}

function setHost($host){
	$this->host = $host;
}

/* Returns the host name of $this URL, i.e. 'server.com' in the url 'http://server.com/' */
function getHost(){
	return $this->host;
}

function setUserName($username){
	$this->username = $username;
}

/* Returns the user name part of $this URL, i.e. 'joe' in the url 'http://joe@server.com/' */
function getUserName(){
	return $this->username;
}

function setPassword($password){
	$this->password = $password;
}

/* Returns the password part of $this url, i.e. 'secret' in the url 'http://joe:secret@server.com/' */
function getPassword(){
	return $this->password;
}

function setFile($file){
	$this->file = $file;
}

/* Returns the file part of $this url, i.e. everything after the host name. */
function getFile(){
	return $this->file;
}

function setReference($reference){
	$this->reference = $reference;
}

/* Returns the reference of $this url, i.e. 'bookmark' in the url 'http://server/file.html#bookmark' */
function getReference(){
	return $this->reference;
}

function setPath($path){
	$this->path = $path;
}

/* Returns the file path of $this url, i.e. '/dir/file.html' in the url 'http://server/dir/file.html' */
function getPath(){
	return $this->path;
}

function toString(){
	return $this->getUrl();
}
}