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
	class CMap extends CTag
	{
/* public */
		function CMap($name="")
		{
			parent::CTag("map","yes");
			$this->SetName($name);
		}
		function AddRectArea($x1,$y1,$x2,$y2,$href,$alt)
		{ 
			return $this->AddArea(array($x1,$y1,$x2,$y2),$href,$alt,'rect'); 
		}
		function AddArea($coords,$href,$alt,$shape)
		{
			return $this->AddItem(new CArea($coords,$href,$alt,$shape));
		}
		function AddItem($value)
		{
			if(strtolower(get_class($value)) != 'carea')
				return $this->error("Incorrect value for AddItem [$value]");

			return parent::AddItem($value);
		}
	}

	class CArea extends CTag
	{
		function CArea($coords,$href,$alt,$shape)
		{
			parent::CTag("area","no");
			$this->SetCoords($coords);
			$this->SetShape($shape);
			$this->SetHref($href);
			$this->SetAlt($alt);
		}
		function SetCoords($value)
		{
			if(!is_array($value))
				return $this->error("Incorrect value for SetCoords [$value]");
			if(count($value)<3)
				return $this->error("Incorrect values count for SetCoords [".count($value)."]");

			$str_val = "";
			foreach($value as $val)
			{
				if(!is_numeric($val))
					return $this->error("Incorrect value for SetCoords [$val]");

				$str_val .= $val.",";
			}
			$this->AddOption("coords",trim($str_val,','));
		}
		function SetShape($value)
		{
			if(!is_string($value))
				return $this->error("Incorrect value for SetShape [$value]");

			$this->AddOption("shape",$value);
		}
		function SetHref($value)
		{
			if(!is_string($value))
				return $this->error("Incorrect value for SetHref [$value]");

			$this->AddOption("href",$value);
		}
		function SetAlt($value)
		{
			if(!is_string($value))
				return $this->error("Incorrect value for SetAlt [$value]");

			$this->AddOption("alt",$value);
		}
	}
?>
