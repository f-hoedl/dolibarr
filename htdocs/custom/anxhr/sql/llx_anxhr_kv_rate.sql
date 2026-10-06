-- Copyright (C) 2026  ANX HR contributors
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_anxhr_kv_rate(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	kv_code VARCHAR(32) NOT NULL,
	label VARCHAR(255),
	valid_from DATE NOT NULL,
	weekly_hours DOUBLE(6,2) DEFAULT 40 NOT NULL,
	ot_pct INTEGER DEFAULT 50,
	ot_night_pct INTEGER DEFAULT 100,
	ot_sunday_pct INTEGER DEFAULT 100,
	parttime_extra_pct INTEGER DEFAULT 25,
	za_factor DOUBLE(4,2) DEFAULT 1.5,
	dec24_free SMALLINT DEFAULT 0,
	dec31_free SMALLINT DEFAULT 0,
	night_start VARCHAR(5) DEFAULT '22:00',
	night_end VARCHAR(5) DEFAULT '06:00',
	note_private TEXT,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER,
	fk_user_modif INTEGER,
	import_key VARCHAR(14)
) ENGINE=innodb;
