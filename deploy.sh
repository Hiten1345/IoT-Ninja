#!/bin/bash
set -e

echo "=== Step 1: Updating System Packages ==="
sudo apt-get update -y

echo "=== Step 2: Installing Docker ==="
sudo apt-get install -y docker.io
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker ubuntu || true

echo "=== Step 3: Setting Up Project Directory ==="
# Navigate to home directory
cd /home/ubuntu

if [ ! -d "iot-node" ]; then
  git clone https://github.com/Hiten1345/IoT-Ninja.git iot-node
else
  cd iot-node
  git pull
  cd ..
fi

cd iot-node

echo "=== Step 4: Configuring Permissions ==="
mkdir -p data
sudo chmod -R 777 data

echo "=== Step 5: Building & Running Docker Container ==="
# Stop and remove existing container if it exists
sudo docker stop ninja-iot-app || true
sudo docker rm ninja-iot-app || true

# Build new docker image
sudo docker build -t ninja-iot-app .

# Run container with mapped SQLite data folder for persistence
sudo docker run -d --name ninja-iot-app -p 80:80 -v /home/ubuntu/iot-node/data:/var/www/html/data --restart always ninja-iot-app

# Ensure SQLite database is writable
sudo chmod -R 777 data

echo "=== DEPLOYMENT COMPLETED SUCCESSFULLY ==="
echo "Your app is now live at: http://13.62.225.114/"
