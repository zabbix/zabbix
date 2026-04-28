<?php
/**
 * This file is part of php-saml.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin
 * @author  Sixto Martin <sixto.martin.garcia@gmail.com>
 * @license MIT https://github.com/SAML-Toolkits/php-saml/blob/master/LICENSE
 * @link    https://github.com/SAML-Toolkits/php-saml
 */

namespace OneLogin\Saml2;

use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecEnc;

use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Exception;

/**
 * SAML 2 Authentication Response
 */
class Response
{
    /**
     * Settings
     *
     * @var Settings
     */
    protected $_settings;

    /**
     * The decoded, unprocessed XML response provided to the constructor.
     *
     * @var string
     */
    public $response;

    /**
     * A DOMDocument class loaded from the SAML Response.
     *
     * @var DOMDocument
     */
    public $document;

    /**
     * A DOMDocument class loaded from the SAML Response (Decrypted).
     *
     * @var DOMDocument
     */
    public $decryptedDocument;

    /**
     * The response contains an encrypted assertion.
     *
     * @var bool
     */
    public $encrypted = false;

    /**
     * The response contains an encrypted nameId in the assertion.
     *
     * @var bool
     */
    public $encryptedNameId = false;

    /**
     * After validation, if it fail this var has the cause of the problem
     *
     * @var Exception|null
     */
    private $_error;

    /**
     * NotOnOrAfter value of a valid SubjectConfirmationData node
     *
     * @var int
     */
    private $_validSCDNotOnOrAfter;

    /**
     * Constructs the SAML Response object.
     *
     * @param Settings $settings Settings.
     * @param string   $response A UUEncoded SAML response from the IdP.
     *
     * @throws Exception
     * @throws ValidationError
     */
    public function __construct(\OneLogin\Saml2\Settings $settings, $response)
    {
        $this->_settings = $settings;

        $baseURL = $this->_settings->getBaseURL();
        if (!empty($baseURL)) {
            Utils::setBaseURL($baseURL);
        }

        $this->response = base64_decode($response);

        $this->document = new DOMDocument();
        $this->document = Utils::loadXML($this->document, $this->response);
        if (!$this->document) {
            throw new ValidationError(
                "SAML Response could not be processed",
                ValidationError::INVALID_XML_FORMAT
            );
        }

        // Quick check for the presence of EncryptedAssertion
        $encryptedAssertionNodes = $this->document->getElementsByTagName('EncryptedAssertion');
        if ($encryptedAssertionNodes->length !== 0) {
            $this->decryptedDocument = clone $this->document;
            $this->encrypted = true;
            $this->decryptedDocument = $this->decryptAssertion($this->decryptedDocument);
        }
    }

    /**
     * Determines if the SAML Response is valid using the certificate.
     *
     * @param string|null $requestId The ID of the AuthNRequest sent by this SP to the IdP
     *
     * @return bool Validate the document
     *
     * @throws Exception
     * @throws ValidationError
     */
    public function isValid($requestId = null)
    {
        $this->_error = null;
        try {
            // Check SAML version
            if ($this->document->documentElement->getAttribute('Version') != '2.0') {
                throw new ValidationError(
                    "Unsupported SAML version",
                    ValidationError::UNSUPPORTED_SAML_VERSION
                );
            }

            if (!$this->document->documentElement->hasAttribute('ID')) {
                throw new ValidationError(
                    "Missing ID attribute on SAML Response",
                    ValidationError::MISSING_ID
                );
            }

            $this->checkStatus();

            $singleAssertion = $this->validateNumAssertions();
            if (!$singleAssertion) {
                throw new ValidationError(
                    "SAML Response must contain 1 assertion",
                    ValidationError::WRONG_NUMBER_OF_ASSERTIONS
                );
            }

            $idpData = $this->_settings->getIdPData();
            $idPEntityId = $idpData['entityId'];
            $spData = $this->_settings->getSPData();
            $spEntityId = $spData['entityId'];

            $signedElements = $this->processSignedElements();

            $responseTag = '{'.Constants::NS_SAMLP.'}Response';
            $assertionTag = '{'.Constants::NS_SAML.'}Assertion';

            $hasSignedResponse = in_array($responseTag, $signedElements);
            $hasSignedAssertion = in_array($assertionTag, $signedElements);

            if ($this->_settings->isStrict()) {
                $security = $this->_settings->getSecurityData();

                if ($security['wantXMLValidation']) {
                    $errorXmlMsg = "Invalid SAML Response. Not match the saml-schema-protocol-2.0.xsd";
                    $res = Utils::validateXML($this->document, 'saml-schema-protocol-2.0.xsd', $this->_settings->isDebugActive(), $this->_settings->getSchemasPath());
                    if (!$res instanceof DOMDocument) {
                        throw new ValidationError(
                            $errorXmlMsg,
                            ValidationError::INVALID_XML_FORMAT
                        );
                    }

                    // If encrypted, check also the decrypted document
                    if ($this->encrypted) {
                        $res = Utils::validateXML($this->decryptedDocument, 'saml-schema-protocol-2.0.xsd', $this->_settings->isDebugActive(), $this->_settings->getSchemasPath());
                        if (!$res instanceof DOMDocument) {
                            throw new ValidationError(
                                $errorXmlMsg,
                                ValidationError::INVALID_XML_FORMAT
                            );
                        }
                    }

                }

                $currentURL = Utils::getSelfRoutedURLNoQuery();

                $responseInResponseTo = null;
                if ($this->document->documentElement->hasAttribute('InResponseTo')) {
                    $responseInResponseTo = $this->document->documentElement->getAttribute('InResponseTo');
                }

                if (!isset($requestId) && isset($responseInResponseTo) && $security['rejectUnsolicitedResponsesWithInResponseTo']) {
                    throw new ValidationError(
                        "The Response has an InResponseTo attribute: " . $responseInResponseTo . " while no InResponseTo was expected",
                        ValidationError::WRONG_INRESPONSETO
                    );
                }

                // Check if the InResponseTo of the Response matchs the ID of the AuthNRequest (requestId) if provided
                if (isset($requestId) && $requestId != $responseInResponseTo) {
                    if ($responseInResponseTo == null) {
                        throw new ValidationError(
                            "No InResponseTo at the Response, but it was provided the requestId related to the AuthNRequest sent by the SP: $requestId",
                            ValidationError::WRONG_INRESPONSETO
                        );
                    } else {
                        throw new ValidationError(
                            "The InResponseTo of the Response: $responseInResponseTo, does not match the ID of the AuthNRequest sent by the SP: $requestId",
                            ValidationError::WRONG_INRESPONSETO
                        );
                    }
                }

                if (!$this->encrypted && $security['wantAssertionsEncrypted']) {
                    throw new ValidationError(
                        "The assertion of the Response is not encrypted and the SP requires it",
                        ValidationError::NO_ENCRYPTED_ASSERTION
                    );
                }

                $this->encryptedNameId = $this->encryptedNameId || $this->_queryAssertion('/saml:Subject/saml:EncryptedID/xenc:EncryptedData')->length > 0;
                if (!$this->encryptedNameId && $security['wantNameIdEncrypted']) {
                    throw new ValidationError(
                        "The NameID of the Response is not encrypted and the SP requires it",
                        ValidationError::NO_ENCRYPTED_NAMEID
                    );
                }

                // Validate Conditions element exists
                if (!$this->checkOneCondition()) {
                    throw new ValidationError(
                        "The Assertion must include a Conditions element",
                        ValidationError::MISSING_CONDITIONS
                    );
                }

                // Validate Assertion timestamps
                $this->validateTimestamps();

                // Validate AuthnStatement element exists and is unique
                if (!$this->checkOneAuthnStatement()) {
                    throw new ValidationError(
                        "The Assertion must include an AuthnStatement element",
                        ValidationError::WRONG_NUMBER_OF_AUTHSTATEMENTS
                    );
                }

                // EncryptedAttributes are not supported
                $encryptedAttributeNodes = $this->_queryAssertion('/saml:AttributeStatement/saml:EncryptedAttribute');
                if ($encryptedAttributeNodes->length > 0) {
                    throw new ValidationError(
                        "There is an EncryptedAttribute in the Response and this SP not support them",
                        ValidationError::ENCRYPTED_ATTRIBUTES
                    );
                }

                // Check destination
                if ($this->document->documentElement->hasAttribute('Destination')) {
                    $destination = $this->document->documentElement->getAttribute('Destination');
                    if (isset($destination)) {
                        $destination = trim($destination);
                    }
                    if (empty($destination)) {
                        if (!$security['relaxDestinationValidation']) {
                            throw new ValidationError(
                                "The response has an empty Destination value",
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
                                    "The response was received at $currentURL instead of $destination",
                                    ValidationError::WRONG_DESTINATION
                                );
                            }
                        }
                    }
                }

                // Check audience
                $validAudiences = $this->getAudiences();
                if (!empty($validAudiences) && !in_array($spEntityId, $validAudiences, true)) {
                    $validAudiencesStr = implode(',', $validAudiences);
                    throw new ValidationError(
                        "Invalid audience for this Response (expected '".$spEntityId."', got '".$validAudiencesStr."')",
                        ValidationError::WRONG_AUDIENCE
                    );
                }

                // Check the issuers
                $issuers = $this->getIssuers();
                foreach ($issuers as $issuer) {
                    if (isset($issuer)) {
                        $trimmedIssuer = trim($issuer);
                        if (empty($trimmedIssuer) || $trimmedIssuer !== $idPEntityId) {
                            throw new ValidationError(
                                "Invalid issuer in the Assertion/Response (expected '".$idPEntityId."', got '".$trimmedIssuer."')",
                                ValidationError::WRONG_ISSUER
                            );
                        }
                    }
                }

                // Check the session Expiration
                $sessionExpiration = $this->getSessionNotOnOrAfter();
                if (!empty($sessionExpiration) && $sessionExpiration + Constants::ALLOWED_CLOCK_DRIFT <= time()) {
                    throw new ValidationError(
                        "The attributes have expired, based on the SessionNotOnOrAfter of the AttributeStatement of this Response",
                        ValidationError::SESSION_EXPIRED
                    );
                }

                // Check the SubjectConfirmation, at least one SubjectConfirmation must be valid
                $anySubjectConfirmation = false;
                $subjectConfirmationNodes = $this->_queryAssertion('/saml:Subject/saml:SubjectConfirmation');
                foreach ($subjectConfirmationNodes as $scn) {
                    if ($scn->hasAttribute('Method') && $scn->getAttribute('Method') != Constants::CM_BEARER) {
                        continue;
                    }
                    $subjectConfirmationDataNodes = $scn->getElementsByTagName('SubjectConfirmationData');
                    if ($subjectConfirmationDataNodes->length == 0) {
                        continue;
                    } else {
                        $scnData = $subjectConfirmationDataNodes->item(0);
                        if ($scnData->hasAttribute('InResponseTo')) {
                            $inResponseTo = $scnData->getAttribute('InResponseTo');
                            if (isset($responseInResponseTo) && $responseInResponseTo != $inResponseTo) {
                                continue;
                            }
                        }
                        if ($scnData->hasAttribute('Recipient')) {
                            $recipient = $scnData->getAttribute('Recipient');
                            if (!empty($recipient) && strpos($recipient, $currentURL) === false) {
                                continue;
                            }
                        }
                        if ($scnData->hasAttribute('NotOnOrAfter')) {
                            $noa = Utils::parseSAML2Time($scnData->getAttribute('NotOnOrAfter'));
                            if ($noa + Constants::ALLOWED_CLOCK_DRIFT <= time()) {
                                continue;
                            }
                        }
                        if ($scnData->hasAttribute('NotBefore')) {
                            $nb = Utils::parseSAML2Time($scnData->getAttribute('NotBefore'));
                            if ($nb > time() + Constants::ALLOWED_CLOCK_DRIFT) {
                                continue;
                            }
                        }

                        // Save NotOnOrAfter value
                        if ($scnData->hasAttribute('NotOnOrAfter')) {
                            $this->_validSCDNotOnOrAfter = $noa;
                        }
                        $anySubjectConfirmation = true;
                        break;
                    }
                }

                if (!$anySubjectConfirmation) {
                    throw new ValidationError(
                        "A valid SubjectConfirmation was not found on this Response",
                        ValidationError::WRONG_SUBJECTCONFIRMATION
                    );
                }

                if ($security['wantAssertionsSigned'] && !$hasSignedAssertion) {
                    throw new ValidationError(
                        "The Assertion of the Response is not signed and the SP requires it",
                        ValidationError::NO_SIGNED_ASSERTION
                    );
                }

                if ($security['wantMessagesSigned'] && !$hasSignedResponse) {
                    throw new ValidationError(
                        "The Message of the Response is not signed and the SP requires it",
                        ValidationError::NO_SIGNED_MESSAGE
                    );
                }
            }

            if (empty($signedElements) || (!$hasSignedResponse && !$hasSignedAssertion)) {
                throw new ValidationError(
                    'No Signature found. SAML Response rejected',
                    ValidationError::NO_SIGNATURE_FOUND
                );
            } else {
                $cert = $idpData['x509cert'];
                $fingerprint = $idpData['certFingerprint'];
                $fingerprintalg = $idpData['certFingerprintAlgorithm'];

                $multiCerts = null;
                $existsMultiX509Sign = isset($idpData['x509certMulti']) && isset($idpData['x509certMulti']['signing']) && !empty($idpData['x509certMulti']['signing']);

                if ($existsMultiX509Sign) {
                    $multiCerts = $idpData['x509certMulti']['signing'];
                }

                // If find a Signature on the Response, validates it checking the original response
                if ($hasSignedResponse && !Utils::validateSign($this->document, $cert, $fingerprint, $fingerprintalg, Utils::RESPONSE_SIGNATURE_XPATH, $multiCerts)) {
                    throw new ValidationError(
                        "Signature validation failed. SAML Response rejected",
                        ValidationError::INVALID_SIGNATURE
                    );
                }

                // If find a Signature on the Assertion (decrypted assertion if was encrypted)
                $documentToCheckAssertion = $this->encrypted ? $this->decryptedDocument : $this->document;
                if ($hasSignedAssertion && !Utils::validateSign($documentToCheckAssertion, $cert, $fingerprint, $fingerprintalg, Utils::ASSERTION_SIGNATURE_XPATH, $multiCerts)) {
                    throw new ValidationError(
                        "Signature validation failed. SAML Response rejected",
                        ValidationError::INVALID_SIGNATURE
                    );
                }
            }
            return true;
        } catch (Exception $e) {
            $this->_error = $e;
            $debug = $this->_settings->isDebugActive();
            if ($debug) {
                echo htmlentities($e->getMessage());
            }
            return false;
        }
    }

    /**
     * @return string|null the ID of the Response
     */
    public function getId()
    {
        $id = null;
        if ($this->document->documentElement->hasAttribute('ID')) {
            $id = $this->document->documentElement->getAttribute('ID');
        }
        return $id;
    }

    /**
     * @return string|null the ID of the assertion in the Response
     *
     * @throws ValidationError
     */
    public function getAssertionId()
    {
        if (!$this->validateNumAssertions()) {
            throw new ValidationError("SAML Response must contain 1 Assertion.", ValidationError::WRONG_NUMBER_OF_ASSERTIONS);
        }
        $assertionNodes = $this->_queryAssertion("");
        $id = null;
        if ($assertionNodes->length == 1 && $assertionNodes->item(0)->hasAttribute('ID')) {
            $id = $assertionNodes->item(0)->getAttribute('ID');
        }
        return $id;
    }

    /**
     * @return int the NotOnOrAfter value of the valid SubjectConfirmationData
     * node if any
     */
    public function getAssertionNotOnOrAfter()
    {
        return $this->_validSCDNotOnOrAfter;
    }

    /**
     * Checks if the Status is success
     *
     * @throws ValidationError If status is not success
     */
    public function checkStatus()
    {
        $status = Utils::getStatus($this->document);

        if (isset($status['code']) && $status['code'] !== Constants::STATUS_SUCCESS) {
            $explodedCode = explode(':', $status['code']);
            $printableCode = array_pop($explodedCode);

            $statusExceptionMsg = 'The status code of the Response was not Success, was '.$printableCode;
            if (!empty($status['msg'])) {
                $statusExceptionMsg .= ' -> '.$status['msg'];
            }
            throw new ValidationError(
                $statusExceptionMsg,
                ValidationError::STATUS_CODE_IS_NOT_SUCCESS
            );
        }
    }

    /**
     * Checks that the samlp:Response/saml:Assertion/saml:Conditions element exists and is unique.
     *
     * @return boolean true if the Conditions element exists and is unique
     */
    public function checkOneCondition()
    {
        $entries = $this->_queryAssertion("/saml:Conditions");
        if ($entries->length == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks that the samlp:Response/saml:Assertion/saml:AuthnStatement element exists and is unique.
     *
     * @return boolean true if the AuthnStatement element exists and is unique
     */
    public function checkOneAuthnStatement()
    {
        $entries = $this->_queryAssertion("/saml:AuthnStatement");
        if ($entries->length == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the audiences.
     *
     * @return array @audience The valid audiences of the response
     */
    public function getAudiences()
    {
        $audiences = array();

        $entries = $this->_queryAssertion('/saml:Conditions/saml:AudienceRestriction/saml:Audience');
        foreach ($entries as $entry) {
            $value = $entry->textContent;
            if (isset($value)) {
                $value = trim($value);
            }
            if (!empty($value)) {
                $audiences[] = $value;
            }
        }

        return array_unique($audiences);
    }

    /**
     * Gets the Issuers (from Response and Assertion).
     *
     * @return array @issuers The issuers of the assertion/response
     *
     * @throws ValidationError
     */
    public function getIssuers()
    {
        $issuers = array();

        $responseIssuer = Utils::query($this->document, '/samlp:Response/saml:Issuer');
        if ($responseIssuer->length > 0) {
            if ($responseIssuer->length == 1) {
                $issuers[] = $responseIssuer->item(0)->textContent;
            } else {
                throw new ValidationError(
                    "Issuer of the Response is multiple.",
                    ValidationError::ISSUER_MULTIPLE_IN_RESPONSE
                );
            }
        }

        $assertionIssuer = $this->_queryAssertion('/saml:Issuer');
        if ($assertionIssuer->length == 1) {
            $issuers[] = $assertionIssuer->item(0)->textContent;
        } else {
            throw new ValidationError(
                "Issuer of the Assertion not found or multiple.",
                ValidationError::ISSUER_NOT_FOUND_IN_ASSERTION
            );
        }

        return array_unique($issuers);
    }

    /**
     * Gets the NameID Data provided by the SAML response from the IdP.
     *
     * @return array Name ID Data (Value, Format, NameQualifier, SPNameQualifier)
     *
     * @throws ValidationError
     */
    public function getNameIdData()
    {
        $encryptedIdDataEntries = $this->_queryAssertion('/saml:Subject/saml:EncryptedID/xenc:EncryptedData');

        if ($encryptedIdDataEntries->length == 1) {
            $encryptedData = $encryptedIdDataEntries->item(0);

            $pem = $this->_settings->getSPkey();

            if (empty($pem)) {
                throw new Error(
                    "No private key available, check settings",
                    Error::PRIVATE_KEY_NOT_FOUND
                );
            }
            $seckey = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, array('type'=>'private'));
            $seckey->loadKey($pem);

            $nameId = Utils::decryptElement($encryptedData, $seckey);

        } else {
            $entries = $this->_queryAssertion('/saml:Subject/saml:NameID');
            if ($entries->length == 1) {
                $nameId = $entries->item(0);
            }
        }

        $nameIdData = array();

        $security = $this->_settings->getSecurityData();
        if (!isset($nameId)) {
            if ($security['wantNameId']) {
                throw new ValidationError(
                    "NameID not found in the assertion of the Response",
                    ValidationError::NO_NAMEID
                );
            }
        } else {
            if ($this->_settings->isStrict() && $security['wantNameId'] && empty($nameId->nodeValue)) {
                throw new ValidationError(
                    "An empty NameID value found",
                    ValidationError::EMPTY_NAMEID
                );
            }
            $nameIdData['Value'] = $nameId->nodeValue;

            foreach (array('Format', 'SPNameQualifier', 'NameQualifier') as $attr) {
                if ($nameId->hasAttribute($attr)) {
                    if ($this->_settings->isStrict() && $attr == 'SPNameQualifier') {
                        $spData = $this->_settings->getSPData();
                        $spEntityId = $spData['entityId'];
                        if ($spEntityId != $nameId->getAttribute($attr)) {
                            throw new ValidationError(
                                "The SPNameQualifier value mismatch the SP entityID value.",
                                ValidationError::SP_NAME_QUALIFIER_NAME_MISMATCH
                            );
                        }
                    }
                    $nameIdData[$attr] = $nameId->getAttribute($attr);
                }
            }
        }

        return $nameIdData;
    }

    /**
     * Gets the NameID provided by the SAML response from the IdP.
     *
     * @return string|null Name ID Value
     *
     * @throws ValidationError
     */
    public function getNameId()
    {
        $nameIdvalue = null;
        $nameIdData = $this->getNameIdData();
        if (!empty($nameIdData) && isset($nameIdData['Value'])) {
            $nameIdvalue = $nameIdData['Value'];
        }
        return $nameIdvalue;
    }

    /**
     * Gets the NameID Format provided by the SAML response from the IdP.
     *
     * @return string|null Name ID Format
     *
     * @throws ValidationError
     */
    public function getNameIdFormat()
    {
        $nameIdFormat = null;
        $nameIdData = $this->getNameIdData();
        if (!empty($nameIdData) && isset($nameIdData['Format'])) {
            $nameIdFormat = $nameIdData['Format'];
        }
        return $nameIdFormat;
    }

    /**
     * Gets the NameID NameQualifier provided by the SAML response from the IdP.
     *
     * @return string|null Name ID NameQualifier
     *
     * @throws ValidationError
     */
    public function getNameIdNameQualifier()
    {
        $nameIdNameQualifier = null;
        $nameIdData = $this->getNameIdData();
        if (!empty($nameIdData) && isset($nameIdData['NameQualifier'])) {
            $nameIdNameQualifier = $nameIdData['NameQualifier'];
        }
        return $nameIdNameQualifier;
    }

    /**
     * Gets the NameID SP NameQualifier provided by the SAML response from the IdP.
     *
     * @return string|null NameID SP NameQualifier
     *
     * @throws ValidationError
     */
    public function getNameIdSPNameQualifier()
    {
        $nameIdSPNameQualifier = null;
        $nameIdData = $this->getNameIdData();
        if (!empty($nameIdData) && isset($nameIdData['SPNameQualifier'])) {
            $nameIdSPNameQualifier = $nameIdData['SPNameQualifier'];
        }
        return $nameIdSPNameQualifier;
    }

    /**
     * Gets the SessionNotOnOrAfter from the AuthnStatement.
     * Could be used to set the local session expiration
     *
     * @return int|null The SessionNotOnOrAfter value
     *
     * @throws Exception
     */
    public function getSessionNotOnOrAfter()
    {
        $notOnOrAfter = null;
        $entries = $this->_queryAssertion('/saml:AuthnStatement[@SessionNotOnOrAfter]');
        if ($entries->length !== 0) {
            $notOnOrAfter = Utils::parseSAML2Time($entries->item(0)->getAttribute('SessionNotOnOrAfter'));
        }
        return $notOnOrAfter;
    }

    /**
     * Gets the SessionIndex from the AuthnStatement.
     * Could be used to be stored in the local session in order
     * to be used in a future Logout Request that the SP could
     * send to the SP, to set what specific session must be deleted
     *
     * @return string|null The SessionIndex value
     */
    public function getSessionIndex()
    {
        $sessionIndex = null;
        $entries = $this->_queryAssertion('/saml:AuthnStatement[@SessionIndex]');
        if ($entries->length !== 0) {
            $sessionIndex = $entries->item(0)->getAttribute('SessionIndex');
        }
        return $sessionIndex;
    }

    /**
     * Gets the Attributes from the AttributeStatement element.
     *
     * @return array The attributes of the SAML Assertion
     *
     * @throws ValidationError
     */
    public function getAttributes()
    {
        return $this->_getAttributesByKeyName('Name');
    }

    /**
     * Gets the Attributes from the AttributeStatement element using their FriendlyName.
     *
     * @return array The attributes of the SAML Assertion
     *
     * @throws ValidationError
     */
    public function getAttributesWithFriendlyName()
    {
        return $this->_getAttributesByKeyName('FriendlyName');
    }

    /**
     * @param string $keyName
     *
     * @return array
     *
     * @throws ValidationError
     */
    private function _getAttributesByKeyName($keyName = "Name")
    {
        $attributes = array();
        $entries = $this->_queryAssertion('/saml:AttributeStatement/saml:Attribute');

        $security = $this->_settings->getSecurityData();
        $allowRepeatAttributeName = $security['allowRepeatAttributeName'];
        /** @var $entry DOMNode */
        foreach ($entries as $entry) {
            $attributeKeyNode = $entry->attributes->getNamedItem($keyName);
            if ($attributeKeyNode === null) {
                continue;
            }
            $attributeKeyName = $attributeKeyNode->nodeValue;
            if (in_array($attributeKeyName, array_keys($attributes), true)) {
                if (!$allowRepeatAttributeName) {
                    throw new ValidationError(
                        "Found an Attribute element with duplicated ".$keyName,
                        ValidationError::DUPLICATED_ATTRIBUTE_NAME_FOUND
                    );
                }
            }
            $attributeValues = array();
            foreach ($entry->childNodes as $childNode) {
                $tagName = ($childNode->prefix ? $childNode->prefix.':' : '') . 'AttributeValue';
                if ($childNode->nodeType == XML_ELEMENT_NODE && $childNode->tagName === $tagName) {
                    $attributeValues[] = $childNode->nodeValue;
                }
            }

            if (in_array($attributeKeyName, array_keys($attributes), true)) {
                $attributes[$attributeKeyName] = array_merge($attributes[$attributeKeyName], $attributeValues);
            } else {
                $attributes[$attributeKeyName] = $attributeValues;
            }
        }
        return $attributes;
    }

    /**
     * Verifies that the document only contains a single Assertion (encrypted or not).
     *
     * @return bool TRUE if the document passes.
     */
    public function validateNumAssertions()
    {
        $encryptedAssertionNodes = $this->document->getElementsByTagName('EncryptedAssertion');
        $assertionNodes = $this->document->getElementsByTagName('Assertion');

        $valid = $assertionNodes->length + $encryptedAssertionNodes->length == 1;

        if ($this->encrypted) {
            $assertionNodes = $this->decryptedDocument->getElementsByTagName('Assertion');
            $valid = $valid && $assertionNodes->length == 1;
        }

        return $valid;
    }

    /**
     * Verifies the signature nodes:
     *   - Checks that are Response or Assertion
     *   - Check that IDs and reference URI are unique and consistent.
     *
     * @return array Signed element tags
     *
     * @throws ValidationError
     */
    public function processSignedElements()
    {
        $signedElements = array();
        $verifiedSeis = array();
        $verifiedIds = array();

        if ($this->encrypted) {
            $signNodes = $this->decryptedDocument->getElementsByTagName('Signature');
        } else {
            $signNodes = $this->document->getElementsByTagName('Signature');
        }
        foreach ($signNodes as $signNode) {
            $responseTag = '{'.Constants::NS_SAMLP.'}Response';
            $assertionTag = '{'.Constants::NS_SAML.'}Assertion';

            $signedElement = '{'.$signNode->parentNode->namespaceURI.'}'.$signNode->parentNode->localName;

            if ($signedElement != $responseTag && $signedElement != $assertionTag) {
                throw new ValidationError(
                    "Invalid Signature Element $signedElement SAML Response rejected",
                    ValidationError::WRONG_SIGNED_ELEMENT
                );
            }

            // Check that reference URI matches the parent ID and no duplicate References or IDs
            $idValue = $signNode->parentNode->getAttribute('ID');
            if (empty($idValue)) {
                throw new ValidationError(
                    'Signed Element must contain an ID. SAML Response rejected',
                    ValidationError::ID_NOT_FOUND_IN_SIGNED_ELEMENT
                );
            }

            if (in_array($idValue, $verifiedIds)) {
                throw new ValidationError(
                    'Duplicated ID. SAML Response rejected',
                    ValidationError::DUPLICATED_ID_IN_SIGNED_ELEMENTS
                );
            }
            $verifiedIds[] = $idValue;

            $ref = $signNode->getElementsByTagName('Reference');
            if ($ref->length == 1) {
                $ref = $ref->item(0);
                $sei = $ref->getAttribute('URI');
                if (!empty($sei)) {
                    $sei = substr($sei, 1);

                    if ($sei != $idValue) {
                        throw new ValidationError(
                            'Found an invalid Signed Element. SAML Response rejected',
                            ValidationError::INVALID_SIGNED_ELEMENT
                        );
                    }

                    if (in_array($sei, $verifiedSeis)) {
                        throw new ValidationError(
                            'Duplicated Reference URI. SAML Response rejected',
                            ValidationError::DUPLICATED_REFERENCE_IN_SIGNED_ELEMENTS
                        );
                    }
                    $verifiedSeis[] = $sei;
                }
            } else {
                throw new ValidationError(
                    'Unexpected number of Reference nodes found for signature. SAML Response rejected.',
                    ValidationError::UNEXPECTED_REFERENCE
                );
            }
            $signedElements[] = $signedElement;
        }

        // Check SignedElements
        if (!empty($signedElements) && !$this->validateSignedElements($signedElements)) {
            throw new ValidationError(
                'Found an unexpected Signature Element. SAML Response rejected',
                ValidationError::UNEXPECTED_SIGNED_ELEMENTS
            );
        }
        return $signedElements;
    }

    /**
     * Verifies that the document is still valid according Conditions Element.
     *
     * @return bool
     *
     * @throws Exception
     * @throws ValidationError
     */
    public function validateTimestamps()
    {
        if ($this->encrypted) {
            $document = $this->decryptedDocument;
        } else {
            $document = $this->document;
        }

        $timestampNodes = $document->getElementsByTagName('Conditions');
        for ($i = 0; $i < $timestampNodes->length; $i++) {
            $nbAttribute = $timestampNodes->item($i)->attributes->getNamedItem("NotBefore");
            $naAttribute = $timestampNodes->item($i)->attributes->getNamedItem("NotOnOrAfter");
            if ($nbAttribute && Utils::parseSAML2Time($nbAttribute->textContent) > time() + Constants::ALLOWED_CLOCK_DRIFT) {
                throw new ValidationError(
                    'Could not validate timestamp: not yet valid. Check system clock.',
                    ValidationError::ASSERTION_TOO_EARLY
                );
            }
            if ($naAttribute && Utils::parseSAML2Time($naAttribute->textContent) + Constants::ALLOWED_CLOCK_DRIFT <= time()) {
                throw new ValidationError(
                    'Could not validate timestamp: expired. Check system clock.',
                    ValidationError::ASSERTION_EXPIRED
                );
            }
        }
        return true;
    }

    /**
     * Verifies that the document has the expected signed nodes.
     *
     * @param array $signedElements Signed elements
     *
     * @return bool
     *
     * @throws ValidationError
     */
    public function validateSignedElements($signedElements)
    {
        if (count($signedElements) > 2) {
            return false;
        }

        $responseTag = '{'.Constants::NS_SAMLP.'}Response';
        $assertionTag = '{'.Constants::NS_SAML.'}Assertion';

        $occurrence = array_count_values($signedElements);
        if ((in_array($responseTag, $signedElements) && $occurrence[$responseTag] > 1)
            || (in_array($assertionTag, $signedElements) && $occurrence[$assertionTag] > 1)
            || !in_array($responseTag, $signedElements) && !in_array($assertionTag, $signedElements)
        ) {
            return false;
        }

        // Check that the signed elements found here, are the ones that will be verified
        // by Utils->validateSign()
        if (in_array($responseTag, $signedElements)) {
            $expectedSignatureNodes = Utils::query($this->document, Utils::RESPONSE_SIGNATURE_XPATH);
            if ($expectedSignatureNodes->length != 1) {
                throw new ValidationError(
                    "Unexpected number of Response signatures found. SAML Response rejected.",
                    ValidationError::WRONG_NUMBER_OF_SIGNATURES_IN_RESPONSE
                );
            }
        }

        if (in_array($assertionTag, $signedElements)) {
            $expectedSignatureNodes = $this->_query(Utils::ASSERTION_SIGNATURE_XPATH);
            if ($expectedSignatureNodes->length != 1) {
                throw new ValidationError(
                    "Unexpected number of Assertion signatures found. SAML Response rejected.",
                    ValidationError::WRONG_NUMBER_OF_SIGNATURES_IN_ASSERTION
                );
            }
        }

        return true;
    }

    /**
     * Extracts a node from the DOMDocument (Assertion).
     *
     * @param string $assertionXpath Xpath Expression
     *
     * @return DOMNodeList The queried node
     */
    protected function _queryAssertion($assertionXpath)
    {
        if ($this->encrypted) {
            $xpath = new DOMXPath($this->decryptedDocument);
        } else {
            $xpath = new DOMXPath($this->document);
        }

        $xpath->registerNamespace('samlp', Constants::NS_SAMLP);
        $xpath->registerNamespace('saml', Constants::NS_SAML);
        $xpath->registerNamespace('ds', Constants::NS_DS);
        $xpath->registerNamespace('xenc', Constants::NS_XENC);

        $assertionNode = '/samlp:Response/saml:Assertion';
        $signatureQuery = $assertionNode . '/ds:Signature/ds:SignedInfo/ds:Reference';
        $assertionReferenceNode = $xpath->query($signatureQuery)->item(0);
        if (!$assertionReferenceNode) {
            // is the response signed as a whole?
            $signatureQuery = '/samlp:Response/ds:Signature/ds:SignedInfo/ds:Reference';
            $responseReferenceNode = $xpath->query($signatureQuery)->item(0);
            if ($responseReferenceNode) {
                $uri = $responseReferenceNode->attributes->getNamedItem('URI')->nodeValue;
                if (empty($uri)) {
                    $id = $responseReferenceNode->parentNode->parentNode->parentNode->attributes->getNamedItem('ID')->nodeValue;
                } else {
                    $id = substr($responseReferenceNode->attributes->getNamedItem('URI')->nodeValue, 1);
                }
                $nameQuery = "/samlp:Response[@ID='$id']/saml:Assertion" . $assertionXpath;
            } else {
                $nameQuery = "/samlp:Response/saml:Assertion" . $assertionXpath;
            }
        } else {
            $uri = $assertionReferenceNode->attributes->getNamedItem('URI')->nodeValue;
            if (empty($uri)) {
                $id = $assertionReferenceNode->parentNode->parentNode->parentNode->attributes->getNamedItem('ID')->nodeValue;
            } else {
                $id = substr($assertionReferenceNode->attributes->getNamedItem('URI')->nodeValue, 1);
            }
            $nameQuery = $assertionNode."[@ID='$id']" . $assertionXpath;
        }

        return $xpath->query($nameQuery);
    }

    /**
     * Extracts nodes that match the query from the DOMDocument (Response Message)
     *
     * @param string $query Xpath Expression
     *
     * @return DOMNodeList The queried nodes
     */
    private function _query($query)
    {
        if ($this->encrypted) {
            return Utils::query($this->decryptedDocument, $query);
        } else {
            return Utils::query($this->document, $query);
        }
    }

    /**
     * Decrypts the Assertion (DOMDocument)
     *
     * @param \DomNode $dom DomDocument
     *
     * @return DOMDocument Decrypted Assertion
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function decryptAssertion(\DomNode $dom)
    {
        $pem = $this->_settings->getSPkey();

        if (empty($pem)) {
            throw new Error(
                "No private key available, check settings",
                Error::PRIVATE_KEY_NOT_FOUND
            );
        }

        $objenc = new XMLSecEnc();
        $encData = $objenc->locateEncryptedData($dom);
        if (!$encData) {
            throw new ValidationError(
                "Cannot locate encrypted assertion",
                ValidationError::MISSING_ENCRYPTED_ELEMENT
            );
        }

        $objenc->setNode($encData);
        $objenc->type = $encData->getAttribute("Type");
        if (!$objKey = $objenc->locateKey()) {
            throw new ValidationError(
                "Unknown algorithm",
                ValidationError::KEY_ALGORITHM_ERROR
            );
        }

        $key = null;
        if ($objKeyInfo = $objenc->locateKeyInfo($objKey)) {
            if ($objKeyInfo->isEncrypted) {
                $objencKey = $objKeyInfo->encryptedCtx;
                $objKeyInfo->loadKey($pem, false, false);
                $key = $objencKey->decryptKey($objKeyInfo);
            } else {
                // symmetric encryption key support
                $objKeyInfo->loadKey($pem, false, false);
            }
        }

        if (empty($objKey->key)) {
            $objKey->loadKey($key);
        }

        $decryptedXML = $objenc->decryptNode($objKey, false);
        $decrypted = new DOMDocument();
        $check = Utils::loadXML($decrypted, $decryptedXML);
        if ($check === false) {
            throw new Exception('Error: string from decrypted assertion could not be loaded into a XML document');
        }

        // check if the decrypted assertion contains an encryptedID
        $encryptedID = $decrypted->getElementsByTagName('EncryptedID')->item(0);

        if ($encryptedID) {
            // decrypt the encryptedID
            $this->encryptedNameId = true;
            $encryptedData = $encryptedID->getElementsByTagName('EncryptedData')->item(0);
            $nameId = $this->decryptNameId($encryptedData, $pem);
            Utils::treeCopyReplace($encryptedID, $nameId);
        }

        if ($encData->parentNode instanceof DOMDocument) {
            return $decrypted;
        } else {
            $decrypted = $decrypted->documentElement;
            $encryptedAssertion = $encData->parentNode;
            $container = $encryptedAssertion->parentNode;

            // Fix possible issue with saml namespace
            if (!$decrypted->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml')
                && !$decrypted->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml2')
                && !$decrypted->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns')
                && !$container->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml')
                && !$container->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:saml2')
            ) {
                if (strpos($encryptedAssertion->tagName, 'saml2:') !== false) {
                    $ns = 'xmlns:saml2';
                } else if (strpos($encryptedAssertion->tagName, 'saml:') !== false) {
                    $ns = 'xmlns:saml';
                } else {
                    $ns = 'xmlns';
                }
                $decrypted->setAttributeNS('http://www.w3.org/2000/xmlns/', $ns, Constants::NS_SAML);
            }

            Utils::treeCopyReplace($encryptedAssertion, $decrypted);

            // Rebuild the DOM will fix issues with namespaces as well
            $dom = new DOMDocument();
            return Utils::loadXML($dom, $container->ownerDocument->saveXML());
        }
    }

    /**
     * Decrypt EncryptedID element
     *
     * @param \DOMElement $encryptedData The encrypted data.
     * @param string     $key            The private key
     *
     * @return \DOMElement  The decrypted element.
     */
    private function decryptNameId(\DOMElement $encryptedData, string $pem)
    {
        $objenc = new XMLSecEnc();
        $encData = $objenc->locateEncryptedData($encryptedData);
        $objenc->setNode($encData);
        $objenc->type = $encData->getAttribute("Type");
        if (!$objKey = $objenc->locateKey()) {
            throw new ValidationError(
                "Unknown algorithm",
                ValidationError::KEY_ALGORITHM_ERROR
            );
        }

        $key = null;
        if ($objKeyInfo = $objenc->locateKeyInfo($objKey)) {
            if ($objKeyInfo->isEncrypted) {
                $objencKey = $objKeyInfo->encryptedCtx;
                $objKeyInfo->loadKey($pem, false, false);
                $key = $objencKey->decryptKey($objKeyInfo);
            } else {
                // symmetric encryption key support
                $objKeyInfo->loadKey($pem, false, false);
            }
        }

        if (empty($objKey->key)) {
            $objKey->loadKey($key);
        }

        return Utils::decryptElement($encryptedData, $objKey);
    }

    /**
     * After execute a validation process, if fails this method returns the cause
     *
     * @return Exception|null Cause
     */
    public function getErrorException()
    {
        return $this->_error;
    }

    /**
     * After execute a validation process, if fails this method returns the cause
     *
     * @param bool $escape Apply or not htmlentities to the message.
     *
     * @return null|string Error reason
     */
    public function getError($escape = true)
    {
        $errorMsg = null;
        if (isset($this->_error)) {
            if ($escape) {
                $errorMsg = htmlentities($this->_error->getMessage());
            } else {
                $errorMsg = $this->_error->getMessage();
            }
        }
        return $errorMsg;
    }

    /**
     * Returns the SAML Response document (If contains an encrypted assertion, decrypts it)
     *
     * @return DomDocument SAML Response
     */
    public function getXMLDocument()
    {
        if ($this->encrypted) {
            return $this->decryptedDocument;
        } else {
            return $this->document;
        }
    }
}
