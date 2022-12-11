<?php
$include_prefix = "../";
include '../lib/common.functions.php';

function test_sorter() {
  $sort = 'x';
  
  $allteams = array( 
    array('x' => 3, 'id' => 0), 
    array('x' => 2, 'id' => 1), 
    array('x' => 0, 'id' => 2), 
    array('x' => 2, 'id' => 3) );
  
  mergesort($allteams, uo_create_key_comparator($sort, true, true));
  echo "sorted ascending, null low\n";
  // print_r($allteams);
  assert($allteams[0]['id'] == 1);
  assert($allteams[3]['id'] == 2);
  
  mergesort($allteams, uo_create_key_comparator($sort, true, false));
  
  echo "sorted ascending, null not low\n";
  // print_r($allteams);
  assert($allteams[0]['id'] == 2);
  assert($allteams[1]['id'] == 1);
  
  mergesort($allteams, uo_create_key_comparator($sort, false, true));
  
  echo "sorted descending, null not low\n";
  // print_r($allteams);
  assert($allteams[0]['id'] == 0);
  assert($allteams[3]['id'] == 2);
  
  
  mergesort($allteams, create_function('$a,$b','return $a[\'x\']==$b[\'x\']?0:($a[\'x\']<$b[\'x\']?-1:1);'));
  assert($allteams[0]['id'] == 2);
  assert($allteams[1]['id'] == 1);
  // print_r($allteams);
}

test_sorter();

?>