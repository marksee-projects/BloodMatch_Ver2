# Presentation workflow examples

Added three explicitly synthetic, active and verified demo members to local `bloodmatch:3306`: requester 143 (DEMO Alex Requester), donor 144 (DEMO Jamie Donor), and donor 145 (DEMO Sam Donor). Emails use reserved `example.invalid` addresses; these are presentation fixtures, not evidence of real verification. Login credentials are retained locally in ignored `scratch/browser-check/presentation-workflow-demo.json` and must not be committed.

| Example | Request | Conversation | Report | Demonstration |
| --- | ---: | ---: | ---: | --- |
| Awaiting donor report | 78 | 9 | None | Requester can send a reminder to Sam; after sending, the reminder disables for 24 hours. |
| Awaiting requester acknowledgment | 79 | 10 | 4 | Jamie's report is pending. Alex can select Donation received, Did not donate, or Not sure. Jamie's report-submission button is disabled. |

Both routine requests and all example messages are clearly marked as fictional. The requests have a 24-hour deadline (2026-10-23 02:36:22 UTC). Requester acceptance used the existing OfferChatService. The pending report used DonationCompletionService, including the report hold and workflow event, without attached proof. This creates a real pending workflow record for a fictional donation; it is not a completed donation or medical record.

The seed was transactional and idempotency-guarded, preceded by a local full database backup at ignored `scratch/browser-check/before-presentation-workflow.sql`. No existing accounts were modified and no passwords were reset. Verification checked both accepted contexts and report states through the existing service read paths and confirmed zero queued email jobs for the three accounts. No SMTP was invoked. Existing email automation remains enabled.

Use two separate browser sessions to demonstrate requester/donor behavior. After the presentation, deactivate only these demo users and close only requests 78 and 79 through the normal reviewed workflows; preserve report/audit history. Do not bulk-delete records or reset pending-report holds.
