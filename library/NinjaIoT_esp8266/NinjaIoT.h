#ifndef NinjaIoT_h
#define NinjaIoT_h

#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <WebSocketsClient.h>

class NinjaIoT {
  public:
    void connect(const char* ssid, const char* password, const String& projectKey);
    void connect(const char* ssid, const char* password, const String& projectKey, const String& wsHost, int wsPort = 8080);
    void connect(const char* ssid, const char* password, const String& projectKey, const String& baseURL); // HTTP fallback

    String ReadAll();
    String Read(const String& field);
    void SyncOut(const String& field);
    void SyncIN(const String& field);
    void SyncPWM(const String& field);
    String SyncVar(const String& variable);

    void WriteVar(const String& variable, const String& value);
    void WriteVar(const String& variable, int value);
    void WriteVar(const String& variable, float value);

    void handleWebSocketEvent(int type, uint8_t * payload, size_t length);

  private:
    String projectKey;
    String baseURL;
    String wsHost;
    int wsPort;
    bool isWebSocketMode;

    WebSocketsClient webSocket;
    String cachedJson;

    int getGPIO(const String& field);
    void writeField(const String& field, const String& value);
    String parseJsonValue(const String& json, const String& key);
    String updateJsonValue(const String& json, const String& key, const String& value);
    String readField(const String& field);
};

#endif
