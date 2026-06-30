-- Run this on Supabase SQL editor before destructive department drops.
-- Creates timestamped backup tables for retirement rollback/audit.

create table if not exists backup_departments_snapshot as
select now() as exported_at, d.*
from departments d;

create table if not exists backup_document_scans_snapshot as
select now() as exported_at, s.*
from document_scans s;

create table if not exists backup_document_route_steps_snapshot as
select now() as exported_at, rs.*
from document_route_steps rs;

create table if not exists backup_routing_rules_snapshot as
select now() as exported_at, rr.*
from routing_rules rr;

create table if not exists backup_department_notifications_snapshot as
select now() as exported_at, dn.*
from department_notifications dn;

create table if not exists backup_users_department_snapshot as
select now() as exported_at, id as user_id, department_id
from users;

create table if not exists backup_documents_current_department_snapshot as
select now() as exported_at, id as document_id, current_department_id
from documents;

create table if not exists backup_document_attachments_department_snapshot as
select now() as exported_at, id as attachment_id, department_id
from document_attachments;
