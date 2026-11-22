# Admin back-office: reservations management

Area: UAT-RES. Source of truth: `AdminReservationController`, `ReceiptController`, `PaymentProofService`, `AvailabilityService`, `ReservationEmailService`, `resources/views/admin/reservations/*`, `resources/views/admin/bookings/index.blade.php`, tests `ReservationWorkflowAndEmailTest`, `AdminReservationCalendarOverrideTest`, `PaymentWorkflowTest`.

Reservation statuses accepted by `updateStatus`: `pending`, `pending_payment`, `pending_validation`, `confirmed`, `checked_in`, `completed`, `cancelled`. Labels (lang `admin.status_*`): Pending, Pending payment, Pending payment validation, Confirmed, Checked in, Completed, Cancelled.
Admin-created reservations: `status=pending`, `source=admin`, ref format `AFK-XXXXXX-yymmdd`.
Access: admin, host (own establishments), concierge for all except `DELETE` (admin only) and the external-calendar override (admin/host only).

Shared data: property `PA` (min stay 2, max guests 4, rate 50000, establishment `E1`, tenant A). Dates use `T` = test day. Guest email `uat-guest-1@example.test`.

---

## UAT-RES-001: Create reservation from admin (success path)
- **Feature / journey:** Manual reservation creation
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned), Concierge
- **References:** `admin.reservations.create`, `admin.reservations.store`; `AdminReservationController::store`; lang `flash.reservation_created_email_queued`; `tests/Feature/ReservationWorkflowAndEmailTest.php`
- **Preconditions / setup:** Logged in as admin; `PA` available for `T+20` to `T+23`.
- **Test data:** property `PA`, full_name `UAT Guest One`, email `UAT-Guest-1@Example.Test`, phone `+22900000001`, country `Benin`, locale `fr`, check_in `T+20`, check_out `T+23`, adults 2, children 1, infants 0, notes `Late arrival`, customer_note `Baby cot`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/reservations/create` | Form with guest fields, property select (tenant/host-scoped), date range picker, party sizes, locale, notes, customer note. Concierge does not see the "ignore external calendar conflicts" checkbox. |
  | 2 | Submit | Redirect to `/admin/reservations/{id}` with "Reservation created. The customer confirmation has been queued for delivery." |
  | 3 | Check detail page | Ref `AFK-XXXXXX-yymmdd`, status Pending, source admin, 3 nights, price lines, totals consistent with property and establishment taxes, email stored lowercase `uat-guest-1@example.test`. |
  | 4 | Check `email_outbox` | One row `template=reservation_created`, `status=queued`, recipient `uat-guest-1@example.test`, locale `fr`. |
  | 5 | Check the guest record | `reservation_guests` row created or reused (same email = same guest, name not overwritten). |
  | 6 | Open `/admin/reservations` | New reservation listed first. |
  | 7 | Create a second reservation for the same email with a different name | Existing guest record reused; record whether the name shown is the old or new one. |
- **Acceptance criteria:** Reservation, guest, price lines and queued email exist and match.
- **Evidence to capture:** Screenshots of form, detail page, DB rows (`reservations`, `reservation_price_lines`, `email_outbox`).
- **Notes / assumptions / open questions:** The creating staff member becomes `reservations.user_id` (not the guest). Visiting the detail page later reassigns it (see RES-008).

## UAT-RES-002: Create reservation validation and boundaries
- **Feature / journey:** Reservation form validation
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `validateReservation`; `ensureReservationRules`; lang `errors.reservation_capacity_exceeded` ("The number of guests exceeds the property capacity."), `properties.minimum_stay_error` ("This property requires a minimum stay of :nights night(s).")
- **Preconditions / setup:** `PA` min stay 2, max guests 4.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty form | Errors for property_id, full_name, email, check_in, check_out, adults. |
  | 2 | check_in = yesterday | Error (`after_or_equal:today`). check_in = today accepted. |
  | 3 | check_out equal to check_in or before | Error (`after:check_in`). |
  | 4 | Stay of 1 night (min stay 2) | Error on `check_out`: "This property requires a minimum stay of 2 night(s)." |
  | 5 | adults 3 + children 2 (5 guests > 4) | Error on `adults`: "The number of guests exceeds the property capacity." Infants are not counted. |
  | 6 | adults `0`, `9`; children `9`; infants `5` | Each rejected (adults 1-8, children 0-8, infants 0-4). Limits `8/8/4` accepted if capacity allows. |
  | 7 | email `bad@`, 256-char email; full_name 121 chars; phone 41 chars; country 81 chars | Rejected. Boundary lengths accepted. |
  | 8 | locale `de` | Rejected (fr or en). |
  | 9 | notes 2001 chars; customer_note 2001 chars | Rejected. 2000 accepted. |
  | 10 | property_id of another tenant or unknown id | Property unknown: validation error; another tenant: 404. |
  | 11 | Dates in invalid format `2026-13-45` | Validation error, no 500. |
  | 12 | Very long stay (e.g. 400 nights) | Record behavior (no maximum in rules). |
- **Acceptance criteria:** Invalid reservations never persist; messages shown on the right fields.
- **Evidence to capture:** Screenshots of errors; reservations count before/after.
- **Notes / assumptions / open questions:** Property `max_guests` > 8 cannot be fully used from this form (adults max 8). The date picker may block steps 2/3 client-side; use a raw request or disable JS to test server rules.

## UAT-RES-003: Double-booking prevention (overlap rules)
- **Feature / journey:** Availability enforcement on admin create and edit
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `AvailabilityService::isAvailable`; lang `errors.property_unavailable` ("The property is not available for these dates.")
- **Preconditions / setup:** `PA` has reservation `R1` confirmed `T+10` to `T+13` and cancelled reservation `RC` `T+30` to `T+33`.
- **Test data:** Candidate stays below.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create `T+11` to `T+14` (overlaps end) | Error on `check_in`: "The property is not available for these dates." |
  | 2 | Create `T+8` to `T+11` (overlaps start) | Same error. |
  | 3 | Create `T+10` to `T+13` (identical) | Same error. |
  | 4 | Create `T+10` to `T+12` (starts on R1's check-in, inside R1; min stay 2 satisfied) | Same error. |
  | 5 | Create `T+8` to `T+10` (checkout on R1's check-in) | Accepted (back-to-back allowed). |
  | 6 | Create `T+13` to `T+15` (check-in on R1's checkout) | Accepted. |
  | 7 | Create `T+30` to `T+33` (over cancelled `RC`) | Accepted (cancelled does not block). |
  | 8 | Create over a `pending` or `pending_payment` reservation's dates | Rejected (any non-cancelled status blocks). |
  | 9 | Submit two creates for the same dates at the same time from two browsers | Record whether both succeed (no database lock is used in the controller; availability is checked before the transaction). |
  | 10 | Edit `R1` to `T+11` to `T+14` | Own reservation ignored in conflict check; accepted. |
  | 11 | Edit another reservation to overlap `R1` | Rejected with 422 error page/message (edit aborts with 422, not a field error). |
- **Acceptance criteria:** Overlapping active stays are never accepted; adjacency and cancelled dates are.
- **Evidence to capture:** Screenshots of results; DB overlap query result (no overlapping non-cancelled rows).
- **Notes / assumptions / open questions:** Step 9 is a concurrency check, run only if tester can coordinate two sessions. `update` uses `abort_unless(..., 422)` so the user sees an error page, unlike `store` which returns a field error. Confirm the UX is acceptable.

## UAT-RES-004: Admin availability blocks and external calendar conflict, with override permission
- **Feature / journey:** Calendar override on reservation create
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned) can override; Concierge cannot
- **References:** `authorizeExternalCalendarOverride`, `appendExternalCalendarOverrideAudit`; lang `admin.ignore_external_calendar_conflicts`, `admin.external_calendar_override_audit` ("[External calendar override] Conflict ignored by :user on :date."), `errors.establishment_manager_required`; `tests/Feature/AdminReservationCalendarOverrideTest.php`
- **Preconditions / setup:** `PA` has an enabled external feed event `T+40` to `T+43` (see UAT-CALA-001) and an admin block `T+50` to `T+53`.
- **Test data:** Candidate stays `T+40` to `T+42`, `T+50` to `T+52`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin creates `T+40` to `T+42` without override | Error on `check_in`: "The property is not available for these dates." |
  | 2 | Same with the checkbox `ignore_external_calendar_conflicts` ticked | Reservation created. Notes end with "[External calendar override] Conflict ignored by <admin name> on <datetime>." |
  | 3 | Check `email_outbox` and run `php artisan messages:send-email-notifications` | `reservation_created` email queued then sent (status `sent`). |
  | 4 | Admin creates `T+50` to `T+52` with the override ticked | Still rejected (override only skips external feed conflicts, not admin blocks or reservations). |
  | 5 | Host assigned to `E1`: open create form and repeat step 2 on another feed event | Checkbox visible, creation accepted with the same audit note. |
  | 6 | Concierge: open create form | Checkbox not shown. |
  | 7 | Concierge posts with `ignore_external_calendar_conflicts=1` (crafted) | 403 "Administrator or host access is required." |
  | 8 | Override ticked on dates with no external conflict | Reservation created; no audit note appended. |
  | 9 | Edit an existing reservation into a feed conflict with override | Accepted; audit note appended to notes. |
- **Acceptance criteria:** Only admins/hosts can override external feed conflicts; every override is traceable in notes.
- **Evidence to capture:** Screenshots; reservation notes text; outbox row.
- **Notes / assumptions / open questions:** On edit, a rejected overlap returns 422 (RES-003 note).

## UAT-RES-005: Edit reservation: dates, party size, guest data, price recalculation
- **Feature / journey:** Reservation edit
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `edit`, `update`; routes `admin.reservations.edit/update`
- **Preconditions / setup:** Reservation `R2` (pending) for `PA`, `T+60` to `T+63`, 2 adults.
- **Test data:** New dates `T+60` to `T+65`, adults 3, new phone, locale `en`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/reservations/R2/edit` | Form prefilled (guest, dates, counts, notes). |
  | 2 | Change dates and adults, save | Redirect to detail with "Reservation updated successfully." Nights = 5; subtotal, fees, taxes, total recalculated; old price lines replaced by new set. |
  | 3 | Change property to another property of the tenant | Totals recompute using the new property's rates and establishment taxes/currency. |
  | 4 | Change guest name and phone | Guest record updated (affects all reservations of that guest). |
  | 5 | Change email to a new address | Reservation `email` and guest email change; record whether an email is sent (none expected). |
  | 6 | Set check_in in the past (edit rule is only `date`) | Accepted on edit (create rejects). Record. |
  | 7 | Edit a confirmed or completed reservation | Status unchanged by edit. Paid amount does not change; total may differ from payments. Record. |
  | 8 | Host edits a reservation of unassigned establishment | 403. |
- **Acceptance criteria:** Totals and lines equal a fresh calculation; no duplicate price lines.
- **Evidence to capture:** Before/after screenshots; `reservation_price_lines` rows.
- **Notes / assumptions / open questions:** The email in step 5 does not notify the customer of changes; no `reservation_updated` template exists. Edits on already paid reservations produce a total/payment mismatch without warning. DEFECT CANDIDATE: `update` calls `PricingCalculator::calculate` without selected property add-ons (the customer-side `AuthController::updateReservation` passes them), so a reservation booked with add-ons (UAT-PROP-013) may lose its add-on price lines and amount after an admin edit. Verify with such a reservation.

## UAT-RES-006: Reservation list: search, filters, pagination, calendar view
- **Feature / journey:** `/admin/reservations` index
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `AdminReservationController::index`; `resources/views/admin/bookings/index.blade.php`; `ReservationWorkflowAndEmailTest::test_admin_can_review_reservations_and_queue_status_updates`
- **Preconditions / setup:** 20+ reservations over two months with various statuses and two properties.
- **Test data:** Search terms: ref fragment, `uat-guest-1`, property name, guest name. Filters: `status=confirmed`, `date_from`, `date_to`; `view=calendar&month=YYYY-MM`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open list | 15 per page, newest first, pager for more. |
  | 2 | Search by ref fragment, email, property name, guest name | Matching reservations only. |
  | 3 | `?status=confirmed` | Only confirmed. Unknown status (`?status=zzz`) returns an empty list. |
  | 4 | `?date_from=T&date_to=T+30` | Reservations with check_in >= from and check_out <= to. |
  | 5 | `?view=calendar&month=<current month>` | Monday-start month grid with reservations spanning their days. |
  | 6 | `?view=calendar&month=2026-13`, `?month=abc`, `?view=grid` | Validation error (no 500). |
  | 7 | Page 2 while filtering | Filters kept in pager links. |
  | 8 | Host account | Only reservations for assigned establishments. |
  | 9 | Search term `%` or `_` | Treated as like-wildcards (record); no error. |
- **Acceptance criteria:** Filters combine correctly and respect role scope.
- **Evidence to capture:** Screenshots per filter.
- **Notes / assumptions / open questions:** `date_to` compares check_out, so a stay that ends after `date_to` is excluded even if it starts inside the range.

## UAT-RES-007: Reservation detail page contents and internal notes
- **Feature / journey:** Reservation detail and notes
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `show`, `updateNotes`; lang `flash.reservation_notes_saved`; `ReservationWorkflowAndEmailTest::test_customer_remark_is_stored_and_displayed_on_reservation_page`
- **Preconditions / setup:** Reservation `R3` with customer note `Baby cot` and a payment attempt.
- **Test data:** Internal note of 5000 chars and 5001 chars.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/reservations/R3` | Shows guest, property, dates, price lines, payment attempts, receipts, customer remark ("Remarque client" in FR), internal note editor, status form, message-customer link. |
  | 2 | Save internal note `Key at reception` via the notes form | "Notes saved" flash (lang `flash.reservation_notes_saved`); persists. |
  | 3 | Save an empty note | Clears the note. |
  | 4 | Note of 5001 chars | Validation error. 5000 accepted. |
  | 5 | Confirm the customer does not see the internal note on `/dashboard/reservations/R3` | Internal note not shown to customer; customer note shown. |
- **Acceptance criteria:** Internal notes stay private; content is complete.
- **Evidence to capture:** Screenshots admin and customer views.
- **Notes / assumptions / open questions:** The status form appends notes up to 500 chars separately (RES-009), the notes form replaces the whole note field.

## UAT-RES-008: Viewing a reservation creates a customer account and message thread
- **Feature / journey:** Side effect of `show` (customer thread)
- **Priority:** P2
- **Role / permissions:** Admin, Host, Concierge
- **References:** `AdminReservationController::ensureCustomerThread`; `admin.messages.show`
- **Preconditions / setup:** Reservation created by admin for `uat-walkin@example.test` (no user account exists for that email).
- **Test data:** Email `uat-walkin@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check `users` for the email | None. |
  | 2 | Open the reservation detail page | Page loads. |
  | 3 | Check `users` again | A customer user was created (random password, role `customer`, email notification flags on) and linked to the reservation (`user_id`). A `message_threads` row exists for customer + establishment. |
  | 4 | Click the "message customer" link | Opens `/admin/messages/{thread}`. |
  | 5 | Try registering publicly with the same email | Record behavior (account already exists; password reset path). |
- **Acceptance criteria:** Behavior is recorded; thread opens.
- **Evidence to capture:** DB rows before/after; screenshots.
- **Notes / assumptions / open questions:** A GET request creating accounts is unusual. Confirm with product owner that this is intended (also affects `users` counts on the dashboard).

## UAT-RES-009: Status changes, internal note append and customer email
- **Feature / journey:** Status transitions
- **Priority:** P0
- **Role / permissions:** Admin, Host, Concierge
- **References:** `updateStatus`; routes `admin.reservations.update-status`; lang `flash.reservation_status_updated`; `ReservationWorkflowAndEmailTest::test_admin_can_review_reservations_and_queue_status_updates`
- **Preconditions / setup:** Reservation `R4` status `pending`, guest locale `en`.
- **Test data:** status values listed; note `Guest called`; note of 501 chars.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Set `confirmed` with note `Guest called` | Redirect to detail with "Reservation status updated." Status Confirmed; note appended on a new line to existing notes. |
  | 2 | Check `email_outbox` | One `reservation_status_updated` row for the guest, locale `en`. |
  | 3 | Save the form again with the same status `confirmed` | Flash shown; no new email (only on change). |
  | 4 | Walk through `checked_in`, then `completed` | Each change queues one email. For `completed` the payload includes a signed review URL valid 30 days and review channel. |
  | 5 | Set each remaining status: `pending`, `pending_payment`, `pending_validation`, `cancelled` | All accepted, in any order (no transition rules). |
  | 6 | Send `status=payment_failed` or `status=foo` | Validation error (not in allowed list). |
  | 7 | Note of 501 chars | Error (max 500). |
  | 8 | Host unassigned / other tenant | 403 / 404. |
  | 9 | Customer opens `/dashboard/reservations/R4` | Shows the updated status. |
- **Acceptance criteria:** Status persists; emails only on change; no unexpected status values.
- **Evidence to capture:** Screenshots; `email_outbox` rows.
- **Notes / assumptions / open questions:** OPEN QUESTION: there is no transition matrix (e.g. `completed` can go back to `pending`; `confirmed` can be set without any payment). Confirm intended with the product owner. `payment_failed` exists in labels but is not accepted here.

## UAT-RES-010: Cancel releases dates; reopening a cancelled reservation is not re-validated
- **Feature / journey:** Cancellation and availability
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `updateStatus`; `AvailabilityService` (`status != cancelled`)
- **Preconditions / setup:** `PA`: reservation `R5` confirmed `T+70` to `T+73`.
- **Test data:** Second guest `uat-guest-2@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Set `R5` to `cancelled` | Status Cancelled; `reservation_status_updated` email queued. |
  | 2 | Public availability or admin check for `T+70` to `T+73` | Available. |
  | 3 | Create `R6` for the same dates | Accepted. |
  | 4 | Set `R5` back to `confirmed` | Record: per code no availability check is performed, so two active reservations may now overlap. |
  | 5 | Open `/admin/properties/PA/availability?check_in=T+70&check_out=T+73` | Not available. Both reservations present in list and calendar view. |
  | 6 | Check the exported ICS (UAT-CALA-009) | Both reservations appear as Unavailable. |
- **Acceptance criteria:** Cancelling frees dates; any overlap created in step 4 is documented as a defect or accepted behavior.
- **Evidence to capture:** Screenshots, DB rows showing overlap.
- **Notes / assumptions / open questions:** DEFECT CANDIDATE: reopening bypasses double-booking protection. Product owner decision needed.

## UAT-RES-011: Cancellation of a paid reservation: refund and fee handling
- **Feature / journey:** Cancellation with payment
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `updateStatus`; no refund route in `routes/web.php`; `Establishment` fields `cancellation_fee_percent`, `cancellation_fee_days`; `PaymentGateway::refund()` is declared in `app/Contracts/PaymentGateway.php` and implemented by gateways, but no controller in `app/` or `routes/` calls `->refund(`; cancellation fee is only used when building the pay-later hold at checkout (`CheckoutController.php` lines 107-120)
- **Preconditions / setup:** Reservation `R7` confirmed, with a `paid` attempt and a receipt. Establishment cancellation fee 20 percent, 7 days.
- **Test data:** Cancellation 3 days before check-in, then 30 days before.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Look for any refund or "cancel with refund" control on the detail page | None expected from the code (record exactly what is shown). |
  | 2 | Set status `cancelled` (3 days before check-in) | Status changes; email queued. No refund amount, fee deduction, or payment attempt change is made automatically. |
  | 3 | Check payment attempts and receipts after cancel | Unchanged (`paid` stays). |
  | 4 | Check reports `/admin/reports` for the period | Reservation counted as cancelled; collected amount (receipts) still includes the receipt. Record. |
  | 5 | Customer view of the cancelled reservation (`/dashboard/reservations/R7`) | Record the status shown. The customer modification action is refused for cancelled reservations (`messages.reservation.modification_unavailable`). |
- **Acceptance criteria:** Behavior documented; any gap is raised for the product owner.
- **Evidence to capture:** Screenshots; DB payment/receipt rows.
- **Notes / assumptions / open questions:** Refund processing from the admin UI is NOT SUPPORTED in code reviewed. The reports page itself states refunds are not computed ("remboursements ne sont pas calculÃ©s"). Do not mark this case Pass; mark Pass only if the tester accepts documented behavior.

## UAT-RES-012: Upload payment proof from admin (validation and replacement)
- **Feature / journey:** Staff payment proof upload
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `uploadPaymentProof`, `PaymentProofService::attach`; lang `receipts.proof_uploaded`; also `updateStatus` optional `payment_proof`
- **Preconditions / setup:** Reservation `R8` pending with no payment attempt. Files: `proof.jpg` 500 KB, `proof.pdf` 1 MB, `proof-big.pdf` 5.1 MB, `proof.png`, `proof.webp`, `proof.gif`, `proof.exe`.
- **Test data:** Files above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/admin/reservations/R8/payment-proof` with `proof.jpg` | Back with "Payment proof uploaded." An `offline` payment attempt (status `created`) is created with amount = reservation total; file stored in `public/uploads/reservations/{id}/<timestamp>-<random>.jpg`. |
  | 2 | Upload `proof.pdf` | Previous file deleted from disk, new file attached to the latest attempt. |
  | 3 | Upload `proof-big.pdf` (just over 5 MB) | Validation error (max 5120 KB). A file of exactly 5120 KB accepted. |
  | 4 | Upload `proof.gif`, `proof.exe`, `proof.png`, `proof.webp` | `gif`/`exe` rejected; `png`/`webp` accepted. |
  | 5 | No file | Required error. |
  | 6 | Use the status form with `payment_proof` and status `pending_validation` | Status changes and proof is attached. |
  | 7 | Open the proof link in the detail page | File downloads under its original name. |
  | 8 | Try path tampering on `/reservations/R8/payment-proofs/{otherAttemptId}` | 404 when attempt does not belong to the reservation. |
- **Acceptance criteria:** Only allowed types and sizes are stored; downloads work and stay inside the reservation folder.
- **Evidence to capture:** Screenshots; directory listing; DB payload JSON (no PII).
- **Notes / assumptions / open questions:** Files are stored in the public upload path; confirm with ops that the `uploads/reservations` folder is not directly browsable in production (proof download is controlled by a route, but the file path pattern is guessable by timestamp+random only).

## UAT-RES-013: Approve an offline payment (confirm with proof, receipt, emails)
- **Feature / journey:** Offline payment approval
- **Priority:** P0
- **Role / permissions:** Admin, Host, Concierge
- **References:** `ReceiptController::confirmOfflinePayment`; route `admin.reservations.confirm-offline-payment`; lang `receipts.confirmed_and_sent`; `ReservationWorkflowAndEmailTest::test_admin_can_edit_notes_update_status_with_proof_and_confirm_payment_with_reference`, `::test_confirmed_payment_automatically_creates_and_emails_receipt`
- **Preconditions / setup:** Reservation `R9` pending validation with an `interac` payment attempt created by the customer; or a reservation with no attempt.
- **Test data:** provider_reference `INT-UAT-0001`, proof `proof.jpg`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On the detail page use the confirm form (`provider_reference`, required `payment_proof`) and submit | Back with "Payment confirmed and receipt queued for delivery." |
  | 2 | Check the attempt | Existing `interac`/`wise`/`revolut` attempt set to `paid`, reference updated, payload has `confirmed_by` and `confirmed_at`. If none existed, an `offline` attempt with status `paid` is created. |
  | 3 | Check reservation | Status `confirmed`; notes end with "Offline payment confirmed by admin." |
  | 4 | Check receipt | One receipt `RC-YYYYMMDD-XXXXXXXX`, amount = attempt amount; detail page lists a download link. |
  | 5 | Check `email_outbox` | `receipt_issued` and `reservation_status_updated` rows queued. |
  | 6 | Submit the form without a file | Validation error (proof required). |
  | 7 | Submit again (second confirmation) | Proof replaced; no second receipt (receipt is unique per attempt). Another `reservation_status_updated` email is queued; record. |
  | 8 | Download the receipt as the customer | PDF with receipt number. |
- **Acceptance criteria:** Confirming transitions the reservation, records the payment and issues exactly one receipt.
- **Evidence to capture:** Screenshots; DB rows; outbox rows; PDF.
- **Notes / assumptions / open questions:** The action does not check that the reservation is still available or not cancelled; confirming a cancelled reservation sets it back to confirmed (same concern as RES-010). The attempt amount for a pre-existing attempt is not validated against the total.

## UAT-RES-014: Rejecting an offline payment proof (gap check)
- **Feature / journey:** Offline payment rejection
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `routes/web.php` (no reject route); `ReceiptController`; `ReservationEmailService::queueOfflineProofNotification`
- **Preconditions / setup:** Reservation `R10` with a customer-submitted proof (`pending_validation`).
- **Test data:** Reason note `Amount does not match`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Look for a "reject proof" action on the detail page | None expected. Record the visible controls. |
  | 2 | Use the status form: set `pending_payment` (or `pending`) with note `Amount does not match` | Status saved; customer email `reservation_status_updated` queued. The proof file and attempt remain. |
  | 3 | Customer opens checkout (`/reservations/R10/checkout?token=...`) | Record whether a new proof can be submitted and what status message is shown. |
  | 4 | Set `cancelled` instead | Reservation cancelled; dates freed. |
- **Acceptance criteria:** Tester documents how a rejection is possible today and what the customer sees.
- **Evidence to capture:** Screenshots of admin and customer views, outbox rows.
- **Notes / assumptions / open questions:** Dedicated approve/reject workflow with reason is NOT SUPPORTED in code. Raise to product owner. Do not mark this as passed without agreement.

## UAT-RES-015: Send payment link to customer
- **Feature / journey:** Payment link email
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `sendPaymentLink`; `Reservation::canSendPaymentLink` (status not `confirmed` and no `verified` attempt); `ReservationEmailService::queuePaymentLink`; lang `flash.payment_link_queued`, `payment_link.email_subject`
- **Preconditions / setup:** Reservation `R11` pending (locale `fr`); reservation `R12` confirmed.
- **Test data:** Guest `uat-guest-1@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `R11` detail | "Send payment link" form visible with the checkout link. |
  | 2 | Submit | "The payment link has been queued for delivery." Outbox row `payment_link`, subject "Lien de paiement..." (FR) containing the reference. |
  | 3 | Run `php artisan messages:send-email-notifications`; open the mail | Link points to `/reservations/R11/checkout?token=...&lang=fr`; shows price lines and a register link. |
  | 4 | Open the link in a private window | Checkout page for the reservation loads without login. |
  | 5 | Open `R12` (confirmed) | Payment-link form hidden. |
  | 6 | POST the route for `R12` manually | 403. |
  | 7 | Same for a reservation with a `verified` payment attempt | Hidden / 403. |
- **Acceptance criteria:** Link sent only for unpaid reservations; checkout works from the email.
- **Evidence to capture:** Screenshots; mail content; outbox row.
- **Notes / assumptions / open questions:** `paid` (non-`verified`) attempts do not hide the link, only `verified` ones; confirm which status the live gateways use.

## UAT-RES-016: Generate and send a receipt manually
- **Feature / journey:** Manual receipt
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `generateReceipt`; `ReservationEmailService::issueReceiptAndQueueEmail`; `ReceiptController::download`; lang `receipts.generated_and_sent`, `receipts.no_paid_attempt`
- **Preconditions / setup:** Reservation `R13` with a `paid` attempt and no receipt; reservation `R14` with no paid attempt.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click generate receipt on `R13` | "Receipt generated and queued for delivery." One receipt created; `receipt_issued` queued. |
  | 2 | Click again | No second receipt row; no second email (receipt unique per attempt). |
  | 3 | On `R14` submit generate | 422 with "No paid payment was found for this reservation." |
  | 4 | Download `/reservations/R13/receipt` as admin, as the owning customer | PDF `RC-....pdf` downloaded. |
  | 5 | Download as a different customer | 403. |
  | 6 | Download for a reservation with no receipt | 404. |
- **Acceptance criteria:** Receipts are unique per payment and downloadable only by authorized users.
- **Evidence to capture:** PDF, DB rows, status codes.
- **Notes / assumptions / open questions:** Download picks the latest receipt by `issued_at`.

## UAT-RES-017: Delete reservation (admin only) and cleanup
- **Feature / journey:** Reservation deletion
- **Priority:** P1
- **Role / permissions:** Admin only; Host, Concierge, Customer forbidden
- **References:** `destroy`; lang `flash.reservation_deleted`, `admin.delete_reservation_confirmation`; `ReservationWorkflowAndEmailTest::test_only_admin_can_delete_reservation_and_associated_payment_records`
- **Preconditions / setup:** Reservation `R15` with an uploaded proof; its guest has only this reservation. Reservation `R16` whose guest has two reservations.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As concierge/host/customer send DELETE `/admin/reservations/R15` | 403. |
  | 2 | Admin deletes `R15` (confirm dialog) | Redirect to list with "Reservation and associated payment data deleted." Proof file removed from disk. Guest record removed (no other reservations). |
  | 3 | Delete `R16` | Guest kept because of other reservation. |
  | 4 | Check dates again in availability | Freed. |
  | 5 | Check log `/admin/logs` | Notice "Reservation deleted by administrator" with reservation ref and admin id. |
  | 6 | Check receipts of the deleted reservation | Record (cascade or orphan). |
- **Acceptance criteria:** Deletion is permanent, cleans files and logs the action.
- **Evidence to capture:** Screenshots; directory listing; log line.
- **Notes / assumptions / open questions:** Deleting a reservation with paid payments removes payment history; financial reports change. Confirm intended.

## UAT-RES-018: Tenant and host scoping on reservation actions
- **Feature / journey:** Reservation isolation
- **Priority:** P0
- **Role / permissions:** Host (assigned `E1`), Admin of tenant A vs reservation of tenant B or of `E2`
- **References:** `ownedReservation`, `ownedProperty`, `tenantProperties`; `ReceiptController::confirmOfflinePayment`
- **Preconditions / setup:** Reservation `RE2` belongs to property of `E2` (host not assigned). In multi-tenant mode a reservation `RB` of tenant B.
- **Test data:** Ids `RE2`, `RB`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Host opens `/admin/reservations/RE2`, `/edit`, PUT, PATCH status, PATCH notes, POST payment-link, payment-proof, receipt | Each 403. |
  | 2 | Host `/admin/reservations` | Only `E1` reservations. |
  | 3 | Host opens create form | Property select lists only `E1` properties; posting `property_id` of `E2` gives 403. |
  | 4 | Tenant A admin opens `RB` via the same URLs | 404. |
  | 5 | Host posts `POST /admin/reservations/RE2/confirm-offline-payment` with a valid proof | Expected: 403/404. Record actual: this action is handled by `ReceiptController` which, per code review, does not call an ownership check. |
  | 6 | Same for tenant A admin on `RB` | Expected 404. Record actual. |
  | 7 | Concierge on `RE2` | Allowed on tenant-level (concierge is not restricted to establishments). |
- **Acceptance criteria:** No staff user can read or change reservations outside their scope; any step-5/6 success is a security defect.
- **Evidence to capture:** Status codes per call; DB check that `RE2` is unchanged after step 5.
- **Notes / assumptions / open questions:** SECURITY CANDIDATE: `confirmOfflinePayment` lacks `ownedReservation` (binding may still scope by tenant if `Reservation` has a global scope; verify). Run step 5 only in a test environment.

## UAT-RES-019: Payment proof download authorization
- **Feature / journey:** Proof file access control
- **Priority:** P1
- **Role / permissions:** Admin, Concierge, Host; owning customer; other customer; anonymous with token
- **References:** `ReceiptController::downloadProof`, `authorizeReservation` (checkout token allowed for proofs only); route `reservations.payment-proof.download`
- **Preconditions / setup:** Reservation `R17` with a proof attached to attempt `AT1` and a `checkout_token`.
- **Test data:** URL `/reservations/R17/payment-proofs/AT1`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Staff user opens the URL | File downloads with the original name. |
  | 2 | Owner customer (same user id or same email) | Downloads. |
  | 3 | Another logged-in customer | 403. |
  | 4 | Anonymous with `?token=<checkout_token>` | Downloads. |
  | 5 | Anonymous with a wrong or missing token | 403 (or login redirect, record). |
  | 6 | Anonymous with token on `/reservations/R17/receipt` | 403 (tokens do not apply to receipts). |
  | 7 | Delete the file from disk then open | 404. |
- **Acceptance criteria:** Proof is available only to staff, the owner or token holder.
- **Evidence to capture:** Status codes.
- **Notes / assumptions / open questions:** None.
