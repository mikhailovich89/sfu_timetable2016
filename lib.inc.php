<?php

// по номеру института (из БД) возвращает его название
function _sfu_timetable2016_institute($n, $abbr = FALSE) {
	static $institutes, $abbrs;
	if (!$institutes) {
		$institutes = array(
			1 => 'Военно-инженерный институт',
			2 => 'Гуманитарный институт',
			3 => 'Инженерно-строительный институт',
			4 => 'Институт архитектуры и дизайна',
			21 => 'Институт гастрономии',
			5 => 'Институт горного дела, геологии и геотехнологий',
			6 => 'Институт инженерной физики и радиоэлектроники',
			7 => 'Институт космических и информационных технологий',
			8 => 'Институт математики и фундаментальной информатики',
			9 => 'Институт нефти и газа',
			10 => 'Институт педагогики, психологии и социологии',
			11 => 'Институт управления бизнес-процессами и экономики',
			12 => 'Институт физической культуры, спорта и туризма',
			13 => 'Институт филологии и языковой коммуникации',
			14 => 'Институт фундаментальной биологии и биотехнологии',
			15 => 'Институт цветных металлов и материаловедения',
			16 => 'Институт экономики, управления и природопользования',
			17 => 'Политехнический институт',
			18 => 'Торгово-экономический институт',
			19 => 'Юридический институт',
			20 => 'Институт экологии и географии',
		);
		$abbrs = array(
			1 => '<abbr title="Военно-инженерный институт">ВИИ</abbr>',
			2 => '<abbr title="Гуманитарный институт">ГИ</abbr>',
			3 => '<abbr title="Инженерно-строительный институт">ИСИ</abbr>',
			4 => '<abbr title="Институт архитектуры и дизайна">ИАиД</abbr>',
			21 => '<abbr title="Институт гастрономии">ИГ</abbr>',
			5 => '<abbr title="Институт горного дела, геологии и геотехнологий">ИГДГиГ</abbr>',
			6 => '<abbr title="Институт инженерной физики и радиоэлектроники">ИИФиРЭ</abbr>',
			7 => '<abbr title="Институт космических и информационных технологий">ИКИТ</abbr>',
			8 => '<abbr title="Институт математики и фундаментальной информатики">ИМиФИ</abbr>',
			9 => '<abbr title="Институт нефти и газа">ИНиГ</abbr>',
			10 => '<abbr title="Институт педагогики, психологии и социологии">ИППС</abbr>',
			11 => '<abbr title="Институт управления бизнес-процессами и экономики">ИУБПЭ</abbr>',
			12 => '<abbr title="Институт физической культуры, спорта и туризма">ИФКСиТ</abbr>',
			13 => '<abbr title="Институт филологии и языковой коммуникации">ИФиЯК</abbr>',
			14 => '<abbr title="Институт фундаментальной биологии и биотехнологии">ИФБиБТ</abbr>',
			15 => '<abbr title="Институт цветных металлов и материаловедения">ИЦМиМ</abbr>',
			16 => '<abbr title="Институт экономики, управления и природопользования">ИЭУиП</abbr>',
			17 => '<abbr title="Политехнический институт">ПИ</abbr>',
			18 => '<abbr title="Торгово-экономический институт">ТЭИ</abbr>',
			19 => '<abbr title="Юридический институт">ЮИ</abbr>',
			20 => '<abbr title="Институт экологии и географии">ИЭиГ</abbr>',
		);
	}
	return $abbr ? $abbrs[$n + 0] : $institutes[$n + 0];
}

// номер дня недели (1..7) по его названию
function _sfu_timetable2016_day_number($day_name) {
	static $days;
	if (!$days) {
		$days = array(
			'понедельник' => 1,
			'вторник' => 2,
			'среда' => 3,
			'четверг' => 4,
			'пятница' => 5,
			'суббота' => 6,
			'воскресенье' => 7,
		);
	}
	return @$days[str_replace(' ', '', drupal_strtolower($day_name))];
}

// название дня недели с прописаной буквы по его номеру (1..7)
function _sfu_timetable2016_day_name($day_number) {
	static $days;
	if (!$days) {
		$days = array(
			1 => 'Понедельник',
			2 => 'Вторник',
			3 => 'Среда',
			4 => 'Четверг',
			5 => 'Пятница',
			6 => 'Суббота',
			7 => 'Воскресенье',
		);
	}
	return $days[$day_number + 0];
}

// проверка формата времени ленты и приведение к единому виду (08:30-10:05) или FALSE, если формат неверный
function _sfu_timetable2016_is_time($time) {
	if (preg_match("/^(\d\d?)[.:](\d\d)[ -.]+(\d\d?)[.:](\d\d)$/", trim($time,'.'), $matches)) {
		if (strlen($matches[1]) == 1) $matches[1] = '0' . $matches[1];
		if (strlen($matches[3]) == 1) $matches[3] = '0' . $matches[3];
		return "$matches[1]:$matches[2]-$matches[3]:$matches[4]";
	}
	else {
		if ($time != '') _sfu_timetable2016_log_show("Неверный формат времени ленты: $time", TRUE);
		return FALSE;
	}
}

// номер стандартной ленты по времени или FALSE, если время не стандартное
function _sfu_timetable2016_lenta_number($time) {
	static $lenty;
	if (!$lenty) {
		$lenty = array(
			'08:30-10:05' => 1,
			'10:15-11:50' => 2,
			'12:00-13:35' => 3,
			'14:10-15:45' => 4,
			'15:55-17:30' => 5,
			'17:40-19:15' => 6,
			'19:25-21:00' => 7,
			'21:10-22:45' => 8,
		);
	}
	$result = $lenty[$time];
	return $result ? $result : FALSE;
}

// возвращает двумерный массив из excel файла, FALSE в случае неудачи
// удаляет ведущие и концевые пробелы, заменяет последовательности пробельных символов одним пробелом
function _sfu_timetable2016_load_excel($filepath) {
	// загрузка файла
	require_once drupal_get_path('module', 'sfu_timetable2016') . "/phpexcel/Classes/PHPExcel/IOFactory.php";
	try {
		$excel = PHPExcel_IOFactory::load($filepath);
	}
	catch(Exception $e) {
		_sfu_timetable2016_log("Ошибка при разборе Excel-файла: $filepath", TRUE);
		return FALSE;
	}
	$excel->setActiveSheetIndex(0);
	$worksheet = $excel->getActiveSheet();
	$rows_number = $worksheet->getHighestRow();
	$cols_number = PHPExcel_Cell::columnIndexFromString($worksheet->getHighestColumn());
	//PHPExcel_Cell::stringFromColumnIndex($col)
	//$cell = $worksheet->getCellByColumnAndRow($col, $row)->getValue();

	// запоминаем скрытые строки
	$hidden_rows = array();
	for ($row = 1; $row <= $rows_number; $row++) {
		$rowDimension = $worksheet->getRowDimension($row);
		if (!$rowDimension->getVisible()) $hidden_rows[$row - 1] = TRUE;
	}

	/*
	// запоминаем скрытые колонки
	$hidden_cols = array();
	for ($col = 1; $col <= $cols_number; $col++) {
		$colDimension = $worksheet->getColumnDimensionByColumn($col);
		if (!$colDimension->getVisible()) $hidden_cols[$col - 1] = TRUE;
	}
	var_dump($hidden_cols);
	*/

	// получаем все строки
	$_rows = $worksheet->toArray();
	if (!$_rows) return FALSE;

	// дублируем объединённые ячейки
	$ranges = $worksheet->getMergeCells();
	foreach ($ranges as $range) {
		$range = PHPExcel_Cell::rangeBoundaries($range);
		list($col_start, $row_start) = $range[0];
		list($col_finish, $row_finish) = $range[1];
		$value = '';
		for ($row = $row_start; $row <= $row_finish; $row++) {
			for ($col = $col_start; $col <= $col_finish; $col++) {
				$value = $_rows[$row - 1][$col - 1];
				if ($value != '') break;
			}
			if ($value != '') break;
		}
		if ($value != '') {
			for ($row = $row_start; $row <= $row_finish; $row++) {
				for ($col = $col_start; $col <= $col_finish; $col++) {
					$_rows[$row - 1][$col - 1] = $value;
				}
			}
		}
	}

	// переносим в $rows не скрытые строки
	$rows = array();
	for ($i = 0; $i < count($_rows); $i++) {
		if (!isset($hidden_rows[$i])) $rows[] = $_rows[$i];
	}
	$rows_number = count($rows);

	// удаляем совершенно дубирующиеся колонки (могут появиться после операции раскрытия объединённых ячеек)
	// ищем начало таблицы с расписанием
	$first_row = 0;
	foreach ($rows as $row_number => $row) {
		if (drupal_strtolower($row[0]) == 'дни') {
			$first_row = $row_number;
			break;
		}
	}
	// ищем конец таблицы с расписанием
	for ($last_row = $first_row; $last_row < $rows_number; $last_row++) {
		$day = drupal_strtolower($rows[$last_row][0]);
		if (!@is_numeric($rows[$last_row][2]) && _sfu_timetable2016_empty_col($rows,0,$last_row) ||
		    $day != '' && $day != 'дни' && !_sfu_timetable2016_day_number($day) ||
		    strpos($rows[$last_row][1].$rows[$last_row][2],'ачальник учебного управления')) {
			break;
		}
	}
	$rows = array_slice($rows, 0, $last_row);
	if ($first_row) {
		// ищем дублирующиеся колонки
		$hidden_cols = array();
		for ($col = 1; $col < $cols_number; $col++) {
			$equals = TRUE;
			for ($row = $first_row; $row < $last_row; $row++) {
				if ($rows[$row][$col] != $rows[$row][$col - 1]) {
					$equals = FALSE;
					break;
				}
			}
			if ($equals) {
				$hidden_cols[$col] = $col;
			}
		}
		// переносим в $rows не дублирующие колонки
		if ($hidden_cols) {
			$_rows = $rows;
			$rows = array();
			for ($row = 0; $row < $first_row; $row++) $rows[$row] = $_rows[$row]; // копируем строки перед таблицей с расписанием (семестр, курс, институт и проч.)
			$current_col = 0;
			for ($col = 0; $col < $cols_number; $col++) {
				if (!isset($hidden_cols[$col])) {
					for ($row = $first_row; $row < $last_row; $row++) {
						$rows[$row][$current_col] = $_rows[$row][$col];
					}
					$current_col++;
				}
			}
		}
	}

	// удаляем пробелы
	foreach ($rows as &$row) {
		foreach ($row as $i => $cell) {
			$row[$i] = preg_replace('/\s+/u', ' ', trim($cell));
		}
	}

	// попытка чистки памяти
	$excel->disconnectWorksheets();
	$excel->__destruct();
	unset($excel);
	return $rows;
}

// удаление расписания по идентификатору
function _sfu_timetable2016_delete_timetable($tid) {
	/*
	if (!is_numeric($tid)) {
		$tid = db_result(db_query("SELECT tid FROM timetable WHERE filepath='%s' AND filemtime=%d", $tid, filemtime($tid)));
	}
	*/
	if ($tid && is_numeric($tid)) {
		db_query("DELETE FROM timetable_lenta WHERE tid=%d", $tid);
		db_query("DELETE FROM timetable WHERE tid=%d", $tid);
	}
}

// удаление всех расписаний по идентификатору ноды
function _sfu_timetable2016_delete_timetables_by_nid($nid) {
	$tids = db_query("SELECT tid FROM timetable WHERE nid=%d", $nid);
	while ($tid = db_fetch_object($tids)) {
		_sfu_timetable2016_delete_timetable($tid->tid);
	}
}

// сохраняет в базу распарсенный файл
function _sfu_timetable2016_parse2db($institute, $department, $nid, $course, $filepath) {
	// парсим
	if (!_sfu_timetable2016_parse($filepath, $timetables, $year, $semester) || !$timetables || !$year || !$semester) {
		if (!$year || !$semester) _sfu_timetable2016_log_show("В файле $filepath не найден семестр.", TRUE);
		_sfu_timetable2016_log_show("Ошибка в формате файла ${filepath}, пропущен.", TRUE);
		$tid = db_next_id('timetable');
		db_query(
			"INSERT INTO timetable (tid, institute, department, course, `group`, year, semester, filepath, filemtime, ready)
			VALUES (%d, %d, '%s', %d, '%s', %d, %d, '%s', %d, %d)",
			$tid, $institute, $department, $course, "error$tid", $year+0, $semester+0, $filepath, filemtime($filepath), -1
		);
		return FALSE;
	}

	// удаляем старое расписание
	//_sfu_timetable2016_delete_timetable($filepath);

	// добавляем в базу новое расписание
	foreach ($timetables as $group => $weeks) {
		$tid = db_next_id('timetable');
		if (!db_query(
			"INSERT INTO timetable (tid, institute, department, nid, course, `group`, year, semester, filepath, filemtime, ready)
			VALUES (%d, %d, '%s', %d, %d, '%s', %d, %d, '%s', %d, %d)",
			$tid, $institute, $department, $nid, $course, $group, $year, $semester, $filepath, filemtime($filepath), 0
		)) {
				_sfu_timetable2016_log_show("Не удалось сохранить расписание в БД. Вероятно, дублируется номер группы.", TRUE);
				continue;
		}
		foreach ($weeks as $week => $days) {
			foreach ($days as $day => $lenty) {
				foreach ($lenty as $lenta => $subject) {
					list($subject, $teacher, $type, $place) = $subject;
					if ($subject == '') continue;
					db_query(
						"INSERT INTO timetable_lenta (tid, week, day, time, subject, type, teacher, place, bid)
						VALUES (%d, %d, %d, '%s', '%s', '%s', '%s', '%s', NULL)",
						$tid, $week, $day, $lenta, $subject, $type, $teacher, $place
					);
				}
			}
		}
		db_query("UPDATE timetable SET ready=1 WHERE tid=%d", $tid);
	}
	return is_numeric($tid);
}

// проверка есть ли в БД актуальное расписание из этого файла
function _sfu_timetable2016_is_parsed($filepath) {
	return db_result(db_query("SELECT COUNT(*) FROM timetable WHERE filepath='%s' AND filemtime=%d AND (ready=1 OR ready=-1)", $filepath, filemtime($filepath))) > 0;
}

// возвращает текущую неделю (1 — нечётная, 2 — чётная)
function _sfu_timetable2016_week_parity($return_title = FALSE, $today = null) {
	$even = mktime(0, 0, 0, 8, 29, 2016); // первый день нечётной недели (unix timestamp)

	if ($today === null)
		$today = time(); // текущее время (unix timestamp)

	$week = 24 * 60 * 60 * 7; // длительность недели в секундах
	$weeks = (int) (($today - $even) / $week); // кол-во полностью прошедших недель
	// $result = $weeks % 2 == 0 ? 1 : 2; // чётность недели в виде числа — 1 или 2
	// С 01.09.2018 четность недели инверсировалась (по мнению УД)
	$result = $weeks % 2 == 0 ? 2 : 1;
	return $return_title ? ($result == 1 ? 'нечётная' : 'чётная') : $result;
}

// проверяет пустоту колонки начиная с указанной строки
function _sfu_timetable2016_empty_col($rows, $col, $from_row = 0) {
	for ($i = $from_row; $i < count($rows); $i++) {
		if (isset($rows[$i][$col]) && $rows[$i][$col] != '') {
			return FALSE;
		}
	}
	return TRUE;
}

// логирование и вывод на экран ошибок
function _sfu_timetable2016_log($message, $is_error = FALSE, $only_log = TRUE) {
	watchdog('timetable', $message, ($is_error?WATCHDOG_ERROR:WATCHDOG_NOTICE));
	if (!$only_log) drupal_set_message($is_error ? "ОШИБКА! $message" : $message);
}
function _sfu_timetable2016_log_show($message, $is_error = FALSE) {
	_sfu_timetable2016_log($message, $is_error, FALSE);
}
