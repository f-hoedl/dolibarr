-- Copyright (C) 2026  ANX HR contributors
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

-- Default data of ANX Time / ANX Travel. Executed by modAnxhr::init() (data*.sql files).
-- Idempotent: unique keys (kv_code, valid_from, entity), (ref, entity), (code, valid_from, entity) reject duplicates,
-- run_sql() accepts DB_ERROR_RECORD_ALREADY_EXISTS.

-- Collective agreement SWOE-KV (Sozialwirtschaft Oesterreich): 37h week, 24.12. and 31.12. free with pay.
INSERT INTO llx_anxhr_kv_rate (entity, kv_code, label, valid_from, weekly_hours, ot_pct, ot_night_pct, ot_sunday_pct, parttime_extra_pct, za_factor, dec24_free, dec31_free, night_start, night_end, date_creation) VALUES (__ENTITY__, 'SWOE', 'SWÖ-KV Sozialwirtschaft Österreich', '2022-01-01', 37, 50, 100, 100, 25, 1.5, 1, 1, '22:00', '06:00', NOW());
-- Generic legal default (AZG, no collective agreement specifics): 40h week.
INSERT INTO llx_anxhr_kv_rate (entity, kv_code, label, valid_from, weekly_hours, ot_pct, ot_night_pct, ot_sunday_pct, parttime_extra_pct, za_factor, dec24_free, dec31_free, night_start, night_end, date_creation) VALUES (__ENTITY__, 'AZG', 'AZG Standard (40h)', '2000-01-01', 40, 50, 100, 100, 25, 1.5, 0, 0, '22:00', '06:00', NOW());

-- Time models
INSERT INTO llx_anxhr_timemodel (entity, ref, label, model_type, weekly_hours, daily_minutes_json, flex_start, flex_end, core_start, core_end, flex_period_months, max_carry_plus_min, max_carry_minus_min, break_rule, normal_daily_max_min, kv_code, status, date_creation) VALUES (__ENTITY__, 'FLEX37', 'Gleitzeit 37h Mo-Fr', 'flex', 37, '{"1":444,"2":444,"3":444,"4":444,"5":444,"6":0,"7":0}', '06:00', '20:00', '09:00', '12:00', 1, 2400, 600, 'auto', 600, 'SWOE', 1, NOW());
INSERT INTO llx_anxhr_timemodel (entity, ref, label, model_type, weekly_hours, daily_minutes_json, flex_start, flex_end, core_start, core_end, flex_period_months, max_carry_plus_min, max_carry_minus_min, break_rule, normal_daily_max_min, kv_code, status, date_creation) VALUES (__ENTITY__, 'FIX40', 'Fixe Arbeitszeit 40h Mo-Fr', 'fixed', 40, '{"1":480,"2":480,"3":480,"4":480,"5":480,"6":0,"7":0}', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'auto', 600, 'AZG', 1, NOW());

-- Travel rates Austria (paragraph 26 Z 4 EStG, Kilometergeldverordnung, BBG 2025)
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'taggeld_in', 'Taggeld Inland', '1900-01-01', 26.40, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'taggeld_in', 'Taggeld Inland', '2025-01-01', 30.00, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'naechtigung_in', 'Naechtigungsgeld Inland', '1900-01-01', 15.00, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'naechtigung_in', 'Naechtigungsgeld Inland', '2025-01-01', 17.00, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_car', 'Kilometergeld PKW', '1900-01-01', 0.42, 30000, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_car', 'Kilometergeld PKW', '2025-01-01', 0.50, 30000, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_motorbike', 'Kilometergeld Motorrad', '1900-01-01', 0.24, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_motorbike', 'Kilometergeld Motorrad', '2025-01-01', 0.50, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_motorbike', 'Kilometergeld Motorrad', '2025-07-01', 0.25, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_bike', 'Kilometergeld Fahrrad', '1900-01-01', 0.38, 2500, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_bike', 'Kilometergeld Fahrrad', '2025-01-01', 0.50, 3000, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_bike', 'Kilometergeld Fahrrad', '2025-07-01', 0.25, 3000, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_passenger', 'Kilometergeld Mitfahrer', '1900-01-01', 0.05, NULL, NOW());
INSERT INTO llx_anxhr_travel_rate (entity, code, label, valid_from, amount, cap_per_year, date_creation) VALUES (__ENTITY__, 'km_passenger', 'Kilometergeld Mitfahrer', '2025-01-01', 0.15, NULL, NOW());
