# 03 — User Flows

**Purpose:** show end-to-end behavior and handoffs between guest, staff, payment provider, permanent driver, and fleet schedule.

## 1. Shared booking flow

```mermaid
flowchart TD
    A[Choose one of four services] --> B[Enter service-specific details]
    B --> C{Input valid and within online window?}
    C -- No --> C1[Explain issue and offer staff-assisted enquiry]
    C -- Yes --> D[Check location, duration, suitability and availability]
    D --> E{Can calculate route and estimate?}
    E -- No --> E1[Preserve details and switch to custom quote]
    E -- Yes --> F{Suitable vehicle available?}
    F -- No --> F1[Show nearby alternatives and staff assistance]
    F -- Yes --> G[Compare exact vehicles]
    G --> H[Select vehicle — start 15-minute hold]
    H --> I[Enter booker, lead traveler, extras and needs]
    I --> J[Review itemized estimate, policy and Sri Lanka time]
    J --> K{Hold and availability still valid?}
    K -- No --> K1[Explain expiry and recheck without losing safe data]
    K -- Yes --> L[Accept required terms and submit request]
    L --> M[Show reference, private link and pending status]
    M --> N[Notify staff + WhatsApp + optional email]
    N --> O[Staff review]
```

### Required distinctions

- Vehicle availability is not confirmation.
- Estimate is not final proposal.
- Request submitted is not confirmed.
- Customer action may be required before payment.
- Payment may be full, deposit, or deferred according to the final proposal.

## 2. Staff review and confirmation

```mermaid
flowchart TD
    A[New submitted request] --> B[Staff opens review workspace]
    B --> C[Validate itinerary, eligibility, capacity, schedule and estimate]
    C --> D{Can fulfill as requested?}
    D -- No --> E{Alternative possible?}
    E -- No --> F[Decline with reason and support path]
    E -- Yes --> G[Create revised vehicle/time/route/price proposal]
    D -- Yes --> H[Create final proposal from request]
    G --> I[Set payment choices and acceptance deadline]
    H --> I
    I --> J[Send proposal through private page + messages]
    J --> K{Traveler accepts before deadline?}
    K -- No --> L[Expire or continue staff follow-up according to policy]
    K -- Requests change --> M[Keep original confirmed booking if one exists; review new request]
    K -- Yes --> N{Payment required now?}
    N -- No / pay later allowed --> O[Confirm booking]
    N -- Yes --> P[Traveler selects allowed full/deposit option]
    P --> Q{Payment outcome}
    Q -- Success --> O
    Q -- Pending --> R[Show pending; reconcile provider callback]
    Q -- Failed/cancelled --> S[Retry or choose another allowed option before deadline]
    O --> T[Reserve final windows and notify traveler/staff/driver when assigned]
```

## 3. Airport Pickup flow

```mermaid
flowchart TD
    A[Select Airport Pickup] --> B[Choose supported arrival airport]
    B --> C[Enter flight number + arrival date/time]
    C --> D[Enter destination + map pin/instructions]
    D --> E[Passengers, luggage, name-board text, assistance/extras]
    E --> F[Show waiting, delay and no-show summary]
    F --> G[Calculate route, service window and estimate]
    G --> H[Exact vehicle availability and selection]
    H --> I[Shared checkout and submission]
    I --> J[Staff manually monitors flight]
    J --> K{Operational timing changes?}
    K -- No --> L[Driver details and meeting instructions sent]
    K -- Yes --> M[Adjust operational timing; notify traveler and driver]
    M --> L
    L --> N[En route → Arrived → Started → Completed]
```

### Airport Pickup exceptions

- Unknown/unlisted airport → custom quote or unsupported-location guidance.
- Missing/invalid flight number → allow correction; staff-assisted fallback if policy permits.
- Flight delay → manual monitoring and policy-based timing adjustment.
- Traveler cannot find driver → urgent support with booking context.
- Waiting limit reached/no-show → staff records contact attempts, incident, and policy outcome.

## 4. Airport Transfer flow

```mermaid
flowchart TD
    A[Select Airport Transfer] --> B[Enter pickup location + instructions]
    B --> C[Choose airport + enter departure flight]
    C --> D[Calculate route and safe recommended pickup time]
    D --> E{Traveler accepts recommendation?}
    E -- Yes --> F[Passengers, luggage, assistance/extras]
    E -- Chooses later time --> G[Show missed-flight-risk warning and flag review]
    G --> F
    F --> H[Exact vehicle availability and estimate]
    H --> I[Shared checkout and submission]
    I --> J[Staff approves or proposes safer time]
    J --> K[Traveler accepts final time]
    K --> L[Confirm + send driver/meeting details]
```

### Airport Transfer exceptions

- Route timing service unavailable → show staff-calculated-time custom review.
- Pickup is remote/uncertain → require map pin/instructions and custom quote if needed.
- Traveler insists on unsafe time → staff may decline or request explicit policy acknowledgment; final business rule is client input.

## 5. Ride flow

```mermaid
flowchart TD
    A[Select Ride] --> B[Enter pickup]
    B --> C[Add ordered stop]
    C --> D[Enter expected wait at stop]
    D --> E{Add another stop?}
    E -- Yes --> C
    E -- No --> F[Enter final destination]
    F --> G{Add return leg?}
    G -- Yes --> H[Enter separate return pickup/date/time/route]
    G -- No --> I[Continue]
    H --> I
    I --> J[Passengers, luggage, assistance/extras]
    J --> K[Calculate each service window + estimate]
    K --> L[Exact vehicle availability across all required windows]
    L --> M[Shared checkout and submission]
```

### Ride schedule rule

An outbound and return leg share one booking but do not automatically hold the vehicle between legs. Planned waiting holds and prices that time explicitly. If the exact vehicle is unavailable for one leg, the result must explain the conflict and offer supported alternatives rather than silently split vehicles.

### Confirmed Ride change

```mermaid
flowchart LR
    A[Traveler requests route/time/stop change] --> B[Original booking remains active]
    B --> C[Recalculate schedule and price]
    C --> D[Staff sends revised proposal]
    D --> E{Traveler accepts?}
    E -- Yes --> F[Replace with revised version + notify]
    E -- No / expires --> G[Original booking remains]
```

## 6. Rent flow

```mermaid
flowchart TD
    A[Select Rent — self-drive] --> B[Pickup + return place/date/time]
    B --> C[Driver age, nationality, licence country]
    C --> D[Expected distance + handover method]
    D --> E[Show 24-hour calculation and eligibility summary]
    E --> F[Filter exact vehicles by time, rental eligibility and features]
    F --> G[Select vehicle — 15-minute hold]
    G --> H[Choose relevant extras / additional driver]
    H --> I[Review mileage, fuel, insurance/excess, deposit and late-return terms]
    I --> J[Submit pending request]
    J --> K[Staff reviews eligibility and proposal]
    K --> L[Traveler accepts and completes allowed payment]
    L --> M[Confirmed handover appointment]
    M --> N[Original documents checked at collection]
    N --> O{Checks complete?}
    O -- No --> P[Do not release vehicle; resolve or decline per policy]
    O -- Yes --> Q[Record condition, odometer, fuel, accessories and signatures]
    Q --> R[Vehicle handed over / In progress]
    R --> S[Return inspection and charge/refund review]
    S --> T[Completed or incident opened]
```

### Rent extension

```mermaid
flowchart TD
    A[Traveler requests new return time] --> B[Check future booking + maintenance + buffer]
    B --> C{Extension available?}
    C -- No --> D[Decline and retain original return requirement]
    C -- Yes --> E[Calculate revised duration/charges]
    E --> F[Staff approves proposal]
    F --> G[Traveler accepts and pays if required]
    G --> H[Update return time and notify handover staff]
```

### Rent incident

- Urgent contact opens a booking-aware support path.
- Staff logs breakdown, accident, damage, safety issue, or dispute.
- Record facts, attachments, actions, charges, insurer/police references when applicable, and resolution.
- The UX does not replace emergency, legal, insurer, or roadside procedures supplied by the client.

## 7. No-availability flow

```mermaid
flowchart TD
    A[No exact vehicle satisfies requested window/needs] --> B[Explain why at a useful level]
    B --> C[Suggest nearby times/dates]
    B --> D[Suggest other suitable exact vehicles]
    B --> E[Allow criteria adjustment]
    B --> F[Offer staff-assisted request]
    C --> G[Re-run availability]
    D --> G
    E --> G
    F --> H[Submit custom assistance request with original details preserved]
```

Do not expose another customer’s booking details when explaining unavailability.

## 8. Checkout-hold expiry flow

```mermaid
flowchart TD
    A[15-minute countdown reaches zero] --> B[Stop final submission]
    B --> C[Preserve safe form data]
    C --> D[Explain that the vehicle hold expired]
    D --> E[Recheck exact window]
    E --> F{Same vehicle still available?}
    F -- Yes --> G[Start new hold and continue]
    F -- No --> H[Show valid alternatives]
```

The timer must not be the only warning. Announce approaching expiry accessibly and avoid trapping a user who needs more time for accessibility reasons; final behavior may include a policy-controlled extension.

## 9. Proposal and payment flow

```mermaid
stateDiagram-v2
    [*] --> ProposalAvailable
    ProposalAvailable --> Accepted: traveler accepts
    ProposalAvailable --> ChangeRequested: traveler requests change
    ProposalAvailable --> Expired: deadline passes
    ChangeRequested --> ProposalAvailable: staff sends new version
    Accepted --> PaymentChoice: payment required or optional
    Accepted --> Confirmed: pay later permitted
    PaymentChoice --> HostedCheckout: online option selected
    PaymentChoice --> AwaitingOffline: bank transfer / offline option selected
    HostedCheckout --> Confirmed: success satisfies requirement
    HostedCheckout --> PaymentChoice: failed/cancelled
    HostedCheckout --> PendingReconciliation: provider pending/unknown
    PendingReconciliation --> Confirmed: later success
    PendingReconciliation --> PaymentChoice: later failure
    AwaitingOffline --> Confirmed: staff records sufficient payment
    Expired --> [*]
    Confirmed --> [*]
```

## 10. Change and cancellation flow

```mermaid
flowchart TD
    A[Open private booking page] --> B{Choose action}
    B -- Request change --> C[Describe changed fields / choose supported edits]
    C --> D[Show original remains active]
    D --> E[Staff reviews availability and price]
    E --> F[Send revised proposal]
    F --> G{Accepted?}
    G -- Yes --> H[Apply revised booking]
    G -- No / expires --> I[Retain original]

    B -- Request cancellation --> J[Show applicable policy and estimated refund/fee]
    J --> K[Traveler confirms request]
    K --> L{Automatic within policy or staff review?}
    L --> M[Cancel and release windows]
    L --> N[Staff reviews and records outcome]
    M --> O[Notify + refund status]
    N --> O
```

Exact auto/manual cancellation behavior depends on client-approved policy values.

## 11. Vehicle disruption after confirmation

```mermaid
flowchart TD
    A[Chosen vehicle becomes unavailable] --> B[Staff records operational issue]
    B --> C{Equal/better suitable replacement exists?}
    C -- No --> D[Offer cancellation/refund or staff-arranged alternative]
    C -- Yes --> E[Create replacement proposal with price effect]
    E --> F{Traveler accepts?}
    F -- Yes --> G[Replace exact vehicle and update driver/meeting details]
    F -- No --> D
```

Never silently substitute the exact confirmed vehicle.

## 12. Driver flow

```mermaid
flowchart TD
    A[Permanent driver receives secure trip link] --> B[Open assignment]
    B --> C[Review vehicle, time, route, meeting point, traveler contact and needs]
    C --> D[Acknowledge]
    D --> E[Mark En route]
    E --> F[Mark Arrived]
    F --> G[Contact traveler / support if needed]
    G --> H[Mark Started]
    H --> I[Follow route and stop instructions]
    I --> J[Mark Completed]
    J --> K[Staff verifies closeout / incident if needed]
```

## 13. Rent handover flow

### Collection

```mermaid
flowchart TD
    A[Open today’s handover] --> B[Verify booking + renter]
    B --> C[Inspect original passport, licence and permit]
    C --> D{Eligibility valid?}
    D -- No --> E[Stop release and escalate]
    D -- Yes --> F[Confirm contract + payment/deposit]
    F --> G[Record odometer, fuel, damage photos, accessories]
    G --> H[Capture staff/renter signatures]
    H --> I[Release vehicle and mark In progress]
```

### Return

```mermaid
flowchart TD
    A[Open scheduled return] --> B[Record actual return time]
    B --> C[Record odometer and fuel]
    C --> D[Compare condition and accessories]
    D --> E{New issue or charge?}
    E -- No --> F[Approve deposit/refund outcome]
    E -- Yes --> G[Open incident/charge review]
    G --> H[Record evidence and traveler acknowledgment]
    H --> F
    F --> I[Complete booking and send final invoice/feedback]
```

## 14. Notification-failure flow

```mermaid
flowchart TD
    A[Message send fails] --> B[Booking/status change still succeeds]
    B --> C[Record failed channel and alert staff]
    C --> D[Success/private page continues to show reference and next action]
    C --> E[Retry automatically according to provider policy]
    C --> F[Allow staff or traveler resend]
    E --> G[Record delivered or final failure]
    F --> G
```

## 15. Feedback flow

```mermaid
flowchart LR
    A[Booking completed] --> B[Send private rating request]
    B --> C[Rating + optional comment]
    C --> D{Consent to testimonial use?}
    D -- No --> E[Internal feedback only]
    D -- Yes --> F[Moderation queue]
    F --> G{Approved?}
    G -- Yes --> H[Eligible for public use]
    G -- No --> E
```

## 16. Status ownership

| Transition | Primary actor | Required evidence/action |
|---|---|---|
| Hold → Submitted | Guest/system | Terms accepted, authoritative availability recheck, reference created |
| Submitted → Under review | Staff/system | Staff opens/claims request or queue rule applies |
| Under review → Proposal sent | Staff | Versioned details, price, payment choices, deadline, message |
| Proposal → Accepted | Guest | Explicit acceptance with timestamp/version |
| Accepted → Awaiting payment | System/staff | Payment requirement exists |
| Accepted/Awaiting payment → Confirmed | System/staff | Required acceptance/payment satisfied |
| Confirmed → In progress | Driver/staff | Service started or Rent handed over |
| In progress → Completed | Driver/staff | Service closeout or Rent return completed |
| Any eligible state → Cancelled | Guest/staff | Policy outcome, reason, release, refund status |
| Review → Declined | Staff | Reason, alternative/support path, release |
| Hold/proposal/payment → Expired | System/staff | Deadline elapsed, notification, release |

## 17. Flow acceptance checks

- No flow silently skips staff confirmation.
- No flow loses a traveler’s valid original confirmed booking while a change is being considered.
- No flow exposes a selectable incompatible/overlapping vehicle.
- No flow requires an account.
- No payment flow collects raw card details in the product.
- Every dead end offers a safe explanation and next action.
- Every material action has an owner, state change, notification effect, and audit event.
