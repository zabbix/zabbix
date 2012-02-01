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
#include "comms.h"

#ifdef HAVE_SNMP

typedef struct
{
	char		*oid;
	char		*value;
	zbx_uint64_t	hostid;
	unsigned short	port;
	int		index;
}
zbx_snmp_index_t;

static zbx_snmp_index_t	*snmpidx = NULL;
static int		snmpidx_count = 0, snmpidx_alloc = 16;

/******************************************************************************
 *                                                                            *
 * Function: zbx_snmp_index_compare                                           *
 *                                                                            *
 * Purpose: compare index against snmpidx entry                               *
 *                                                                            *
 * Parameters: item  - configuration of zabbix item                           *
 *             oid   - index OID                                              *
 *             value - index value                                            *
 *             pos   - position in snmpidx                                    *
 *                                                                            *
 * Return value: -1, 0 or 1 if snmpidx entry is respectively less than,       *
 *               equal to or greater than index                               *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	zbx_snmp_index_compare(zbx_snmp_index_t *s1, zbx_snmp_index_t *s2)
{
	int	rc;

	if (s1->hostid < s2->hostid) return -1;
	if (s1->hostid > s2->hostid) return +1;
	if (s1->port < s2->port) return -1;
	if (s1->port > s2->port) return +1;
	if (0 != (rc = strcmp(s1->oid, s2->oid)))
		return rc;
	return strcmp(s1->value, s2->value);
}

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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_snmpidx_nearestindex(zbx_snmp_index_t *s)
{
	const char	*__function_name = "get_snmpidx_nearestindex";
	int		first_index, last_index, index = 0, cmp_res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " port:%hu oid:'%s' value:'%s'",
			__function_name, s->hostid, s->port, s->oid, s->value);

	if (snmpidx_count == 0)
		goto end;

	first_index = 0;
	last_index = snmpidx_count - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (0 == (cmp_res = zbx_snmp_index_compare(s, &snmpidx[index])))
			break;

		if (last_index == first_index)
		{
			if (0 < cmp_res)
				index++;
			break;
		}

		if (0 < cmp_res)
			first_index = index + 1;
		else
			last_index = index;
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, index);

	return index;
}

static int	cache_get_snmp_index(DC_ITEM *item, char *oid, char *value, int *index)
{
	const char		*__function_name = "cache_get_snmp_index";
	int			i, res = FAIL;
	zbx_snmp_index_t	s;

	assert(index);

	*index = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)", __function_name, oid, value);

	if (NULL == snmpidx)
		goto end;

	s.hostid = item->host.hostid;
	s.port = item->snmp_port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
	{
		*index = snmpidx[i].index;
		res = SUCCEED;
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(index:%d):%s", __function_name, *index, zbx_result_string(res));

	return res;
}

static void	cache_put_snmp_index(DC_ITEM *item, char *oid, char *value, int index)
{
	const char		*__function_name = "cache_put_snmp_index";
	int			i;
	zbx_snmp_index_t	s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s,index:%d)", __function_name, oid, value, index);

	if (NULL == snmpidx)
		snmpidx = zbx_malloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));

	s.hostid = item->host.hostid;
	s.port = item->snmp_port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
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

	snmpidx[i].hostid = item->host.hostid;
	snmpidx[i].port = item->snmp_port;
	snmpidx[i].oid = strdup(oid);
	snmpidx[i].value = strdup(value);
	snmpidx[i].index = index;
	snmpidx_count++;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	cache_del_snmp_index(DC_ITEM *item, char *oid, char *value)
{
	const char		*__function_name = "cache_del_snmp_index";
	int			i;
	zbx_snmp_index_t	s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s,value:%s)", __function_name, oid, value);

	if (NULL == snmpidx)
		goto end;

	s.hostid = item->host.hostid;
	s.port = item->snmp_port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
	{
		zbx_free(snmpidx[i].oid);
		zbx_free(snmpidx[i].value);
		memmove(&snmpidx[i], &snmpidx[i + 1], sizeof(zbx_snmp_index_t) * (snmpidx_count - i - 1));
		snmpidx_count--;
	}

	if (snmpidx_count == snmpidx_alloc - 16)
	{
		snmpidx_alloc -= 16;
		snmpidx = zbx_realloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static struct snmp_session	*snmp_open_session(DC_ITEM *item, char *err)
{
	const char		*__function_name = "snmp_open_session";
	struct snmp_session	session, *ss = NULL;
	char			addr[128], *conn;
#ifdef HAVE_IPV6
	int			family;
#endif	/* HAVE_IPV6 */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	snmp_sess_init(&session);

	switch (item->type)
	{
		case ITEM_TYPE_SNMPv1:
			session.version = SNMP_VERSION_1;
			break;
		case ITEM_TYPE_SNMPv2c:
			session.version = SNMP_VERSION_2c;
			break;
		case ITEM_TYPE_SNMPv3:
			session.version = SNMP_VERSION_3;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			break;
	}

	conn = (item->host.useip == 1 ? item->host.ip : item->host.dns);

#ifdef HAVE_IPV6
	if (SUCCEED != get_address_family(conn, &family, err, MAX_STRING_LEN))
		goto end;

	if (family == PF_INET)
		zbx_snprintf(addr, sizeof(addr), "%s:%d", conn, item->snmp_port);
	else
	{
		if (item->host.useip == 1)
			zbx_snprintf(addr, sizeof(addr), "udp6:[%s]:%d", conn, item->snmp_port);
		else
			zbx_snprintf(addr, sizeof(addr), "udp6:%s:%d", conn, item->snmp_port);
	}
#else
	zbx_snprintf(addr, sizeof(addr), "%s:%d", conn, item->snmp_port);
#endif	/* HAVE_IPV6 */
	session.peername = addr;
	session.remote_port = item->snmp_port;	/* remote_port is no longer used in latest versions of NET-SNMP */

	if (session.version == SNMP_VERSION_1 || session.version == SNMP_VERSION_2c)
	{
		session.community = (u_char *)item->snmp_community;
		session.community_len = strlen((void *)session.community);
		zabbix_log(LOG_LEVEL_DEBUG, "SNMP [%s@%s]", session.community, session.peername);
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

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_authpassphrase,
					strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen))
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from authentication pass phrase");
				goto end;
			}
		}
		else if (item->snmpv3_securitylevel == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV)
		{
			session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

			/* set the authentication method to MD5 */
			session.securityAuthProto = usmHMACMD5AuthProtocol;
			session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
			session.securityAuthKeyLen = USM_AUTH_KU_LEN;

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_authpassphrase,
					strlen(item->snmpv3_authpassphrase),
					session.securityAuthKey,
					&session.securityAuthKeyLen))
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from authentication pass phrase");
				goto end;
			}

			/* set the privacy method to DES */
			session.securityPrivProto = usmDESPrivProtocol;
			session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
			session.securityPrivKeyLen = USM_PRIV_KU_LEN;

			if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
					session.securityAuthProtoLen,
					(u_char *)item->snmpv3_privpassphrase,
					strlen(item->snmpv3_privpassphrase),
					session.securityPrivKey,
					&session.securityPrivKeyLen))
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Error generating Ku from privacy pass phrase");
				goto end;
			}
		}
		zabbix_log(LOG_LEVEL_DEBUG, "SNMPv3 [%s@%s]", session.securityName, session.peername);
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

		zbx_snprintf(err, MAX_STRING_LEN, "Error doing snmp_open()");
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ss;
}

static void	snmp_close_session(struct snmp_session *session)
{
	const char *__function_name = "snmp_close_session";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	snmp_close(session);
	SOCK_CLEANUP;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: snmp_get_index                                                   *
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
 *                          value 'value'                                     *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	snmp_get_index(struct snmp_session *ss, DC_ITEM *item, const char *OID, const char *value,
		int *idx, char *err, int bulk)
{
	const char		*__function_name = "snmp_get_index";
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN;
	char			temp[MAX_STRING_LEN], strval[MAX_STRING_LEN], *conn, snmp_oid[MAX_STRING_LEN];
	int			status, running, ret = NOTSUPPORTED;
	struct snmp_pdu		*pdu, *response;
	struct variable_list	*vars;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' value:'%s' bulk:%d", __function_name, OID, value, bulk);

	*idx = 0;
	*err = '\0';

	/* create OID from string */
	snmp_parse_oid(OID, rootOID, &rootOID_len);

	/* copy rootOID to anOID */
	memcpy(anOID, rootOID, rootOID_len * sizeof(oid));
	anOID_len = rootOID_len;

	running = 1;
	while (1 == running)
	{
		pdu = snmp_pdu_create(bulk ? SNMP_MSG_GETNEXT : SNMP_MSG_GET);	/* create empty PDU */
		snmp_add_null_var(pdu, anOID, anOID_len);			/* add OID as variable to PDU */

		/* communicate with agent */
		status = snmp_synch_response(ss, pdu, &response);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response():%d", __function_name, status);

		/* process response */
		if (STAT_SUCCESS == status && SNMP_ERR_NOERROR == response->errstat)
		{
			for (vars = response->variables; vars && running; vars = vars->next_variable)
			{
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
					if (SNMP_ENDOFMIBVIEW != vars->type && SNMP_NOSUCHOBJECT != vars->type &&
							SNMP_NOSUCHINSTANCE != vars->type)
					{
						/* not an exception value */
						if (0 <= snmp_oid_compare(anOID, anOID_len, vars->name, vars->name_length))
						{
							zbx_snprintf(err, MAX_STRING_LEN, "OID not increasing.");
							ret = NOTSUPPORTED;
							running = 0;
						}

						memset(temp, '\0', sizeof(temp));
						snprint_value(temp, sizeof(temp) - 1,
								vars->name, vars->name_length, vars);
						memset(snmp_oid, '\0', sizeof(snmp_oid));
						snprint_objid(snmp_oid, sizeof(snmp_oid) - 1,
								vars->name, vars->name_length);
						zabbix_log(LOG_LEVEL_DEBUG, "AV loop OID:'%s' value:'%s' type:0x%02X",
								snmp_oid, temp, vars->type);

						if (ASN_OCTET_STR == vars->type)
						{
							if (0 == strncmp(temp, "STRING: ", 8))
								strscpy(strval, temp + 8);
							else if (0 == strncmp(temp, "Hex-STRING: ", 12))
								strscpy(strval, temp + 12);
							else
								strscpy(strval, temp);
						}
#ifdef OPAQUE_SPECIAL_TYPES
						else if (ASN_UINTEGER == vars->type || ASN_COUNTER == vars->type ||
								ASN_TIMETICKS == vars->type ||
								ASN_GAUGE == vars->type ||
								ASN_UNSIGNED64 == vars->type)
#else
						else if (ASN_UINTEGER == vars->type || ASN_COUNTER == vars->type ||
								ASN_TIMETICKS == vars->type || ASN_GAUGE == vars->type)
#endif
						{
							zbx_snprintf(strval, sizeof(strval), "%u", *vars->val.integer);
						}
						else if (ASN_COUNTER64 == vars->type)
						{
							zbx_snprintf(strval, sizeof(strval), ZBX_FS_UI64,
									(((zbx_uint64_t)vars->val.counter64->high) << 32) +
									(zbx_uint64_t)vars->val.counter64->low);
						}
#ifdef OPAQUE_SPECIAL_TYPES
						else if (ASN_INTEGER == vars->type || ASN_INTEGER64 == vars->type)
#else
						else if (ASN_INTEGER == vars->type)
#endif
						{
							zbx_snprintf(strval, sizeof(strval), "%d", *vars->val.integer);
						}
						else if (ASN_IPADDRESS == vars->type)
						{
							zbx_snprintf(strval, sizeof(strval), "%d.%d.%d.%d",
									vars->val.string[0],
									vars->val.string[1],
									vars->val.string[2],
									vars->val.string[3]);
						}
						else
						{
							zabbix_log(LOG_LEVEL_DEBUG,
									"OID [%s] value has unknown type [0x%02X]",
									snmp_oid, vars->type);
						}

						if (0 == strcmp(value, strval))
						{
							*idx = vars->name[vars->name_length - 1];
							zabbix_log(LOG_LEVEL_DEBUG, "index found: %d", *idx);
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
						zabbix_log(LOG_LEVEL_DEBUG, "exception value found");
						running = 0;
						ret = NOTSUPPORTED;
					}
				}
			}
		}
		else
		{
			conn = (item->host.useip == 1 ? item->host.ip : item->host.dns);
			running = 0;

			if (STAT_SUCCESS == status)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "SNMP error [%s]", snmp_errstring(response->errstat));
				ret = NOTSUPPORTED;
			}
			else if (STAT_ERROR == status)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Could not connect to [%s:%d]",
						conn, item->snmp_port);
				ret = NETWORK_ERROR;
			}
			else if (STAT_TIMEOUT == status)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Timeout while connecting to [%s:%d]",
						conn, item->snmp_port);
				ret = NETWORK_ERROR;
			}
			else
			{
				zbx_snprintf(err, MAX_STRING_LEN, "SNMP error [%d]", status);
				ret = NOTSUPPORTED;
			}
		}

		if (response)
			snmp_free_pdu(response);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	get_snmp(struct snmp_session *ss, DC_ITEM *item, char *snmp_oid, AGENT_RESULT *value)
{
	const char		*__function_name = "get_snmp";
	struct snmp_pdu		*pdu, *response;
	char			temp[MAX_STRING_LEN], *ptemp, *conn;
	oid			anOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN;
	struct variable_list	*vars;
	int			status, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:%s", __function_name, snmp_oid);

	init_result(value);

	snmp_parse_oid(snmp_oid, anOID, &anOID_len);

	pdu = snmp_pdu_create(SNMP_MSG_GET);
	snmp_add_null_var(pdu, anOID, anOID_len);

	status = snmp_synch_response(ss, pdu, &response);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response():%d", __function_name, status);

	if (STAT_SUCCESS == status && SNMP_ERR_NOERROR == response->errstat)
	{
		for (vars = response->variables; vars; vars = vars->next_variable)
		{
			memset(temp, '\0', sizeof(temp));
			snprint_value(temp, sizeof(temp) - 1, vars->name, vars->name_length, vars);
			zabbix_log(LOG_LEVEL_DEBUG, "AV loop OID:'%s' value:'%s' type:0x%02X",
					snmp_oid, temp, vars->type);

			if (ASN_OCTET_STR == vars->type)
			{
				if (0 == strncmp(temp, "STRING: ", 8))
					ptemp = temp + 8;
				else if (0 == strncmp(temp, "Hex-STRING: ", 12))
					ptemp = temp + 12;
				else
					ptemp = temp;
				if (SUCCEED != set_result_type(value, item->value_type, item->data_type, ptemp))
					ret = NOTSUPPORTED;
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (ASN_UINTEGER == vars->type || ASN_COUNTER == vars->type ||
					ASN_TIMETICKS == vars->type || ASN_GAUGE == vars->type ||
					ASN_UNSIGNED64 == vars->type)
#else
			else if (ASN_UINTEGER == vars->type || ASN_COUNTER == vars->type ||
					ASN_TIMETICKS == vars->type || ASN_GAUGE == vars->type)
#endif
			{
				SET_UI64_RESULT(value, (unsigned long)*vars->val.integer);
			}
			else if (ASN_COUNTER64 == vars->type)
			{
				SET_UI64_RESULT(value, (((zbx_uint64_t)vars->val.counter64->high) << 32) +
						(zbx_uint64_t)vars->val.counter64->low);
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (ASN_INTEGER == vars->type || ASN_INTEGER64 == vars->type)
#else
			else if (ASN_INTEGER == vars->type)
#endif
			{
				/* Negative integer values are converted to double */
				if (*vars->val.integer < 0)
					SET_DBL_RESULT(value, (double)*vars->val.integer);
				else
					SET_UI64_RESULT(value, (zbx_uint64_t)*vars->val.integer);
			}
#ifdef OPAQUE_SPECIAL_TYPES
			else if (ASN_FLOAT == vars->type)
			{
				SET_DBL_RESULT(value, *vars->val.floatVal);
			}
			else if (ASN_DOUBLE == vars->type)
			{
				SET_DBL_RESULT(value, *vars->val.doubleVal);
			}
#endif
			else if (ASN_IPADDRESS == vars->type)
			{
				SET_STR_RESULT(value, zbx_dsprintf(NULL, "%d.%d.%d.%d",
						vars->val.string[0],
						vars->val.string[1],
						vars->val.string[2],
						vars->val.string[3]));
			}
			else
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID [%s] value has unknown type [0x%02X]",
						snmp_oid,
						vars->type));
				ret = NOTSUPPORTED;
			}

			if (SUCCEED == ret)
				break;
		}
	}
	else
	{
		conn = (item->host.useip == 1 ? item->host.ip : item->host.dns);

		if (STAT_SUCCESS == status)
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "SNMP error [%s]", snmp_errstring(response->errstat)));
			ret = NOTSUPPORTED;
		}
		else if (STAT_ERROR == status)
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Could not connect to [%s:%d]",
					conn, item->snmp_port));
			ret = NETWORK_ERROR;
		}
		else if (STAT_TIMEOUT == status)
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Timeout while connecting to [%s:%d]",
					conn, item->snmp_port));
			ret = NETWORK_ERROR;
		}
		else
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "SNMP error [%d]", status));
			ret = NOTSUPPORTED;
		}
	}

	if (response)
		snmp_free_pdu(response);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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
static void	snmp_normalize(char *buf, const char *oid, int maxlen)
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(oid:%s)", __function_name, oid);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, buf);
}

int	get_value_snmp(DC_ITEM *item, AGENT_RESULT *value)
{
	const char		*__function_name = "get_value_snmp";
	struct snmp_session	*ss;
	int	ret = SUCCEED;
	char	method[MAX_STRING_LEN];
	char	oid_normalized[MAX_STRING_LEN];
	char	oid_index[MAX_STRING_LEN];
	char	oid_full[MAX_STRING_LEN];
	char	index_value[MAX_STRING_LEN];
	char	err[MAX_STRING_LEN];
	int	idx;
	char	*pl;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' oid:'%s'",
			__function_name, item->key_orig, item->snmp_oid);

	assert(item->type == ITEM_TYPE_SNMPv1 || item->type == ITEM_TYPE_SNMPv2c ||
			item->type == ITEM_TYPE_SNMPv3);

	if (NULL == (ss = snmp_open_session(item, err)))
	{
		ret = NOTSUPPORTED;

		SET_MSG_RESULT(value, strdup(err));

		goto out;
	}

	switch (num_key_param(item->snmp_oid))
	{
	case 0:
		zabbix_log(LOG_LEVEL_DEBUG, "standard processing");
		snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
		ret = get_snmp(ss, item, oid_normalized, value);
		break;
	case 3:
		do
		{
			zabbix_log(LOG_LEVEL_DEBUG, "special processing");

			if (get_key_param(item->snmp_oid, 1, method, MAX_STRING_LEN) != 0
				|| get_key_param(item->snmp_oid, 2, oid_index, MAX_STRING_LEN) != 0
				|| get_key_param(item->snmp_oid, 3, index_value, MAX_STRING_LEN) != 0)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "cannot retrieve all three parameters from [%s]",
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "method:%s", method);
			zabbix_log(LOG_LEVEL_DEBUG, "oid_index:%s", oid_index);
			zabbix_log(LOG_LEVEL_DEBUG, "index_value:%s", index_value);

			if (0 != strcmp("index", method))
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "unsupported method [%s] in the OID [%s]",
						method,
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}

			snmp_normalize(oid_normalized, oid_index, sizeof(oid_normalized));

			if (SUCCEED == (ret = cache_get_snmp_index(item, oid_normalized, index_value, &idx)))
			{
				zbx_snprintf(oid_full, sizeof(oid_full), "%s.%d", oid_normalized, idx);
				ret = snmp_get_index(ss, item, oid_full, index_value, &idx, err, 0);
			}

			if (SUCCEED != ret && SUCCEED != (ret = snmp_get_index(ss, item, oid_normalized, index_value, &idx, err, 1)))
			{
				cache_del_snmp_index(item, oid_normalized, index_value);

				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "cannot find index [%s] of the OID [%s]: %s",
						oid_index,
						item->snmp_oid,
						err));
				ret = NOTSUPPORTED;
				break;
			}

			cache_put_snmp_index(item, oid_normalized, index_value, idx);

			zabbix_log(LOG_LEVEL_DEBUG, "Found index:%d", idx);

			pl = strchr(item->snmp_oid, '[');
			if (NULL == pl)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "cannot find left bracket in the OID [%s]",
						item->snmp_oid));
				ret = NOTSUPPORTED;
				break;
			}

			pl[0] = '\0';
			snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
			pl[0] = '[';

			zbx_snprintf(oid_full, sizeof(oid_full), "%s.%d", oid_normalized, idx);
			zabbix_log(LOG_LEVEL_DEBUG, "Full OID:%s", oid_full);

			ret = get_snmp(ss, item, oid_full, value);
		}
		while (0);
		break;
	default:
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID [%s] contains unsupported parameters",
				item->snmp_oid));
		ret = NOTSUPPORTED;
	}

	snmp_close_session(ss);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif
