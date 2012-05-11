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
	function get_default_image($image=false, $imagetype=IMAGE_TYPE_ICON){
		if($image){
			$image = imagecreate(50, 50);
			$color = imagecolorallocate($image, 250, 50, 50);
			imagefill($image, 0, 0, $color);
		}
		else{
			$sql = 'SELECT i.imageid '.
				' FROM images i '.
				' WHERE '.DBin_node('i.imageid', false).
					' AND imagetype='.$imagetype.
				' ORDER BY name ASC';
			$result = DBselect($sql,1);
			if($image = DBfetch($result)) return $image;
			else{
				$image = array();
				$image['imageid'] = 0;
			}
		}

	return $image;
	}

	function get_image_by_imageid($imageid){

		$sql = 'SELECT * FROM images WHERE imageid='.$imageid;
		$result = DBselect($sql);
		if($row = DBfetch($result)){
			$row['image'] = zbx_unescape_image($row['image']);
		}

	return $row;
	}

	function zbx_unescape_image($image){
		global $DB;

		$result = ($image)?$image:0;
		if($DB['TYPE'] == "POSTGRESQL"){
			$result = pg_unescape_bytea($image);
		}
		else if($DB['TYPE'] == "SQLITE3"){
			$result = pack('H*', $image);
		}

	return $result;
	}

?>
