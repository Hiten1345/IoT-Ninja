#!/bin/bash

# Start Node.js WebSocket server in the background
echo "Starting Node.js WebSocket Server on port 8080..."
node server-ws.js &

# Start Apache Web Server in the foreground
echo "Starting Apache Web Server..."
exec apache2-foreground
