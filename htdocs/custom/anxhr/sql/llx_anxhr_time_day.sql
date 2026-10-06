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


CREATE TABLE llx_anxhr_time_day(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_user INTEGER NOT NULL,
	day DATE NOT NULL,
	target_min INTEGER DEFAULT 0 NOT NULL,
	worked_min INTEGER DEFAULT 0 NOT NULL,
	break_min INTEGER DEFAULT 0 NOT NULL,
	diff_min INTEGER DEFAULT 0 NOT NULL,
	overtime50_min INTEGER DEFAULT 0 NOT NULL,
	overtime100_min INTEGER DEFAULT 0 NOT NULL,
	extra_parttime_min INTEGER DEFAULT 0 NOT NULL,
	night_min INTEGER DEFAULT 0 NOT NULL,
	holiday_flag SMALLINT DEFAULT 0 NOT NULL,
	absence_code VARCHAR(32),
	open_entry SMALLINT DEFAULT 0 NOT NULL,
	violations_json TEXT,
	locked SMALLINT DEFAULT 0 NOT NULL,
	computed_at DATETIME,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
