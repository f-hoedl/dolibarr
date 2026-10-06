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

-- HR document vault: metadata of encrypted employee documents (payslips, contracts, certificates, time sheets).
-- The file itself is stored encrypted under documents/anxhr/vault/<fk_user>/<filename_stored>.
CREATE TABLE llx_anxhr_vault_doc(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	ref VARCHAR(128) NOT NULL,
	fk_user INTEGER NOT NULL,
	category VARCHAR(32) NOT NULL,
	period VARCHAR(7) NULL,
	label VARCHAR(255) NULL,
	filename_original VARCHAR(255) NOT NULL,
	filename_stored VARCHAR(255) NOT NULL,
	filesize INTEGER DEFAULT 0 NOT NULL,
	mimetype VARCHAR(128) NULL,
	sha256 VARCHAR(64) NOT NULL,
	encrypted TINYINT DEFAULT 1 NOT NULL,
	uploaded_by INTEGER NULL,
	date_upload DATETIME NULL,
	visible_to_employee TINYINT DEFAULT 1 NOT NULL,
	first_viewed_at DATETIME NULL,
	note_private TEXT NULL,
	status INTEGER DEFAULT 1 NOT NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER NOT NULL,
	fk_user_modif INTEGER NULL,
	import_key VARCHAR(14) NULL
) ENGINE=innodb;
