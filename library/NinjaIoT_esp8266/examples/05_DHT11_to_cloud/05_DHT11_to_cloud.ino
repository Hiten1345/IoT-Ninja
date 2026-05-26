#include <NinjaIoT.h>
#include "DHT.h"

NinjaIoT iot;

//first search "DHT sensor library" in library manager by Adafruit
//and install it

#define DHTPIN D3   // connect DHT11 to pin D3   
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200);
  iot.connect("wifi-name", "wifi-pass", "Your_Project_API");   // Use your Project API Key from https://iot.roboninja.in/
  dht.begin();
}

void loop() {
  
 float h = dht.readHumidity();
 float t = dht.readTemperature();
 
 iot.WriteVar("Temperature", t);
 iot.WriteVar("Humidity", h);
 
 delay(1500); 
}


