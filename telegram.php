<?php

// BLUF webhook handler for the Telegram bot

require_once('KEYStelegram.php') ;

require_once('api/apiconfig.php') ;
require_once('api/apifunctions.php') ;
require_once('core/BLUFclasses.php') ;

use Klev\TelegramBotApi\Telegram;
use Klev\TelegramBotApi\TelegramException;
use Klev\TelegramBotApi\Methods\SendMessage;
use Klev\TelegramBotApi\Methods\SendPhoto;

require('vendor/autoload.php') ;

$blufDB = init_database('live') ;

date_default_timezone_set('UTC') ;
setlocale(LC_ALL, 'en_EN.UTF8') ;

try {
	$bot = new Telegram(BOT_KEY) ;

	$update = $bot->getWebhookUpdates();

	if ($update->message) {
		$chatId = $update->message->chat->id;
		$username = $update->message->from->first_name;

		if ($update->message->text == "/start") {
			// this is the friendly message we send when someone starts chatting with us
			$text = "<i>Hello, $username</i>";
			$text .= "\nThis bot posts scheduled updates to the @BLUFCalendar channel, and you can also ask for an instant summary of events in a particular city, for the next 30 days." ;
			$text .= "\nJust type the name of the city, and we'll send you the results." ;

			$result = $bot->sendMessage(new SendMessage($chatId, $text));
		} else {
			$request = trim($update->message->text) ;

			if (strlen($request) > 0) {
				// find matching cities in our event database, plus the title and start date for events, in the next 30 days
				$sql = sprintf("SELECT title, startdate FROM events WHERE private = 'n' AND ( city LIKE '%%%s%%' OR localisedcity LIKE '%%%s%%') AND  startdate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL +30 DAY) ORDER BY startdate ASC", $blufDB->real_escape_string($request), $blufDB->real_escape_string($request)) ;
				$events = $blufDB->query($sql) ;

				if ($events->num_rows == 0) {
					$text = sprintf("Sorry, we can't find any events in the next 30 days that match your search for %s", $request) ;
					$result = $bot->sendMessage(new SendMessage($chatId, $text));
				} else {
					$text = sprintf("<b>Here's your roundup of what's happening in %s in the next 30 days</b>\n\n", $request) ;

					// iterate over the results, adding them to the post text
					while ($e = $events->fetch_assoc()) {
						$when = strftime('%A %d %B %Y', strtotime($e['startdate'])) ;
						$text .=  sprintf("%s on %s

", $e['title'], $when) ;
					}

					$text .= sprintf("<i>See more details at <a href='https://bluf.com/e/%s'>bluf.com/e/%s</a></i>", urlencode(strtolower($request)), urlencode(strtolower($request))) ;

					$result = $bot->sendMessage(new SendMessage($chatId, $text));
				}
			}
		}
	}
} catch (TelegramException $e) {
	// log errors to the address defined in our apiconfig.php file
	mail(ERROR_EMAIL, 'Telegram exception', print_r($e, true)) ;
}
