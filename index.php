<?php

initialize();

top();
main();
bottom();

function main()
{
	$pages = array(
		'introduction',
		'test',
		'result',
	);

	$current = !empty($_GET['page']) ? (string) $_GET['page'] : '';
	if (!in_array($current, $pages, true))
		$current = 'introduction';

	$function = 'page_' . $current;
	$function();
}

function page_introduction()
{
	global $site, $kanji;

	echo '
			<form action="', $site['index'], '?page=test" method="post">
				<h2><span>Welcome!</span></h2>
				<p>Welcome to the Japanese Kanji Practice Tool. This tool will help you practice ', count($kanji), ' first grade Japanese Kanji.</p>
				<p>Kanji is one of the scripts heavily used in the Japanese writing. Most of the words in the Japanese written language are written in Kanji (nouns, verbs, adjectives). There exists over 40,000 Kanji where about 2,000 represent over 95% of characters actually used in written text. There are no spaces in Japanese; so, Kanji is necessary in distinguishing between separate words within a sentence. Kanji is also useful for discriminating between homophones, which occurs quite often given the limited number of distinct sounds in Japanese.</p>
				<p>You will be shown a Kanji and four alternatives to choose from, as the meaning of Kanji you are given, in English. Select the appropriate option and continue with the next question. The time you spend on the test will be tracked. When you are ready, you can start by clicking on the Start! button below.</p>
				<div class="button"><input type="submit" name="start" value="Start!" /></div>
			</form>';
}

function page_test()
{
	global $site, $kanji, $question_limit;

	$time = time();

	if (!empty($_POST['start']))
	{
		session_regenerate_id();

		$_SESSION['log'] = 'log_' . md5(session_id() . microtime() . mt_rand() . 'kanji');

		$log = array(
			'start' => $time,
			'questions' => array(),
			'question' => 1,
			'used' => array(),
		);
		write_log($log);
	}
	elseif (!isset($_SESSION['log']) || !file_exists($site['dir'] . '/log/' . $_SESSION['log']))
		redirect();
	else
		$log = read_log();

	if (!empty($_POST['answer']))
	{
		$log['questions'][$log['question']]['end'] = $time;
		$log['questions'][$log['question']]['answer'] = (string) $_POST['answer'];
		$log['question']++;
	}

	foreach ($log['used'] as $used)
		unset($kanji[$used]);

	$question = get_random_kanji();
	if ($question === false || $log['question'] > $question_limit)
	{
		$log['end'] = $time;
		$log['completed'] = true;
		write_log($log);
		redirect('result');
	}

	reload_kanji();
	unset($kanji[$question['id']]);

	$options = array($question);
	for ($i = 0; $i < 3; $i++)
		$options[] = get_random_kanji();
	shuffle($options);

	$log['used'][] = $question['id'];
	$log['questions'][$log['question']] = array(
		'question' => $question,
		'options' => $options,
		'start' => $time,
	);
	write_log($log);

	echo '
			<form action="', $site['index'], '?page=test" method="post">
				<h2><span id="timer">', calculate_duration($time - $log['start']), '</span><span>Question ', $log['question'], ' of ', $question_limit, '</span></h2>
				<div class="question">
					<div class="kanji">', $question['parsed'], '</div>
					<ul class="test">';

	foreach ($options as $option)
		echo '
						<li><input type="submit" name="answer" value="', $option['meaning'], '" />';

	echo '
					</ul>
				</div>
			</form>
			<script type="text/javascript"><!-- // --><![CDATA[
				var current = ', $time - $log['start'], ';
				setInterval("update_timer()", 1000);
				function update_timer()
				{
					var hours, minutes, seconds, text = "";

					current = current + 1;

					hours = Math.floor(current / (60 * 60));
					seconds = current % (60 * 60);
					minutes = Math.floor(seconds / 60);
					seconds = seconds % 60;

					if (hours > 0)
						text = text + hours + (hours > 1 ? " hours " : " hour ");
					if (minutes > 0)
						text = text + minutes + (minutes > 1 ? " minutes " : " minute ");
					if (seconds > 0)
						text = text + seconds + (seconds > 1 ? " seconds " : " second ");

					document.getElementById("timer").innerHTML = text;
				}
			// ]]></script>';
}

function page_result()
{
	global $site;

	if (!isset($_SESSION['log']) || !file_exists($site['dir'] . '/log/' . $_SESSION['log']))
		redirect();
	else
		$log = read_log();

	if (empty($log['completed']))
		redirect();

	$total_seconds = $log['end'] - $log['start'];
	$time_spent = calculate_duration($total_seconds);
	$total_questions = count($log['questions']);
	$total_correct = 0;
	$total_incorrect = 0;

	foreach ($log['questions'] as $question)
	{
		if ($question['question']['meaning'] == $question['answer'])
			$total_correct++;
		else
			$total_incorrect++;
	}

	$success_rate = min(array(round(($total_correct * 100) / $total_questions), 100));

	$messages = array(
		95 => array('Excellent!', 'You have answered all of the questions correctly. You seem to have mastered first grade Kanji. Now on to the next level. Good luck!'),
		75 => array('Great job!', 'You have answered most of the questions correctly. You seem to have a solid knowledge of first grade Kanji but make sure you review your incorrect answers.'),
		50 => array('Good try...', 'Your knowledge of first grade Kanji is just above average. Make sure to carefully review your incorrect answers. You may want to go over your study materials as well.'),
		25 => array('Not bad...', 'Your knowledge of first grade Kanji is below the average. It would be a really good idea to review your study materials.'),
		-1 => array('Oops!', 'You could not answer most of the questions correctly. Make sure to review your study materials and take the test again.'),
	);

	foreach ($messages as $limit => $message)
	{
		if ($success_rate > $limit)
		{
			$feedback = $message;
			break;
		}
	}

	echo '
			<h2><span>', $feedback[0], '</span></h2>
			<p>', $feedback[1], '</p>
			<h2><span>Summary</span></h2>
			<dl>
				<dt>Number of questions:</dt>
				<dd>', $total_questions, '</dd>
				<dt>Number of correct answers:</dt>
				<dd>', $total_correct, '</dd>
				<dt>Number of incorrect answers:</dt>
				<dd>', $total_incorrect, '</dd>
				<dt>Success rate:</dt>
				<dd>%', $success_rate, '</dd>
				<dt>Time spent:</dt>
				<dd>', $time_spent, '</dd>
			</dl>
			<h2><span>Review</span></h2>
			<p>You can review your answers clicking on the questions below. The alternative marked with green is the correct answer, whereas the one marked with red is the alternative you have chosen for the incorrect answers. The time you spent on the question is given next to the question number as well.</p>';

	foreach ($log['questions'] as $number => $question)
	{
		echo '
			<h3><a href="#review_', $number, '" onclick="toggle_question(', $number, '); return false;">Question ', $number, '</a> - ', ($question['question']['meaning'] == $question['answer'] ? '<span class="correct">[correct]</span>' : '<span class="incorrect">[incorrect]</span>'), ' - (', calculate_duration($question['end'] - $question['start']), ')</h3>
			<div id="review_', $number, '" style="display: none;" class="review">
				<div>', $question['question']['parsed'], '</div>
				<ul>';

		foreach ($question['options'] as $option)
		{
			$class = '';
			if ($option['meaning'] == $question['answer'])
				$class = 'incorrect';
			if ($option['meaning'] == $question['question']['meaning'])
				$class = 'correct';
			echo '
					<li><span class="', $class,'">', $option['meaning'], ' (', $option['parsed'], ')</span></li>';
		}

		echo '
				</ul>
			</div>';
	}

	echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				function toggle_question(item)
				{
					var review_item = document.getElementById("review_" + item);
					review_item.style.display = review_item.style.display == "" ? "none" : "";
				}
			// ]]></script>';
}

function initialize()
{
	global $site, $kanji, $question_limit;

	ob_start();

	session_start();

	$site = array();

	$site['dir'] = dirname(__FILE__);
	$site['url'] = 'http://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/';
	$site['index'] = $site['url'] . 'index.php';

	$question_limit = 20;

	reload_kanji();
}

function reload_kanji()
{
	global $kanji;

	$kanji = array(
		array('4e00', 'one'),
		array('4e8c', 'two'),
		array('4e09', 'three'),
		array('56db', 'four'),
		array('4e94', 'five'),
		array('516d', 'six'),
		array('4e03', 'seven'),
		array('516b', 'eight'),
		array('4e5d', 'nine'),
		array('5341', 'ten'),
		array('767e', 'hundred'),
		array('5343', 'thousand'),
		array('4e0a', 'above'),
		array('4e0b', 'below'),
		array('5de6', 'left'),
		array('53f3', 'right'),
		array('4e2d', 'center'),
		array('5927', 'big'),
		array('5c0f', 'small'),
		array('6708', 'month'),
	);
}

function get_random_kanji()
{
	global $kanji;

	$total = count($kanji);
	if ($total < 1)
		return false;

	$random = mt_rand(1, $total);
	$counter = 1;

	foreach ($kanji as $id => $item)
	{
		if ($counter === $random)
		{
			$return = array(
				'id' => $id,
				'code' => $item[0],
				'parsed' => '&#x' . $item[0] . ';',
				'meaning' => $item[1],
			);

			unset($kanji[$id]);

			break;
		}

		$counter++;
	}

	return $return;
}

function write_log($log)
{
	global $site;

	file_put_contents($site['dir'] . '/log/' . $_SESSION['log'], serialize($log));
}

function read_log()
{
	global $site;

	$log = unserialize(file_get_contents($site['dir'] . '/log/' . $_SESSION['log']));

	return $log;
}

function calculate_duration($seconds)
{
	if ($seconds < 1)
		return 'less than a second';

	$return = '';

	$hours = floor($seconds / (60 * 60));
	$seconds %= 60 * 60;
	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;

	if ($hours > 0)
		$return .= $hours . ' ' . ($hours > 1 ? 'hours' : 'hour') . ' ';
	if ($minutes > 0)
		$return .= $minutes . ' ' . ($minutes > 1 ? 'minutes' : 'minute') . ' ';
	if ($seconds > 0)
		$return .= $seconds . ' ' . ($seconds > 1 ? 'seconds' : 'second') . ' ';

	return rtrim($return);
}

function redirect($page = '')
{
	global $site;

	header('Location: ' . $site['index'] . ($page !== '' ? '?page=' . $page : ''));
	exit();
}

function top()
{
	global $site;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<link rel="stylesheet" type="text/css" href="', $site['url'], 'style.css" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Japanese Kanji Practice</title>
</head>
<body>
	<div id="wrapper">
		<div id="header"><h1><span>Japanese Kanji Practice</span></h1></div>
		<div id="content">';
}

function bottom()
{
	global $site;

	echo '
		</div>
		<div id="footer">
			<span class="home">', strftime('%H:%M, %d/%m/%y'), ' | <a href="', $site['index'], '">Start Over</a></span>
			Japanese Kanji Practice &copy; 2011, Selman Eser &amp; Rasel Ahmed
		<div>
	</div>
</body>
</html>';
}

function debug($var)
{
	echo '<pre>';
	print_r($var);
	echo '</pre>';
}

?>