<?php

class CArrayHelperTest extends PHPUnit_Framework_TestCase {

	public function testSortProvider() {
		return array(
			array(
				array(
					'x' => array('a' => 1, 'b' => 1, 'c' => 2),
					'y' => array('a' => 1, 'b' => 1, 'c' => 2),
					'z' => array('a' => 1, 'b' => 1, 'c' => 2)
				),
				array('a', 'b'),
				array('x', 'y', 'z')
			),
			array(
				array(
					'x' => array('a' => 1, 'b' => 2, 'c' => 2),
					'y' => array('a' => 1, 'b' => 3, 'c' => 2),
					'z' => array('a' => 1, 'b' => 1, 'c' => 2)
				),
				array('a', 'b'),
				array('z', 'x', 'y')
			),
			array(
				array(
					'x' => array('a' => 1, 'b' => 2, 'c' => 2),
					'y' => array('a' => 1, 'b' => 2, 'c' => 2),
					'z' => array('a' => 1, 'b' => 1, 'c' => 2)
				),
				array('a', 'b'),
				array('z', 'x', 'y')
			),
			array(
				array(
					'x' => array('a' => 1, 'b' => 2, 'c' => 2),
					'z' => array('a' => 1),
					'y' => array('a' => 1)
				),
				array('a', 'b'),
				array('z', 'y', 'x')
			),
			array(
				array(
					'z' => array('a' => 1),
					'y' => array('a' => 1),
					'x' => array('a' => 1, 'b' => 2, 'c' => 2)
				),
				array('a', 'b'),
				array('z', 'y', 'x')
			),
			array(
				array(
					'x' => array('a' => 1, 'b' => 2, 'c' => 2),
					'z' => array('a' => 1, 'b' => 2, 'c' => 2),
					'y' => array('a' => 1)
				),
				array('a', 'b'),
				array('y', 'x', 'z')
			),
			array(
				array(
					't' => array('a' => 2, 'b' => 3),
					'q' => array('a' => 1, 'b' => 1),
					'w' => array('a' => 1, 'b' => 2),
					'y' => array('a' => 2, 'b' => 2),
					'r' => array('a' => 1, 'b' => 3),
					's' => array('a' => 2, 'b' => 2),
					'p' => array('a' => 2, 'b' => 1),
				),
				array(
					array('field' => 'a', 'order' => ZBX_SORT_UP),
					array('field' => 'b', 'order' => ZBX_SORT_DOWN),
				),
				array('r', 'w', 'q', 't', 'y', 's', 'p')
			)
		);
	}

	/**
	 * @dataProvider testSortProvider
	 *
	 * @param array $array
	 * @param array $fields
	 * @param array $expectedKeys
	 */
	public function testSort(array $array, array $fields, array $expectedKeys) {

		CArrayHelper::sort($array, $fields);

		$this->assertEquals($expectedKeys, array_keys($array));
	}
}
