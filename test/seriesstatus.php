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
  echo "sorted ascending, null low";
  print_r($allteams);
  
  mergesort($allteams, uo_create_key_comparator($sort, true, false));
  
  echo "sorted ascending, null not low";
  print_r($allteams);
  mergesort($allteams, uo_create_key_comparator($sort, false, true));
  
  echo "sorted descending, null not low";
  print_r($allteams);
}

test_sorter();

?>