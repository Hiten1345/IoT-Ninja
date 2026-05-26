const http = require('http');
const fs = require('fs');
const path = require('path');
const { WebSocketServer } = require('ws');

// 1. Resolve PHP Local API URL
let appBaseUrl = 'http://localhost/IOT-Node/';
try {
    const configPath = path.join(__dirname, 'config.php');
    if (fs.existsSync(configPath)) {
        const configContent = fs.readFileSync(configPath, 'utf8');
        const match = configContent.match(/define\(\s*['"]APP_BASE_URL['"]\s*,\s*['"](.*?)['"]\s*\)/);
        if (match && match[1]) {
            appBaseUrl = match[1];
        }
    }
} catch (e) {
    console.error('Error reading config.php:', e);
}

let localApiUrl = 'http://127.0.0.1/index.php';
try {
    const isDocker = fs.existsSync('/.dockerenv') || __dirname.startsWith('/var/www/html') || process.env.RENDER;
    if (isDocker) {
        localApiUrl = 'http://127.0.0.1/index.php';
    } else {
        const parsedUrl = new URL(appBaseUrl);
        const port = parsedUrl.port ? `:${parsedUrl.port}` : '';
        localApiUrl = `http://127.0.0.1${port}${parsedUrl.pathname}index.php`;
    }
} catch (e) {
    console.error('Error parsing APP_BASE_URL, using default:', e);
}

console.log('App Base URL:', appBaseUrl);
console.log('Resolved Local PHP API URL:', localApiUrl);

// 2. Map of Project ID (UID) -> Set of WebSocket clients
const subscriptions = new Map();

// Helper to broadcast to a project
function broadcastToProject(uid, message) {
    const clients = subscriptions.get(uid);
    if (!clients) return;
    const payload = JSON.stringify(message);
    for (const ws of clients) {
        if (ws.readyState === 1) { // WebSocket.OPEN
            ws.send(payload);
        }
    }
}

// 3. Create HTTP Server
const server = http.createServer((req, res) => {
    // Handle local notification from PHP
    if (req.method === 'POST' && req.url === '/update') {
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const { uid, field, value } = data;
                if (uid && field) {
                    console.log(`PHP Broadcast -> Project: ${uid}, Field: ${field}, Value: ${value}`);
                    broadcastToProject(uid, {
                        type: 'update',
                        field: field,
                        value: value
                    });
                }
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true }));
            } catch (err) {
                res.writeHead(400, { 'Content-Type': 'text/plain' });
                res.end('Invalid JSON');
            }
        });
        return;
    }

    res.writeHead(404);
    res.end('Not Found');
});

// 4. Create WebSocket Server
const wss = new WebSocketServer({ server });

wss.on('connection', (ws) => {
    let clientUid = null;
    console.log('Client connected');

    ws.on('message', async (messageStr) => {
        try {
            const msg = JSON.parse(messageStr);
            console.log('Received WS Message:', msg);

            if (msg.type === 'subscribe') {
                let uid = msg.uid;

                // If apiKey is provided, validate it first
                if (msg.apiKey) {
                    uid = await validateApiKey(msg.apiKey);
                    if (!uid) {
                        ws.send(JSON.stringify({ type: 'error', message: 'invalid_api_key' }));
                        ws.close();
                        return;
                    }
                }

                if (!uid) {
                    ws.send(JSON.stringify({ type: 'error', message: 'missing_uid' }));
                    return;
                }

                clientUid = uid;
                
                // Add to subscription set
                if (!subscriptions.has(uid)) {
                    subscriptions.set(uid, new Set());
                }
                subscriptions.get(uid).add(ws);
                console.log(`Client subscribed to project: ${uid}`);

                // Fetch and send initial state
                const state = await fetchProjectState(uid);
                if (state) {
                    ws.send(JSON.stringify({
                        type: 'init',
                        data: state
                    }));
                }
            }

            else if (msg.type === 'write') {
                const { uid, field, value } = msg;
                if (!uid || !field) return;

                // Send write request to PHP
                const success = await writeProjectValue(uid, field, value);
                if (!success) {
                    ws.send(JSON.stringify({ type: 'error', message: 'write_failed', field }));
                }
            }
        } catch (err) {
            console.error('Error handling message:', err);
        }
    });

    ws.on('close', () => {
        console.log('Client disconnected');
        if (clientUid && subscriptions.has(clientUid)) {
            const set = subscriptions.get(clientUid);
            set.delete(ws);
            if (set.size === 0) {
                subscriptions.delete(clientUid);
            }
        }
    });
});

// Helper: validate API Key via PHP API
function validateApiKey(apiKey) {
    return new Promise((resolve) => {
        const url = `${localApiUrl}?action=verify_api_key&api_key=${encodeURIComponent(apiKey)}`;
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    if (json && json.valid) {
                        resolve(json.project_id);
                    } else {
                        resolve(null);
                    }
                } catch (e) {
                    console.error('Failed to parse API key validation response:', e, 'Response was:', data);
                    resolve(null);
                }
            });
        }).on('error', (err) => {
            console.error('API key validation request failed:', err);
            resolve(null);
        });
    });
}

// Helper: fetch latest values from PHP
function fetchProjectState(uid) {
    return new Promise((resolve) => {
        const url = `${localApiUrl}?action=read&UID=${encodeURIComponent(uid)}`;
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    resolve(json);
                } catch (e) {
                    resolve(null);
                }
            });
        }).on('error', () => resolve(null));
    });
}

// Helper: send write request to PHP
function writeProjectValue(uid, field, value) {
    return new Promise((resolve) => {
        const url = `${localApiUrl}?action=write&UID=${encodeURIComponent(uid)}&${encodeURIComponent(field)}=${encodeURIComponent(value)}`;
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                resolve(data.trim() === 'success');
            });
        }).on('error', (err) => {
            console.error('Write request to PHP failed:', err);
            resolve(false);
        });
    });
}

// Start HTTP server on port 8080
server.listen(8080, '0.0.0.0', () => {
    console.log('WebSocket Server is running on port 8080');
});
