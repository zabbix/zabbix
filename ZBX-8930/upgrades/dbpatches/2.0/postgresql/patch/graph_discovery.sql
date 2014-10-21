CREATE TABLE graph_discovery (
	graphdiscoveryid         bigint                                    NOT NULL,
	graphid                  bigint                                    NOT NULL,
	parent_graphid           bigint                                    NOT NULL,
	name                     varchar(128)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (graphdiscoveryid)
);
CREATE UNIQUE INDEX graph_discovery_1 on graph_discovery (graphid,parent_graphid);
ALTER TABLE ONLY graph_discovery ADD CONSTRAINT c_graph_discovery_1 FOREIGN KEY (graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE ONLY graph_discovery ADD CONSTRAINT c_graph_discovery_2 FOREIGN KEY (parent_graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
