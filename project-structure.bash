# 🧱 PHP (Non-Symfony) Feature-Based Architecture

project-root/
│
├── app/
│   ├── Shared/
│   │   ├── Entities/
│   │   │   ├── //User.php
│   │   │   ├── //Organizer.php
│   │   │   ├── //Event.php
│   │   │   ├── //Category.php
│   │   │   ├── //TicketTier.php
│   │   │   ├── //SeatReservation.php
│   │   │   ├── //Booking.php
│   │   │   ├── //BookingItem.php
│   │   │   ├── //ETicket.php
│   │   │   └── //Transaction.php
│   │   │
│   │   ├── Enums/
│   │   │   ├── //UserRole.php
│   │   │   ├── //EventStatus.php
│   │   │   ├── //BookingStatus.php
│   │   │   └── TransactionType.php
│   │   │
│   │   ├── Services/
│   │   │   └── PricingService.php
│   │   │
│   │   └── Database/
│   │       └── Database.php
│
│   ├── Admin/
│   │   ├── Dashboard/
│   │   │   ├── AdminDashboardController.php
│   │   │   └── AdminDashboardService.php
│   │   │
│   │   ├── OrganizerManagement/
│   │   │   ├── OrganizerController.php
│   │   │   ├── OrganizerService.php
│   │   │   └── OrganizerRepository.php
│   │   │
│   │   ├── EventManagement/
│   │   │   ├── AdminEventController.php
│   │   │   ├── AdminEventService.php
│   │   │   └── EventRepository.php
│   │   │
│   │   ├── UserManagement/
│   │   │   ├── AdminUserController.php
│   │   │   ├── AdminUserService.php
│   │   │   └── UserRepository.php
│   │   │
│   │   └── Revenue/
│   │       ├── TransactionController.php
│   │       ├── TransactionService.php
│   │       └── TransactionRepository.php
│
│   ├── User/
│   │   ├── Auth/
│   │   │   ├── AuthController.php
│   │   │   ├── AuthService.php
│   │   │   └── AuthRepository.php
│   │   │
│   │   ├── EventBrowsing/
│   │   │   ├── EventController.php
│   │   │   ├── EventBrowsingService.php
│   │   │   └── EventRepository.php
│   │   │
│   │   ├── Cart/
│   │   │   ├── CartController.php
│   │   │   ├── CartService.php
│   │   │   └── SeatReservationRepository.php
│   │   │
│   │   ├── Checkout/
│   │   │   ├── CheckoutController.php
│   │   │   ├── CheckoutService.php
│   │   │   ├── PaymentService.php
│   │   │   ├── BookingRepository.php
│   │   │   └── TransactionRepository.php
│   │   │
│   │   ├── BookingHistory/
│   │   │   ├── BookingController.php
│   │   │   ├── BookingService.php
│   │   │   └── BookingRepository.php
│   │   │
│   │   └── Dashboard/
│   │       ├── UserDashboardController.php
│   │       └── UserDashboardService.php
│
│   ├── Organizer/
│   │   ├── Auth/
│   │   │   ├── OrganizerAuthController.php
│   │   │   ├── OrganizerAuthService.php
│   │   │   └── OrganizerRepository.php
│   │   │
│   │   ├── EventManagement/
│   │   │   ├── OrganizerEventController.php
│   │   │   ├── OrganizerEventService.php
│   │   │   └── EventRepository.php
│   │   │
│   │   ├── TicketTier/
│   │   │   ├── TicketTierController.php
│   │   │   ├── TicketTierService.php
│   │   │   └── TicketTierRepository.php
│   │   │
│   │   ├── Bookings/
│   │   │   ├── OrganizerBookingController.php
│   │   │   ├── OrganizerBookingService.php
│   │   │   └── BookingRepository.php
│   │   │
│   │   └── Dashboard/
│   │       ├── OrganizerDashboardController.php
│   │       └── OrganizerDashboardService.php
│
│   ├── Infrastructure/
│   │   ├── Database/
│   │   │   └── Connection.php
│   │   │
│   │   ├── Messaging/
│   │   │   ├── GenerateETicketJob.php
│   │   │   └── ExpireReservationsJob.php
│   │   │
│   │   └── Security/
│   │       ├── AuthMiddleware.php
│   │       └── RoleMiddleware.php
│
│   └── Helpers/
│       ├── Response.php
│       ├── Validator.php
│       └── Utils.php
│
├── public/
│   ├── index.php
│   └── .htaccess
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── storage/
│   ├── logs/
│   └── uploads/
│
├── config/
│   ├── database.php
│   └── app.php
│
├── vendor/
├── composer.json
└── README.md