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

-- Items of a checklist instance. Column position is used instead of rank (rank is a reserved word in MySQL 8)
CREATE TABLE llx_anxhr_checklist_item(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_checklist INTEGER NOT NULL,
	position INTEGER DEFAULT 0 NOT NULL,
	label VARCHAR(255) NOT NULL,
	responsible_role VARCHAR(16) NULL,
	fk_user_responsible INTEGER NULL,
	date_due DATE NULL,
	date_done DATETIME NULL,
	fk_user_done INTEGER NULL,
	fk_handover INTEGER NULL,
	creates_handover TINYINT DEFAULT 0 NOT NULL,
	handover_category VARCHAR(32) NULL,
	status INTEGER DEFAULT 0 NOT NULL,
	note TEXT NULL
) ENGINE=innodb;
