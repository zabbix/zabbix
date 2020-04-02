# Zabbix Telegram webhook intergration

![](images/telegram_logo.png?raw=true)

This guide describes how to send Zabbix notifications with Telegram messenger Bot API using the Zabbix webhook feature.

## Features
* personal and group notifications
* Markdown/HTML support

## Not implemented
* graphs sending - waiting for [ZBXNEXT-5611](https://support.zabbix.com/browse/ZBXNEXT-5611)
* emoji support

## How to configure

### Register new Telegram Bot: send "/newbot" to @BotFather and follow instructions

[![](images/1.png?raw=true)](images/1.png)

### Copy and paste obtained token into the "Token" field

[![](images/2.png?raw=true)](images/2.png)

### If you want to send personal notifications, you need to get chat id of the user you want to send messages to

#### Send "/getid" to "@myidbot" in Telegram messenger

[![](images/3.png?raw=true)](images/3.png)

#### Copy returned chat id and save it in the "Telegram Webhook" media for the user

[![](images/4.png?raw=true)](images/4.png)

#### Ask the user to send "/start" to your bot (Telegram bot won't send anything to the user without it)

[![](images/5.png?raw=true)](images/5.png)

#### Test the media type using the chat id you've got

[![](images/6.png?raw=true)](images/6.png)
[![](images/7.png?raw=true)](images/7.png)

Everything is Ok

#### Remember to send '/start', otherwise you will get the error

[![](images/8.png?raw=true)](images/8.png)

### If you want to send group notifications, you need to get group id of the group you want to send messages to

#### Add "@myidbot" to your group
#### Send "/getgroupid@myidbot" in your group
#### Copy returned group id save it in the "Telegram Webhook" media for the user you created for  group notifications
#### Send "/start@your_bot_name_here" in your group (Telegram bot won't send anything to the group without it)

[![](images/9.png?raw=true)](images/9.png)

#### Test the media type using the group id you've got

[![](images/10.png?raw=true)](images/10.png)

### Markdown / HTML formatting

Read the Telegram Bot API documentation to learn how to format your actions: [Markdown](https://core.telegram.org/bots/api#markdown-style), [HTML](https://core.telegram.org/bots/api#html-style) and [MarkdownV2](https://core.telegram.org/bots/api#html-style).

Set `ParseMode` parameter with needed option and configure your actions according to the Telegram's documentation.
Note: in that case your actions should be separated from other actions (SMS for example) or you're risking to get plain text alert with raw Markdown/HTML tags.

