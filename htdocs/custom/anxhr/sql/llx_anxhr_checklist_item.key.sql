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

ALTER TABLE llx_anxhr_checklist_item ADD INDEX idx_anxhr_checklist_item_fk_checklist (fk_checklist);
ALTER TABLE llx_anxhr_checklist_item ADD INDEX idx_anxhr_checklist_item_fk_user_responsible (fk_user_responsible);
ALTER TABLE llx_anxhr_checklist_item ADD INDEX idx_anxhr_checklist_item_fk_handover (fk_handover);
ALTER TABLE llx_anxhr_checklist_item ADD INDEX idx_anxhr_checklist_item_status (status);
ALTER TABLE llx_anxhr_checklist_item ADD INDEX idx_anxhr_checklist_item_date_due (date_due);
ALTER TABLE llx_anxhr_checklist_item ADD CONSTRAINT fk_anxhr_checklist_item_fk_checklist FOREIGN KEY (fk_checklist) REFERENCES llx_anxhr_checklist(rowid);
