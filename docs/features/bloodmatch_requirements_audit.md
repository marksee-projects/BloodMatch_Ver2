# BloodMatch Requirements Audit

This is a high-level requirements audit for the **BloodMatch** system based on the current state of the codebase.

### **Audit Summary**
**Implemented:** 13
**Partial:** 4
**Missing / Unverified:** 5

---

### **Critical Missing / Unverified Features:**
1. **Automated Soft-Deactivation (FR-11)**: The 42-hour automatic standby timeout for non-responsive donors needs a scheduled background task (cron job).
2. **Automated Emails (FR-13)**: Needs verification on whether actual SMTP emails are being dispatched alongside in-app notifications.
3. **Audit Logging (FR-17)**: Needs a comprehensive logging system for sensitive data access and role changes to meet compliance.
4. **Regional Blood Demand Map (FR-14)**: Interactive map for authorized officers needs to be built or fully connected.
5. **Downloadable Reports (FR-16)**: Analytics backend exists, but needs to be verified if CSV/PDF downloads are fully working.

---

### **Core Workflow Status:**
- **Registration/Login:** ✅ Implemented (`Auth` controllers, React Pages)
- **Role-Based Access Control:** ✅ Implemented (Admin, Officer, Donor roles separated in frontend/backend)
- **Profile & Member Management:** ✅ Implemented (`ProfileController`, `ProfilePage.jsx`)
- **Verification:** ✅ Implemented (`DocumentController`, Officer dashboards)
- **Blood Request:** ✅ Implemented (`RequestsController`, `RequestFormPage.jsx`)
- **Matching:** ⚠️ Partial/Implemented (`CompatibilityController`, `MatchesController`) — Cascading biological match rules should be rigorously tested.
- **Donor Response:** ✅ Implemented (`MatchesPage.jsx`)
- **Donation Confirmation:** ✅ Implemented (`DonationReportController`)
- **Notifications:** ✅ Implemented (In-app notifications via `NotificationsController`, `NotificationsPage.jsx`)
- **42-Hour Standby:** ❌ Missing/Unverified (Requires background job)
- **Analytics/Reports:** ⚠️ Partial (`Analytics` backend present, full reporting features need testing)
- **Regional Demand Map:** ❌ Missing/Unverified 
- **Audit Logging:** ❌ Missing/Unverified

---

### **Top Priority Next Steps:**
1. **Implement the 42-Hour Standby CRON Job:** This is a core required feature to ensure the donor pool remains active and accurate. We need to create a background script in `backend/scripts/` to handle this.
2. **Verify/Implement Email Notifications:** Ensure SMTP/email dispatching works for matched blood requests.
3. **Review Cascading Match Engine:** Double-check that `CompatibilityController` correctly handles the multi-tier biological substitution hierarchy and not just exact matches.

*Note: This audit is a live document and can be updated as we build out the remaining features.*
