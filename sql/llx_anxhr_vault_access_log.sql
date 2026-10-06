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

-- Access log of the HR document vault (upload, view, download, delete, denied). Never updated, only inserted.
CREATE TABLE llx_anxhr_vault_access_log(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_doc INTEGER NULL,
	fk_user INTEGER NULL,
	action VARCHAR(16) NOT NULL,
	ip VARCHAR(45) NULL,
	user_agent VARCHAR(255) NULL,
	datec DATETIME NOT NULL
) ENGINE=innodb;
