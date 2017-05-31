<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:param name="lang" select="undef"/>


<!-- 

 -->
<xsl:template match="node()|@*">
   <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
   </xsl:copy>
</xsl:template>




<xsl:template match="//trigger/description">
	<description>
		<xsl:copy-of select="@*|b/@*" /> <!-- copy all attributes, including lang -->

		<xsl:choose>
			<xsl:when test="@lang='RU'">
				<xsl:value-of  select="concat('Последнее значение: {ITEM.LASTVALUE1}.',.)"/>
			</xsl:when>
			<xsl:otherwise >
				<xsl:value-of  select="concat('Last value: {ITEM.LASTVALUE1}.',.)"/>
			</xsl:otherwise>
		</xsl:choose>
		
		
	</description>
    <!--    -->
</xsl:template>

</xsl:stylesheet>
