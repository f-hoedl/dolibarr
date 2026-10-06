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

-- Emergency contacts of an employee (1:n)
CREATE TABLE llx_anxhr_emergency_contact(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_user INTEGER NOT NULL,
	name VARCHAR(255) NOT NULL,
	relation VARCHAR(128) NULL,
	phone VARCHAR(64) NULL,
	email VARCHAR(255) NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER NOT NULL,
	fk_user_modif INTEGER NULL,
	import_key VARCHAR(14) NULL
) ENGINE=innodb;
