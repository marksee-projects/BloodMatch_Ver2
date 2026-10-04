# BloodMatch System Requirements & Feature Checklist

## Purpose

This file is the **requirements checklist for the BloodMatch codebase**.

The coding agent should use this document to inspect the current implementation and report:

- **IMPLEMENTED** — the requirement is working and can be demonstrated.
- **PARTIAL** — some of the requirement exists, but important behavior, validation, UI, database logic, or integration is missing.
- **MISSING** — the requirement is not implemented.
- **UNVERIFIED** — the implementation may exist, but the agent cannot confirm that it works from the code/configuration/tests.

Do **not** invent new product requirements. Use the requirements below as the source-of-truth baseline for the current project documentation.

---

## 1. Project Scope

**System:** BloodMatch: A Peer-to-Peer Blood Donor Matching Platform for DeMolay Bataan

**Primary purpose:** Provide a web-based platform for DeMolay Bataan members to:

1. Create accounts and manage profiles.
2. Submit blood requests.
3. Register as blood donors.
4. Verify donor/member information through authorized officers.
5. Match blood requests with compatible donors.
6. Filter potential donors by chapter and municipality.
7. Notify users about matches and important account/request updates.
8. Track donor participation and donation workflow.
9. Monitor blood demand and system activity.
10. Generate reports for authorized officers.

**Scope limitation:** The platform is for DeMolay Bataan members and eligible donors/requesters. It does not collect, store, transport, or medically process blood and does not replace medical or clinical judgment.

---

# 2. User Roles and Access Control

The system must enforce role-based access control with three main access levels:

### 2.1 Donor / Member User

A member/donor must be able to:

- Register an account.
- Log in securely.
- Manage their personal/profile information.
- Provide chapter information.
- Provide municipality/location information.
- Provide blood type information.
- Submit verification documents when required.
- View verification/account status.
- Register or maintain donor information.
- View compatible blood requests/matches applicable to them.
- Respond to blood requests.
- Control/view their donor status.
- Receive notifications and email alerts.
- Track relevant request/donation status.

### 2.2 Chapter Officer

A chapter officer must be able to:

- Access an officer dashboard.
- Review donor/member information.
- Review submitted verification documents.
- Approve or reject donor verification.
- Monitor donor status.
- Check blood requests.
- Monitor matching/request activity.
- Confirm completed donation workflow events where required.
- View relevant chapter/region activity.
- Access authorized reports and analytics.
- Review system activity/audit information where authorized.

### 2.3 System Administrator

A system administrator must be able to:

- Manage users.
- Manage chapters.
- Manage system-wide data/configuration.
- Monitor users, requests, donor activity, and chapter performance.
- View system-wide dashboards.
- Access audit logs.
- Access authorized analytics/reports.
- Manage administrative functions unavailable to normal members.

### 2.4 Authorization Requirements

The implementation must prevent users from accessing functions outside their role.

Examples:

- A normal member must not be able to approve their own verification.
- A normal member must not access admin/chapter-officer dashboards.
- Only authorized officers/admins may access protected reports and audit logs.
- Protected verification documents and personal data must not be exposed to unauthorized users.

---

# 3. Authentication and User Management

## 3.1 Registration

The system must allow DeMolay members to create an account.

Registration must support the information required by the project, including:

- Personal information.
- Email/account credentials.
- Chapter.
- Municipality/location.
- Blood type.
- Relevant donor information.

## 3.2 Login

The system must allow registered users to securely log in using their account credentials.

## 3.3 Account Status

The system must maintain account status information, including relevant verification/status states.

The implementation should support the project's verification and standby/deactivation workflows.

## 3.4 Profile Management

Users must be able to view/update authorized profile information.

Profile information described in the project includes:

- Personal information.
- Location/municipality.
- Chapter.
- Blood type.
- Donor status.
- Verification documents/status.

---

# 4. Verification and Document Management

The system must provide a secure verification process for members/donors.

## Required capabilities

- User can submit a verification document.
- The system stores the submitted verification information securely.
- Authorized officers can visually review the submitted document.
- Authorized officers can approve or reject verification.
- Verification status is recorded.
- Users can see the result/status of their verification.
- Verification actions should be auditable.

### Source-document terminology note

The project documentation mentions both:

- uploading a **donor national ID**, and
- reviewing/approving **donor cards / verification documents**.

The implementation should therefore treat this as a **verification-document workflow** rather than silently assuming that only one specific document type is required.

---

# 5. Blood Request Management

The system must allow a requester/member to create and manage blood requests.

## Required blood request capabilities

A blood request must support:

- Requested blood type.
- Location of the request.
- Request status.
- Matching donor information/status.
- Request lifecycle management.

## Request lifecycle

The system must support request states/actions covering the workflow from:

**Opened → Matched/Responded → Fulfilled OR Cancelled OR Expired**

The exact status names may vary in implementation, but the behavior must support the documented lifecycle.

## Requester capabilities

The requester must be able to:

- Create a request.
- View their requests.
- Track request status.
- View available/matching donor information as permitted.
- Edit requests where allowed.
- Cancel requests where allowed.
- Receive relevant notifications.

---

# 6. Blood Compatibility and Cascading Match Engine

This is a **core required feature**.

The system must provide a matching engine that:

1. Receives an active blood request.
2. Determines biologically compatible donor blood types using the system's compatibility rules.
3. Applies the project's **multi-tier biological substitution hierarchy / cascading match logic**.
4. Filters candidate donors based on required location criteria.
5. Returns compatible donor results.
6. Considers donor eligibility/status.
7. Provides results without replacing medical or clinical judgment.

## Matching factors

The engine must consider, at minimum:

- Requested blood type.
- Donor blood type.
- Biological compatibility rules.
- Donor eligibility/status.
- Chapter.
- Municipality/location.

## Cascading matching requirement

The engine must support the project's stated goal of finding a compatible donor result using a **multi-tier biological substitution hierarchy**.

The implementation should not reduce the feature to an exact blood-type lookup only.

## Performance requirement

The location-based match engine must return filtered donor results within:

**3 seconds**

---

# 7. Location-Based Matching

The system must support localized donor filtering.

Required location factors:

- Chapter.
- Municipality.

The system must be able to filter potential donors according to their chapter and municipality.

The system documentation describes the main matching goal as finding donors in the relevant local/chapter area.

---

# 8. Donor Management and Eligibility

The system must maintain donor participation information.

## Required donor information

The system must be able to store/manage:

- Blood type.
- Chapter.
- Municipality/location.
- Donor status.
- Verification status.
- Relevant donation history/status needed for eligibility.

## Eligibility

The donor management module must check whether a donor is currently eligible to donate based on the required waiting period after previous donations.

## Donor status

The system must support donor status changes required by the workflow.

At minimum, the system must support the project's automated standby behavior for non-responsive donors.

---

# 9. Automated Soft-Deactivation / Standby

This is a required automated feature.

The system must automatically place a donor into **standby** when the donor fails to respond within:

**42 hours**

The implementation must:

- Track the relevant response/match timing.
- Detect when the 42-hour period is reached.
- Automatically update the donor's status to standby.
- Prevent the system from treating that donor as normally available when the standby status should exclude them.
- Record the status change where appropriate for monitoring/audit purposes.

---

# 10. Donation Workflow

The system must support the documented donation workflow:

**Donor receives/responds to match → donation occurs → donation is completed/confirmed → blood request can be fulfilled**

Required capabilities include:

- Donor response to a match/request.
- Recording the relevant response state.
- Recording/completing the donation workflow.
- Officer confirmation of completed donation where required.
- Updating the associated blood request when fulfillment is confirmed.

The system is for coordination and matching only; it does not physically handle blood.

---

# 11. Notifications and Email

The system must provide automated notifications.

## Notification triggers

The system must be able to notify users about:

- Compatible/matched blood requests.
- Verification results.
- Account status updates.
- Blood request status updates.
- Other important system status changes defined by the workflow.

## Email notifications

The system must automatically send email alerts for required matches and updates.

## Notification quality

The notification module should avoid repeatedly sending duplicate notifications for the same event.

---

# 12. Regional Blood Demand Map

The system must provide an interactive map/dashboard showing aggregated active blood requests.

Required behavior:

- Display active blood demand by region/location.
- Provide a real-time or near-real-time representation of active requests as intended by the system.
- Aggregate information in a way that protects individual user privacy.
- Allow authorized officers/admins to monitor demand across Bataan/chapter regions.

The map is an operational monitoring feature, not a public disclosure of sensitive personal information.

---

# 13. Analytics and Reporting

The system must provide an analytics/reporting module for authorized officers.

## Required reports

The system must support:

- Daily reports.
- Weekly reports.
- Monthly reports.
- Yearly reports.

## Required reporting areas

Reports should cover the project-defined activity such as:

- Blood requests.
- Request fulfillment.
- Donor statuses.
- Donations/donation activity.
- Regional/chapter engagement.
- System activity.

## Report output

The project specifically requires **downloadable activity and analytics reports**.

Authorized officers must be able to generate and download reports.

---

# 14. Audit Logging

The system must keep a permanent record of important system and user activities.

Audit logging should cover important events such as:

- Account/role changes.
- Verification actions.
- Blood request actions.
- Matching-related events.
- Donor status changes.
- Donation workflow confirmations.
- Administrative activity.
- Security-relevant actions.

Audit logs must be protected from unauthorized access.

---

# 15. Dashboard Requirements

## Member / User Dashboard

The user-facing area should provide access to relevant functions such as:

- Profile.
- Donor status.
- Blood requests.
- Matches.
- Notifications.
- Verification status.

## Officer/Admin Dashboard

The officer/admin area must provide visibility into the system's operational status.

Required dashboard information includes relevant views for:

- Users.
- Blood requests.
- Donor activity.
- Request fulfillment.
- Chapter/region activity.
- Verification queue/status.
- System activity.
- Reports/analytics.
- Demand map.

The project UI documentation specifically includes an admin dashboard for system-wide monitoring.

---

# 16. Required UI Modules

The project documentation identifies the following interface/module areas. The implementation should have working pages/components/routes for the functionality they represent.

### Public/User Pages

- Home / Dashboard
- Login
- Registration
- User Profile
- Blood Request pages
- Match/request information
- Verification-related UI
- Notification-related UI

### Admin/Officer Pages

- Admin Home
- Admin Profile
- Admin My Request
- Admin Dashboard
- Verification management
- User/member management
- Blood request monitoring
- Analytics/reporting
- Regional demand/map monitoring
- Audit/activity monitoring

The exact page/component names may differ, but equivalent working functionality must exist.

---

# 17. Nonfunctional Requirements

## 17.1 Performance

The location-based match engine must return filtered donor results within:

**3 seconds**

## 17.2 Security

The system must securely protect:

- Verification documents.
- Personal information.
- Sensitive health/blood-related information.
- Transaction/audit logs.

The project specifically requires sensitive data to be encrypted and logs protected from unauthorized access.

## 17.3 Availability

The documented target is:

**99.5% system uptime**

This is especially important because the platform is intended for urgent blood-request situations.

## 17.4 Mobile Responsiveness

The web application must be fully mobile-responsive.

Users must be able to use the platform from smartphones, including when submitting/broadcasting requests from hospital settings.

## 17.5 Privacy

The system must avoid exposing sensitive personal information unnecessarily.

Aggregated demand-map information should protect individual user privacy.

---

# 18. Scope Boundaries — Do NOT Treat These as Required Features

The system documentation explicitly limits BloodMatch.

The implementation is **not required to**:

- Collect blood.
- Store blood.
- Transport blood.
- Provide built-in medical instruments.
- Diagnose medical conditions.
- Determine a person's health status using medical hardware.
- Guarantee that a matched donor will actually donate.
- Replace medical or clinical judgment.

BloodMatch is a coordination, matching, verification, notification, and monitoring platform.

---

# 19. Core Database / Data Areas

The documented ERD and module design indicate that the system should have data structures covering at least:

- Users.
- Chapters.
- Blood requests.
- Matches.
- Donation reports / donation records.
- Notifications.
- Verification documents.
- Audit logs.
- Donor/profile information.
- Status information required by the workflows.
- Supporting data required for analytics and reporting.

The implementation may use different table/model names, but equivalent data relationships must exist.

---

# 20. Feature-to-Requirement Traceability

The coding agent should verify these core features first.

| ID | Required Feature | Priority | Verification Goal |
|---|---|---|---|
| FR-01 | Registration and Login | MUST | User can register and securely log in |
| FR-02 | Role-Based Access Control | MUST | Donor/member, Chapter Officer, and Admin access are separated |
| FR-03 | Profile & Member Management | MUST | Required profile/chapter/location/blood information can be managed |
| FR-04 | Verification Document Workflow | MUST | User submits document; authorized officer approves/rejects |
| FR-05 | Blood Request Creation/Management | MUST | Request can be opened, tracked, fulfilled/cancelled/expired |
| FR-06 | Cascading Blood Match Engine | MUST | Compatible donors are found using multi-tier compatibility logic |
| FR-07 | Chapter/Municipality Filtering | MUST | Matching can be filtered by chapter and municipality |
| FR-08 | Donor Eligibility Management | MUST | Eligibility and donation waiting-period rules are applied |
| FR-09 | Donor Response Workflow | MUST | Donor can respond to a match/request |
| FR-10 | Donation Confirmation/Fulfillment | MUST | Authorized officer can confirm completed donation and fulfillment |
| FR-11 | 42-Hour Soft-Deactivation | MUST | Non-responsive donor becomes standby after 42 hours |
| FR-12 | Automated Notifications | MUST | Users receive required match/status notifications |
| FR-13 | Automated Email | MUST | Email alerts are triggered for required events |
| FR-14 | Regional Blood Demand Map | MUST | Active requests are aggregated and visualized by region |
| FR-15 | Analytics & Reports | MUST | Daily/weekly/monthly/yearly reports can be generated |
| FR-16 | Downloadable Reports | MUST | Authorized officers can download reports |
| FR-17 | Audit Logging | MUST | Important user/system events are permanently logged |
| FR-18 | Officer/Admin Dashboard | MUST | Officers/admins can monitor requests, donors, activity, and regions |
| FR-19 | Security & Data Protection | MUST | Sensitive documents/data/logs are protected |
| FR-20 | Mobile Responsive UI | MUST | Core workflows work on smartphones |
| FR-21 | Match Performance | MUST | Matching/filtering returns results within 3 seconds |
| FR-22 | Availability Target | MUST | System architecture targets 99.5% availability |

---

# 21. Agent Audit Instructions

When this file is placed in the codebase, the coding agent should perform a **requirements audit**.

For every requirement above:

1. Search the frontend.
2. Search the backend/API.
3. Search the database/schema/models.
4. Check authentication and authorization.
5. Check scheduled/background jobs where automation is required.
6. Check email/notification integration.
7. Check maps/location logic.
8. Check report generation/download logic.
9. Check audit logging.
10. Check tests where available.

For each item, report:

- Requirement ID.
- Status: `IMPLEMENTED`, `PARTIAL`, `MISSING`, or `UNVERIFIED`.
- Files/components involved.
- What currently works.
- What is missing or incorrect.
- Any bug or requirement mismatch.
- Recommended implementation step.
- Whether the requirement is blocked by another missing dependency.

Do not mark a feature as implemented merely because a page/button exists. Verify the underlying behavior, data flow, API/database logic, authorization, automation, and error handling where applicable.

---

# 22. Definition of Done for a Requirement

A requirement should be considered **IMPLEMENTED** only when:

- The relevant UI exists where needed.
- The required backend/business logic exists.
- Required database/storage support exists.
- Correct users/roles can access it.
- Unauthorized users are blocked.
- Required automated behavior actually executes.
- Important state changes are persisted.
- The feature works through its intended workflow.
- Errors/failures are handled reasonably.
- Existing related requirements are not broken.

---

# 23. Known Documentation Ambiguities to Preserve During Audit

The project document contains a few terminology/details that should not be silently "fixed" by the agent:

### Verification document

The functional requirements mention a **donor national ID**, while the scope/module design refers to **donor cards / verification documents**.

Audit the existing implementation and report what document type is currently supported.

### Roles

The documentation uses both general terms such as **users/requesters/donors** and a three-tier access model of:

- Donor/member
- Chapter Officer
- System Administrator

Treat requester as a user/member function rather than automatically creating a fourth role unless the existing architecture/documentation explicitly requires it.

### Matching terminology

The project requires a **cascading match engine** and a **multi-tier biological substitution hierarchy**. An implementation that performs only exact blood-type matching should be marked **PARTIAL**, not complete.

---

# 24. Final Audit Summary Format

After inspecting the codebase, the coding agent should produce a summary similar to:

```text
BloodMatch Requirements Audit

Implemented: XX
Partial: XX
Missing: XX
Unverified: XX

Critical Missing Features:
1. ...
2. ...
3. ...

Core Workflow Status:
Registration/Login: ...
Verification: ...
Blood Request: ...
Matching: ...
Donor Response: ...
Donation Confirmation: ...
Notifications: ...
42-Hour Standby: ...
Analytics/Reports: ...
Regional Demand Map: ...
Audit Logging: ...

Top Priority Fixes:
1. ...
2. ...
3. ...
```

The goal of this document is to make the current BloodMatch implementation measurable against the requirements already documented by the project team.
