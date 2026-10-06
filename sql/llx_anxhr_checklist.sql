-- Copyright (C) 2026 ANX
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

-- Checklist instance (onboarding, offboarding, transfer) of an employee
CREATE TABLE llx_anxhr_checklist(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	ref VARCHAR(128) NOT NULL,
	fk_user INTEGER NOT NULL,
	fk_template INTEGER NULL,
	checklist_type VARCHAR(16) NOT NULL,
	anchor_date DATE NOT NULL,
	status INTEGER DEFAULT 0 NOT NULL,
	progress_pct INTEGER DEFAULT 0 NOT NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER NOT NULL,
	fk_user_modif INTEGER NULL,
	import_key VARCHAR(14) NULL
) ENGINE=innodb;
