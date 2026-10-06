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


CREATE TABLE llx_anxhr_time_correction(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_user INTEGER NOT NULL,
	day DATE NOT NULL,
	old_json TEXT,
	new_json TEXT,
	reason TEXT,
	status INTEGER DEFAULT 0 NOT NULL,
	fk_user_approve INTEGER,
	date_approve DATETIME,
	approve_comment TEXT,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER,
	fk_user_modif INTEGER,
	import_key VARCHAR(14)
) ENGINE=innodb;
