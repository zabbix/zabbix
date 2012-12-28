/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
			return zbx_dsprintf(NULL, "Value has unknown type 0x%02X", type);
	}
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

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
	{
		snmpidx[i].idx = zbx_strdup(snmpidx[i].idx, idx);
		goto end;
	}

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

static void	cache_del_snmp_index(DC_ITEM *item, char *oid, char *value)
{
	const char		*__function_name = "cache_del_snmp_index";
	int			i;
	zbx_snmp_index_t	s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' value:'%s'", __function_name, oid, value);

	if (NULL == snmpidx)
		goto end;

	s.hostid = item->host.hostid;
	s.port = item->interface.port;
	s.oid = oid;
	s.value = value;

	if (snmpidx_count > (i = get_snmpidx_nearestindex(&s)) && 0 == zbx_snmp_index_compare(&s, &snmpidx[i]))
	{
		zbx_free(snmpidx[i].oid);
		zbx_free(snmpidx[i].value);
		zbx_free(snmpidx[i].idx);
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

#ifdef HAVE_IPV6
	if (SUCCEED != get_address_family(item->interface.addr, &family, err, MAX_STRING_LEN))
		goto end;

	if (family == PF_INET)
		zbx_snprintf(addr, sizeof(addr), "%s:%d", item->interface.addr, (int)item->interface.port);
	else
	{
		if (item->interface.useip)
			zbx_snprintf(addr, sizeof(addr), "udp6:[%s]:%d", item->interface.addr, (int)item->interface.port);
		else
			zbx_snprintf(addr, sizeof(addr), "udp6:%s:%d", item->interface.addr, (int)item->interface.port);
	}
#else
	zbx_snprintf(addr, sizeof(addr), "%s:%d", item->interface.addr, (int)item->interface.port);
#endif	/* HAVE_IPV6 */
	session.peername = addr;
	session.remote_port = item->interface.port;	/* remote_port is no longer used in latest versions of NET-SNMP */

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

static void	snmp_close_session(struct snmp_session *session)
{
	const char *__function_name = "snmp_close_session";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	snmp_close(session);
	SOCK_CLEANUP;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static char	*snmp_get_octet_string(struct variable_list *vars)
{
	const char	*__function_name = "snmp_get_octet_string";
	static char	buf[MAX_STRING_LEN];
	const char	*hint;
	char		*strval_dyn = NULL, is_hex = 0;
	size_t          offset = 0;
	struct tree     *subtree;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* find the subtree to get display hint */
	subtree = get_tree(vars->name, vars->name_length, get_tree_head());
	hint = subtree ? subtree->hint : NULL;

	/* we will decide if we want the value from vars->val or what snprint_value() returned later */
	if (-1 == snprint_value(buf, sizeof(buf), vars->name, vars->name_length, vars))
		goto end;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() full value:'%s'", __function_name, buf);

	/* decide if it's Hex, offset will be possibly needed later */
	if (0 == strncmp(buf, "Hex-STRING: ", 12))
	{
		is_hex = 1;
		offset = 12;
	}

	/* in case of no hex and no display hint take the value from */
	/* vars->val, it contains unquoted and unescaped string */
	if (0 == is_hex && NULL == hint)
	{
		strval_dyn = zbx_malloc(strval_dyn, vars->val_len + 1);
		memcpy(strval_dyn, vars->val.string, vars->val_len);
		strval_dyn[vars->val_len] = '\0';
	}
	else
	{
		if (0 == is_hex && 0 == strncmp(buf, "STRING: ", 8))
			offset = 8;

		strval_dyn = zbx_strdup(strval_dyn, buf + offset);
	}

	zbx_rtrim(strval_dyn, ZBX_WHITESPACE);
	zbx_ltrim(strval_dyn, ZBX_WHITESPACE);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __function_name, strval_dyn ? strval_dyn : "(null)");

	return strval_dyn;
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
 *             char **idx    - result to be placed here                       *
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
		char **idx, size_t *idx_alloc, char *err, int bulk)
{
	const char		*__function_name = "snmp_get_index";
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN, OID_len = 0;
	char			strval[MAX_STRING_LEN], *strval_dyn, snmp_oid[MAX_STRING_LEN], *error;
	int			status, running, ret = NOTSUPPORTED;
	struct snmp_pdu		*pdu, *response;
	struct variable_list	*vars;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s' value:'%s' bulk:%d", __function_name, OID, value, bulk);

	*err = '\0';

	/* create OID from string */
	snmp_parse_oid(OID, rootOID, &rootOID_len);

	if (NULL != idx)
	{
		snprint_objid(snmp_oid, sizeof(snmp_oid), rootOID, rootOID_len);
		OID_len = strlen(snmp_oid);
	}

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
					zbx_snprintf(err, MAX_STRING_LEN, "NOT FOUND: %s[%s]", OID, value);
					ret = NOTSUPPORTED;
					running = 0;
				}
				else
				{
					/* verify if OIDs are increasing */
					if (SNMP_ENDOFMIBVIEW != vars->type && SNMP_NOSUCHOBJECT != vars->type &&
							SNMP_NOSUCHINSTANCE != vars->type)
					{
						snprint_objid(snmp_oid, sizeof(snmp_oid),
								vars->name, vars->name_length);

						/* not an exception value */
						if (0 <= snmp_oid_compare(anOID, anOID_len, vars->name, vars->name_length))
						{
							zbx_snprintf(err, MAX_STRING_LEN, "OID not increasing.");
							ret = NOTSUPPORTED;
							running = 0;
						}

						if (ASN_OCTET_STR == vars->type)
						{
							if (NULL == (strval_dyn = snmp_get_octet_string(vars)))
							{
								zbx_strlcpy(err, "out of memory", MAX_STRING_LEN);
								ret = NOTSUPPORTED;
								running = 0;
							}
							else
							{
								strscpy(strval, strval_dyn);
								zbx_free(strval_dyn);
							}
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
							error = zbx_get_snmp_type_error(vars->type);
							zabbix_log(LOG_LEVEL_DEBUG, "OID \"%s\": %s", snmp_oid, error);
							zbx_free(error);
						}

						if (0 == strcmp(value, strval))
						{
							size_t	idx_offset = 0;

							if (NULL != idx)
							{
								zbx_strcpy_alloc(idx, idx_alloc, &idx_offset,
										&snmp_oid[OID_len + 1]);
								zabbix_log(LOG_LEVEL_DEBUG, "index found: '%s'", *idx);
							}
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
						ret = NOTSUPPORTED;
						running = 0;
					}
				}
			}
		}
		else
		{
			running = 0;

			if (STAT_SUCCESS == status)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "SNMP error: %s", snmp_errstring(response->errstat));
				ret = NOTSUPPORTED;
			}
			else if (STAT_ERROR == status)
			{
				zbx_snprintf(err, MAX_STRING_LEN, "Could not connect to \"%s:%d\": %s",
						item->interface.addr, (int)item->interface.port,
						snmp_api_errstring(ss->s_snmp_errno));
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
				zbx_snprintf(err, MAX_STRING_LEN, "Timeout while connecting to \"%s:%d\"",
						item->interface.addr, (int)item->interface.port);
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

static int	snmp_set_value(const char *snmp_oid, struct variable_list *vars, DC_ITEM *item, AGENT_RESULT *value)
{
	const char	*__function_name = "snmp_set_value";
	char		*strval_dyn;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ASN_OCTET_STR == vars->type)
	{
		if (NULL == (strval_dyn = snmp_get_octet_string(vars)))
		{
			SET_MSG_RESULT(value, zbx_strdup(NULL, "Cannot receive string value: out of memory"));
			ret = NOTSUPPORTED;
		}
		else
		{
			if (SUCCEED != set_result_type(value, item->value_type, item->data_type, strval_dyn))
				ret = NOTSUPPORTED;

			zbx_free(strval_dyn);
		}
	}
#ifdef OPAQUE_SPECIAL_TYPES
	else if (ASN_UINTEGER == vars->type || ASN_COUNTER == vars->type || ASN_UNSIGNED64 == vars->type ||
			ASN_TIMETICKS == vars->type || ASN_GAUGE == vars->type)
#else
	else if (vars->type == ASN_UINTEGER || vars->type == ASN_COUNTER ||
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
		if (0 > *vars->val.integer)
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
		SET_MSG_RESULT(value, zbx_get_snmp_type_error(vars->type));
		ret = NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: snmp_walk                                                        *
 *                                                                            *
 * Purpose: retrieve information for low-level discovery item                 *
 *                                                                            *
 * Parameters: ss    - [IN] SNMP session handle                               *
 *             item  - [IN] configuration of Zabbix item                      *
 *             OID   - [IN] OID of table with values of interest              *
 *             value - [OUT] result structure                                 *
 *                                                                            *
 * Return value:  NOTSUPPORTED - OID does not exist, any other critical error *
 *                NETWORK_ERROR - recoverable network error                   *
 *                SUCCEED - if function successfully completed                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	snmp_walk(struct snmp_session *ss, DC_ITEM *item, const char *OID, AGENT_RESULT *value)
{
	const char		*__function_name = "snmp_walk";

	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN], rootOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN, rootOID_len = MAX_OID_LEN, OID_len;
	char			snmp_oid[MAX_STRING_LEN];
	struct variable_list	*vars;
	int			status, running, ret = SUCCEED;
	struct zbx_json		j;
	AGENT_RESULT		snmp_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s'", __function_name, OID);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	/* create OID from string */
	snmp_parse_oid(OID, rootOID, &rootOID_len);

	snprint_objid(snmp_oid, sizeof(snmp_oid), rootOID, rootOID_len);
	OID_len = strlen(snmp_oid);

	/* copy rootOID to anOID */
	memcpy(anOID, rootOID, rootOID_len * sizeof(oid));
	anOID_len = rootOID_len;

	running = 1;
	while (running)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s: snmp_pdu_create()", __function_name);

		pdu = snmp_pdu_create(SNMP_MSG_GETNEXT);	/* create empty PDU */
		snmp_add_null_var(pdu, anOID, anOID_len);	/* add OID as variable to PDU */

		/* communicate with agent */
		status = snmp_synch_response(ss, pdu, &response);

		/* process response */
		if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
		{
			for (vars = response->variables; vars && running; vars = vars->next_variable)
			{
				/* verify if we are in the same subtree */
				if (vars->name_length < rootOID_len ||
						0 != memcmp(rootOID, vars->name, rootOID_len * sizeof(oid)))
				{
					/* not part of this subtree */
					running = 0;
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
							SET_MSG_RESULT(value, strdup("OID not increasing."));
							ret = NOTSUPPORTED;
							running = 0;
							break;
						}

						snprint_objid(snmp_oid, sizeof(snmp_oid),
								vars->name, vars->name_length);

						init_result(&snmp_value);

						if (SUCCEED == snmp_set_value(snmp_oid, vars, item, &snmp_value) &&
								GET_STR_RESULT(&snmp_value))
						{
							zbx_json_addobject(&j, NULL);
							zbx_json_addstring(&j, "{#SNMPINDEX}", &snmp_oid[OID_len + 1],
									ZBX_JSON_TYPE_STRING);
							zbx_json_addstring(&j, "{#SNMPVALUE}", snmp_value.str,
									ZBX_JSON_TYPE_STRING);
							zbx_json_close(&j);
						}

						free_result(&snmp_value);

						/* go to next variable */
						memmove((char *)anOID, (char *)vars->name, vars->name_length * sizeof(oid));
						anOID_len = vars->name_length;
					}
					else
					{
						/* an exception value, so stop */
						zabbix_log(LOG_LEVEL_DEBUG, "%s: Exception value found", __function_name);
						ret = NOTSUPPORTED;
						running = 0;
					}
				}
			}
		}
		else
		{
			if (status == STAT_SUCCESS)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "SNMP error: %s",
						snmp_errstring(response->errstat)));
				ret = NOTSUPPORTED;
			}
			else if (STAT_ERROR == status)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Could not connect to \"%s:%d\": %s",
						item->interface.addr, (int)item->interface.port,
						snmp_api_errstring(ss->s_snmp_errno)));
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
			else if (status == STAT_TIMEOUT)
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Timeout while connecting to \"%s:%d\"",
						item->interface.addr, (int)item->interface.port));
				ret = NETWORK_ERROR;
			}
			else
			{
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "SNMP error [%d]", status));
				ret = NOTSUPPORTED;
			}
			running = 0;
		}

		if (response)
			snmp_free_pdu(response);
	}

	zbx_json_close(&j);

	if (ret == SUCCEED)
		SET_TEXT_RESULT(value, strdup(j.buffer));

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	get_snmp(struct snmp_session *ss, DC_ITEM *item, const char *snmp_oid, AGENT_RESULT *value)
{
	const char		*__function_name = "get_snmp";

	struct snmp_pdu		*pdu, *response;
	oid			anOID[MAX_OID_LEN];
	size_t			anOID_len = MAX_OID_LEN;
	struct variable_list	*vars;
	int			status, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() oid:'%s'", __function_name, snmp_oid);

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
			if (SUCCEED == (ret = snmp_set_value(snmp_oid, vars, item, value)))
				break;
		}
	}
	else
	{
		if (STAT_SUCCESS == status)
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "SNMP error: %s", snmp_errstring(response->errstat)));
			ret = NOTSUPPORTED;
		}
		else if (STAT_ERROR == status)
		{
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Could not connect to \"%s:%d\": %s",
					item->interface.addr, (int)item->interface.port,
					snmp_api_errstring(ss->s_snmp_errno)));
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
			SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Timeout while connecting to \"%s:%d\"",
					item->interface.addr, (int)item->interface.port));
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
	char			method[8], oid_normalized[MAX_STRING_LEN], oid_index[MAX_STRING_LEN],
				oid_full[MAX_STRING_LEN], index_value[MAX_STRING_LEN], err[MAX_STRING_LEN], *pl;
	int			num, ret = SUCCEED;
	char			*idx = NULL;
	size_t			idx_alloc = 32;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' oid:'%s'", __function_name, item->key_orig, item->snmp_oid);

	assert(item->type == ITEM_TYPE_SNMPv1 || item->type == ITEM_TYPE_SNMPv2c || item->type == ITEM_TYPE_SNMPv3);

	if (NULL == (ss = snmp_open_session(item, err)))
	{
		SET_MSG_RESULT(value, strdup(err));
		ret = NOTSUPPORTED;
		goto out;
	}

	num = num_key_param(item->snmp_oid);

	if (0 != (ZBX_FLAG_DISCOVERY & item->flags))
	{
		switch (num)
		{
			case 0:
				snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
				ret = snmp_walk(ss, item, oid_normalized, value);
				break;
			default:
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID [%s] contains unsupported parameters",
						item->snmp_oid));
				ret = NOTSUPPORTED;
		}
	}
	else
	{
		switch (num)
		{
			case 0:
				zabbix_log(LOG_LEVEL_DEBUG, "Standard processing");
				snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
				ret = get_snmp(ss, item, oid_normalized, value);
				break;
			case 3:
				do
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Special processing");

					if (0 != get_key_param(item->snmp_oid, 1, method, sizeof(method)) ||
						0 != get_key_param(item->snmp_oid, 2, oid_index, MAX_STRING_LEN) ||
						0 != get_key_param(item->snmp_oid, 3, index_value, MAX_STRING_LEN))
					{
						SET_MSG_RESULT(value, zbx_dsprintf(NULL,
								"Cannot retrieve all three parameters from [%s]",
								item->snmp_oid));
						ret = NOTSUPPORTED;
						break;
					}

					zabbix_log(LOG_LEVEL_DEBUG, "%s() method:'%s' oid_index:'%s' index_value:'%s'",
							__function_name, method, oid_index, index_value);

					if (0 != strcmp("index", method))
					{
						SET_MSG_RESULT(value, zbx_dsprintf(NULL,
								"Unsupported method [%s] in the OID [%s]",
								method, item->snmp_oid));
						ret = NOTSUPPORTED;
						break;
					}

					idx = zbx_malloc(idx, idx_alloc);

					snmp_normalize(oid_normalized, oid_index, sizeof(oid_normalized));
					if (SUCCEED == (ret = cache_get_snmp_index(item, oid_normalized, index_value,
							&idx, &idx_alloc)))
					{
						zbx_snprintf(oid_full, sizeof(oid_full), "%s.%s", oid_normalized, idx);
						ret = snmp_get_index(ss, item, oid_full, index_value,
								NULL, NULL, err, 0);
					}

					if (SUCCEED != ret && SUCCEED != (ret = snmp_get_index(ss, item, oid_normalized,
							index_value, &idx, &idx_alloc, err, 1)))
					{
						cache_del_snmp_index(item, oid_normalized, index_value);

						SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Cannot find index [%s]"
								" of the OID [%s]: %s",
								oid_index, item->snmp_oid, err));
						break;
					}

					cache_put_snmp_index(item, oid_normalized, index_value, idx);

					if (NULL == (pl = strchr(item->snmp_oid, '[')))
					{
						SET_MSG_RESULT(value, zbx_dsprintf(NULL, "Cannot find left bracket"
								" in the OID [%s]",
								item->snmp_oid));
						ret = NOTSUPPORTED;
						break;
					}

					*pl = '\0';
					snmp_normalize(oid_normalized, item->snmp_oid, sizeof(oid_normalized));
					*pl = '[';

					zbx_snprintf(oid_full, sizeof(oid_full), "%s.%s", oid_normalized, idx);
					ret = get_snmp(ss, item, oid_full, value);
				}
				while (0);

				zbx_free(idx);

				break;
			default:
				SET_MSG_RESULT(value, zbx_dsprintf(NULL, "OID [%s] contains unsupported parameters",
						item->snmp_oid));
				ret = NOTSUPPORTED;
		}
	}

	snmp_close_session(ss);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif
