<?php
/**
 * This file is part of php-saml.
 *
 * (c) OneLogin Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin
 * @author  OneLogin Inc <saml-info@onelogin.com>
 * @license MIT https://github.com/onelogin/php-saml/blob/master/LICENSE
 * @link    https://github.com/onelogin/php-saml
 */

namespace OneLogin\Saml2;

/**
 * Constants of OneLogin PHP Toolkit
 *
 * Defines all required constants
 */
class Constants
{
    // Value added to the current time in time condition validations
    const ALLOWED_CLOCK_DRIFT = 180;  // 3 min in seconds

    // NameID Formats
    const NAMEID_EMAIL_ADDRESS = 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress';
    const NAMEID_X509_SUBJECT_NAME = 'urn:oasis:names:tc:SAML:1.1:nameid-format:X509SubjectName';
    const NAMEID_WINDOWS_DOMAIN_QUALIFIED_NAME = 'urn:oasis:names:tc:SAML:1.1:nameid-format:WindowsDomainQualifiedName';
    const NAMEID_UNSPECIFIED = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified';
    const NAMEID_KERBEROS   = 'urn:oasis:names:tc:SAML:2.0:nameid-format:kerberos';
    const NAMEID_ENTITY     = 'urn:oasis:names:tc:SAML:2.0:nameid-format:entity';
    const NAMEID_TRANSIENT  = 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';
    const NAMEID_PERSISTENT = 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent';
    const NAMEID_ENCRYPTED = 'urn:oasis:names:tc:SAML:2.0:nameid-format:encrypted';

    // Attribute Name Formats
    const ATTRNAME_FORMAT_UNSPECIFIED = 'urn:oasis:names:tc:SAML:2.0:attrname-format:unspecified';
    const ATTRNAME_FORMAT_URI = 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri';
    const ATTRNAME_FORMAT_BASIC = 'urn:oasis:names:tc:SAML:2.0:attrname-format:basic';

    // Namespaces
    const NS_SAML = 'urn:oasis:names:tc:SAML:2.0:assertion';
    const NS_SAMLP = 'urn:oasis:names:tc:SAML:2.0:protocol';
    const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_MD = 'urn:oasis:names:tc:SAML:2.0:metadata';
    const NS_XS = 'http://www.w3.org/2001/XMLSchema';
    const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const NS_XENC = 'http://www.w3.org/2001/04/xmlenc#';
    const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    // Bindings
    const BINDING_HTTP_POST = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
    const BINDING_HTTP_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
    const BINDING_HTTP_ARTIFACT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
    const BINDING_SOAP = 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP';
    const BINDING_DEFLATE = 'urn:oasis:names:tc:SAML:2.0:bindings:URL-Encoding:DEFLATE';

    // Auth Context Class
    const AC_UNSPECIFIED = 'urn:oasis:names:tc:SAML:2.0:ac:classes:unspecified';
    const AC_PASSWORD = 'urn:oasis:names:tc:SAML:2.0:ac:classes:Password';
    const AC_PASSWORD_PROTECTED = 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport';
    const AC_X509 = 'urn:oasis:names:tc:SAML:2.0:ac:classes:X509';
    const AC_SMARTCARD = 'urn:oasis:names:tc:SAML:2.0:ac:classes:Smartcard';
    const AC_SMARTCARD_PKI = 'urn:oasis:names:tc:SAML:2.0:ac:classes:SmartcardPKI';
    const AC_KERBEROS = 'urn:oasis:names:tc:SAML:2.0:ac:classes:Kerberos';
    const AC_WINDOWS = 'urn:federation:authentication:windows';
    const AC_TLS = 'urn:oasis:names:tc:SAML:2.0:ac:classes:TLSClient';
    const AC_RSATOKEN = 'urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken';

    // Subject Confirmation
    const CM_BEARER = 'urn:oasis:names:tc:SAML:2.0:cm:bearer';
    const CM_HOLDER_KEY = 'urn:oasis:names:tc:SAML:2.0:cm:holder-of-key';
    const CM_SENDER_VOUCHES = 'urn:oasis:names:tc:SAML:2.0:cm:sender-vouches';

    // Status Codes
    const STATUS_SUCCESS = 'urn:oasis:names:tc:SAML:2.0:status:Success';
    const STATUS_REQUESTER = 'urn:oasis:names:tc:SAML:2.0:status:Requester';
    const STATUS_RESPONDER = 'urn:oasis:names:tc:SAML:2.0:status:Responder';
    const STATUS_VERSION_MISMATCH = 'urn:oasis:names:tc:SAML:2.0:status:VersionMismatch';
    const STATUS_NO_PASSIVE = 'urn:oasis:names:tc:SAML:2.0:status:NoPassive';
    const STATUS_PARTIAL_LOGOUT = 'urn:oasis:names:tc:SAML:2.0:status:PartialLogout';
    const STATUS_PROXY_COUNT_EXCEEDED = 'urn:oasis:names:tc:SAML:2.0:status:ProxyCountExceeded';
}
