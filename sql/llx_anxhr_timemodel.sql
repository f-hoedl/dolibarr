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


CREATE TABLE llx_anxhr_timemodel(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	ref VARCHAR(128) NOT NULL,
	label VARCHAR(255),
	model_type VARCHAR(16) DEFAULT 'fixed' NOT NULL,
	weekly_hours DOUBLE(6,2),
	daily_minutes_json VARCHAR(255),
	flex_start VARCHAR(5),
	flex_end VARCHAR(5),
	core_start VARCHAR(5),
	core_end VARCHAR(5),
	flex_period_months INTEGER DEFAULT 1,
	max_carry_plus_min INTEGER,
	max_carry_minus_min INTEGER,
	break_rule VARCHAR(16) DEFAULT 'auto',
	normal_daily_max_min INTEGER DEFAULT 600,
	kv_code VARCHAR(32),
	note_private TEXT,
	status INTEGER DEFAULT 1 NOT NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER,
	fk_user_modif INTEGER,
	import_key VARCHAR(14)
) ENGINE=innodb;
