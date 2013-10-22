ALTER TABLE ONLY dservices ALTER dserviceid DROP DEFAULT,
			   ALTER dhostid DROP DEFAULT,
			   ALTER dcheckid DROP DEFAULT,
			   ALTER key_ SET DEFAULT '',
			   ALTER value SET DEFAULT '',
			   ADD dns varchar(64) DEFAULT '' NOT NULL;
DELETE FROM dservices WHERE NOT EXISTS (SELECT 1 FROM dhosts WHERE dhosts.dhostid=dservices.dhostid);
DELETE FROM dservices WHERE NOT EXISTS (SELECT 1 FROM dchecks WHERE dchecks.dcheckid=dservices.dcheckid);
ALTER TABLE ONLY dservices ADD CONSTRAINT c_dservices_1 FOREIGN KEY (dhostid) REFERENCES dhosts (dhostid) ON DELETE CASCADE;
ALTER TABLE ONLY dservices ADD CONSTRAINT c_dservices_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE;
