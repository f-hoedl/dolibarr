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

ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_entity (entity);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_fk_user (fk_user);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_fk_contract (fk_contract);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_fk_user_responsible (fk_user_responsible);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_status (status);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_date_due (date_due);
ALTER TABLE llx_anxhr_deadline ADD INDEX idx_anxhr_deadline_type (type);
ALTER TABLE llx_anxhr_deadline ADD CONSTRAINT fk_anxhr_deadline_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user(rowid);
ALTER TABLE llx_anxhr_deadline ADD CONSTRAINT fk_anxhr_deadline_fk_contract FOREIGN KEY (fk_contract) REFERENCES llx_anxhr_contract(rowid);
