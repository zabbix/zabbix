# Telegram webhook 

![](images/telegram_logo.png?raw=true)

This guide describes how to send notifications from Zabbix 5.0 to the Telegram messenger using Telegram Bot API and Zabbix webhook feature.

## Supported features:
* Personal and group notifications
* Markdown/HTML support

## Not implemented:
* Graphs sending - waiting for [ZBXNEXT-5611](https://support.zabbix.com/browse/ZBXNEXT-5611)
* Emoji support

## Telegram setup

1\. Register a new Telegram Bot: send "/newbot" to @BotFather and follow the instructions. The token provided by @BotFather in the final step will be needed for configuring Zabbix webhook.
[![](images/1.png?raw=true)](images/1.png)

2\. If you want to send personal notifications, you need to obtain chat ID of the user the bot should send messages to.

Send "/getid" to "@myidbot" in Telegram messenger.

[![](images/3.png?raw=true)](images/3.png)

Ask the user to send "/start" to the bot, created in step 1. If you skip this step, Telegram bot won't be able to send messages to the user.

[![](images/5.png?raw=true)](images/5.png)

3\.If you want to send group notifications, you need to obtain group ID of the group the bot should send messages to. To do so:

Add "@myidbot" and "@your_bot_name_here" to your group.
Send "/getgroupid@myidbot" message in the group.
In the group chat send "/start@your_bot_name_here". If you skip this step, Telegram bot won't be able to send messages to the group.

[![](images/9.png?raw=true)](images/9.png)

## Zabbix setup

1\. In the "Administration > Media types" section, import the media_telegram.yaml.
2\. Configure the added media type: 
Copy and paste your Telegram bot token into the "telegramToken" field.

[![](images/2.png?raw=true)](images/2.png)

In the `ParseMode` parameter set required option according to the Telegram's documentation. 
Read the Telegram Bot API documentation to learn how to format action notification messages: [Markdown](https://core.telegram.org/bots/api#markdown-style) / [HTML](https://core.telegram.org/bots/api#html-style) / [MarkdownV2](https://core.telegram.org/bots/api#markdownv2-style).
Note: in this case, your Telegram-related actions should be separated from other notification actions (for example, SMS), otherwise you may get plain text alert with raw Markdown/HTML tags.

Test the media type using chat ID or group ID you've got.

[![](images/6.png?raw=true)](images/6.png)
[![](images/7.png?raw=true)](images/7.png)

If you have forgotten to send '/start' to the bot from Telegram, you will get the following error:

[![](images/8.png?raw=true)](images/8.png)

3\.To receive notifications in Telegram, you need to create a Zabbix user and add Media with the Telegram type.
In the "Send to" field enter Telegram user chat ID or group ID obtained during Telegram setup.

[![](images/4.png?raw=true)](images/4.png)

Make sure the user has access to all hosts for which you would like to receive Telegram notifications.

Great, you can now start receiving Zabbix notifications in Telegram!
