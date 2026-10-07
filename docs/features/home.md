# Home page: functional spec (DRAFT, edit before use)

Save as `docs/features/home.md`. Tags:
- **[DESIGN]** seen in your previous design screenshots (lost in the power cut)
- **[DECIDED]** you told me this
- **[PROPOSED]** my suggestion. Keep it, change it or delete it
- **[DECIDE]** open question. Write your answer on the same line before sending this to the agent

---

## 1. Purpose
Home is where a logged-in DeMolay Bataan member sees blood requests that matter to them and acts on them. Private community: members only, no public pages.

## 2. Layout
- **[DESIGN]** Three columns on desktop, one column on mobile.
- **[DESIGN]** Header on every page: logo, global search bar ("Search BloodMatch"), Home and Profile icons, "+" create-request button, bell with unread badge, avatar menu.
- **[DESIGN]** Left: profile card (avatar, name, role label, expandable "Account Details").
- **[DESIGN]** Center: request feed ("Recent regional blood requests").
- **[DESIGN]** Right: Chapter Demand Map card (see section 8).
- **[PROPOSED]** Mobile order: feed first, demand card second, profile card collapsed.

## 3. Which requests appear in the feed
- **[DECIDED]** The feed suggests only requests compatible with the user's blood type.
- **[DECIDE]** Your old design showed several members' requests, including your own, under "Recent regional blood requests". Choose one: (a) compatible only (recommended), (b) all open requests with a "Compatible with you" label, (c) compatible by default plus a "Show all open requests" toggle. ANSWER:
- **[PROPOSED]** Only OPEN requests. Fulfilled, cancelled and expired ones live in My Requests.
- **[DECIDE]** Your own requests in the feed? Recommended: no, they live in My Requests. ANSWER:
- **[PROPOSED]** Sort: Emergency, then Urgent, then Routine, then newest. 20 per page with "Load more".
- **[PROPOSED]** Compatibility is decided only by the backend (`BloodCompatibilityService` / `MatchService`), never in the browser.
- **[PROPOSED]** Members who aren't enrolled as donors, or are on standby or cooldown, see an explanation instead of a blank page.

## 4. Request card
- **[DESIGN]** Collapsed: avatar, requester name (verified check if verified), chapter or "Community Member", blood type badge, chevron.
- **[DESIGN]** Expanded: description ("situation details"), units needed, hospital/clinic, municipality, needed-by date and time, urgency, "View Details & Respond", "View Profile".
- **[PROPOSED]** Collapsed also shows an urgency label and time posted, so urgent requests stand out without expanding.
- **[PROPOSED]** Description is plain text only, max 500 characters, long words wrap, "Read more" in the feed, full text on the details page. Never rendered as HTML.
- **[DECIDE]** Showing the requester's name and a "View Profile" link. Check CONTEXT.md section 9 and requirements 17.2 and 17.5 for what one member may see about another. Allowed on a profile page: ANSWER:

## 5. Actions
- **[DESIGN]** "View Details & Respond" opens the request details page.
- **[PROPOSED]** A donor responds through the existing respond endpoint, and the email-not-verified dialog opens if their email isn't verified.
- **[PROPOSED]** After responding, the card shows "You responded", and the requester gets a bell notification.
- **[DECIDE]** What does a donor see on the details page, and what does the requester see (matched donors list)? ANSWER:

## 6. Search (header bar)
- **[DECIDED]** The search bar must work.
- **[PROPOSED]** It searches requests in the user's feed by hospital/facility name and municipality. No donor or people search (CONTEXT.md 9.8), and patient names are not searchable.
- **[PROPOSED]** Debounced (about 300 ms), clear button, "N results" line, query kept in the URL.
- **[DECIDE]** Should it search only on Home, or from any page? ANSWER:

## 7. Filters
- **[DECIDED]** Municipality filter (the 12 Bataan municipalities, from the existing locations list).
- **[PROPOSED]** Urgency filter (Emergency / Urgent / Routine).
- **[DECIDE]** Chapter filter? ANSWER:
- **[PROPOSED]** "Clear filters", filters kept in the URL, honest empty states.

## 8. Chapter Demand Map card
- **[DESIGN]** Chips with counts of active requests by urgency, bars per chapter, "View Full Map" opening /demand-map.
- **[DECIDED]** Named "Chapter Demand Map" (not "Regional").
- **[PROPOSED]** Aggregated chapter-level counts only, no individual people or exact locations.
- **[DECIDE]** Clicking a chapter filters the feed by that chapter? ANSWER:

## 9. Notifications (bell)
- **[DECIDED]** In-app notices for new matches, responses, and fulfilled, cancelled or expired requests.
- **[DECIDED]** Request alert emails only for emergency requests. System emails stay (verification code, password reset, verification result, account status).
- **[DECIDED]** Emergency uses the canonical `emergency` urgency value.

## 10. Create request ("+")
- **[DESIGN]** Fields: blood type needed, units, hospital/clinic and municipality, needed by, urgency, situation details.
- **[PROPOSED]** Unverified-email users get the verification dialog instead of the form.
- **[PROPOSED]** Abuse guard: one active emergency request per requester at a time.

## 11. States
Loading skeleton, empty (with a reason), error with retry, no search results (with "Clear filters").

## 12. Rules that must always hold
- Authorization is enforced in the backend; hiding things in the UI is not enough.
- No contact details, ID documents or health records in the feed.
- The description is untrusted text: escape it, cap its length, never render HTML.
- Follow DESIGN.md. Works at 375px, in dark mode, with reduced motion and the keyboard.
- Every new endpoint gets tests in the isolated runner. No donor directory or donor search.

## 13. Known problems in the previous design
- A long unbroken description overflowed the card (needs wrapping and a length cap).
- The feed showed my own request and seed "Dummy User" data (confirm what's intended).

## 14. Open questions (answer the **[DECIDE]** lines above)
| # | Question | Decision |
|---|---|---|
| 1 | Feed contents | Compatible only, taken from the match engine (MatchService / BloodCompatibilityService) |
| 2 | Own requests in the feed | No, they live in My Requests |
| 3 | "View Profile" | Yes, the limited profile in section 15, reachable only through a request |
| 4 | Details page | A donor sees the request and only their own match. The requester sees all privacy-safe matched donors |
| 5 | Search | Global header search. Used on another page, it navigates to Home with `?q=` |
| 6 | Chapter filter | Not now. Municipality and urgency only |
| 7 | Demand-map chapter click | Not interactive for now |
| 8 | "Emergency" | Means the existing `emergency` urgency |
| 9 | Demand-map card for members | Omitted for members (they get 403 under 9.9). Kept for officers and admins |

## 15. Profile visibility (CONTEXT.md 9.8 amendment applies)

Fields shown on a profile:

| Field | Own profile | Another member |
|---|---|---|
| Name, photo, chapter, role label, member since | Yes | Yes |
| Verification status | Yes | Yes |
| Blood type | Yes | Yes (new) |
| Email | Yes | Yes (new) |
| Phone, date of birth, ID documents, exact address, donation history, availability | Yes | No |
| Edit controls | Yes | No |

Who can open a member's profile (Option A, request-scoped):

| Viewer | Can open this member's profile |
|---|---|
| Yourself | Yes |
| A donor | The requester's, while the donor has a match on that requester's OPEN request (the feed card's "View Profile") |
| A requester | A donor's, only after that donor responded to the requester's OPEN request |
| An admin | Any member |
| An officer | Members of their own chapter (existing scoped rule) |
| Anyone else | "Not found" (404) |

No member directory and no member search anywhere.
