# POS Thermal Printer Bridge

This bridge lets the browser POS send ESC/POS receipt bytes to a local USB or Bluetooth thermal printer.

## Setup

1. Install Node.js on the cashier computer.
2. Copy `config.example.json` to `config.json`.
3. Configure one transport:

### Bluetooth or USB COM Port

Use this when the printer appears as `COM3`, `COM4`, etc.

```json
{
  "host": "127.0.0.1",
  "port": 9123,
  "transport": "serial",
  "serialPath": "COM3"
}
```

### Windows Installed Printer

Use this when the thermal printer appears in Windows Printers & Scanners.

```json
{
  "host": "127.0.0.1",
  "port": 9123,
  "transport": "windows-printer",
  "windowsPrinterName": "POS-58"
}
```

4. Run `start-printer-bridge.bat`.
5. In POS, check that the Thermal Printer status says `Connected`.
6. Complete a sale, then click `Thermal Print` in the receipt modal.

The bridge listens only on `127.0.0.1` by default.
