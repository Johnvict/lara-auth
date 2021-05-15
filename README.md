## LaraAuth
## Laravel/Lumen  - PHP Framework

LaraAuth is a basic **Authentication** app that implements:
- User Registration
- User Account Verificstion
- User and Admin Login
- User and Admin Token Refresh
- Admin Login
- Admin Creation of another admin
- Admin Activate/Deactivate User Account
- Admin fetch all users
- Admin fetch all admins
- Account Update
- Forgot Password and Password reset 
- Change Password
- Disable/Enable app
- Get App status (Health check)


This project is a part of some of my private repositories. It only showcase a few of my coding styles and how I implement some app features on my own, such as
- Validation Handling and Error Messages
- Response sent for every request, using HTTP status code 200 for all requests
- While code 200 is used, distiguished response code and status are sent based on response type 



## How to setup on your local machine
- Clone this repository
    ```git clone https://github.com/Johnvict/lara-auth.git```

- Run following commands to complete setup
    ```cd lara-auth```
    ```composer install```

- Create a database of your choice (Though I used lara_auth, you can use whatever you want)

- Run following command to seed Super Admin credential
    ```php artisan db:seed```
    **The default credential is**
        - Email:    super.admin@johnvict.com
        - Phone:    07084677075
        - Password: sup3r@dm!n
    **Check out /database/seeders/AdminSeeder.php if yout wish to change the credential**

- Visit https://documenter.getpostman.com/view/9029061/TzRX7Qbz to get Postman Documentation


