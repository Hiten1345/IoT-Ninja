# AWS EC2 Deployment Guide: Ninja IoT Platform

This guide provides step-by-step instructions for deploying your **Ninja IoT Platform** to a free-tier **AWS EC2 instance (Ubuntu LTS)**.

Since your codebase includes both a **PHP Web App (Apache)** and a **Node.js WebSocket Server (running on port 8080)**, you have two main deployment options:

*   **Option A: Docker Deployment (Highly Recommended)** — Uses your existing `Dockerfile` and handles environment differences automatically. It is much easier to configure and maintain.
*   **Option B: Manual Server Setup** — Installs Apache, PHP, Node.js, and SQLite directly on the Ubuntu OS.

---

## Table of Contents
1. [Step 1: Launching your AWS EC2 Instance](#step-1-launching-your-aws-ec2-instance)
2. [Step 2: Transferring Code to EC2](#step-2-transferring-code-to-ec2)
3. [Option A: Docker-based Deployment (Recommended)](#option-a-docker-based-deployment-recommended)
4. [Option B: Manual Ubuntu Server Setup](#option-b-manual-ubuntu-server-setup)
5. [Step 3: Setting up Domain & SSL (HTTPS & WSS)](#step-3-setting-up-domain--ssl-https--wss)
6. [Step 4: ESP8266 Client Library Configuration](#step-4-esp8266-client-library-configuration)

---

## Step 1: Launching your AWS EC2 Instance

1. Log in to your **AWS Management Console**.
2. Search for **EC2** and click **Launch Instance**.
3. **Configure the Instance:**
   * **Name**: `Ninja-IoT-Server`
   * **OS (AMI)**: Choose **Ubuntu** (select **Ubuntu Server 24.04 LTS** or **22.04 LTS** - Free tier eligible).
   * **Instance Type**: Select `t2.micro` (or `t3.micro` depending on region - Free tier eligible).
   * **Key Pair (login)**: Create a new key pair (e.g., `ninja-iot-key.pem`), download it, and keep it safe! You will need it to SSH into the server.
4. **Configure Security Group (Firewall Rules):**
   * Check **Allow SSH traffic from** (preferably "My IP", or "Anywhere" `0.0.0.0/0` if necessary).
   * Check **Allow HTTP traffic from the internet** (Port 80).
   * Check **Allow HTTPS traffic from the internet** (Port 443).
   * > [!NOTE]
     > You **do not** need to open port 8080 in the Security Group. Because Apache runs on port 80/443 and proxies WebSocket requests locally to port 8080 (via `.htaccess`), keeping 8080 closed from the outside world is safer and cleaner.
5. Click **Launch Instance**.

---

## Step 2: Transferring Code to EC2

Get the public IP address of your EC2 instance from the EC2 Dashboard (e.g., `54.210.12.34`).

### Method 1: Using Git (Recommended)
1. Push your code from your local machine to a GitHub private (or public) repository.
2. SSH into your EC2 instance (replace `ninja-iot-key.pem` and `54.210.12.34` with your file and IP):
   ```bash
   ssh -i /path/to/ninja-iot-key.pem ubuntu@54.210.12.34
   ```
3. Update packages and clone your repository:
   ```bash
   sudo apt update && sudo apt upgrade -y
   git clone https://github.com/yourusername/your-repo-name.git iot-node
   cd iot-node
   ```

### Method 2: Using WinSCP / FileZilla (SFTP)
1. Open WinSCP or FileZilla.
2. Connect using:
   * **Protocol**: SFTP
   * **Host Name**: Your EC2 Public IP
   * **User**: `ubuntu`
   * **Key file**: Load your `.pem` file (WinSCP will automatically convert it to `.ppk`).
3. Drag and drop your project files (excluding `node_modules` and local sqlite database files) to `/home/ubuntu/iot-node/`.

---

## Option A: Docker-based Deployment (Recommended)

Since the project already contains a `Dockerfile` that packages PHP, Apache, and Node.js together, using Docker simplifies deployment dramatically.

### 1. Install Docker & Docker Compose on EC2
On your EC2 terminal, run:
```bash
# Install Docker
sudo apt install -y docker.io
sudo systemctl start docker
sudo systemctl enable docker

# Allow the ubuntu user to run Docker commands without sudo
sudo usermod -aG docker ubuntu
```
*Log out of your SSH session (`exit`) and log back in to apply group membership.*

### 2. Create Docker Compose Configuration
To manage container startup, ports, and database persistence easily, create a `docker-compose.yml` file in your project directory:
```bash
nano docker-compose.yml
```
Paste the following content:
```yaml
version: '3.8'

services:
  app:
    build: .
    container_name: ninja-iot-app
    restart: always
    ports:
      - "80:80"
    volumes:
      # CRITICAL: Persists your SQLite database outside the container
      - ./data:/var/www/html/data
```
Press `Ctrl+O` then `Enter` to save, and `Ctrl+X` to exit nano.

### 3. Start the Application
Run the following command to build and launch the container in the background:
```bash
docker compose up -d --build
```
Your server will now be live on port 80 of your EC2 Public IP!

---

## Option B: Manual Ubuntu Server Setup

If you prefer to run the applications directly on the host OS instead of inside Docker, follow these steps.

### 1. Install PHP, Apache, and Node.js
```bash
# Update repository
sudo apt update

# Install Apache, PHP, PHP Extensions and SQLite
sudo apt install -y apache2 php php-curl php-sqlite3 php-xml php-mbstring libapache2-mod-php unzip curl

# Install Node.js v18 and NPM
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Configure Apache Modules & Document Root
Enable rewrite and proxy modules required for WebSocket forwarding:
```bash
sudo a2enmod rewrite proxy proxy_http proxy_wstunnel headers
sudo systemctl restart apache2
```

Now, move your project files to the Apache web directory:
```bash
sudo cp -r /home/ubuntu/iot-node/* /var/www/html/
# Or if you cloned directly into /var/www/html:
# sudo git clone https://github.com/yourusername/your-repo-name.git /var/www/html/
```

Fix permissions to allow SQLite write access:
```bash
sudo mkdir -p /var/www/html/data
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 775 /var/www/html/data
```

### 3. Run Node.js WebSocket Server under PM2
PM2 is a production process manager that keeps your Node.js application running 24/7 and restarts it if it crashes.
```bash
cd /var/www/html
sudo npm install --production

# Install PM2 globally
sudo npm install -y -g pm2

# Start the WebSocket server
pm2 start server-ws.js --name "ninja-websocket"

# Set up PM2 to auto-start on boot
pm2 startup
# (Run the exact command outputted by the screen command above, e.g. sudo env PATH...)
pm2 save
```

---

## Step 3: Setting up Domain & SSL (HTTPS & WSS)

> [!IMPORTANT]
> Modern browsers, web applications, and many secure microcontrollers (like ESP8266 with secure clients) require HTTPS/WSS. Running on a raw IP address (like `http://54.210.12.34`) will cause mixed-content errors on your client dashboard and connection blocks.

### 1. Point Domain to EC2
1. Go to your domain registrar (GoDaddy, Namecheap, Route 53, etc.).
2. Add an **A Record**:
   * **Host**: `@` (or a subdomain like `iot`)
   * **Value**: Your EC2 Public IP address

### 2. Install Let's Encrypt SSL (Certbot)
If you went with **Option B (Manual)**:
```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

If you went with **Option A (Docker)**:
Since Docker is holding port 80, you can temporarily stop it to request the certificate, or use Nginx/Apache on the host as a reverse proxy.
The easiest setup for Docker is using a host-based Apache to terminate SSL:
1. Bind Docker to port `8080` instead in `docker-compose.yml` (`ports: - "127.0.0.1:8080:80"`).
2. Install Apache on the host: `sudo apt install apache2 -y`.
3. Enable proxy modules on host Apache: `sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers ssl`.
4. Configure `/etc/apache2/sites-available/000-default.conf` to proxy everything to the Docker container:
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       
       # Proxy WebSockets
       RewriteEngine On
       RewriteCond %{HTTP:Upgrade} =websocket [NC]
       RewriteRule ^(.*)$ ws://127.0.0.1:8080$1 [P,L]
       
       # Proxy regular HTTP
       ProxyPass / http://127.0.0.1:8080/
       ProxyPassReverse / http://127.0.0.1:8080/
   </VirtualHost>
   ```
5. Install Certbot: `sudo apt install certbot python3-certbot-apache -y` and run `sudo certbot --apache -d yourdomain.com`. It will auto-configure SSL for you!

---

## Step 4: ESP8266 Client Library Configuration

Once the server is running on `yourdomain.com` with SSL, update your ESP8266 firmware to connect to it.

1. Open your ESP8266 code (using the `NinjaIoT` library).
2. Update the credentials and server parameters in your Arduino Sketch:
   ```cpp
   // Before (Localhost / XAMPP):
   // NinjaIoT iot("192.168.1.100", 80, "YOUR_API_KEY");

   // After (AWS Secure EC2 Deployment):
   // Use port 443 for secure connections (HTTPS / WSS)
   NinjaIoT iot("yourdomain.com", 443, "YOUR_API_KEY");
   ```
3. Set the client connection mode to secure if your library supports it, or use the non-secure port (80) if SSL verification is skipped.

---

## Key Maintenance Tips for Free-Tier

1. **Monitor Disk Space**: A `t2.micro` has a 30 GB EBS limit (within free tier). Watch out for growing SQLite log tables (`iot_history`). You can set up a cron job to purge rows older than 30 days.
2. **Elastic IP (EIP)**: By default, EC2 public IP addresses change if the instance is stopped and started. To keep your IP static, allocate an **Elastic IP** in AWS Console and associate it with your EC2 instance. (Note: Elastic IPs are free *only* when attached to a running instance).
3. **Backup Database**: Periodically download or copy the SQLite database located at `data/iot_platform.db` using SCP/SFTP to keep your user credentials and IoT telemetry history safe.
