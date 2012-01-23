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
class CPie extends CGraphDraw{

	public function __construct($type = GRAPH_TYPE_PIE){
		parent::__construct($type);

		$this->background = false;
		$this->sum = false;
		$this->exploderad = 1;
		$this->exploderad3d = 3;
		$this->graphheight3d = 12;
		$this->shiftlegendright = 17*7 + 7 + 10; // count of static chars * px/char + for color rectangle + space
	}

/********************************************************************************************************/
// PRE CONFIG:	ADD / SET / APPLY
/********************************************************************************************************/

	public function addItem($itemid, $calc_fnc=CALC_FNC_AVG,$color=null, $type=null, $periods_cnt=null){

		$this->items[$this->num] = get_item_by_itemid($itemid);
		$this->items[$this->num]['description']=item_description($this->items[$this->num]);
		$host=get_host_by_hostid($this->items[$this->num]['hostid']);

		$this->items[$this->num]['host'] = $host['host'];
		$this->items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;
		$this->items[$this->num]['periods_cnt'] = is_null($periods_cnt) ? 0 : $periods_cnt;

		$this->num++;
	}

	public function set3DAngle($angle = 70){
		if(is_numeric($angle) && ($angle < 85) && ($angle > 10)){
			$this->angle3d = (int) $angle;
		}
		else {
			$this->angle3d = 70;
		}
	}

	public function switchPie3D($type = false){
		if($type){
			$this->type = $type;
		}
		else{
			switch($this->type){
				case GRAPH_TYPE_EXPLODED:
					$this->type = GRAPH_TYPE_3D_EXPLODED;
					break;
				case GRAPH_TYPE_3D_EXPLODED:
					$this->type = GRAPH_TYPE_EXPLODED;
					break;
				case GRAPH_TYPE_3D:
					$this->type = GRAPH_TYPE_PIE;
					break;
				case GRAPH_TYPE_PIE:
					$this->type = GRAPH_TYPE_3D;
					break;
				default:
					$this->type = GRAPH_TYPE_PIE;
			}
		}
	return $this->type;
	}

	public function switchPieExploded($type){
		if($type){
			$this->type = $type;
		}
		else{
			switch($this->type){
				case GRAPH_TYPE_EXPLODED:
					$this->type = GRAPH_TYPE_PIE;
					break;
				case GRAPH_TYPE_3D_EXPLODED:
					$this->type = GRAPH_TYPE_3D;
					break;
				case GRAPH_TYPE_3D:
					$this->type = GRAPH_TYPE_3D_EXPLODED;
					break;
				case GRAPH_TYPE_PIE:
					$this->type = GRAPH_TYPE_EXPLODED;
					break;
				default:
					$this->type = GRAPH_TYPE_PIE;
			}
		}
	return $this->type;
	}

	protected function calc3dheight($height){
		$this->graphheight3d = (int) ($height/20);
	}

	protected function calcExplodedCenter($anglestart,$angleend,$x,$y,$count){
		$count *= $this->exploderad;
		$anglemid = (int) (($anglestart + $angleend) / 2 );

		$y+= round($count * sin(deg2rad($anglemid)));
		$x+= round($count * cos(deg2rad($anglemid)));

	return array($x,$y);
	}

	protected function calcExplodedRadius($sizeX,$sizeY,$count){
		$count *= $this->exploderad*2;
		$sizeX -= $count;
		$sizeY -= $count;
	return array($sizeX,$sizeY);
	}

	protected function calc3DAngle($sizeX,$sizeY){
		$sizeY *= (GRAPH_3D_ANGLE / 90);
	return array($sizeX,round($sizeY));
	}

	protected function selectData(){
		$this->data = array();

		$now = time(NULL);

		if(isset($this->stime)){
			$this->from_time	= $this->stime;
			$this->to_time		= $this->stime + $this->period;
		}
		else{
			$this->to_time		= $now - 3600 * $this->from;
			$this->from_time	= $this->to_time - $this->period;
		}

		$p = $this->to_time - $this->from_time;		// graph size in time
		$z = $p - $this->from_time % $p;		//<strong></strong>
		$x = $this->sizeX;		// graph size in px
		$strvaluelength = 0;	// we need to know how long in px will be our legend

		for($i=0; $i < $this->num; $i++){

			$real_item = get_item_by_itemid($this->items[$i]['itemid']);
			$type = $this->items[$i]['calc_type'];

			$from_time	= $this->from_time;
			$to_time	= $this->to_time;

			$sql_arr = array();

// [ZBX-3249] for partitioned DB installs!
			if(ZBX_HISTORY_DATA_UPKEEP > -1) $real_item['history'] = ZBX_HISTORY_DATA_UPKEEP;
//---

			if((($real_item['history']*86400) > (time()-($from_time+$this->period/2))) &&				// should pick data from history or trends
				(($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)))		// is reasonable to take data from history?
			{
				$this->dataFrom = 'history';
				array_push($sql_arr,
					'SELECT h.itemid, '.
						' avg(h.value) AS avg,min(h.value) AS min, '.
						' max(h.value) AS max,max(h.clock) AS clock, max(i.lastvalue) as lst '.
					' FROM history h '.
						' LEFT JOIN items i ON h.itemid = i.itemid'.
					' WHERE h.itemid='.$this->items[$i]['itemid'].
						' AND h.clock>='.$from_time.
						' AND h.clock<='.$to_time.
					' GROUP BY h.itemid'
					,

					'SELECT hu.itemid, '.
						' avg(hu.value) AS avg,min(hu.value) AS min,'.
						' max(hu.value) AS max,max(hu.clock) AS clock, max(i.lastvalue) as lst'.
					' FROM history_uint hu '.
						' LEFT JOIN items i ON hu.itemid = i.itemid'.
					' WHERE hu.itemid='.$this->items[$i]['itemid'].
						' AND hu.clock>='.$from_time.
						' AND hu.clock<='.$to_time.
					' GROUP BY hu.itemid'
					);
			}
			else{
				$this->dataFrom = 'trends';
				array_push($sql_arr,
					'SELECT t.itemid, '.
						' avg(t.value_avg) AS avg,min(t.value_min) AS min,'.
						' max(t.value_max) AS max,max(t.clock) AS clock, max(i.lastvalue) as lst'.
					' FROM trends t '.
						' LEFT JOIN items i ON t.itemid = i.itemid'.
					' WHERE t.itemid='.$this->items[$i]['itemid'].
						' AND t.clock>='.$from_time.
						' AND t.clock<='.$to_time.
					' GROUP BY t.itemid'
					,

					'SELECT t.itemid, '.
						' avg(t.value_avg) AS avg,min(t.value_min) AS min,'.
						' max(t.value_max) AS max,max(t.clock) AS clock, max(i.lastvalue) as lst'.
					' FROM trends_uint t '.
						' LEFT JOIN items i ON t.itemid = i.itemid'.
					' WHERE t.itemid='.$this->items[$i]['itemid'].
						' AND t.clock>='.$from_time.
						' AND t.clock<='.$to_time.
					' GROUP BY t.itemid'
					);
			}

			$curr_data = &$this->data[$this->items[$i]['itemid']][$type];
			$curr_data->min = NULL;
			$curr_data->max = NULL;
			$curr_data->avg = NULL;
			$curr_data->clock = NULL;

			foreach($sql_arr as $sql){
				$result=DBselect($sql);

				while($row=DBfetch($result)){
					$curr_data->min	= $row['min'];
					$curr_data->max	= $row['max'];
					$curr_data->avg	= $row['avg'];
					$curr_data->lst	= $row['lst'];
					$curr_data->clock	= $row['clock'];
					$curr_data->shift_min = 0;
					$curr_data->shift_max = 0;
					$curr_data->shift_avg = 0;
				}
				unset($row);
			}

			switch($this->items[$i]['calc_fnc']){
				case CALC_FNC_MIN:
					$item_value = abs($curr_data->min);

					break;
				case CALC_FNC_MAX:
					$item_value = abs($curr_data->max);
					break;
				case CALC_FNC_LST:
					$item_value = abs($curr_data->lst);
					break;
				case CALC_FNC_AVG:
				default:
					$item_value = abs($curr_data->avg);
			}

			if($type == GRAPH_ITEM_SUM){
				$this->background = $i;
				$graph_sum = $item_value;
			}

			$this->sum += $item_value;
			$strvaluelength = max($strvaluelength,zbx_strlen(convert_units($item_value,$this->items[$i]['unit'])));
		}

		if(isset($graph_sum)) $this->sum = $graph_sum;
		$this->shiftlegendright += $strvaluelength * 7;
	}

	protected function drawLegend(){

		$shiftY = $this->shiftY + $this->shiftYLegend;

		$max_host_len=0;
		$max_desc_len=0;

		for($i=0;$i<$this->num;$i++){
			if(zbx_strlen($this->items[$i]['host'])>$max_host_len)		$max_host_len=zbx_strlen($this->items[$i]['host']);
			if(zbx_strlen($this->items[$i]['description'])>$max_desc_len)	$max_desc_len=zbx_strlen($this->items[$i]['description']);
		}

		for($i=0;$i<$this->num;$i++){

			$color = $this->getColor($this->items[$i]['color'], 0);
			$data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];

			switch($this->items[$i]['calc_fnc']){
				case CALC_FNC_MIN:
					$fnc_name = 'min';
					$datavalue = $data->min;
					break;
				case CALC_FNC_MAX:
					$fnc_name = 'max';
					$datavalue = $data->max;
					break;
				case CALC_FNC_LST:
					$fnc_name = 'last';
					$datavalue = $data->lst;
					break;
				case CALC_FNC_AVG:
				default:
					$fnc_name = 'avg';
					$datavalue = $data->avg;
			}

			$proc = ($datavalue * 100)/ $this->sum;

//			convert_units($datavalue,$this->items[$i]["units"]),
			if(isset($data) && isset($datavalue)){
				$strvalue = sprintf(S_VALUE.': %s ('.(round($proc)!=$proc? '%0.2f':'%s').'%%)',convert_units($datavalue,$this->items[$i]['units']),$proc);

				$str = sprintf('%s: %s [%s] ',
						str_pad($this->items[$i]['host'],$max_host_len,' '),
						str_pad($this->items[$i]['description'],$max_desc_len,' '),
						$fnc_name);
			}
			else{
				$strvalue = sprintf(S_VALUE.': '.S_NO_DATA_SMALL);
				$str = sprintf('%s: %s [ '.S_NO_DATA_SMALL.' ]',
					str_pad($this->items[$i]['host'],$max_host_len,' '),
					str_pad($this->items[$i]['description'],$max_desc_len,' '));
			}


			imagefilledrectangle($this->im,
							$this->shiftXleft,
							$this->sizeY+$shiftY+14*$i - 5,
							$this->shiftXleft+10,
							$this->sizeY+$shiftY+5+14*$i,
							$color);

			imagerectangle($this->im,
							$this->shiftXleft,
							$this->sizeY+$shiftY+14*$i - 5,
							$this->shiftXleft+10,
							$this->sizeY+$shiftY+5+14*$i,
							$this->getColor('Black No Alpha')
						);

			$dims = imageTextSize(8, 0, $str);
			imageText($this->im,
						8,
						0,
						$this->shiftXleft+15,
						$this->sizeY+$shiftY+14*$i+5,
						$this->getColor($this->graphtheme['textcolor'],0),
						$str
					);


			$shiftX = $this->fullSizeX - $this->shiftlegendright - $this->shiftXright + 25;
	//		SDI($shiftX.','.$this->sizeX);

			imagefilledrectangle($this->im,
						$shiftX-10,
						$this->shiftY+10+14*$i,
						$shiftX,
						$this->shiftY+10+10+14*$i,
						$color
					);

			imagerectangle($this->im,
						$shiftX-10,
						$this->shiftY+10+14*$i,
						$shiftX,
						$this->shiftY+10+10+14*$i,
						$this->GetColor('Black No Alpha')
					);

			imagetext($this->im,
						8,
						0,
						$shiftX+5,
						$this->shiftY+10+14*$i+10,
						$this->getColor($this->graphtheme['textcolor'],0),
						$strvalue
				);
		}

		if($this->sizeY < 120) return;
	}


	protected function drawElementPie($values){

		$sum = $this->sum;

		if($this->background !== false){
			$least = 0;
			foreach($values as $item => $value){
	//			SDI($item.' : '.$value.' , '.$this->background);
				if($item != $this->background){
					$least += $value;
				}
			}
			$values[$this->background] -= $least;
		}

		if($sum <= 0){
			$this->items[0]['color'] = 'FFFFFF';
			$values = array(0 => 1);
			$sum = 1;
		}
	//		asort($values);

		$sizeX = $this->sizeX;
		$sizeY = $this->sizeY;

		if($this->type == GRAPH_TYPE_EXPLODED){
			list($sizeX,$sizeY) = $this->calcExplodedRadius($sizeX,$sizeY,count($values));
		} else {
			$sizeX =(int) $sizeX * 0.95;
			$sizeY =(int) $sizeY * 0.95;
		}

		$xc = $x = (int) $this->sizeX/2 + ($this->shiftXleft);
		$yc = $y = (int) $this->sizeY/2 + $this->shiftY;

		$anglestart = 0;
		$angleend = 0;
		foreach($values as $item => $value){
			$angleend += (int)(360 * $value/$sum)+1;
			$angleend = ($angleend > 360)?(360):($angleend);
			if(($angleend - $anglestart) < 1) continue;

			if($this->type == GRAPH_TYPE_EXPLODED){
				list($x,$y) = $this->calcExplodedCenter($anglestart,$angleend,$xc,$yc,count($values));
			}

			imagefilledarc($this->im, $x, $y, $sizeX, $sizeY, $anglestart, $angleend, $this->GetColor($this->items[$item]['color'],0), IMG_ARC_PIE);
			imagefilledarc($this->im, $x, $y, $sizeX, $sizeY, $anglestart, $angleend, $this->GetColor('Black'), IMG_ARC_PIE|IMG_ARC_EDGED|IMG_ARC_NOFILL);
			$anglestart = $angleend;
		}
	//		imageline($this->im, $xc, $yc, $xc, $yc, $this->GetColor('Black'));
	}

	protected function drawElementPie3D($values){

		$sum = $this->sum;

		if($this->background !== false){
			$least = 0;
			foreach($values as $item => $value){
				if($item != $this->background){
					$least += $value;
				}
			}
			$values[$this->background] -= $least;
		}

		if($sum <= 0){
			$this->items[0]['color'] = 'FFFFFF';
			$values = array(0 => 1);
			$sum = 1;
		}
	//		asort($values);

		$sizeX = $this->sizeX;
		$sizeY = $this->sizeY;

		$this->exploderad = $this->exploderad3d;

		if($this->type == GRAPH_TYPE_3D_EXPLODED){
			list($sizeX,$sizeY) = $this->calcExplodedRadius($sizeX,$sizeY,count($values));
		}

		list($sizeX,$sizeY) = $this->calc3DAngle($sizeX,$sizeY);

		$xc = $x = (int) $this->sizeX/2 + ($this->shiftXleft);
		$yc = $y = (int) $this->sizeY/2 + $this->shiftY;

	// ----- bottom angle line ----
		$anglestart = 0;
		$angleend = 0;
		foreach($values as $item => $value){

			$angleend += (int)(360 * $value/$sum)+1;
			$angleend = ($angleend > 360)?(360):($angleend);
			if(($angleend - $anglestart) < 1) continue;

			if($this->type == GRAPH_TYPE_3D_EXPLODED){
				list($x,$y) = $this->calcExplodedCenter($anglestart,$angleend,$xc,$yc,count($values));
			}
			imagefilledarc($this->im, $x, $y+$this->graphheight3d+1, $sizeX, $sizeY, $anglestart, $angleend, $this->GetShadow($this->items[$item]['color'],0), IMG_ARC_PIE);
			imagefilledarc($this->im, $x, $y+$this->graphheight3d+1, $sizeX, $sizeY, $anglestart, $angleend, $this->GetColor('Black'), IMG_ARC_PIE|IMG_ARC_EDGED|IMG_ARC_NOFILL);
			$anglestart = $angleend;
		}//*/

	//	------ 3d effect	------
		for ($i = $this->graphheight3d; $i > 0; $i--) {
			$anglestart = 0;
			$angleend = 0;
			foreach($values as $item => $value){
				$angleend += (int)(360 * $value/$sum)+1;
				$angleend = ($angleend > 360)?(360):($angleend);

				if(($angleend - $anglestart) < 1) continue;
				elseif($this->sum == 0) continue;

				if($this->type == GRAPH_TYPE_3D_EXPLODED){
					list($x,$y) = $this->calcExplodedCenter($anglestart,$angleend,$xc,$yc,count($values));
				}

				imagefilledarc($this->im, $x, $y+$i, $sizeX, $sizeY, $anglestart, $angleend, $this->GetShadow($this->items[$item]['color'],0), IMG_ARC_PIE);
				$anglestart = $angleend;
			}
		}

		$anglestart = 0;
		$angleend = 0;
		foreach($values as $item => $value){

			$angleend += (int)(360 * $value/$sum)+1;
			$angleend = ($angleend > 360)?(360):($angleend);
			if(($angleend - $anglestart) < 1) continue;

			if($this->type == GRAPH_TYPE_3D_EXPLODED){
				list($x,$y) = $this->calcExplodedCenter($anglestart,$angleend,$xc,$yc,count($values));
			}

			imagefilledarc($this->im, $x, $y, $sizeX, $sizeY, $anglestart, $angleend, $this->GetColor($this->items[$item]['color'],0), IMG_ARC_PIE);
			imagefilledarc($this->im, $x, $y, $sizeX, $sizeY, $anglestart, $angleend, $this->GetColor('Black'), IMG_ARC_PIE|IMG_ARC_EDGED|IMG_ARC_NOFILL);
			$anglestart = $angleend;
		}//*/
	}

	public function draw(){
		$start_time=getmicrotime();
		set_image_header();
		check_authorisation();

		$this->selectData();

		$this->shiftY = 30;
		$this->shiftYLegend = 20;
		$this->shiftXleft = 10;
		$this->shiftXright = 0;

		$this->fullSizeX = $this->sizeX;
		$this->fullSizeY = $this->sizeY;

		if(($this->sizeX < 300) || ($this->sizeY < 200)) $this->showLegend(0);

		if($this->drawLegend == 1){
			$this->sizeX -= ($this->shiftXleft+$this->shiftXright+$this->shiftlegendright);
			$this->sizeY -= ($this->shiftY+$this->shiftYLegend+12*$this->num+8);
		}
		else {
			$this->sizeX -= ($this->shiftXleft*2);
			$this->sizeY -= ($this->shiftY*2);
		}

//	SDI($this->sizeX.','.$this->sizeY);

		$this->sizeX = min($this->sizeX,$this->sizeY);
		$this->sizeY = min($this->sizeX,$this->sizeY);

		$this->calc3dheight($this->sizeY);

		$this->exploderad = (int) $this->sizeX / 100;
		$this->exploderad3d = (int) $this->sizeX / 60;

		if(function_exists('ImageColorExactAlpha')&&function_exists('ImageCreateTrueColor')&&@imagecreatetruecolor(1,1))
			$this->im = imagecreatetruecolor($this->fullSizeX,$this->fullSizeY);
		else
			$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);


		$this->initColors();
		$this->drawRectangle();
		$this->drawHeader();

		$maxX = $this->sizeX;

// For each metric
		for($item = 0; $item < $this->num; $item++){
			$minY = $this->m_minY[$this->items[$item]['axisside']];
			$maxY = $this->m_maxY[$this->items[$item]['axisside']];

			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if(!isset($data))	continue;

			$drawtype	= $this->items[$item]['drawtype'];

			$max_color	= $this->GetColor('ValueMax');
			$avg_color	= $this->GetColor($this->items[$item]['color']);
			$min_color	= $this->GetColor('ValueMin');
			$minmax_color	= $this->GetColor('ValueMinMax');

			$calc_fnc = $this->items[$item]['calc_fnc'];

			switch($calc_fnc){
				case CALC_FNC_MAX:
					$values[$item] = abs($data->max);
					break;
				case CALC_FNC_MIN:
					$values[$item] = abs($data->min);
					break;
				case CALC_FNC_AVG:
					$values[$item] = abs($data->avg);
					break;
				case CALC_FNC_LST:
					$values[$item] = abs($data->lst);
					break;
			}
		}

		switch($this->type){
			case GRAPH_TYPE_EXPLODED:
				$this->drawElementPie($values);
				break;
			case GRAPH_TYPE_3D:
				$this->drawElementPie3D($values);
				break;
			case GRAPH_TYPE_3D_EXPLODED:
				$this->drawElementPie3D($values);
				break;
			default:
				$this->drawElementPie($values);
				break;
		}

		$this->drawLogo();
		if($this->drawLegend == 1)	$this->drawLegend();

		$str=sprintf('%0.2f',(getmicrotime()-$start_time));
		imagestring($this->im, 0,$this->fullSizeX-210,$this->fullSizeY-12,'Data from '.$this->dataFrom.'. Generated in '.$str.' sec', $this->getColor('Gray'));

		unset($this->items, $this->data);

		ImageOut($this->im);
	}
}
?>
