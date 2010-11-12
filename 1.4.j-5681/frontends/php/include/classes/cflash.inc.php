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
	/* private */ class CFlashEmbed extends CTag
	{
		function CFlashEmbed($src=NULL, $width = NULL, $height = NULL)
		{
			parent::CTag('embed');
			$this->options['allowScriptAccess'] = 'sameDomain';
			$this->options['type'] = 'application/x-shockwave-flash';
			$this->options['pluginspage']  = 'http://www.macromedia.com/go/getflashplayer';
			$this->options['align'] = 'middle';
			$this->options['quality'] = 'high';
			
			$this->options['width'] = $width;
			$this->options['height'] = $height;
			$this->options['src'] = $src;
		}
		function SetWidth($value)
		{
			$this->options['width']  = $value;
		}
		function SetHeight($value)
		{
			$this->options['height'] = $value;
		}
		function SetSrc($value)
		{
			$this->options['src'] = $value;
		}
	}

	/* private */ class CParam extends CTag
	{
		function CParam($name,$value)
		{
			parent::CTag("param","no");
			$this->options['name'] = $name;
			$this->options['value'] = $value;
		}
	}

	/* public */ class CFlash extends CTag
	{
		/*
		var $SrcParam;
		var $EmbededFlash; */

		function CFlash($src=NULL, $width = NULL, $height = NULL)
		{
			parent::CTag("object",'yes');
			$this->options['classid'] = 'clsid:d27cdb6e-ae6d-11cf-96b8-444553540000';
			$this->options['codebase'] = 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0';
			$this->options['align'] = 'middle';

			$this->AddItem(new CParam("allowScriptAccess","sameDomain"));
			$this->AddItem(new CParam("quality","high"));

			$this->SrcParam = new CParam("movie",$src);
			$this->EmbededFlash = new CFlashEmbed();

			$this->SetWidth($width);
			$this->SetHeight($height);
			$this->SetSrc($src);
		}
		function SetWidth($value)
		{
			$this->options['width'] = $value;
			$this->EmbededFlash->options['width'] = $value;
		}
		function SetHeight($value)
		{
			$this->options['height'] = $value;
			$this->EmbededFlash->options['height'] = $value;
		}
		function SetSrc($value)
		{
			$this->SrcParam->options['value'] = $value;
			$this->EmbededFlash->options['src'] = $value;
		}
		function BodyToString()
		{
			$ret = parent::BodyToString();
			$ret .= $this->SrcParam->ToString();
			$ret .= $this->EmbededFlash->ToString();
			return $ret;
		}
	}
?>
