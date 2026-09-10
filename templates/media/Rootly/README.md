# <img width="42" height="41" alt="image" src="https://github.com/user-attachments/assets/a5b08642-45f7-4304-a1b9-cbfab69b469a" /> Rootly webhook

## Overview

This guide describes how to integrate your Zabbix installation with Rootly using the Zabbix webhook feature.

## Requirements

Zabbix version: 7.0 and higher.

### Setup

1. In Rootly, get your Bearer Token and URL (with the parameters).
    - The Webhook URL is found in Rootly when adding a Webhook (**Alerts > Sources > + New Source >  Generic Webhook**).

  
2. Add them as macro variables in Zabbix (**Administration > Macros**).
    - Name them exactly as shown here and input their values from **Step 1**: `{$ROOTLY.URL}` and `{$ROOTLY_BEARER_TOKEN}`



3. Import the JSON Rootly Media Type



4. Attach this to a new user called "**Rootly**" and assign **User role permissions** with this media type

5. Send a test alert to **Rootly** from Zabbix to make sure its working (**Zabbix > Media Type > on the Rootly row, there will be an `Action` column with a "Test" button on it**)


### Bonus Feature for Rootly "2 way ack" back to Zabbix

If you would like your acks/resolves of alerts in Rootly that are received from the Zabbix Alert source to reflect in Zabbix itself (alert gets acknowledged/resolved in Zabbix)
you can set this up here: https://github.com/francisheroux/rootly2zabbix
