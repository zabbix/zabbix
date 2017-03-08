<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:param name="template_suffix" select="undef"/>


<!-- 

 -->
<xsl:template match="node()|@*">
   <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
   </xsl:copy>
</xsl:template>



<xsl:template match="/*/template/name">
	<xsl:copy>
		<xsl:copy-of  select="concat(.,'_SNMP_PLACEHOLDER')"/>
	</xsl:copy>
    <!--    -->
</xsl:template>

</xsl:stylesheet>

