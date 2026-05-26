# NinjaIoT Arduino Library

Arduino library for connecting ESP8266 devices to the NinjaIoT platform.

## Installation

1. Download this library
2. In Arduino IDE, go to **Sketch > Include Library > Add .ZIP Library**
3. Select the downloaded library file

## API Changes (v2.0)

This library has been updated to work with the new API structure:

### New API Endpoints

- **Base URL**: `https://iot.roboninja.in/api/v1/{PROJECT_KEY}/`
- **Write Value**: `/write?field=value`
- **Read Specific Value**: `/read?field` (returns plain text)
- **Read Multiple Values**: `/read?fields=field1,field2` (returns JSON)
- **Read All Values**: `/read_all` (returns JSON)

### Migration from v1.0

**Old way (v1.0):**
```cpp
iot.connect("wifi-name", "wifi-pass", "UIDofIoTPlatform");
```

**New way (v2.0):**
```cpp
iot.connect("wifi-name", "wifi-pass", "UM0vACbn");  // Use your Project API Key
```

### Custom Base URL

If you need to use a different server or domain, you can specify a custom base URL:

```cpp
iot.connect("wifi-name", "wifi-pass", "UM0vACbn", "https://your-custom-domain.com/api/v1/");
```

## Quick Start

```cpp
#include <NinjaIoT.h>

NinjaIoT iot;

void setup() {
  Serial.begin(115200);
  iot.connect("your-wifi-name", "your-wifi-password", "UM0vACbn");
}

void loop() {
  iot.ReadAll();   // Read all values from the cloud
  iot.SyncOut("D1");  // Control LED D1 according to server value
  delay(50);
}
```

## Available Functions

### Connection
- `connect(ssid, password, projectKey)` - Connect to WiFi and set project key (uses default localhost API)
- `connect(ssid, password, projectKey, baseURL)` - Connect with custom base URL

### Reading Data
- `ReadAll()` - Fetch all values from cloud and cache them (returns JSON string)
- `Read(field)` - Read a specific field value (returns plain text)
- `SyncVar(variable)` - Get variable value from cached JSON

### Writing Data
- `WriteVar(variable, value)` - Write a variable to the cloud (supports String, int, float)
- `SyncIN(field)` - Read digital pin state and upload to cloud
- `SyncOut(field)` - Set digital pin according to cloud value
- `SyncPWM(field)` - Set PWM output according to cloud value

## Pin Mapping (ESP8266)

| Arduino Pin | GPIO Pin |
|-------------|----------|
| D0          | GPIO16   |
| D1          | GPIO5    |
| D2          | GPIO4    |
| D3          | GPIO0    |
| D4          | GPIO2    |
| D5          | GPIO14   |
| D6          | GPIO12   |
| D7          | GPIO13   |
| D8          | GPIO15   |
| A0          | ADC0     |

## Examples

Check the `examples` folder for:
- **01_Simple_LED** - Control an LED from the cloud
- **02_LED_Brightness_control** - PWM brightness control
- **03_Pin_State_to_cloud** - Upload button state
- **04_analogSensor_to_cloud** - Send analog sensor data
- **05_DHT11_to_cloud** - Temperature and humidity monitoring
- **allFunction** - Demonstrates all library functions

## Technical Notes

### API Changes
- Changed from HTTPS to HTTP (removed SSL/TLS overhead)
- Changed from `WiFiClientSecure` to `WiFiClient`
- Updated URL structure from query parameters to REST-style paths
- Project identification changed from `UID` to `projectKey`

### Performance
- The `Read(field)` method now makes individual API calls for specific fields
- `ReadAll()` still caches JSON for use with `SyncVar()`
- Rate limiting: 50ms delay between requests

## License

MIT License

## Support

For issues and questions, please visit: https://iot.roboninja.in/



