Online Ticket Sales Platform - Project Specification
Project Overview
Build a multi-role Online Ticket Sales Platform where users can purchase tickets to events using account credits, organizers can list and manage their own events and seating, and an admin oversees the entire platform. The platform must handle concurrent booking conflicts gracefully - a core technical challenge of the project.
________________


Mandatory Technical Requirements
Core Stack
* Backend Framework: Laravel
* ORM: Eloquent
* Database: MySQL
________________


Functional Requirements
1. User Roles & Authentication
Three User Types:
Admin (Single account)
* Manages all organizer accounts
* Approves or rejects organizer registrations
* Views system-wide statistics and revenue
* Can cancel events (full credit refund to all ticket holders)
Organizer (Multiple accounts)
* Registers and awaits admin approval before creating events
* Creates and manages their own events and seating/ticket tiers
* Views booking history and revenue breakdown per event
* Updates physical event statuses (e.g., postponed, sold out)
User (Multiple accounts)
* Browses and searches the event catalog
* Purchases tickets using account credits
* Views booking history and downloads e-tickets (PDF/QR code)
________________


2. User Features
Registration & Login
* Email/password authentication
* Each new user receives 2000 credits upon registration
* Users cannot add more credits (fixed initial amount)
Event Browsing
* List all upcoming events
* Filter and search by:
   * Event name
   * Category (Concert, Sports, Theater, Conference, etc.)
   * Date range
   * Location / Online
   * Price range
   * Organizer
   * Availability (has tickets left)
* View event detail page: description, date/time, venue, ticket tiers, organizer name, remaining seats per tier
Cart & Purchase
* Add tickets to a cart (with quantity selection per tier)
* Purchase the entire cart using account credits
* Final price calculation per ticket: Final Price = Base Price × (1 + 0.01 System Fee)
* Cannot purchase if insufficient credits
* Real-time credit balance update after checkout
* Order confirmation with full transaction breakdown
* Immediately after purchase: e-ticket is generated (PDF or QR token) and available for download
* Seat reservation expires if not confirmed within 10 minutes (reservation timeout)
User Dashboard
* Current credit balance
* Full booking history with event details and booking status
* Downloadable e-tickets for confirmed bookings
* Upcoming events the user is attending
________________


3. Organizer Features
Registration
* Must register and wait for admin approval
* Cannot create events until approved
Event & Ticket Management (CRUD)
Create a new event with:
* Event name, description, category
* Date & time (with timezone)
* Venue name + address (or "Online" flag)
* Banner image upload
* Ticket Tiers (one event can have multiple tiers, e.g., VIP / General / Early Bird):
   * Tier name
   * Base price (in credits)
   * Total seat count
   * Sale start/end datetime per tier
* Edit event and tier details at any time (except confirmed bookings are unaffected)
* Soft-cancel events (existing bookings preserved, refunds issued)
* View per-event statistics
Organizer Dashboard
* Full event catalog with remaining capacity per tier
* Bookings received per event
* Revenue breakdown:
   * Gross revenue
   * System fee deducted (1%)
   * Net revenue (organizer's share)




4. Admin Features
Organizer Management
* View all pending organizer registration requests
* Approve or reject organizer accounts
* View list of all active organizers
* Deactivate or reactivate organizer accounts
System Overview
* Total registered users
* Total events listed
* Total tickets sold
* Total system revenue (1% from all transactions)
________________


Concurrency Challenges (Core Technical Requirement)
This is where the project diverges significantly from a simple store. Ticket sales are inherently concurrent - dozens of users may attempt to book the last seat in a tier at the exact same moment. You must implement real solutions.
Challenge 1 - Seat Inventory Race Condition (Optimistic Locking)
Scenario: Two users both see "1 seat remaining" and both click "Buy" simultaneously.
Requirement:
* Use Doctrine Optimistic Locking (@Version annotation) on the TicketTier entity
* On version conflict, catch OptimisticLockException, re-check availability, and return a meaningful error to the user ("Sorry, this tier just sold out")
* Must NOT allow overselling under any concurrent load
Challenge 2 - Seat Reservation with Expiry (Pessimistic / Soft Lock)
Scenario: A user adds tickets to cart. Those seats should be "held" for a limited time while they complete checkout, but released if they abandon.
Requirement:
* When a ticket is added to cart, create a SeatReservation record with:
   * reserved_at timestamp
   * expires_at = reserved_at + 10 minutes
   * status: pending | confirmed | expired
* Available seat count shown to other users = total_seats - confirmed_bookings - active_reservations
* A Symfony Console Command (or Messenger handler) runs periodically to expire stale reservations and restore seat counts
* On checkout, validate that the reservation has not expired before deducting credits
Challenge 3 - Double-Booking Prevention (Database-Level Uniqueness)
Scenario: For numbered seating (optional advanced feature), the same seat number must not be assigned to two users.
Requirement:
* Seat entity has a unique constraint on (event_id, seat_number)
* Attempt to double-assign a seat raises a database-level UniqueConstraintViolationException
* Application must catch this and handle gracefully (retry with next available seat or show error)
Challenge 4 - Atomic Credit Deduction
Scenario: Network failure or double-submit causes two checkout requests for the same cart.
Requirement:
* Use a database transaction wrapping: credit check -> credit deduction -> reservation confirmation -> booking creation
* Use SELECT ... FOR UPDATE (Doctrine PESSIMISTIC_WRITE lock) on the User's credit balance row during checkout
* Idempotency key on the cart/checkout to prevent double-processing
Challenge 5 - Flash Sale / Tier Sale Window
Scenario: A tier opens for sale at a specific datetime (e.g., "Early Bird tickets go on sale Friday at 10:00 AM").
Requirement:
* TicketTier has sale_starts_at and sale_ends_at
* Server-side validation (never trust client time) enforces the sale window
* Countdown timer on the frontend for upcoming sale windows
* Simultaneous requests at exact sale-open time must not bypass the window check
Business Logic & Constraints
Pricing Formula
Final Ticket Price = Base Price × 1.01
* Example: Base Price 200 credits -> Final Price 202 credits
* The 2 credit difference goes to system revenue; organizer receives the base price
Transaction Rules
* Users start with 2000 credits - no way to add more
* Credits deducted immediately upon confirmed checkout
* No refunds - except when an organizer/admin cancels an entire event (full refund to all ticket holders)
* Cannot purchase tickets to past or cancelled events
* Cannot checkout if total cart cost exceeds available credits
Booking & Fulfillment Rules
Ticket Type
	Fulfillment
	Any ticket
	Instant - e-ticket (PDF + QR code token) generated on confirmation
	Physical event
	Status: Confirmed -> Attended / No-show (updated post-event)
	Online event
	Access link delivered via booking confirmation
	Event & Ticket Constraints
* Organizers can only manage their own events
* Seat inventory must be validated - no overselling (see concurrency challenges)
* Soft-cancelled events remain visible on existing bookings
* Sold-out tiers are visible but marked as unavailable (not hidden)
* Events in the past are browsable but not purchasable










Technical Implementation Requirements
Security
* Proper authentication and role-based authorization (Symfony Security / Voters)
* Protection against XSS, CSRF, SQL injection
* Secure file storage for e-tickets (not publicly accessible - served through a controller with authorization check)
* Validated and sanitized file uploads
* Rate limiting on checkout endpoint to reduce abuse during flash sales
Database Design Considerations (Recommended)
Entity
	Key Fields
	User
	role, credit_balance, timezone, created_at
	Organizer
	approval_status, linked user
	Event
	name, category, date_time, venue, status, organizer
	TicketTier
	event, name, base_price, total_seats, sold_count, version (optimistic lock), sale_starts_at, sale_ends_at
	Seat 
	event, tier, seat_number - unique constraint on (event_id, seat_number)
	SeatReservation
	user, tier, quantity, reserved_at, expires_at, status
	Booking
	user, event, items, total_credits, status, idempotency_key
	BookingItem
	booking, tier, quantity, unit_price
	ETicket
	booking_item, qr_token (unique), file_path
	Transaction
	user, amount, type (debit/credit/refund), reference
	Category
	name
	Symfony Messenger (Recommended)
* Async job for e-ticket PDF generation after booking confirmed
* Async job for expiring stale seat reservations
* Async job for sending booking confirmation emails
________________


Deliverables
1. Source Code - complete working application
2. Setup Instructions - how to install, configure, and run (including worker process for Messenger)
3. Documentation - architectural decisions, concurrency strategy explanation, and how to reproduce a race condition scenario in testing
________________


Evaluation Criteria
Area
	What's Assessed
	Functionality
	All required features working correctly
	Concurrency Handling
	Correct implementation of locking strategies - no overselling under load
	Code Quality
	Clean, maintainable, best-practice Symfony code
	Database Design
	Proper schema, relationships, migrations, constraints
	Security
	Auth, authorization, file access control, input validation
	User Experience
	Interface usability, error handling during sold-out/conflict scenarios
	Extra
	Dynamic user timezone, flash sale countdown, Symfony Messenger async jobs
	________________


AI Usage Policy
AI tools (ChatGPT, Claude, GitHub Copilot, etc.) are allowed and encouraged as development aids, but:
* NOT allowed: Vibe coding or copying entire solutions without understanding
* Allowed: Using AI as a tool for research, debugging, and learning
* Expected: You understand every line of code you submit - especially the locking strategies
* Required: You can explain why you chose optimistic vs pessimistic locking in each scenario, and demonstrate that your solution actually prevents overselling
We value developers who use AI effectively while maintaining code ownership and deep understanding of their work.