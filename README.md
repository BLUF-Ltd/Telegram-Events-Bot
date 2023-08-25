# BLUF events Telegram bot

_August 2023_

This document describes how we built the BLUF Telegram bot, which posts automated announcements of events 
to our BLUF Calendar channel, at [t.me/BLUFcalendar](https://t.me/BLUFcalendar).

## Getting started
First, you'll need to create a Bot user on Telegram. Set up your own account, and then create a channel to which
you want the bot to post, like BLUF Calendar, by selecting New Channel in the Telegram app.

Then, you start a conversation with the [Bot Father bot](https://t.me/BotFather), which will guide you through the
process when you send it the command

		/start

Give the bot a friendly name (ours is called BLUFcal), and then a username, which must end in bot; ours is BLUFCalendarBot.

You'll receive a message which contains a token, which can be used to access the Telegram API. Make a note of this, and save
it somewhere safe. We have a folder outside the web server space, but in the PHP include path, and all the keys for
Telegram are in the file KEYStelegram.php:

		// BLUF keys for Telegram calendar bot
		//
		define('BOT_KEY', 'token_you_got_from_botfather) ;
		define('CHANNEL_ID', '@BLUFcalendar') ;
		define('WEBHOOK_URL', 'https://somewhere.on.your.server/path/to/telegram.php') ;
		
The webhook URL you see there is for the optional part of this code that lets someone send a message to the bot with the
name of a city, to see what's coming up in that location.

When the bot has been successfully created, go to your channel in the Telegram app, select Manage Channel, and add the bot
as an administrator, which will allow it to post messages.

## Setting up your environment
To handle interaction with the Telegram api, we're using the [Telegram Bot API library](https://github.com/klev-o/telegram-bot-api).
You'll need to add this to your system using composer:

		composer require klev-o/telegram-bot-api
		
There are some other files you'll see included in the scripts. Here's a quick guide

### apiconfig.php
This file contains various constant definitions, including parameters to access the database.

### apifunctions.php
This contains functions to initialise our database connection, connecting to either our live or test server, and to initialise
the Redis cache, which helps reduce the load on the database.

### BLUFClasses.php
This is the autoloader for the various BLUF helper classes, which provides us with tools for things like managing multilingual
texts, retrieving stuff from the database or the cache. Obviously, you'll need something equivalent that will handle access to 
your own database of events.

Here's a quick explanation of how it all hangs together in our implementation. You'll need to do something similar.

Our \BLUF\Calendar\event class can be called with the id number of an event, which will cause the specified event to be loaded.
The key properties that are used are

		int $id ;
		string $name ;
		string $startdate ;
		string $venue ;
		string $city ;
		string $poster ;
		\BLUF\Text\multilingual $long_description ;
		
The multilingual text object parses a piece of text to detect language markup, splitting it into separate elements for each of the
four languages (english, german, french, spanish) that we support. If there's no markup, then the $default property has all the text,
otherwise the english will be in the $lang_en property.

These descriptions may also have other markup in them, including BLUF markup for things like profile references, and ordinary Markdown.
So, our \BLUF\text\parser object handles the pre-processing required for that.

What you need in your implementation is something that will return a what, when, where and description for each of your events; our
classes handle that for us, inside our main bot code's telegram_post_event code.

We build the text for an event by taking the event name and wrapping it in Markdown tags, to make it bold. Then the date and location
are wrapped in italic tags, using the venue name if available, otherwise the city.

Then we append the plain text for the description, followed by a link to the public page on the BLUF calendar.

## The main Telegram feed
The main part of this project is the [event_telegram_feed.php](event_telegram_feed.php) file. This is a script that's designed to be run at intervals, for example
from cron. It takes at least one parameter, which is the mode option, eg 

		php8.2 event_telegram_feed.php --mode=daily

Internally, if an option defines a query string, that's used to find matching events in the database; as long as there are matching
events, any defined text is posted, followed by a post for each event.
		
Here's a guide to the different modes

### daily
In this mode, the bot will send the introductory message "Here's your summary of leather events starting tomorrow", followed by a post
for each event found. If the event has a value set for the $poster property, then we send it as a photo with a caption. If $poster is
null, then there's no poster, so we just post the description.

### monthly
This mode is intended to be run on the first of the month. Instead of listing any events, it just posts a message like 
"Welcome to September! This month there are 24 events in the #BLUFclub calendar. #gayleather Find them all here:" with a link to the
calendar page.

### weekly
We run this on a Tuesday, and it posts an intro saying "Here's what's in the BLUF calendar this week" followed by a post for each event.

### updates
Also intended to be run once a week (we do this on Sunday), it posts a message like "There are 6 new and updated events in the #BLUFclub calendar"
with a link to the updates page of the calendar site.

### new
This runs daily, and if there are any events that have been newly classified, it will post "These events have been added to the BLUF Calendar today"
followed by a post for each event.

### post
This is just a quick tool to post to the channel from the server's command line. Whatever you add to the command will be posted as text, eg

		php8.2 event_telegram_feed.php --mode=post Sorry for the delay in updates to the calendar this week

## Responding to messages from channel users
If you want to respond to messages from channel users, you'll need to set up a webhook - a script on your server that can be called from
the Telegram service, when someone starts a conversation with your bot. In our case, it's a very simple one that will just look for a match
in event cities (and their localised names, which we also store, so people can search for 'Wien' or 'Vienna', for instance), and if any are
found, it sends them back a summary message with the names and dates, plus a link to see more details on the calendar web site.

To set the bot up, you will need to install the [telegram.php](telegram.php) script somewhere on your server where it's accessible to the outside world, 
and make sure that URL is defined as WEBHOOK_URL in the KEYStelegram.php file. 

Then, from the command line on your server, run the [register_telegram_webhook.php](register_telegram_webhook.php) script to tell Telegram where you want to receive messages:

		php8.2 register_telegram_webhook.php
		
If all is well, you'll see a successful confirmation, and you can then try chatting to your bot, and typing the name of a city. Of course, if
you don't want to offer this functionality, you needn't register the webhook, or install the telegram.php script.

### Conclusion
I hope this has given you some inspiration; obviously, you'll have to amend some things to deal with how your database is set up, and what
information you store about events, but I think this will give you a decent foundation on which to build an event bot and link it to a Telegram
channel. Please remember, however, I'm limited in the amount of time I can give freely, and I can't promise to help you build a complete
integration with whatever your own systems are.
