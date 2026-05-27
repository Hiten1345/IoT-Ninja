#!/bin/bash
set -e

DOMAIN=${1:-"iot.beerobokids.com"}
EMAIL="hiten1345@gmail.com"

echo "=== Step 1: Re-configuring Docker Container to Port 8080 ==="
# Stop and remove existing container on port 80
sudo docker stop ninja-iot-app || true
sudo docker rm ninja-iot-app || true

# Run container on local port 8080 (secured to localhost)
sudo docker run -d --name ninja-iot-app -p 127.0.0.1:8080:80 -v /home/ubuntu/iot-node/data:/var/www/html/data --restart always ninja-iot-app

echo "=== Step 2: Installing Nginx and Certbot ==="
sudo apt-get update -y
sudo apt-get install -y nginx certbot python3-certbot-nginx

echo "=== Step 3: Configuring Nginx Reverse Proxy ==="
# Create virtual host config
sudo tee /etc/nginx/sites-available/ninja-iot > /dev/null <<EOF
server {
    listen 80;
    server_name $DOMAIN;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSockets Support
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}
EOF

# Enable configuration and disable default
sudo ln -sf /etc/nginx/sites-available/ninja-iot /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default || true

# Test and reload Nginx
sudo nginx -t
sudo systemctl restart nginx

echo "=== Step 4: Obtaining Let's Encrypt SSL Certificate ==="
# Request SSL Certificate (non-interactive)
sudo certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m $EMAIL --redirect

echo "=== SSL SETUP COMPLETED SUCCESSFULLY ==="
echo "Your app is now securely live at: https://$DOMAIN/"
