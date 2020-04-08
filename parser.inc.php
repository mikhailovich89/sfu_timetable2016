<?php

// разбор файла с расписанием, на выходе массив: группа=>неделя=>день=>время=>(предмет,вид,преподаватель,аудитория)
function _sfu_timetable2016_parse($filepath, &$timetables, &$year, &$semester) {
	set_time_limit(30);
	$timetables = array();
	$semester = $year = FALSE;

	// загрузка сырых данных из excel-файла
	if (!($rows = _sfu_timetable2016_load_excel($filepath))) {
		_sfu_timetable2016_log_show("Не удалось распарсить Excel-данные: $filepath", TRUE);
		return FALSE;
	}

	// поиск колонок у групп
	$spec_group_cols = _sfu_timetable2016_find_groups($rows, $spec_row);

	// определение учебного года и семестра
	for ($col = 0; $col < 10; $col++) {
		for ($row = 0; $row < $spec_row; $row++) {
			$cell = drupal_strtolower($rows[$row][$col]);
			if (strstr($cell, 'семестр') !== FALSE && preg_match("@(осенний|весенний|веснний).+(\d{4})/\d{4}@u", $cell, $matches)) {
				$semester = $matches[1] == 'осенний' ? 1 : 2;
				$year = $matches[2];
				break;
			}
		}
		if ($semester) break;
	}

	// разбор расписания всех групп
	if (!$spec_group_cols) return FALSE;
	$timetables = array();
	foreach ($spec_group_cols as $spec => $groups) {
		foreach ($groups as $group => $group_col) {
			if ($group == '') {
				_sfu_timetable2016_log_show("Пустое название группы в расписании: $filepath, файл пропущен.", TRUE);
				return FALSE;
			}
			$timetables[$group] = _sfu_timetable2016_parse_group($rows, $spec_row, $group_col);
			if (!$timetables[$group]) {
				$timetables = array();
				return FALSE;
			}
		}
	}
	return TRUE;
}

// ищёт колонки групп, возвращает массив: направление=>группа=>номер колонки
function _sfu_timetable2016_find_groups($rows, &$row) {
	// ищем строку с направлениями
	$row = 0;
	$found = FALSE;
	foreach ($rows as $row_data) {
		$has_weeks = drupal_substr(drupal_strtolower($row_data[2]), 0, 5) == 'недел' ? 1 : 0;
		$found = drupal_strtolower($row_data[0]) == 'дни' && ($has_weeks && _sfu_timetable2016_parse_spec($row_data[3]) || !$has_weeks && _sfu_timetable2016_parse_spec($row_data[2]));
		if ($found) break;
		$row++;
	}
	if (!$found) {
		_sfu_timetable2016_log_show("Не найдена строка со специальностями, расписание пропущено.", TRUE);
		return FALSE;
	}
	$cols_number = count($rows[$row]); // кол-во колонок в строке специальностей
	$has_weeks = drupal_substr(drupal_strtolower($rows[$row][2]), 0, 5) == 'недел' ? 1 : 0; // есть колонка с неделями

	// подгруппы указаны в отдельной строке
	if ($subgroups_mode = (drupal_strtolower($rows[$row+2][0]) == 'дни' || $rows[$row+2][0] == '')) {
		$subgroups = $rows[$row+2];
	}

	// расписание есть для подгрупп, но нет строки с названиями подгрупп
	if (!$subgroups_mode) {
		$subgroups = array();
		$group_size = 1;
		for ($col = 2 + $has_weeks + 1; $col < $cols_number; $col++) {
			$group = $rows[$row+1][$col];
			$pre_group = $rows[$row+1][$col-1];
			if ($group != '' && $group == $pre_group) {
				$subgroups_mode = $auto_subgroups_mode = TRUE;
				$subgroups[$col-1] = "подгруппа $group_size";
				$group_size++;
				$subgroups[$col] = "подгруппа $group_size";
			}
			else {
				$group_size = 1;
			}
		}
		//if ($subgroups_mode) {print "subgroups:\n"; print_r($subgroups);}
		if ($subgroups_mode) _sfu_timetable2016_log_show("Не указаны подгруппы, пронумированы автоматически, требуется проверка.");
	}

	// ищем колонки направлений
	$spec_group_cols = array();
	for ($col = 2 + $has_weeks; $col < $cols_number; $col++) {
		$spec = $rows[$row][$col];
		if (in_array(drupal_strtolower($spec), array('дни', 'часы', 'недели', 'неделя'))) continue;
		$spec = _sfu_timetable2016_parse_spec($spec);
		if (!$spec) continue;
		list($spec_code, $spec_title) = $spec;
		$spec = trim("$spec_code $spec_title");
		for(;;) { // просмотр групп для направления
			$group = $rows[$row+1][$col];
			if ($subgroups_mode) { // режим с подгруппами
				//print "group=$group, subgroup=" . $subgroups[$col];
				if (isset($subgroups[$col]) && ($subgroup=$subgroups[$col]) != '') { // подгруппа указана
					if (drupal_substr($subgroup, 0, drupal_strlen($group)) != $group) { // подгруппа не включает название группы
						$group = "$group (" . trim($subgroup,'() ') . ')';
					}
				}
				//print ", result=$group\n";
			}
			if (isset($spec_group_cols[$spec][$group])) {
				_sfu_timetable2016_log_show("Дублирование названия группы ($group), пропуск расписания.", TRUE);
				return FALSE;
			}
			$spec_group_cols[$spec][$group] = $col;
			$col++;
			if ($rows[$row][$col] != $rows[$row][$col-1] && $rows[$row][$col] != '' || $rows[$row+1][$col] == '') break;
		}
		$col--;
	}

	if ($subgroups_mode && !$auto_subgroups_mode) $row++;
	//print_r($spec_group_cols);
	return $spec_group_cols;
}

// разбирает расписание группы, возвращает массив: неделя=>день=>лента=>(предмет,преподаватель,вид,аудитория) или FALSE при ошибке
function _sfu_timetable2016_parse_group($rows, $spec_row, $group_col) {
	$week_day_lenty = array(); // результирующее расписание
	$rows_number = count($rows); // кол-во строк в таблице
	$row = $spec_row + 2; // начальный ряд с расписанием
	while ($row < $rows_number) {
		$day = _sfu_timetable2016_day_number($rows[$row][0]); // день недели из первой колонки
		if (!$day) break;
		// просмотр всех лент (вторая колонка)
		$pre_time = 0;
		$no_weeks_mode = drupal_substr(drupal_strtolower($rows[$spec_row][2]), 0, 5) != 'недел';
		for(;;) {
			$time = isset($rows[$row][1]) ? _sfu_timetable2016_is_time($rows[$row][1]) : '';
			if (!$time || strcmp($time,$pre_time) < 0 || $rows[$row][0] != '' && $day != _sfu_timetable2016_day_number($rows[$row][0])) break;

			// случай, когда только одна строка для ленты (ЮИ)
			if ($rows[$row][1] != $rows[$row+1][1]) {
				if (!$no_weeks_mode) {_sfu_timetable2016_log_show("Несправимая ошибка в формате: одна строка на ленту вместо трёх", TRUE); return FALSE;}
				_sfu_timetable2016_log_show("Исправимая ошибка в формате: одна строка на ленту вместо трёх");
				$week_day_lenty[1][$day][$time] = $week_day_lenty[2][$day][$time] = array($rows[$row][$group_col]);
				$row++;
			}
			else {
				// две пустые клетки вместо номеров недель
				if (!$no_weeks_mode && $rows[$row][2] == '' && $rows[$row + 3][2] == '' && $rows[$row][1] == $rows[$row + 3][1]) {
					$rows[$row][2] = '1';
					$rows[$row + 3][2] = '2';
				}
				// разбор первой недели
				$week = $no_weeks_mode ? 1 : $rows[$row][2];
				if ($week != 1 && $week != 2) {_sfu_timetable2016_log_show("Неверный номер недели: строка=$row, номер недели=$week", TRUE); return FALSE;}
				if (isset($week_day_lenty[$week][$day][$time])) _sfu_timetable2016_log_show("Уже есть лента на это время, возможна ошибка в формате: неделя=$week, день=$day, время=$time", TRUE);
				$week_day_lenty[$week][$day][$time] = _sfu_timetable2016_parse_subject($rows, $row, $group_col);
				// разбор второй недели, если есть
				if ($no_weeks_mode) $week_day_lenty[2][$day][$time] = $week_day_lenty[1][$day][$time];
				$week2 = $rows[$row + 3][2];
				if (!$no_weeks_mode && $week2 == 2 && $week == 1 && $rows[$row][1] == $rows[$row + 3][1]) {
					$week_day_lenty[$week2][$day][$time] = _sfu_timetable2016_parse_subject($rows, $row + 3, $group_col);
					$row += 6;
				}
				else {
					$row += 3;
				}
			}
			$pre_time = $time;
		}
		if (!$time) break;
	}
	return $week_day_lenty;
}

function _sfu_timetable2016_ivanov_ab($matches) {return "$matches[1] $matches[2]. $matches[3].";}
function _sfu_timetable2016_ab_ivanov($matches) {return "$matches[3] $matches[1]. $matches[2].";}

// разбор трёх строчек с информацией о конкретной ленте, возвращает массив: (название,преподаватель,вид,аудитория)
function _sfu_timetable2016_parse_subject($rows, $row, $col) {
	// виды занятий
	static $types;
	if (!$types) {
		$types = array(
			'л' => 'лекция',
			'лек' => 'лекция',
			'лекции и семинары' => 'лекции и семинары',
			'лекция' => 'лекция',
			'лекции' => 'лекция',
			'пр.зан' => 'практическое занятие',
			'пр. зан' => 'практическое занятие',
			'пр' => 'практика',
			'практика' => 'практика',
			'практкика' => 'практика',
			'прктика' => 'практика',
			'лаб. раб' => 'лабораторная работа',
			'лаб.раб' => 'лабораторная работа',
			'лаб' => 'лабораторная работа',
			'семинар' => 'семинар',
			'семинары' => 'семинары',
			'сем' => 'семинар',
		);
	}

	list($subject, $teacher, $place) = array($rows[$row][$col], $rows[$row+1][$col], $rows[$row+2][$col]);
	if ($subject == '') return array();

	// приведение к приличному виду преподавателя
	if ($teacher == $subject) $teacher = '';
	//$teacher = preg_replace('/[.](\S)/u', '. $1', $teacher);
	/*
	if ($teachers_number = preg_match_all('/((?:\p{L}|-){2,}) (\p{L})[.] ?(\p{L})[.](?! ?\p{L}[.])/u', $teacher, $matches)) {
		$teachers = array();
		for ($i = 0; $i < $teachers_number; $i++) {
			$teachers[] = $matches[1][$i] . ' ' . $matches[2][$i] . '. ' . $matches[3][$i] . '.';
		}
		$teacher = join(', ', $teachers);
	}
	elseif ($teachers_number = preg_match_all('/(?!\p{L}[.] ?)(\p{L})[.] ?(\p{L})[.] ?((?:\p{L}|-){2,})/u', $teacher, $matches)) {
		$teachers = array();
		for ($i = 0; $i < $teachers_number; $i++) {
			$teachers[] = $matches[3][$i] . ' ' . $matches[1][$i] . '. ' . $matches[2][$i] . '.';
		}
		$teacher = join(', ', $teachers);
	}
	elseif ($teacher != '') {
		//_sfu_timetable2016_log_show("Ошибка в формате преподавателя: $teacher", TRUE);
	}
	*/
	$teacher = preg_replace_callback('/((?:\p{L}|-){2,}) (\p{L})[.] ?(\p{L})[.](?! ?\p{L}[.])/u', '_sfu_timetable2016_ivanov_ab', $teacher);
	$teacher = preg_replace_callback('/(?!\p{L}[.] ?)(\p{L})[.] ?(\p{L})[.] ?((?:\p{L}|-){2,})/u', '_sfu_timetable2016_ab_ivanov', $teacher);

	// поиск вида ленты
	$type = '';
	$commas_in_place = count(explode(',', $place)) - 1;
	if ($commas_in_place <= 1) {
		foreach ($types as $type_prefix => $type_title) {
			if (preg_match("!^$type_prefix(?:[ /,.]|$)(.*)$!iu", $place, $matches)) {
				$type = $type_title;
				if ($commas_in_place == 0) $place = ltrim($matches[1], ' /,.');
				break;
			}
		}
	}
	if (preg_match("/^\d+ заняти/", $teacher)) {
		$type = $type == '' ? $teacher : "$type, $teacher";
		$teacher = '';
	}

	// приведение к приличному виду места проведения
	if ($place == $subject) $place = '';
	//if (preg_match('/^\p{L}? *[0-9-]{3,}$/u', $place)) $place = "ауд. $place";
	//elseif (drupal_substr($place, 0, 2) == 'а.') $place = 'ауд. ' . trim(drupal_substr($place, 2, 200));
	if ($place == 'ауд.') $place = '';
	//$place = preg_replace('/ауд[.](\S)/ui', 'ауд. $1', $place); // ауд.31-07 => ауд. 31-07

	return array($subject, $teacher, $type, $place);
}

// парсит строку с направлением вида: 47.03.01 Философия
// возвращает массив (код,название) или FALSE, если строка не соответствует формату
function _sfu_timetable2016_parse_spec($title) {
	if (preg_match('/^(?:\p{L}+ )?(\d{2,6}.*?)(?: (.*))?$/u', $title, $matches)) {
		return array($matches[1], $matches[2]);
	}
	elseif (preg_match('/^\p{L}(\p{L}|[0-9 ,;.-])+$/u', $title)) {
		return array('', $title);
	}
	else {
		return FALSE;
	}
}
