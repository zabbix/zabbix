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

use DOMDocument;
use Exception;

/**
 * SAML 2 Logout Request
 */
class LogoutRequest
{
    /**
     * Contains the ID of the Logout Request
     *
     * @var string
     */
    public $id;

    /**
     * Object that represents the setting info
     *
     * @var Settings
     */
    protected $_settings;

    /**
     * SAML Logout Request
     *
     * @var string
     */
    protected $_logoutRequest;

    /**
     * After execute a validation process, this var contains the cause
     *
     * @var Exception
     */
    private $_error;

    /**
     * Constructs the Logout Request object.
     *
     * @param Settings $settings            Settings
     * @param string|null             $request             A UUEncoded Logout Request.
     * @param string|null             $nameId              The NameID that will be set in the LogoutRequest.
     * @param string|null             $sessionIndex        The SessionIndex (taken from the SAML Response in the SSO process).
     * @param string|null             $nameIdFormat        The NameID Format will be set in the LogoutRequest.
     * @param string|null             $nameIdNameQualifier The NameID NameQualifier will be set in the LogoutRequest.
     * @param string|null             $nameIdSPNameQualifier The NameID SP NameQualifier will be set in the LogoutRequest.
     */
    public function __construct(\OneLogin\Saml2\Settings $settings, $request = null, $nameId = null, $sessionIndex = null, $nameIdFormat = null, $nameIdNameQualifier = null, $nameIdSPNameQualifier = null)
    {
        $this->_settings = $settings;

        $baseURL = $this->_settings->getBaseURL();
        if (!empty($baseURL)) {
            Utils::setBaseURL($baseURL);
        }

        if (!isset($request) || empty($request)) {
            $spData = $this->_settings->getSPData();
            $idpData = $this->_settings->getIdPData();
            $security = $this->_settings->getSecurityData();

            $id = Utils::generateUniqueID();
            $this->id = $id;

            $issueInstant = Utils::parseTime2SAML(time());

            $cert = null;
            if (isset($security['nameIdEncrypted']) && $security['nameIdEncrypted']) {
                $existsMultiX509Enc = isset($idpData['x509certMulti']) && isset($idpData['x509certMulti']['encryption']) && !empty($idpData['x509certMulti']['encryption']);

                if ($existsMultiX509Enc) {
                    $cert = $idpData['x509certMulti']['encryption'][0];
                } else {
                    $cert = $idpData['x509cert'];
                }
            }

            if (!empty($nameId)) {
                if (empty($nameIdFormat)
                    && $spData['NameIDFormat'] != Constants::NAMEID_UNSPECIFIED) {
                    $nameIdFormat = $spData['NameIDFormat'];
                }
            } else {
                $nameId = $idpData['entityId'];
                $nameIdFormat = Constants::NAMEID_ENTITY;
            }

            /* From saml-core-2.0-os 8.3.6, when the entity Format is used:
               "The NameQualifier, SPNameQualifier, and SPProvidedID attributes MUST be omitted.
            */
            if (!empty($nameIdFormat) && $nameIdFormat == Constants::NAMEID_ENTITY) {
                $nameIdNameQualifier = null;
                $nameIdSPNameQualifier = null;
            }

            // NameID Format UNSPECIFIED omitted
            if (!empty($nameIdFormat) && $nameIdFormat == Constants::NAMEID_UNSPECIFIED) {
                $nameIdFormat = null;
            }

            $nameIdObj = Utils::generateNameId(
                $nameId,
                $nameIdSPNameQualifier,
                $nameIdFormat,
                $cert,
                $nameIdNameQualifier,
                $security['encryption_algorithm']
            );

            $sessionIndexStr = isset($sessionIndex) ? "<samlp:SessionIndex>{$sessionIndex}</samlp:SessionIndex>" : "";

            $spEntityId = htmlspecialchars($spData['entityId'], ENT_QUOTES);
            $destination = $this->_settings->getIdPSLOUrl();
            $logoutRequest = <<<LOGOUTREQUEST
<samlp:LogoutRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$id}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$destination}">
    <saml:Issuer>{$spEntityId}</saml:Issuer>
    {$nameIdObj}
    {$sessionIndexStr}
</samlp:LogoutRequest>
LOGOUTREQUEST;
        } else {
            $decoded = base64_decode($request);
            // We try to inflate
            $inflated = @gzinflate($decoded);
            if ($inflated != false) {
                $logoutRequest = $inflated;
            } else {
                $logoutRequest = $decoded;
            }
            $this->id = static::getID($logoutRequest);
        }
        $this->_logoutRequest = $logoutRequest;
    }

    /**
     * Returns the Logout Request defated, base64encoded, unsigned
     *
     * @param bool|null $deflate Whether or not we should 'gzdeflate' the request body before we return it.
     *
     * @return string Deflated base64 encoded Logout Request
     */
    public function getRequest($deflate = null)
    {
        $subject = $this->_logoutRequest;

        if (is_null($deflate)) {
            $deflate = $this->_settings->shouldCompressRequests();
        }

        if ($deflate) {
            $subject = gzdeflate($this->_logoutRequest);
        }

        return base64_encode($subject);
    }

    /**
     * Returns the ID of the Logout Request.
     *
     * @param string|DOMDocument $request Logout Request Message
     *
     * @return string ID
     *
     * @throws Error
     */
    public static function getID($request)
    {
        if ($request instanceof DOMDocument) {
            $dom = $request;
        } else {
            $dom = new DOMDocument();
            $dom = Utils::loadXML($dom, $request);
        }


        if (false === $dom) {
            throw new Error(
                "LogoutRequest could not be processed",
                Error::SAML_LOGOUTREQUEST_INVALID
            );
        }

        $id = $dom->documentElement->getAttribute('ID');
        return $id;
    }

    /**
     * Gets the NameID Data of the the Logout Request.
     *
     * @param string|DOMDocument $request Logout Request Message
     * @param string|null        $key     The SP key
     *
     * @return array Name ID Data (Value, Format, NameQualifier, SPNameQualifier)
     *
     * @throws Error
     * @throws Exception
     * @throws ValidationError
     */
    public static function getNameIdData($request, $key = null)
    {
        if ($request instanceof DOMDocument) {
            $dom = $request;
        } else {
            $dom = new DOMDocument();
            $dom = Utils::loadXML($dom, $request);
        }

        $encryptedEntries = Utils::query($dom, '/samlp:LogoutRequest/saml:EncryptedID');

        if ($encryptedEntries->length == 1) {
            $encryptedDataNodes = $encryptedEntries->item(0)->getElementsByTagName('EncryptedData');
            $encryptedData = $encryptedDataNodes->item(0);

            if (empty($key)) {
                throw new Error(
                    "Private Key is required in order to decrypt the NameID, check settings",
                    Error::PRIVATE_KEY_NOT_FOUND
                );
            }

            $seckey = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, array('type'=>'private'));
            $seckey->loadKey($key);

            $nameId = Utils::decryptElement($encryptedData, $seckey);

        } else {
            $entries = Utils::query($dom, '/samlp:LogoutRequest/saml:NameID');
            if ($entries->length == 1) {
                $nameId = $entries->item(0);
            }
        }

        if (!isset($nameId)) {
            throw new ValidationError(
                "NameID not found in the Logout Request",
                ValidationError::NO_NAMEID
            );
        }

        $nameIdData = array();
        $nameIdData['Value'] = $nameId->nodeValue;
        foreach (array('Format', 'SPNameQualifier', 'NameQualifier') as $attr) {
            if ($nameId->hasAttribute($attr)) {
                $nameIdData[$attr] = $nameId->getAttribute($attr);
            }
        }

        return $nameIdData;
    }

    /**
     * Gets the NameID of the Logout Request.
     *
     * @param string|DOMDocument $request Logout Request Message
     * @param string|null        $key     The SP key
     *
     * @return string Name ID Value
     *
     * @throws Error
     * @throws Exception
     * @throws ValidationError
     */
    public static function getNameId($request, $key = null)
    {
        $nameId = self::getNameIdData($request, $key);
        return $nameId['Value'];
    }

    /**
     * Gets the Issuer of the Logout Request.
     *
     * @param string|DOMDocument $request Logout Request Message
     *
     * @return string|null $issuer The Issuer
     *
     * @throws Exception
     */
    public static function getIssuer($request)
    {
        if ($request instanceof DOMDocument) {
            $dom = $request;
        } else {
            $dom = new DOMDocument();
            $dom = Utils::loadXML($dom, $request);
        }

        $issuer = null;
        $issuerNodes = Utils::query($dom, '/samlp:LogoutRequest/saml:Issuer');
        if ($issuerNodes->length == 1) {
            $issuer = $issuerNodes->item(0)->textContent;
        }
        return $issuer;
    }

    /**
     * Gets the SessionIndexes from the Logout Request.
     * Notice: Our Constructor only support 1 SessionIndex but this parser
     *         extracts an array of all the  SessionIndex found on a
     *         Logout Request, that could be many.
     *
     * @param string|DOMDocument $request Logout Request Message
     *
     * @return array The SessionIndex value
     *
     * @throws Exception
     */
    public static function getSessionIndexes($request)
    {
        if ($request instanceof DOMDocument) {
            $dom = $request;
        } else {
            $dom = new DOMDocument();
            $dom = Utils::loadXML($dom, $request);
        }

        $sessionIndexes = array();
        $sessionIndexNodes = Utils::query($dom, '/samlp:LogoutRequest/samlp:SessionIndex');
        foreach ($sessionIndexNodes as $sessionIndexNode) {
            $sessionIndexes[] = $sessionIndexNode->textContent;
        }
        return $sessionIndexes;
    }

    /**
     * Checks if the Logout Request recieved is valid.
     *
     * @param bool $retrieveParametersFromServer True if we want to use parameters from $_SERVER to validate the signature
     *
     * @return bool If the Logout Request is or not valid
     *
     * @throws Exception
     * @throws ValidationError
     */
    public function isValid($retrieveParametersFromServer = false)
    {
        $this->_error = null;
        try {
            $dom = new DOMDocument();
            $dom = Utils::loadXML($dom, $this->_logoutRequest);

            $idpData = $this->_settings->getIdPData();
            $idPEntityId = $idpData['entityId'];

            if ($this->_settings->isStrict()) {
                $security = $this->_settings->getSecurityData();

                if ($security['wantXMLValidation']) {
                    $res = Utils::validateXML($dom, 'saml-schema-protocol-2.0.xsd', $this->_settings->isDebugActive(), $this->_settings->getSchemasPath());
                    if (!$res instanceof DOMDocument) {
                        throw new ValidationError(
                            "Invalid SAML Logout Request. Not match the saml-schema-protocol-2.0.xsd",
                            ValidationError::INVALID_XML_FORMAT
                        );
                    }
                }

                $currentURL = Utils::getSelfRoutedURLNoQuery();

                // Check NotOnOrAfter
                if ($dom->documentElement->hasAttribute('NotOnOrAfter')) {
                    $na = Utils::parseSAML2Time($dom->documentElement->getAttribute('NotOnOrAfter'));
                    if ($na <= time()) {
                        throw new ValidationError(
                            "Could not validate timestamp: expired. Check system clock.",
                            ValidationError::RESPONSE_EXPIRED
                        );
                    }
                }

                // Check destination
                if ($dom->documentElement->hasAttribute('Destination')) {
                    $destination = $dom->documentElement->getAttribute('Destination');
                    if (empty($destination)) {
                        if (!$security['relaxDestinationValidation']) {
                            throw new ValidationError(
                                "The LogoutRequest has an empty Destination value",
                                ValidationError::EMPTY_DESTINATION
                            );
                        }
                    } else {
                        $urlComparisonLength = $security['destinationStrictlyMatches'] ? strlen($destination) : strlen($currentURL);
                        if (strncmp($destination, $currentURL, $urlComparisonLength) !== 0) {
                            $currentURLNoRouted = Utils::getSelfURLNoQuery();
                            $urlComparisonLength = $security['destinationStrictlyMatches'] ? strlen($destination) : strlen($currentURLNoRouted);
                            if (strncmp($destination, $currentURLNoRouted, $urlComparisonLength) !== 0) {
                                throw new ValidationError(
                                    "The LogoutRequest was received at $currentURL instead of $destination",
                                    ValidationError::WRONG_DESTINATION
                                );
                            }
                        }
                    }
                }

                $nameId = static::getNameId($dom, $this->_settings->getSPkey());

                // Check issuer
                $issuer = static::getIssuer($dom);
                if (!empty($issuer) && $issuer != $idPEntityId) {
                    throw new ValidationError(
                        "Invalid issuer in the Logout Request",
                        ValidationError::WRONG_ISSUER
                    );
                }

                if ($security['wantMessagesSigned'] && !isset($_GET['Signature'])) {
                    throw new ValidationError(
                        "The Message of the Logout Request is not signed and the SP require it",
                        ValidationError::NO_SIGNED_MESSAGE
                    );
                }
            }

            if (isset($_GET['Signature'])) {
                $signatureValid = Utils::validateBinarySign("SAMLRequest", $_GET, $idpData, $retrieveParametersFromServer);
                if (!$signatureValid) {
                    throw new ValidationError(
                        "Signature validation failed. Logout Request rejected",
                        ValidationError::INVALID_SIGNATURE
                    );
                }
            }

            return true;
        } catch (Exception $e) {
            $this->_error = $e;
            $debug = $this->_settings->isDebugActive();
            if ($debug) {
                echo htmlentities($this->_error->getMessage());
            }
            return false;
        }
    }

    /**
     * After execute a validation process, if fails this method returns the Exception of the cause
     *
     * @return Exception Cause
     */
    public function getErrorException()
    {
        return $this->_error;
    }

    /**
     * After execute a validation process, if fails this method returns the cause
     *
     * @return null|string Error reason
     */
    public function getError()
    {
        $errorMsg = null;
        if (isset($this->_error)) {
            $errorMsg = htmlentities($this->_error->getMessage());
        }
        return $errorMsg;
    }

    /**
     * Returns the XML that will be sent as part of the request
     * or that was received at the SP
     *
     * @return string
     */
    public function getXML()
    {
        return $this->_logoutRequest;
    }
}
