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
 * Metadata lib of OneLogin PHP Toolkit
 */
class Metadata
{
    const TIME_VALID = 172800;  // 2 days
    const TIME_CACHED = 604800; // 1 week

    /**
     * Generates the metadata of the SP based on the settings
     *
     * @param array         $sp            The SP data
     * @param bool|string   $authnsign     authnRequestsSigned attribute
     * @param bool|string   $wsign         wantAssertionsSigned attribute
     * @param int|null      $validUntil    Metadata's valid time
     * @param int|null      $cacheDuration Duration of the cache in seconds
     * @param array         $contacts      Contacts info
     * @param array         $organization  Organization ingo
     * @param array         $attributes
     *
     * @return string SAML Metadata XML
     */
    public static function builder($sp, $authnsign = false, $wsign = false, $validUntil = null, $cacheDuration = null, $contacts = array(), $organization = array(), $attributes = array())
    {

        if (!isset($validUntil)) {
            $validUntil =  time() + self::TIME_VALID;
        }
        $validUntilTime =  Utils::parseTime2SAML($validUntil);

        if (!isset($cacheDuration)) {
            $cacheDuration = self::TIME_CACHED;
        }

        $sls = '';

        if (isset($sp['singleLogoutService'])) {
            $slsUrl = htmlspecialchars($sp['singleLogoutService']['url'], ENT_QUOTES);
            $sls = <<<SLS_TEMPLATE
        <md:SingleLogoutService Binding="{$sp['singleLogoutService']['binding']}"
                                Location="{$slsUrl}" />

SLS_TEMPLATE;
        }

        if ($authnsign) {
            $strAuthnsign = 'true';
        } else {
            $strAuthnsign = 'false';
        }

        if ($wsign) {
            $strWsign = 'true';
        } else {
            $strWsign = 'false';
        }

        $strOrganization = '';

        if (!empty($organization)) {
            $organizationInfoNames = array();
            $organizationInfoDisplaynames = array();
            $organizationInfoUrls = array();
            foreach ($organization as $lang => $info) {
                $organizationInfoNames[] = <<<ORGANIZATION_NAME
       <md:OrganizationName xml:lang="{$lang}">{$info['name']}</md:OrganizationName>
ORGANIZATION_NAME;
                $organizationInfoDisplaynames[] = <<<ORGANIZATION_DISPLAY
       <md:OrganizationDisplayName xml:lang="{$lang}">{$info['displayname']}</md:OrganizationDisplayName>
ORGANIZATION_DISPLAY;
                $organizationInfoUrls[] = <<<ORGANIZATION_URL
       <md:OrganizationURL xml:lang="{$lang}">{$info['url']}</md:OrganizationURL>
ORGANIZATION_URL;
            }
            $orgData = implode("\n", $organizationInfoNames)."\n".implode("\n", $organizationInfoDisplaynames)."\n".implode("\n", $organizationInfoUrls);
            $strOrganization = <<<ORGANIZATIONSTR

    <md:Organization>
{$orgData}
    </md:Organization>
ORGANIZATIONSTR;
        }

        $strContacts = '';
        if (!empty($contacts)) {
            $contactsInfo = array();
            foreach ($contacts as $type => $info) {
                $contactsInfo[] = <<<CONTACT
    <md:ContactPerson contactType="{$type}">
        <md:GivenName>{$info['givenName']}</md:GivenName>
        <md:EmailAddress>{$info['emailAddress']}</md:EmailAddress>
    </md:ContactPerson>
CONTACT;
            }
            $strContacts = "\n".implode("\n", $contactsInfo);
        }

        $strAttributeConsumingService = '';
        if (isset($sp['attributeConsumingService'])) {
            $attrCsDesc = '';
            if (isset($sp['attributeConsumingService']['serviceDescription'])) {
                $attrCsDesc = sprintf(
                    '            <md:ServiceDescription xml:lang="en">%s</md:ServiceDescription>' . PHP_EOL,
                    $sp['attributeConsumingService']['serviceDescription']
                );
            }
            if (!isset($sp['attributeConsumingService']['serviceName'])) {
                $sp['attributeConsumingService']['serviceName'] = 'Service';
            }
            $requestedAttributeData = array();
            foreach ($sp['attributeConsumingService']['requestedAttributes'] as $attribute) {
                $requestedAttributeStr = sprintf('            <md:RequestedAttribute Name="%s"', $attribute['name']);
                if (isset($attribute['nameFormat'])) {
                    $requestedAttributeStr .= sprintf(' NameFormat="%s"', $attribute['nameFormat']);
                }
                if (isset($attribute['friendlyName'])) {
                    $requestedAttributeStr .= sprintf(' FriendlyName="%s"', $attribute['friendlyName']);
                }
                if (isset($attribute['isRequired'])) {
                    $requestedAttributeStr .= sprintf(' isRequired="%s"', $attribute['isRequired'] === true ? 'true' : 'false');
                }
                $reqAttrAuxStr = " />";

                if (isset($attribute['attributeValue']) && !empty($attribute['attributeValue'])) {
                    $reqAttrAuxStr = '>';
                    if (is_string($attribute['attributeValue'])) {
                        $attribute['attributeValue'] = array($attribute['attributeValue']);
                    }
                    foreach ($attribute['attributeValue'] as $attrValue) {
                        $reqAttrAuxStr .=<<<ATTRIBUTEVALUE

                <saml:AttributeValue xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">{$attrValue}</saml:AttributeValue>
ATTRIBUTEVALUE;
                    }
                    $reqAttrAuxStr .= "\n            </md:RequestedAttribute>";
                }

                $requestedAttributeData[] = $requestedAttributeStr . $reqAttrAuxStr;
            }

            $requestedAttributeStr = implode(PHP_EOL, $requestedAttributeData);
            $strAttributeConsumingService = <<<METADATA_TEMPLATE
<md:AttributeConsumingService index="1">
            <md:ServiceName xml:lang="en">{$sp['attributeConsumingService']['serviceName']}</md:ServiceName>
{$attrCsDesc}{$requestedAttributeStr}
        </md:AttributeConsumingService>
METADATA_TEMPLATE;
        }

        $spEntityId = htmlspecialchars($sp['entityId'], ENT_QUOTES);
        $acsUrl = htmlspecialchars($sp['assertionConsumerService']['url'], ENT_QUOTES);
        $metadata = <<<METADATA_TEMPLATE
<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     validUntil="{$validUntilTime}"
                     cacheDuration="PT{$cacheDuration}S"
                     entityID="{$spEntityId}">
    <md:SPSSODescriptor AuthnRequestsSigned="{$strAuthnsign}" WantAssertionsSigned="{$strWsign}" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
{$sls}        <md:NameIDFormat>{$sp['NameIDFormat']}</md:NameIDFormat>
        <md:AssertionConsumerService Binding="{$sp['assertionConsumerService']['binding']}"
                                     Location="{$acsUrl}"
                                     index="1" />
        {$strAttributeConsumingService}
    </md:SPSSODescriptor>{$strOrganization}{$strContacts}
</md:EntityDescriptor>
METADATA_TEMPLATE;
        return $metadata;
    }

    /**
     * Signs the metadata with the key/cert provided
     *
     * @param string $metadata        SAML Metadata XML
     * @param string $key             x509 key
     * @param string $cert            x509 cert
     * @param string $signAlgorithm   Signature algorithm method
     * @param string $digestAlgorithm Digest algorithm method
     *
     * @return string Signed Metadata
     *
     * @throws Exception
     */
    public static function signMetadata($metadata, $key, $cert, $signAlgorithm = XMLSecurityKey::RSA_SHA256, $digestAlgorithm = XMLSecurityDSig::SHA256)
    {
        return Utils::addSign($metadata, $key, $cert, $signAlgorithm, $digestAlgorithm);
    }

    /**
     * Adds the x509 descriptors (sign/encryption) to the metadata
     * The same cert will be used for sign/encrypt
     *
     * @param string $metadata       SAML Metadata XML
     * @param string $cert           x509 cert
     * @param bool   $wantsEncrypted Whether to include the KeyDescriptor for encryption
     *
     * @return string Metadata with KeyDescriptors
     *
     * @throws Exception
     */
    public static function addX509KeyDescriptors($metadata, $cert, $wantsEncrypted = true)
    {
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;
        try {
            $xml = Utils::loadXML($xml, $metadata);
            if (!$xml) {
                throw new Exception('Error parsing metadata');
            }
        } catch (Exception $e) {
            throw new Exception('Error parsing metadata. '.$e->getMessage());
        }

        $formatedCert = Utils::formatCert($cert, false);
        $x509Certificate = $xml->createElementNS(Constants::NS_DS, 'X509Certificate', $formatedCert);

        $keyData = $xml->createElementNS(Constants::NS_DS, 'ds:X509Data');
        $keyData->appendChild($x509Certificate);

        $keyInfo = $xml->createElementNS(Constants::NS_DS, 'ds:KeyInfo');
        $keyInfo->appendChild($keyData);

        $keyDescriptor = $xml->createElementNS(Constants::NS_MD, "md:KeyDescriptor");

        $SPSSODescriptor = $xml->getElementsByTagName('SPSSODescriptor')->item(0);
        $SPSSODescriptor->insertBefore($keyDescriptor->cloneNode(), $SPSSODescriptor->firstChild);
        if ($wantsEncrypted === true) {
            $SPSSODescriptor->insertBefore($keyDescriptor->cloneNode(), $SPSSODescriptor->firstChild);
        }

        $signing = $xml->getElementsByTagName('KeyDescriptor')->item(0);
        $signing->setAttribute('use', 'signing');
        $signing->appendChild($keyInfo);

        if ($wantsEncrypted === true) {
            $encryption = $xml->getElementsByTagName('KeyDescriptor')->item(1);
            $encryption->setAttribute('use', 'encryption');

            $encryption->appendChild($keyInfo->cloneNode(true));
        }

        return $xml->saveXML();
    }
}
