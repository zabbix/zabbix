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

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

use DOMDocument;
use Exception;

/**
 * Configuration of the OneLogin PHP Toolkit
 */
class Settings
{
    /**
     * List of paths.
     *
     * @var array
     */
    private $_paths = array();

    /**
     * @var string
     */
    private $_baseurl;

    /**
     * Strict. If active, PHP Toolkit will reject unsigned or unencrypted messages
     * if it expects them signed or encrypted. If not, the messages will be accepted
     * and some security issues will be also relaxed.
     *
     * @var bool
     */
    private $_strict = true;

    /**
     * Activate debug mode
     *
     * @var bool
     */
    private $_debug = false;

    /**
     * SP data.
     *
     * @var array
     */
    private $_sp = array();

    /**
     * IdP data.
     *
     * @var array
     */
    private $_idp = array();

    /**
     * Compression settings that determine
     * whether gzip compression should be used.
     *
     * @var array
     */
    private $_compress = array();

    /**
     * Security Info related to the SP.
     *
     * @var array
     */
    private $_security = array();

    /**
     * Setting contacts.
     *
     * @var array
     */
    private $_contacts = array();

    /**
     * Setting organization.
     *
     * @var array
     */
    private $_organization = array();

    /**
     * Setting errors.
     *
     * @var array
     */
    private $_errors = array();

    /**
     * Valitate SP data only flag
     *
     * @var bool
     */
    private $_spValidationOnly = false;

    /**
     * Initializes the settings:
     * - Sets the paths of the different folders
     * - Loads settings info from settings file or array/object provided
     *
     * @param array|null $settings         SAML Toolkit Settings
     * @param bool       $spValidationOnly Validate or not the IdP data
     *
     * @throws Error If any settings parameter is invalid
     * @throws Exception If Settings is incorrectly supplied
     */
    public function __construct(array $settings = null, $spValidationOnly = false)
    {
        $this->_spValidationOnly = $spValidationOnly;
        $this->_loadPaths();

        if (!isset($settings)) {
            if (!$this->_loadSettingsFromFile()) {
                throw new Error(
                    'Invalid file settings: %s',
                    Error::SETTINGS_INVALID,
                    array(implode(', ', $this->_errors))
                );
            }
            $this->_addDefaultValues();
        } else {
            if (!$this->_loadSettingsFromArray($settings)) {
                throw new Error(
                    'Invalid array settings: %s',
                    Error::SETTINGS_INVALID,
                    array(implode(', ', $this->_errors))
                );
            }
        }

        $this->formatIdPCert();
        $this->formatSPCert();
        $this->formatSPKey();
        $this->formatSPCertNew();
        $this->formatIdPCertMulti();
    }

    /**
     * Sets the paths of the different folders
     * @suppress PhanUndeclaredConstant
     */
    private function _loadPaths()
    {
        $basePath = dirname(dirname(__DIR__)) . '/';
        $this->_paths = array(
            'base' => $basePath,
            'config' => $basePath,
            'cert' => $basePath.'certs/',
            'lib' => __DIR__ . '/',
        );

        if (defined('ONELOGIN_CUSTOMPATH')) {
            $this->_paths['config'] = ONELOGIN_CUSTOMPATH;
            $this->_paths['cert'] = ONELOGIN_CUSTOMPATH . 'certs/';
        }
    }

    /**
     * Returns base path.
     *
     * @return string  The base toolkit folder path
     */
    public function getBasePath()
    {
        return $this->_paths['base'];
    }

    /**
     * Returns cert path.
     *
     * @return string The cert folder path
     */
    public function getCertPath()
    {
        return $this->_paths['cert'];
    }

    /**
     * Returns config path.
     *
     * @return string The config folder path
     */
    public function getConfigPath()
    {
        return $this->_paths['config'];
    }

    /**
     * Returns lib path.
     *
     * @return string The library folder path
     */
    public function getLibPath()
    {
        return $this->_paths['lib'];
    }

    /**
     * Returns schema path.
     *
     * @return string  The external library folder path
     */
    public function getSchemasPath()
    {
        if (isset($this->_paths['schemas'])) {
            return $this->_paths['schemas'];
        }
        return __DIR__ . '/schemas/';
    }

    /**
     * Set schemas path
     *
     * @param string $path
     * @return $this
     */
    public function setSchemasPath($path)
    {
        $this->_paths['schemas'] = $path;
    }

    /**
     * Loads settings info from a settings Array
     *
     * @param array $settings SAML Toolkit Settings
     *
     * @return bool True if the settings info is valid
     */
    private function _loadSettingsFromArray(array $settings)
    {
        if (isset($settings['sp'])) {
            $this->_sp = $settings['sp'];
        }
        if (isset($settings['idp'])) {
            $this->_idp = $settings['idp'];
        }

        $errors = $this->checkSettings($settings);
        if (empty($errors)) {
            $this->_errors = array();

            if (isset($settings['strict'])) {
                $this->_strict = $settings['strict'];
            }
            if (isset($settings['debug'])) {
                $this->_debug = $settings['debug'];
            }

            if (isset($settings['baseurl'])) {
                $this->_baseurl = $settings['baseurl'];
            }

            if (isset($settings['compress'])) {
                $this->_compress = $settings['compress'];
            }

            if (isset($settings['security'])) {
                $this->_security = $settings['security'];
            }

            if (isset($settings['contactPerson'])) {
                $this->_contacts = $settings['contactPerson'];
            }

            if (isset($settings['organization'])) {
                $this->_organization = $settings['organization'];
            }

            $this->_addDefaultValues();
            return true;
        } else {
            $this->_errors = $errors;
            return false;
        }
    }

    /**
     * Loads settings info from the settings file
     *
     * @return bool True if the settings info is valid
     *
     * @throws Error
     *
     * @suppress PhanUndeclaredVariable
     */
    private function _loadSettingsFromFile()
    {
        $filename = $this->getConfigPath().'settings.php';

        if (!file_exists($filename)) {
            throw new Error(
                'Settings file not found: %s',
                Error::SETTINGS_FILE_NOT_FOUND,
                array($filename)
            );
        }

        /** @var array $settings */
        include $filename;

        // Add advance_settings if exists
        $advancedFilename = $this->getConfigPath().'advanced_settings.php';

        if (file_exists($advancedFilename)) {
            /** @var array $advancedSettings */
            include $advancedFilename;
            $settings = array_merge($settings, $advancedSettings);
        }


        return $this->_loadSettingsFromArray($settings);
    }

    /**
     * Add default values if the settings info is not complete
     */
    private function _addDefaultValues()
    {
        if (!isset($this->_sp['assertionConsumerService']['binding'])) {
            $this->_sp['assertionConsumerService']['binding'] = Constants::BINDING_HTTP_POST;
        }
        if (isset($this->_sp['singleLogoutService']) && !isset($this->_sp['singleLogoutService']['binding'])) {
            $this->_sp['singleLogoutService']['binding'] = Constants::BINDING_HTTP_REDIRECT;
        }

        if (!isset($this->_compress['requests'])) {
            $this->_compress['requests'] = true;
        }

        if (!isset($this->_compress['responses'])) {
            $this->_compress['responses'] = true;
        }

        // Related to nameID
        if (!isset($this->_sp['NameIDFormat'])) {
            $this->_sp['NameIDFormat'] = Constants::NAMEID_UNSPECIFIED;
        }
        if (!isset($this->_security['nameIdEncrypted'])) {
            $this->_security['nameIdEncrypted'] = false;
        }
        if (!isset($this->_security['requestedAuthnContext'])) {
            $this->_security['requestedAuthnContext'] = true;
        }

        // sign provided
        if (!isset($this->_security['authnRequestsSigned'])) {
            $this->_security['authnRequestsSigned'] = false;
        }
        if (!isset($this->_security['logoutRequestSigned'])) {
            $this->_security['logoutRequestSigned'] = false;
        }
        if (!isset($this->_security['logoutResponseSigned'])) {
            $this->_security['logoutResponseSigned'] = false;
        }
        if (!isset($this->_security['signMetadata'])) {
            $this->_security['signMetadata'] = false;
        }

        // sign expected
        if (!isset($this->_security['wantMessagesSigned'])) {
            $this->_security['wantMessagesSigned'] = false;
        }
        if (!isset($this->_security['wantAssertionsSigned'])) {
            $this->_security['wantAssertionsSigned'] = false;
        }

        // NameID element expected
        if (!isset($this->_security['wantNameId'])) {
            $this->_security['wantNameId'] = true;
        }

        // Relax Destination validation
        if (!isset($this->_security['relaxDestinationValidation'])) {
            $this->_security['relaxDestinationValidation'] = false;
        }

        // Strict Destination match validation
        if (!isset($this->_security['destinationStrictlyMatches'])) {
            $this->_security['destinationStrictlyMatches'] = false;
        }

        // Allow duplicated Attribute Names
        if (!isset($this->_security['allowRepeatAttributeName'])) {
            $this->_security['allowRepeatAttributeName'] = false;
        }

        // InResponseTo
        if (!isset($this->_security['rejectUnsolicitedResponsesWithInResponseTo'])) {
            $this->_security['rejectUnsolicitedResponsesWithInResponseTo'] = false;
        }

        // encrypt expected
        if (!isset($this->_security['wantAssertionsEncrypted'])) {
            $this->_security['wantAssertionsEncrypted'] = false;
        }
        if (!isset($this->_security['wantNameIdEncrypted'])) {
            $this->_security['wantNameIdEncrypted'] = false;
        }

        // XML validation
        if (!isset($this->_security['wantXMLValidation'])) {
            $this->_security['wantXMLValidation'] = true;
        }

        // SignatureAlgorithm
        if (!isset($this->_security['signatureAlgorithm'])) {
            $this->_security['signatureAlgorithm'] = XMLSecurityKey::RSA_SHA256;
        }

        // DigestAlgorithm
        if (!isset($this->_security['digestAlgorithm'])) {
            $this->_security['digestAlgorithm'] = XMLSecurityDSig::SHA256;
        }

        // EncryptionAlgorithm
        if (!isset($this->_security['encryption_algorithm'])) {
            $this->_security['encryption_algorithm'] = XMLSecurityKey::AES128_CBC;
        }

        if (!isset($this->_security['lowercaseUrlencoding'])) {
            $this->_security['lowercaseUrlencoding'] = false;
        }

        // Certificates / Private key /Fingerprint
        if (!isset($this->_idp['x509cert'])) {
            $this->_idp['x509cert'] = '';
        }
        if (!isset($this->_idp['certFingerprint'])) {
            $this->_idp['certFingerprint'] = '';
        }
        if (!isset($this->_idp['certFingerprintAlgorithm'])) {
            $this->_idp['certFingerprintAlgorithm'] = 'sha1';
        }

        if (!isset($this->_sp['x509cert'])) {
            $this->_sp['x509cert'] = '';
        }
        if (!isset($this->_sp['privateKey'])) {
            $this->_sp['privateKey'] = '';
        }
    }

    /**
     * Checks the settings info.
     *
     * @param array $settings Array with settings data
     *
     * @return array $errors  Errors found on the settings data
     */
    public function checkSettings(array $settings)
    {
        if (empty($settings)) {
            $errors = array('invalid_syntax');
        } else {
            $errors = array();
            if (!$this->_spValidationOnly) {
                $idpErrors = $this->checkIdPSettings($settings);
                $errors = array_merge($idpErrors, $errors);
            }
            $spErrors = $this->checkSPSettings($settings);
            $errors = array_merge($spErrors, $errors);

            $compressErrors = $this->checkCompressionSettings($settings);
            $errors = array_merge($compressErrors, $errors);
        }

        return $errors;
    }

    /**
     * Checks the compression settings info.
     *
     * @param array $settings Array with settings data
     *
     * @return array $errors  Errors found on the settings data
     */
    public function checkCompressionSettings($settings)
    {
        $errors = array();

        if (isset($settings['compress'])) {
            if (!is_array($settings['compress'])) {
                $errors[] = "invalid_syntax";
            } else if (isset($settings['compress']['requests'])
                && $settings['compress']['requests'] !== true
                && $settings['compress']['requests'] !== false
            ) {
                $errors[] = "'compress'=>'requests' values must be true or false.";
            } else if (isset($settings['compress']['responses'])
                && $settings['compress']['responses'] !== true
                && $settings['compress']['responses'] !== false
            ) {
                $errors[] = "'compress'=>'responses' values must be true or false.";
            }
        }
        return $errors;
    }

    /**
     * Checks the IdP settings info.
     *
     * @param array $settings Array with settings data
     *
     * @return array $errors  Errors found on the IdP settings data
     */
    public function checkIdPSettings(array $settings)
    {
        if (empty($settings)) {
            return array('invalid_syntax');
        }

        $errors = array();

        if (!isset($settings['idp']) || empty($settings['idp'])) {
            $errors[] = 'idp_not_found';
        } else {
            $idp = $settings['idp'];
            if (!isset($idp['entityId']) || empty($idp['entityId'])) {
                $errors[] = 'idp_entityId_not_found';
            }

            if (!isset($idp['singleSignOnService'])
                || !isset($idp['singleSignOnService']['url'])
                || empty($idp['singleSignOnService']['url'])
            ) {
                $errors[] = 'idp_sso_not_found';
            } else if (!filter_var($idp['singleSignOnService']['url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'idp_sso_url_invalid';
            }

            if (isset($idp['singleLogoutService'])
                && isset($idp['singleLogoutService']['url'])
                && !empty($idp['singleLogoutService']['url'])
                && !filter_var($idp['singleLogoutService']['url'], FILTER_VALIDATE_URL)
            ) {
                $errors[] = 'idp_slo_url_invalid';
            }

            if (isset($idp['singleLogoutService'])
                && isset($idp['singleLogoutService']['responseUrl'])
                && !empty($idp['singleLogoutService']['responseUrl'])
                && !filter_var($idp['singleLogoutService']['responseUrl'], FILTER_VALIDATE_URL)
            ) {
                $errors[] = 'idp_slo_response_url_invalid';
            }

            $existsX509 = isset($idp['x509cert']) && !empty($idp['x509cert']);
            $existsMultiX509Sign = isset($idp['x509certMulti']) && isset($idp['x509certMulti']['signing']) && !empty($idp['x509certMulti']['signing']);
            $existsFingerprint = isset($idp['certFingerprint']) && !empty($idp['certFingerprint']);
            if (!($existsX509 || $existsFingerprint || $existsMultiX509Sign)
            ) {
                $errors[] = 'idp_cert_or_fingerprint_not_found_and_required';
            }

            if (isset($settings['security'])) {
                $existsMultiX509Enc = isset($idp['x509certMulti']) && isset($idp['x509certMulti']['encryption']) && !empty($idp['x509certMulti']['encryption']);

                if ((isset($settings['security']['nameIdEncrypted']) && $settings['security']['nameIdEncrypted'] == true)
                    && !($existsX509 || $existsMultiX509Enc)
                ) {
                    $errors[] = 'idp_cert_not_found_and_required';
                }
            }
        }

        return $errors;
    }

    /**
     * Checks the SP settings info.
     *
     * @param array $settings Array with settings data
     *
     * @return array $errors  Errors found on the SP settings data
     */
    public function checkSPSettings(array $settings)
    {
        if (empty($settings)) {
            return array('invalid_syntax');
        }

        $errors = array();

        if (!isset($settings['sp']) || empty($settings['sp'])) {
            $errors[] = 'sp_not_found';
        } else {
            $sp = $settings['sp'];
            $security = array();
            if (isset($settings['security'])) {
                $security = $settings['security'];
            }

            if (!isset($sp['entityId']) || empty($sp['entityId'])) {
                $errors[] = 'sp_entityId_not_found';
            }

            if (!isset($sp['assertionConsumerService'])
                || !isset($sp['assertionConsumerService']['url'])
                || empty($sp['assertionConsumerService']['url'])
            ) {
                $errors[] = 'sp_acs_not_found';
            } else if (!filter_var($sp['assertionConsumerService']['url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'sp_acs_url_invalid';
            }

            if (isset($sp['singleLogoutService'])
                && isset($sp['singleLogoutService']['url'])
                && !filter_var($sp['singleLogoutService']['url'], FILTER_VALIDATE_URL)
            ) {
                $errors[] = 'sp_sls_url_invalid';
            }

            if (isset($security['signMetadata']) && is_array($security['signMetadata'])) {
                if ((!isset($security['signMetadata']['keyFileName'])
                    || !isset($security['signMetadata']['certFileName'])) &&
                    (!isset($security['signMetadata']['privateKey'])
                    || !isset($security['signMetadata']['x509cert']))
                ) {
                    $errors[] = 'sp_signMetadata_invalid';
                }
            }

            if (((isset($security['authnRequestsSigned']) && $security['authnRequestsSigned'] == true)
                || (isset($security['logoutRequestSigned']) && $security['logoutRequestSigned'] == true)
                || (isset($security['logoutResponseSigned']) && $security['logoutResponseSigned'] == true)
                || (isset($security['wantAssertionsEncrypted']) && $security['wantAssertionsEncrypted'] == true)
                || (isset($security['wantNameIdEncrypted']) && $security['wantNameIdEncrypted'] == true))
                && !$this->checkSPCerts()
            ) {
                $errors[] = 'sp_certs_not_found_and_required';
            }
        }

        if (isset($settings['contactPerson'])) {
            $types = array_keys($settings['contactPerson']);
            $validTypes = array('technical', 'support', 'administrative', 'billing', 'other');
            foreach ($types as $type) {
                if (!in_array($type, $validTypes)) {
                    $errors[] = 'contact_type_invalid';
                    break;
                }
            }

            foreach ($settings['contactPerson'] as $type => $contact) {
                if (!isset($contact['givenName']) || empty($contact['givenName'])
                    || !isset($contact['emailAddress']) || empty($contact['emailAddress'])
                ) {
                    $errors[] = 'contact_not_enought_data';
                    break;
                }
            }
        }

        if (isset($settings['organization'])) {
            foreach ($settings['organization'] as $organization) {
                if (!isset($organization['name']) || empty($organization['name'])
                    || !isset($organization['displayname']) || empty($organization['displayname'])
                    || !isset($organization['url']) || empty($organization['url'])
                ) {
                    $errors[] = 'organization_not_enought_data';
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Checks if the x509 certs of the SP exists and are valid.
     *
     * @return bool
     */
    public function checkSPCerts()
    {
        $key = $this->getSPkey();
        $cert = $this->getSPcert();
        return (!empty($key) && !empty($cert));
    }

    /**
     * Returns the x509 private key of the SP.
     *
     * @return string SP private key
     */
    public function getSPkey()
    {
        $key = null;
        if (isset($this->_sp['privateKey']) && !empty($this->_sp['privateKey'])) {
            $key = $this->_sp['privateKey'];
        } else {
            $keyFile = $this->_paths['cert'].'sp.key';

            if (file_exists($keyFile)) {
                $key = file_get_contents($keyFile);
            }
        }
        return $key;
    }

    /**
     * Returns the x509 public cert of the SP.
     *
     * @return string SP public cert
     */
    public function getSPcert()
    {
        $cert = null;

        if (isset($this->_sp['x509cert']) && !empty($this->_sp['x509cert'])) {
            $cert = $this->_sp['x509cert'];
        } else {
            $certFile = $this->_paths['cert'].'sp.crt';

            if (file_exists($certFile)) {
                $cert = file_get_contents($certFile);
            }
        }
        return $cert;
    }

    /**
     * Returns the x509 public of the SP that is
     * planed to be used soon instead the other
     * public cert
     *
     * @return string SP public cert New
     */
    public function getSPcertNew()
    {
        $cert = null;

        if (isset($this->_sp['x509certNew']) && !empty($this->_sp['x509certNew'])) {
            $cert = $this->_sp['x509certNew'];
        } else {
            $certFile = $this->_paths['cert'].'sp_new.crt';

            if (file_exists($certFile)) {
                $cert = file_get_contents($certFile);
            }
        }
        return $cert;
    }

    /**
     * Gets the IdP data.
     *
     * @return array  IdP info
     */
    public function getIdPData()
    {
        return $this->_idp;
    }

    /**
     * Gets the SP data.
     *
     * @return array  SP info
     */
    public function getSPData()
    {
        return $this->_sp;
    }

    /**
     * Gets security data.
     *
     * @return array  SP info
     */
    public function getSecurityData()
    {
        return $this->_security;
    }

    /**
     * Gets contact data.
     *
     * @return array  SP info
     */
    public function getContacts()
    {
        return $this->_contacts;
    }

    /**
     * Gets organization data.
     *
     * @return array  SP info
     */
    public function getOrganization()
    {
        return $this->_organization;
    }

    /**
     * Should SAML requests be compressed?
     *
     * @return bool Yes/No as True/False
     */
    public function shouldCompressRequests()
    {
        return $this->_compress['requests'];
    }

    /**
     * Should SAML responses be compressed?
     *
     * @return bool Yes/No as True/False
     */
    public function shouldCompressResponses()
    {
        return $this->_compress['responses'];
    }

    /**
     * Gets the IdP SSO url.
     *
     * @return string|null The url of the IdP Single Sign On Service
     */
    public function getIdPSSOUrl()
    {
        $ssoUrl = null;
        if (isset($this->_idp['singleSignOnService']) && isset($this->_idp['singleSignOnService']['url'])) {
            $ssoUrl = $this->_idp['singleSignOnService']['url'];
        }
        return $ssoUrl;
    }

    /**
     * Gets the IdP SLO url.
     *
     * @return string|null The request url of the IdP Single Logout Service
     */
    public function getIdPSLOUrl()
    {
        $sloUrl = null;
        if (isset($this->_idp['singleLogoutService']) && isset($this->_idp['singleLogoutService']['url'])) {
            $sloUrl = $this->_idp['singleLogoutService']['url'];
        }
        return $sloUrl;
    }

    /**
     * Gets the IdP SLO response url.
     *
     * @return string|null The response url of the IdP Single Logout Service
     */
    public function getIdPSLOResponseUrl()
    {
        if (isset($this->_idp['singleLogoutService']) && isset($this->_idp['singleLogoutService']['responseUrl'])) {
            return $this->_idp['singleLogoutService']['responseUrl'];
        }
        return $this->getIdPSLOUrl();
    }

    /**
     * Gets the SP metadata. The XML representation.
     *
     * @param bool $alwaysPublishEncryptionCert When 'true', the returned
     * metadata will always include an 'encryption' KeyDescriptor. Otherwise,
     * the 'encryption' KeyDescriptor will only be included if
     * $advancedSettings['security']['wantNameIdEncrypted'] or
     * $advancedSettings['security']['wantAssertionsEncrypted'] are enabled.
     * @param int|null      $validUntil    Metadata's valid time
     * @param int|null      $cacheDuration Duration of the cache in seconds
     *
     * @return string  SP metadata (xml)
     * @throws Exception
     * @throws Error
     */
    public function getSPMetadata($alwaysPublishEncryptionCert = false, $validUntil = null, $cacheDuration = null)
    {
        $metadata = Metadata::builder($this->_sp, $this->_security['authnRequestsSigned'], $this->_security['wantAssertionsSigned'], $validUntil, $cacheDuration, $this->getContacts(), $this->getOrganization());

        $certNew = $this->getSPcertNew();
        if (!empty($certNew)) {
            $metadata = Metadata::addX509KeyDescriptors(
                $metadata,
                $certNew,
                $alwaysPublishEncryptionCert || $this->_security['wantNameIdEncrypted'] || $this->_security['wantAssertionsEncrypted']
            );
        }

        $cert = $this->getSPcert();
        if (!empty($cert)) {
            $metadata = Metadata::addX509KeyDescriptors(
                $metadata,
                $cert,
                $alwaysPublishEncryptionCert || $this->_security['wantNameIdEncrypted'] || $this->_security['wantAssertionsEncrypted']
            );
        }

        //Sign Metadata
        if (isset($this->_security['signMetadata']) && $this->_security['signMetadata'] != false) {
            if ($this->_security['signMetadata'] === true) {
                $keyMetadata = $this->getSPkey();
                $certMetadata = $cert;

                if (!$keyMetadata) {
                    throw new Error(
                        'SP Private key not found.',
                        Error::PRIVATE_KEY_FILE_NOT_FOUND
                    );
                }

                if (!$certMetadata) {
                    throw new Error(
                        'SP Public cert not found.',
                        Error::PUBLIC_CERT_FILE_NOT_FOUND
                    );
                }
            } else if (isset($this->_security['signMetadata']['keyFileName']) &&
                isset($this->_security['signMetadata']['certFileName'])) {
                $keyFileName = $this->_security['signMetadata']['keyFileName'];
                $certFileName = $this->_security['signMetadata']['certFileName'];

                $keyMetadataFile = $this->_paths['cert'].$keyFileName;
                $certMetadataFile = $this->_paths['cert'].$certFileName;

                if (!file_exists($keyMetadataFile)) {
                    throw new Error(
                        'SP Private key file not found: %s',
                        Error::PRIVATE_KEY_FILE_NOT_FOUND,
                        array($keyMetadataFile)
                    );
                }

                if (!file_exists($certMetadataFile)) {
                    throw new Error(
                        'SP Public cert file not found: %s',
                        Error::PUBLIC_CERT_FILE_NOT_FOUND,
                        array($certMetadataFile)
                    );
                }
                $keyMetadata = file_get_contents($keyMetadataFile);
                $certMetadata = file_get_contents($certMetadataFile);
            } else if (isset($this->_security['signMetadata']['privateKey']) &&
                isset($this->_security['signMetadata']['x509cert'])) {
                $keyMetadata = Utils::formatPrivateKey($this->_security['signMetadata']['privateKey']);
                $certMetadata = Utils::formatCert($this->_security['signMetadata']['x509cert']);
                if (!$keyMetadata) {
                    throw new Error(
                        'Private key not found.',
                        Error::PRIVATE_KEY_FILE_NOT_FOUND
                    );
                }

                if (!$certMetadata) {
                    throw new Error(
                        'Public cert not found.',
                        Error::PUBLIC_CERT_FILE_NOT_FOUND
                    );
                }
            } else {
                throw new Error(
                    'Invalid Setting: signMetadata value of the sp is not valid',
                    Error::SETTINGS_INVALID_SYNTAX
                );

            }

            $signatureAlgorithm = $this->_security['signatureAlgorithm'];
            $digestAlgorithm = $this->_security['digestAlgorithm'];
            $metadata = Metadata::signMetadata($metadata, $keyMetadata, $certMetadata, $signatureAlgorithm, $digestAlgorithm);
        }
        return $metadata;
    }

    /**
     * Validates an XML SP Metadata.
     *
     * @param string $xml Metadata's XML that will be validate
     *
     * @return array The list of found errors
     *
     * @throws Exception
     */
    public function validateMetadata($xml)
    {
        assert(is_string($xml));

        $errors = array();
        $res = Utils::validateXML($xml, 'saml-schema-metadata-2.0.xsd', $this->_debug, $this->getSchemasPath());
        if (!$res instanceof DOMDocument) {
            $errors[] = $res;
        } else {
            $dom = $res;
            $element = $dom->documentElement;
            if ($element->tagName !== 'md:EntityDescriptor') {
                $errors[] = 'noEntityDescriptor_xml';
            } else {
                $validUntil = $cacheDuration = $expireTime = null;

                if ($element->hasAttribute('validUntil')) {
                    $validUntil = Utils::parseSAML2Time($element->getAttribute('validUntil'));
                }
                if ($element->hasAttribute('cacheDuration')) {
                    $cacheDuration = $element->getAttribute('cacheDuration');
                }

                $expireTime = Utils::getExpireTime($cacheDuration, $validUntil);
                if (isset($expireTime) && time() > $expireTime) {
                    $errors[] = 'expired_xml';
                }
            }
        }

        // TODO: Support Metadata Sign Validation

        return $errors;
    }

    /**
     * Formats the IdP cert.
     */
    public function formatIdPCert()
    {
        if (isset($this->_idp['x509cert'])) {
            $this->_idp['x509cert'] = Utils::formatCert($this->_idp['x509cert']);
        }
    }

    /**
     * Formats the Multple IdP certs.
     */
    public function formatIdPCertMulti()
    {
        if (isset($this->_idp['x509certMulti'])) {
            if (isset($this->_idp['x509certMulti']['signing'])) {
                foreach ($this->_idp['x509certMulti']['signing'] as $i => $cert) {
                    $this->_idp['x509certMulti']['signing'][$i] = Utils::formatCert($cert);
                }
            }
            if (isset($this->_idp['x509certMulti']['encryption'])) {
                foreach ($this->_idp['x509certMulti']['encryption'] as $i => $cert) {
                    $this->_idp['x509certMulti']['encryption'][$i] = Utils::formatCert($cert);
                }
            }
        }
    }

    /**
     * Formats the SP cert.
     */
    public function formatSPCert()
    {
        if (isset($this->_sp['x509cert'])) {
            $this->_sp['x509cert'] = Utils::formatCert($this->_sp['x509cert']);
        }
    }

    /**
     * Formats the SP cert.
     */
    public function formatSPCertNew()
    {
        if (isset($this->_sp['x509certNew'])) {
            $this->_sp['x509certNew'] = Utils::formatCert($this->_sp['x509certNew']);
        }
    }

    /**
     * Formats the SP private key.
     */
    public function formatSPKey()
    {
        if (isset($this->_sp['privateKey'])) {
            $this->_sp['privateKey'] = Utils::formatPrivateKey($this->_sp['privateKey']);
        }
    }

    /**
     * Returns an array with the errors, the array is empty when the settings is ok.
     *
     * @return array Errors
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Activates or deactivates the strict mode.
     *
     * @param bool $value Strict parameter
     *
     * @throws Exception
     */
    public function setStrict($value)
    {
        if (!is_bool($value)) {
            throw new Exception('Invalid value passed to setStrict()');
        }

        $this->_strict = $value;
    }

    /**
     * Returns if the 'strict' mode is active.
     *
     * @return bool Strict parameter
     */
    public function isStrict()
    {
        return $this->_strict;
    }

    /**
     * Returns if the debug is active.
     *
     * @return bool Debug parameter
     */
    public function isDebugActive()
    {
        return $this->_debug;
    }

    /**
     * Set a baseurl value.
     *
     * @param string $baseurl Base URL.
     */
    public function setBaseURL($baseurl)
    {
        $this->_baseurl = $baseurl;
    }

    /**
     * Returns the baseurl set on the settings if any.
     *
     * @return null|string The baseurl
     */
    public function getBaseURL()
    {
        return $this->_baseurl;
    }

    /**
     * Sets the IdP certificate.
     *
     * @param string $cert IdP certificate
     */
    public function setIdPCert($cert)
    {
        $this->_idp['x509cert'] = $cert;
        $this->formatIdPCert();
    }
}
