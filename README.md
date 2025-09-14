# 710 Den Glass - Premium Glass E-commerce Platform

A modern, professional e-commerce website for premium glass pieces and accessories, featuring age verification, advanced filtering, and AI chat assistance.

## 🌟 Features

### Core Functionality
- **Age Verification System** - Compliant 21+ verification for all users
- **Product Catalog** - Comprehensive product management with categories and tags
- **Advanced Filtering** - Search by category, price range, tags, and keywords
- **Shopping Cart** - Full cart management with session persistence
- **User Authentication** - Secure login/registration system
- **Admin Dashboard** - Complete backend management system
- **AI Chat Assistant** - Integrated chat support for customers

### Design & UX
- **Modern Design System** - Clean white/black theme inspired by shadcn/ui
- **Mobile Responsive** - Optimized for all device sizes
- **One-Page Shopping** - Streamlined product discovery experience
- **Floating AI Chat** - Always accessible customer support
- **Professional Navigation** - Minimal design with prominent branding

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.2.3
- **Icons**: Bootstrap Icons
- **Typography**: Poppins (Google Fonts)

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🚀 Installation & Setup

### 1. Clone the Repository
```bash
git clone https://github.com/lalomorales22/710denglass.git
cd 710denglass
```

### 2. Database Setup
```sql
-- Import the database schema
mysql -u your_username -p your_database_name < schema.sql
```

### 3. Configuration
Edit `applications/denglass-config.php` with your database credentials:
```php
$servername = "localhost";
$username = "your_db_username";
$password = "your_db_password";
$dbname = "your_database_name";
```

### 4. Web Server Setup
- Place files in your web server document root
- Ensure PHP has write permissions to the `uploads/` directory
- Configure your web server to serve `index.php` as the default document

### 5. Admin Account
Create an admin user through the registration system, then manually update the database:
```sql
UPDATE users SET is_admin = 1 WHERE username = 'your_admin_username';
```

## 📁 Project Structure

```
710denglass/
├── index.php              # Main landing page with product grid
├── shop.php               # Product filtering and search
├── product.php            # Individual product pages
├── login.php              # User authentication
├── chat.php               # AI chat assistant
├── about.php              # About us page
├── contact.php            # Contact form and info
├── terms.php              # Terms & conditions
├── privacy.php            # Privacy policy
├── backend.php            # Admin dashboard
├── applications/
│   └── denglass-config.php # Database configuration
├── images/                # Product and site images
├── uploads/               # User-uploaded content
├── style.css              # Main stylesheet
├── script.js              # JavaScript functionality
└── schema.sql             # Database structure
```

## 🎨 Design System

The site uses a modern design system with:
- **Color Palette**: Clean whites, professional blacks, subtle grays
- **Typography**: Poppins font family with proper hierarchy
- **Components**: Card-based layouts with consistent shadows and borders
- **Responsive Design**: Mobile-first approach with breakpoints
- **Accessibility**: Proper contrast ratios and focus states

## 🔧 Key Features

### Age Verification
- Required for all users before accessing content
- Session-based verification with database logging
- Compliant with legal requirements

### Product Management
- Categories and subcategories
- Product tags for advanced filtering
- Multiple product images with primary image selection
- Sale pricing and featured product designation

### Shopping Experience
- Real-time cart updates
- Session-based cart persistence
- Advanced product filtering
- Responsive product grid

### Admin Features
- Product CRUD operations
- Category management
- User management
- Order tracking
- Analytics dashboard

## 🚀 Usage

### For Customers
1. **Age Verification** - Complete age verification on first visit
2. **Browse Products** - Use left sidebar to filter by category, price, or tags
3. **Product Details** - Click any product for detailed view and purchase
4. **Shopping Cart** - Add items and checkout securely
5. **AI Assistance** - Click the blue chat icon for help

### For Administrators
1. **Access Admin Panel** - Login and navigate to backend.php
2. **Manage Products** - Add, edit, or remove products
3. **Manage Categories** - Organize product categories
4. **View Orders** - Track customer orders and fulfillment
5. **User Management** - Manage customer accounts

## 🔒 Security Features

- **SQL Injection Protection** - Prepared statements and input sanitization
- **XSS Prevention** - Output escaping and validation
- **Session Security** - Secure session management
- **Age Verification Logging** - Compliance tracking
- **Admin Access Control** - Role-based permissions

## 📱 Mobile Optimization

- **Responsive Grid** - Adapts to all screen sizes
- **Touch-Friendly** - Optimized for mobile interaction
- **Fast Loading** - Optimized images and efficient CSS
- **Mobile Navigation** - Collapsible menu for small screens

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is proprietary software. All rights reserved.

## 📞 Support

For technical support or questions:
- **Email**: andrew@710denglass.com
- **GitHub Issues**: [Create an issue](https://github.com/lalomorales22/710denglass/issues)
- **AI Chat**: Available on the website

## 🏗️ Development Notes

### Recent Updates
- **Design System Overhaul** - Migrated from dark theme to modern white/black design
- **Navigation Simplification** - Streamlined to logo-centric design
- **CSS Centralization** - Moved all styling to centralized stylesheet
- **Footer Standardization** - Unified footer across all pages
- **Mobile Optimization** - Enhanced responsive design

### Performance Optimizations
- **Centralized CSS** - Single stylesheet for better caching
- **Optimized Images** - Proper image sizing and formats
- **Efficient Queries** - Optimized database queries
- **Session Management** - Efficient cart and user session handling

---

**Built with ❤️ for the glass community**

*710 Den Glass - Premium glass pieces for discerning collectors*
