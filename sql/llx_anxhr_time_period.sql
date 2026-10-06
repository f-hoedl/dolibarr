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


CREATE TABLE llx_anxhr_time_period(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_user INTEGER NOT NULL,
	year INTEGER NOT NULL,
	month INTEGER NOT NULL,
	target_min INTEGER DEFAULT 0 NOT NULL,
	worked_min INTEGER DEFAULT 0 NOT NULL,
	overtime50_min INTEGER DEFAULT 0 NOT NULL,
	overtime100_min INTEGER DEFAULT 0 NOT NULL,
	extra_parttime_min INTEGER DEFAULT 0 NOT NULL,
	night_min INTEGER DEFAULT 0 NOT NULL,
	vacation_days DOUBLE(6,2) DEFAULT 0,
	sick_days DOUBLE(6,2) DEFAULT 0,
	balance_start_min INTEGER DEFAULT 0 NOT NULL,
	balance_end_min INTEGER DEFAULT 0 NOT NULL,
	overtime_paid_min INTEGER DEFAULT 0 NOT NULL,
	za_taken_min INTEGER DEFAULT 0 NOT NULL,
	status INTEGER DEFAULT 0 NOT NULL,
	fk_user_confirm INTEGER,
	date_confirm DATETIME,
	fk_user_approve INTEGER,
	date_approve DATETIME,
	note TEXT,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER,
	fk_user_modif INTEGER,
	import_key VARCHAR(14)
) ENGINE=innodb;
