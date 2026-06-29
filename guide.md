Recommended architecture

For this project, I would use:

Frontend: React with Vite, not Next.js

Backend: Laravel

Admin/back office: Filament

Database: PostgreSQL

Storage: S3-compatible object storage or SharePoint connector layer

Queue/jobs: Redis + Laravel queues

Search: PostgreSQL full-text at first, Elasticsearch/OpenSearch only if needed later

Document conversion: LibreOffice/unoconv or external conversion service

AI layer: isolated service for comparison, extraction, clause/risk analysis

Auth: SSO/AD if EFES uses corporate identity, otherwise Laravel Sanctum/Passport

Notifications: email first, optional SMS/Teams later

Why React instead of Next.js

This is an internal workflow product, not a public SEO-driven website. Most value is in authenticated dashboards, forms, workflow state, document preview, approvals, and integrations. Next.js is possible, but it adds framework overhead without giving major business value here. For this case, React + Vite + Laravel API is the cleaner, faster, cheaper choice.

What must be built
Core modules

Authentication and RBAC

Initiator

Manager

Lawyer / Super User

Admin 

Document Flow Tecnical Task

Partner / Counterparty management

create/edit partner

BIN/IIN uniqueness checks

statutory documents

bank details / email

reliability check

blacklist with mandatory reason 

Document Flow Tecnical Task

Document/task creation

template vs non-template flow

document categories

field validation

protected editable sections in templates

route engine by type/category 

Document Flow Tecnical Task

Workflow engine

standard and simplified routes

parallel/sequential approval

add reviewer

delegate review

fast-track

status timeline / action history / deadlines 

Document Flow Tecnical Task

Document management

versioning

preview

compare versions

PDF generation after approval

unique registration/graphical ID 

Document Flow Tecnical Task

Signing

e-signature integration

signed file upload

signed-vs-approved comparison 

Document Flow Tecnical Task

Archive / registry

archive card

filters

Excel export by year/type

finalized documents section without approval flow 

Document Flow Tecnical Task

 

Document Flow Tecnical Task

Notifications and reminders

email reminders

due dates / overdue flags 

Document Flow Tecnical Task

Substitution / absence management

acting approver/substitute user 

Document Flow Tecnical Task

Migration and integrations

DocLogix counterparties/documents

ADATA

Paragraph

government reliability source

e-sign provider API 

Document Flow Tecnical Task

 

Contract_Management_in_KZ_v1

AI features

field extraction

risk / clause analysis

metadata validation

signed document comparison

recommendations / flags 

Document Flow Tecnical Task

 

Document Flow Tecnical Task

 

Document Flow Tecnical Task

Effort estimate
1) Discovery and technical specification

This is mandatory because several critical items are not yet specified:

exact DocLogix migration format

ADATA / Paragraph API details

e-sign provider choice

SharePoint vs internal storage

AI rules and legal acceptance criteria

template editing rules

archive numbering rules

mobile scope: responsive web or native app

Estimate: 3–4 weeks
Hours: 120–180

2) UI/UX and product design

process mapping

screen flows

design system

role-specific dashboards

document detail screens

task workflow screens

archive/search screens

mobile responsive layouts

Estimate: 4–6 weeks
Hours: 160–240

3) Backend development (Laravel + Filament + APIs)

auth / roles / permissions

partner module

blacklist

workflow engine

task/document modules

versioning/history

deadline engine

substitutions

notifications

archive/registry

export

admin settings

audit logging

API integrations

migration tools

AI orchestration

Estimate: 14–20 weeks
Hours: 900–1,300

4) Frontend development (React)

login / auth

dashboards

partner forms

task creation

document upload / preview

review/approval flows

comments/timeline

archive search

mobile responsive UX

notifications / statuses

Estimate: 12–18 weeks
Hours: 700–1,000

5) Document processing / AI / e-sign / conversion

This is the hardest technical block.

docx template handling

editable/non-editable sections

PDF generation

document comparison

AI validation

AI extraction/risk flags

e-sign API

signed file reconciliation

Estimate: 8–12 weeks
Hours: 350–600

6) Migration + integrations

DocLogix mapping

import scripts

reconciliation

ADATA / Paragraph integration

government trustworthiness service

SharePoint/storage integration if needed

Estimate: 6–10 weeks
Hours: 300–500

7) QA, UAT, hardening, deployment

manual QA

workflow scenario testing

permission testing

document edge cases

load/security checks

UAT fixes

release

user manual

Estimate: 6–8 weeks
Hours: 300–450