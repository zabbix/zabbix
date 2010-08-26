ALTER TABLE ONLY dservices ALTER dserviceid DROP DEFAULT,
			   ALTER dhostid DROP DEFAULT,
			   ALTER dcheckid DROP DEFAULT;
DELETE FROM dservices WHERE NOT dhostid IN (SELECT dhostid FROM dhosts);
DELETE FROM dservices WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE ONLY dservices ADD CONSTRAINT c_dservices_1 FOREIGN KEY (dhostid) REFERENCES dhosts (dhostid) ON DELETE CASCADE;
ALTER TABLE ONLY dservices ADD CONSTRAINT c_dservices_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE;
