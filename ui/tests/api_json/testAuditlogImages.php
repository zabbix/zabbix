<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup images
 */
class testAuditlogImages extends testAuditlogCommon {

	/**
	 * Existing Image ID.
	 */
	protected const IMAGEID = 5;

	public function testAuditlogImages_Create() {
		$create = $this->call('image.create', [
			[
				'imagetype' => 2,
				'name' => 'Created image',
				'image' => 'iVBORw0KGgoAAAANSUhEUgAAABgAAAANCAYAAACzbK7QAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAACmAAAApgBN'.
						'tNH3wAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAIcSURBVDjLrZLbSxRRHMdPKiEiRQ89CD0s+N'.
						'5j9BIMEf4Hg/jWexD2ZEXQbC9tWUFZimtLhswuZiVujK1UJmYXW9PaCUdtb83enL3P7s6ss5f5dc7EUsmqkPuFH3M4/Ob'.
						'7+V0OAgC0UyDENFEU03rh1uNOs/lFG75o2i2/rkd9Y3Tgyj3HiaezbukdH9A/rP4E9vWi0u+Y4fuGnMf3DRgYc3Z/84Yr'.
						'QSkD3mgKhFAC+KAEK74Y2Lj3MjPoOokQ3Xyx/1GHeXCifbfO6lRPH/wi+AvZQhGSsgKxdB5CCRkCGPbDgMXBMbukTc4vK'.
						'5/WRHizsq7fZl2LFuvE4T0BZDTXHtgv4TNUqlUolsqQL2qQwbDEXzBBTIJ7I4y/cfAENmHZF4XrY9Mc+X9HAFmoyXS2dd'.
						'y1IOg6/KNyBcM0DFP/wFZFCcOy4N9Mw0YkCTOfhdL5AfZQXQBFn2t/ODXHC8FYVcoWjNEQ03qqwTJ5FdI44jg/msoB2Zd'.
						'5ZKq3q6evA1FUS60bYyyj3AJf3V72HiLZJQxTtRLk1C2IYEg4mTNg63hPd1mOJd7Ict911OMNlWEf0nFxpCt16zcshTuL'.
						'pGSwDDuPIfv0xzNyQYVGicC0cgUUDLM6Xp02lvvW/V2EBssnxlSGmWsxljw0znV9XfPLjTCW84r+cn7Jc8c2eWrbM6Wbe'.
						'6/aTJbhJ/TNkWc9/xXW592Xb9iPkKnUfH8BKdLgFy0lDyQAAAAASUVORK5CYII='
			]
		]);

		$resourceid = $create['result']['imageids'][0];

		$created = json_encode([
			'image.imagetype' => ['add', '2'],
			'image.name' => ['add', 'Created image'],
			'image.image' => ['add'],
			'image.imageid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	/**
	 * @depends testAuditlogImages_Create
	 */
	public function testAuditlogImages_Update() {
		$this->call('image.update', [
			[
				'imageid' => self::IMAGEID,
				'name' => 'Updated image',
				'image' => 'iVBORw0KGgoAAAANSUhEUgAAABgAAAANCAYAAACzbK7QAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAACmAAAApgBN'.
						'tNH3wAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAIcSURBVDjLrZLbSxRRHMdPKiEiRQ89CD0s+N'.
						'5j9BIMEf4Hg/jWexD2ZEXQbC9tWUFZimtLhswuZiVujK1UJmYXW9PaCUdtb83enL3P7s6ss5f5dc7EUsmqkPuFH3M4/Ob'.
						'7+V0OAgC0UyDENFEU03rh1uNOs/lFG75o2i2/rkd9Y3Tgyj3HiaezbukdH9A/rP4E9vWi0u+Y4fuGnMf3DRgYc3Z/84Yr'.
						'QSkD3mgKhFAC+KAEK74Y2Lj3MjPoOokQ3Xyx/1GHeXCifbfO6lRPH/wi+AvZQhGSsgKxdB5CCRkCGPbDgMXBMbukTc4vK'.
						'5/WRHizsq7fZl2LFuvE4T0BZDTXHtgv4TNUqlUolsqQL2qQwbDEXzBBTIJ7I4y/cfAENmHZF4XrY9Mc+X9HAFmoyXS2dd'.
						'y1IOg6/KNyBcM0DFP/wFZFCcOy4N9Mw0YkCTOfhdL5AfZQXQBFn2t/ODXHC8FYVcoWjNEQ03qqwTJ5FdI44jg/msoB2Zd'.
						'5ZKq3q6evA1FUS60bYyyj3AJf3V72HiLZJQxTtRLk1C2IYEg4mTNg63hPd1mOJd7Ict911OMNlWEf0nFxpCt16zcshTuL'.
						'pGSwDDuPIfv0xzNyQYVGicC0cgUUDLM6Xp02lvvW/V2EBssnxlSGmWsxljw0znV9XfPLjTCW84r+cn7Jc8c2eWrbM6Wbe'.
						'6/aTJbhJ/TNkWc9/xXW592Xb9iPkKnUfH8BKdLgFy0lDyQAAAAASUVORK5CYII='
			]
		]);

		$updated = json_encode([
			'image.name' => ['update', 'Updated image', 'Cloud_(96)'],
			'image.image' => ['update']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::IMAGEID);
	}

	public function testAuditlogImages_Delete() {
		$this->call('image.delete', [self::IMAGEID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated image', self::IMAGEID);
	}
}
