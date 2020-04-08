<?php

///////////////////////////////// ВКЛАДКИ и ТАБЛИЦА С РАСПИСАНИЕМ /////////////////////////////////

// active_tab = groups | teachers | places
function _sfu_timetable2016_theme_tabs($active_tab = FALSE, $full_url = FALSE) {
	$active[$active_tab] = ' active';
	$href = $full_url ? url($_GET['q']) : '';

	// 2018-10-10: вкладка "Аудитории" скрыта на некоторое время.
	return "<nav class='tabs'><ul class='tabs-items'>
	       <li class='tabs-item$active[groups]'><a href='$href#groups' class='tabs-item-link'><span class='tabs-item-title'>Группы</span></a></li>
	       <li class='tabs-item$active[teachers]'><a href='$href#teachers' class='tabs-item-link'><span class='tabs-item-title'>Преподаватели</span></a></li>
	       </ul></nav>";

	return "<nav class='tabs'><ul class='tabs-items'>
	       <li class='tabs-item$active[groups]'><a href='$href#groups' class='tabs-item-link'><span class='tabs-item-title'>Группы</span></a></li>
	       <li class='tabs-item$active[teachers]'><a href='$href#teachers' class='tabs-item-link'><span class='tabs-item-title'>Преподаватели</span></a></li>
	       <li class='tabs-item$active[places]'><a href='$href#places' class='tabs-item-link'><span class='tabs-item-title'>Аудитории</span></a></li>
	       </ul></nav>";
}

function _sfu_timetable2016_subjects_equals($s1, $s2) {
	return $s1->subject == $s2->subject && $s1->teacher == $s2->teacher && $s1->type == $s2->type && $s1->place == $s2->place;
}

// $db_lenty — выборка всех полей из timetable_lenta с сортировкой по day, time, week
function _sfu_timetable2016_theme($db_lenty) {
	// вывод чётности недели
	$output .= '<p><b>Идёт ' . _sfu_timetable2016_week_parity(TRUE) . ' неделя</b> (расписание на текущую неделю выделено в таблице).</p>';

	// перенос выборки из БД в массив с проверкой дублей
	$lenty = array();
	while ($subject = db_fetch_object($db_lenty)) {
		// расстановка ссылок
		foreach(array('teacher','place','group') as $fieldname) {
			if ($subject->{$fieldname} != '') $subject->{$fieldname} = "<a class='no-link' href='?$fieldname=" . urlencode($subject->{$fieldname}) . "'>" . $subject->{$fieldname} . "</a>";
		}

		// проверка дублей
		$pre_subject = $lenty[$subject->day][$subject->time][$subject->week];
		if ($pre_subject) {
			if (_sfu_timetable2016_subjects_equals($subject,$pre_subject)) { // всё совпало, кроме может группы
				$subject->group = trim($pre_subject->group . ', ' . $subject->group, ', ');
			}
			else { // что-то не совпало, конфликт расписания
				foreach(array('subject','teacher','type','place','group') as $fieldname) {
					$subject->{$fieldname} = trim($pre_subject->{$fieldname} . ' // ' . $subject->{$fieldname}, '/ ');
					if ($subject->{$fieldname} != '') $subject->{$fieldname} = '<span class="conflict">' . $subject->{$fieldname} . '</span>';
				}
			}
		}
		$lenty[$subject->day][$subject->time][$subject->week] = $subject;
	}

	$output .= "<table class='table timetable'>";
	$header = "<tr class='heading'><th>№</th><th>Время</th><th>Нечётная неделя</th><th>Чётная неделя</th></tr>";
	$week_parity = _sfu_timetable2016_week_parity();
	foreach ($lenty as $day => $lenty_day) {
		$output .= "<tr class='heading heading-section'><th colspan='4'>" . _sfu_timetable2016_day_name($day) . "</th></tr>"; // день недели
		$output .= $header;
		foreach ($lenty_day as $time => $lenty_time) {
			$lenta_number = _sfu_timetable2016_lenta_number($time);
			$output .= "<tr class='table-center'><td width='1%'>$lenta_number</td><td class='nobr' width='1%'>$time</td>"; // номер и время лент
			// расписание на нечётную и чётную недели совпадают
			if (_sfu_timetable2016_subjects_equals($lenty_time[1],$lenty_time[2]) && $lenty_time[1]->group == $lenty_time[2]->group) {
				$subjects = array(1 => $lenty_time[1]);
				$colspan = ' colspan="2" ';
			}
			else {
				$subjects = array(1 => $lenty_time[1], 2 => $lenty_time[2]);
				$colspan = '';
			}
			foreach ($subjects as $week => $subject) {
				$class = $week_parity == $week || $colspan ? ' class="light" ' : '';
				if ($subject->subject != '') {
					$type = $subject->type == '' ? '' : " ($subject->type)";
					$teacher = $subject->teacher == '' ? '' : "<br><em>$subject->teacher</em>";
					$place = $subject->place == '' ? '' : "<br>$subject->place";
					$group = $subject->group == '' ? '' : "$subject->group<br>";
					$output .= "<td width='40%'$colspan$class>$group<b>$subject->subject</b>$type$teacher$place</td>";
				}
				else {
					$output .= "<td width='40%'$colspan$class></td>";
				}
			}
			$output .= "</tr>";
		}
	}
	$output .= '</table>';

	return $output;
}

///////////////////////////////// РАСПИСАНИЕ ГРУППЫ /////////////////////////////////

function _sfu_timetable2016_theme_group($group, $check_year = FALSE, $check_semester = FALSE) {
	// определение идентификатора расписания группы
	$timetable = db_fetch_object(db_query("SELECT * FROM timetable WHERE `group`='%s'", $group));
	if (!$timetable) {
		drupal_set_message('Открыта страница с неверным номером группы, возможно произошли какие-то изменения в расписании. Выберите нужную группу из расписания вашего института.');
		drupal_goto($_GET['q']);
	}

	// вкладки, поиск, подзаголовок
	$output .= _sfu_timetable2016_theme_tabs('groups', TRUE);
	$output .= drupal_get_form('timetable_groups_search_form');
	$output .= "<h3>" . _sfu_timetable2016_institute($timetable->institute) . ": группа " . check_plain($timetable->group) . "</h3>";

	// проверка года и семестра
	if ($check_year && $check_semester && ($timetable->year != $check_year || $timetable->semester != $check_semester)) {
		$output .= "<p>Расписание для данной группы на семестр ещё не размещено.</p>";
		return $output;
	}

	// выборка и форматирование таблицы
	$db_lenty = db_query("SELECT * FROM timetable_lenta WHERE tid=%d ORDER BY day, time, week, subject, lid", $timetable->tid);
	$output .= _sfu_timetable2016_theme($db_lenty);
	$output .= '<p>' . l('Скачать в формате Excel', $timetable->filepath, array('class' => 'link-text')) . ' (.xls)</p>';
	return $output;
}

///////////////////////////////// СПИСОК ГРУПП /////////////////////////////////

function _sfu_timetable2016_theme_listing() {
	// админу
	$is_admin = node_access('create', 'timetable');
	$destination = urlencode(ltrim(url($_GET['q']), '/'));
	$edit_icon = theme('image', drupal_get_path('module', 'sfu_timetable2016') . '/img/edit.png');

	// вкладки
	$output .= _sfu_timetable2016_theme_tabs('groups');

	// группы
	$output .= "<section class='tabs-page active timetable-groups' id='groups'>";
	$timetables = db_query("SELECT tid, nid, institute, department, course, `group`, filepath FROM timetable WHERE ready=1 ORDER BY institute, department, course, `group`");
	$output .= '<p><b>Идёт ' . _sfu_timetable2016_week_parity(TRUE) . ' неделя.</b></p><p>Введите номер группы:</p>';
	$output .= drupal_get_form('timetable_groups_search_form');
	while ($timetable = db_fetch_object($timetables)) {
		$_timetables[$timetable->institute]->L[$timetable->department]->timetable = $timetable;
		$_timetables[$timetable->institute]->L[$timetable->department]->L[$timetable->course]->L[$timetable->group] = $timetable;
	}
	$output .= "<br><p>Или выберите группу из списка:</p><ul>";
	$institutes = array(); // для сортировки по алфавиту
	foreach ($_timetables as $institute => $departments) {
		$institute = _sfu_timetable2016_institute($institute);
		$output2 = "<li><div class='collapsed-block'><a href='#show' class='trigger no-visited'><span class='trigger-title'>$institute</span></a>";
		$output2 .= "<div class='collapsed-content'>";
		foreach ($departments->L as $department => $courses) {
			if ($department != '') $output2 .= "<p><b>$department</b></p>";
			$output2 .= $is_admin ? l($edit_icon, "node/" . $courses->timetable->nid . "/edit", array(), "destination=$destination", NULL, FALSE, TRUE) : '';
			$output2 .= '<ul>';
			foreach ($courses->L as $course => $groups) {
				$output2 .= "<li><div class='collapsed-block'><a href='#show' class='trigger no-visited'><span class='trigger-title'>" . ($course >= 7 ? ($course-6) . "&nbsp;курс&nbsp;магистратуры" : "$course&nbsp;курс") . "</span></a>";
				$output2 .= "<div class='collapsed-content'><ul>";
				foreach ($groups->L as $group => $timetable) {
					$group = preg_replace('/ (\d)/u', '&nbsp;\1', str_replace('( ', '(', preg_replace('/(\d) /u', '\1&nbsp;', check_plain($group))));
					$output2 .= "<li><a href='?group=" . urlencode($timetable->group) . "'>$group</a></li>";
				}
				$output2 .= '</ul></div></div></li>';
			}
			$output2 .= '</ul>';
			//$output2 .= '<p>' . l('Скачать в формате Excel', $courses->timetable->filepath, array('class' => 'link-text')) . ' (.xls)</p>';
		}
		$output2 .= "</div></li>";
		$institutes[$institute] = $output2;
	}
	ksort($institutes);
	$output .= join('', $institutes);
	$output .= '</ul>';

	$output .= "<br><p><a class='link-outside' href='/timetable.xls'>Показать расписание в Excel</a></p>";
	if ($is_admin) $output .= l("<span class='button-title'>Добавить расписание</span>", "node/add/timetable", array('class' => 'button'), "destination=$destination", NULL, FALSE, TRUE);
	$output .= "</section>";

	// преподаватели
	$output .= "<section class='tabs-page' id='teachers'>";
	$output .= drupal_get_form('timetable_teachers_search_form');
	$output .= "</section>";

	// аудитории
	$output .= "<section class='tabs-page' id='places'>";
	$output .= drupal_get_form('timetable_places_search_form');
	$output .= "</section>";

	return $output;
}

///////////////////////////////// ПОИСКОВЫЕ ФОРМЫ /////////////////////////////////

// форма для поиска расписания по группе/преподавателю/аудитории
function _timetable_search_form_helper($fieldname, $autocomplete_path, $placeholder) {
	$form[$fieldname] = array('#type' => 'textfield', '#autocomplete_path' => $autocomplete_path, '#attributes' => array('placeholder' => $placeholder));
	$form['submit'] = array('#type' => 'submit', '#value' => 'Показать расписание');
	return $form;
}
// обработчик формы для поиска расписания
function _timetable_search_form_submit_helper(&$form_values, $fieldname, $table, $table_fieldname, $redirect_param, $notfound_message, $notfound_fragment) {
	$value = $form_values[$fieldname];
	$value = db_result(db_query("SELECT `${table_fieldname}` FROM $table WHERE LOWER(`${table_fieldname}`)=LOWER('%s')", $value));
	if ($value != '') {
		drupal_goto($_GET['q'], "$redirect_param=" . urlencode($value));
	}
	else {
		drupal_set_message($notfound_message, 'error');
		drupal_goto($_GET['q'], NULL, $notfound_fragment);
	}
}

// форма поиска группы
function timetable_groups_search_form() {
	return _timetable_search_form_helper('group', 'timetable/groups/autocomplete', 'Группа');
}
function timetable_groups_search_form_submit($form_id, &$form_values) {
	_timetable_search_form_submit_helper($form_values, 'group', 'timetable', 'group', 'group', 'Выберите группу.', 'groups');
}

// форма поиска преподавателя
function timetable_teachers_search_form() {
	return _timetable_search_form_helper('teacher', 'timetable/teachers/autocomplete', 'ФИО преподавателя');
}
function timetable_teachers_search_form_submit($form_id, &$form_values) {
	_timetable_search_form_submit_helper($form_values, 'teacher', 'timetable_lenta', 'teacher', 'teacher', 'Преподаватель не найден.', 'teachers');
}

// форма поиска аудиторий
function timetable_places_search_form() {
	return _timetable_search_form_helper('place', 'timetable/places/autocomplete', 'Номер аудитории');
}
function timetable_places_search_form_submit($form_id, &$form_values) {
	_timetable_search_form_submit_helper($form_values, 'place', 'timetable_lenta', 'place', 'place', 'Аудитория не найдена.', 'places');
}


///////////////////////////////// РАСПИСАНИЕ ПРЕПОДАВАТЕЛЯ /////////////////////////////////

function _sfu_timetable2016_theme_teacher($teacher, $check_year = FALSE, $check_semester = FALSE) {
	// вкладки, поиск, подзаголовок
	$output .= _sfu_timetable2016_theme_tabs('teachers', TRUE);
	$output .= drupal_get_form('timetable_teachers_search_form');
	$output .= "<h3>Преподаватель: " . check_plain($teacher) . "</h3>";

	// выборка и форматирование таблицы
	$where = $check_year && $check_semester ? "AND T.year=" . ($check_year+0) . " AND T.semester=" . ($check_semester+0) : '';
	$db_lenty = db_query("SELECT T.institute, T.group, L.* FROM timetable_lenta L JOIN timetable T ON T.tid=L.tid WHERE L.teacher='%s' $where ORDER BY L.day, L.time, L.week, L.subject, L.lid", $teacher);
	$output .= _sfu_timetable2016_theme($db_lenty);
	return $output;
}


///////////////////////////////// РАСПИСАНИЕ АУДИТОРИИ /////////////////////////////////

function _sfu_timetable2016_theme_place($place, $check_year = FALSE, $check_semester = FALSE) {
	// вкладки, поиск, подзаголовок
	$output .= _sfu_timetable2016_theme_tabs('places', TRUE);
	$output .= drupal_get_form('timetable_places_search_form');
	$output .= "<h3>Аудитория: " . check_plain($place) . "</h3>";

	// выборка и форматирование таблицы
	$where = $check_year && $check_semester ? "AND T.year=" . ($check_year+0) . " AND T.semester=" . ($check_semester+0) : '';
	$db_lenty = db_query("SELECT T.institute, T.group, L.* FROM timetable_lenta L JOIN timetable T ON T.tid=L.tid WHERE L.place='%s' $where ORDER BY L.day, L.time, L.week, L.subject, L.lid", $place);
	$output .= _sfu_timetable2016_theme($db_lenty);
	return $output;
}
