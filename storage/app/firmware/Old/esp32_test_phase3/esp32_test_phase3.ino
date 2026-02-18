void setup() {
  // ESP32-P4 uses USB Serial/JTAG for logging
  // Initialize both Serial (USB-JTAG) and Serial0 (UART0)
  Serial.begin(115200);
  Serial0.begin(115200);

  // Wait for serial port to connect
  delay(2000);

  // Print to both serial interfaces
  Serial.println("\n\n=================================");
  Serial.println("ESP32-P4 Hello World Test");
  Serial.println("=================================");
  Serial.println("Setup complete!");

  Serial0.println("\n\n=================================");
  Serial0.println("ESP32-P4 Hello World Test (UART0)");
  Serial0.println("=================================");
  Serial0.println("Setup complete!");

  // Also try printf which goes through ESP-IDF logging
  printf("Hello from printf!\n");
}

void loop() {
  static int counter = 0;

  Serial.print("Hello World from ESP32-P4! Counter: ");
  Serial.println(counter);

  Serial0.print("Hello World (UART0)! Counter: ");
  Serial0.println(counter);

  printf("Hello from printf! Counter: %d\n", counter);

  counter++;
  delay(2000);  // Wait 2 seconds between messages
}