# Test Case Progress Tracker — Internal Process (dept-to-dept requests)

Manual test cases for the **internal request** module: an office files a signed
paper request, its own department head endorses it, and it then travels office
to office until one of them marks it done. Every decision is gated by physical
custody of the folder (its QR) and signed with a registered e-signature.

**How to use:** work top to bottom. Tick **Pass** only when the expected result
in the *Test Case Scenario* column happens exactly; otherwise tick **Fail** and
write what happened in *Comments/Suggestion*. `☐` = not yet run.

---

## Test environment

| Item | Value |
|---|---|
| Build / branch | `vamos` — fill in the commit hash on the day of testing |
| URL | `http://127.0.0.1:8000` (`composer dev`) or the `./start-demo.sh` address |
| Browser | Chrome (latest) — note the version actually used |
| Date tested | |
| Tested by | |

### Accounts (seeded by `php artisan db:seed --class=TeamUsersSeeder`)

| Role in these tests | Account | Password | Office |
|---|---|---|---|
| Filer (staff) | `ana.cruz@speedtraqr.com` | `staff1234` | Tourism Office (TRSM) |
| Endorsing head — own office | `maria.santos@speedtraqr.com` | `staff1234` | Tourism Office (TRSM) |
| Receiving head — next office | `budget.head@speedtraqr.com` | `staff1234` | Municipal Budget Office (BO) |
| Unrelated head — wrong office | `engineering.head@speedtraqr.com` | `staff1234` | Municipal Engineering Office (ENG) |
| Org-wide admin | `admin@speedtraqr.com` | from `ADMIN_PASSWORD` | org-wide |

### Before starting

1. `php artisan migrate:fresh --seed` then seed the team accounts above.
2. Have a scanned request on disk: one **JPG/PNG** and one **PDF** (≤ 10 MB each).
3. Maria Santos and Elena Marquez must each draw an e-signature on
   **Profile → e-signature** (TC-INT-052 covers the missing-signature case, so
   register Elena's only after that case is run).
4. Have a way to show a QR to the webcam (a second phone, or the request's own
   printed claim slip). Cases also cover the no-camera path.

---

## A. Access control and inbox

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-001 | Internal inbox — entry | A staff member with an office opens the internal inbox → the page loads with the **Awaiting my office / Filed by my office / Closed** tabs and a **＋** file button | Log in, click **Internal** in the sidebar | `ana.cruz@speedtraqr.com` / `staff1234` | ☐ | ☐ | Functional Suitability | |
| TC-INT-002 | Internal inbox — scoping | A department head sees only their office's requests → "Awaiting my office" lists requests whose current hop is their office; another office's in-flight request is absent | Log in, open **Internal**, read both tabs | `budget.head@speedtraqr.com` / `staff1234` | ☐ | ☐ | Security | |
| TC-INT-003 | Internal inbox — org-wide view | The org-wide admin sees every internal request regardless of office | Log in as admin, open **Internal** | `admin@speedtraqr.com` / `ADMIN_PASSWORD` | ☐ | ☐ | Functional Suitability | |
| TC-INT-004 | Internal inbox — unauthorised | A citizen (not logged in) cannot reach the inbox → redirected to the login page, never the list | Log out, type the URL directly | `/requests` | ☐ | ☐ | Security | |
| TC-INT-005 | Internal inbox — search | Searching narrows the list by tracking number **or** purpose; a term that matches nothing shows an empty state, not an error | Type in the inbox search box, press **Search** | `INT-` then `electric fan` then `zzzzz` | ☐ | ☐ | Usability | |
| TC-INT-006 | File button — no office | A user with no department cannot file → the **＋** button is absent and the wizard URL explains why instead of 500-ing | Admin creates a staff user with no department, log in as them, then open the URL | `/requests/create` | ☐ | ☐ | Reliability | |

## B. Filing a request (2-step wizard)

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-010 | File request — open wizard | The wizard opens on step 1 with every **active** office listed in "goes first to" | Click **＋** on the inbox | — | ☐ | ☐ | Functional Suitability | |
| TC-INT-011 | File request — required fields | Submitting with the office, purpose or scan missing is refused with a message naming the missing field; nothing is filed | Leave all three empty, press **Review** / **File request** | (blank form) | ☐ | ☐ | Functional Suitability | |
| TC-INT-012 | File request — purpose length | The purpose accepts a normal sentence and refuses an over-long one (255 character cap) | Paste a 300-character string into **Purpose**, submit | 300 × `a` | ☐ | ☐ | Functional Suitability | |
| TC-INT-013 | File request — attach scan (image) | A JPG/PNG scan uploads and the file name shows on the form before submitting | Choose the image file | `request-scan.jpg` | ☐ | ☐ | Usability | |
| TC-INT-014 | File request — rejected file type | A file type the office does not accept is refused with a message listing what *is* accepted | Choose an unsupported file | `malware.exe` (or `.zip`) | ☐ | ☐ | Security | |
| TC-INT-015 | File request — oversized file | A file over 10 MB is refused and says so; the form keeps the other entries | Choose a >10 MB file | `big-scan.pdf` (12 MB) | ☐ | ☐ | Performance Efficiency | |
| TC-INT-016 | File request — QR placement | On step 2 the QR can be dragged onto a clear area of the scan and resized; the preview shows where it will land | Drag the QR box, move the size slider | drop near the bottom-right margin | ☐ | ☐ | Usability | |
| TC-INT-017 | File request — submit | Filing succeeds → the confirmation screen shows a tracking number in the form `INT-YYYYMMDD-XXXXXX` (note it as **REQ-A**) | Complete step 1 and 2, press **File request** | office `Municipal Budget Office (BO)`, purpose `Request for 2 units electric fan`, scan `request-scan.jpg` | ☐ | ☐ | Functional Suitability | |
| TC-INT-018 | File request — prefix | The number starts `INT-`, not `SPD-`, so an internal request is distinguishable from a citizen ticket at a glance | Read the tracking number from TC-INT-017 | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-019 | File request — chain created | The new request opens with **two** hops: the filing office's own endorsement (current) and the chosen office (pending, in that order) | Open the request from the inbox | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-020 | File request — notification | The **filing office's own head** is notified that a request needs their endorsement (the first hop is their own office; the receiving office is notified later, at TC-INT-054) | Log in as the endorsing head, open the bell menu | `maria.santos@speedtraqr.com` | ☐ | ☐ | Functional Suitability | |
| TC-INT-021 | File request — duplicate guard | Refreshing the confirmation screen does **not** file a second request | On the confirmation screen, press F5 / Reload | REQ-A | ☐ | ☐ | Reliability | |
| TC-INT-022 | File request — filer is kept informed | The filer is notified when an office later acts on their request (approved / denied / returned) | After TC-INT-050, log in as the filer and open the bell menu | `ana.cruz@speedtraqr.com` | ☐ | ☐ | Functional Suitability | |

## C. Confirmation screen, QR and the paper trail

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-030 | Confirmation — QR image | The screen shows a scannable QR for the request, and the image exists on disk | Open the confirmation screen, then check `storage/app/public/qrcodes/` | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-031 | Confirmation — stamped copy (image) | The archived copy of an **image** scan carries the QR stamped at the chosen spot, and the untouched original is kept as well | Open the request's attachments | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-032 | Confirmation — stamped copy (PDF) | Filing with a **PDF** scan produces a stamped PDF on the chosen page | File a second request (note as **REQ-B**) with the PDF, page `2` | purpose `Request for office supplies`, scan `request-scan.pdf` | ☐ | ☐ | Compatibility | |
| TC-INT-033 | Confirmation — print | The print view fits one page and the QR is legible after printing on plain paper | Press **Print**, print or save as PDF | REQ-B | ☐ | ☐ | Usability | |
| TC-INT-034 | QR scan — opens the record | Scanning the printed QR with a phone camera opens the request's tracking page directly, with no extra tap | Scan the printed sheet from TC-INT-033 | REQ-B QR | ☐ | ☐ | Usability | |
| TC-INT-035 | Attachment privacy | A scanned attachment cannot be opened by a logged-out visitor pasting its URL | Copy an attachment link, log out, paste it | attachment URL of REQ-A | ☐ | ☐ | Security | |

## D. Custody gate (the folder must be in the office)

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-040 | Custody — endorsement locked | Before any office takes custody of a *forwarded* hop, the decision buttons refuse to act and say the paper must be in the office first | As the receiving head (after TC-INT-050), open the request and press **Approve** without taking custody | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-041 | Custody — scan to receive | Scanning the folder's QR records custody and names the receiving office on the chain | Open the request, **Take custody → scan**, show the QR to the camera | REQ-A QR | ☐ | ☐ | Functional Suitability | |
| TC-INT-042 | Custody — wrong folder | Scanning a **different** request's QR is refused and names the other request | **Take custody → scan**, show REQ-B's QR while on REQ-A | REQ-B QR on REQ-A | ☐ | ☐ | Security | |
| TC-INT-043 | Custody — foreign QR | Scanning a QR this system never issued is refused as a foreign code | **Take custody → scan**, show any non-system QR | a Wi-Fi or GCash QR | ☐ | ☐ | Security | |
| TC-INT-044 | Custody — no camera | With no working camera, custody can still be recorded manually, and the reason is required | **Take custody → record manually**, submit empty, then with a reason | `Webcam not working on this counter PC` | ☐ | ☐ | Reliability | |
| TC-INT-045 | Custody — trail | The request shows who holds the folder now and who held it before, with timestamps | Open the request's custody section | REQ-A | ☐ | ☐ | Functional Suitability | |

## E. Hop decisions — approve, deny, return, done

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-050 | Decision — own-office endorsement | The filing office's head approves the first hop → status becomes **In Progress** and the request moves to the chosen office | Log in as the endorsing head, open REQ-A, scan the QR, press **Approve** | `maria.santos@speedtraqr.com`, scan REQ-A QR | ☐ | ☐ | Functional Suitability | |
| TC-INT-051 | Decision — wrong office refused | A head of an office that does **not** hold the current hop gets no action panel, and the action URL returns "access denied" rather than acting | Log in as the unrelated head, open REQ-A | `engineering.head@speedtraqr.com` | ☐ | ☐ | Security | |
| TC-INT-052 | Decision — signature required | Approving without a registered e-signature is refused and points the user to their Profile page | As the receiving head **before** drawing a signature, take custody, press **Approve** | `budget.head@speedtraqr.com` | ☐ | ☐ | Functional Suitability | |
| TC-INT-053 | E-signature — register | A signature drawn on the Profile page saves and shows back on the page | **Profile → e-signature**, draw, **Save** | drawn signature | ☐ | ☐ | Usability | |
| TC-INT-054 | Decision — approve forwards on | With custody and a signature, approving asks where the request goes next and forwards it there; the next office's head is notified | Take custody, press **Approve**, choose the next office | next office `General Services Office (GSO)` | ☐ | ☐ | Functional Suitability | |
| TC-INT-055 | Decision — no self-forward | An office cannot forward a request to itself → refused with a message to pick a different office | On the approve panel, choose the acting office itself | `Municipal Budget Office (BO)` | ☐ | ☐ | Functional Suitability | |
| TC-INT-056 | Decision — frozen signature | The approved hop shows the signature as it was at the time of signing, and re-drawing the signature later does **not** change it | Approve, then re-draw the signature on Profile, re-open the request | REQ-A | ☐ | ☐ | Security | |
| TC-INT-057 | Decision — scan or reason required | A decision with neither a QR scan nor a written reason is refused; a reason shorter than 10 characters is also refused | Press **Approve** with both fields empty, then with reason `no` | (empty), then `no` | ☐ | ☐ | Functional Suitability | |
| TC-INT-058 | Decision — mismatched QR | Confirming a decision with another request's QR is refused and names the other request | On REQ-A's approve panel, scan REQ-B's QR | REQ-B QR | ☐ | ☐ | Security | |
| TC-INT-059 | Decision — scan override logged | A decision confirmed without a scan is recorded in the request's own feed and the audit log, naming who did it and why | Approve using the written reason, then read the activity feed | `Sticker torn, QR will not read` | ☐ | ☐ | Security | |
| TC-INT-060 | Decision — deny needs a reason | Denying with an empty reason is refused; with a reason the request becomes **Denied** and stops moving | File **REQ-C**, endorse it, then as the holding head press **Deny** — empty first, then with text | `Budget not available for this quarter` | ☐ | ☐ | Functional Suitability | |
| TC-INT-061 | Decision — deny is attributed | A denied request names the office and person who denied it, and the reason, on the request page | Open REQ-C after TC-INT-060 | REQ-C | ☐ | ☐ | Functional Suitability | |
| TC-INT-062 | Decision — return for revision | Returning sends the request back to the filing office as **Returned / For Revision** with the reason, and the filer is notified | As the holding head press **Return**, then log in as the filer | `Attach the approved purchase request first` | ☐ | ☐ | Functional Suitability | |
| TC-INT-063 | Decision — mark as done | **Mark as done** is the only action that completes a request → status **Completed**, and any hop still queued behind it is closed off | As the last holding office, press **Mark as done** | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-064 | Decision — approve never completes | Approving at the last office does **not** silently complete the request; it asks for the next office or for **Done** | Press **Approve** leaving the next office unchosen | (no office chosen) | ☐ | ☐ | Functional Suitability | |
| TC-INT-065 | Decision — closed request is final | A completed or denied request offers no further decision buttons, and the action URL refuses to act | Open REQ-A (completed) and REQ-C (denied) as the last holding head | REQ-A, REQ-C | ☐ | ☐ | Reliability | |

## F. Audit trail, history and exposure

| Test Case Scenario ID | Name of the Module Function | Test Case Scenario | Action | Actual Input | Pass | Fail | Product Quality Component | Comments/Suggestion |
|---|---|---|---|---|---|---|---|---|
| TC-INT-070 | Chain view | The request page shows every hop in order with its office, who acted, when, the remarks and the signature | Open a completed request | REQ-A | ☐ | ☐ | Functional Suitability | |
| TC-INT-071 | Audit log | Every filing, custody event and decision appears in the activity log with the person who caused it | Open the request's activity feed end to end | REQ-A | ☐ | ☐ | Security | |
| TC-INT-072 | Closed tab | A completed or denied request leaves the active tabs and appears under **Closed** for the offices it passed through | Open **Internal → Closed** as both the filing and a transit office | REQ-A, REQ-C | ☐ | ☐ | Functional Suitability | |
| TC-INT-073 | Not publicly trackable | An internal request's number cannot be tracked from the public page — the citizen tracker refuses it rather than exposing office business | Log out, open the public tracking page, enter the number | REQ-A number on `/track` | ☐ | ☐ | Security | |
| TC-INT-074 | Not in citizen triage | Internal requests do not appear in the citizen request tables or counts | Log in as a Supervisor, open **Look Up** and the dashboard tiles | — | ☐ | ☐ | Functional Suitability | |
| TC-INT-075 | Signature privacy | A hop's signature image is served to signed-in staff only; a logged-out visitor pasting the URL is refused | Copy the signature URL, log out, paste it | signature URL from TC-INT-070 | ☐ | ☐ | Security | |

---

## Summary

| Module group | Cases | Passed | Failed | Not run |
|---|---|---|---|---|
| A. Access control and inbox | 6 | | | |
| B. Filing a request | 13 | | | |
| C. Confirmation, QR, paper trail | 6 | | | |
| D. Custody gate | 6 | | | |
| E. Hop decisions | 16 | | | |
| F. Audit trail and exposure | 6 | | | |
| **Total** | **53** | | | |

**Product quality components used** (ISO/IEC 25010): Functional Suitability,
Performance Efficiency, Compatibility, Usability, Reliability, Security.
Maintainability and Portability are not exercised by black-box UI testing and
are deliberately absent.

### Defects raised from this run

| ID | Case | Severity | What happens | Status |
|---|---|---|---|---|
| | | | | |
