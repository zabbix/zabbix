<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * A class for accessing once loaded parameters of Authentication API object.
 */
class CAuthenticationHelper extends CConfigGeneralHelper {

	public const AUTHENTICATION_TYPE = 'authentication_type';
	public const HTTP_AUTH_ENABLED = 'http_auth_enabled';
	public const HTTP_CASE_SENSITIVE = 'http_case_sensitive';
	public const HTTP_LOGIN_FORM = 'http_login_form';
	public const HTTP_STRIP_DOMAINS = 'http_strip_domains';
	public const LDAP_BASE_DN = 'ldap_base_dn';
	public const LDAP_BIND_DN = 'ldap_bind_dn';
	public const LDAP_BIND_PASSWORD = 'ldap_bind_password';
	public const LDAP_CASE_SENSITIVE = 'ldap_case_sensitive';
	public const LDAP_CONFIGURED = 'ldap_configured';
	public const LDAP_HOST = 'ldap_host';
	public const LDAP_PORT = 'ldap_port';
	public const LDAP_SEARCH_ATTRIBUTE = 'ldap_search_attribute';
	public const PASSWD_CHECK_RULES = 'passwd_check_rules';
	public const PASSWD_MIN_LENGTH = 'passwd_min_length';
	public const SAML_AUTH_ENABLED = 'saml_auth_enabled';
	public const SAML_CASE_SENSITIVE = 'saml_case_sensitive';
	public const SAML_ENCRYPT_ASSERTIONS = 'saml_encrypt_assertions';
	public const SAML_ENCRYPT_NAMEID = 'saml_encrypt_nameid';
	public const SAML_IDP_ENTITYID = 'saml_idp_entityid';
	public const SAML_NAMEID_FORMAT = 'saml_nameid_format';
	public const SAML_SIGN_ASSERTIONS = 'saml_sign_assertions';
	public const SAML_SIGN_AUTHN_REQUESTS = 'saml_sign_authn_requests';
	public const SAML_SIGN_LOGOUT_REQUESTS = 'saml_sign_logout_requests';
	public const SAML_SIGN_LOGOUT_RESPONSES = 'saml_sign_logout_responses';
	public const SAML_SIGN_MESSAGES = 'saml_sign_messages';
	public const SAML_SLO_URL = 'saml_slo_url';
	public const SAML_SP_ENTITYID = 'saml_sp_entityid';
	public const SAML_SSO_URL = 'saml_sso_url';
	public const SAML_USERNAME_ATTRIBUTE = 'saml_username_attribute';

	/**
	 * Authentication API object parameters array.
	 *
	 * @static
	 *
	 * @var array
	 */
	protected static $params = [];

	/**
	 * @inheritdoc
	 */
	protected static function loadParams(?string $param = null, bool $is_global = false): void {
		if (!self::$params) {
			self::$params = API::Authentication()->get(['output' => 'extend']);

			if (self::$params === false) {
				throw new Exception(_('Unable to load authentication API parameters.'));
			}
		}
	}
}
