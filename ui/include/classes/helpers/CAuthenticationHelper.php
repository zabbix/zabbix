<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
class CAuthenticationHelper {

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
	 * Authentication parameters array.
	 *
	 * @var array
	 */
	private static $params = [];

	/**
	 * Load once all parameters of Authentication API object.
	 */
	private static function loadParams() {
		if (!self::$params) {
			self::$params = API::Authentication()->get(['output' => 'extend']);
		}
	}

	/**
	 * Get value by parameter name of Authentication (load parameters if need).
	 *
	 * @param string  $name  Authentication parameter name.
	 *
	 * @return string|null Parameter value. If parameter not exists, return null.
	 */
	public static function get(string $name): ?string {
		self::loadParams();

		return array_key_exists($name, self::$params) ? self::$params[$name] : null;
	}

	/**
	 * Get values of all parameters of Authentication (load parameters if need).
	 *
	 * @return array String array with all values of Authentication parameters in format <parameter name> => <value>.
	 */
	public static function getAll(): array {
		self::loadParams();

		return self::$params;
	}

	/**
	 * Set value by parameter name of Authentication into $params (load parameters if need).
	 *
	 * @param string $name   Authentication parameter name.
	 * @param string $value  Authentication parameter value.
	 */
	public static function set(string $key, string $value): void {
		self::loadParams();

		if (array_key_exists($key, self::$params)) {
			self::$params[$key] = $value;
		}
	}
}
