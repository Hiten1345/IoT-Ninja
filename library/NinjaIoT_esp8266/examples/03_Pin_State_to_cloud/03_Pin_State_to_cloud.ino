#include <NinjaIoT.h>

NinjaIoT iot;

void setup() {
  Serial.begin(115200);
  iot.connect("wifi-name", "wifi-pass", "Your_Project_API");   // Use your Project API Key from https://iot.roboninja.in/
}

void loop() {
 
  // Read button state on D0 and upload it
  iot.SyncIN("D0");

  delay(50);  // wait 50 milliseconds
}



