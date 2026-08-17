
# GLPI by HTTP

## Overview

To use this template, you need to have a GLPI instance set up and accessible via HTTP/HTTPS with the REST API v2 enabled.
Ensure that OAuth2 authentication is configured and a dedicated monitoring user has been created with appropriate read-only permissions.
Check the [`GLPI API documentation`](https://help.glpi-project.org/documentation/modules/configuration/general/api/restful-api-v2) for details.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- GLPI 11.0.6, 11.0.7

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

### GLPI configuration

1. Enable access to the GLPI API:
  - In the GLPI web interface, go to *Setup* > *General* > *API*.
  - Switch the toggle to activate *Enable API* and click the *Save* button.
2. Add an [OAuth client](https://help.glpi-project.org/documentation/modules/configuration/oauth-clients):
  - Go to *Setup* > *OAuth clients*.
  - Click the *Add* button on the top of the page.
  - Set the client name; enter *api* in the *Scopes* field and *Password* in *Grants*.
  - Click the *Add* button.
  - Open the settings of the created client, and then copy and save the client ID and client secret.
3. Create a new [user profile](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/profiles/profiles.html) with read-only permissions for the sections you intend to monitor:
  - Go to *Administration* > *Profiles* and click the *Add* button on the top of the page.
  - Specify the profile name and set the *Profile's Interface* option to *Standard Interface*, and then click the *Add* button.
  - Open the created profile and click the *Assets* tab. Set *View all* for each asset type you intend to monitor, and click the *Save* button.
  - Click the *Assistance* tab. Set permissions for each assistance type you intend to monitor: *See all tickets* in the *Tickets* section, *See all* in the *Changes* section, *See all* in the *Problems* section, and click the *Save* button.
  - Click the *Management* tab. Set *View all* / *Read* for each management item you intend to monitor, and click the *Save* button.
4. Create a new [user](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/users/users.html):
  - Go to *Administration* > *Users* and click the *Add* button on the top of the page.
  - Specify the user login and set the *Authorization* > *Profile* option to the profile you created in the previous step.
  - Set the password for the user.
  - Click the *Add* button.

### Zabbix configuration

1. In Zabbix, create a new host and assign this template to it (GLPI by HTTP).
2. Open the *Macros* section of the host you created and set the following user macro values according to the OAuth2 client configuration from step 2 and the monitoring user credentials from step 4 of the *GLPI configuration* section above:

    * `{$GLPI.API.URL}` - GLPI REST API v2 base URL, e.g. `https://glpi.example.com/api.php/v2.3`.

    * `{$GLPI.CLIENT.ID}` - OAuth2 client ID from the GLPI OAuth client configuration.

    * `{$GLPI.CLIENT.SECRET}` - OAuth2 client secret from the GLPI OAuth client configuration.

    * `{$GLPI.USER}` - username of the dedicated monitoring user created.

    * `{$GLPI.PASSWORD}` - password of the dedicated monitoring user created.

### Optional setup

**LLD resource filtering**

Every LLD rule has pre-configured filtering options to avoid discovering unwanted resource types.
Each discovery rule supports regex-based filters: Asset types via `{$GLPI.ASSET.TYPE.NOT_MATCHES}` and `{$GLPI.ASSET.TYPE.MATCHES}`,
and Management items via `{$GLPI.MANAGEMENT.TYPE.NOT_MATCHES}` and `{$GLPI.MANAGEMENT.TYPE.MATCHES}`.
The values of these filters are defined by user macros and can be adjusted per host to include or exclude specific resource types from discovery using regular expressions.

Additionally, when configuring Asset and Management discovery, two extra macros must be defined if the GLPI user account has restricted API permissions:
`{$GLPI.ASSET.API.RESTRICTIONS}` and `{$GLPI.MANAGEMENT.API.RESTRICTIONS}`.
These macros accept a regex pattern and are used to exclude Asset or Management types that are inaccessible to the configured user. If the user was granted limited access in the GLPI web interface,
any resource types outside of those permissions must be listed here - otherwise discovery will produce errors when attempting to query restricted endpoints.

Note: The Asset discovery rule collects data from both standard Assets and Custom Assets. All asset-related macros, including:
`{$GLPI.ASSET.TYPE.MATCHES}`, `{$GLPI.ASSET.TYPE.NOT_MATCHES}`, and `{$GLPI.ASSET.API.RESTRICTIONS}` - apply to both asset types and can be used to filter or restrict Custom Assets as well.

**Trigger context macros**

Trigger thresholds can be customized per assistance type using Zabbix context macros, allowing different threshold values for Ticket, Change, and Problem types. The following macros support context-based configuration:
  - `{$GLPI.WORKLOAD.WARN}`,
  - `{$GLPI.WORKLOAD.CRIT}`,
  - `{$GLPI.SOLVE_TIME.WARN}`,
  - `{$GLPI.PRIORITY.MAJOR.THRESHOLD}`,
  - `{$GLPI.PRIORITY.VERY_HIGH.THRESHOLD}`,
  - `{$GLPI.PRIORITY.HIGH.THRESHOLD}`.

Example: set different workload thresholds per assistance type:
  - `{$GLPI.WORKLOAD.WARN:Ticket}` = `50`,
  - `{$GLPI.WORKLOAD.WARN:Change}` = `10`,
  - `{$GLPI.WORKLOAD.WARN:Problem}` = `5`.

Context macros can be defined in the *Macros* section of the host and will override the default macro value for the specified assistance type only.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GLPI.HTTP.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$GLPI.API.URL}|<p>GLPI REST API v2 base URL, e.g., https://glpi.example.com/api.php/v2.3.</p>||
|{$GLPI.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$GLPI.CLIENT.ID}|<p>OAuth2 client ID used for GLPI API authentication.</p>||
|{$GLPI.CLIENT.SECRET}|<p>OAuth2 client secret used for GLPI API authentication.</p>||
|{$GLPI.USER}|<p>Username of the dedicated monitoring user in GLPI.</p>||
|{$GLPI.PASSWORD}|<p>Password of the dedicated monitoring user in GLPI.</p>||
|{$GLPI.ASSET.API.RESTRICTIONS}|<p>Regex filter for restricting asset API access. Use to specify asset types that are inaccessible to the user.</p>|`Unmanaged`|
|{$GLPI.ASSET.TYPE.MATCHES}|<p>Regex filter for including asset types in discovery. Use to include only specific asset types.</p>|`.*`|
|{$GLPI.ASSET.TYPE.NOT_MATCHES}|<p>Regex filter for excluding asset types from discovery. Use to exclude specific asset types from discovery.</p>|`CHANGE_IF_NEEDED`|
|{$GLPI.MANAGEMENT.API.RESTRICTIONS}|<p>Regex filter for restricting management API access. Use to specify management types that are inaccessible to the user.</p>|`SoftwareLicense`|
|{$GLPI.MANAGEMENT.TYPE.MATCHES}|<p>Regex filter for including management types in discovery. Use to include only specific management types.</p>|`.*`|
|{$GLPI.MANAGEMENT.TYPE.NOT_MATCHES}|<p>Regex filter for excluding management types from discovery. Use to exclude specific management types from discovery.</p>|`CHANGE_IF_NEEDED`|
|{$GLPI.WORKLOAD.WARN}|<p>Maximum number of open assistance items to trigger a workload warning. Use context macros to set different thresholds per assistance type.</p>|`100`|
|{$GLPI.WORKLOAD.CRIT}|<p>Maximum number of open assistance items to trigger a critical workload alert. Use context macros to set different thresholds per assistance type.</p>|`200`|
|{$GLPI.SOLVE_TIME.WARN}|<p>Maximum average solve time in minutes to trigger a warning alert. Use context macros to set different thresholds per assistance type.</p>|`20160`|
|{$GLPI.PRIORITY.MAJOR.THRESHOLD}|<p>Number of open Major priority items to trigger an alert. Use context macros to set different thresholds per assistance type.</p>|`5`|
|{$GLPI.PRIORITY.VERY_HIGH.THRESHOLD}|<p>Number of open Very high priority items to trigger an alert. Use context macros to set different thresholds per assistance type.</p>|`15`|
|{$GLPI.PRIORITY.HIGH.THRESHOLD}|<p>Number of open High priority items to trigger an alert. Use context macros to set different thresholds per assistance type.</p>|`30`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service: Get data|<p>Item for gathering GLPI service status data.</p>|Script|glpi.status.get|
|Service: Get errors|<p>List of errors from API requests for GLPI service metrics.</p>|Dependent item|glpi.status.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assets: Get data|<p>Item for gathering GLPI Asset and Custom Asset data.</p>|Script|glpi.assets.data.get|
|Assets: Get errors|<p>List of errors from API requests for GLPI Assets metrics.</p>|Dependent item|glpi.assets.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Get data|<p>Item for gathering GLPI Change data.</p>|Script|glpi.assistance.change.data.get|
|Assistance [Changes]: Get errors|<p>List of errors from GLPI Assistance Changes API requests.</p>|Dependent item|glpi.assistance.change.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Get data|<p>Item for gathering GLPI Problem data.</p>|Script|glpi.assistance.problem.data.get|
|Assistance [Problems]: Get errors|<p>List of errors from GLPI Assistance Problems API requests.</p>|Dependent item|glpi.assistance.problem.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Get data|<p>Item for gathering GLPI Ticket data.</p>|Script|glpi.assistance.ticket.data.get|
|Assistance [Tickets]: Get errors|<p>List of errors from GLPI Assistance Tickets API requests.</p>|Dependent item|glpi.assistance.ticket.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Management: Get data|<p>Item for gathering GLPI Management data.</p>|Script|glpi.management.data.get|
|Management: Get errors|<p>List of errors from API requests for GLPI Management metrics.</p>|Dependent item|glpi.management.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [glpi]: Status|<p>Current operational status of the core GLPI application service.</p>|Dependent item|glpi.service.glpi.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.glpi.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [db]: Status|<p>Current operational status of the GLPI database connection.</p>|Dependent item|glpi.service.db.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.db.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [cas]: Status|<p>Current operational status of the CAS (Central Authentication Service) integration.</p>|Dependent item|glpi.service.cas.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.cas.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [ldap]: Status|<p>Current operational status of the LDAP directory service connection used for user authentication.</p>|Dependent item|glpi.service.ldap.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.ldap.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [imap]: Status|<p>Current operational status of the IMAP mail service.</p>|Dependent item|glpi.service.imap.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.imap.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [mail collectors]: Status|<p>Current operational status of GLPI mail collectors.</p>|Dependent item|glpi.service.mail_collectors.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.mail_collectors.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [crontasks]: Status|<p>Current operational status of GLPI scheduled cron tasks.</p>|Dependent item|glpi.service.crontasks.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.crontasks.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [filesystem]: Status|<p>Current operational status of the GLPI filesystem.</p>|Dependent item|glpi.service.filesystem.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.filesystem.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [plugins]: Status|<p>Current operational status of installed and active GLPI plugins.</p>|Dependent item|glpi.service.plugins.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.plugins.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Opened this month|<p>Number of "Change" items opened during the current month.</p>|Dependent item|glpi.change.open.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Solved this month|<p>Number of "Change" items solved during the current month.</p>|Dependent item|glpi.change.solved.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Late this month|<p>Number of late "Change" items during the current month.</p>|Dependent item|glpi.change.late.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Closed this month|<p>Number of "Change" items closed during the current month.</p>|Dependent item|glpi.change.closed.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Average solve time this month|<p>Average time to resolve "Change" items during the current month, in seconds.</p>|Dependent item|glpi.change.solve.time.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Opened previous month|<p>Number of "Change" items opened during the previous month.</p>|Dependent item|glpi.change.open.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Changes]: Solved previous month|<p>Number of "Change" items solved during the previous month.</p>|Dependent item|glpi.change.solved.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Changes]: Late previous month|<p>Number of late "Change" items during the previous month.</p>|Dependent item|glpi.change.late.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Changes]: Closed previous month|<p>Number of "Change" items closed during the previous month.</p>|Dependent item|glpi.change.closed.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Changes]: Average solve time previous month|<p>Average time to resolve "Change" items during the previous month, in seconds.</p>|Dependent item|glpi.change.solve.time.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Changes]: Total|<p>Total number of "Change" items.</p>|Dependent item|glpi.assistance.change.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Open|<p>Number of "Change" items currently open (excluding applied, closed, refused and cancelled).</p>|Dependent item|glpi.assistance.change.open<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.open`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: New|<p>Number of "Change" items currently in the "New" status.</p>|Dependent item|glpi.assistance.change.new<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.new`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Pending|<p>Number of "Change" items currently on hold.</p>|Dependent item|glpi.assistance.change.pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.pending`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Applied|<p>Number of "Change" items with status Applied.</p>|Dependent item|glpi.assistance.change.applied<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.applied`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Closed|<p>Number of "Change" items closed.</p>|Dependent item|glpi.assistance.change.closed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.closed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Accepted|<p>Number of "Change" items with status Accepted.</p>|Dependent item|glpi.assistance.change.accepted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.accepted`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Review|<p>Number of "Change" items currently under review.</p>|Dependent item|glpi.assistance.change.review<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.review`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Evaluation|<p>Number of "Change" items currently in evaluation.</p>|Dependent item|glpi.assistance.change.evaluation<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.evaluation`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Approval|<p>Number of "Change" items waiting for approval.</p>|Dependent item|glpi.assistance.change.approval<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.approval`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Testing|<p>Number of "Change" items currently in testing.</p>|Dependent item|glpi.assistance.change.testing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.testing`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Qualification|<p>Number of "Change" items currently in qualification.</p>|Dependent item|glpi.assistance.change.qualification<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.qualification`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Refused|<p>Number of "Change" items that were refused.</p>|Dependent item|glpi.assistance.change.refused<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.refused`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes]: Cancelled|<p>Number of "Change" items that were cancelled.</p>|Dependent item|glpi.assistance.change.cancelled<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.cancelled`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority Major]: Open|<p>Number of open "Change" items with priority Major.</p>|Dependent item|glpi.change.priority.major<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.major`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority Very high]: Open|<p>Number of open "Change" items with priority Very high.</p>|Dependent item|glpi.change.priority.very_high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority High]: Open|<p>Number of open "Change" items with priority High.</p>|Dependent item|glpi.change.priority.high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority Medium]: Open|<p>Number of open "Change" items with priority Medium.</p>|Dependent item|glpi.change.priority.medium<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.medium`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority Low]: Open|<p>Number of open "Change" items with priority Low.</p>|Dependent item|glpi.change.priority.low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Changes][Priority Very low]: Open|<p>Number of open "Change" items with priority Very low.</p>|Dependent item|glpi.change.priority.very_low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Opened this month|<p>Number of "Problem" items opened during the current month.</p>|Dependent item|glpi.problem.open.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Solved this month|<p>Number of "Problem" items solved during the current month.</p>|Dependent item|glpi.problem.solved.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Late this month|<p>Number of late "Problem" items during the current month.</p>|Dependent item|glpi.problem.late.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Closed this month|<p>Number of "Problem" items closed during the current month.</p>|Dependent item|glpi.problem.closed.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Average solve time this month|<p>Average time to resolve "Problem" items during the current month, in seconds.</p>|Dependent item|glpi.problem.solve.time.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Opened previous month|<p>Number of "Problem" items opened during the previous month.</p>|Dependent item|glpi.problem.open.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Problems]: Solved previous month|<p>Number of "Problem" items solved during the previous month.</p>|Dependent item|glpi.problem.solved.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Problems]: Late previous month|<p>Number of late "Problem" items during the previous month.</p>|Dependent item|glpi.problem.late.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Problems]: Closed previous month|<p>Number of "Problem" items closed during the previous month.</p>|Dependent item|glpi.problem.closed.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Problems]: Average solve time previous month|<p>Average time to resolve "Problem" items during the previous month, in seconds.</p>|Dependent item|glpi.problem.solve.time.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Problems]: Total|<p>Total number of "Problem" items.</p>|Dependent item|glpi.assistance.problem.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Open|<p>Number of "Problem" items currently open (excluding solved and closed).</p>|Dependent item|glpi.assistance.problem.open<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.open`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: New|<p>Number of "Problem" items currently in the "New" status.</p>|Dependent item|glpi.assistance.problem.new<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.new`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Assigned|<p>Number of "Problem" items currently assigned to a technician.</p>|Dependent item|glpi.assistance.problem.processing_assigned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.processing_assigned`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Planned|<p>Number of "Problem" items currently in "Planned" status.</p>|Dependent item|glpi.assistance.problem.processing_planned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.processing_planned`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Pending|<p>Number of "Problem" items currently on hold.</p>|Dependent item|glpi.assistance.problem.pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.pending`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Solved|<p>Number of "Problem" items marked as solved.</p>|Dependent item|glpi.assistance.problem.solved<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.solved`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Closed|<p>Number of "Problem" items closed.</p>|Dependent item|glpi.assistance.problem.closed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.closed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Accepted|<p>Number of "Problem" items with status Accepted.</p>|Dependent item|glpi.assistance.problem.accepted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.accepted`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems]: Under observation|<p>Number of "Problem" items currently under observation.</p>|Dependent item|glpi.assistance.problem.under_observation<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.under_observation`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority Major]: Open|<p>Number of open "Problem" items with priority Major.</p>|Dependent item|glpi.problem.priority.major<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.major`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority Very high]: Open|<p>Number of open "Problem" items with priority Very high.</p>|Dependent item|glpi.problem.priority.very_high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority High]: Open|<p>Number of open "Problem" items with priority High.</p>|Dependent item|glpi.problem.priority.high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority Medium]: Open|<p>Number of open "Problem" items with priority Medium.</p>|Dependent item|glpi.problem.priority.medium<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.medium`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority Low]: Open|<p>Number of open "Problem" items with priority Low.</p>|Dependent item|glpi.problem.priority.low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Problems][Priority Very low]: Open|<p>Number of open "Problem" items with priority Very low.</p>|Dependent item|glpi.problem.priority.very_low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Opened this month|<p>Number of "Ticket" items opened during the current month.</p>|Dependent item|glpi.ticket.open.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Solved this month|<p>Number of "Ticket" items solved during the current month.</p>|Dependent item|glpi.ticket.solved.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Late this month|<p>Number of late "Ticket" items during the current month.</p>|Dependent item|glpi.ticket.late.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Closed this month|<p>Number of "Ticket" items closed during the current month.</p>|Dependent item|glpi.ticket.closed.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Average solve time this month|<p>Average time to resolve "Ticket" items during the current month, in seconds.</p>|Dependent item|glpi.ticket.solve.time.month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Opened previous month|<p>Number of "Ticket" items opened during the previous month.</p>|Dependent item|glpi.ticket.open.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_open[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Tickets]: Solved previous month|<p>Number of "Ticket" items solved during the previous month.</p>|Dependent item|glpi.ticket.solved.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_solved[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Tickets]: Late previous month|<p>Number of late "Ticket" items during the previous month.</p>|Dependent item|glpi.ticket.late.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_late[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Tickets]: Closed previous month|<p>Number of "Ticket" items closed during the previous month.</p>|Dependent item|glpi.ticket.closed.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.number_closed[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Tickets]: Average solve time previous month|<p>Average time to resolve "Ticket" items during the previous month, in seconds.</p>|Dependent item|glpi.ticket.solve.time.previous_month<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.global.time_solve_avg[-2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Assistance [Tickets]: Total|<p>Total number of "Ticket" items.</p>|Dependent item|glpi.assistance.ticket.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Open|<p>Number of "Ticket" items currently open (excluding solved and closed).</p>|Dependent item|glpi.assistance.ticket.open<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.open`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: New|<p>Number of "Ticket" items currently in the "New" status.</p>|Dependent item|glpi.assistance.ticket.new<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.new`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Assigned|<p>Number of "Ticket" items currently assigned to a technician.</p>|Dependent item|glpi.assistance.ticket.processing_assigned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.processing_assigned`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Planned|<p>Number of "Ticket" items currently in "Planned" status.</p>|Dependent item|glpi.assistance.ticket.processing_planned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.processing_planned`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Pending|<p>Number of "Ticket" items currently on hold.</p>|Dependent item|glpi.assistance.ticket.pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.pending`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Solved|<p>Number of "Ticket" items marked as solved.</p>|Dependent item|glpi.assistance.ticket.solved<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.solved`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Closed|<p>Number of "Ticket" items closed.</p>|Dependent item|glpi.assistance.ticket.closed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.closed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets]: Approval|<p>Number of "Ticket" items waiting for approval.</p>|Dependent item|glpi.assistance.ticket.approval<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status.approval`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority Major]: Open|<p>Number of open "Ticket" items with priority Major.</p>|Dependent item|glpi.ticket.priority.major<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.major`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority Very high]: Open|<p>Number of open "Ticket" items with priority Very high.</p>|Dependent item|glpi.ticket.priority.very_high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority High]: Open|<p>Number of open "Ticket" items with priority High.</p>|Dependent item|glpi.ticket.priority.high<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.high`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority Medium]: Open|<p>Number of open "Ticket" items with priority Medium.</p>|Dependent item|glpi.ticket.priority.medium<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.medium`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority Low]: Open|<p>Number of open "Ticket" items with priority Low.</p>|Dependent item|glpi.ticket.priority.low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Assistance [Tickets][Priority Very low]: Open|<p>Number of open "Ticket" items with priority Very low.</p>|Dependent item|glpi.ticket.priority.very_low<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.priority.very_low`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GLPI: Service: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.status.errors))>0`|Average||
|GLPI: Assets: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.assets.errors))>0`|Average|**Depends on**:<br><ul><li>GLPI: Service: There are errors in requests to API</li></ul>|
|GLPI: Assistance [Changes]: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.assistance.change.errors))>0`|Average|**Depends on**:<br><ul><li>GLPI: Service: There are errors in requests to API</li></ul>|
|GLPI: Assistance [Problems]: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.assistance.problem.errors))>0`|Average|**Depends on**:<br><ul><li>GLPI: Service: There are errors in requests to API</li></ul>|
|GLPI: Assistance [Tickets]: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.assistance.ticket.errors))>0`|Average|**Depends on**:<br><ul><li>GLPI: Service: There are errors in requests to API</li></ul>|
|GLPI: Management: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/GLPI by HTTP/glpi.management.errors))>0`|Average|**Depends on**:<br><ul><li>GLPI: Service: There are errors in requests to API</li></ul>|
|GLPI: Service [glpi]: Is in a problem state|<p>Service GLPI is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.glpi.status)=3`|Average||
|GLPI: Service [glpi]: Is in a warning state|<p>Service GLPI is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.glpi.status)=2`|Warning||
|GLPI: Service [db]: Is in a problem state|<p>Service database is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.db.status)=3`|Average||
|GLPI: Service [db]: Is in a warning state|<p>Service database is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.db.status)=2`|Warning||
|GLPI: Service [cas]: Is in a problem state|<p>Service CAS is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.cas.status)=3`|Warning||
|GLPI: Service [cas]: Is in a warning state|<p>Service CAS is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.cas.status)=2`|Warning||
|GLPI: Service [ldap]: Is in a problem state|<p>Service LDAP is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.ldap.status)=3`|Warning||
|GLPI: Service [ldap]: Is in a warning state|<p>Service LDAP is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.ldap.status)=2`|Warning||
|GLPI: Service [imap]: Is in a problem state|<p>Service IMAP is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.imap.status)=3`|Warning||
|GLPI: Service [imap]: Is in a warning state|<p>Service IMAP is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.imap.status)=2`|Warning||
|GLPI: Service [mail collectors]: Is in a problem state|<p>The mail collectors service is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.mail_collectors.status)=3`|Warning||
|GLPI: Service [mail collectors]: Is in a warning state|<p>The mail collectors service is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.mail_collectors.status)=2`|Warning||
|GLPI: Service [crontasks]: Is in a problem state|<p>Service crontasks is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.crontasks.status)=3`|Warning||
|GLPI: Service [crontasks]: Is in a warning state|<p>Service crontasks is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.crontasks.status)=2`|Warning||
|GLPI: Service [filesystem]: Is in a problem state|<p>Service filesystem is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.filesystem.status)=3`|Average||
|GLPI: Service [filesystem]: Is in a warning state|<p>Service filesystem is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.filesystem.status)=2`|Warning||
|GLPI: Service [plugins]: Is in a problem state|<p>Service plugins is in a problem state.</p>|`last(/GLPI by HTTP/glpi.service.plugins.status)=3`|Warning||
|GLPI: Service [plugins]: Is in a warning state|<p>Service plugins is in a warning state.</p>|`last(/GLPI by HTTP/glpi.service.plugins.status)=2`|Warning||
|GLPI: Change: Average solve time is high|<p>Change average solve time this month exceeded the warning threshold.</p>|`min(/GLPI by HTTP/glpi.change.solve.time.month,1h)>{$GLPI.SOLVE_TIME.WARN:"Change"}*60`|Warning|**Manual close**: Yes|
|GLPI: Change: Workload is too high|<p>Change workload is too high.</p>|`min(/GLPI by HTTP/glpi.assistance.change.open,5m)>{$GLPI.WORKLOAD.CRIT:"Change"}`|Average||
|GLPI: Change: Workload is high|<p>Change workload is high.</p>|`min(/GLPI by HTTP/glpi.assistance.change.open,5m)>{$GLPI.WORKLOAD.WARN:"Change"}`|Warning|**Depends on**:<br><ul><li>GLPI: Change: Workload is too high</li></ul>|
|GLPI: Change: Too many open Major priority items|<p>Number of open Major priority "Change" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.change.priority.major,5m)>{$GLPI.PRIORITY.MAJOR.THRESHOLD:"Change"}`|High||
|GLPI: Change: Too many open Very high priority items|<p>Number of open Very high priority "Change" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.change.priority.very_high,5m)>{$GLPI.PRIORITY.VERY_HIGH.THRESHOLD:"Change"}`|Average||
|GLPI: Change: Too many open High priority items|<p>Number of open High priority "Change" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.change.priority.high,5m)>{$GLPI.PRIORITY.HIGH.THRESHOLD:"Change"}`|Warning||
|GLPI: Problem: Average solve time is high|<p>Problem average solve time this month exceeded the warning threshold.</p>|`min(/GLPI by HTTP/glpi.problem.solve.time.month,1h)>{$GLPI.SOLVE_TIME.WARN:"Problem"}*60`|Warning|**Manual close**: Yes|
|GLPI: Problem: Workload is too high|<p>Problem workload is too high.</p>|`min(/GLPI by HTTP/glpi.assistance.problem.open,5m)>{$GLPI.WORKLOAD.CRIT:"Problem"}`|Average||
|GLPI: Problem: Workload is high|<p>Problem workload is high.</p>|`min(/GLPI by HTTP/glpi.assistance.problem.open,5m)>{$GLPI.WORKLOAD.WARN:"Problem"}`|Warning|**Depends on**:<br><ul><li>GLPI: Problem: Workload is too high</li></ul>|
|GLPI: Problem: Too many open Major priority items|<p>Number of open Major priority "Problem" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.problem.priority.major,5m)>{$GLPI.PRIORITY.MAJOR.THRESHOLD:"Problem"}`|High||
|GLPI: Problem: Too many open Very high priority items|<p>Number of open Very high priority "Problem" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.problem.priority.very_high,5m)>{$GLPI.PRIORITY.VERY_HIGH.THRESHOLD:"Problem"}`|Average||
|GLPI: Problem: Too many open High priority items|<p>Number of open High priority "Problem" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.problem.priority.high,5m)>{$GLPI.PRIORITY.HIGH.THRESHOLD:"Problem"}`|Warning||
|GLPI: Ticket: Average solve time is high|<p>Ticket average solve time this month exceeded the warning threshold.</p>|`min(/GLPI by HTTP/glpi.ticket.solve.time.month,1h)>{$GLPI.SOLVE_TIME.WARN:"Ticket"}*60`|Warning|**Manual close**: Yes|
|GLPI: Ticket: Workload is too high|<p>Ticket workload is too high.</p>|`min(/GLPI by HTTP/glpi.assistance.ticket.open,5m)>{$GLPI.WORKLOAD.CRIT:"Ticket"}`|Average||
|GLPI: Ticket: Workload is high|<p>Ticket workload is high.</p>|`min(/GLPI by HTTP/glpi.assistance.ticket.open,5m)>{$GLPI.WORKLOAD.WARN:"Ticket"}`|Warning|**Depends on**:<br><ul><li>GLPI: Ticket: Workload is too high</li></ul>|
|GLPI: Ticket: Too many open Major priority items|<p>Number of open Major priority "Ticket" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.ticket.priority.major,5m)>{$GLPI.PRIORITY.MAJOR.THRESHOLD:"Ticket"}`|High||
|GLPI: Ticket: Too many open Very high priority items|<p>Number of open Very high priority "Ticket" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.ticket.priority.very_high,5m)>{$GLPI.PRIORITY.VERY_HIGH.THRESHOLD:"Ticket"}`|Average||
|GLPI: Ticket: Too many open High priority items|<p>Number of open High priority "Ticket" items exceeded the threshold.</p>|`min(/GLPI by HTTP/glpi.ticket.priority.high,5m)>{$GLPI.PRIORITY.HIGH.THRESHOLD:"Ticket"}`|Warning||

### LLD rule Assets type discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Assets type discovery|<p>Assets type discovery.</p>|Dependent item|assets.type.discovery|

### Item prototypes for Assets type discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Assets [{#ASSETS_TYPE}]: Total|<p>Total number of {#ASSETS_TYPE} assets.</p>|Dependent item|glpi.assets.total[{#ASSETS_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=="{#ASSETS_TYPE}")].count.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Assets type discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GLPI: Assets: Number of {#ASSETS_TYPE} items has changed|<p>Number of {#ASSETS_TYPE} items in Assets has changed. Acknowledge to close the problem manually.</p>|`last(/GLPI by HTTP/glpi.assets.total[{#ASSETS_TYPE}],#1)<>last(/GLPI by HTTP/glpi.assets.total[{#ASSETS_TYPE}],#2)`|Info|**Manual close**: Yes|

### LLD rule Management type discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Management type discovery|<p>Management type discovery.</p>|Dependent item|management.type.discovery|

### Item prototypes for Management type discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Management [{#MANAGEMENT_TYPE}]: Total|<p>Total number of {#MANAGEMENT_TYPE} assets.</p>|Dependent item|glpi.management.total[{#MANAGEMENT_TYPE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.type=="{#MANAGEMENT_TYPE}")].count.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Management type discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GLPI: Management: Number of {#MANAGEMENT_TYPE} items has changed|<p>Number of {#MANAGEMENT_TYPE} items in Management has changed. Acknowledge to close the problem manually.</p>|`last(/GLPI by HTTP/glpi.management.total[{#MANAGEMENT_TYPE}],#1)<>last(/GLPI by HTTP/glpi.management.total[{#MANAGEMENT_TYPE}],#2)`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

