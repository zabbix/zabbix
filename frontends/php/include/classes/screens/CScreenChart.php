<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CScreenChart extends CScreenBase {

	/**
	 * Graph id
	 *
	 * @var int
	 */
	public $graphid;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param int		$options['graphid']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);

		$this->graphid = isset($options['graphid']) ? $options['graphid'] : null;
		$this->profileIdx2 = $this->graphid;
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'graph_full';
		$containerId = 'graph_container';

		// time control
		$graphDims = getGraphDims($this->graphid);
		if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
			$loadSBox = 0;
			$src = 'chart6.php';
		}
		else {
			$loadSBox = 1;
			$src = 'chart2.php';
		}
		$src .= '?graphid='.$this->graphid.'&from='.$this->timeline['from'].'&to='.$this->timeline['to'].
			$this->getProfileUrlParams();

		$this->timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_graphid($this->graphid));

		$timeControlData = [
			'id' => $this->getDataId(),
			'containerid' => $containerId,
			'src' => $src,
			'objDims' => $graphDims,
			'loadSBox' => $loadSBox,
			'loadImage' => 1,
			'dynamic' => 1
		];

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			$timeControlData['dynamic'] = 0;
			$timeControlData['loadSBox'] = 0;

			return 'timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).')';
		}
		else {
			if ($this->mode == SCREEN_MODE_SLIDESHOW) {
				insert_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');');
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');');
			}

			return $this->getOutput(
				(new CDiv())
					->addClass('center')
					->setId($containerId),
				true,
				['graphid' => $this->graphid]
			);
		}
	}
}
