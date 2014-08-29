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
		const struct snmp_pdu *response, char *error, int max_error_len)
{
	int	ret;

	if (STAT_SUCCESS == status)
	{
		zbx_snprintf(error, max_error_len, "SNMP error: %s", snmp_errstring(response->errstat));
		ret = NOTSUPPORTED;
	}
	else if (STAT_ERROR == status)
	{
		zbx_snprintf(error, max_error_len, "Cannot connect to \"%s:%hu\": %s.",
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
		zbx_snprintf(error, max_error_len, "Timeout while connecting to \"%s:%hu\".",
				interface->addr, interface->port);
		ret = NETWORK_ERROR;
	}
	else
	{
		zbx_snprintf(error, max_error_len, "SNMP error: [%d]", status);
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

	ZBX_RETURN_IF_NOT_EQUAL(s1->hostid, s2->hostid);
	ZBX_RETURN_IF_NOT_EQUAL(s1->port, s2->port);

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

static int	cache_get_snmp_index(const DC_ITEM *item, char *oid, char *value, char **idx, size_t *idx_alloc)
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

static void	cache_put_snmp_index(const DC_ITEM *item, char *oid, char *value, const char *idx)
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

static void	cache_del_snmp_index_subtree(const DC_ITEM *item, const char *oid)
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

static struct snmp_session	*zbx_snmp_open_session(const DC_ITEM *item, char *error, int max_error_len)
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
	if (SUCCEED != get_address_family(item->interface.addr, &family, error, max_error_len))
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
						zbx_snprintf(error, max_error_len,
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
					zbx_strlcpy(error, "Error generating Ku from authentication pass phrase",
							max_error_len);
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
						zbx_snprintf(error, max_error_len,
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
					zbx_strlcpy(error, "Error generating Ku from authentication pass phrase",
							max_error_len);
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
						zbx_snprintf(error, max_error_len,
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
					zbx_strlcpy(error, "Error generating Ku from privacy pass phrase",
							max_error_len);
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

		zbx_strlcpy(error, "Cannot open snmp session", max_error_len);
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

	const char	*hint;
	char		buffer[MAX_STRING_LEN];
	char		*strval_dyn = NULL, is_hex = 0;
	size_t          offset = 0;
	struct tree     *subtree;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* find the subtree to get display hint */
	subtree = get_tree(var->name, var->name_length, get_tree_head());
	hint = (NULL != subtree ? subtree->hint : NULL);

	/* we will decide if we want the value from var->val or what snprint_value() returned later */
	if (-1 == snprint_value(buffer, sizeof(buffer), var->name, var->name_length, var))
		goto end;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() full value:'%s' hint:'%s'", __function_name, buffer, ZBX_NULL2STR(hint));

	/* decide if it's Hex, offset will be possibly needed later */
	if (0 == strncmp(buffer, "Hex-STRING: ", 12))
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
		if (0 == is_hex && 0 == strncmp(buffer, "STRING: ", 8))
			offset = 8;

		strval_dyn = zbx_strdup(strval_dyn, buffer + offset);
	}

	zbx_lrtrim(strval_dyn, ZBX_WHITESPACE);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __function_name, ZBX_NULL2STR(strval_dyn));

	return strval_dyn;
}

static int	zbx_snmp_set_result(const struct variable_list *var, unsigned char value_type, unsigned char data_type,
		AGENT_RESULT *result)
{
	const char	*__function_name = "zbx_snmp_set_result";
	char		*strval_dyn;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%d value_type:%d data_type:%d", __function_name,
			(int)var->type, (int)value_type, (int)data_type);

	if (ASN_OCTET_STR == var->type)
	{
		if (NULL == (strval_dyn = zbx_snmp_get_octet_string(var)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot receive string value: out of memory."));
			ret = NOTSUPPORTED;
		}
		else
		{
			if (SUCCEED != set_result_type(result, value_type, data_type, strval_dyn))
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
		SET_UI64_RESULT(result, (unsigned long)*var->val.integer);
	}
	else if (ASN_COUNTER64 == var->type)
	{
		SET_UI64_RESULT(result, (((zbx_uint64_t)var->val.counter64->high) << 32) +
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
			SET_DBL_RESULT(result, (double)*var->val.integer);
		else
			SET_UI64_RESULT(result, (zbx_uint64_t)*var->val.integer);
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_FLOAT == var->type)
	{
		SET_DBL_RESULT(result, *var->val.floatVal);
	}
	else if (ASN_DOUBLE == var->type)
	{
		SET_DBL_RESULT(result, *var->val.doubleVal);
	}
#endif
	else if (ASN_IPADDRESS == var->type)
	{
		SET_STR_RESULT(result, zbx_dsprintf(NULL, "%u.%u.%u.%u",
				(unsigned int)var->val.string[0],
				(unsigned int)var->val.string[1],
				(unsigned int)var->val.string[2],
				(unsigned int)var->val.string[3]));
	}
	else
	{
		SET_MSG_RESULT(result, zbx_get_snmp_type_error(var->type));
		ret = NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#define ZBX_OID_INDEX_STRING	0
#define ZBX_OID_INDEX_NUMERIC	1

static int	zbx_snmp_print_oid(char *buffer, size_t buffer_len, const oid *objid, size_t objid_len, int format)
{
	if (SNMPERR_SUCCESS != netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DONT_BREAKDOWN_OIDS,
			format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set \"dontBreakdownOids\" option to %d for Net-SNMP", format);
		return -1;
	}

	return snprint_objid(buffer, buffer_len, objid, objid_len);
}

static int	zbx_snmp_choose_index(char *buffer, size_t buffer_len, const oid *objid, size_t objid_len,
		size_t root_string_len, size_t root_numeric_len)
{
	const char	*__function_name = "zbx_snmp_choose_index";

	oid	parsed_oid[MAX_OID_LEN];
	size_t	parsed_oid_len = MAX_OID_LEN;
	char	printed_oid[MAX_STRING_LEN];

	/**************************************************************************************************************/
	/*                                                                                                            */
	/* When we are providing a value for {#SNMPINDEX}, we would like to provide a pretty value. This is only a    */
	/* concern for OIDs with string indices. For instance, suppose we are walking the following OID:              */
	/*                                                                                                            */
	/*   SNMP-VIEW-BASED-ACM-MIB::vacmGroupName                                                                   */
	/*                                                                                                            */
	/* Suppose also that we are currently looking at this OID:                                                    */
	/*                                                                                                            */
	/*   SNMP-VIEW-BASED-ACM-MIB::vacmGroupName.3."authOnlyUser"                                                  */
	/*                                                                                                            */
	/* Then, we would like to provide {#SNMPINDEX} with this value:                                               */
	/*                                                                                                            */
	/*   3."authOnlyUser"                                                                                         */
	/*                                                                                                            */
	/* An alternative approach would be to provide {#SNMPINDEX} with numeric value. While it is equivalent to the */
	/* string representation above, the string representation is more readable and thus more useful to users:     */
	/*                                                                                                            */
	/*   3.12.97.117.116.104.79.110.108.121.85.115.101.114                                                        */
	/*                                                                                                            */
	/* Here, 12 is the length of "authOnlyUser" and the rest is the string encoding using ASCII characters.       */
	/*                                                                                                            */
	/* There are two problems with always providing {#SNMPINDEX} that has an index representation as a string.    */
	/*                                                                                                            */
	/* The first problem is indices of type InetAddress. The Net-SNMP library has code for pretty-printing IP     */
	/* addresses, but no way to parse them back. As an example, consider the following OID:                       */
	/*                                                                                                            */
	/*   .1.3.6.1.2.1.4.34.1.4.1.4.192.168.3.255                                                                  */
	/*                                                                                                            */
	/* Its pretty representation is like this:                                                                    */
	/*                                                                                                            */
	/*   IP-MIB::ipAddressType.ipv4."192.168.3.255"                                                               */
	/*                                                                                                            */
	/* However, when trying to parse it, it turns into this OID:                                                  */
	/*                                                                                                            */
	/*   .1.3.6.1.2.1.4.34.1.4.1.13.49.57.50.46.49.54.56.46.51.46.50.53.53                                        */
	/*                                                                                                            */
	/* Apparently, this is different than the original.                                                           */
	/*                                                                                                            */
	/* The second problem is indices of type OCTET STRING, which might contain unprintable characters:            */
	/*                                                                                                            */
	/*   1.3.6.1.2.1.17.4.3.1.1.0.0.240.122.113.21                                                                */
	/*                                                                                                            */
	/* Its pretty representation is like this (note the single quotes which stand for a fixed-length string):     */
	/*                                                                                                            */
	/*   BRIDGE-MIB::dot1dTpFdbAddress.'...zq.'                                                                   */
	/*                                                                                                            */
	/* Here, '...zq.' stands for 0.0.240.122.113.21, where only 'z' (122) and 'q' (113) are printable.            */
	/*                                                                                                            */
	/* Apparently, this cannot be turned back into the numeric representation.                                    */
	/*                                                                                                            */
	/* So what we try to do is first print it pretty. If there is no string-looking index, return it as output.   */
	/* If there is such an index, we check that it can be parsed and that the result is the same as the original. */
	/*                                                                                                            */
	/**************************************************************************************************************/

	if (-1 == zbx_snmp_print_oid(printed_oid, sizeof(printed_oid), objid, objid_len, ZBX_OID_INDEX_STRING))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot print OID with string indices", __function_name);
		goto numeric;
	}

	if (NULL == strchr(printed_oid, '"') && NULL == strchr(printed_oid, '\''))
	{
		zbx_strlcpy(buffer, printed_oid + root_string_len + 1, buffer_len);
		return SUCCEED;
	}

	if (NULL == snmp_parse_oid(printed_oid, parsed_oid, &parsed_oid_len))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot parse OID '%s'", __function_name, printed_oid);
		goto numeric;
	}

	if (parsed_oid_len == objid_len && 0 == memcmp(parsed_oid, objid, parsed_oid_len * sizeof(oid)))
	{
		zbx_strlcpy(buffer, printed_oid + root_string_len + 1, buffer_len);
		return SUCCEED;
	}
numeric:
	if (-1 == zbx_snmp_print_oid(printed_oid, sizeof(printed_oid), objid, objid_len, ZBX_OID_INDEX_NUMERIC))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot print OID with numeric indices", __function_name);
		return FAIL;
	}

	zbx_strlcpy(buffer, printed_oid + root_numeric_len + 1, buffer_len);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snmp_walk                                                    *
 *                                                                            *
 * Purpose: retrieve information by walking an OID tree                       *
 *                                                                            *
 * Parameters: ss    - [IN] SNMP session handle                               *
 *             item  - [IN] configuration of Zabbix item                      *
 *             OID   - [IN] OID of table with values of interest              *
 *             mode  - [IN] mode to operate in                                *
 *             value - [OUT] result structure                                 *
 *                                                                            *
 * Return value: NOTSUPPORTED - OID does not exist, any other critical error  *
 *               NETWORK_ERROR - recoverable network error                    *
 *               CONFIG_ERROR - item configuration error                      *
 *               SUCCEED - if function successfully completed                 *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments: This function can operate in one of two modes.                   *
 *                                                                            *
 *           Mode ZBX_SNMP_WALK_MODE_CACHE is used for dynamic indices. The   *
 *           function walks the OID tree and caches all values in the table.  *
 *           The 'value' parameter is only used to store an error message.    *
 *                                                                            *
 *           Mode ZBX_SNMP_WALK_MODE_DISCOVERY is used for low-level          *
 *           discovery. The 'value' parameter in this case is used to         *
 *           store JSON with discovery data.                                  *
 *                                                                            *
 ******************************************************************************/

#define ZBX_SNMP_WALK_MODE_CACHE	0
#define ZBX_SNMP_WALK_MODE_DISCOVERY	1

static int	zbx_snmp_walk(struct snmp_session *ss, const DC_ITEM *item, const char *OID, unsigned char mode,
		AGENT_RESULT *result, int *max_succeed, int *min_fail, int max_vars)
{
	const char		*__function_name = "zbx_snmp_walk";

	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN, root_string_len, root_numeric_len;
	char			snmp_oid[MAX_STRING_LEN], error[MAX_STRING_LEN];
	struct variable_list	*var;
	int			bulk, status, level, running, num_vars, ret = SUCCEED;
	struct zbx_json		j;
	AGENT_RESULT		snmp_result;

	bulk = (ITEM_TYPE_SNMPv1 == item->type ? 0 : 1);	/* GetBulkRequest-PDU available since SNMPv2 */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' bulk:%d mode:%d", __function_name, OID, bulk, (int)mode);

	/* create OID from string */
	if (NULL == snmp_parse_oid(OID, rootOID, &rootOID_len))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "snmp_parse_oid(): cannot parse OID \"%s\".", OID));
		ret = CONFIG_ERROR;
		goto out;
	}

	if (-1 == zbx_snmp_print_oid(snmp_oid, sizeof(snmp_oid), rootOID, rootOID_len, ZBX_OID_INDEX_STRING))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "zbx_snmp_print_oid():"
				" cannot print OID \"%s\" with string indices.", OID));
		ret = CONFIG_ERROR;
		goto out;
	}

	root_string_len = strlen(snmp_oid);

	if (-1 == zbx_snmp_print_oid(snmp_oid, sizeof(snmp_oid), rootOID, rootOID_len, ZBX_OID_INDEX_NUMERIC))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "zbx_snmp_print_oid():"
				" cannot print OID \"%s\" with numeric indices.", OID));
		ret = CONFIG_ERROR;
		goto out;
	}

	root_numeric_len = strlen(snmp_oid);

	/* copy rootOID to anOID */
	memcpy(anOID, rootOID, rootOID_len * sizeof(oid));
	anOID_len = rootOID_len;

	/* initialize variables */
	if (ZBX_SNMP_WALK_MODE_CACHE == mode)
	{
		cache_del_snmp_index_subtree(item, OID);
	}
	else
	{
		zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
		zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);
	}

	level = 0;
	running = 1;

	while (1 == running)
	{
		if (NULL == (pdu = snmp_pdu_create(0 == bulk ? SNMP_MSG_GETNEXT : SNMP_MSG_GETBULK)))	/* create PDU */
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "snmp_pdu_create(): cannot create PDU object."));
			ret = CONFIG_ERROR;
			break;
		}

		if (NULL == snmp_add_null_var(pdu, anOID, anOID_len))	/* add OID as variable to PDU */
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "snmp_add_null_var(): cannot add null variable."));
			ret = CONFIG_ERROR;
			snmp_free_pdu(pdu);
			break;
		}

		if (1 == bulk)
		{
			pdu->non_repeaters = 0;
			pdu->max_repetitions = max_vars;
		}

		/* communicate with agent */
		status = snmp_synch_response(ss, pdu, &response);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response() status:%d errstat:%ld max_vars:%d",
				__function_name, status, NULL == response ? (long)-1 : response->errstat, max_vars);

		if (1 < max_vars &&
			((STAT_SUCCESS == status && SNMP_ERR_TOOBIG == response->errstat) || STAT_TIMEOUT == status))
		{
			/* The logic of iteratively reducing request size here is the same as in function */
			/* zbx_snmp_get_values(). Please refer to the description there for explanation.  */

			if (*min_fail > max_vars)
				*min_fail = max_vars;

			if (0 == level)
			{
				max_vars /= 2;
			}
			else if (1 == level)
			{
				max_vars = 1;
			}

			level++;

			goto next;
		}
		else if (STAT_SUCCESS != status || SNMP_ERR_NOERROR != response->errstat)
		{
			ret = zbx_get_snmp_response_error(ss, &item->interface, status, response, error, sizeof(error));
			SET_MSG_RESULT(result, zbx_strdup(NULL, error));
			running = 0;
			goto next;
		}

		/* process response */
		for (num_vars = 0, var = response->variables; NULL != var; num_vars++, var = var->next_variable)
		{
			/* verify if we are in the same subtree */
			if (SNMP_ENDOFMIBVIEW == var->type || var->name_length < rootOID_len ||
					0 != memcmp(rootOID, var->name, rootOID_len * sizeof(oid)))
			{
				/* reached the end or past this subtree */
				running = 0;
				break;
			}
			else if (SNMP_NOSUCHOBJECT != var->type && SNMP_NOSUCHINSTANCE != var->type)
			{
				/* not an exception value */

				if (0 <= snmp_oid_compare(anOID, anOID_len, var->name, var->name_length))
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "OID not increasing."));
					ret = NOTSUPPORTED;
					running = 0;
					break;
				}

				if (SUCCEED != zbx_snmp_choose_index(snmp_oid, sizeof(snmp_oid), var->name,
						var->name_length, root_string_len, root_numeric_len))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "zbx_snmp_choose_index():"
							" cannot choose appropriate index while walking for"
							" OID \"%s\".", OID));
					ret = NOTSUPPORTED;
					running = 0;
					break;
				}

				init_result(&snmp_result);

				if (SUCCEED == zbx_snmp_set_result(var, ITEM_VALUE_TYPE_STR, 0, &snmp_result) &&
						NULL != GET_STR_RESULT(&snmp_result))
				{
					if (ZBX_SNMP_WALK_MODE_CACHE == mode)
					{
						cache_put_snmp_index(item, (char *)OID, snmp_result.str, snmp_oid);
					}
					else
					{
						zbx_json_addobject(&j, NULL);
						zbx_json_addstring(&j, "{#SNMPINDEX}", snmp_oid, ZBX_JSON_TYPE_STRING);
						zbx_json_addstring(&j, "{#SNMPVALUE}", snmp_result.str,
								ZBX_JSON_TYPE_STRING);
						zbx_json_close(&j);
					}
				}
				else
				{
					char	**msg;

					msg = GET_MSG_RESULT(&snmp_result);

					zabbix_log(LOG_LEVEL_DEBUG, "cannot get index '%s' string value: %s",
							snmp_oid, NULL != msg && NULL != *msg ? *msg : "(null)");
				}

				free_result(&snmp_result);

				/* go to next variable */
				memcpy((char *)anOID, (char *)var->name, var->name_length * sizeof(oid));
				anOID_len = var->name_length;
			}
			else
			{
				/* an exception value, so stop */

				SET_MSG_RESULT(result, zbx_get_snmp_type_error(var->type));
				ret = NOTSUPPORTED;
				running = 0;
				break;
			}
		}

		if (*max_succeed < num_vars)
			*max_succeed = num_vars;
next:
		if (NULL != response)
			snmp_free_pdu(response);
	}

	if (ZBX_SNMP_WALK_MODE_DISCOVERY == mode)
	{
		zbx_json_close(&j);

		if (SUCCEED == ret)
			SET_TEXT_RESULT(result, zbx_strdup(NULL, j.buffer));

		zbx_json_free(&j);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_get_values(struct snmp_session *ss, const DC_ITEM *items, char oids[][ITEM_SNMP_OID_LEN_MAX],
		AGENT_RESULT *results, int *errcodes, unsigned char *query_and_ignore_type, int num, int level,
		char *error, int max_error_len, int *max_succeed, int *min_fail)
{
	const char		*__function_name = "zbx_snmp_get_values";

	int			i, j, status, ret = SUCCEED;
	int			mapping[MAX_SNMP_ITEMS], mapping_num = 0;
	struct snmp_pdu		*pdu, *response;
	struct variable_list	*var;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() num:%d level:%d", __function_name, num, level);

	if (NULL == (pdu = snmp_pdu_create(SNMP_MSG_GET)))
	{
		strlcpy(error, "snmp_pdu_create(): cannot create PDU object.", max_error_len);
		ret = CONFIG_ERROR;
		goto out;
	}

	for (i = 0; i < num; i++)
	{
		oid	anOID[MAX_OID_LEN];
		size_t	anOID_len = MAX_OID_LEN;

		if (SUCCEED != errcodes[i])
			continue;

		if (NULL != query_and_ignore_type && 0 == query_and_ignore_type[i])
			continue;

		if (NULL == snmp_parse_oid(oids[i], anOID, &anOID_len))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "snmp_parse_oid(): cannot parse OID \"%s\".",
					oids[i]));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		if (NULL == snmp_add_null_var(pdu, anOID, anOID_len))
		{
			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "snmp_add_null_var(): cannot add null variable."));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		mapping[mapping_num++] = i;
	}

	if (0 == mapping_num)
	{
		snmp_free_pdu(pdu);
		goto out;
	}
retry:
	status = snmp_synch_response(ss, pdu, &response);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response() status:%d errstat:%ld mapping_num:%d",
			__function_name, status, NULL == response ? (long)-1 : response->errstat, mapping_num);

	if (STAT_SUCCESS == status && SNMP_ERR_NOERROR == response->errstat)
	{
		if (*max_succeed < mapping_num)
			*max_succeed = mapping_num;

		for (i = 0, var = response->variables; NULL != var; i++, var = var->next_variable)
		{
			j = mapping[i];

			if (NULL != query_and_ignore_type && 1 == query_and_ignore_type[j])
			{
				(void)zbx_snmp_set_result(var, ITEM_VALUE_TYPE_STR, 0, &results[j]);
			}
			else
			{
				errcodes[j] = zbx_snmp_set_result(var, items[j].value_type, items[j].data_type,
						&results[j]);
			}
		}
	}
	else if (STAT_SUCCESS == status && SNMP_ERR_NOSUCHNAME == response->errstat && 0 != response->errindex &&
			ITEM_TYPE_SNMPv1 == items[0].type)
	{
		/* If a request PDU contains a bad variable, the specified behavior is different between SNMPv1 and */
		/* later versions. In SNMPv1, the whole PDU is rejected and "response->errindex" is set to indicate */
		/* the bad variable. In SNMPv2 and later, the SNMP agent processes the PDU by filling values for the */
		/* known variables and marking unknown variables individually in the variable binding list. So if we */
		/* get this error with SNMPv1, we fix the PDU by removing the bad variable and retry the request. */

		i = response->errindex - 1;
		j = mapping[i];

		zabbix_log(LOG_LEVEL_DEBUG, "%s() snmp_synch_response() errindex:%ld oid:'%s'", __function_name,
				response->errindex, oids[j]);

		if (NULL == query_and_ignore_type || 0 == query_and_ignore_type[j])
		{
			errcodes[j] = zbx_get_snmp_response_error(ss, &items[0].interface, status, response, error,
					max_error_len);
			SET_MSG_RESULT(&results[j], zbx_strdup(NULL, error));
			*error = '\0';
		}

		if (1 < mapping_num)
		{
			if (NULL != (pdu = snmp_fix_pdu(response, SNMP_MSG_GET)))
			{
				memmove(mapping + i, mapping + i + 1, sizeof(int) * (mapping_num - i - 1));
				mapping_num--;

				snmp_free_pdu(response);
				goto retry;
			}
			else
			{
				strlcpy(error, "snmp_fix_pdu(): cannot fix PDU object.", max_error_len);
				ret = NOTSUPPORTED;
			}
		}
	}
	else if (1 < mapping_num &&
			((STAT_SUCCESS == status && SNMP_ERR_TOOBIG == response->errstat) || STAT_TIMEOUT == status))
	{
		/* Since we are trying to obtain multiple values from the SNMP agent, the response that it has to  */
		/* generate might be too big. It seems to be required by the SNMP standard that in such cases the  */
		/* error status should be set to "tooBig(1)". However, some devices simply do not respond to such  */
		/* queries and we get a timeout. Moreover, some devices exhibit both behaviors - they either send  */
		/* "tooBig(1)" or do not respond at all. So what we do is halve the number of variables to query - */
		/* it should work in the vast majority of cases, because, since we are now querying "num" values,  */
		/* we know that querying "num/2" values succeeded previously. The case where it can still fail due */
		/* to exceeded maximum response size is if we are now querying values that are unusually large. So */
		/* if querying with half the number of the last values does not work either, we resort to querying */
		/* values one by one, and the next time configuration cache gives us items to query, it will give  */
		/* us less. */

		if (*min_fail > mapping_num)
			*min_fail = mapping_num;

		if (0 == level)
		{
			/* halve the number of items */

			int	base;

			ret = zbx_snmp_get_values(ss, items, oids, results, errcodes, query_and_ignore_type, num / 2,
					level + 1, error, max_error_len, max_succeed, min_fail);

			if (SUCCEED != ret)
				goto exit;

			base = num / 2;

			ret = zbx_snmp_get_values(ss, items + base, oids + base, results + base, errcodes + base,
					NULL == query_and_ignore_type ? NULL : query_and_ignore_type + base, num - base,
					level + 1, error, max_error_len, max_succeed, min_fail);
		}
		else if (1 == level)
		{
			/* resort to querying items one by one */

			for (i = 0; i < num; i++)
			{
				if (SUCCEED != errcodes[i])
					continue;

				ret = zbx_snmp_get_values(ss, items + i, oids + i, results + i, errcodes + i,
						NULL == query_and_ignore_type ? NULL : query_and_ignore_type + i, 1,
						level + 1, error, max_error_len, max_succeed, min_fail);

				if (SUCCEED != ret)
					goto exit;
			}
		}
	}
	else
		ret = zbx_get_snmp_response_error(ss, &items[0].interface, status, response, error, max_error_len);
exit:
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

static int	zbx_snmp_process_discovery(struct snmp_session *ss, const DC_ITEM *items, AGENT_RESULT *results,
		int *errcodes, int num, char *error, int max_error_len, int *max_succeed, int *min_fail, int max_vars)
{
	const char	*__function_name = "zbx_snmp_process_discovery";

	int		ret;
	char		oid_translated[ITEM_SNMP_OID_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	assert(1 == num);

	switch (num_key_param(items[0].snmp_oid))
	{
		case 0:
			zbx_snmp_translate(oid_translated, items[0].snmp_oid, sizeof(oid_translated));
			errcodes[0] = zbx_snmp_walk(ss, &items[0], oid_translated, ZBX_SNMP_WALK_MODE_DISCOVERY,
					&results[0], max_succeed, min_fail, max_vars);
			break;
		default:
			SET_MSG_RESULT(&results[0], zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
					items[0].snmp_oid));
			errcodes[0] = CONFIG_ERROR;
	}

	if (SUCCEED != (ret = errcodes[0]))
		zbx_strlcpy(error, results[0].msg, max_error_len);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_process_dynamic(struct snmp_session *ss, const DC_ITEM *items, AGENT_RESULT *results,
		int *errcodes, int num, char *error, int max_error_len, int *max_succeed, int *min_fail)
{
	const char	*__function_name = "zbx_snmp_process_dynamic";

	int		i, j, k, ret;
	int		to_walk[MAX_SNMP_ITEMS], to_walk_num = 0;
	int		to_verify[MAX_SNMP_ITEMS], to_verify_num = 0;
	char		to_verify_oids[MAX_SNMP_ITEMS][ITEM_SNMP_OID_LEN_MAX];
	unsigned char	query_and_ignore_type[MAX_SNMP_ITEMS];
	char		index_oids[MAX_SNMP_ITEMS][ITEM_SNMP_OID_LEN_MAX];
	char		index_values[MAX_SNMP_ITEMS][ITEM_SNMP_OID_LEN_MAX];
	char		oids_translated[MAX_SNMP_ITEMS][ITEM_SNMP_OID_LEN_MAX];
	char		*idx = NULL, *pl;
	size_t		idx_alloc = 32;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	idx = zbx_malloc(idx, idx_alloc);

	/* perform initial item validation */

	for (i = 0; i < num; i++)
	{
		char	method[8];

		if (SUCCEED != errcodes[i])
			continue;

		if (3 != num_key_param(items[i].snmp_oid))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
					items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		get_key_param(items[i].snmp_oid, 1, method, sizeof(method));
		get_key_param(items[i].snmp_oid, 2, index_oids[i], sizeof(index_oids[i]));
		get_key_param(items[i].snmp_oid, 3, index_values[i], sizeof(index_values[i]));

		if (0 != strcmp("index", method))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "Unsupported method \"%s\" in the OID \"%s\".",
					method, items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		zbx_snmp_translate(oids_translated[i], index_oids[i], sizeof(oids_translated[i]));

		if (SUCCEED == cache_get_snmp_index(&items[i], oids_translated[i], index_values[i], &idx, &idx_alloc))
		{
			zbx_snprintf(to_verify_oids[i], sizeof(to_verify_oids[i]), "%s.%s", oids_translated[i], idx);

			to_verify[to_verify_num++] = i;
			query_and_ignore_type[i] = 1;
		}
		else
		{
			to_walk[to_walk_num++] = i;
			query_and_ignore_type[i] = 0;
		}
	}

	/* verify that cached indices are still valid */

	if (0 != to_verify_num)
	{
		ret = zbx_snmp_get_values(ss, items, to_verify_oids, results, errcodes, query_and_ignore_type, num, 0,
				error, max_error_len, max_succeed, min_fail);

		if (SUCCEED != ret)
			goto exit;

		for (i = 0; i < to_verify_num; i++)
		{
			j = to_verify[i];

			if (SUCCEED != errcodes[j])
				continue;

			if (NULL == GET_STR_RESULT(&results[j]) || 0 != strcmp(results[j].str, index_values[j]))
			{
				to_walk[to_walk_num++] = j;
			}
			else
			{
				/* ready to construct the final OID with index */

				size_t	len;

				len = strlen(oids_translated[j]);

				pl = strchr(items[j].snmp_oid, '[');

				*pl = '\0';
				zbx_snmp_translate(oids_translated[j], items[j].snmp_oid, sizeof(oids_translated[j]));
				*pl = '[';

				zbx_strlcat(oids_translated[j], to_verify_oids[j] + len, sizeof(oids_translated[j]));
			}

			free_result(&results[j]);
		}
	}

	/* walk OID trees to build index cache for cache misses */

	if (0 != to_walk_num)
	{
		for (i = 0; i < to_walk_num; i++)
		{
			int		errcode;
			AGENT_RESULT	result;

			j = to_walk[i];

			/* see whether this OID tree was already walked for another item */

			for (k = 0; k < i; k++)
			{
				if (0 == strcmp(oids_translated[to_walk[k]], oids_translated[j]))
					break;
			}

			if (k != i)
				continue;

			/* walk */

			init_result(&result);

			errcode = zbx_snmp_walk(ss, &items[j], oids_translated[j], ZBX_SNMP_WALK_MODE_CACHE, &result,
					max_succeed, min_fail, num);

			if (NETWORK_ERROR == errcode)
			{
				/* consider a network error as relating to all items passed to */
				/* this function, including those we did not just try to walk for */

				strlcpy(error, result.msg, max_error_len);
				ret = NETWORK_ERROR;

				free_result(&result);
				goto exit;
			}

			if (CONFIG_ERROR == errcode || NOTSUPPORTED == errcode)
			{
				/* consider a configuration or "not supported" error as */
				/* relating only to the items we have just tried to walk for */

				for (k = i; k < to_walk_num; k++)
				{
					if (0 == strcmp(oids_translated[to_walk[k]], oids_translated[j]))
					{
						SET_MSG_RESULT(&results[to_walk[k]], zbx_strdup(NULL, result.msg));
						errcodes[to_walk[k]] = errcode;
					}
				}
			}

			free_result(&result);
		}

		for (i = 0; i < to_walk_num; i++)
		{
			j = to_walk[i];

			if (SUCCEED != errcodes[j])
				continue;

			if (SUCCEED == cache_get_snmp_index(&items[j], oids_translated[j], index_values[j], &idx,
						&idx_alloc))
			{
				/* ready to construct the final OID with index */

				pl = strchr(items[j].snmp_oid, '[');

				*pl = '\0';
				zbx_snmp_translate(oids_translated[j], items[j].snmp_oid, sizeof(oids_translated[j]));
				*pl = '[';

				zbx_strlcat(oids_translated[j], ".", sizeof(oids_translated[j]));
				zbx_strlcat(oids_translated[j], idx, sizeof(oids_translated[j]));
			}
			else
			{
				SET_MSG_RESULT(&results[j], zbx_dsprintf(NULL,
						"Cannot find index of \"%s\" in \"%s\".",
						index_values[j], index_oids[j]));
				errcodes[j] = NOTSUPPORTED;
			}
		}
	}

	/* query values based on the indices verified and/or determined above */

	ret = zbx_snmp_get_values(ss, items, oids_translated, results, errcodes, NULL, num, 0, error, max_error_len,
			max_succeed, min_fail);
exit:
	zbx_free(idx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	zbx_snmp_process_standard(struct snmp_session *ss, const DC_ITEM *items, AGENT_RESULT *results,
		int *errcodes, int num, char *error, int max_error_len, int *max_succeed, int *min_fail)
{
	const char	*__function_name = "zbx_snmp_process_standard";

	int		i, ret;
	char		oids_translated[MAX_SNMP_ITEMS][ITEM_SNMP_OID_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (0 != num_key_param(items[i].snmp_oid))
		{
			SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "OID \"%s\" contains unsupported parameters.",
					items[i].snmp_oid));
			errcodes[i] = CONFIG_ERROR;
			continue;
		}

		zbx_snmp_translate(oids_translated[i], items[i].snmp_oid, sizeof(oids_translated[i]));
	}

	ret = zbx_snmp_get_values(ss, items, oids_translated, results, errcodes, NULL, num, 0, error, max_error_len,
			max_succeed, min_fail);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	get_value_snmp(const DC_ITEM *item, AGENT_RESULT *result)
{
	int	errcode = SUCCEED;

	get_values_snmp(item, result, &errcode, 1);

	return errcode;
}

void	get_values_snmp(const DC_ITEM *items, AGENT_RESULT *results, int *errcodes, int num)
{
	const char		*__function_name = "get_values_snmp";

	struct snmp_session	*ss;
	char			error[MAX_STRING_LEN];
	int			i, j, err = SUCCEED, max_succeed = 0, min_fail = MAX_SNMP_ITEMS + 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' num:%d",
			__function_name, items[0].host.host, items[0].interface.addr, num);

	for (j = 0; j < num; j++)	/* locate first supported item to use as a reference */
	{
		if (SUCCEED == errcodes[j])
			break;
	}

	if (j == num)	/* all items already NOTSUPPORTED (with invalid key, port or SNMP parameters) */
		goto out;

	if (NULL == (ss = zbx_snmp_open_session(&items[j], error, sizeof(error))))
	{
		err = NETWORK_ERROR;
		goto exit;
	}

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & items[j].flags))
	{
		int	max_vars;

		if (SUCCEED == DCconfig_get_interface_snmp_stats(items[j].interface.interfaceid,
				&max_succeed, &min_fail))
		{
			max_vars = DCconfig_get_suggested_snmp_vars(max_succeed, min_fail);
		}
		else
			max_vars = 1;

		err = zbx_snmp_process_discovery(ss, items + j, results + j, errcodes + j, num - j, error, sizeof(error),
				&max_succeed, &min_fail, max_vars);
	}
	else if (NULL != strchr(items[j].snmp_oid, '['))
	{
		err = zbx_snmp_process_dynamic(ss, items + j, results + j, errcodes + j, num - j, error, sizeof(error),
				&max_succeed, &min_fail);
	}
	else
	{
		err = zbx_snmp_process_standard(ss, items + j, results + j, errcodes + j, num - j, error, sizeof(error),
				&max_succeed, &min_fail);
	}

	zbx_snmp_close_session(ss);
exit:
	if (SUCCEED != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "getting SNMP values failed: %s", error);

		for (i = j; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
			errcodes[i] = err;
		}
	}
	else if (0 != max_succeed || MAX_SNMP_ITEMS + 1 != min_fail)
	{
		DCconfig_update_interface_snmp_stats(items[j].interface.interfaceid, max_succeed, min_fail);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#endif	/* HAVE_SNMP */
