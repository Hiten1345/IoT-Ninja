#include "NinjaIoT.h"
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>

static NinjaIoT* _instance = nullptr;

static void webSocketEvent(WStype_t type, uint8_t * payload, size_t length) {
  if (_instance) {
    _instance->handleWebSocketEvent((int)type, payload, length);
  }
}

void NinjaIoT::connect(const char* ssid, const char* password, const String& projectKey) {
  // Default to WebSocket on iot-ninja.onrender.com at port 80
  connect(ssid, password, projectKey, "iot-ninja.onrender.com", 80);
}

void NinjaIoT::connect(const char* ssid, const char* password, const String& projectKey, const String& wsHost, int wsPort) {
  this->projectKey = projectKey;
  this->wsHost = wsHost;
  this->wsPort = wsPort;
  this->isWebSocketMode = true;
  this->cachedJson = "";
  _instance = this;

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(50);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");

  Serial.print("Connecting to WebSocket: ws://");
  Serial.print(wsHost);
  Serial.print(":");
  Serial.println(wsPort);

  webSocket.begin(wsHost, wsPort, "/");
  webSocket.onEvent(webSocketEvent);
  webSocket.setReconnectInterval(3000);
}

void NinjaIoT::connect(const char* ssid, const char* password, const String& projectKey, const String& baseURL) {
  this->projectKey = projectKey;
  this->baseURL = baseURL;
  this->isWebSocketMode = false;
  this->cachedJson = "";

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(50);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");
}

void NinjaIoT::handleWebSocketEvent(int type, uint8_t * payload, size_t length) {
  switch(type) {
    case WStype_DISCONNECTED:
      Serial.println("[WS] Disconnected!");
      break;
    case WStype_CONNECTED: {
      Serial.println("[WS] Connected!");
      String subMsg = "{\"type\":\"subscribe\",\"apiKey\":\"" + projectKey + "\"}";
      webSocket.sendTXT(subMsg);
      break;
    }
    case WStype_TEXT: {
      String text = String((char*)payload);
      String msgType = parseJsonValue(text, "type");
      if (msgType == "init") {
          int dataIndex = text.indexOf("\"data\":");
          if (dataIndex != -1) {
              int startBrace = text.indexOf('{', dataIndex);
              int lastBrace = text.lastIndexOf('}');
              if (startBrace != -1 && lastBrace != -1 && lastBrace > startBrace) {
                  cachedJson = text.substring(startBrace, lastBrace + 1);
              }
          }
      } else if (msgType == "update") {
          String field = parseJsonValue(text, "field");
          String value = parseJsonValue(text, "value");
          if (field != "") {
              cachedJson = updateJsonValue(cachedJson, field, value);
          }
      }
      break;
    }
  }
}

String NinjaIoT::ReadAll() {
  if (WiFi.status() != WL_CONNECTED) return "";
  
  if (isWebSocketMode) {
    webSocket.loop();
    return cachedJson;
  } else {
    WiFiClientSecure secureClient;
    secureClient.setInsecure();
    HTTPClient https;
    String url = baseURL + projectKey + "/read_all";
    https.begin(secureClient, url);
    int httpCode = https.GET();
    String payload = "";
    if (httpCode > 0) {
      payload = https.getString();
    }
    https.end();
    delay(50);
    cachedJson = payload;
    return payload;
  }
}

String NinjaIoT::parseJsonValue(const String& json, const String& key) {
  int keyIndex = json.indexOf("\"" + key + "\"");
  if (keyIndex == -1) return "";

  int colonIndex = json.indexOf(':', keyIndex);
  if (colonIndex == -1) return "";

  int firstQuote = json.indexOf('"', colonIndex);
  if (firstQuote == -1) return "";

  int secondQuote = json.indexOf('"', firstQuote + 1);
  if (secondQuote == -1) return "";

  return json.substring(firstQuote + 1, secondQuote);
}

String NinjaIoT::updateJsonValue(const String& json, const String& key, const String& value) {
  int keyIndex = json.indexOf("\"" + key + "\"");
  if (keyIndex == -1) {
      if (json == "" || json == "{}") {
          return "{\"" + key + "\":\"" + value + "\"}";
      }
      int lastBrace = json.lastIndexOf('}');
      if (lastBrace != -1) {
          return json.substring(0, lastBrace) + ",\"" + key + "\":\"" + value + "\"}";
      }
      return json;
  }
  
  int colonIndex = json.indexOf(':', keyIndex);
  if (colonIndex == -1) return json;
  
  int firstQuote = json.indexOf('"', colonIndex);
  if (firstQuote == -1) return json;
  
  int secondQuote = json.indexOf('"', firstQuote + 1);
  if (secondQuote == -1) return json;
  
  return json.substring(0, firstQuote + 1) + value + json.substring(secondQuote);
}

String NinjaIoT::Read(const String& field) {
  if (WiFi.status() != WL_CONNECTED) return "";
  
  if (isWebSocketMode) {
    webSocket.loop();
    return parseJsonValue(cachedJson, field);
  } else {
    WiFiClientSecure secureClient;
    secureClient.setInsecure();
    HTTPClient https;
    String url = baseURL + projectKey + "/read?" + field;
    https.begin(secureClient, url);
    int httpCode = https.GET();
    String payload = "";
    if (httpCode > 0) {
      payload = https.getString();
      payload.trim();
    }
    https.end();
    delay(50);
    return payload;
  }
}

String NinjaIoT::readField(const String& field) {
  return Read(field);
}

void NinjaIoT::SyncOut(const String& field) {
  int pin = getGPIO(field);
  if (pin == -1) {
    Serial.println("SyncOut: Invalid pin name.");
    return;
  }

  pinMode(pin, OUTPUT);
  String value = readField(field);
  digitalWrite(pin, value == "1" ? HIGH : LOW);
  Serial.println("SyncOut: Set " + field + " -> " + value);
}

void NinjaIoT::SyncIN(const String& field) {
  int pin = getGPIO(field);
  if (pin == -1) {
    Serial.println("SyncIN: Invalid pin name.");
    return;
  }

  pinMode(pin, INPUT_PULLUP);
  int value = digitalRead(pin);
  writeField(field, String(value));
  Serial.println("SyncIN: Sent " + field + " -> " + String(value));
}

void NinjaIoT::SyncPWM(const String& field) {
  int pin = getGPIO(field);
  if (pin == -1) {
    Serial.println("SyncPWM: Invalid pin name.");
    return;
  }

  pinMode(pin, OUTPUT);
  String valueStr = readField(field);
  int pwmValue = valueStr.toInt();

  if (pwmValue < 0) pwmValue = 0;
  if (pwmValue > 1023) pwmValue = 1023; // ESP8266 PWM limit

  analogWrite(pin, pwmValue);
  Serial.println("SyncPWM: PWM " + field + " = " + String(pwmValue));
}

String NinjaIoT::SyncVar(const String& variable) {
  if (isWebSocketMode) {
    webSocket.loop();
  } else if (cachedJson == "") {
    ReadAll();
  }
  String val = parseJsonValue(cachedJson, variable);
  Serial.println("SyncVar: " + variable + " = " + val);
  return val;
}

void NinjaIoT::WriteVar(const String& variable, const String& value) {
  if (WiFi.status() != WL_CONNECTED) return;

  if (isWebSocketMode) {
    // Send write message via WebSocket
    String wsMsg = "{\"type\":\"write\",\"uid\":\"" + projectKey + "\",\"field\":\"" + variable + "\",\"value\":\"" + value + "\"}";
    webSocket.sendTXT(wsMsg);
    Serial.println("WriteVar (WS) OK: " + variable + "=" + value);
  } else {
    // HTTP Fallback
    WiFiClientSecure secureClient;
    secureClient.setInsecure();
    HTTPClient https;
    String url = baseURL + projectKey + "/write?" + variable + "=" + value;
    https.begin(secureClient, url);
    int httpCode = https.GET();
    https.end();
    if (httpCode > 0) {
      Serial.println("WriteVar HTTP OK: " + variable + "=" + value);
    } else {
      Serial.println("WriteVar HTTP failed");
    }
    delay(50);
  }
}

void NinjaIoT::WriteVar(const String& variable, int value) {
  WriteVar(variable, String(value));
}

void NinjaIoT::WriteVar(const String& variable, float value) {
  WriteVar(variable, String(value, 2));
}

void NinjaIoT::writeField(const String& field, const String& value) {
  WriteVar(field, value);
}

int NinjaIoT::getGPIO(const String& field) {
  if (field == "D0") return 16;
  if (field == "D1") return 5;
  if (field == "D2") return 4;
  if (field == "D3") return 0;
  if (field == "D4") return 2;
  if (field == "D5") return 14;
  if (field == "D6") return 12;
  if (field == "D7") return 13;
  if (field == "D8") return 15;
  if (field == "A0") return A0;
  return -1;
}
