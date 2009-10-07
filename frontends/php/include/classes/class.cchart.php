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

class CChart extends CGraphDraw{

	public function __construct($type = GRAPH_TYPE_NORMAL){
		parent::__construct($type);

		$this->yaxismin = null;
		$this->yaxismax = null;

		$this->triggers = array();

		$this->ymin_type = GRAPH_YAXIS_TYPE_CALCULATED;
		$this->ymax_type = GRAPH_YAXIS_TYPE_CALCULATED;

		$this->yaxisright=0;
		$this->yaxisleft=0;

		$this->ymin_itemid = 0;
		$this->ymax_itemid = 0;

		$this->legendOffsetY = 100;

		$this->percentile = array(
				'left' => array(
					'percent' => 0,		// draw percentage line
					'value'	=> 0		// calculated percentage value left y axis
				),

				'right' => array(
					'percent' => 0,		// draw percentage line
					'value'	=> 0		// calculated percentage value right y axis
				)
			);

		$this->m_showWorkPeriod = 1;
		$this->m_showTriggers = 1;

		$this->zero = array();
		$this->graphOrientation = array(
				GRAPH_YAXIS_SIDE_LEFT=>'', 
				GRAPH_YAXIS_SIDE_RIGHT=>''
			);

		$this->grid = array();				// vertical & horizontal grids params

		$this->gridLinesCount = array();	// How many grids to draw
		$this->gridPixels = 25;				// optimal grid size

		$this->graphtheme = array(
			'description' => 'default',
			'frontendtheme' => 'default.css',
			'textcolor' => '202020',
			'highlightcolor' => 'aa4444',
			'backgroundcolor' => 'f0f0f0',
			'graphcolor' => 'ffffff',
			'graphbordercolor' => '333333',
			'gridcolor' => 'cccccc',
			'maingridcolor' => 'aaaaaa',
			'gridbordercolor' => '000000',
			'noneworktimecolor' => 'eaeaea',
			'leftpercentilecolor' => '00AA00',
			'righttpercentilecolor' => 'AA0000',
			'legendview' => '1',
			'gridview' => '1'
		);

		$this->applyGraphTheme();
	}

/********************************************************************************************************/
// PRE CONFIG:	ADD / SET / APPLY
/********************************************************************************************************/

	public function applyGraphTheme($description=null){
		global $USER_DETAILS;

		if(!is_null($description)){
			$sql_where = ' AND gt.description='.zbx_dbstr($description);
		}
		else{
			$config=select_config();
			if(isset($config['default_theme']) && file_exists('styles/'.$config['default_theme'])){
				$css = $config['default_theme'];
			}

			if(isset($USER_DETAILS['theme']) && ($USER_DETAILS['theme']!=ZBX_DEFAULT_CSS) && ($USER_DETAILS['alias']!=ZBX_GUEST_USER)){
				if(file_exists('styles/'.$USER_DETAILS['theme'])){
					$css = $USER_DETAILS['theme'];
				}
			}

			$sql_where = ' AND gt.theme='.zbx_dbstr($css);
		}

		$sql = 'SELECT gt.* '.
				' FROM graph_theme gt '.
				' WHERE '.DBin_node('gt.graphthemeid').
				$sql_where;
//SDI($sql);
		$res = DBselect($sql);
		if($theme = DBfetch($res)){
			$this->graphtheme = $theme;
		}
	}

	public function updateShifts(){
		if( ($this->yaxisleft == 1) && ($this->yaxisright == 1)){
			$this->shiftXleft = 100;
			$this->shiftXright = 100;
		}
		else if($this->yaxisleft == 1){
			$this->shiftXleft = 100;
			$this->shiftXright = 50;
		}
		else if($this->yaxisright == 1){
			$this->shiftXleft = 50;
			$this->shiftXright = 100;
		}
//			$this->sizeX = $this->sizeX - $this->shiftXleft-$this->shiftXright;
	}

	public function getShifts(){
		$shifts = array();
		$shifts['shiftXleft'] = $this->shiftXleft;
		$shifts['shiftXright'] = $this->shiftXright;
		$shifts['shiftY'] = $this->shiftY;

		$shifts['height'] = $this->sizeY;
		$shifts['width'] = $this->sizeX;

	return $shifts;
	}

	public function showWorkPeriod($value){
		$this->m_showWorkPeriod = ($value==1)?1:0;
	}

	public function showTriggers($value){
		$this->m_showTriggers = ($value==1)?1:0;
	}

	public function addItem($itemid, $axis=GRAPH_YAXIS_SIDE_LEFT, $calc_fnc=CALC_FNC_AVG,
				$color=null, $drawtype=null, $type=null, $periods_cnt=null){
		if($this->type == GRAPH_TYPE_STACKED /* stacked graph */)
			$drawtype = GRAPH_ITEM_DRAWTYPE_FILLED_REGION;

		$this->items[$this->num] = get_item_by_itemid($itemid);
		$this->items[$this->num]['description']=item_description($this->items[$this->num]);
		$host=get_host_by_hostid($this->items[$this->num]['hostid']);

		$this->items[$this->num]['host'] = $host['host'];
		$this->items[$this->num]['color'] = is_null($color) ? 'Dark Green' : $color;
		$this->items[$this->num]['drawtype'] = is_null($drawtype) ? GRAPH_ITEM_DRAWTYPE_LINE : $drawtype;
		$this->items[$this->num]['axisside'] = is_null($axis) ? GRAPH_YAXIS_SIDE_RIGHT : $axis;
		$this->items[$this->num]['calc_fnc'] = is_null($calc_fnc) ? CALC_FNC_AVG : $calc_fnc;
		$this->items[$this->num]['calc_type'] = is_null($type) ? GRAPH_ITEM_SIMPLE : $type;
		$this->items[$this->num]['periods_cnt'] = is_null($periods_cnt) ? 0 : $periods_cnt;

		if($this->items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_LEFT)
			$this->yaxisleft=1;

		if($this->items[$this->num]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT)
			$this->yaxisright=1;
//		SDI($this->items);

		$this->num++;
	}

	public function setGraphOrientation($value, $axisside){
		if($value < 0){
			$this->graphOrientation[$axisside] = '-';
		}
		else if(zbx_empty($this->graphOrientation[$axisside]) && ($value > 0)){
			$this->graphOrientation[$axisside] = '+';
		}
	return $this->graphOrientation[$axisside];
	}

	public function setYMinAxisType($yaxistype){
		$this->ymin_type=$yaxistype;
	}

	public function setYMaxAxisType($yaxistype){
		$this->ymax_type=$yaxistype;
	}

	public function setYAxisMin($yaxismin){
		$this->yaxismin=$yaxismin;
	}

	public function setYAxisMax($yaxismax){
		$this->yaxismax=$yaxismax;
	}

	public function setYMinItemId($itemid){
		$this->ymin_itemid=$itemid;
	}

	public function setYMaxItemId($itemid){
		$this->ymax_itemid=$itemid;
	}

	public function setLeftPercentage($percentile){
		$this->percentile['left']['percent'] = $percentile;
	}

	public function setRightPercentage($percentile){
		$this->percentile['right']['percent'] = $percentile;
	}

	protected function selectData(){

		$this->data = array();

		$now = time(NULL);

		if(isset($this->stime)){
			$this->from_time	= $this->stime; // + timeZone offset
			$this->to_time		= $this->stime + $this->period; // + timeZone offset
		}
		else{
			$this->to_time		= $now - 3600 * $this->from;
			$this->from_time	= $this->to_time - $this->period; // + timeZone offset
		}

		$p = $this->to_time - $this->from_time;		// graph size in time
		$z = $p - $this->from_time % $p;			// graphsize - mod(from_time,p) for Oracle...
		$x = $this->sizeX;							// graph size in px

		for($i=0; $i < $this->num; $i++){

			$real_item = get_item_by_itemid($this->items[$i]['itemid']);

			if(!isset($this->axis_valuetype[$this->items[$i]['axisside']])){
				$this->axis_valuetype[$this->items[$i]['axisside']] = $real_item['value_type'];
			}
			else if($this->axis_valuetype[$this->items[$i]['axisside']] != $real_item['value_type']){
				$this->axis_valuetype[$this->items[$i]['axisside']] = ITEM_VALUE_TYPE_FLOAT;
			}

			$type = $this->items[$i]['calc_type'];
			if($type == GRAPH_ITEM_AGGREGATED) {
				/* skip current period */
				$from_time	= $this->from_time - $this->period * $this->items[$i]['periods_cnt'];
				$to_time	= $this->from_time;
			}
			else {
				$from_time	= $this->from_time;
				$to_time	= $this->to_time;
			}

			$calc_field = 'round('.$x.'*(mod('.zbx_dbcast_2bigint('clock').'+'.$z.','.$p.'))/('.$p.'),0)';  /* required for 'group by' support of Oracle */

			$sql_arr = array();

			if((($real_item['history']*86400) > (time()-($this->from_time+$this->period/2))) &&			// should pick data from history or trends
				(($this->period / $this->sizeX) <= (ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)))		// is reasonable to take data from history?
			{
				$this->dataFrom = 'history';
				array_push($sql_arr,
					'SELECT itemid,'.$calc_field.' as i,'.
						' count(*) as count,avg(value) as avg,min(value) as min,'.
						' max(value) as max,max(clock) as clock'.
					' FROM history '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					,

					'SELECT itemid,'.$calc_field.' as i,'.
						' count(*) as count,avg(value) as avg,min(value) as min,'.
						' max(value) as max,max(clock) as clock'.
					' FROM history_uint '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					);
			}
			else{
				$this->dataFrom = 'trends';
				array_push($sql_arr,
					'SELECT itemid,'.$calc_field.' as i,'.
						' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
						' max(value_max) as max,max(clock) as clock'.
					' FROM trends '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					,

					'SELECT itemid,'.$calc_field.' as i,'.
						' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
						' max(value_max) as max,max(clock) as clock'.
					' FROM trends_uint '.
					' WHERE itemid='.$this->items[$i]['itemid'].
						' AND clock>='.$from_time.
						' AND clock<='.$to_time.
					' GROUP BY itemid,'.$calc_field
					);

				$this->items[$i]['delay'] = max($this->items[$i]['delay'],3600);
			}
//SDI($sql_arr);
			$curr_data = &$this->data[$this->items[$i]['itemid']][$type];
			$curr_data->count = NULL;
			$curr_data->min = NULL;
			$curr_data->max = NULL;
			$curr_data->avg = NULL;
			$curr_data->clock = NULL;

			foreach($sql_arr as $sql){
				$result=DBselect($sql);

				while($row=DBfetch($result)){
					$idx=$row['i']-1;
					if($idx<0) continue;
/* --------------------------------------------------
	We are taking graph on 1px more than we need,
	and here we are skiping first px, because of MOD (in SELECT),
	it combines prelast point (it would be last point if not that 1px in begining)
	and first point, but we still losing prelast point :(
	but now we've got the first point.
--------------------------------------------------*/

					$curr_data->count[$idx]	= $row['count'];
					$curr_data->min[$idx]	= $row['min'];
					$curr_data->max[$idx]	= $row['max'];
					$curr_data->avg[$idx]	= $row['avg'];
					$curr_data->clock[$idx]	= $row['clock'];
					$curr_data->shift_min[$idx] = 0;
					$curr_data->shift_max[$idx] = 0;
					$curr_data->shift_avg[$idx] = 0;
				}
				
				$this->setGraphOrientation(min($curr_data->min), $this->items[$i]['axisside']);
				unset($row);
			}
			/* calculate missed points */
			$first_idx = 0;
			/*
				first_idx - last existed point
				ci - current index
				cj - count of missed in onetime
				dx - offset to first value (count to last existed point)
			//*/

			for($ci = 0, $cj=0; $ci < $this->sizeX; $ci++){
				if(!isset($curr_data->count[$ci]) || $curr_data->count[$ci] == 0){
					$curr_data->count[$ci] = 0;
					$curr_data->shift_min[$ci] = 0;
					$curr_data->shift_max[$ci] = 0;
					$curr_data->shift_avg[$ci] = 0;
					$cj++;
				}
				else if($cj > 0){
					$dx = $cj + 1;

					$first_idx = $ci - $dx;

					if($first_idx < 0)	$first_idx = $ci; // if no data FROM start of graph get current data as first data

					for(;$cj > 0; $cj--){
						if(($dx < ($this->sizeX/20)) && ($this->type == GRAPH_TYPE_STACKED)){
							$curr_data->count[$ci - ($dx - $cj)] = 1;
						}

						foreach(array('clock','min','max','avg') as $var_name){
							$var = &$curr_data->$var_name;

							if($first_idx == $ci && $var_name == 'clock'){
								$var[$ci - ($dx - $cj)] = $var[$first_idx] - (($p / $this->sizeX) * ($dx - $cj));
								continue;
							}

							$dy = $var[$ci] - $var[$first_idx];
							$var[$ci - ($dx - $cj)] = $var[$first_idx] + ($cj * $dy) / $dx;
						}
					}
				}
			}

			if($cj > 0 && $ci > $cj){
				$dx = $cj + 1;

				$first_idx = $ci - $dx;

				for(;$cj > 0; $cj--){

//					if($dx < ($this->sizeX/20))			//($this->type == GRAPH_TYPE_STACKED)
//						$curr_data->count[$first_idx + ($dx - $cj)] = 1;

					foreach(array('clock','min','max','avg') as $var_name){
						$var = &$curr_data->$var_name;

						if( $var_name == 'clock'){
							$var[$first_idx + ($dx - $cj)] = $var[$first_idx] + (($p / $this->sizeX) * ($dx - $cj));
							continue;
						}
						$var[$first_idx + ($dx - $cj)] = $var[$first_idx];
					}
				}
			}
			/* end of missed points calculation */
		}

		/* calculte shift for stacked graphs */

		if($this->type == GRAPH_TYPE_STACKED){
			for($i=1; $i<$this->num; $i++){
				$curr_data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];

				if(!isset($curr_data))	continue;

				for($j = $i-1; $j >= 0; $j--){
					if($this->items[$j]['axisside'] != $this->items[$i]['axisside']) continue;

					$prev_data = &$this->data[$this->items[$j]['itemid']][$this->items[$j]['calc_type']];

					if(!isset($prev_data))	continue;

					for($ci = 0; $ci < $this->sizeX; $ci++){
						foreach(array('min','max','avg') as $var_name){
							$shift_var_name	= 'shift_'.$var_name;
							$curr_shift	= &$curr_data->$shift_var_name;
							$curr_var	= &$curr_data->$var_name;
							$prev_shift	= &$prev_data->$shift_var_name;
							$prev_var	= &$prev_data->$var_name;
							$curr_shift[$ci] = $prev_var[$ci] + $prev_shift[$ci];
						}
					}
					break;
				}
			}
		}
		/* end calculation of stacked graphs */
	}
/********************************************************************************************************/
// CALCULATIONS
/********************************************************************************************************/

	protected function calcTriggers(){
		$this->triggers = array();
		if($this->m_showTriggers != 1) return;
		if($this->num != 1) return; // skip multiple graphs

		$max = 3;
		$cnt = 0;

		$sql = 'SELECT distinct tr.triggerid,tr.expression,tr.priority '.
				' FROM triggers tr,functions f,items i'.
				' WHERE tr.triggerid=f.triggerid '.
					" AND f.function IN ('last','min','avg','max') ".
					' AND tr.status='.TRIGGER_STATUS_ENABLED.
					' AND i.itemid=f.itemid '.
					' AND f.itemid='.$this->items[0]['itemid'].
				' ORDER BY tr.priority';
		$db_triggers = DBselect($sql);
		while(($trigger = DBfetch($db_triggers)) && ($cnt < $max)){
			$db_fnc_cnt = DBselect('SELECT count(*) as cnt FROM functions f WHERE f.triggerid='.$trigger['triggerid']);
			$fnc_cnt = DBfetch($db_fnc_cnt);
			if($fnc_cnt['cnt'] != 1) continue;

			CUserMacro::resolveTrigger($trigger);

			if(!eregi('\{([0-9]{1,})\}([\<\>\=]{1})([0-9\.]{1,})([K|M|G]{0,1})',$trigger['expression'],$arr))
				continue;

			$val = $arr[3];
			if(strcasecmp($arr[4],'K') == 0)	$val *= 1024;
			else if(strcasecmp($arr[4],'M') == 0)	$val *= 1048576; //1024*1024;
			else if(strcasecmp($arr[4],'G') == 0)	$val *= 1073741824; //1024*1024*1024;

			$minY = $this->m_minY[$this->items[0]['axisside']];
			$maxY = $this->m_maxY[$this->items[0]['axisside']];

			if($val <= $minY || $val >= $maxY)	continue;

			if($trigger['priority'] == 5)		$color = 'Priority Disaster';
			elseif($trigger['priority'] == 4)	$color = 'Priority High';
			elseif($trigger['priority'] == 3)	$color = 'Priority Average';
			else 					$color = 'Priority';

			array_push($this->triggers,array(
				'y' => $this->sizeY - (($val-$minY) / ($maxY-$minY)) * $this->sizeY + $this->shiftY,
				'color' => $color,
				'description' => 'trigger: '.expand_trigger_description($trigger['triggerid']).' ['.$arr[2].' '.$arr[3].$arr[4].']'
				));
			++$cnt;
		}

	}

//Calculates percentages for left & right y axis
	protected function calcPercentile(){
		if($this->type != GRAPH_TYPE_NORMAL){
			return ;
		}

		$values = array(
			'left' => array(),
			'right'=> array()
		);

		$maxX = $this->sizeX;

// For each metric
		for($item = 0; $item < $this->num; $item++){
			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if(!isset($data))	continue;
// For each X
			for($i = 0; $i < $maxX; $i++){  // new point
				if(($data->count[$i] == 0) && ($i != ($maxX-1))) continue;

				$min = $data->min[$i];
				$max = $data->max[$i];
				$avg = $data->avg[$i];

				switch($this->items[$item]['calc_fnc']){
					case CALC_FNC_MAX:
						$value = $max;
						break;
					case CALC_FNC_MIN:
						$value = $min;
						break;
					case CALC_FNC_ALL:
					case CALC_FNC_AVG:
					default:
						$value = $avg;
				}

				if($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT){
					$values['left'][] = $value;
				}
				else{
					$values['right'][] = $value;
				}
			}
		}

		foreach($this->percentile as $side => $percentile){
			if(($percentile['percent']>0) && !empty($values[$side])){
				sort($values[$side]);
				$percent = (int) ((count($values[$side]) * $percentile['percent'] / 100) + 0.5);
//SDI($percent);
				$this->percentile[$side]['value'] = $values[$side][$percent];
				unset($values[$side]);
			}
//SDI($side.' : '.$this->percentile[$side]['value']);
		}
	}

// Calculation of minimum Y axis
	protected function calculateMinY($side){
		if($this->ymin_type==GRAPH_YAXIS_TYPE_FIXED){
			return $this->yaxismin;
		}
		else if($this->ymin_type==GRAPH_YAXIS_TYPE_ITEM_VALUE){
			$item = get_item_by_itemid($this->ymin_itemid);
			if($item && isset($item['lastvalue']) && !is_null($item['lastvalue']))
				return $item['lastvalue'];
		}

		unset($minY);
		for($i=0;$i<$this->num;$i++){

			if($this->items[$i]['axisside'] != $side)
				continue;

			foreach(array(GRAPH_ITEM_SIMPLE, GRAPH_ITEM_AGGREGATED) as $type){

				if(!isset($this->data[$this->items[$i]['itemid']][$type]))
					continue;

				$data = &$this->data[$this->items[$i]['itemid']][$type];

				if(!isset($data))	continue;

				if($type == GRAPH_ITEM_AGGREGATED)
					$calc_fnc = CALC_FNC_ALL;
				else
					$calc_fnc = $this->items[$i]['calc_fnc'];

				switch($calc_fnc){
					case CALC_FNC_ALL:	/* use min */
					case CALC_FNC_MIN:
						$val = $data->min;
						$shift_val = $data->shift_min;
						break;
					case CALC_FNC_MAX:
						$val = $data->max;
						$shift_val = $data->shift_max;
						break;
					case CALC_FNC_AVG:
					default:
						$val = $data->avg;
						$shift_val = $data->shift_avg;
				}

				if(!isset($val)) continue;

				if($this->type == GRAPH_TYPE_STACKED){
					$min_val_shift = min(count($val), count($shift_val));
					for($ci=0; $ci < $min_val_shift; $ci++){
						if($shift_val[$ci] < 0){
							$val[$ci] += $shift_val[$ci];
						}
					}

				}

				if(!isset($minY)){
					if(isset($val) && count($val) > 0){
						$minY = min($val);
					}
				}
				else{
					$minY = min($minY, min($val));
				}
			}
		}

	return $minY;
	}

// Calculation of maximum Y of a side (left/right)
	protected function calculateMaxY($side){
		if($this->ymax_type==GRAPH_YAXIS_TYPE_FIXED){
			return $this->yaxismax;
		}
		else if($this->ymax_type==GRAPH_YAXIS_TYPE_ITEM_VALUE){
			$item = get_item_by_itemid($this->ymax_itemid);
			if($item && isset($item['lastvalue']) && !is_null($item['lastvalue'])){
				 return $item['lastvalue'];
			}
		}

		unset($maxY);
		for($i=0;$i<$this->num;$i++){
			if($this->items[$i]['axisside'] != $side)
				continue;

			foreach(array(GRAPH_ITEM_SIMPLE, GRAPH_ITEM_AGGREGATED) as $type){
				if(!isset($this->data[$this->items[$i]['itemid']][$type]))
					continue;

				$data = &$this->data[$this->items[$i]['itemid']][$type];

				if(!isset($data))	continue;

				if($type == GRAPH_ITEM_AGGREGATED)
					$calc_fnc = CALC_FNC_ALL;
				else
					$calc_fnc = $this->items[$i]['calc_fnc'];

				switch($calc_fnc){
					case CALC_FNC_ALL:	/* use max */
					case CALC_FNC_MAX:
						$val = $data->max;
						$shift_val = $data->shift_max;
						break;
					case CALC_FNC_MIN:
						$val = $data->min;
						$shift_val = $data->shift_min;
						break;
					case CALC_FNC_AVG:
					default:
						$val = $data->avg;
						$shift_val = $data->shift_avg;
				}

				if(!isset($val)) continue;

				for($ci=0; $ci < min(count($val),count($shift_val)); $ci++) $val[$ci] += $shift_val[$ci];

				if(!isset($maxY)){
					if(isset($val) && count($val) > 0){
						$maxY = max($val);
					}
				}
				else{
					$maxY = max($maxY, max($val));
				}

			}
		}
/*
		if(isset($maxY)&&($maxY>0)){

//				$exp = round(log10($maxY));
//				$mant = $maxY/pow(10,$exp);

//				$mant=((round(($mant*11)/6)-1)*6)/10;
//				$maxY = $mant*pow(10,$exp);

			$maxY = round($maxY,1);// + round($maxY,1)*0.2 + 0.05;
		}
		else if(isset($maxY)&&($maxY<0)){
			$maxY = round($maxY,1);// - round($maxY,1)*0.2 + 0.05;
		}
		else {
			$maxY=0.3;
		}
//*/
	return $maxY;
	}

	protected function calcZero(){
		$left = GRAPH_YAXIS_SIDE_LEFT;
		$right = GRAPH_YAXIS_SIDE_RIGHT;

		$this->unit2px[$right] = ($this->m_maxY[$right] - $this->m_minY[$right])/$this->sizeY;
		$this->unit2px[$left] = ($this->m_maxY[$left] - $this->m_minY[$left])/$this->sizeY;

		if($this->m_minY[$right]>0){
			$this->zero[$right] = $this->sizeY+$this->shiftY;
			$this->oxy[$right] = min($this->m_minY[$right],$this->m_maxY[$right]);
		}
		else if($this->m_maxY[$right]<0) {
			$this->zero[$right] = $this->shiftY;
			$this->oxy[$right] = max($this->m_minY[$right],$this->m_maxY[$right]);
		}
		else{
			$this->zero[$right] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$right]/$this->unit2px[$right]);
			$this->oxy[$right] = 0;
		}

		if($this->m_minY[$left]>0){
			$this->zero[$left] = $this->sizeY+$this->shiftY;
			$this->oxy[$left] = min($this->m_minY[$left],$this->m_maxY[$left]);
		}
		else if($this->m_maxY[$left]<0){
			$this->zero[$left] = $this->shiftY;
			$this->oxy[$left] = max($this->m_minY[$left],$this->m_maxY[$left]);
		}
		else{
			$this->zero[$left] = $this->sizeY+$this->shiftY - (int)abs($this->m_minY[$left]/$this->unit2px[$left]);
			$this->oxy[$left] = 0;
		}
	}

	protected function correctMinMax(){

		$sides = array(GRAPH_YAXIS_SIDE_LEFT,GRAPH_YAXIS_SIDE_RIGHT);
		foreach($sides as $side){
//SDI($side);
			if(!isset($this->axis_valuetype[$side])) continue;

			$tmp_maxY = $this->m_maxY[$side];
			$tmp_minY = $this->m_minY[$side];

			$this->m_maxY[$side] = ceil($this->m_maxY[$side]);
			$this->m_minY[$side] = floor($this->m_minY[$side]);

// gridLines
			$this->gridLinesCount[$side] = round($this->sizeY/$this->gridPixels);
			$diff = abs($this->m_minY[$side] - $this->m_maxY[$side]);
			if($diff < $this->gridLinesCount[$side])
				$this->gridLinesCount[$side] = abs($this->m_minY[$side] - $this->m_maxY[$side]);

			if($this->gridLinesCount[$side] < 1 ) $this->gridLinesCount[$side] = 1;

//SDI($this->gridLinesCount[$side]);
//----------


//SDI($this->m_minY[$side].' - '.$this->m_maxY[$side]);
			$value_delta = round($this->m_maxY[$side] - $this->m_minY[$side]);

//			$step = floor((($value_delta/$this->gridLinesCount[$side]) + 1));	// round to top
			$step = ceil($value_delta/$this->gridLinesCount[$side]);	// round to top
			$value_delta2 = $step * $this->gridLinesCount[$side];
//SDI($value_delta.' <> '.$value_delta2);

			$first_delta = round(($value_delta2-$value_delta)/2);
			$second_delta = ($value_delta2-$value_delta) - $first_delta;

//SDI($this->m_maxY[$side].' : '.$first_delta.' --- '.$this->m_minY[$side].' : '.$second_delta);
			if($this->m_minY[$side] >= 0){
				if($this->m_minY[$side] < $second_delta){
					$first_delta += $second_delta - $this->m_minY[$side];
					$second_delta = $this->m_minY[$side];
				}
			}
			else if(($this->m_maxY[$side] <= 0)){
				if($this->m_maxY[$side] > $first_delta){
					$second_delta += $first_delta - $this->m_maxY[$side];
					$first_delta = $this->m_maxY[$side];
				}
			}

			$this->m_maxY[$side] += $first_delta;
			$this->m_minY[$side] -= ($value_delta2-$value_delta) - $first_delta;

//SDI($this->m_minY[$side].' - '.$this->m_maxY[$side]);
//---------

			if($this->ymax_type == GRAPH_YAXIS_TYPE_FIXED){
				$this->m_maxY[$side] = $this->yaxismax;
			}
			else if($this->ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$this->m_maxY[$side] = $tmp_maxY;
			}

			if($this->ymin_type == GRAPH_YAXIS_TYPE_FIXED){
				$this->m_minY[$side] = $this->yaxismin;
			}
			else if($this->ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$this->m_minY[$side] = $tmp_minY;
			}
		}
	}

	private function calcTimeInterval(){
		$this->grid['horizontal'] = array('sub' => array(), 'main' => array());

// align to the closest human time interval
		$raw_time_interval = ($this->gridPixels*$this->period)/$this->sizeX;
		$intervals = array(
			array('main'=> 600,'sub' => 60),				// 1 minute
			array('main'=> 3600,'sub' => 300),				// 5 minutes
			array('main'=> 3600,'sub' => 900),				// 15 minutes
			array('main'=> 3600,'sub' => 1800),				// 30 minutes
			array('main'=> 86400,'sub' => 3600),			// 1 hour
			array('main'=> 86400,'sub' => 10800),			// 3 hours
			array('main'=> 86400,'sub' => 21600),			// 6 hours
			array('main'=> 86400,'sub' => 43200),			// 12 hours
			array('main'=> 604800,'sub' => 86400),			// 1 day
			array('main'=> 1209600,'sub' => 604800),		// 1 week
			array('main'=> 1209600,'sub' => 1209600)		// 2 weeks
		);

		$dist = 604800; //def week;
		$sub_interval = 0;
		$main_interval = 0;

		foreach($intervals as $num => $int){
			$t = abs($int['sub']-$raw_time_interval);

			if($t<$dist){
				$dist = $t;
				$sub_interval = $int['sub'];

				$main_interval = $int['main'];
			}
		}
//------

// Sub
		$intervalX = ($sub_interval * $this->sizeX) / $this->period;

		if($sub_interval > 86400){
			$offset = (8 - date('w',$this->from_time)) * 86400;
			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
			$offsetX = $offset * ($this->sizeX / $this->period);
		}
		else{
			$offset = $sub_interval - (($this->from_time + date('Z', $this->from_time)) % $sub_interval);
			$offsetX = ($offset * $this->sizeX) / $this->period;
		}

		$vline_count = floor(($this->period-$offset) / $sub_interval);

		$start_i = 0;
		if($offsetX < 12) $start_i++;

		while(($this->sizeX - ($offsetX + ($vline_count*$intervalX))) < 12){
			$vline_count--;
		}

//SDI($this->from_time);
		$sub = &$this->grid['horizontal']['sub'];
		$sub['interval'] = $sub_interval;
		$sub['linecount'] = $vline_count;
		$sub['intervalx'] = $intervalX;
		$sub['offset'] = $offset;
		$sub['offsetx'] = $offsetX;
		$sub['start'] = $start_i;
//-----

// Main
		$intervalX = ($main_interval * $this->sizeX) / $this->period;

		if($main_interval > 86400){
			$offset = (8 - date('w',$this->from_time)) * 86400;
			$next = $this->from_time + $offset;

			$offset = mktime(0, 0, 0, date('m', $next), date('d', $next), date('Y', $next)) - $this->from_time;
			$offsetX = $offset * ($this->sizeX / $this->period);
		}
		else{
			$offset = $main_interval - (($this->from_time + (date('Z', $this->from_time))) % $main_interval);
			$offsetX = $offset * ($this->sizeX / $this->period);
		}

		$vline_count = floor(($this->period-$offset) / $main_interval);

		$start_i = 0;
		if($offsetX < 12) $start_i++;

		while(($this->sizeX - ($offsetX + ($vline_count*$intervalX))) < 12){
			$vline_count--;
		}

		$main = &$this->grid['horizontal']['main'];
		$main['interval'] = $main_interval;
		$main['linecount'] = $vline_count;
		$main['intervalx'] = $intervalX;
		$main['offset'] = $offset;
		$main['offsetx'] = $offsetX;
		$main['start'] = $start_i;
//----
	}


/********************************************************************************************************/
// DRAW ELEMENTS
/********************************************************************************************************/
	public function drawXYAxisScale(){
		dashedrectangle($this->im,
			$this->shiftXleft+$this->shiftXCaption-1,
			$this->shiftY-1,
			$this->sizeX+$this->shiftXleft+$this->shiftXCaption,
			$this->sizeY+$this->shiftY+1,
			$this->getColor($this->graphtheme['gridcolor'], 0)
			);

		if($this->yaxisleft){
			imageline($this->im,
				$this->shiftXleft+$this->shiftXCaption-1,
				$this->shiftY-5,
				$this->shiftXleft+$this->shiftXCaption-1,
				$this->sizeY+$this->shiftY+4,
				$this->getColor($this->graphtheme['gridbordercolor'], 0)
				);

			imagefilledpolygon($this->im,
					array(
						$this->shiftXleft+$this->shiftXCaption-4, $this->shiftY-5,
						$this->shiftXleft+$this->shiftXCaption+2, $this->shiftY-5,
						$this->shiftXleft+$this->shiftXCaption-1, $this->shiftY-10,
					),
					3,
					$this->getColor('White')
				);

			imagepolygon($this->im,
					array(
						$this->shiftXleft+$this->shiftXCaption-4, $this->shiftY-5,
						$this->shiftXleft+$this->shiftXCaption+2, $this->shiftY-5,
						$this->shiftXleft+$this->shiftXCaption-1, $this->shiftY-10,
					),
					3,
					$this->getColor($this->graphtheme['gridbordercolor'], 0)
				);
		}

		if($this->yaxisright){
			if($this->yaxisleft) $color = $this->getColor($this->graphtheme['maingridcolor'], 0);
			else $color = $this->getColor($this->graphtheme['gridbordercolor'], 0);

			imageline($this->im,
				$this->sizeX+$this->shiftXleft+$this->shiftXCaption,
				$this->shiftY-5,
				$this->sizeX+$this->shiftXleft+$this->shiftXCaption,
				$this->sizeY+$this->shiftY+4,
				$color
				);

			imagefilledpolygon($this->im,
					array(
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption-3, $this->shiftY-5,
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption+3, $this->shiftY-5,
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption, $this->shiftY-10,
					),
					3,
					$this->getColor('White')
				);

			imagepolygon($this->im,
					array(
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption-3, $this->shiftY-5,
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption+3, $this->shiftY-5,
						$this->sizeX+$this->shiftXleft+$this->shiftXCaption, $this->shiftY-10,
					),
					3,
					$color
				);
		}

		imageline($this->im,
			$this->shiftXleft+$this->shiftXCaption-4,
			$this->sizeY+$this->shiftY+1,
			$this->sizeX+$this->shiftXleft+$this->shiftXCaption+5,
			$this->sizeY+$this->shiftY+1,
			$this->getColor($this->graphtheme['gridbordercolor'], 0)
			);

		imagefilledpolygon($this->im,
				array(
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+5, $this->sizeY+$this->shiftY-2,
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+5, $this->sizeY+$this->shiftY+4,
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+10, $this->sizeY+$this->shiftY+1,
				),
				3,
				$this->getColor('White')
			);

		imagepolygon($this->im,
				array(
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+5, $this->sizeY+$this->shiftY-2,
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+5, $this->sizeY+$this->shiftY+4,
					$this->sizeX+$this->shiftXleft+$this->shiftXCaption+10, $this->sizeY+$this->shiftY+1,
				),
				3,
				$this->getColor($this->graphtheme['gridbordercolor'], 0)
			);
	}

// Vertical grid
	private function drawVerticalGrid(){
		$hline_count = round($this->sizeY / $this->gridPixels);

		if($this->yaxisleft)
			$tmp_hlines = $this->gridLinesCount[GRAPH_YAXIS_SIDE_LEFT];
		else
			$tmp_hlines = $this->gridLinesCount[GRAPH_YAXIS_SIDE_RIGHT];

		if($tmp_hlines < $hline_count)
			$hline_count = $tmp_hlines * 2 - 1;
		else
			$hline_count = $tmp_hlines - 1;


		for($i=1; $i<=$hline_count; $i++){
			dashedline($this->im,
					$this->shiftXleft,
					$i*($this->sizeY/($hline_count+1))+$this->shiftY,
					$this->sizeX+$this->shiftXleft,
					$i*($this->sizeY/($hline_count+1))+$this->shiftY,
					$this->getColor($this->graphtheme['maingridcolor'],0)
				);
		}
	}

	private function drawTimeGrid(){
		$this->calctimeInterval();
		$this->drawSubTimeGrid();
		$this->drawMainTimeGrid();
	}

	private function drawSubTimeGrid(){
		$main_interval = $this->grid['horizontal']['main']['interval'];
		$main_intervalX = $this->grid['horizontal']['main']['intervalx'];
		$main_offset = $this->grid['horizontal']['main']['offset'];
		$main_offsetX = $this->grid['horizontal']['main']['offsetx'];

		$sub = &$this->grid['horizontal']['sub'];
		$interval = $sub['interval'];
		$vline_count = $sub['linecount'];
		$intervalX = $sub['intervalx'];

		$offset = $sub['offset'];
		$offsetX = $sub['offsetx'];
		$start_i = $sub['start'];

		if($interval == $main_interval) return;

		$test_dims = imageTextSize(7, 90, 'WWW');
		for($i=$start_i; $i<=$vline_count; $i++){
			$new_time = $this->from_time+$i*$interval+$offset;
			$new_pos = $i*$intervalX+$offsetX;

			if(($interval == 86400) && date('N',$new_time) == 1) continue;
// if we step to main_time
			else if(($i*$interval % $main_interval + $offset) == $main_offset) continue;			
/*
SDI(($i*$interval % $main_interval + $offset).' == '.$main_offset);
SDI(($interval*$i+$offset).' : '.$interval.' - '.$main_interval);
SDI($offset.' : '.$main_offset);
SDI('======================================');
//*/

			dashedline($this->im,
					$this->shiftXleft+$new_pos,
					$this->shiftY,
					$this->shiftXleft+$new_pos,
					$this->sizeY+$this->shiftY,
					$this->getColor($this->graphtheme['gridcolor'], 0)
			);

			if($main_intervalX < floor(($main_interval/$interval)*$intervalX)) continue;
			else if($main_intervalX < (ceil($main_interval/$interval + 1)*$test_dims['width'])) continue;

			if($interval == 86400) $date_format = 'D';
			else if($interval > 86400) $date_format = 'd.m';
			else if($interval < 86400) $date_format = 'H:i';

			$str = date($date_format, $new_time);
			$dims = imageTextSize(7, 90, $str);

			imageText($this->im,
						7,
						90,
						$this->shiftXleft+$new_pos+round($dims['width']/2),
						$this->sizeY+$this->shiftY+$dims['height']+4,
						$this->getColor($this->graphtheme['textcolor'],0),
						$str
			);

		}
	}

	private function drawMainTimeGrid(){
		$main = &$this->grid['horizontal']['main'];
		$interval = $main['interval'];
		$vline_count = $main['linecount'];
		$intervalX = $main['intervalx'];
		$offset = $main['offset'];
		$offsetX = $main['offsetx'];
		$start_i = $main['start'];

		$old_day=date('d',$this->from_time);
		for($i=$start_i; $i<=$vline_count; $i++){
			$new_time = $this->from_time+$i*$interval+$offset;

			$new_day=date('d', $new_time);
			if($old_day != $new_day){
				$old_day=$new_day;

				if($interval > 86400) $date_format = 'd.m';
				else if(date('Hi', $new_time) == 0) $date_format = 'd.m';
				else $date_format = 'd.m H:i';

				$color = $this->graphtheme['highlightcolor'];
			}
			else{
				$date_format = 'H:i';
				$color = $this->graphtheme['highlightcolor'];
			}


			$str = date($date_format, $new_time);
			$dims = imageTextSize(8, 90, $str);

			imageText($this->im,
						8,
						90,
						$i*$intervalX+$this->shiftXleft+$offsetX+round($dims['width']/2),
						$this->sizeY+$this->shiftY+$dims['height']+4,
						$this->getColor($color,0),
						$str
			);

			dashedline($this->im,
					$i*$intervalX+$this->shiftXleft+$offsetX,
					$this->shiftY,
					$i*$intervalX+$this->shiftXleft+$offsetX,
					$this->sizeY+$this->shiftY,
					$this->getColor($this->graphtheme['maingridcolor'], 0)
			);
		}


// First && Last
// Start
		$str = date('d.m H:i',$this->from_time);
		$dims = imageTextSize(8, 90, $str);
		imageText($this->im,
					8,
					90,
					$this->shiftXleft + round($dims['width']/2),
					$this->sizeY+$this->shiftY + $dims['height'] + 4,
					$this->getColor($this->graphtheme['highlightcolor'], 0),
					$str
			);

// End
		$str = date('d.m H:i',$this->to_time);
		$dims = imageTextSize(8, 90, $str);
		imageText($this->im,
					8,
					90,
					$this->sizeX+$this->shiftXleft + round($dims['width']/2),
					$this->sizeY+$this->shiftY + $dims['height'] + 4,
					$this->getColor($this->graphtheme['highlightcolor'], 0),
					$str
			);
	}

	private function drawLeftSide(){
		if($this->yaxisleft == 1){
			$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
			$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

			for($item=0;$item<$this->num;$item++){
				if($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_LEFT){
					$units=$this->items[$item]['units'];
					break;
				}
			}

			$hstr_count = $this->gridLinesCount[GRAPH_YAXIS_SIDE_LEFT];
			for($i=0; $i<=$hstr_count; $i++){

				$str = convert_units($this->sizeY*$i/$hstr_count*($maxY-$minY)/$this->sizeY+$minY,$units);
				$dims = imageTextSize(8, 0, $str);

				imageText($this->im,
					8,
					0,
					$this->shiftXleft - $dims['width'] - 10,
					$this->sizeY-$this->sizeY*$i/$hstr_count+$this->shiftY + 4,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$str
				);
			}

			if(($this->zero[GRAPH_YAXIS_SIDE_LEFT] != ($this->sizeY+$this->shiftY)) && ($this->zero[GRAPH_YAXIS_SIDE_LEFT] != $this->shiftY)){
				imageline($this->im,
							$this->shiftXleft,
							$this->zero[GRAPH_YAXIS_SIDE_LEFT],
							$this->shiftXleft+$this->sizeX,
							$this->zero[GRAPH_YAXIS_SIDE_LEFT],
							$this->getColor(GRAPH_ZERO_LINE_COLOR_LEFT)
						);
			}
		}
	}

	private function drawRightSide(){
		if($this->yaxisright == 1){
			$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
			$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

			for($item=0;$item<$this->num;$item++){
				if($this->items[$item]['axisside'] == GRAPH_YAXIS_SIDE_RIGHT){
					$units=$this->items[$item]['units'];
					break;
				}
			}

			$hstr_count = $this->gridLinesCount[GRAPH_YAXIS_SIDE_RIGHT];
			for($i=0;$i<=$hstr_count;$i++){
				$str = convert_units($this->sizeY*$i/$hstr_count*($maxY-$minY)/$this->sizeY+$minY,$units);
				imageText($this->im,
					8,
					0,
					$this->sizeX+$this->shiftXleft+12,
					$this->sizeY-$this->sizeY*$i/$hstr_count+$this->shiftY + 4,
					$this->getColor($this->graphtheme['textcolor'], 0),
					$str
				);
			}

			if(($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->sizeY+$this->shiftY) &&
				($this->zero[GRAPH_YAXIS_SIDE_RIGHT] != $this->shiftY))
			{
				imageline($this->im,
							$this->shiftXleft,
							$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
							$this->shiftXleft+$this->sizeX,
							$this->zero[GRAPH_YAXIS_SIDE_RIGHT],
							$this->getColor(GRAPH_ZERO_LINE_COLOR_RIGHT)
						); //*/
			}
		}
	}

	protected function drawWorkPeriod(){
		imagefilledrectangle($this->im,
			$this->shiftXleft+1,
			$this->shiftY,
			$this->sizeX+$this->shiftXleft-2,	// -2 border
			$this->sizeY+$this->shiftY,
			$this->getColor($this->graphtheme['graphcolor'], 0));

		if($this->m_showWorkPeriod != 1) return;
		if($this->period > 2678400) return; // > 31*24*3600 (month)

		$db_work_period = DBselect('SELECT work_period FROM config');
		$work_period = DBfetch($db_work_period);
		if(!$work_period) return;

		$periods = parse_period($work_period['work_period']);
		if(!$periods) return;

		imagefilledrectangle($this->im,
			$this->shiftXleft+1,
			$this->shiftY,
			$this->sizeX+$this->shiftXleft-2,	// -2 border
			$this->sizeY+$this->shiftY,
			$this->getColor($this->graphtheme['noneworktimecolor'], 0));

		$now = time();
		if(isset($this->stime)){
			$this->from_time=$this->stime;
			$this->to_time=$this->stime+$this->period;
		}
		else{
			$this->to_time=$now-3600*$this->from;
			$this->from_time=$this->to_time-$this->period;
		}

		$from = $this->from_time;
		$max_time = $this->to_time;

		$start = find_period_start($periods,$from);
		$end = -1;
		while($start < $max_time && $start > 0){
			$end = find_period_end($periods,$start,$max_time);

			$x1 = round((($start-$from)*$this->sizeX)/$this->period) + $this->shiftXleft;
			$x2 = ceil((($end-$from)*$this->sizeX)/$this->period) + $this->shiftXleft;

			//draw rectangle
			imagefilledrectangle(
				$this->im,
				$x1,
				$this->shiftY,
				$x2-2,		// -2 border
				$this->sizeY+$this->shiftY,
				$this->getColor($this->graphtheme['graphcolor'], 0));

			$start = find_period_start($periods,$end);
		}
	}


	protected function drawPercentile(){
		if($this->type != GRAPH_TYPE_NORMAL){
			return ;
		}

		foreach($this->percentile as $side => $percentile){
			if(($percentile['percent']>0) && $percentile['value']){
				if($side == 'left'){
					$minY = $this->m_minY[GRAPH_YAXIS_SIDE_LEFT];
					$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT];

					$color = $this->graphtheme['leftpercentilecolor'];
				}
				else{
					$minY = $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT];
					$maxY = $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT];

					$color = $this->graphtheme['rightpercentilecolor'];
				}

				$y = $this->sizeY - (($percentile['value']-$minY) / ($maxY-$minY)) * $this->sizeY + $this->shiftY;
				imageline(
					$this->im,
					$this->shiftXleft,
					$y,
					$this->sizeX+$this->shiftXleft,
					$y,
					$this->getColor($color));
			}
		}
	}

	protected function drawTriggers(){
		if($this->m_showTriggers != 1) return;
		if($this->num != 1) return; // skip multiple graphs

		foreach($this->triggers as $trigger){
			dashedline(
				$this->im,
				$this->shiftXleft,
				$trigger['y'],
				$this->sizeX+$this->shiftXleft,
				$trigger['y'],
				$this->getColor($trigger['color']));
		}
	}


	protected function drawLegend(){
		$leftXShift = 20;

		$units = array('left'=>0, 'right'=>0 );

		$legend = new CImageTextTable($this->im, $leftXShift+10, $this->sizeY+$this->shiftY+$this->legendOffsetY);
		$legend->color = $this->getColor($this->graphtheme['textcolor'], 0);
		$legend->rowheight = 14;
		$legend->fontsize = 9;

		$row = array(
				array('text' => ''),
				array('text' => ''),
				array('text' => 'Last', 'align'=> 1, 'fontsize' => 10),
				array('text' => 'Min', 'align'=> 1, 'fontsize' => 10),
				array('text' => 'Avg', 'align'=> 1, 'fontsize' => 10),
				array('text' => 'Max', 'align'=> 1, 'fontsize' => 10)
			);

		$legend->addRow($row);
		$colNum = $legend->getNumRows();

		$i = ($this->type == GRAPH_TYPE_STACKED)?($this->num-1):0;
		while(($i>=0) && ($i<$this->num)){
			$row = array();

			if($this->items[$i]['calc_type'] == GRAPH_ITEM_AGGREGATED){
				$fnc_name = 'agr('.$this->items[$i]['periods_cnt'].')';
				$color = $this->getColor('HistoryMinMax');
			}
			else{
				$color = $this->getColor($this->items[$i]['color'], GRAPH_STACKED_ALFA);
				switch($this->items[$i]['calc_fnc']){
					case CALC_FNC_MIN:	$fnc_name = 'min';	break;
					case CALC_FNC_MAX:	$fnc_name = 'max';	break;
					case CALC_FNC_ALL:	$fnc_name = 'all';	break;
					case CALC_FNC_AVG:
					default:		$fnc_name = 'avg';
				}
			}

			$data = &$this->data[$this->items[$i]['itemid']][$this->items[$i]['calc_type']];
			if(isset($data) && isset($data->min)){
				if($this->items[$i]['axisside'] == GRAPH_YAXIS_SIDE_LEFT)
					$units['left'] = $this->items[$i]['units'];
				else
					$units['right'] = $this->items[$i]['units'];

				$legend->addCell($colNum, array('text' => $this->items[$i]['host'].': '.$this->items[$i]['description']));
				$legend->addCell($colNum, array('text' => '['.$fnc_name.']'));
				$legend->addCell($colNum, array('text' => convert_units($this->getLastValue($i),$this->items[$i]['units']), 'align'=> 2));
				$legend->addCell($colNum, array('text' => convert_units(min($data->min),$this->items[$i]['units']), 'align'=> 2));
				$legend->addCell($colNum, array('text' => convert_units(zbx_avg($data->avg),$this->items[$i]['units']), 'align'=> 2));
				$legend->addCell($colNum, array('text' => convert_units(max($data->max),$this->items[$i]['units']), 'align'=> 2));
			}
			else{
				$legend->addCell($colNum,array('text' => $this->items[$i]['host'].': '.$this->items[$i]['description']));
				$legend->addCell($colNum,array('text' => '[ no data ]'));
			}

			imagefilledrectangle($this->im,
							$leftXShift - 5,
							$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY-10,
							$leftXShift + 5,
							$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
							$color
						);

			imagerectangle($this->im,
							$leftXShift - 5,
							$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY-10,
							$leftXShift + 5,
							$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
							$this->getColor('Black')
						);

			$colNum++;

			if($this->type == GRAPH_TYPE_STACKED) $i--;
			else $i++;
		}

		if($this->sizeY < 100){
			$legend->draw();
			return ;
		}

// Draw percentile
		if($this->type == GRAPH_TYPE_NORMAL){
			foreach($this->percentile as $side => $percentile){
				if(($percentile['percent']>0) && $percentile['value']){

					$str = '%sth percentile: %s';
					$percentile['percent'] = (float) $percentile['percent'];
					$legend->addCell($colNum,array('text' => $percentile['percent'].'th percentile: '.convert_units($percentile['value'],$units[$side]).'  ('.$side.')'));
					if($side == 'left'){
						$color = $this->graphtheme['leftpercentilecolor'];
					}
					else{
						$color = $this->graphtheme['rightpercentilecolor'];
					}

 					imagefilledpolygon($this->im,
							array(
								$leftXShift+5,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
								$leftXShift-5,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
								$leftXShift,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY - 10,
							),
							3,
							$this->getColor($color)
						);

					imagepolygon($this->im,
							array(
								$leftXShift+5,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
								$leftXShift-5,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY,
								$leftXShift,$this->sizeY+$this->shiftY+14*$colNum+$this->legendOffsetY - 10,
							),
							3,
							$this->getColor('Black No Alpha')
						);



					$colNum++;
				}
			}
		}

// Draw triggers
		foreach($this->triggers as $trigger){
			imagefilledellipse($this->im,
				$leftXShift,
				$this->sizeY+$this->shiftY+12*$colNum+$this->legendOffsetY,
				10,
				10,
				$this->getColor($trigger['color']));

			imageellipse($this->im,
				$leftXShift,
				$this->sizeY+$this->shiftY+12*$colNum+$this->legendOffsetY,
				10,
				10,
				$this->getColor('Black No Alpha'));

			$legend->addCell($colNum,array('text' => $trigger['description']));
			$colNum++;
		}

		$legend->draw();
	}

	protected function drawElement(&$data, $from, $to, $minX, $maxX, $minY, $maxY, $drawtype, $max_color, $avg_color, $min_color, $minmax_color,$calc_fnc, $axisside){
		if(!isset($data->max[$from]) || !isset($data->max[$to])) return;

		$oxy = $this->oxy[$axisside];
		$zero = $this->zero[$axisside];
		$unit2px = $this->unit2px[$axisside];
//SDI($oxy);
		$shift_min_from = $shift_min_to = 0;
		$shift_max_from = $shift_max_to = 0;
		$shift_avg_from = $shift_avg_to = 0;

		if(isset($data->shift_min[$from]))	$shift_min_from = $data->shift_min[$from];
		if(isset($data->shift_min[$to]))	$shift_min_to = $data->shift_min[$to];

		if(isset($data->shift_max[$from]))	$shift_max_from = $data->shift_max[$from];
		if(isset($data->shift_max[$to]))	$shift_max_to = $data->shift_max[$to];

		if(isset($data->shift_avg[$from]))	$shift_avg_from = $data->shift_avg[$from];
		if(isset($data->shift_avg[$to]))	$shift_avg_to = $data->shift_avg[$to];
/**/
		$min_from	= $data->min[$from]	+ $shift_min_from;
		$min_to		= $data->min[$to]	+ $shift_min_to;

		$max_from	= $data->max[$from]	+ $shift_max_from;
		$max_to		= $data->max[$to]	+ $shift_max_to;

		$avg_from	= $data->avg[$from]	+ $shift_avg_from;
		$avg_to		= $data->avg[$to]	+ $shift_avg_to;

		$x1 = $from + $this->shiftXleft - 1;
		$x2 = $to + $this->shiftXleft;


		$y1min = $zero - ($min_from-$oxy)/$unit2px;
		$y2min = $zero - ($min_to-$oxy)/$unit2px;
//SDI(array($y1min,$zero,$min_from,$oxy,$unit2px));
		$y1max = $zero - ($max_from-$oxy)/$unit2px;
		$y2max = $zero - ($max_to-$oxy)/$unit2px;

		$y1avg = $zero - ($avg_from-$oxy)/$unit2px;
		$y2avg = $zero - ($avg_to-$oxy)/$unit2px;		//*/

		switch($calc_fnc){
			case CALC_FNC_MAX:
				$y1 = $y1max;
				$y2 = $y2max;
				$shift_from	= $shift_max_from;
				$shift_to	= $shift_max_to;
				break;
			case CALC_FNC_MIN:
				$y1 = $y1min;
				$y2 = $y2min;
				$shift_from	= $shift_min_from;
				$shift_to	= $shift_min_to;
				break;
			case CALC_FNC_ALL:
				$a[0] = $x1;		$a[1] = $y1max;
				$a[2] = $x1;		$a[3] = $y1min;
				$a[4] = $x2;		$a[5] = $y2min;
				$a[6] = $x2;		$a[7] = $y2max;
//SDI('2: '.$x2.' - '.$x1.' : '.$y2min.' - '.$y1min);
				imagefilledpolygon($this->im,$a,4,$minmax_color);
				imageline($this->im,$x1,$y1max,$x2,$y2max,$max_color);
				imageline($this->im,$x1,$y1min,$x2,$y2min,$min_color);

				/* don't use break, avg must be drawed in this statement */
				// break;
			case CALC_FNC_AVG:
				/* don't use break, avg must be drawed in this statement */
				// break;
			default:
				$y1 = $y1avg;
				$y2 = $y2avg;
				$shift_from	= $shift_avg_from ;
				$shift_to	= $shift_avg_to;
		}

		$shift_from -= ($shift_from != 0)?($oxy):(0);
		$shift_to -= ($shift_to != 0)?($oxy):(0);

		$y1_shift	= $zero - $shift_from/$unit2px;
		$y2_shift	= $zero - $shift_to/$unit2px;//*/


// Fixes graph out of bounds problem
		if( (($y1 > ($this->sizeY+$this->shiftY)) && ($y2 > ($this->sizeY+$this->shiftY))) || (($y1 < $this->shiftY) && ($y2 < $this->shiftY)) ){
			if($drawtype == GRAPH_ITEM_DRAWTYPE_FILLED_REGION){
				if($y1 > ($this->sizeY+$this->shiftY)) $y1 = $this->sizeY+$this->shiftY;
				if($y2 > ($this->sizeY+$this->shiftY)) $y2 = $this->sizeY+$this->shiftY;

				if($y1 < ($this->sizeY+$this->shiftY)) $y1 = $this->shiftY;
				if($y2 < ($this->sizeY+$this->shiftY)) $y2 = $this->shiftY;
			}
			else{
				return true;
			}
		}

		$y_first = !(($y1 > ($this->sizeY+$this->shiftY)) || ($y1 < $this->shiftY));
		$y_second = !(($y2 > ($this->sizeY+$this->shiftY)) || ($y2 < $this->shiftY));

		if(!$y_first){
			$y1 = ($y1 > ($this->sizeY+$this->shiftY))?($this->sizeY+$this->shiftY):$this->shiftY;
		}
		else if(!$y_second){
			$y2 = ($y2 > ($this->sizeY+$this->shiftY))?($this->sizeY+$this->shiftY):$this->shiftY;
		}
//--------

		/* draw main line */
		switch($drawtype){
			case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
				imageline($this->im,$x1,$y1+1,$x2,$y2+1,$avg_color);
				// break; /* don't use break, must be drawed line also */
			case GRAPH_ITEM_DRAWTYPE_LINE:
//SDI(array($this->im,$x1,$y1,$x2,$y2,$avg_color));
				imageline($this->im,$x1,$y1,$x2,$y2,$avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
				$a[0] = $x1;		$a[1] = $y1;
				$a[2] = $x1;		$a[3] = $y1_shift;
				$a[4] = $x2;		$a[5] = $y2_shift;
				$a[6] = $x2;		$a[7] = $y2;
//SDI($a);
				imagefilledpolygon($this->im,$a,4,$avg_color);
//				imageline($this->im,$x1,$y1,$x2,$y2,$this->getShadow('333333',50));
				break;
			case GRAPH_ITEM_DRAWTYPE_DOT:
				imagefilledrectangle($this->im,$x1-1,$y1-1,$x1+1,$y1+1,$avg_color);
				imagefilledrectangle($this->im,$x2-1,$y2-1,$x2+1,$y2+1,$avg_color);
				break;
			case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
				if( function_exists('imagesetstyle') ){

					/* Use imagesetstyle+imageline instead of bugged imagedashedline */
					$style = array($avg_color, $avg_color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
					imagesetstyle($this->im, $style);
					imageline($this->im,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
				}
				else{
					imagedashedline($this->im,$x1,$y1,$x2,$y2,$avg_color);
				}
				break;
			case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
				
				ImageLine($this->im, $x1, $y1, $x2, $y2, $avg_color);  //draw the initial line
				ImageLine($this->im, $x1, $y1-1, $x2, $y2-1, $avg_color);
				
				$bitmask = 255;
				$blue = $avg_color&$bitmask;
				// $blue_diff = 255 - $blue;
				$bitmask = $bitmask<<8;
				$green = ($avg_color&$bitmask)>>8;
				// $green_diff = 255 - $green;
				$bitmask = $bitmask<<8;
				$red = ($avg_color&$bitmask)>>16;
				// $red_diff = 255 - $red;
				
				$maxAlpha = 110;
				$startAlpha = 50;
				$alphaRatio = $maxAlpha / ($this->sizeY - $startAlpha);
	
				$diffX = $x1 - $x2;
//sdi('x1: '.$x1.'  x2: '.$x2);
				for($i=0; $i<=$diffX; $i++){
				
					$Yincr = abs($y2 - $y1) / $diffX;
					$gy = ($y1 > $y2) ? ($y2 + $Yincr*$i) : ($y2 - $Yincr*$i);
					$steps = $this->sizeY + $this->shiftY - $gy + 1;
					
					for($j=0; $j<$steps; $j++){
						if(($gy + $j) < ($this->shiftY + $startAlpha)){
							$alpha = 0;
						}
						else{
							$alpha = 127 - abs(127 - ($alphaRatio * ($gy + $j - $this->shiftY - $startAlpha)));
						}
						
						$color = imagecolorexactalpha($this->im, $red, $green, $blue, $alpha);
						imagesetpixel($this->im, $x2 + $i, $gy + $j, $color);
					}
				}
			break;
		}
	}

	public function draw(){
		$start_time=getmicrotime();

		set_image_header();

		check_authorisation();

		$this->selectData();

		$this->m_minY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_minY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMinY(GRAPH_YAXIS_SIDE_RIGHT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_LEFT);
		$this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]	= $this->calculateMaxY(GRAPH_YAXIS_SIDE_RIGHT);

		if($this->m_minY[GRAPH_YAXIS_SIDE_LEFT] == $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT]){
			if($this->graphOrientation[GRAPH_YAXIS_SIDE_LEFT] == '-') $this->m_maxY[GRAPH_YAXIS_SIDE_LEFT] = 0;
			else $this->m_minY[GRAPH_YAXIS_SIDE_LEFT] = 0;
		}
		
		if($this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] == $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT]){
			if($this->graphOrientation[GRAPH_YAXIS_SIDE_RIGHT] == '-') $this->m_maxY[GRAPH_YAXIS_SIDE_RIGHT] = 0;
			else $this->m_minY[GRAPH_YAXIS_SIDE_RIGHT] = 0;
		}


		$this->correctMinMax();

		$this->updateShifts();
		$this->calcTriggers();
		$this->calcZero();
		$this->calcPercentile();

		$this->fullSizeX = $this->sizeX+$this->shiftXleft+$this->shiftXright+1;
		$this->fullSizeY = $this->sizeY+$this->shiftY+$this->legendOffsetY;
		$this->fullSizeY += 14*($this->num+1+(($this->sizeY < 120)?0:count($this->triggers)))+8;

		foreach($this->percentile as $side => $percentile){
			if(($percentile['percent']>0) && $percentile['value']){
				$this->fullSizeY += 14;
			}
		}

		if(function_exists('imagecolorexactalpha') && function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1))
			$this->im = imagecreatetruecolor($this->fullSizeX,$this->fullSizeY);
		else
			$this->im = imagecreate($this->fullSizeX,$this->fullSizeY);


		$this->initColors();
		$this->drawRectangle($this->graphtheme['backgroundcolor'], $this->graphtheme['graphbordercolor']);
		$this->drawHeader($this->graphtheme['textcolor']);

		if($this->num==0){
//				$this->noDataFound();
		}

		$this->drawWorkPeriod();
//*/ grid
		$this->drawTimeGrid();
		$this->drawVerticalGrid();
		$this->drawXYAxisScale($this->graphtheme['gridbordercolor']);
//-----*/

		$maxX = $this->sizeX;

// For each metric
		for($item = 0; $item < $this->num; $item++){
			$minY = $this->m_minY[$this->items[$item]['axisside']];
			$maxY = $this->m_maxY[$this->items[$item]['axisside']];

			$data = &$this->data[$this->items[$item]['itemid']][$this->items[$item]['calc_type']];

			if(!isset($data))	continue;

			if($this->items[$item]['calc_type'] == GRAPH_ITEM_AGGREGATED){
				$drawtype	= GRAPH_ITEM_DRAWTYPE_LINE;

				$max_color	= $this->getColor('HistoryMax');
				$avg_color	= $this->getColor('HistoryAvg');
				$min_color	= $this->getColor('HistoryMin');
				$minmax_color	= $this->getColor('HistoryMinMax');

				$calc_fnc	= CALC_FNC_ALL;
			}
			else if($this->type == GRAPH_TYPE_STACKED){
				$drawtype	= $this->items[$item]['drawtype'];

				$max_color	= $this->getColor('ValueMax',GRAPH_STACKED_ALFA);
				$avg_color	= $this->getColor($this->items[$item]['color'],GRAPH_STACKED_ALFA);
				$min_color	= $this->getColor('ValueMin',GRAPH_STACKED_ALFA);
				$minmax_color	= $this->getColor('ValueMinMax',GRAPH_STACKED_ALFA);

				$calc_fnc = $this->items[$item]['calc_fnc'];
			}
			else{
				$drawtype	= $this->items[$item]['drawtype'];

				$max_color	= $this->getColor('ValueMax', GRAPH_STACKED_ALFA);
				$avg_color	= $this->getColor($this->items[$item]['color'], GRAPH_STACKED_ALFA);
				$min_color	= $this->getColor('ValueMin', GRAPH_STACKED_ALFA);
				$minmax_color	= $this->getColor('ValueMinMax', GRAPH_STACKED_ALFA);

				$calc_fnc = $this->items[$item]['calc_fnc'];
			}

// For each X
			for($i = 1, $j = 0; $i < $maxX; $i++){  // new point
				if(($data->count[$i] == 0) && ($i != ($maxX-1))) continue;

				$diff	= abs($data->clock[$i] - $data->clock[$j]);
				$cell	= ($this->to_time - $this->from_time)/$this->sizeX;
				$delay	= $this->items[$item]['delay'];

				if($cell > $delay)
					$draw = (boolean) ($diff < (ZBX_GRAPH_MAX_SKIP_CELL * $cell));
				else
					$draw = (boolean) ($diff < (ZBX_GRAPH_MAX_SKIP_DELAY * $delay));

				if(($draw == false) && ($this->items[$item]['calc_type'] == GRAPH_ITEM_AGGREGATED))
					$draw = $i - $j < 5;

				if($this->items[$item]['type'] == ITEM_TYPE_TRAPPER)
					$draw = true;
//SDI($draw);

				if($draw){
					$this->drawElement(
						$data,
						$i, $j,
						0, $this->sizeX,
						$minY, $maxY,
						$drawtype,
						$max_color,
						$avg_color,
						$min_color,
						$minmax_color,
						$calc_fnc,
						$this->items[$item]['axisside']
						);
				}
//echo '\nDraw II \n'; printf('%0.4f',(getmicrotime()-$start_time));

				$j = $i;
			}
		}

/* grid
		$this->drawTimeGrid();
		$this->drawVerticalGrid();
		$this->drawXYAxisScale($this->graphtheme['gridbordercolor']);
//-----*/

		$this->drawLeftSide();
		$this->drawRightSide();

		$this->drawTriggers();
		$this->drawPercentile();

		$this->drawLogo();

		$this->drawLegend();

		$end_time=getmicrotime();
		$str=sprintf('%0.2f',(getmicrotime()-$start_time));
		imagestring($this->im, 0,$this->fullSizeX-210,$this->fullSizeY-12,'Data from '.$this->dataFrom.'. Generated in '.$str.' sec', $this->getColor('Gray'));

		unset($this->items, $this->data);

		ImageOut($this->im);
	}
}

?>