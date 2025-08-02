# Medi Guru Portal

A comprehensive medical training and meeting management system built with PHP and MySQL.

## Features

- **User Authentication**: Secure login system with role-based access
- **Meeting Management**: Create, view, and manage medical training meetings
- **Role-Based Access**: Different access levels for different user roles
- **CORS Enabled**: Frontend-backend communication support

## Database Structure

### Users Table
```sql
CREATE TABLE `users` (
  `id` int(255) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Meetings Table
```sql
CREATE TABLE `meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `topic` text NOT NULL,
  `hosters` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Setup Instructions

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser
- Frontend application (React/Vue/Angular) running on localhost:5174

### Installation

1. **Clone/Download the project**
   ```bash
   # Place files in XAMPP htdocs directory
   C:\xampp\htdocs\mediguru\
   ```

2. **Database Setup**
   - Start XAMPP (Apache + MySQL)
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database: `mediguru`
   - Import the SQL tables (users and meetings)

3. **Database Configuration**
   - Update database credentials in `medi_database.php`:
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "your_password_here";
   $dbname = "mediguru";
   ```

4. **Start the Server**
   ```bash
   # Start XAMPP Apache server
   # Access API at: http://localhost/mediguru/
   ```

## API Endpoints

### Authentication
- **POST** `/login.php` - User login
  ```json
  {
    "email": "user@example.com",
    "password": "password"
  }
  ```

### Meetings
- **GET** `/medi_meetings.php` - Get all meetings (requires authentication)

## File Structure

```
mediguru/
├── README.md
├── database.php           # Original database connection
├── medi_database.php      # Enhanced database connection with CORS
├── medi_helpers.php       # Helper functions (to be created)
├── login.php              # User authentication endpoint
├── medi_meetings.php      # Meetings management endpoint
└── [other API files]
```

## Frontend Integration

### CORS Configuration
The API is configured to work with frontend applications running on:
- `http://localhost:5174` (Vite dev server)
- Can be modified in the CORS headers

### Sample Frontend Request
```javascript
// Login request
const loginUser = async (email, password) => {
  try {
    const response = await fetch('http://localhost/mediguru/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ email, password })
    });
    
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Login error:', error);
  }
};

// Get meetings (with authentication)
const getMeetings = async (token) => {
  try {
    const response = await fetch('http://localhost/mediguru/medi_meetings.php', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      credentials: 'include'
    });
    
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Meetings error:', error);
  }
};
```

## User Roles

- **Admin**: Full system access
- **Doctor**: Medical professional access
- **Student**: Limited access for learning

## Security Features

- **JWT Token Authentication**: Secure token-based authentication
- **Password Validation**: Email format validation
- **SQL Injection Prevention**: Prepared statements
- **CORS Protection**: Configured for specific frontend domains

## Development

### Adding New Endpoints
1. Create new PHP file in the project root
2. Include `medi_database.php` for database connection
3. Include `medi_helpers.php` for utility functions
4. Follow the existing CORS and error handling patterns

### Error Handling
All endpoints return JSON responses with appropriate HTTP status codes:
- `200`: Success
- `400`: Bad Request
- `401`: Unauthorized
- `405`: Method Not Allowed
- `500`: Internal Server Error

## Troubleshooting

### Common Issues

1. **CORS Errors**
   - Check frontend URL in CORS headers
   - Ensure credentials are included in requests

2. **Database Connection Issues**
   - Verify XAMPP MySQL is running
   - Check database credentials
   - Ensure database and tables exist

3. **File Not Found Errors**
   - Ensure all required files exist
   - Check file paths and includes

## License

This project is for educational and medical training purposes.

## Support

For issues and questions, please check the error logs and ensure all prerequisites are met.
