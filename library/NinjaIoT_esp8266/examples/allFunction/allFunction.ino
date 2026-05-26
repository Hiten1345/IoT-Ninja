#include <NinjaIoT.h>

NinjaIoT iot;
int var = 0;

void setup() {
  Serial.begin(115200);
  iot.connect("wifi-name", "wifi-pass", "Your_Project_API");   // Use your Project API Key from https://iot.roboninja.in/
}

void loop() {
  var = random(0, 100);

  // This will fetch and cache all values once per loop cycle
  iot.ReadAll();

  // Control LED D1 according to server value (ON/OFF)
  iot.SyncOut("D1");

  // Adjust PWM brightness on D2 from server value
  iot.SyncPWM("D2");

  // Read button state on D0 and upload it
  iot.SyncIN("D0");

  // Read Temperature variable from cached JSON
  String temp = iot.SyncVar("Temperature");
  Serial.println("Temperature from cloud: " + temp);

  // Write new Temperature value to cloud
  iot.WriteVar("Temperature", var);

  delay(2000);  // wait 2 seconds before next cycle
}



