# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Product implementation stack is deliberately undecided until technical planning. The current browser-based walkthrough is a static review artifact, not the production application.

## Users

Primary users are international travelers planning Sri Lankan transport before they enter the country. They may book for themselves or provide separate details for a lead traveler or self-drive renter.

Internal users are:

- **Owner/Admin:** controls staff access, fleet, rates, policies, settings, exports, and all booking operations.
- **Staff:** reviews requests, communicates with travelers, manages schedules, payments, handovers, and incidents within granted permissions.
- **Driver:** the permanent driver tied to a chauffeured vehicle; uses a limited secure trip link rather than a full account or app.

## Product Purpose

The product lets international travelers check real availability across a five-vehicle fleet and request one of four advance-booked services without creating an account:

1. Airport Pickup — arrival airport to destination with driver and meet-and-greet support.
2. Airport Transfer — origin to departure airport with driver and guided pickup timing.
3. Ride — scheduled point-to-point or multi-stop travel with driver.
4. Rent — exact self-drive vehicle hire for a selected period, without a driver.

Success means travelers can understand the service differences, select a suitable exact vehicle, see a transparent estimate, submit confidently, and manage the resulting booking while staff can prevent conflicts and confirm every request reliably.

## Positioning

Unlike live ride-hailing, the product is a pre-arrival planning and fleet-reservation service: it combines exact-vehicle availability, international-traveler guidance, staff-reviewed confirmation, and guest booking management across both chauffeured and self-drive services.

## Operating Context

Travelers commonly use the site from another country, often on mobile or roaming connections. Booking dates and times are Sri Lanka time. WhatsApp is the required contact channel; email is optional. The product must handle unfamiliar locations, international phone numbers, flight context, passenger and luggage constraints, and booking on behalf of another traveler.

Staff operate a small fleet where an overlapping booking, maintenance block, preparation buffer, or unavailable permanent driver can materially affect fulfillment. Staff also record phone and WhatsApp bookings so online availability remains authoritative.

## Capabilities and Constraints

- Responsive customer web experience; no native application.
- No customer account, password, or OTP.
- All services are advance bookings; there is no live dispatch or moving-map tracking.
- Customers select one exact available vehicle from the fleet.
- Search uses service-specific fields, supported place search, optional map pin, and pickup instructions.
- Availability considers requested duration, route or rental period, configured buffers, offline bookings, and maintenance blocks.
- A selected vehicle receives a 15-minute checkout hold.
- Every submission is pending until staff confirms it.
- Staff-proposed changes require explicit traveler acceptance.
- Guest management uses a private link and booking reference.
- Prices display primarily in USD with an approximate LKR value; final settlement terms are explicit.
- Payment options are controlled per booking or policy: full payment, deposit, or pay later.
- Provider-neutral hosted payment checkout; online and offline payments share one record.
- Automatic WhatsApp messages and email when an address is supplied.
- English-first and localization-ready.
- Provider-neutral integration boundaries for maps/routes, payments, WhatsApp Business, transactional email, and exchange rates.
- Live flight tracking, live vehicle tracking, accounting sync, customer loyalty, referrals, promo codes, and digital tipping are deferred.
- Actual product name, fleet data, rates, policy values, legal wording, proof-backed claims, and final imagery remain client-supplied inputs.

## Evidence on Hand

- The operator currently has five vehicles; their exact details and imagery have not yet been supplied.
- Functional references supplied by the user:
  - https://less.lk/best-taxis-from-colombo-airport
  - https://less.lk/airport_transfer
- The reference pages expose airport, destination, date, and search concepts. They are feature references only and are not visual or UX authority.
- No verified brand assets, testimonials, prices, safety claims, legal policies, or vehicle specifications are currently available. Future work must not fabricate them.

## Product Principles

1. **Planned, not live:** communicate advance reservation and staff confirmation clearly at every stage.
2. **Availability must be trustworthy:** online, manual, maintenance, and buffer time share one schedule.
3. **International clarity:** explain Sri Lanka time, locations, currency, airport timing, inclusions, and next steps without local assumptions.
4. **No-account control:** a private booking link should provide the useful control of an account without requiring registration.
5. **Proof over claims:** use real fleet, driver, policy, and review evidence only when the client supplies it.

## Accessibility & Inclusion

Target WCAG 2.2 Level AA. Required behavior includes keyboard operation, visible focus, semantic form structure, associated labels and errors, screen-reader status announcements, sufficient contrast, adequate target sizes, reduced-motion support, structured accessibility and assistance needs, and layouts that remain usable under zoom and localization.
