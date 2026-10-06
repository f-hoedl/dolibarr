-- Copyright (C) 2026 ANX HR contributors
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

-- Initial data of module anxhr. Executed at module activation (idempotent thanks to unique keys).

-- Deadline types
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'PROBATION_END', 'End of probation period', '30,14', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'FIXED_TERM_END', 'End of fixed-term contract', '60,30', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'CONTRACT_END', 'End of employment', '30,14,7', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'CERTIFICATE', 'Certificate expiry', '60,30', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'PERMIT', 'Work or residence permit expiry', '90,60,30', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'REVIEW', 'Employee review', '14,7', 1);
INSERT INTO llx_c_anxhr_deadline_type (entity, code, label, remind_days, active) VALUES (__ENTITY__, 'CUSTOM', 'Other deadline', '30,14,7', 1);

-- Handover categories
INSERT INTO llx_c_anxhr_handover_cat (entity, code, label, active) VALUES (__ENTITY__, 'HARDWARE', 'Hardware', 1);
INSERT INTO llx_c_anxhr_handover_cat (entity, code, label, active) VALUES (__ENTITY__, 'KEY', 'Key', 1);
INSERT INTO llx_c_anxhr_handover_cat (entity, code, label, active) VALUES (__ENTITY__, 'TOKEN', 'Token or badge', 1);
INSERT INTO llx_c_anxhr_handover_cat (entity, code, label, active) VALUES (__ENTITY__, 'ACCESS', 'System access', 1);
INSERT INTO llx_c_anxhr_handover_cat (entity, code, label, active) VALUES (__ENTITY__, 'OTHER', 'Other', 1);

-- Business events for notifications and agenda.
-- elementtype 'anxhr' is required for the Notification module (it checks $conf->{elementtype}->enabled).
INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang) VALUES ('ANXHR_DEADLINE_REMIND', 'HR deadline reminder', 'Executed when an HR deadline reminder is due', 'anxhr', 500100);
INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang) VALUES ('ANXHR_TIMEPERIOD_APPROVE', 'Monthly time period approved', 'Executed when a monthly time period is approved', 'anxhr', 500101);
INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang) VALUES ('ANXHR_TIMECORRECTION_APPROVE', 'Time correction approved', 'Executed when a time correction request is approved', 'anxhr', 500102);
INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang) VALUES ('ANXHR_CONTRACT_CREATE', 'Employment contract created', 'Executed when an employment contract is created', 'anxhr_contract@anxhr', 500103);
