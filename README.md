# Sentient Social API Platform


## 📱 About Sentient API

Sentient API is a full-featured meditation and wellness platform that combines mindfulness practices with social networking and health tracking. Built with Laravel as a robust API backend, it serves a Flutter mobile application with features including guided meditation sessions, AI-powered meditation guidance, social interactions, health data integration, and real-time notifications.

### Key Features

- 🧘 **Meditation Sessions** - Track meditation sessions with duration, type, and completion status
- 🤖 **AI Meditation Guide** - Personalized AI chat for meditation guidance and mindfulness support
- 👥 **Social Network** - Follow users, share posts, like, comment, and engage with the community
- 💬 **Direct Messaging** - Private conversations with real-time push notifications
- 📊 **Health Integration** - Sync Apple Health data via Terra API (steps, calories, heart rate, etc.)
- 🔔 **Push Notifications** - Firebase Cloud Messaging for instant updates
- 🖼️ **Media Management** - Upload and share images with posts and profiles
- 🔒 **Privacy Controls** - Granular privacy settings for user data
- 📈 **Statistics & Analytics** - Track meditation progress and health metrics

## 🛠️ Tech Stack

### Backend
- **Framework**: Laravel 12.x (PHP 8.2+)
- **Authentication**: Laravel Sanctum (Bearer token API auth)
- **Database**: MySQL/PostgreSQL/SQLite
- **Queue**: Laravel Queue with database driver
- **Storage**: Local filesystem with public symlink

### Integrations
- **Firebase**: Cloud Messaging (FCM) for push notifications (`kreait/firebase-php`)
- **Terra API**: Apple Health data synchronization
- **Image Processing**: Intervention Image library

### Frontend
- **Mobile App**: Flutter (iOS/Android)
- **Admin Panel**: Vite + Tailwind CSS (optional)

## 📋 Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and npm (for asset compilation)
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3
- Firebase project with Cloud Messaging enabled
- Terra API account (optional, for health data)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone <repository-url> <github_name>
cd <github_name>
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies (for Vite)
npm install
```

### 3. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Database Setup

Edit `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medapp
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations to create tables:

```bash
php artisan migrate
```

Optionally seed with test data:

```bash
php artisan db:seed
```

### 5. Firebase Configuration

1. Create a Firebase project at [Firebase Console](https://console.firebase.google.com/)
2. Download the service account JSON file
3. Save it as `storage/app/firebase/firebase-credentials.json`
4. Add to `.env`:

```env
FIREBASE_CREDENTIALS=firebase/firebase-credentials.json
FIREBASE_DATABASE_URL=https://your-project.firebaseio.com
```

### 6. API Key Setup

Set your API key in `.env`:

```env
API_KEY=medapp-secure-api-key-2025
```

This key is required for public authentication endpoints (register/login).

### 7. Storage Link

Create symbolic link for public file access:

```bash
php artisan storage:link
```

### 8. Queue Worker

Start the queue worker for processing notifications:

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor.

## 🔧 Configuration

### File Uploads

Configure file upload limits in `.env`:

```env
UPLOAD_MAX_FILESIZE=10M
POST_MAX_SIZE=10M
```

### Queue Driver

For production, use Redis or database:

```env
QUEUE_CONNECTION=database
```

### Terra Health API (Optional)

```env
TERRA_API_KEY=your-terra-api-key
TERRA_API_SECRET=your-terra-api-secret
```

## 📚 API Documentation

### Base URL

```
http://localhost:8000/api/v1
```

### Authentication

Most endpoints require Bearer token authentication:

```bash
Authorization: Bearer {token}
```

Public endpoints (register/login) require API key:

```bash
X-API-Key: medapp-secure-api-key-2025
```

### Main Endpoints

#### Authentication
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login user
- `POST /auth/logout` - Logout user
- `POST /auth/forgot-password` - Request password reset
- `POST /auth/reset-password` - Reset password

#### User & Profile
- `GET /users/me` - Get current user
- `GET /users/{id}` - Get user profile
- `GET /users/search` - Search users
- `PUT /profile` - Update profile
- `POST /upload/avatar` - Upload avatar image
- `POST /upload/background` - Upload background image

#### Social Features
- `POST /users/{id}/follow` - Follow user
- `POST /users/{id}/unfollow` - Unfollow user
- `GET /users/{id}/followers` - Get followers
- `GET /users/{id}/following` - Get following
- `GET /users/{id}/posts` - Get user posts

#### Posts
- `GET /posts` - List posts (with pagination)
- `POST /posts` - Create post
- `GET /posts/{id}` - Get single post
- `PUT /posts/{id}` - Update post
- `DELETE /posts/{id}` - Delete post
- `POST /posts/{id}/like` - Like post
- `POST /posts/{id}/unlike` - Unlike post
- `GET /posts/{id}/likes` - Get post likes

#### Comments
- `GET /posts/{id}/comments` - List comments
- `POST /posts/{id}/comments` - Create comment
- `PUT /comments/{id}` - Update comment
- `DELETE /comments/{id}` - Delete comment

#### Meditation
- `GET /meditation-sessions` - List sessions
- `POST /meditation-sessions` - Create session
- `POST /meditation-sessions/{id}/complete` - Complete session
- `GET /meditation-statistics` - Get statistics

#### Messages
- `GET /messages` - List conversations
- `POST /messages` - Send message
- `GET /messages/conversation/{userId}` - Get conversation
- `GET /messages/unread-count` - Get unread count
- `PUT /messages/{id}/read` - Mark as read

#### Notifications
- `GET /notifications` - List notifications
- `GET /notifications/counts` - Get unread counts
- `PATCH /notifications/{id}/read` - Mark as read
- `PATCH /notifications/read-all` - Mark all as read
- `POST /notifications/fcm-token` - Register FCM token

#### Health Data
- `POST /health/sync` - Sync health metrics (Terra)
- `GET /health/metrics` - Get health metrics

#### AI Chat
- `GET /ai-chat` - Get chat history
- `POST /ai-chat` - Send AI message

For detailed request/response examples, see [doc/FLUTTER_API_DOCS.md](doc/FLUTTER_API_DOCS.md).

## 📁 Project Structure

```
medapp/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/    # API Controllers
│   │   └── Middleware/            # Custom middleware (ApiKeyMiddleware)
│   ├── Models/                    # Eloquent models
│   ├── Services/                  # Business logic (FirebaseService)
│   ├── Jobs/                      # Queue jobs (SendNotification)
│   └── Traits/                    # Reusable traits (SendsNotifications)
├── config/                        # Configuration files
├── database/
│   ├── migrations/                # Database migrations
│   ├── seeders/                   # Database seeders
│   └── factories/                 # Model factories
├── doc/                           # Documentation
│   ├── FLUTTER_API_DOCS.md
│   ├── NOTIFICATION_SYSTEM_IMPLEMENTATION.md
│   ├── TERRA_HEALTH_API_DOCS.md
│   ├── DEPLOYMENT_README.md
│   └── CLOUDWAYS_DEPLOYMENT_GUIDE.md
├── routes/
│   └── api.php                    # API routes
├── storage/
│   ├── app/firebase/              # Firebase credentials
│   └── app/public/                # Public files (uploads)
└── tests/                         # PHPUnit tests
```

## 🧪 Testing

Run PHPUnit tests:

```bash
php artisan test
```

Test specific endpoints:

```bash
# Test user search
php test_user_search.php

# Various test scripts available in nocommit/ directory
```

## 🚢 Deployment

### Production Setup

1. Set environment to production in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
```

2. Optimize Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. Set proper file permissions:

```bash
chmod -R 755 storage bootstrap/cache
```

4. Configure web server (Apache/Nginx)
5. Set up SSL certificate
6. Configure queue worker with Supervisor
7. Set up scheduled tasks in cron

For detailed deployment guides:
- [Cloudways Deployment](doc/CLOUDWAYS_DEPLOYMENT_GUIDE.md)
- [General Deployment](doc/DEPLOYMENT_README.md)

## 📱 Flutter Mobile App

This backend serves a Flutter mobile application. Refer to Flutter-specific documentation:
- [Flutter API Integration](doc/FLUTTER_API_DOCS.md)
- [Flutter Development Guide](doc/FLUTTER_DEVELOPMENT_PROMPT.md)

## 🔐 Security

- All API endpoints (except auth) require Sanctum authentication
- API key protection for public auth endpoints
- Rate limiting on search endpoints (10 requests/minute)
- CORS middleware configured
- Input validation on all requests
- SQL injection protection via Eloquent ORM
- XSS protection enabled

## 📝 Development

### Start Development Server

```bash
php artisan serve
```

Server runs at `http://localhost:8000`

### Watch Assets

```bash
npm run dev
```

### Queue Worker

```bash
php artisan queue:work --tries=3
```

### Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request
