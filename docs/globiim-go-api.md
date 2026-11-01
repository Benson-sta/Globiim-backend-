# Globiim Go API — initial backend foundation

All routes are under `/api/v1/go`. Private routes require Laravel Sanctum authentication.

## Implemented API foundation
- `GET /dashboard` — current user's Go activity counts.
- `POST /partners/apply` — sales agent, driver, fleet owner, tour, bus, courier, truck and corporate partner applications.
- `POST /drivers/apply` — driver profile and documents metadata; starts pending/offline.
- `POST /vehicles` — register an owner vehicle; starts pending/inactive.
- `POST /fleet/management-requests` — request full, driver, self or investment management; no agreement is active until approved and signed.
- `POST /rides`, `GET /rides` — create and list ride requests. Matching/fare calculation is not connected.
- `GET /trips`, `POST /trips`, `POST /trips/{id}/publish`, `POST /trips/{id}/book` — draft, compliance review and transaction-locked seat reservation. A booking remains payment_pending.
- `POST /deliveries` — delivery/cargo request.
- `POST /vehicle-transports` — vehicle relocation quotation request.
- `POST /corporate/accounts` — submit a corporate account for verification.
- `POST /safety/incidents` — authenticated incident report; not an emergency dispatch service.

## Database modules included in this migration
Partner applications and agent referrals; driver profiles; owned vehicles; fleet agreements and assignments; rides; shared trips and seat bookings; bus operators, schedules and bookings; delivery jobs; vehicle relocation; rental listings and bookings; corporate accounts and members; financial ledger entries; safety incidents; reviews.

## Not yet exposed as production workflows
Agent referral activation and commission release; admin review/role policies; driver/vehicle verification; driver dispatch and GPS; pickup PIN lifecycle; fleet driver assignment and owner settlements; trip cancellation/refunds and commission settlement; bus route/schedule/seat APIs and QR ticket issue; rental search/calendar/booking; provider matching and proof-of-delivery updates; Commerce order hand-off; M-Pesa/card/Wallet/cash integrations; SOS dispatch; notifications; audit/event processing and analytics exports.

## Critical safety and payment boundaries
- No endpoint reports a successful payment. Trip seat reservations are marked `payment_pending`; integrate a payment provider and verified callbacks before confirmation.
- Seat allocation uses a database transaction and row lock to reduce double-booking risk. Configure a transactional database such as MySQL or PostgreSQL for production; test lock semantics on the chosen engine.
- No live driver matching, route calculation, map tracking, emergency dispatch or real commission payout is active.
- Uploaded documents and images are represented as metadata/URLs only. Add private object storage, signed upload URLs, access controls and retention policies before collecting identity or vehicle documents.
- Production admin routes must use explicit role/permission middleware and audit logging; do not expose approval operations to ordinary users.
- The schema is an initial modular foundation, not a completed or legally validated transport operation. Validate Kenyan PSV, insurance, tour and commercial transport requirements with qualified compliance staff.
