<?php

use Klev\TelegramBotApi\Telegram;
use Klev\TelegramBotApi\TelegramException;
use Klev\TelegramBotApi\Methods\SetWebhook;

require 'vendor/autoload.php';

require_once('KEYStelegram.php') ;

try {
	$bot = new Telegram(BOT_KEY);

	if (!file_exists("webhook.trigger")) {
		$webhook = new SetWebhook(WEBHOOK_URL);
		$result = $bot->setWebhook($webhook);
		var_dump($result) ;
	}
} catch (TelegramException $e) {
	// log errors
	var_dump($e) ;
}
