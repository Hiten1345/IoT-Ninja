#include <NinjaIoT.h>

NinjaIoT iot;

void setup() {
  Serial.begin(115200);
  iot.connect("wifi-name", "wifi-pass", "Your_Project_API");   // Use your Project API Key from https://iot.roboninja.in/
}

void loop() {

  // Control LED D1 according to server value (ON/OFF)
  iot.SyncOut("D1");

  delay(50);  // wait 50 milliseconds
}



