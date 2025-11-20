# Library Management System

A comprehensive web-based library management system built with PHP and MySQL, featuring book reservations, verified reviews, and automated notifications.

## Features

### 👨‍💼 Admin Module
- Complete dashboard with analytics
- Add/Edit/Delete categories, authors, and books
- Issue books to students with automatic tracking
- Manage issued books and overdue fines
- View registered students and their details
- Search functionality for books and students

### 👨‍🎓 Student Module
- User registration with auto-generated Student ID
- Personal dashboard with issued books overview
- **Book Reservation System** with queue management
- **Verified Review System** (only for borrowed books)
- **Real-time Notifications** with due date reminders
- Profile management and password recovery
- Fine calculation and payment tracking

## Technologies Used

- **Backend**: PHP 8.2
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 4
- **JavaScript**: jQuery, DataTables
- **Server**: Apache (XAMPP)

## Prerequisites

Before running this project, ensure you have:
- XAMPP (or any PHP server with MySQL)
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Edge)

## Installation & Setup

### Step 1: Clone or Download the Project
```bash
git clone https://github.com/TanmaySharma31/Library-Management-System.git
```
Or download and extract the ZIP file.

### Step 2: Setup XAMPP
1. Download and install [XAMPP](https://www.apachefriends.org/)
2. Start **Apache** and **MySQL** from XAMPP Control Panel

### Step 3: Database Configuration
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Create a new database named `library`
3. Import the SQL file:
   - Click on the `library` database
   - Go to **Import** tab
   - Choose file: `sql file/library.sql`
   - Click **Go**

### Step 4: Configure Database Connection
Open `library/includes/config.php` and verify the database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Qwerty9611');  // Update if your MySQL password is different
define('DB_NAME', 'library');
```

### Step 5: Copy Project to Server Directory
- Copy the entire project folder to `C:\xampp\htdocs\`
- Or rename the `library` folder and place it there

## Running the Application

### Method 1: Using XAMPP (Recommended)
1. Ensure Apache and MySQL are running in XAMPP
2. Open browser and navigate to:
   - **Student Portal**: `http://localhost/library`
   - **Admin Panel**: `http://localhost/library/admin`

### Method 2: Using PHP Built-in Server
1. Open terminal/command prompt
2. Navigate to the library folder:
   ```bash
   cd path/to/LibManagementSys(DBMS)/library
   ```
3. Start the server:
   ```bash
   php -S localhost:8000
   ```
4. Access at `http://localhost:8000`

## Login Credentials

### Student Account
- **Email**: test@gmail.com
- **Password**: Test@123

### Admin Account
- **Username**: admin
- **Password**: admin@123

## Key Functionalities

### 📚 Book Reservation System
- Students can reserve currently issued books
- Queue-based system with position tracking
- Automatic notifications when books become available

### ⭐ Verified Review System
- Only students who have borrowed a book can review it
- Prevents fake reviews
- Rating system with detailed review text

### 🔔 Notification System
- Real-time notifications for due dates
- Visual notification bell with unread count
- Automated reminders for book returns

### 💰 Fine Management
- Automatic fine calculation for overdue books
- Fine payment tracking
- Admin dashboard for fine overview

## Project Structure
```
LibManagementSys(DBMS)/
├── library/
│   ├── admin/              # Admin panel files
│   ├── assets/             # CSS, JS, images
│   ├── includes/           # Config and common files
│   ├── index.php           # Student login
│   ├── signup.php          # Student registration
│   ├── dashboard.php       # Student dashboard
│   └── ...                 # Other student modules
├── sql file/
│   └── library.sql         # Database schema
└── README.md
```

## Troubleshooting

### Database Connection Error
- Verify MySQL is running in XAMPP
- Check database credentials in `includes/config.php`
- Ensure `library` database exists and SQL is imported

### Page Not Found (404)
- Verify the correct folder path in `htdocs`
- Check if Apache is running
- Clear browser cache

### Notification Bell Not Visible
- Clear browser cache and refresh
- Ensure CSS files are loading properly

## Contributing

Feel free to fork this project and submit pull requests for improvements.

## License

This project is open-source and available under the MIT License.

## Contact

For any queries or issues, please open an issue on GitHub.





