localhost/motoshapi -- to access our website's homepage
localhost/motoshapi/admin -- to access our website's admin page
-- make sure that the xampp is open and apache and mysql in running

username at password ng admin:
marc
marc123

-magcreate po muna kayo ng database sa phpmyadmin na ang name ay "motoshapi_db"
-then import nyo po yung file na "motoshapi_db.sql" sa folder na ito 

-yung migrate database po nagana lang pag may existing database na po kayo ng motoshapi_db incase na magupdate kayo sa database, mababago nadin po sa file na nasa folder.

-yung item po na may variation is yung "RacingBoy 6-Spoke Alloy Mags"


## Features
- User authentication system
- Product catalog with categories
- Shopping cart functionality
- Admin panel for inventory management
- Payment processing (COD only)
- Order management system
- **Email Integration System**
- Responsive design
- Product variations support
- Featured products
- About us management
- Password reset functionality

## Email Integration

The system now includes comprehensive email functionality:

### Features Added:
- **Welcome Emails**: Automatically sent to new user registrations
- **Order Confirmations**: Detailed order confirmation emails with itemized receipts
- **Order Status Updates**: Notifications when order status changes (pending → processing → shipped → delivered)
- **Password Reset**: Secure password reset functionality via email

### Setup:
1. Configure email settings in `email/config/email.php`
2. Run database migration: `localhost/motoshapi/database/migrate_database.php`
3. Test email configuration: `localhost/motoshapi/email/pages/test_email.php`
4. See `email/README.md` for detailed setup instructions

### Email Templates:
- Modern, responsive HTML email templates
- Plain text alternatives for compatibility
- Customizable styling and content
- Professional branding with Motoshapi colors

If may further questions po kayo about the system you can contact us the creators of motoshapi

Marc Angelo Canillas 
Ralph Mathew Cawilan
John Carlo Marasigan 
Paul Anthony Pancho
Mark Vincent Plaza

we are from BSIT 3-2 po. 

