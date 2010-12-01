<?php

require('include/func.inc.php');
require('include/items.inc.php');
require('include/defines.inc.php');
require('include/locales.inc.php');

$test = array(
	'key[a]' => true,
	'key["a"]' => true,
	'key[a, b, c]' => true,
	'key["a", "b", "c"]' => true,
	'key[a, b, "c"]' => true,
	'key["a", "b", c]' => true,
	'key["a[][][]]],\"!@$#$^%*&*)"]' => true,
	'key[["a"],b]'=> true,
	'complex.key[a, b, c]' => true,
	'complex.key[[a, b], c]' => true,
	'complex.key[abc"efg"h]' => true,
	'complex.key[a][b]' => true,
	'complex.key["a"]["b"]' => true,
	'complex.key["a"][b]' => true,
	'complex.key[a, b][c, d]' => true,
	'complex.key["a", "b"]["c", "d"]' => true,
	'more.complex.key[1, 2, [A, B, [a, b], C], 3]' => true,
	'more.complex.key["1", "2", ["A", "B", ["a", "b"], "C"], "3"]' => true,
	'more.complex.key[["1"]]' => true,

	'key[a]]' => false,
	'key["a"]]' => false,
	'key["a]' => false,
	'key[a,]' => true,
	'key["a",]' => true,
	'key[["a",]' => false,
	'key[a]654' => false,
	'key["a"]654' => false,
	'key[a][[b]' => false,
	'key["a"][["b"]' => false,
	'key(a)' => false,
	'key[,,]' => true,
);

echo "<table border=1 cellpadding=5>";
foreach($test as $key=>$r){
	$result = check_item_key($key);

	if($result[0] != $r)
		echo '<tr style="color:white;background-color:red">';
	else
		echo '<tr>';

	echo "<td>$key</td><td>{$result[1]}</td>";

	echo "</tr>";
}
echo "</table>";
