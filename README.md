# Agent Forge

## How to Run

This project uses PHP's built-in web server.

### Prerequisites
- PHP 8.0 or higher

### Setup
Before running the application for the first time, initialize the database:

1. Run the setup script:
   ```bash
   php setup_db.php
   ```
   This will create the database and a default admin user:
   - **Username:** `luizcf14`
   - **Password:** `qazx74123`

### Running the Application

1. Open a terminal in the project root.
2. Run the following command:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Open your browser and navigate to `http://localhost:8000`.

### Windows Helper
You can also simply double-click the `start.bat` file to run the server.
