# Approval email delay investigation

Two current membership-decision jobs (26 and 27, accounts 140 and 141) were pending with zero attempts after approvals at 01:45:59 and 01:58:27 UTC. The latest worker log was a manual run at 01:29:08 UTC despite the scheduled task existing. Windows reported the task queued, with `DisallowStartIfOnBatteries=true`, `StopIfGoingOnBatteries=true`, and `StartWhenAvailable=false`.

Updated only the owned local Email task to permit battery operation, continue when switching to battery, and start when available. Preserved the windowless PHP launcher, schedule, identity, overlap policy and email cutover. Started the task normally; no jobs were reset, copied, or manually resent.

The scheduled worker processed both jobs. Job 26 was marked sent at 02:01:41 UTC and job 27 at 02:01:46 UTC, each with one attempt and no error code. This establishes SMTP acceptance, not Inbox placement. Historical completed jobs were not replayed.

New task installations through `scripts/manage_application_tasks.ps1` now explicitly allow battery operation and missed-start recovery. Existing unrelated tasks were not changed. The laptop still needs to be awake, the Windows user signed in, MySQL available, and network access working.
