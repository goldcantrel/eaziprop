# Property Management System

A comprehensive property management system built with Laravel, featuring property listings, rental management, payment processing, maintenance requests, and real-time chat functionality.

## Features

- User Management (Landlords, Tenants, Superusers)
- Property Listings and Management
- Rental Contract Management
- Online Rent Payment Processing (Stripe Integration)
- Maintenance Request System
- Document Management
- Real-time Chat System
- Email Notifications
- OAuth Authentication (Google, GitHub)

## Requirements

- PHP 8.1+
- MySQL 5.7+
- Composer
- Redis (for queues and real-time features)
- Node.js & NPM (for frontend assets)
- AWS S3 (for document storage)
- Stripe Account (for payment processing)
- Pusher Account (for real-time features)

## Installation

1. Clone the repository:


2. Install PHP dependencies:


3. Copy the environment file:


4. Generate application key:


5. Configure your `.env` file with your database, AWS S3, Stripe, and Pusher credentials.

6. Run migrations:


7. Create storage symlink:


8. Start the queue worker:


## API Endpoints

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/auth/{provider}/redirect` - OAuth redirect
- `GET /api/auth/{provider}/callback` - OAuth callback

### Users
- `GET /api/user/profile` - Get authenticated user profile
- `PUT /api/user/profile` - Update user profile
- `GET /api/users` - List all users (superuser only)
- `POST /api/users` - Create user (superuser only)
- `PUT /api/users/{id}` - Update user (superuser only)
- `DELETE /api/users/{id}` - Delete user (superuser only)

### Properties
- `GET /api/properties` - List properties
- `POST /api/properties` - Create property
- `GET /api/properties/{id}` - Get property details
- `PUT /api/properties/{id}` - Update property
- `DELETE /api/properties/{id}` - Delete property

### Rentals
- `GET /api/rentals` - List rentals
- `POST /api/rentals` - Create rental
- `GET /api/rentals/{id}` - Get rental details
- `PUT /api/rentals/{id}` - Update rental
- `DELETE /api/rentals/{id}` - Delete rental

### Payments
- `GET /api/payments` - List payments
- `POST /api/payments` - Create payment
- `GET /api/payments/{id}` - Get payment details
- `PUT /api/payments/{id}` - Update payment
- `POST /api/webhook/stripe` - Stripe webhook endpoint

### Maintenance Requests
- `GET /api/maintenance` - List maintenance requests
- `POST /api/maintenance` - Create maintenance request
- `GET /api/maintenance/{id}` - Get request details
- `PUT /api/maintenance/{id}` - Update request
- `DELETE /api/maintenance/{id}` - Delete request

### Documents
- `GET /api/documents` - List documents
- `POST /api/documents` - Upload document
- `GET /api/documents/{id}` - Get document details
- `PUT /api/documents/{id}` - Update document metadata
- `DELETE /api/documents/{id}` - Delete document
- `GET /api/documents/{id}/download` - Download document

### Chat
- `GET /api/chat/{property_id}/messages` - Get chat messages
- `POST /api/chat/{property_id}/messages` - Send message
- `POST /api/chat/{property_id}/mark-read` - Mark messages as read
- `DELETE /api/chat/messages/{id}` - Delete message

## Testing

Run the test suite:


## Scheduled Tasks

The application includes several scheduled tasks:
- Daily rent payment reminders
- Overdue payment checks
- Temporary file cleanup
- Database backups
- Maintenance request follow-ups

Configure your server's crontab to run Laravel's scheduler:


## Security

- All API endpoints are protected with Laravel Sanctum
- CSRF protection enabled for web routes
- File uploads restricted to authorized users
- Rate limiting applied to API endpoints
- S3 temporary URLs for secure file downloads

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License.