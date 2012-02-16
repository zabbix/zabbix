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
// Title: url manipulation class
// Author: Aly

class Curl{
 private $url;
 protected $port;
 protected $host;
 protected $protocol;
 protected $username;
 protected $password;
 protected $file;
 protected $reference;
 protected $path;
 protected $query;
 protected $arguments;

	public function __construct($url=null){
		$this->url =		null;
		$this->port =		null;
		$this->host =		null;
		$this->protocol =	null;
		$this->username =	null;
		$this->password =	null;
		$this->file =		null;
		$this->reference =	null;
		$this->path =		null;
		$this->query =		null;
		$this->arguments =	array();

		if(empty($url)){
			$this->formatGetArguments();

			// $protocol = (zbx_strpos(zbx_strtolower($_SERVER['SERVER_PROTOCOL']), 'shttp') !== false)?'shttp':'http';
			$protocol = ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) || ($_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http';

			$this->url = $url = $protocol.'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['SCRIPT_NAME'].'?'.$this->getQuery();
		}
		else{
			$this->url = $url;

			$tmp_pos = zbx_strpos($this->url,'?');
			$this->query=($tmp_pos!==false)?(substr($this->url,$tmp_pos+1)):'';

			$tmp_pos = zbx_strpos($this->query,'#');
			if($tmp_pos!==false) $this->query=zbx_substring($this->query,0,$tmp_pos);

			$this->formatArguments($this->query);
		}

		$protocolSepIndex=zbx_strpos($this->url,'://');
		if($protocolSepIndex!==false){
			$this->protocol= zbx_strtolower(zbx_substring($this->url,0,$protocolSepIndex));

			$this->host=substr($this->url, $protocolSepIndex+3);

			$tmp_pos = zbx_strpos($this->host,'/');
			if($tmp_pos!==false) $this->host=zbx_substring($this->host,0,$tmp_pos);

			$atIndex=zbx_strpos($this->host,'@');
			if($atIndex!==false){
				$credentials=zbx_substring($this->host,0,$atIndex);

				$colonIndex=zbx_strpos(credentials,':');
				if($colonIndex!==false){
					$this->username=zbx_substring($credentials,0,$colonIndex);
					$this->password=substr($credentials,$colonIndex);
				}
				else{
					$this->username=$credentials;
				}
				$this->host=substr($this->host,$atIndex+1);
			}

			$host_ipv6 = zbx_strpos($this->host,']');
			if($host_ipv6!==false){
				if($host_ipv6 < (zbx_strlen($this->host)-1)){
					$host_ipv6++;
					$host_less = substr($this->host,$host_ipv6);

					$portColonIndex=zbx_strpos($host_less,':');
					if($portColonIndex!==false){
						$this->host=zbx_substring($this->host,0,$host_ipv6);
						$this->port=substr($host_less,$portColonIndex+1);
					}
				}
			}
			else{
				$portColonIndex=zbx_strpos($this->host,':');

				if($portColonIndex!==false){
					$this->port=substr($this->host,$portColonIndex+1);
					$this->host=zbx_substring($this->host,0,$portColonIndex);
				}
			}

			$this->file = substr($this->url,$protocolSepIndex+3);
			$this->file = substr($this->file, zbx_strpos($this->file,'/'));

			if($this->file == $this->host) $this->file = '';
		}
		else{
			$this->file = $this->url;
		}

		$tmp_pos = zbx_strpos($this->file,'?');
		if($tmp_pos!==false) $this->file=zbx_substring($this->file, 0, $tmp_pos);

		$refSepIndex=zbx_strpos($url,'#');
		if($refSepIndex!==false){
			$this->file = zbx_substring($this->file,0,$refSepIndex);
			$this->reference = substr($url,zbx_strpos($url,'#')+1);
		}

		$this->path=$this->file;
		if(zbx_strlen($this->query)>0)		$this->file.='?'.$this->query;
		if(zbx_strlen($this->reference)>0)	$this->file.='#'.$this->reference;

		if(isset($_COOKIE['zbx_sessionid']))
			$this->setArgument('sid', substr($_COOKIE['zbx_sessionid'],16,16));
	}

	public function formatQuery(){
		$query = Array();

		foreach($this->arguments as $key => $value){
			if(is_null($value)) continue;
			if(is_array($value)){
				foreach($value as $vkey => $vvalue){
					if(is_array($vvalue)) continue;

					$query[] = $key.'['.$vkey.']='.rawurlencode($vvalue);
				}
			}
			else{
				$query[] = $key.'='.rawurlencode($value);
			}
		}
		$this->query = implode('&', $query);
	}

	public function formatGetArguments(){
		$this->arguments = $_GET;
		if(isset($_COOKIE['zbx_sessionid']))
			$this->setArgument('sid', substr($_COOKIE['zbx_sessionid'],16,16));
		$this->formatQuery();
	}

	public function formatArguments($query=null){
		if(is_null($query)){
			$this->arguments = $_REQUEST;
		}
		else{
			//$query=ltrim($query,'?');
			$args = explode('&',$query);
			foreach($args as $id => $arg){
				if(empty($arg)) continue;

				if(strpos($arg, '=') !== false){
					list($name, $value) = explode('=', $arg);
					$this->arguments[$name] = isset($value) ? urldecode($value):'';
				}
				else{
					$this->arguments[$arg] = '';
				}

			}
		}
		$this->formatQuery();
	}

	public function getUrl(){
		$this->formatQuery();

		$url = $this->protocol ? $this->protocol.'://' : '';

		$url .= $this->username ? $this->username : '';
		$url .= $this->password ? ':'.$this->password : '';

		if($this->username || $this->password){
			$url .= '@';
		}

		$url .= $this->host ? $this->host : '';
		$url .= $this->port ? ':'.$this->port : '';
		$url .= $this->path ? $this->path : '';
		$url .= $this->query ? '?'.$this->query : '';
		$url .= $this->reference ? '#'.urlencode($this->reference) : '';
//SDI($this->getProtocol().' : '.$this->getHost().' : '.$this->getPort().' : '.$this->getPath().' : '.$this->getQuery());
	return $url;
	}

	public function setPort($port){
		$this->port = $port;
	}

	public function getPort(){
		return $this->port;
	}

	public function setArgument($key,$value=''){
		$this->arguments[$key] = $value;
	}

	public function getArgument($key){
		if(isset($this->arguments[$key])) return $this->arguments[$key];
		else return NULL;
	}

	public function setQuery($query){
		$this->query = $query;
		$this->formatArguments();
		$this->formatQuery();
	}

	public function getQuery(){
		$this->formatQuery();
		return $this->query;
	}

	public function setProtocol($protocol){
		$this->protocol = $protocol;
	}

	/* Returns the protocol of $this URL, i.e. 'http' in the url 'http://server/' */
	public function getProtocol(){
		return $this->protocol;
	}

	public function setHost($host){
		$this->host = $host;
	}

	/* Returns the host name of $this URL, i.e. 'server.com' in the url 'http://server.com/' */
	public function getHost(){
		return $this->host;
	}

	public function setUserName($username){
		$this->username = $username;
	}

	/* Returns the user name part of $this URL, i.e. 'joe' in the url 'http://joe@server.com/' */
	public function getUserName(){
		return $this->username;
	}

	public function setPassword($password){
		$this->password = $password;
	}

	/* Returns the password part of $this url, i.e. 'secret' in the url 'http://joe:secret@server.com/' */
	public function getPassword(){
		return $this->password;
	}

	/* Returns the file part of $this url, i.e. everything after the host name. */
	public function getFile(){
		$url .= $this->path ? $this->path : '';
		$url .= $this->query ? '?'.$this->query : '';
		$url .= $this->reference ? '#'.urlencode($this->reference) : '';
		return $url;
	}

	public function setReference($reference){
		$this->reference = $reference;
	}

	/* Returns the reference of $this url, i.e. 'bookmark' in the url 'http://server/file.html#bookmark' */
	public function getReference(){
		return $this->reference;
	}

	public function setPath($path){
		$this->path = $path;
	}

	/* Returns the file path of $this url, i.e. '/dir/file.html' in the url 'http://server/dir/file.html' */
	public function getPath(){
		return $this->path;
	}

	public function toString(){
		return $this->getUrl();
	}
}
?>
