#include <NinjaIoT.h>

NinjaIoT iot;

int SoilMoisture = 0;

void setup() {
  Serial.begin(115200);
  iot.connect("wifi-name", "wifi-pass", "Your_Project_API");   // Use your Project API Key from https://iot.roboninja.in/
 
}

void loop() {
 
 SoilMoisture = analogRead(A0); // Read the analog value from A0 pin
 iot.WriteVar("SoilMoisture", SoilMoisture); // Send the value to the cloud
 
 delay(1500); // Wait for 1.5 seconds before sending the next value
}


