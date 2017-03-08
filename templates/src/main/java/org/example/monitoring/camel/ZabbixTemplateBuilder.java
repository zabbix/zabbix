package org.example.monitoring.camel;

import org.apache.camel.builder.RouteBuilder;
import org.springframework.stereotype.Component;

@Component
public class ZabbixTemplateBuilder extends RouteBuilder {
 
  @Override
  public void configure() throws Exception {
    from("file:bin/in?noop=true&delay=30000&idempotentKey=${file:name}-${file:modified}")
    .log("Loading file: ${in.headers.CamelFileNameOnly}")
	.to("xslt:templates/to_metrics_add_name_placeholder.xsl?saxon=true") //will add _SNMP_PLACEHOLDER
    .to("xslt:templates/to_metrics.xsl?saxon=true")
    .to("file:bin/merged")
    .to("validator:templates/metrics.xsd")
	.multicast().parallelProcessing().to("direct:RU", "direct:EN");
  
    from("direct:RU")
	    .filter().xpath("//node()[@lang='RU']")
	    .log("Going to do Russian template")
		.setHeader("lang", simple("RU", String.class)).to("xslt:templates/to_metrics_lang.xsl?saxon=true")
		.to("log:result?level=DEBUG").multicast().parallelProcessing().to("direct:snmpv1", "direct:snmpv2");
	    
    
    
    from("direct:EN")
	    .log("Going to do English template")
		.setHeader("lang", simple("EN", String.class)).to("xslt:templates/to_metrics_lang.xsl?saxon=true")
		.to("log:result?level=DEBUG").multicast().parallelProcessing().to("direct:snmpv1", "direct:snmpv2");
	    
    //zabbix types: 4- snmpv2, 1-snmpv2 <xsl:variable name="snmp_item_type">4</xsl:variable>
    from("direct:snmpv1")
    	.setHeader("snmp_item_type", simple("1", String.class))
    	.setHeader("template_suffix", simple("SNMPv1", String.class))
    	.to("xslt:templates/to_zabbix_export.xsl?saxon=true")
    	.to("direct:zabbix_export");
    
    
    from("direct:snmpv2")
	    .setHeader("snmp_item_type", simple("4", String.class))
	    .setHeader("template_suffix", simple("SNMPv2", String.class))
		.to("xslt:templates/to_zabbix_export.xsl?saxon=true")
		.to("direct:zabbix_export");
    
    from("direct:zabbix_export")
		//with lang.setBody(body().regexReplaceAll("_SNMP_PLACEHOLDER", simple(" ${in.headers.template_suffix} ${in.headers.lang}")))
		.setBody(body().regexReplaceAll("_SNMP_PLACEHOLDER", simple(" ${in.headers.template_suffix}"))) //w/o lang
		.setHeader("subfolder",simple("${in.headers.CamelFileName.split('_')[1]}",String.class))
		.setHeader("CamelOverruleFileName",simple("${in.headers.subfolder}/${in.headers.CamelFileName.replace('.xml','')}_${in.headers.template_suffix}_${in.headers.lang}.xml"))
		.to("file:bin/out/");
	


  } 
}

