<?php

// BLUF telegram-feeder for events

// set up our BLUF environment - see the docs for what we're doing here
require_once('KEYStelegram.php') ;

require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;
require_once('core/BLUFclasses.php') ;

use Klev\TelegramBotApi\Telegram;
use Klev\TelegramBotApi\TelegramException;
use Klev\TelegramBotApi\Methods\SendMessage;
use Klev\TelegramBotApi\Methods\SendPhoto;

require('vendor/autoload.php') ;

$bot = new Telegram(BOT_KEY) ;

$blufDB = init_database('live') ;
$cache = new \BLUF\Cache\connection('live') ;

$options = getopt('', array('mode:'), $rest) ;
$text = trim(implode(' ', array_slice($argv, $rest)));


switch ($options['mode']) {
	case 'daily':
		// a list of events starting tomorrow
		$text = "Here's your summary of leather events starting tomorrow";

		$eventSQL = "SELECT id FROM events WHERE private = 'n' AND cancelled = 'n' AND startdate = DATE_ADD(CURRENT_DATE(), INTERVAL +1 DAY)" ;

		break ;

	case 'monthly':
		// just tell people how many things are happening this month
		$monthQ = $blufDB->query("SELECT id FROM events WHERE MONTH(startdate) = MONTH(CURRENT_DATE()) AND startdate >= CURRENT_DATE() AND private = 'n' AND cancelled = 'n'") ;
		telegram_post_text(sprintf("Welcome to %s! This month there are %d events in the #BLUFclub calendar. #gayleather Find them all here: <a href='http://bluf.com/events/thismonth'>bluf.com/events/thismonth</a>", date('F'), $monthQ->num_rows)) ;

		break ;

	case 'weekly':
		// a look ahead for the next seven days
		 $text = "Here's what's in the BLUF calendar this week" ;

		$eventSQL = "SELECT id FROM events WHERE private = 'n' AND cancelled = 'n' AND startdate >= CURRENT_DATE() AND startdate < DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) ORDER BY startdate ASC" ;

		break ;

	case 'updates':
		// what events have been updated this week?
		$changeQ = $blufDB->query("SELECT id FROM events WHERE updated > DATE_ADD(NOW(), INTERVAL -7 DAY) AND private = 'n' AND cancelled = 'n'") ;
		if ($changeQ->num_rows > 0) {
			telegram_post_text(sprintf("There are %d new and updated events in the #BLUFclub calendar <a href='https://bluf.com/e/latest'>bluf.com/e/latest</a> #gayleather", $changeQ->num_rows)) ;
		}

		break ;

	case 'post':
		// a helper for posting to the channel from the command line
		telegram_post_text($text) ;

		break ;

	case 'new':
		// what's been added to the calendar today
		$text = "These events have been added to the BLUF Calendar today";
		$eventSQL = "SELECT id FROM events, eventClassification WHERE eventid = id AND classification != 'unclassified' AND classifiedtime > DATE_ADD(NOW(), INTERVAL -1 DAY) AND private = 'n' AND cancelled = 'n' AND creator > 0" ;

		break ;
}

if (isset($eventSQL)) {
	$events = $blufDB->query($eventSQL) ;

	if ((strlen($text) > 0) && ($events->num_rows > 0)) {
		telegram_post_text($text) ;
	}

	while ($event = $events->fetch_assoc()) {
		telegram_post_event($event['id']) ;

		sleep(rand(5, 20)) ; // avoid flooding
	}
}

function telegram_post_text($post_text)
{
	global $bot ;

	$entry = new SendMessage(CHANNEL_ID, $post_text) ;
	$entry->parse_mode = 'html' ;

	$bot->sendMessage($entry) ;
}

function telegram_post_event($eventid)
{
	global $bot ;

	// use the id to get our event from the BLUF data store
	$event = new \BLUF\Calendar\event($eventid) ;

	// start building the post text for Telegram, using markdown
	$post_text = '*' . $event->name . "*" ;

	// this handles our multilingual markup
	$desc = new \BLUF\Text\multilingual($event->long_description) ;

	if (trim($desc->default) == '') {
		$description = $desc->lang_en ;
	} else {
		$description = $desc->default ;
	}
	$parser = new \BLUF\Text\parser($description) ;

	// turn the start date into something more readable
	$when = IntlDateFormatter::formatObject(new DateTime($event->startdate), 'EEEE d LLLL') ;

	// if the venue's not specified, give the city instead
	$where = (strlen($event->venue) == 0) ? $event->city : $event->venue ;

	$post_text .= "\n\n_" . $when . "\n\n" . $where . "_\n\n" . $parser->PlainText() ;

	// Captions can't be more than 1024 chars
	if (strlen($post_text) > 950) {
		$post_text = substr($post_text, 0, 950) . '...' ;
	}

	// add the link text to the end
	$post_text .= "\n\n[See more on bluf.com](https://bluf.com/e/" . $event->id . ")\n" ;

	if ($event->poster == null) {
		// text only
		$entry = new SendMessage(CHANNEL_ID, $post_text) ;
		$entry->parse_mode = 'markdown' ;
		try {
			$bot->sendMessage($entry) ;
		} catch (TelegramException $e) {
			printf("Unable to send event %s\n", $event->name) ;
		}
	} else {
		$entry = new SendPhoto(CHANNEL_ID, $event->poster) ;
		$entry->caption = $post_text ;
		$entry->parse_mode = 'markdown' ;

		try {
			$bot->SendPhoto($entry) ;
		} catch (TelegramException $e) {
			printf("Unable to send event %s\n", $event->name) ;
		}
	}
}
