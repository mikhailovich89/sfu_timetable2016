<?php

require('lib.inc.php');
require('parser.inc.php');

function drupal_strtolower($s) {return mb_strtolower($s, 'utf-8');}
function drupal_substr($s, $pos, $len = NULL) {return mb_substr($s, $pos, $len, 'utf-8');}
function drupal_get_path($a, $b) {return '.';}
function drupal_strlen($s) {return mb_strlen($s, 'utf-8');}

var_dump(_sfu_timetable2016_load_excel('1.xls'));

#var_dump($res=_sfu_timetable2016_parse('1.xls', $tt, $year, $semester));
#var_dump($tt);
