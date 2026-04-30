# AAC Grants Review

Standalone WordPress plugin for routing AAC grant applications from the custom AAC portal form builder into a reviewer-friendly approval workflow.

## What it does

- captures AAC portal grant submissions into AAC-owned review tables
- uses the custom grant builder managed in `AAC Portal > Grants`
- shows reviewers a clean application detail view instead of a raw form dump
- tracks workflow movement with notes and reviewer history

## Workflow states

- Submitted
- Eligibility Review
- Committee Review
- Needs Revision
- Approved
- Rejected

## Setup

1. Activate the plugin.
2. Open `AAC Grants Review > Settings`.
3. Open the linked `AAC Portal > Grants` builder.
4. Add or adjust the grant opportunities and application fields there.
5. Member submissions from that custom form will flow directly into the review queue.

## Notes

- Review capability is added to Administrators and Editors on activation.
- The plugin stores both normalized fields and raw entry payloads so the reviewer UI can improve later without losing source detail.
