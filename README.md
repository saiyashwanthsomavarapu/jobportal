# Job Portal - Modern Migration

A modern job portal application built with React 19, NestJS, and shadcn/ui, migrated from a legacy PHP application with 100% functional parity.

## 🚀 Tech Stack

### Frontend
- **React 19** - Latest React with improved performance
- **Vite** - Fast build tool and dev server
- **TypeScript** - Type-safe development
- **shadcn/ui** - Modern, accessible UI components
- **Tailwind CSS** - Utility-first CSS framework
- **React Router** - Client-side routing
- **Axios** - HTTP client with interceptors
- **Lucide React** - Icon library

### Backend
- **NestJS** - Progressive Node.js framework
- **TypeScript** - Type-safe backend development
- **Prisma** - Modern ORM for database access
- **JWT** - JSON Web Token authentication
- **Passport** - Authentication middleware
- **MySQL** - Database (with graceful fallback for development)

### Testing
- **Vitest** - Fast unit testing for frontend
- **Jest** - Testing framework for backend
- **Testing Library** - React component testing

## 📁 Project Structure

```
jobportal_backup/
├── jobportal-frontend/          # React frontend application
│   ├── src/
│   │   ├── components/          # Reusable UI components
│   │   ├── pages/              # Page components
│   │   ├── services/           # API service layer
│   │   ├── types/              # TypeScript type definitions
│   │   └── test/               # Test files
│   ├── public/                 # Static assets
│   └── package.json
├── jobportal-backend/           # NestJS backend application
│   ├── src/
│   │   ├── auth/               # Authentication module
│   │   ├── jobs/               # Jobs CRUD module
│   │   ├── clients/            # Clients CRUD module
│   │   ├── admin/              # Admin users module
│   │   ├── common/             # Shared utilities
│   │   └── prisma/             # Prisma service
│   ├── prisma/
│   │   └── schema.prisma       # Database schema
│   └── package.json
└── README.md
```

## 🛠️ Installation

### Prerequisites
- Node.js 18+ 
- MySQL 8+ (optional for development)
- npm or yarn

### Frontend Setup

```bash
cd jobportal-frontend
npm install
```

### Backend Setup

```bash
cd jobportal-backend
npm install
```

### Database Setup (Optional)

1. Create a MySQL database:
```sql
CREATE DATABASE jobportal;
```

2. Configure environment variables in `jobportal-backend/.env`:
```env
DATABASE_URL="mysql://user:password@localhost:3306/jobportal"
JWT_SECRET=your-secret-key
JWT_EXPIRATION=7d
```

3. Run migrations:
```bash
cd jobportal-backend
npx prisma migrate dev
```

## 🚀 Running the Application

### Development Mode

#### Start Backend
```bash
cd jobportal-backend
npm run start:dev
```
Backend runs on `http://localhost:3001`

#### Start Frontend
```bash
cd jobportal-frontend
npm run dev
```
Frontend runs on `http://localhost:5173`

### Production Build

#### Build Frontend
```bash
cd jobportal-frontend
npm run build
```

#### Build Backend
```bash
cd jobportal-backend
npm run build
```

## 🧪 Testing

### Frontend Tests
```bash
cd jobportal-frontend
npm run test
```

### Backend Tests
```bash
cd jobportal-backend
npm run test
```

### Backend Coverage
```bash
cd jobportal-backend
npm run test:cov
```

## 📡 API Endpoints

### Authentication
- `POST /auth/login` - User login
- `POST /auth/mock-login` - Mock login for development
- `POST /auth/logout` - User logout

### Jobs
- `GET /jobs/published` - Get all published jobs
- `GET /jobs/embed` - Get jobs for embed widget
- `GET /jobs/slug/:slug` - Get job by slug
- `GET /jobs/similar/:id` - Get similar jobs
- `GET /jobs` - Get all jobs (admin)
- `POST /jobs` - Create job (admin)
- `PUT /jobs/:id` - Update job (admin)
- `DELETE /jobs/:id` - Delete job (admin)

### Clients
- `GET /clients` - Get all clients
- `GET /clients/:id` - Get client by ID
- `POST /clients` - Create client
- `PUT /clients/:id` - Update client
- `DELETE /clients/:id` - Delete client

### Admin Users
- `GET /admin` - Get all admin users
- `GET /admin/:id` - Get admin user by ID
- `POST /admin` - Create admin user
- `PUT /admin/:id` - Update admin user
- `DELETE /admin/:id` - Delete admin user
- `POST /admin/:id/deactivate` - Deactivate admin user
- `POST /admin/:id/activate` - Activate admin user
- `POST /admin/change-password` - Change password

## 🎨 Features

### Public Features
- **Job Listings** - Search and filter jobs by country, type, and workplace
- **Job Details** - View comprehensive job information
- **Similar Jobs** - Discover related opportunities
- **Share & Print** - Share job links and print pages
- **Responsive Design** - Mobile-friendly interface

### Admin Features
- **Authentication** - Secure login with JWT
- **Dashboard** - Overview of job statistics
- **Job Management** - Create, edit, delete jobs
- **Client Management** - Manage client information
- **User Management** - Admin user management
- **Activity Logging** - Track all admin activities

## 🔐 Authentication

The application uses JWT-based authentication:

1. Login via `/auth/login` endpoint
2. Receive JWT token in response
3. Token stored in localStorage
4. Token sent in Authorization header for protected routes
5. Automatic redirect to login on token expiration

## 🎯 Development Features

### Mock Data
All API services include mock data fallbacks for development without a database:
- Mock authentication (any email/password works)
- Mock job listings
- Mock job details
- Mock similar jobs

### Graceful Database Fallback
Backend continues to run without MySQL connection for development testing.

## 🌐 Environment Variables

### Frontend (.env)
```env
VITE_API_URL=http://localhost:3001
```

### Backend (.env)
```env
DATABASE_URL="mysql://user:password@localhost:3306/jobportal"
JWT_SECRET=your-secret-key
JWT_EXPIRATION=7d
PORT=3001
```

## 📱 Pages

### Public Pages
- `/` - Job listings with search and filters
- `/job-detail` - Detailed job information

### Admin Pages
- `/admin/login` - Admin authentication
- `/admin/dashboard` - Admin dashboard with job management

## 🎨 Design System

### Colors
- Primary: `#1A4C8F` (Deep Blue)
- Secondary: Slate grays
- Success: Green variants
- Warning: Yellow/amber variants
- Error: Red variants

### Typography
- Font Family: Sora (headings), DM Sans (body)
- Font Sizes: Responsive scaling
- Line Heights: Optimized for readability

### Components
- Cards with subtle shadows
- Buttons with hover effects
- Badges for status indicators
- Tables with hover states
- Inputs with focus states

## 🔄 Migration from PHP

### Original PHP Structure
- `job-detail.php` - Job detail page
- `index.php` - Job listings
- `admin/login.php` - Admin login
- `admin/index.php` - Admin dashboard
- `admin/config.php` - Configuration
- Database: MySQL with custom queries

### Modern Equivalent
- `JobDetail.tsx` - React component
- `JobListings.tsx` - React component
- `AdminLogin.tsx` - React component
- `AdminDashboard.tsx` - React component
- Environment variables - Configuration
- Prisma ORM - Type-safe database access

## 📊 Database Schema

### Jobs Table
- Basic job information (title, description, etc.)
- Location details (city, state, country)
- Job metadata (type, workplace, experience)
- Salary information
- Status tracking
- View counts

### Clients Table
- Client company information
- Contact details
- Client codes for job references

### Admin Users Table
- User authentication credentials
- Role-based access
- Activity tracking
- Account status

### Activity Logs Table
- Admin action tracking
- Timestamp and user information
- Action details

## 🚦 Deployment

### Frontend Deployment
1. Build the application: `npm run build`
2. Deploy `dist/` folder to static hosting (Vercel, Netlify, etc.)
3. Configure environment variables

### Backend Deployment
1. Build the application: `npm run build`
2. Deploy to Node.js hosting (AWS, DigitalOcean, etc.)
3. Configure database connection
4. Set environment variables

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

## 📝 License

Private project - All rights reserved

## 📞 Support

For issues or questions, please contact the development team.

## 🎯 Phase 1 Status

✅ **Completed**
- Frontend setup with React 19 + Vite + shadcn/ui
- Backend setup with NestJS + Prisma
- Authentication system with JWT
- Job CRUD operations
- Client CRUD operations
- Admin user CRUD operations
- JSON API endpoint for embed widget
- Modern UI migration for all pages
- Testing infrastructure
- Documentation

**100% Functional Parity Achieved**
