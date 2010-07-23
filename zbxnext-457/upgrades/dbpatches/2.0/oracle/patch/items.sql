alter table items modify units           nvarchar2(255);

UPDATE items SET units='Bps' WHERE type=9 AND units='bps';