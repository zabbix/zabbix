ALTER TABLE profiles
	MODIFY profileid bigint unsigned NOT NULL,
	MODIFY userid bigint unsigned NOT NULL;
DELETE FROM profiles WHERE NOT userid IN (SELECT userid FROM users);
DELETE FROM profiles WHERE idx LIKE 'web.%.sort' OR idx LIKE 'web.%.sortorder';
ALTER TABLE profiles ADD CONSTRAINT c_profiles_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;

UPDATE profiles SET idx = 'web.screens.period' WHERE idx = 'web.charts.period';
UPDATE profiles SET idx = 'web.screens.stime' WHERE idx = 'web.charts.stime';
UPDATE profiles SET idx = 'web.screens.timelinefixed' WHERE idx = 'web.charts.timelinefixed';
