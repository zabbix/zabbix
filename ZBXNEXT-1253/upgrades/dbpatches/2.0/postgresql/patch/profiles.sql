ALTER TABLE ONLY profiles
	ALTER profileid DROP DEFAULT,
	ALTER userid DROP DEFAULT;
DELETE FROM profiles WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=profiles.userid);
DELETE FROM profiles WHERE idx LIKE 'web.%.sort' OR idx LIKE 'web.%.sortorder';
ALTER TABLE ONLY profiles ADD CONSTRAINT c_profiles_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;

UPDATE profiles SET idx = 'web.screens.period' WHERE idx = 'web.charts.period';
UPDATE profiles SET idx = 'web.screens.stime' WHERE idx = 'web.charts.stime';
UPDATE profiles SET idx = 'web.screens.timelinefixed' WHERE idx = 'web.charts.timelinefixed';
