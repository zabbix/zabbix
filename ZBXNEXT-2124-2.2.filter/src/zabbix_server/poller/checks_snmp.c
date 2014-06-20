/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "checks_snmp.h"
#include "comms.h"
#include "zbxjson.h"

#ifdef HAVE_SNMP

typedef struct
{
	char		*oid;
	char		*value;
	char		*idx;
	zbx_uint64_t	hostid;
	unsigned short	port;
}
zbx_snmp_index_t;

static zbx_snmp_index_t	*snmpidx = NULL;
static int		snmpidx_count = 0, snmpidx_alloc = 16;

static char	*zbx_get_snmp_type_error(u_char type)
{
	switch (type)
	{
		case SNMP_NOSUCHOBJECT:
			return zbx_strdup(NULL, "No Such Object available on this agent at this OID");
		case SNMP_NOSUCHINSTANCE:
			return zbx_strdup(NULL, "No Such Instance currently exists at this OID");
		case SNMP_ENDOFMIBVIEW:
			return zbx_strdup(NULL, "No more variables left in this MIB View"
					" (it is past the end of the MIB tree)");
		default:
			return zbx_dsprintf(NULL, "Value has unknown type 0x%02X", (unsigned int)type);
	}
}

static int	zbx_get_snmp_response_error(const struct snmp_session *ss, const DC_INTERFACE *interface, int status,
		const struct snmp_pdu *response, char *err)
{
	int	ret;

	if (STAT_SUCCESS == status)
	{
		zbx_snprintf(err, MAX_STRING_LEN, "SNMP error: %s", snmp_errstring(response->errstat));
		ret = NOTSUPPORTED;
	}
	else if (STAT_ERROR == status)
	{
		zbx_snprintf(err, MAX_STRING_LEN, "Cannot connect to \"%s:%hu\": %s.",
				interface->addr, interface->port, snmp_api_errstring(ss->s_snmp_errno));

		switch (ss->s_snmp_errno)
		{
			case SNMPERR_UNKNOWN_USER_NAME:
			case SNMPERR_UNSUPPORTED_SEC_LEVEL:
			case SNMPERR_AUTHENTICATION_FAILURE:
				ret = NOTSUPPORTED;
				break;
			default:
				ret = NETWORK_ERROR;
		}
	}
	else if (STAT_TIMEOUT == status)
	{
		zbx_snprintf(err, MAX_STRING_LEN, "Timeout while connecting to \"%s:%hu\".",
				interface->addr, interface->port);
		ret = NETWORK_ERROR;
	}
	else
	{
		zbx_snprintf(err, MAX_STRING_LEN, "SNMP error: [%d]", status);
		ret = NOTSUPPORTED;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snmp_index_compare                                           *
 *                                                                            *
 * Purpose: compare s1 and s2 index entries                                   *
 *                                                                            *
 * Parameters: s1 - snmp index entry                                          *
 *             s2 - snmp index entry                                          *
 *                                                                            *
 * Return value: -1, 0 or 1 if s1 entry is respectively less than, equal to   *
 *               or greater than s2                                           *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_snmp_index_compare(const zbx_snmp_index_t *s1, const zbx_snmp_index_t *s2)
{
	int	rc;

	if (s1->hostid < s2->hostid)
		return -1;
	if (s1->hostid > s2->hostid)
		return +1;

	if (s1->port < s2->port)
		return -1;
	if (s1->port > s2->port)
		return +1;

	if (0 != (rc = strcmp(s1->oid, s2->oid)))
		return rc;

	return strcmp(s1->value, s2->value);
}

/******************************************************************************
 *                                                                            *
 * Function: find nearest index for new record                                *
 *                                                                            *
 * Return value: index of new record                                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	get_snmpidx_nearestindex(const zbx_snmp_index_t *s)
{
	const char	*__function_name = "get_snmpidx_nearestindex";
	int		first_index, last_index, index = 0, cmp_res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 " port:%hu oid:'%s' value:'%s'",
			__function_name, s->hostid, s->port, s->oid, s->value);

	if (0 == snmpidx_count)
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

static int	cache_get_snmp_index(DC_ITEM *item, char *oid, char *value, char **idx, size_t *idx_alloc)
{
	const char		*__function_name = "cache_get_snmp_index";
	int			i, res = FAIL;
	zbx_snmp_index_t	s;
	size_t			idx_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' value:'%s'", __function_name, oid, value);

	if (NULL == snmpidx)
		goto end;

	s.hostid = item->host.hostid;
	s.port = item->interface.port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
	{
		zbx_strcpy_alloc(idx, idx_alloc, &idx_offset, snmpidx[i].idx);
		res = SUCCEED;
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s idx:%s", __function_name, zbx_result_string(res),
			SUCCEED == res ? *idx : "");

	return res;
}

static void	cache_put_snmp_index(DC_ITEM *item, char *oid, char *value, const char *idx)
{
	const char		*__function_name = "cache_put_snmp_index";
	int			i;
	zbx_snmp_index_t	s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' value:'%s' idx:'%s'", __function_name, oid, value, idx);

	if (NULL == snmpidx)
		snmpidx = zbx_malloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));

	s.hostid = item->host.hostid;
	s.port = item->interface.port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
		goto end;

	if (snmpidx_count == snmpidx_alloc)
	{
		snmpidx_alloc += 16;
		snmpidx = zbx_realloc(snmpidx, snmpidx_alloc * sizeof(zbx_snmp_index_t));
	}

	memmove(&snmpidx[i + 1], &snmpidx[i], sizeof(zbx_snmp_index_t) * (snmpidx_count - i));

	snmpidx[i].hostid = item->host.hostid;
	snmpidx[i].port = item->interface.port;
	snmpidx[i].oid = zbx_strdup(NULL, oid);
	snmpidx[i].value = zbx_strdup(NULL, value);
	snmpidx[i].idx = zbx_strdup(NULL, idx);
	snmpidx_count++;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	cache_del_snmp_index_by_position(int i)
{
	snmpidx_count--;
	zbx_free(snmpidx[i].oid);
	zbx_free(snmpidx[i].value);
	zbx_free(snmpidx[i].idx);
	memmove(&snmpidx[i], &snmpidx[i + 1], sizeof(zbx_snmp_index_t) * (snmpidx_count - i));
}

static void	cache_del_snmp_index_subtree(DC_ITEM *item, const char *oid)
{
	const char		*__function_name = "cache_del_snmp_index_subtree";
	int			i;
	zbx_snmp_index_t	s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s'", __function_name, oid);

	if (NULL == snmpidx)
		goto end;

	s.hostid = item->host.hostid;
	s.port = item->interface.port;
	s.oid = (char *)oid;
	s.value = "";

	i = get_snmpidx_nearestindex(&s);

	while (i < snmpidx_count)
	{
		if (snmpidx[i].hostid != s.hostid || snmpidx[i].port != s.port || 0 != strcmp(snmpidx[i].oid, s.oid))
			break;

		cache_del_snmp_index_by_position(i);
		/* No need to increment 'i'. Deleting an element from cache */
		/* brings the next element into position 'i'. */
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static struct snmp_session	*zbx_snmp_open_session(DC_ITEM *item, char *err)
{
	const char		*__function_name = "zbx_snmp_open_session";
	struct snmp_session	session, *ss = NULL;
	char			addr[128];
#ifdef HAVE_IPV6
	int			family;
#endif

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

	session.retries = 0;				/* number of retries after failed attempt */
							/* (net-snmp default = 5) */
	session.timeout = CONFIG_TIMEOUT * 1000 * 1000;	/* timeout of one attempt in microseconds */
							/* (net-snmp default = 1 second) */

#ifdef HAVE_IPV6
	if (SUCCEED != get_address_family(item->interface.addr, &family, err, MAX_STRING_LEN))
		goto end;

	if (PF_INET == family)
	{
		zbx_snprintf(addr, sizeof(addr), "%s:%hu", item->interface.addr, item->interface.port);
	}
	else
	{
		if (item->interface.useip)
			zbx_snprintf(addr, sizeof(addr), "udp6:[%s]:%hu", item->interface.addr, item->interface.port);
		else
			zbx_snprintf(addr, sizeof(addr), "udp6:%s:%hu", item->interface.addr, item->interface.port);
	}
#else
	zbx_snprintf(addr, sizeof(addr), "%s:%hu", item->interface.addr, item->interface.port);
#endif
	session.peername = addr;
	session.remote_port = item->interface.port;	/* remote_port is no longer used in latest versions of Net-SNMP */

	if (SNMP_VERSION_1 == session.version || SNMP_VERSION_2c == session.version)
	{
		session.community = (u_char *)item->snmp_community;
		session.community_len = strlen((void *)session.community);
		zabbix_log(LOG_LEVEL_DEBUG, "SNMP [%s@%s]", session.community, session.peername);
	}
	else if (SNMP_VERSION_3 == session.version)
	{
		/* set the SNMPv3 user name */
		session.securityName = item->snmpv3_securityname;
		session.securityNameLen = strlen(session.securityName);

		/* set the SNMPv3 context if specified */
		if ('\0' != *item->snmpv3_contextname)
		{
			session.contextName = item->snmpv3_contextname;
			session.contextNameLen = strlen(session.contextName);
		}

		/* set the security level to authenticated, but not encrypted */
		switch (item->snmpv3_securitylevel)
		{
			case ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_NOAUTH;
				break;
			case ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_AUTHNOPRIV;

				switch (item->snmpv3_authprotocol)
				{
					case ITEM_SNMPV3_AUTHPROTOCOL_MD5:
						/* set the authentication protocol to MD5 */
						session.securityAuthProto = usmHMACMD5AuthProtocol;
						session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
						break;
					case ITEM_SNMPV3_AUTHPROTOCOL_SHA:
						/* set the authentication protocol to SHA */
						session.securityAuthProto = usmHMACSHA1AuthProtocol;
						session.securityAuthProtoLen = USM_AUTH_PROTO_SHA_LEN;
						break;
					default:
						zbx_snprintf(err, MAX_STRING_LEN,
								"Unsupported authentication protocol [%d]",
								item->snmpv3_authprotocol);
						goto end;
				}

				session.securityAuthKeyLen = USM_AUTH_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)item->snmpv3_authpassphrase,
						strlen(item->snmpv3_authpassphrase), session.securityAuthKey,
						&session.securityAuthKeyLen))
				{
					zbx_strlcpy(err, "Error generating Ku from authentication pass phrase",
							MAX_STRING_LEN);
					goto end;
				}
				break;
			case ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV:
				session.securityLevel = SNMP_SEC_LEVEL_AUTHPRIV;

				switch (item->snmpv3_authprotocol)
				{
					case ITEM_SNMPV3_AUTHPROTOCOL_MD5:
						/* set the authentication protocol to MD5 */
						session.securityAuthProto = usmHMACMD5AuthProtocol;
						session.securityAuthProtoLen = USM_AUTH_PROTO_MD5_LEN;
						break;
					case ITEM_SNMPV3_AUTHPROTOCOL_SHA:
						/* set the authentication protocol to SHA */
						session.securityAuthProto = usmHMACSHA1AuthProtocol;
						session.securityAuthProtoLen = USM_AUTH_PROTO_SHA_LEN;
						break;
					default:
						zbx_snprintf(err, MAX_STRING_LEN,
								"Unsupported authentication protocol [%d]",
								item->snmpv3_authprotocol);
						goto end;
				}

				session.securityAuthKeyLen = USM_AUTH_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)item->snmpv3_authpassphrase,
						strlen(item->snmpv3_authpassphrase), session.securityAuthKey,
						&session.securityAuthKeyLen))
				{
					zbx_strlcpy(err, "Error generating Ku from authentication pass phrase",
							MAX_STRING_LEN);
					goto end;
				}

				switch (item->snmpv3_privprotocol)
				{
					case ITEM_SNMPV3_PRIVPROTOCOL_DES:
						/* set the privacy protocol to DES */
						session.securityPrivProto = usmDESPrivProtocol;
						session.securityPrivProtoLen = USM_PRIV_PROTO_DES_LEN;
						break;
					case ITEM_SNMPV3_PRIVPROTOCOL_AES:
						/* set the privacy protocol to AES */
						session.securityPrivProto = usmAESPrivProtocol;
						session.securityPrivProtoLen = USM_PRIV_PROTO_AES_LEN;
						break;
					default:
						zbx_snprintf(err, MAX_STRING_LEN,
								"Unsupported privacy protocol [%d]",
								item->snmpv3_privprotocol);
						goto end;
				}

				session.securityPrivKeyLen = USM_PRIV_KU_LEN;

				if (SNMPERR_SUCCESS != generate_Ku(session.securityAuthProto,
						session.securityAuthProtoLen, (u_char *)item->snmpv3_privpassphrase,
						strlen(item->snmpv3_privpassphrase), session.securityPrivKey,
						&session.securityPrivKeyLen))
				{
					zbx_strlcpy(err, "Error generating Ku from privacy pass phrase",
							MAX_STRING_LEN);
					goto end;
				}
				break;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "SNMPv3 [%s@%s]", session.securityName, session.peername);
	}

#ifdef HAVE_SNMP_SESSION_LOCALNAME
	if (NULL != CONFIG_SOURCE_IP)
	{
		/* In some cases specifying just local host (without local port) is not enough. We do */
		/* not care about the port number though so we let the OS select one by specifying 0. */
		/* See marc.info/?l=net-snmp-bugs&m=115624676507760 for details. */

		static char	localname[64];

		zbx_snprintf(localname, sizeof(localname), "%s:0", CONFIG_SOURCE_IP);
		session.localname = localname;
	}
#endif

	SOCK_STARTUP;

	if (NULL == (ss = snmp_open(&session)))
	{
		SOCK_CLEANUP;

		zbx_strlcpy(err, "Cannot open snmp session", MAX_STRING_LEN);
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ss;
}

static void	zbx_snmp_close_session(struct snmp_session *session)
{
	const char	*__function_name = "zbx_snmp_close_session";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	snmp_close(session);
	SOCK_CLEANUP;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static char	*zbx_snmp_get_octet_string(const struct variable_list *var)
{
	const char	*__function_name = "zbx_snmp_get_octet_string";
	static char	buf[MAX_STRING_LEN];
	const char	*hint;
	char		*strval_dyn = NULL, is_hex = 0;
	size_t          offset = 0;
	struct tree     *subtree;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* find the subtree to get display hint */
	subtree = get_tree(var->name, var->name_length, get_tree_head());
	hint = (NULL != subtree ? subtree->hint : NULL);

	/* we will decide if we want the value from var->val or what snprint_value() returned later */
	if (-1 == snprint_value(buf, sizeof(buf), var->name, var->name_length, var))
		goto end;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() full value:'%s' hint:'%s'", __function_name, buf, ZBX_NULL2STR(hint));

	/* decide if it's Hex, offset will be possibly needed later */
	if (0 == strncmp(buf, "Hex-STRING: ", 12))
	{
		is_hex = 1;
		offset = 12;
	}

	/* in case of no hex and no display hint take the value from */
	/* var->val, it contains unquoted and unescaped string */
	if (0 == is_hex && NULL == hint)
	{
		strval_dyn = zbx_malloc(strval_dyn, var->val_len + 1);
		memcpy(strval_dyn, var->val.string, var->val_len);
		strval_dyn[var->val_len] = '\0';
	}
	else
	{
		if (0 == is_hex && 0 == strncmp(buf, "STRING: ", 8))
			offset = 8;

		strval_dyn = zbx_strdup(strval_dyn, buf + offset);
	}

	zbx_lrtrim(strval_dyn, ZBX_WHITESPACE);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __function_name, ZBX_NULL2STR(strval_dyn));

	return strval_dyn;
}

static int	zbx_snmp_set_result(const struct variable_list *var, unsigned char value_type, unsigned char data_type,
		AGENT_RESULT *value)
{
	const char	*__function_name = "zbx_snmp_set_result";
	char		*strval_dyn;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ASN_OCTET_STR == var->type)
	{
		if (NULL == (strval_dyn = zbx_snmp_get_octet_string(var)))
		{
			SET_MSG_RESULT(value, zbx_strdup(NULL, "Cannot receive string value: out of memory."));
			ret = NOTSUPPORTED;
		}
		else
		{
			if (SUCCEED != set_result_type(value, value_type, data_type, strval_dyn))
				ret = NOTSUPPORTED;

			zbx_free(strval_dyn);
		}
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_UINTEGER == var->type || ASN_COUNTER == var->type || ASN_UNSIGNED64 == var->type ||
			ASN_TIMETICKS == var->type || ASN_GAUGE == var->type)
#else
	else if (ASN_UINTEGER == var->type || ASN_COUNTER == var->type ||
			ASN_TIMETICKS == var->type || ASN_GAUGE == var->type)
#endif
	{
		SET_UI64_RESULT(value, (unsigned long)*var->val.integer);
	}
	else if (ASN_COUNTER64 == var->type)
	{
		SET_UI64_RESULT(value, (((zbx_uint64_t)var->val.counter64->high) << 32) +
				(zbx_uint64_t)var->val.counter64->low);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_INTEGER == var->type || ASN_INTEGER64 == var->type)
#else
	else if (ASN_INTEGER == var->type)
#endif
	{
		/* negative integer values are converted to double */
		if (0 > *var->val.integer)
			SET_DBL_RESULT(value, (double)*var->val.integer);
		else
			SET_UI64_RESULT(value, (zbx_uint64_t)*var->val.integer);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_FLOAT == var->type)
	{
		SET_DBL_RESULT(value, *var->val.floatVal);
	}
	else if (ASN_DOUBLE == var->type)
	{
		SET_DBL_RESULT(value, *var->val.doubleVal);
	}
#endif
	else if (ASN_IPADDRESS == var->type)
	{
		SET_STR_RESULT(value, zbx_dsprintf(NULL, "%u.%u.%u.%u",
				(unsigned int)var->val.string[0],
				(unsigned int)var->val.string[1],
				(unsigned int)var->val.string[2],
				(unsigned int)var->val.string[3]));
	}
	else
	{
		SET_MSG_RESULT(value, zbx_get_snmp_type_error(var->type));
		ret = NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snmp_walk                                                    *
 *                                                                            *
 * Purpose: retrieve information by walking an OID tree                       *
 *                                                                            *
 * Parameters: ss     - [IN] SNMP session handle                              *
 *             item   - [IN] configuration of Zabbix item                     *
 *             OID    - [IN] OID of table with values of interest             *
 *             search - [IN] value to look for (optional)                     *
 *             value  - [OUT] result structure                                *
 *                                                                            *
 * Return value:  NOTSUPPORTED - OID does not exist, any other critical error *
 *                NETWORK_ERROR - recoverable network error                   *
 *                SUCCEED - if function successfully completed                *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: This function can operate in one of two modes.                   *
 *                                                                            *
 *           The first mode is enabled when 'search' parameter is specified   *
 *           and is meant to be used for dynamic indices. The function walks  *
 *           the OID tree, looking for the specified value, and it caches all *
 *           values in the table. If the value is found, the requested index  *
 *           is returned in the 'value' parameter.                            *
 *                                                                            *
 *           The second mode is enabled when 'search' is not specified and is *
 *           meant to be used for low-level discovery. In this case, 'value'  *
 *           parameter contains JSON with discovery data.                     *
 *                                                                            *
 ******************************************************************************/
static int	zbx_snmp_walk(struct snmp_session *ss, DC_ITEM *item, const char *OID, const char *search,
		AGENT_RESULT *value)
{
	const char		*__function_name = "zbx_snmp_walk";

	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN, OID_len;
	char			snmp_oid[MAX_STRING_LEN], err[MAX_STRING_LEN];
	struct variable_list	*var;
	int			status, running, found, ret = SUCCEED;
	struct zbx_json		j;
	AGENT_RESULT		snmp_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' search:'%s'", __function_name, OID, ZBX_NULL2STR(search));

	/* create OID from string */
	if (NULL == snmp_parse_oid(OID, rootOID, &rootOID_len))
	{
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "snmp_parse_oid(): cannot parse OID \"%s\".", OID));
		ret = NOTSUPPORTED;
		goto out;
	}

	if (-1 == snprint_objid(snmp_oid, sizeof(snmp_oid), rootOID, rootOID_len))
	{
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "snprint_objid(): buffer is not large enough: \"%s\".", OID));
		ret = NOTSUPPORTED;
		goto out;
	}

	OID_len = strlen(snmp_oid);

	/* copy rootOID to anOID */
	memcpy(anOID, rootOID, rootOID_len * sizeof(oid));
	anOID_len = rootOID_len;

	/* initialize variables */
	if (NULL != search)
	{
		found = 0;
		cache_del_snmp_index_subtree(item, OID);
	}
	else
	{
		zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
		zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);
	}

	running = 1;

	while (1 == running)
	{
		if (NULL == (pdu = snmp_pdu_create(SNMP_MSG_GETNEXT)))	/* create empty PDU */
		{
			SET_MSG_RESULT(value, zbx_strdup(NULL, "snmp_pdu_create(): cannot create PDU object."));
			ret = NOTSUPPORTED;
			break;
		}

		if (NULL == snmp_add_null_var(pdu, anOID, anOID_len))	/* add OID as variable to PDU */
		{
			SET_MSG_RESULT(value, zbx_strdup(NULL, "snmp_add_null_var(): cannot add null variable."));
			ret = NOTSUPPORTED;
			snmp_free_pdu(pdu);
			break;
		}

		/* communicate with agent */
		status = snmp_synch_response(ss, pdu, &response);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response():%d", __function_name, status);

		if (STAT_SUCCESS != status || SNMP_ERR_NOERROR != response->errstat)
		{
			ret = zbx_get_snmp_response_error(ss, &item->interface, status, response, err);
			SET_MSG_RESULT(value, zbx_strdup(NULL, err));
			running = 0;
			goto next;
		}

		/* process response */
		for (var = response->variables; NULL != var; var = var->next_variable)
		{
			/* verify if we are in the same subtree */
			if (var->name_length < rootOID_len ||
					0 != memcmp(rootOID, var->name, rootOID_len * sizeof(oid)))
			{
				/* not part of this subtree */

				if (NULL != search)
				{
					SET_MSG_RESULT(value, zbx_dsprintf(NULL, "NOT FOUND: %s[%s]", OID, search));
					ret = NOTSUPPORTED;
				}

				running = 0;
				break;
			}
			else
			{
				/* verify if OIDs are increasing */
				if (SNMP_ENDOFMIBVIEW != var->type && SNMP_NOSUCHOBJECT != var->type &&
						SNMP_NOSUCHINSTANCE != var->type)
				{
					/* not an exception value */

					if (0 <= snmp_oid_compare(anOID, anOID_len, var->name, var->name_length))
					{
						SET_MSG_RESULT(value, zbx_strdup(NULL, "OID not increasing."));
						ret = NOTSUPPORTED;
						running = 0;
						break;
					}

					if (-1 == snprint_objid(snmp_oid, sizeof(snmp_oid),
								var->name, var->name_length))
					{
						SET_MSG_RESULT(value, zbx_dsprintf(NULL,
								"snprint_objid(): buffer is not large enough:"
								" OID: \"%s\" snmp_oid: \"%s\".", OID, snmp_oid));
						ret = NOTSUPPORTED;
						running = 0;
						break;
					}

					init_result(&snmp_value);

					if (SUCCEED == zbx_snmp_set_result(var, ITEM_VALUE_TYPE_STR, 0, &snmp_value) &&
							NULL != GET_STR_RESULT(&snmp_value))
					{
						if (NULL != search)
						{
							cache_put_snmp_index(item, (char *)OID, snmp_value.str,
									&snmp_oid[OID_len + 1]);

							if (0 == found && 0 == strcmp(search, snmp_value.str))
							{
								SET_STR_RESULT(value, zbx_strdup(NULL,
										&snmp_oid[OID_len + 1]));

								zabbix_log(LOG_LEVEL_DEBUG, "index found: '%s'",
										&snmp_oid[OID_len + 1]);

								found = 1;
							}
						}
						else
						{
							zbx_json_addobject(&j, NULL);
							zbx_json_addstring(&j, "{#SNMPINDEX}", &snmp_oid[OID_len + 1],
									ZBX_JSON_TYPE_STRING);
							zbx_json_addstring(&j, "{#SNMPVALUE}", snmp_value.str,
									ZBX_JSON_TYPE_STRING);
							zbx_json_close(&j);
						}
					}
					else
					{
						char	**msg;

						msg = GET_MSG_RESULT(&snmp_value);

						zabbix_log(LOG_LEVEL_DEBUG, "cannot get OID '%s' string value: %s",
								snmp_oid,
								NULL != msg && NULL != *msg ? *msg : "(null)");
					}

					free_result(&snmp_value);

					/* go to next variable */
					memcpy((char *)anOID, (char *)var->name, var->name_length * sizeof(oid));
					anOID_len = var->name_length;
				}
				else
				{
					/* an exception value, so stop */

					SET_MSG_RESULT(value, zbx_get_snmp_type_error(var->type));
					ret = NOTSUPPORTED;
					running = 0;
					break;
				}
			}
		}
next:
		if (NULL != response)
			snmp_free_pdu(response);
	}

	if (NULL != search)
	{
		ret = (1 == found ? SUCCEED : NOTSUPPORTED);
	}
	else
	{
		zbx_json_close(&j);

		if (SUCCEED == ret)
			SET_TEXT_RESULT(value, zbx_strdup(NULL, j.buffer));

		zbx_json_free(&j);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_get_value(struct snmp_session *ss, unsigned char value_type, unsigned char data_type,
		const DC_INTERFACE *interface, const char *snmp_oid, AGENT_RESULT *value)
{
	const char		*__function_name = "zbx_snmp_get_value";

	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN;
	struct variable_list	*var;
	int			status, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s'", __function_name, snmp_oid);

	if (NULL == snmp_parse_oid(snmp_oid, anOID, &anOID_len))
	{
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "snmp_parse_oid(): cannot parse OID \"%s\".", snmp_oid));
		ret = NOTSUPPORTED;
		goto out;
	}

	if (NULL == (pdu = snmp_pdu_create(SNMP_MSG_GET)))
	{
		SET_MSG_RESULT(value, zbx_strdup(NULL, "snmp_pdu_create(): cannot create PDU object."));
		ret = NOTSUPPORTED;
		goto out;
	}

	if (NULL == snmp_add_null_var(pdu, anOID, anOID_len))
	{
		SET_MSG_RESULT(value, zbx_strdup(NULL, "snmp_add_null_var(): cannot add null variable."));
		ret = NOTSUPPORTED;
		snmp_free_pdu(pdu);
		goto out;
	}

	status = snmp_synch_response(ss, pdu, &response);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response():%d", __function_name, status);

	if (STAT_SUCCESS == status && SNMP_ERR_NOERROR == response->errstat)
	{
		if (NULL == (var = response->variables))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ret = NOTSUPPORTED;
		}
		else
			ret = zbx_snmp_set_result(var, value_type, data_type, value);
	}
	else
	{
		char	err[MAX_STRING_LEN];

		ret = zbx_get_snmp_response_error(ss, interface, status, response, err);
		SET_MSG_RESULT(value, zbx_strdup(NULL, err));
	}

	if (NULL != response)
		snmp_free_pdu(response);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snmp_translate                                               *
 *                                                                            *
 * Purpose: translate well-known object identifiers into numeric form         *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_snmp_translate(char *oid_translated, const char *oid, size_t max_oid_len)
{
	const char	*__function_name = "zbx_snmp_translate";

	typedef struct
	{
		const size_t	sz;
		const char	*mib;
		const char	*replace;
	}
	zbx_mib_norm_t;

#define LEN_STR(x)	sizeof(x) - 1, x
	static zbx_mib_norm_t mibs[] =
	{
		/* the most popular items first */
		{LEN_STR("ifDescr"),		".1.3.6.1.2.1.2.2.1.2"},
		{LEN_STR("ifInOctets"),		".1.3.6.1.2.1.2.2.1.10"},
		{LEN_STR("ifOutOctets"),	".1.3.6.1.2.1.2.2.1.16"},
		{LEN_STR("ifAdminStatus"),	".1.3.6.1.2.1.2.2.1.7"},
		{LEN_STR("ifOperStatus"),	".1.3.6.1.2.1.2.2.1.8"},
		{LEN_STR("ifIndex"),		".1.3.6.1.2.1.2.2.1.1"},
		{LEN_STR("ifType"),		".1.3.6.1.2.1.2.2.1.3"},
		{LEN_STR("ifMtu"),		".1.3.6.1.2.1.2.2.1.4"},
		{LEN_STR("ifSpeed"),		".1.3.6.1.2.1.2.2.1.5"},
		{LEN_STR("ifPhysAddress"),	".1.3.6.1.2.1.2.2.1.6"},
		{LEN_STR("ifInUcastPkts"),	".1.3.6.1.2.1.2.2.1.11"},
		{LEN_STR("ifInNUcastPkts"),	".1.3.6.1.2.1.2.2.1.12"},
		{LEN_STR("ifInDiscards"),	".1.3.6.1.2.1.2.2.1.13"},
		{LEN_STR("ifInErrors"),		".1.3.6.1.2.1.2.2.1.14"},
		{LEN_STR("ifInUnknownProtos"),	".1.3.6.1.2.1.2.2.1.15"},
		{LEN_STR("ifOutUcastPkts"),	".1.3.6.1.2.1.2.2.1.17"},
		{LEN_STR("ifOutNUcastPkts"),	".1.3.6.1.2.1.2.2.1.18"},
		{LEN_STR("ifOutDiscards"),	".1.3.6.1.2.1.2.2.1.19"},
		{LEN_STR("ifOutErrors"),	".1.3.6.1.2.1.2.2.1.20"},
		{LEN_STR("ifOutQLen"),		".1.3.6.1.2.1.2.2.1.21"},
		{0}
	};
#undef LEN_STR

	int	found = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s'", __function_name, oid);

	for (i = 0; 0 != mibs[i].sz; i++)
	{
		if (0 == strncmp(mibs[i].mib, oid, mibs[i].sz))
		{
			found = 1;
			zbx_snprintf(oid_translated, max_oid_len, "%s%s", mibs[i].replace, oid + mibs[i].sz);
			break;
		}
	}

	if (0 == found)
		zbx_strlcpy(oid_translated, oid, max_oid_len);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() oid_translated:'%s'", __function_name, oid_translated);
}

int	get_value_snmp(DC_ITEM *item, AGENT_RESULT *value)
{
	const char		*__function_name = "get_value_snmp";

	struct snmp_session	*ss;
	char			method[8], oid_translated[MAX_STRING_LEN], oid_index[MAX_STRING_LEN],
				oid_full[MAX_STRING_LEN], index_value[MAX_STRING_LEN], err[MAX_STRING_LEN], *pl;
	int			num, ret;
	char			*idx = NULL;
	size_t			idx_alloc = 32;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' oid:'%s'", __function_name, item->key_orig, item->snmp_oid);

	assert(item->type == ITEM_TYPE_SNMPv1 || item->type == ITEM_TYPE_SNMPv2c || item->type == ITEM_TYPE_SNMPv3);

	if (NULL == (ss = zbx_snmp_open_session(item, err)))
	{
		SET_MSG_RESULT(value, zbx_strdup(NULL, err));
		ret = NOTSUPPORTED;
		goto out;
	}

	num = num_key_param(item->snmp_oid);

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
	{
		switch (num)
		{
			case 0:
				zbx_snmp_translate(oid_translated, item->snmp_oid, sizeof(oid_translated));
				ret = zbx_snmp_walk(ss, item, oid_translated, NULL, value);
				break;
			default:
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
						item->snmp_oid));
				ret = NOTSUPPORTED;
		}
	}
	else
	{
		switch (num)
		{
			case 0:
				zabbix_log(LOG_LEVEL_DEBUG, "%s() standard processing", __function_name);
				zbx_snmp_translate(oid_translated, item->snmp_oid, sizeof(oid_translated));
				ret = zbx_snmp_get_value(ss, item->value_type, item->data_type, &item->interface,
						oid_translated, value);
				break;
			case 3:
				zabbix_log(LOG_LEVEL_DEBUG, "%s() special processing", __function_name);

				get_key_param(item->snmp_oid, 1, method, sizeof(method));
				get_key_param(item->snmp_oid, 2, oid_index, sizeof(oid_index));
				get_key_param(item->snmp_oid, 3, index_value, sizeof(index_value));

				zabbix_log(LOG_LEVEL_DEBUG, "%s() method:'%s' oid_index:'%s' index_value:'%s'",
						__function_name, method, oid_index, index_value);

				if (0 != strcmp("index", method))
				{
					SET_MSG_RESULT(value, zbx_dsprintf(NULL,
							"Unsupported method \"%s\" in the OID \"%s\".",
							method, item->snmp_oid));
					ret = NOTSUPPORTED;
					break;
				}

				idx = zbx_malloc(idx, idx_alloc);

				zbx_snmp_translate(oid_translated, oid_index, sizeof(oid_translated));

				if (SUCCEED == (ret = cache_get_snmp_index(item, oid_translated, index_value,
						&idx, &idx_alloc)))
				{
					AGENT_RESULT	current_index_value;

					init_result(&current_index_value);

					zbx_snprintf(oid_full, sizeof(oid_full), "%s.%s", oid_translated, idx);

					ret = zbx_snmp_get_value(ss, ITEM_VALUE_TYPE_STR, 0, &item->interface, oid_full,
							&current_index_value);

					if (SUCCEED == ret && (NULL == GET_STR_RESULT(&current_index_value) ||
							0 != strcmp(current_index_value.str, index_value)))
					{
						ret = NOTSUPPORTED;
					}

					free_result(&current_index_value);
				}

				if (SUCCEED != ret)
				{
					AGENT_RESULT	index;

					init_result(&index);

					if (SUCCEED == (ret = zbx_snmp_walk(ss, item, oid_translated, index_value,
							&index)))
					{
						size_t	idx_offset = 0;

						zbx_strcpy_alloc(&idx, &idx_alloc, &idx_offset, index.str);
					}
					else
					{
						SET_MSG_RESULT(value, zbx_dsprintf(NULL,
								"Cannot find index \"%s\" of the OID \"%s\": %s",
								oid_index, item->snmp_oid, index.msg));
					}

					free_result(&index);
				}

				if (SUCCEED == ret)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s() idx:'%s'", __function_name, idx);

					pl = strchr(item->snmp_oid, '[');

					*pl = '\0';
					zbx_snmp_translate(oid_translated, item->snmp_oid, sizeof(oid_translated));
					*pl = '[';

					zbx_snprintf(oid_full, sizeof(oid_full), "%s.%s", oid_translated, idx);
					ret = zbx_snmp_get_value(ss, item->value_type, item->data_type,
							&item->interface, oid_full, value);
				}

				zbx_free(idx);

				break;
			default:
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
						item->snmp_oid));
				ret = NOTSUPPORTED;
		}
	}

	zbx_snmp_close_session(ss);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif	/* HAVE_SNMP */
