#!/usr/bin/env python3
import serial
import time
import sys

port = '/dev/cu.usbmodem2101'
baudrate = 115200
timeout_seconds = 15

try:
    ser = serial.Serial(port, baudrate, timeout=1)
    print(f"Connected to {port} at {baudrate} baud", file=sys.stderr)
    print(f"Reading for {timeout_seconds} seconds...\n", file=sys.stderr)

    start_time = time.time()
    while (time.time() - start_time) < timeout_seconds:
        if ser.in_waiting > 0:
            data = ser.read(ser.in_waiting)
            try:
                print(data.decode('utf-8', errors='ignore'), end='')
            except:
                pass
        time.sleep(0.01)

    ser.close()
    print(f"\n\n=== Capture complete ===", file=sys.stderr)

except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
