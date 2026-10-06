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

ALTER TABLE llx_anxhr_checklist_template_item ADD INDEX idx_anxhr_checklist_template_item_fk_template (fk_template);
ALTER TABLE llx_anxhr_checklist_template_item ADD CONSTRAINT fk_anxhr_checklist_template_item_fk_template FOREIGN KEY (fk_template) REFERENCES llx_anxhr_checklist_template(rowid);
