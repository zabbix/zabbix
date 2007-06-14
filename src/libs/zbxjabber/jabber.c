/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <errno.h>

#include "common.h"
#include "log.h"
#include "zlog.h"

#include "jabber.h"

#include <iksemel.h>

#define JABBER_DISCONNECTED	0
#define JABBER_ERROR		1

#define JABBER_CONNECTING	2
#define JABBER_CONNECTED	3
#define JABBER_AUTHORIZED	4
#define JABBER_WORKING		5
#define JABBER_READY		10

typedef struct jabber_session {
	iksparser *prs;
	iksid *acc;
	char *pass;
	int features;
	iksfilter *my_filter;
	int opt_use_tls;
	int opt_use_sasl;
	int opt_log;
	int status;
} jabber_session_t, *jabber_session_p;

static jabber_session_p jsess = NULL;

static int on_result (jabber_session_p sess, ikspak *pak)
{
	zabbix_log (LOG_LEVEL_DEBUG, "JABBER: ready");
	sess->status = JABBER_READY;
	return IKS_FILTER_EAT;
}

static int disconnect_jabber()
{
	zabbix_log(LOG_LEVEL_INFORMATION, "JABBER: disconnecting");
	iks_disconnect(jsess->prs);
	if (jsess->my_filter) iks_filter_delete (jsess->my_filter);
	if (jsess->prs) iks_parser_delete (jsess->prs);
	jsess->status = JABBER_DISCONNECTED;
	return SUCCEED;
}

static int on_stream (jabber_session_p sess, int type, iks *node)
{
	iks *x = NULL;
	ikspak *pak = NULL;

	switch (type) {
		case IKS_NODE_START:
			if (sess->opt_use_tls && !iks_is_secure (sess->prs)) {
				iks_start_tls (sess->prs);
				break;
			}
			if (!sess->opt_use_sasl) {
				x = iks_make_auth (sess->acc, sess->pass, iks_find_attrib (node, "id"));
				iks_insert_attrib (x, "id", "auth");
				iks_send (sess->prs, x);
				iks_delete (x);
			}
			break;

		case IKS_NODE_NORMAL:
			if (strcmp ("stream:features", iks_name (node)) == 0) {
				sess->features = iks_stream_features (node);
				if (sess->opt_use_sasl) {
					if (sess->opt_use_tls && !iks_is_secure (sess->prs)) break;
					if (sess->status == JABBER_AUTHORIZED) {
						if (sess->features & IKS_STREAM_BIND) {
							x = iks_make_resource_bind (sess->acc);
							iks_send (sess->prs, x);
							iks_delete (x);
						}
						if (sess->features & IKS_STREAM_SESSION) {
							x = iks_make_session ();
							iks_insert_attrib (x, "id", "auth");
							iks_send (sess->prs, x);
							iks_delete (x);
						}
					} else {
						if (sess->features & IKS_STREAM_SASL_MD5)
							iks_start_sasl (sess->prs, IKS_SASL_DIGEST_MD5, sess->acc->user, sess->pass);
						else if (sess->features & IKS_STREAM_SASL_PLAIN)
							iks_start_sasl (sess->prs, IKS_SASL_PLAIN, sess->acc->user, sess->pass);
					}
				}
			} else if (strcmp ("failure", iks_name (node)) == 0) {
				zabbix_log (LOG_LEVEL_WARNING, "JABBER: sasl authentication failed");
				zabbix_syslog ("JABBER: sasl authentication failed");
			} else if (strcmp ("success", iks_name (node)) == 0) {
				zabbix_log (LOG_LEVEL_INFORMATION, "JABBER: authorized");
				sess->status = JABBER_AUTHORIZED;
				iks_send_header (sess->prs, sess->acc->server);
			} else {
				pak = iks_packet (node);
				iks_filter_packet (sess->my_filter, pak);
				//if (error) return IKS_HOOK;
			}
			break;

		case IKS_NODE_STOP:
			zabbix_log (LOG_LEVEL_WARNING, "JABBER: server disconnected");
			jsess->status = JABBER_DISCONNECTED;
			disconnect_jabber();
			//connect_jabber();
			break;
		case IKS_NODE_ERROR:
			zabbix_log (LOG_LEVEL_WARNING, "JABBER: stream error");
			jsess->status = JABBER_ERROR;
			disconnect_jabber();
			//connect_jabber();
			//Got a <stream:error> tag, details can be accessed from node.
	}

	if (node) iks_delete (node);
	return IKS_OK;
}

static int on_error (void *user_data, ikspak *pak)
{
	zabbix_log (LOG_LEVEL_WARNING, "JABBER: authorization failed");
	return IKS_FILTER_EAT;
}

static void on_log (jabber_session_p sess, const char *data, size_t size, int is_incoming)
{
	char msg[16] = "";
	if (iks_is_secure (sess->prs)) strcat(msg, "Sec");
	strcat (msg, is_incoming ? "RECV" : "SEND");
	zabbix_log(LOG_LEVEL_DEBUG, "%s [%s]\n", msg, data);
}

static void j_setup_filter (jabber_session_p sess)
{
	if (sess->my_filter) iks_filter_delete (sess->my_filter);
	sess->my_filter = iks_filter_new ();
	iks_filter_add_rule (sess->my_filter, (iksFilterHook *) on_result, sess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_RESULT,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);
	iks_filter_add_rule (sess->my_filter, on_error, sess,
		IKS_RULE_TYPE, IKS_PAK_IQ,
		IKS_RULE_SUBTYPE, IKS_TYPE_ERROR,
		IKS_RULE_ID, "auth",
		IKS_RULE_DONE);
}

static int connect_jabber(char *jabber_id, char *password, int use_sasl, char *error, int len)
{
	char *buf = NULL;
		
	zabbix_log(LOG_LEVEL_DEBUG, "JABBER: connecting as %s, pass %s", jabber_id, password);

	if(NULL == jsess) jsess = zbx_malloc(jsess, sizeof (jabber_session_t));

	memset (jsess, 0, sizeof (jabber_session_t));

	jsess->opt_use_sasl = use_sasl;
	jsess->prs = iks_stream_new (IKS_NS_CLIENT, jsess, (iksStreamHook *) on_stream);
	if (!jsess->prs) {
		zbx_snprintf(error, len, "Cannot create iksemel parser [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_WARNING, error);
		zabbix_syslog(error);
		return FAIL; 
	}

	if (jsess->opt_log) iks_set_log_hook (jsess->prs, (iksLogHook *) on_log);

	jsess->acc = iks_id_new (iks_parser_stack (jsess->prs), jabber_id);
	if (NULL == jsess->acc->resource) {
		/* user gave no resource name, use the default */
		buf = zbx_dsprintf (buf, "%s@%s/%s", jsess->acc->user, jsess->acc->server, "ZABBIX");
		jsess->acc = iks_id_new (iks_parser_stack (jsess->prs), buf);
		zbx_free (buf);
	}
	jsess->pass = strdup(password);

	j_setup_filter (jsess);

	switch (iks_connect_tcp (jsess->prs, jsess->acc->server, IKS_JABBER_PORT)) {
		case IKS_OK:
			break;
		case IKS_NET_NODNS:
			zbx_snprintf(error, len, "JABBER: hostname lookup failed");
			zabbix_log(LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
			disconnect_jabber();;
			return FAIL;
		case IKS_NET_NOCONN:
			zbx_snprintf(error, len, "JABBER: connection failed");
			zabbix_log(LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
			disconnect_jabber();;
			return FAIL;
		default:
			zbx_snprintf(error, len, "JABBER: io error");
			zabbix_log(LOG_LEVEL_WARNING, error);
			zabbix_syslog(error);
			disconnect_jabber();;
			return FAIL;
	}

	while (jsess->status != JABBER_READY) {
		switch (iks_recv (jsess->prs, 1)) {
			case IKS_OK:
				break;
			case IKS_HOOK:
				return SUCCEED;
			case IKS_NET_TLSFAIL:
				zbx_snprintf(error, len, "JABBER: tls handshake failed");
				zabbix_log(LOG_LEVEL_WARNING, error);
				zabbix_syslog(error);
				jsess->status = JABBER_ERROR;
				return FAIL; 
			default:
				zbx_snprintf(error, len, "JABBER: io error");
				zabbix_log(LOG_LEVEL_WARNING, error);
				zabbix_syslog(error);
				jsess->status = JABBER_ERROR;
				return FAIL; 
		}
	}

	return SUCCEED;
}


/*
 * Send email
 */ 
int	send_jabber(char *username, char *passwd, char *sendto, char *message, char *error, int max_error_len)
{
	iks *x = NULL;

	zabbix_log( LOG_LEVEL_DEBUG, "JABBER: sending message");

	if (NULL == jsess || jsess->status == JABBER_DISCONNECTED || jsess->status == JABBER_ERROR) {
		if (SUCCEED != connect_jabber(username, passwd, 1, error, max_error_len))
			return FAIL;
	}

	if ( NULL == (x = iks_new ("message")) ||
	     NULL == iks_insert_attrib (x, "to", sendto) ||
	     NULL == iks_insert_cdata (iks_insert (x, "body"), message, strlen(message)) ||
	     IKS_OK != iks_send (jsess->prs, x))
	{
		if (x) iks_delete(x);
		zbx_snprintf(error, max_error_len, "Cannot create or send message [%s] TEST", strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, error);
		zabbix_syslog(error);
		
		return FAIL;
	}
	
	iks_delete (x);

	zabbix_log( LOG_LEVEL_DEBUG, "JABBER: message sent");
	
	return SUCCEED;
}

