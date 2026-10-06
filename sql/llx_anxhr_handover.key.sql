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

ALTER TABLE llx_anxhr_handover ADD UNIQUE INDEX uk_anxhr_handover_ref (ref, entity);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_entity (entity);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_fk_user (fk_user);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_category (category);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_status (status);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_date_out (date_out);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_date_return (date_return);
ALTER TABLE llx_anxhr_handover ADD INDEX idx_anxhr_handover_fk_asset (fk_asset);
ALTER TABLE llx_anxhr_handover ADD CONSTRAINT fk_anxhr_handover_fk_user FOREIGN KEY (fk_user) REFERENCES llx_user(rowid);
