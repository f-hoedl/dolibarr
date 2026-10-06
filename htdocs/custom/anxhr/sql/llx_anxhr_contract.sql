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

-- Employment contract (historised: one record per change)
CREATE TABLE llx_anxhr_contract(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity INTEGER DEFAULT 1 NOT NULL,
	ref VARCHAR(128) NOT NULL,
	fk_user INTEGER NOT NULL,
	date_start DATE NOT NULL,
	date_end DATE NULL,
	probation_end DATE NULL,
	fixed_term_end DATE NULL,
	weekly_hours DOUBLE(8,2) NULL,
	fk_timemodel INTEGER NULL,
	kv_code VARCHAR(32) NULL,
	kv_group VARCHAR(64) NULL,
	kv_level VARCHAR(32) NULL,
	kv_level_date DATE NULL,
	employment_type VARCHAR(32) NULL,
	notice_period_weeks INTEGER NULL,
	homeoffice_days_week DOUBLE(4,2) NULL,
	homeoffice_agreement_date DATE NULL,
	vacation_days_year DOUBLE(6,2) DEFAULT 25,
	vacation_in_hours TINYINT DEFAULT 0 NOT NULL,
	prior_service_months INTEGER DEFAULT 0,
	disability_status VARCHAR(255) NULL,
	note_private TEXT NULL,
	status INTEGER DEFAULT 0 NOT NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat INTEGER NOT NULL,
	fk_user_modif INTEGER NULL,
	import_key VARCHAR(14) NULL
) ENGINE=innodb;
