const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

const configPath = path.join(__dirname, 'config.json');
const exampleConfigPath = path.join(__dirname, 'config.example.json');
const config = fs.existsSync(configPath)
  ? JSON.parse(fs.readFileSync(configPath, 'utf8'))
  : JSON.parse(fs.readFileSync(exampleConfigPath, 'utf8'));

const host = config.host || '127.0.0.1';
const port = Number(config.port || 9123);

function sendJson(res, status, body) {
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'GET,POST,OPTIONS'
  });
  res.end(JSON.stringify(body));
}

function friendlyPrintError(error) {
  const message = String(error && (error.message || error) || '');

  if (/EPERM|UnauthorizedAccess|Access.*denied|operation not permitted/i.test(message)) {
    return `Cannot open ${config.serialPath}. The COM port is busy or blocked. Close other printer apps/queues, turn the printer off/on, then restart the bridge. If it continues, use the outgoing Bluetooth COM port instead.`;
  }

  if (/ETIMEDOUT|timed out|TimeoutException/i.test(message)) {
    return `Timed out opening ${config.serialPath}. This is usually the wrong Bluetooth COM port or the printer is not connected/awake. Use the Outgoing COM port in Bluetooth settings.`;
  }

  if (/ENOENT|does not exist|not found/i.test(message)) {
    return `${config.serialPath} was not found. Check Device Manager > Ports (COM & LPT), then update config.json.`;
  }

  return message || 'Unable to print receipt';
}

function readRequestBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', chunk => {
      body += chunk;
      if (body.length > 1024 * 1024) {
        req.destroy();
        reject(new Error('Request body is too large'));
      }
    });
    req.on('end', () => resolve(body));
    req.on('error', reject);
  });
}

function printToSerial(buffer) {
  return new Promise((resolve, reject) => {
    const serialPath = String(config.serialPath || '').trim();
    const baudRate = Number(config.baudRate || 9600);
    const timeoutMs = Number(config.printTimeoutMs || 12000);

    if (!serialPath) {
      reject(new Error('serialPath is required for serial transport'));
      return;
    }

    const tempFile = path.join(os.tmpdir(), `pos-thermal-serial-${Date.now()}.bin`);
    fs.writeFileSync(tempFile, buffer);

    const script = `
$portName = ${JSON.stringify(serialPath)}
$baudRate = ${baudRate}
$timeoutMs = ${timeoutMs}
$filePath = ${JSON.stringify(tempFile)}
$port = New-Object System.IO.Ports.SerialPort $portName, $baudRate, 'None', 8, 'One'
$port.Encoding = [System.Text.Encoding]::GetEncoding(437)
$port.ReadTimeout = $timeoutMs
$port.WriteTimeout = $timeoutMs
try {
  $port.Open()
  $bytes = [System.IO.File]::ReadAllBytes($filePath)
  $port.Write($bytes, 0, $bytes.Length)
  Start-Sleep -Milliseconds 300
} finally {
  if ($port.IsOpen) { $port.Close() }
  $port.Dispose()
  Remove-Item -LiteralPath $filePath -Force -ErrorAction SilentlyContinue
}
`;

    const child = spawn('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', script], {
      windowsHide: true
    });

    let stderr = '';
    child.stderr.on('data', data => {
      stderr += data.toString();
    });
    child.on('error', reject);
    child.on('close', code => {
      if (fs.existsSync(tempFile)) {
        fs.unlinkSync(tempFile);
      }

      if (code === 0) {
        resolve();
      } else {
        reject(new Error(stderr || `Unable to write to ${serialPath}. Make sure it is the outgoing Bluetooth COM port and the printer is awake.`));
      }
    });
  });
}

function printToWindowsPrinter(buffer) {
  return new Promise((resolve, reject) => {
    const printerName = String(config.windowsPrinterName || '').trim();
    if (!printerName) {
      reject(new Error('windowsPrinterName is required for windows-printer transport'));
      return;
    }

    const tempFile = path.join(os.tmpdir(), `pos-thermal-${Date.now()}.bin`);
    fs.writeFileSync(tempFile, buffer);

    const script = `
$printerName = ${JSON.stringify(printerName)}
$filePath = ${JSON.stringify(tempFile)}
Add-Type -TypeDefinition @"
using System;
using System.IO;
using System.Runtime.InteropServices;
public class RawPrinterHelper {
  [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Ansi)]
  public class DOCINFOA {
    [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
    [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
    [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
  }
  [DllImport("winspool.Drv", EntryPoint="OpenPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);
  [DllImport("winspool.Drv", EntryPoint="ClosePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool ClosePrinter(IntPtr hPrinter);
  [DllImport("winspool.Drv", EntryPoint="StartDocPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);
  [DllImport("winspool.Drv", EntryPoint="EndDocPrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool EndDocPrinter(IntPtr hPrinter);
  [DllImport("winspool.Drv", EntryPoint="StartPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool StartPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.Drv", EntryPoint="EndPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool EndPagePrinter(IntPtr hPrinter);
  [DllImport("winspool.Drv", EntryPoint="WritePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
  public static extern bool WritePrinter(IntPtr hPrinter, byte[] pBytes, Int32 dwCount, out Int32 dwWritten);
  public static bool SendBytes(string printerName, byte[] bytes) {
    IntPtr hPrinter;
    DOCINFOA di = new DOCINFOA();
    di.pDocName = "POS Thermal Receipt";
    di.pDataType = "RAW";
    if (!OpenPrinter(printerName.Normalize(), out hPrinter, IntPtr.Zero)) return false;
    bool ok = false;
    try {
      if (StartDocPrinter(hPrinter, 1, di)) {
        if (StartPagePrinter(hPrinter)) {
          int written;
          ok = WritePrinter(hPrinter, bytes, bytes.Length, out written);
          EndPagePrinter(hPrinter);
        }
        EndDocPrinter(hPrinter);
      }
    } finally {
      ClosePrinter(hPrinter);
    }
    return ok;
  }
}
"@
$bytes = [System.IO.File]::ReadAllBytes($filePath)
if (-not [RawPrinterHelper]::SendBytes($printerName, $bytes)) {
  throw "Unable to write raw bytes to printer: $printerName"
}
Remove-Item -LiteralPath $filePath -Force
`;

    const child = spawn('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', script], {
      windowsHide: true
    });

    let stderr = '';
    child.stderr.on('data', data => {
      stderr += data.toString();
    });
    child.on('error', reject);
    child.on('close', code => {
      if (fs.existsSync(tempFile)) {
        fs.unlinkSync(tempFile);
      }

      if (code === 0) {
        resolve();
      } else {
        reject(new Error(stderr || `PowerShell exited with code ${code}`));
      }
    });
  });
}

async function printReceipt(buffer) {
  if (config.transport === 'windows-printer') {
    await printToWindowsPrinter(buffer);
    return;
  }

  await printToSerial(buffer);
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'OPTIONS') {
    sendJson(res, 200, { ok: true });
    return;
  }

  if (req.method === 'GET' && req.url === '/status') {
    sendJson(res, 200, {
      ok: true,
      bridge: 'pos-printer-bridge',
      transport: config.transport || 'serial',
      target: config.transport === 'windows-printer' ? config.windowsPrinterName : config.serialPath
    });
    return;
  }

  if (req.method === 'POST' && req.url === '/print') {
    try {
      const body = JSON.parse(await readRequestBody(req));
      const payload = Buffer.from(body.payload || '', 'base64');
      if (!payload.length) {
        sendJson(res, 400, { ok: false, message: 'Missing print payload' });
        return;
      }

      await printReceipt(payload);
      sendJson(res, 200, { ok: true });
    } catch (error) {
      console.error(`[print-error] ${error.stack || error.message}`);
      sendJson(res, 500, { ok: false, message: friendlyPrintError(error) });
    }
    return;
  }

  sendJson(res, 404, { ok: false, message: 'Not found' });
});

server.listen(port, host, () => {
  console.log(`POS printer bridge listening at http://${host}:${port}`);
  console.log(`Transport: ${config.transport || 'serial'}`);
  console.log(`Target: ${config.transport === 'windows-printer' ? config.windowsPrinterName : config.serialPath}`);
});
