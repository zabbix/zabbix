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

#include "checks_snmp.h"

#ifdef HAVE_SNMP

typedef struct zbx_snmp_index_s
{
	char	*oid;
	char	*value;
	int	index;
} zbx_snmp_index_t;
static zbx_snmp_index_t	*snmpidx = NULL;
static int		snmpidx_count = 0, snmpidx_alloc = 16;

/******************************************************************************
 *                                                                            *
 * Function: find nearest index for new record                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: index of new record                                          *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_snmpidx_nearestindex(const char *oid, const char *value)
{
	const char	*__function_name = "get_snmpidx_nearestindex";
	int		first_index, last_index, index = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)",
			__function_name,
			oid, value);

	if (snmpidx_count == 0)
		goto end;

	first_index = 0;
	last_index = snmpidx_count - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (0 == strcmp(snmpidx[index].oid, oid) && 0 == strcmp(snmpidx[index].value, value))
			break;
		else if (last_index == first_index)
		{
			if (0 > strcmp(snmpidx[index].oid, oid) ||
					(0 == strcmp(snmpidx[index].oid, oid) && 0 > strcmp(snmpidx[index].value, value)))
				index++;
			break;
		}
		else if (0 > strcmp(snmpidx[index].oid, oid) ||
				(0 == strcmp(snmpidx[index].oid, oid) && 0 > strcmp(snmpidx[index].value, value)))
			first_index = index + 1;
		else
			last_index = index;
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d",
			__function_name,
			index);

	return index;
}

/******************************************************************************
 * Function: cache_put_snmp_index                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                * 
 *                                                                            *
 * Return value: -                                                            *
 ******************************************************************************/
static int cache_get_snmp_index(const char *oid, const char *value, int *index)
{
	const char	*__function_name = "cache_get_snmp_index";
	int		i, res = FAIL;

	assert(index);

	*index = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)",
			__function_name,
			oid, value);

	if (NULL == snmpidx)
		goto end;

	i = get_snmpidx_nearestindex(oid, value);
	if (i < snmpidx_count && 0 == strcmp(oid, snmpidx[i].oid) && 0 == strcmp(value, snmpidx[i].value))
	{
		*index = snmpidx[i].index;
		res = SUCCEED;
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(index:%d):%s",
			__function_name,
			*index, zbx_result_string(res));

	return res;
}

/******************************************************************************
 * Function: cache_put_snmp_index                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                * 
 *                                                                            *
 * Return value: -                                                            *
 ******************************************************************************/
static void cache_put_snmp_index(const char *oid, const char *value, int index)
{
	const char	*__function_name = "cache_put_snmp_index";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s,index:%d)",
			__function_name,
			oid, value, index);

	if (NULL == snmpidx)
		snmpidx = zbx_malloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));

	i = get_snmpidx_nearestindex(oid, value);
	if (i < snmpidx_count && 0 == strcmp(oid, snmpidx[i].oid) && 0 == strcmp(value, snmpidx[i].value))
	{
		snmpidx[i].index = index;
		goto end;
	}

	if (snmpidx_count == snmpidx_alloc)
	{
		snmpidx_alloc += 16;
		snmpidx = zbx_realloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));
	}

	memmove(&snmpidx[i + 1], &snmpidx[i], sizeof(zbx_snmp_index_t) * (snmpidx_count - i));

	snmpidx[i].oid = strdup(oid);
	snmpidx[i].value = strdup(value);
	snmpidx[i].index = index;
	snmpidx_count++;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()",
			__function_name);
}

/******************************************************************************
 * Function: cache_del_snmp_index                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                * 
 *                                                                            *
 * Return value: -                                                            *
 ******************************************************************************/
static void cache_del_snmp_index(const char *oid, const char *value)
{
	const char	*__function_name = "cache_del_snmp_index";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)",
			__function_name,
			oid, value);

	if (NULL == snmpidx)
		goto end;

	i = get_snmpidx_nearestindex(oid, value);
	if (i < snmpidx_count && 0 == strcmp(oid, snmpidx[i].oid) && 0 == strcmp(value, snmpidx[i].value))
	{
		memmove(&snmpidx[i], &snmpidx[i + 1], sizeof(zbx_snmp_index_t) * (snmpidx_count - i - 1));
		snmpidx_count--;
	}

	if (snmpidx_count == snmpidx_alloc - 16)
	{
		snmpidx_alloc -= 16;
		snmpidx = zbx_realloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()",
			__function_name);
}

/* Function: snmp_get_index                                                   *
 *                                                                            *
 * Purpose: find index of OID with given value                                *
 *                                                                            *
 * Parameters: DB_ITEM *item - configuration of zabbix item                   *
 *             char *OID     - OID of table with values of interest           *
 *             char *value   - value to look for                              *
 *             int  *idx     - result to be placed here                       *
 *                                                                            *
 * Return value:  NOTSUPPORTED - OID does not exist, any other critical error *
 *                NETWORK_ERROR - recoverable network error                   *
 *                SUCCEED - success, variable 'idx' contains index having     *
 *                          value 'value'                                     */
static int snmp_get_index(DB_ITEM * item, char *OID, char *value, int *idx, char *err, int bulk)
{
	const char *__function_name = "snmp_get_index";
	struct snmp_session session, *ss;
	struct snmp_pdu *pdu;
	struct snmp_pdu *response;

	oid anOID[MAX_OID_LEN];
	oid rootOID[MAX_OID_LEN];
	size_t anOID_len = MAX_OID_LEN;
	size_t rootOID_len = MAX_OID_LEN;

	char temp[MAX_STRING_LEN];
	char strval[MAX_STRING_LEN];
	struct variable_list *vars;

	int len;
	int status;
	int running;

	int ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)",
			__function_name,
			OID, value);

	*idx = 0;
	*err = '\0';

	assert(item->type == ITEM_TYPE_SNMPv1 || item->type == ITEM_TYPE_SNMPv2c || item->type == ITEM_TYPE_SNMPv3);

	snmp_sess_init (&session);

	if (item->type == ITEM_TYPE_SNMPv1)
		session.version = SNMP_VERSION_1;
	else if(item->type == ITEM_TYPE_SNMPv2c)
		session.version = SNMP_VERSION_2c;
	else if(item->type == ITEM_TYPE_SNMPv3)
		session.version = SNMP_VERSION_3;
	else
		/* this should never happen */;

	if (item->useip == 1)
	{
		zbx_snprintf (temp, sizeof (temp), "%s:%d", item->host_ip,
					  item->snmp_port);
		session.peername = temp;
		session.remote_port = item->snmp_port;
	}
	else
	{
		zbx_snprintf (temp, sizeof (temp), "%s:%d",
					  item->host_dns, item->snmp_port);
		session.peername = temp;
		session.remote_port = item->snmp_port;
	}

	if (session.version == SNMP_VERSION_1 || item->type == ITEM_TYPE_SNMPv2c)
	{
		session.community = (u_char *) item->snmp_community;
		session.community_len = strlen ((void *) session.community);
		zabbix_log (LOG_LEVEL_DEBUG, "SNMP [%s@%s:%d]",
					session.community, session.peername, session.remote_port);
	}
	else if (session.version == SNMP_VERSION_3)
	{
		/* set the SNMPv3 user name */
		session.securityName = item->snmpv3_securityname;
		session.securityNameLen = strlen (session.securityName);

		/* set the security level to authenticated, but not encrypted */

		if (item->snmpv3_securitylevel ==
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_NOAUTH;
		}
		else if (item->snmpv3_securitylevel ==
				 ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHNOPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (generate_Ku (session.securityAuthProto,
							 session.securityAuthProtoLen,
							 (u_char *) item->snmpv3_authpassphrase,
							 strlen (item->snmpv3_authpassphrase),
							 session.securityAuthKey,
							 &session.securityAuthKeyLen) != SNMPERR_SUCCESS)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from authentication pass phrase");
				return NOTSUPPORTED;
			}
		}
		else if (item->snmpv3_securitylevel ==
				 ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (generate_Ku (session.securityAuthProto,
							 session.securityAuthProtoLen,
							 (u_char *) item->snmpv3_authpassphrase,
							 strlen (item->snmpv3_authpassphrase),
							 session.securityAuthKey,
							 &session.securityAuthKeyLen) != SNMPERR_SUCCESS)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from authentication pass phrase");
				return NOTSUPPORTED;
			}

			/* set the private method to DES */
			session.securityPrivProto = usmDESPrivProtocol;
			session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
			session.securityPrivKeyLen = USM_PRIV_KU_LEN;

			if (generate_Ku (session.securityAuthProto,
							 session.securityAuthProtoLen,
							 (u_char *) item->snmpv3_privpassphrase,
							 strlen (item->snmpv3_privpassphrase),
							 session.securityPrivKey,
							 &session.securityPrivKeyLen) != SNMPERR_SUCCESS)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from priv pass phrase");
				return NOTSUPPORTED;
			}
		}
		zabbix_log (LOG_LEVEL_DEBUG, "SNMPv3 [%s@%s:%d]",
					session.securityName,
					session.peername, session.remote_port);
	}

#ifdef HAVE_SNMP_SESSION_LOCALNAME
	if (NULL != CONFIG_SOURCE_IP)
		session.localname = CONFIG_SOURCE_IP;
#endif

	SOCK_STARTUP;
	ss = snmp_open (&session);

	if (ss == NULL)
	{
		SOCK_CLEANUP;

		zbx_snprintf(err, MAX_STRING_LEN, "Error doing snmp_open()");
		return NOTSUPPORTED;
	}

	/* create OID from string */
	snmp_parse_oid (OID, rootOID, &rootOID_len);

	/* copy rootOID to anOID */
	memcpy (anOID, rootOID, rootOID_len * sizeof (oid));
	anOID_len = rootOID_len;

	running = 1;
	while (running)
	{
		zabbix_log (LOG_LEVEL_DEBUG, "%s: snmp_pdu_create()",
					__function_name);
		/* prepare PDU */
		pdu = snmp_pdu_create(bulk ? SNMP_MSG_GETNEXT : SNMP_MSG_GET);	/* create empty PDU */
		snmp_add_null_var (pdu, anOID, anOID_len);	/* add OID as variable to PDU */
		/* communicate with agent */
		status = snmp_synch_response (ss, pdu, &response);

		/* process response */
		if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
		{
			for (vars = response->variables; vars && running; vars = vars->next_variable)
			{
				memcpy(strval, vars->val.string, vars->val_len);
				strval[vars->val_len] = 0;	/* terminate */

				len = snprint_objid(temp, sizeof(temp), vars->name, vars->name_length);
				zabbix_log(LOG_LEVEL_DEBUG, "VAR: %s = %s (type=%d)(length = %d)",
						temp, strval, vars->type, vars->val_len);

				/* verify if we are in the same subtree */
				if (vars->name_length < rootOID_len ||
						0 != memcmp(rootOID, vars->name, rootOID_len * sizeof(oid)))
				{
					/* not part of this subtree */
					running = 0;
					zbx_snprintf(err, MAX_STRING_LEN, "NOT FOUND: %s[%s]", OID, value);
					ret = NOTSUPPORTED;
				}
				else
				{
					/* verify if OIDs are increasing */
					if (vars->type != SNMP_ENDOFMIBVIEW && vars->type != SNMP_NOSUCHOBJECT &&
							vars->type != SNMP_NOSUCHINSTANCE)
					{
						/* not an exception value */
						if (snmp_oid_compare(anOID, anOID_len, vars->name, vars->name_length) >= 0)
						{
							zbx_snprintf(err, MAX_STRING_LEN, "OID not increasing.");
							ret = NOTSUPPORTED;
							running = 0;
						}

						/*__compare with key value__ */
						if (0 == strcmp(value, strval))
						{
							*idx = vars->name[vars->name_length - 1];
							zabbix_log(LOG_LEVEL_DEBUG, "FOUND: Index is %d", *idx);
							ret = SUCCEED;
							running = 0;
						}

						/* go to next variable */
						memmove((char *)anOID, (char *)vars->name, vars->name_length * sizeof(oid));
						anOID_len = vars->name_length;
					}
					else
					{
						/* an exception value, so stop */
						zabbix_log(LOG_LEVEL_DEBUG, "%s: Exception value found", __function_name);
						running = 0;
						ret = NOTSUPPORTED;
					}
				}			/*same subtree */
			}				/*for */
		}
		else
		{
			if (status == STAT_SUCCESS)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "SNMP error [%s]",
						snmp_errstring(response->errstat));
				running = 0;
				ret = NOTSUPPORTED;
			}
			else if(status == STAT_TIMEOUT)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Timeout while connecting to [%s]",
						session.peername);
				running = 0;
				ret = NETWORK_ERROR;
			}
			else
			{
				zbx_snprintf(err, MAX_STRING_LEN, "SNMP error [%d]",
						status);
				running = 0;
				ret = NOTSUPPORTED;
			}
		}

		if (response)
		{
			snmp_free_pdu(response);
		}
	}						/* while(running) */

	snmp_close(ss);

	SOCK_CLEANUP;

	zabbix_log(LOG_LEVEL_DEBUG, "%s: end", __function_name);

	return ret;
}


int	get_snmp(DB_ITEM *item, char *snmp_oid, AGENT_RESULT *value)
{
	const char *__function_name = "get_snmp";

#define NEW_APPROACH

	struct snmp_session session, *ss;
	struct snmp_pdu *pdu;
	struct snmp_pdu *response;

	char temp[MAX_STRING_LEN];

	oid anOID[MAX_OID_LEN];
	size_t anOID_len = MAX_OID_LEN;

	struct variable_list *vars;
	int status;

	int ret = SUCCEED;

	char *addr, buf[MAX_STRING_LEN], error[MAX_STRING_LEN], *pbuf = buf;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s)",
			__function_name,
			snmp_oid);

	init_result(value);

	assert(item->type == ITEM_TYPE_SNMPv1 || item->type == ITEM_TYPE_SNMPv2c || item->type == ITEM_TYPE_SNMPv3);

	snmp_sess_init( &session );

	if (item->type == ITEM_TYPE_SNMPv1)
		session.version = SNMP_VERSION_1;
	else if(item->type == ITEM_TYPE_SNMPv2c)
		session.version = SNMP_VERSION_2c;
	else if(item->type == ITEM_TYPE_SNMPv3)
		session.version = SNMP_VERSION_3;
	else
		/* this should never happen */;

	addr = (item->useip == 1) ? item->host_ip : item->host_dns;
#ifdef NEW_APPROACH
	zbx_snprintf(buf, sizeof(buf),"%s:%d", addr, item->snmp_port);
	session.peername = buf;
#else
	session.peername = addr;
#endif
	session.remote_port = item->snmp_port;

	if (session.version == SNMP_VERSION_1 || item->type == ITEM_TYPE_SNMPv2c)
	{
		session.community = (u_char *)item->snmp_community;
		session.community_len = strlen((void *)session.community);
		zabbix_log(LOG_LEVEL_DEBUG, "SNMP [%s@%s:%d]",
				session.community,
				session.peername,
				session.remote_port);
	}
	else if (session.version == SNMP_VERSION_3)
	{
		/* set the SNMPv3 user name */
		session.securityName = item->snmpv3_securityname;
		session.securityNameLen = strlen(session.securityName);

		/* set the security level to authenticated, but not encrypted */
		if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_NOAUTH;
		}
		else if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHNOPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *) item->snmpv3_authpassphrase, strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen) != SNMPERR_SUCCESS)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Error generating Ku from authentication pass phrase"));
				return NOTSUPPORTED;
			}
		}
		else if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *) item->snmpv3_authpassphrase, strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen) != SNMPERR_SUCCESS)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Error generating Ku from authentication pass phrase"));
				return NOTSUPPORTED;
			}
			
			/* set the private method to DES */
			session.securityPrivProto = usmDESPrivProtocol;
    			session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
			session.securityPrivKeyLen = USM_PRIV_KU_LEN;
			
			if (generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
			                (u_char *) item->snmpv3_privpassphrase, strlen(item->snmpv3_privpassphrase),
					session.securityPrivKey,
					&session.securityPrivKeyLen) != SNMPERR_SUCCESS) 
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Error generating Ku from priv pass phrase"));
				return NOTSUPPORTED;
			}
		}
		zabbix_log(LOG_LEVEL_DEBUG, "SNMPv3 [%s@%s:%d]",
				session.securityName,
				session.peername,
				session.remote_port);
	}

#ifdef HAVE_SNMP_SESSION_LOCALNAME
	if (NULL != CONFIG_SOURCE_IP)
		session.localname = CONFIG_SOURCE_IP;
#endif

	SOCK_STARTUP;
	ss = snmp_open(&session);

	if (ss == NULL)
	{
		SOCK_CLEANUP;

		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Error doing snmp_open()"));
		return NOTSUPPORTED;
	}

	pdu = snmp_pdu_create(SNMP_MSG_GET);
/* Changed to snmp_parse_oid */
/* read_objid(item->snmp_oid, anOID, &anOID_len);*/
	snmp_parse_oid(snmp_oid, anOID, &anOID_len);

#if OTHER_METHODS
	get_node("sysDescr.0", anOID, &anOID_len);
	read_objid(".1.3.6.1.2.1.1.1.0", anOID, &anOID_len);
	read_objid("system.sysDescr.0", anOID, &anOID_len);
#endif

	snmp_add_null_var(pdu, anOID, anOID_len);
  
	status = snmp_synch_response(ss, pdu, &response);
	zabbix_log( LOG_LEVEL_DEBUG, "Status send [%d]", status);

	if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
	{
		for (vars = response->variables; vars; vars = vars->next_variable)
		{
			memset(temp, '\0', sizeof(temp));
			snprint_value(temp, sizeof(temp) - 1, vars->name, vars->name_length, vars);
			zabbix_log(LOG_LEVEL_DEBUG, "AV loop OID [%s] Type [%d] '%s'",
					snmp_oid, vars->type, temp);

			if (vars->type == ASN_UINTEGER || vars->type == ASN_COUNTER ||
#ifdef OPAQUE_SPECIAL_TYPES
					vars->type == ASN_UNSIGNED64 ||
#endif
					vars->type == ASN_TIMETICKS || vars->type == ASN_GAUGE)
			{
				zbx_snprintf(buf, sizeof(buf), ZBX_FS_UI64, (unsigned long)*vars->val.integer);
			}
			else if (vars->type == ASN_COUNTER64)
			{
				/* Incorrect code for 32 bit platforms */
/*				SET_UI64_RESULT(value, ((vars->val.counter64->high)<<32)+(vars->val.counter64->low));*/
				zbx_snprintf(buf, sizeof(buf), ZBX_FS_UI64, (((zbx_uint64_t)vars->val.counter64->high) << 32) +
						(zbx_uint64_t)vars->val.counter64->low);
			}
			else if (vars->type == ASN_INTEGER ||
#ifdef OPAQUE_SPECIAL_TYPES
					vars->type == ASN_INTEGER64
#endif
					)
			{
				/* Negative integer values are converted to double */
				if (*vars->val.integer < 0)
					zbx_snprintf(buf, sizeof(buf), ZBX_FS_DBL, (double)*vars->val.integer);
				else
					zbx_snprintf(buf, sizeof(buf), ZBX_FS_UI64, (unsigned long)*vars->val.integer);
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (vars->type == ASN_FLOAT)
			{
				zbx_snprintf(buf, sizeof(buf), ZBX_FS_DBL, *vars->val.floatVal);
			}
			else if (vars->type == ASN_DOUBLE)
			{
				zbx_snprintf(buf, sizeof(buf), ZBX_FS_DBL, *vars->val.doubleVal);
			}
#endif
			else if (vars->type == ASN_OCTET_STR)
			{
				if (0 == strncmp(buf, "STRING: ", 8))
					pbuf += 8;
				else if (0 == strncmp(buf, "Hex-STRING: ", 12))
					pbuf += 12;
			}
			else if (vars->type == ASN_IPADDRESS)
			{
				zbx_snprintf(buf, sizeof(buf), "%d.%d.%d.%d",
						vars->val.string[0],
						vars->val.string[1],
						vars->val.string[2],
						vars->val.string[3]);
			}
			else
			{
				zbx_snprintf(error, sizeof(error), "OID [%s] value has unknow type [%X]",
						snmp_oid,
						vars->type);
				SET_MSG_RESULT(value, strdup(error));
				ret = NOTSUPPORTED;
			}

			if (SUCCEED == ret)
				break;
		}
	}
	else
	{
		if (status == STAT_SUCCESS)
		{
			zbx_snprintf(error, sizeof(error), "SNMP error [%s]",
					snmp_errstring(response->errstat));
			SET_MSG_RESULT(value, strdup(error));
			ret = NOTSUPPORTED;
		}
		else if(status == STAT_TIMEOUT)
		{
			zbx_snprintf(error, sizeof(error), "Timeout while connecting to [%s]",
					session.peername);
			SET_MSG_RESULT(value, strdup(error));
			ret = NETWORK_ERROR;
		}
		else
		{
			zbx_snprintf(error, sizeof(error), "SNMP error [%d]",
					status);
			SET_MSG_RESULT(value, strdup(error));
			ret = NOTSUPPORTED;
		}
	}

	if (response)
	{
		snmp_free_pdu(response);
	}
	snmp_close(ss);

	SOCK_CLEANUP;

	if (SUCCEED == ret && FAIL == set_result_type(value, item->value_type, item->data_type, pbuf))
	{
		zbx_remove_chars(pbuf, "\r\n");
		zbx_snprintf(error, sizeof(error), "Type of received value [%s] is not suitable for value type [%s]",
				pbuf,
				zbx_item_value_type_string(item->value_type));
		SET_MSG_RESULT(value, strdup(error));
		ret = NOTSUPPORTED;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: snmp_normalize                                                   *
 *                                                                            *
 * Purpose:  translate well known MIBs into numerics                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void snmp_normalize(char *buf, char *oid, int maxlen)
{
#define ZBX_MIB_NORM struct zbx_mib_norm_t

ZBX_MIB_NORM
{
	char	*mib;
	char	*replace;
};

static ZBX_MIB_NORM mibs[]=
{
	{"ifIndex",		"1.3.6.1.2.1.2.2.1.1"},
	{"ifDescr",		"1.3.6.1.2.1.2.2.1.2"},
	{"ifType",		"1.3.6.1.2.1.2.2.1.3"},
	{"ifMtu",		"1.3.6.1.2.1.2.2.1.4"},
	{"ifSpeed",		"1.3.6.1.2.1.2.2.1.5"},
	{"ifPhysAddress",	"1.3.6.1.2.1.2.2.1.6"},
	{"ifAdminStatus",	"1.3.6.1.2.1.2.2.1.7"},
	{"ifOperStatus",	"1.3.6.1.2.1.2.2.1.8"},
	{"ifInOctets",		"1.3.6.1.2.1.2.2.1.10"},
	{"ifInUcastPkts",	"1.3.6.1.2.1.2.2.1.11"},
	{"ifInNUcastPkts",	"1.3.6.1.2.1.2.2.1.12"},
	{"ifInDiscards",	"1.3.6.1.2.1.2.2.1.13"},
	{"ifInErrors",		"1.3.6.1.2.1.2.2.1.14"},
	{"ifInUnknownProtos",	"1.3.6.1.2.1.2.2.1.15"},
	{"ifOutOctets",		"1.3.6.1.2.1.2.2.1.16"},
	{"ifOutUcastPkts",	"1.3.6.1.2.1.2.2.1.17"},
	{"ifOutNUcastPkts",	"1.3.6.1.2.1.2.2.1.18"},
	{"ifOutDiscards",	"1.3.6.1.2.1.2.2.1.19"},
	{"ifOutErrors",		"1.3.6.1.2.1.2.2.1.20"},
	{"ifOutQLen",		"1.3.6.1.2.1.2.2.1.21"},
	{NULL}
};
	const char	*__function_name = "snmp_normalize";
	int		found = 0, i;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s)",
			__function_name,
			oid);

	for (i = 0; mibs[i].mib != NULL; i++)
	{
		sz = strlen(mibs[i].mib);
		if (0 == strncmp(mibs[i].mib, oid, sz))
		{
			found = 1;
			zbx_snprintf(buf, maxlen, "%s%s",
					mibs[i].replace,
					oid + sz);
			break;
		} 
	}
	if (0 == found)
		zbx_strlcpy(buf, oid, maxlen);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name,
			buf);
}

int	get_value_snmp(DB_ITEM *item, AGENT_RESULT *value)
{
	const char	*__function_name = "get_value_snmp";
	int	ret = SUCCEED;
	char	method[MAX_STRING_LEN];
	char	oid_normalized[MAX_STRING_LEN];
	char	oid_index[MAX_STRING_LEN];
	char	oid_full[MAX_STRING_LEN];
	char	index_value[MAX_STRING_LEN];
	char	err[MAX_STRING_LEN];
	int	idx;
	char	*pl;
	int	num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(key:%s,oid:%s)",
			__function_name,
			item->key,
			item->snmp_oid);

	num = num_key_param(item->snmp_oid);

	switch (num)
	{
	case 0:
		zabbix_log( LOG_LEVEL_DEBUG,"Standard processing");
		snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
		ret = get_snmp(item, oid_normalized, value);
		break;
	case 3:
		do {
			zabbix_log( LOG_LEVEL_DEBUG,"Special processing");
			oid_index[0]='\0';
			method[0]='\0';
			index_value[0]='\0';
			if (get_key_param(item->snmp_oid, 1, method, MAX_STRING_LEN) != 0
				|| get_key_param(item->snmp_oid, 2, oid_index, MAX_STRING_LEN) != 0
				|| get_key_param(item->snmp_oid, 3, index_value, MAX_STRING_LEN) != 0)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Cannot retrieve all three parameters from [%s]",
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}
			zabbix_log( LOG_LEVEL_DEBUG,"method:%s", method);
			zabbix_log( LOG_LEVEL_DEBUG,"oid_index:%s", oid_index);
			zabbix_log( LOG_LEVEL_DEBUG,"index_value:%s", index_value);
			if (0 != strcmp("index", method))
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Unsupported method [%s] in the OID [%s]",
						method,
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}
			snmp_normalize(oid_normalized, oid_index, sizeof(oid_normalized));

			if (SUCCEED == (ret = cache_get_snmp_index(oid_normalized, index_value, &idx)))
			{
				zbx_snprintf(oid_full, sizeof(oid_full), "%s.%d",
						oid_normalized,
						idx);
				ret = snmp_get_index(item, oid_full, index_value, &idx, err, 0);
			}

			if (SUCCEED != ret && SUCCEED != (ret = snmp_get_index(item, oid_normalized, index_value, &idx, err, 1)))
			{
				cache_del_snmp_index(oid_normalized, index_value);

				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Cannot find index [%s] of the OID [%s]: %s",
						oid_index,
						item->snmp_oid,
						err));
				ret = NOTSUPPORTED;
				break;
			}

			cache_put_snmp_index(oid_normalized, index_value, idx);

			zabbix_log( LOG_LEVEL_DEBUG,"Found index:%d", idx);
			pl=strchr(item->snmp_oid,'[');
			if (NULL == pl)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Cannot find left bracket in the OID [%s]",
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}
			pl[0]='\0';
			snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
			zbx_snprintf(oid_full, sizeof(oid_full), "%s.%d",
				oid_normalized,
				idx);
			zabbix_log( LOG_LEVEL_DEBUG,"Full OID:%s", oid_full);
			ret = get_snmp(item, oid_full, value);
			pl[0]='[';
		} while(0);
		break;
	default:
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID [%s] contains unsupported parameters",
				item->snmp_oid));
		ret = NOTSUPPORTED;
	}

	return ret;
}

#endif
