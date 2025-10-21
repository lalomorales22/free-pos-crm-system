# Your App Your Data – Free POS + CRM Sandbox

Your App Your Data is a playful, modern take on a point-of-sale and customer relationship management stack. The project ships with an expressive front office storefront, a full-featured admin workspace, and an AI assistant so you can test-drive flows without handing over your data. Spin it up locally, drop it on a server, or remix it for your next retail concept.

## ✨ Highlights
- **Unified retail workflows** – Manage products, inventory, carts, orders, and customers from one interface.
- **Offline-friendly POS** – Session-based cart handling with quick updates and flexible payment methods.
- **CRM essentials** – Customer profiles, order history, saved addresses, and account management.
- **Admin command center** – Secure dashboard for products, categories, tags, users, orders, and promotions.
- **AI copilot** – Context-aware chat assistant ready to answer POS or CRM questions.
- **Widget-inspired design** – White surfaces, bold black borders, and elevated shadows inspired by shadcn projects.

## 🧱 Architecture
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, vanilla JavaScript
- **UI**: Bootstrap 5.2, Bootstrap Icons, Plus Jakarta Sans
- **Data model**: Relational schema with products, categories, orders, carts, users, chat logs, and contact messages

## 🚀 Getting Started

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/your-app-your-data.git
   cd your-app-your-data
   ```

2. **Install dependencies**
   - Ensure PHP 7.4+, MySQL 5.7+, and a web server (Apache or Nginx) are available.
   - Enable the MySQLi extension in your PHP environment.

3. **Create the database**
   ```sql
   mysql -u your_username -p < schema.sql
   ```

4. **Configure the connection**
   Update `applications/config.php` (legacy filename) with your credentials:
   ```php
   $servername = 'localhost';
   $username   = 'db_user';
   $password   = 'db_password';
   $dbname     = 'your_app_your_data';
   ```

5. **Serve the application**
   - Point your virtual host or local server to the project root.
   - Ensure the web server can write to any upload/cache directories if you enable file uploads later.

6. **Sign in to the admin**
   - Importing `schema.sql` seeds an admin account:
     - **Username:** `admin`
     - **Email:** `admin@yourappyourdata.com`
     - **Password:** `admin123`
   - Change the password immediately after logging in.

## 🛠️ Developer Notes
- The storefront (index.php, shop.php, product.php) showcases the retail experience.
- `backend.php` houses the admin routing and renders dashboards, forms, and analytics widgets.
- `chat.php` + `chat_api.php` power the AI assistant; swap in your own provider or prompt.
- CSS lives in `style.css` and leans on CSS variables for quick theme adjustments.
- `schema.sql` includes tables for orders, order items, product tags, chat analytics, and contact messages.

## 🧪 Quick Checks
- Verify PHP syntax: `php -l *.php`
- Run a local server: `php -S localhost:8000`
- Inspect MySQL connectivity with `mysql -u user -p -e 'SHOW TABLES;' your_app_your_data`

## 📬 Support & Feedback
- Email: [hello@yourappyourdata.com](mailto:hello@yourappyourdata.com)
- Issues & ideas: open a ticket or drop a note through the in-app contact form.

## 🤝 Contributing
Pull requests and design critiques are welcome. If you plan a large change—new modules, payment drivers, or deployment recipes—start a discussion so we can align on architecture.

## 📄 License
This project is released under the MIT License. See `LICENSE` for details.
