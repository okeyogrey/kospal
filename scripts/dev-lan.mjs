/**
 * Local-only LAN dev server: bind Laravel + Vite on 0.0.0.0 so a phone/tablet
 * on the same Wi-Fi can open KOSPAL in the browser.
 *
 * Does not change Tauri / desktop:serve (those stay on 127.0.0.1).
 * Does not mutate .env — APP_URL is set only for these child processes.
 *
 * Usage: npm run dev:lan
 * Override IP: set KOSPAL_LAN_HOST=192.168.x.x
 * Optional ports: KOSPAL_LAN_PORT (8000), KOSPAL_LAN_VITE_PORT (5173)
 */
import { execSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import concurrently from 'concurrently';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const APP_PORT = Number(process.env.KOSPAL_LAN_PORT || 8000);
const VITE_PORT = Number(process.env.KOSPAL_LAN_VITE_PORT || 5173);

const SKIP_IFACE = /virtualbox|vmware|hyper-v|vethernet|wsl|docker|loopback|bluetooth|tailscale|zerotier|pseudo|isatap|teredo/i;

function isIPv4(value) {
    return /^\d{1,3}(?:\.\d{1,3}){3}$/.test(value);
}

function isUsableLanIPv4(ip) {
    return (
        isIPv4(ip) &&
        !ip.startsWith('127.') &&
        !ip.startsWith('169.254.') &&
        ip !== '0.0.0.0'
    );
}

function ipv4FromOs() {
    const found = [];

    for (const [name, addrs] of Object.entries(os.networkInterfaces())) {
        if (!addrs || SKIP_IFACE.test(name)) {
            continue;
        }

        for (const addr of addrs) {
            const family = addr.family === 4 || addr.family === 'IPv4';
            if (!family || addr.internal || !isUsableLanIPv4(addr.address)) {
                continue;
            }

            found.push({ name, ip: addr.address });
        }
    }

    const rank = (ip) => {
        if (ip.startsWith('192.168.')) {
            return 0;
        }
        if (ip.startsWith('10.')) {
            return 1;
        }
        const [a, b] = ip.split('.').map(Number);
        if (a === 172 && b >= 16 && b <= 31) {
            return 2;
        }

        return 3;
    };

    found.sort((x, y) => rank(x.ip) - rank(y.ip));

    return found;
}

function ipv4FromWindowsDefaultRoute() {
    if (process.platform !== 'win32') {
        return null;
    }

    const script = `
$ProgressPreference = 'SilentlyContinue'
$ErrorActionPreference = 'Stop'
$route = Get-NetRoute -AddressFamily IPv4 -DestinationPrefix '0.0.0.0/0' |
  Sort-Object -Property RouteMetric, InterfaceMetric |
  Select-Object -First 1
if (-not $route) { exit 1 }
$ip = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $route.InterfaceIndex |
  Where-Object { $_.IPAddress -notmatch '^127\\.' -and $_.IPAddress -notmatch '^169\\.254\\.' } |
  Select-Object -First 1 -ExpandProperty IPAddress
if (-not $ip) { exit 1 }
[Console]::Out.Write($ip)
`.trim();

    try {
        const encoded = Buffer.from(script, 'utf16le').toString('base64');
        const out = execSync(
            `powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ${encoded}`,
            {
                encoding: 'utf8',
                timeout: 8000,
                windowsHide: true,
                stdio: ['ignore', 'pipe', 'ignore'],
            },
        ).trim();

        return isUsableLanIPv4(out) ? out : null;
    } catch {
        return null;
    }
}

function resolveLanHost() {
    const override = process.env.KOSPAL_LAN_HOST?.trim();
    if (override && isUsableLanIPv4(override)) {
        return override;
    }

    return ipv4FromWindowsDefaultRoute() ?? ipv4FromOs()[0]?.ip ?? null;
}

function printBanner(lanHost, others) {
    const url = lanHost ? `http://${lanHost}:${APP_PORT}` : `http://<PC-LAN-IP>:${APP_PORT}`;

    console.log('');
    console.log('KOSPAL LAN dev (local Wi-Fi only — not for production / Play Store / cloud)');
    console.log('─────────────────────────────────────────────────────────────────────────');
    console.log(`  On a phone or tablet on the same Wi-Fi, open Chrome:`);
    console.log(`    ${url}`);
    console.log('  Use http://  —  not https://  (this server has no TLS).');
    console.log('  "Unsupported SSL request" means the phone asked for https://; go back to http://');
    console.log('  Home-screen "Add to Home screen" on this URL still opens in Chrome.');
    console.log('  Real "Install app" (no address bar) needs a trusted https:// URL.');
    console.log('');
    console.log('  Find this PC LAN IPv4 on Windows:');
    console.log('    ipconfig');
    console.log('    Look under "Wireless LAN adapter Wi-Fi" (or Ethernet) → IPv4 Address');
    console.log('    Ignore 127.0.0.1 and 169.254.x.x');
    console.log('');
    if (others.length > 0) {
        console.log('  Other IPv4s on this PC (try one if the URL above is a VM/VPN adapter):');
        for (const { name, ip } of others) {
            console.log(`    ${ip}    (${name})`);
        }
        console.log('');
    }
    console.log('  Windows Firewall: if the phone cannot connect, allow inbound TCP');
    console.log(`    ${APP_PORT} (php.exe) and ${VITE_PORT} (node.exe) on a Private network.`);
    console.log('  Stop desktop:serve / tauri:dev first if those ports are already in use.');
    console.log('  Override detected IP:  set KOSPAL_LAN_HOST=192.168.x.x   then re-run.');
    console.log('─────────────────────────────────────────────────────────────────────────');
    console.log(`  Laravel  0.0.0.0:${APP_PORT}`);
    console.log(`  Vite     0.0.0.0:${VITE_PORT}  (HMR host ${lanHost ?? 'set KOSPAL_LAN_HOST'})`);
    console.log('');
}

const others = ipv4FromOs();
const lanHost = resolveLanHost();

if (!lanHost) {
    console.error('Could not auto-detect a LAN IPv4.');
    console.error('Run ipconfig, then: set KOSPAL_LAN_HOST=<IPv4> && npm run dev:lan');
    process.exit(1);
}

printBanner(
    lanHost,
    others.filter((row) => row.ip !== lanHost),
);

try {
    execSync('php artisan config:clear', { cwd: root, stdio: 'ignore' });
} catch {
    // Config cache may already be empty; artisan serve still starts.
}

const binDir = path.join(root, 'node_modules', '.bin');
const existingPath = process.env.PATH ?? process.env.Path ?? '';
const lanEnv = {
    ...process.env,
    APP_URL: `http://${lanHost}:${APP_PORT}`,
    KOSPAL_LAN_HOST: lanHost,
    SESSION_SECURE_COOKIE: 'false',
    KOSPAL_LAN_PORT: String(APP_PORT),
    KOSPAL_LAN_VITE_PORT: String(VITE_PORT),
    PATH: `${binDir}${path.delimiter}${existingPath}`,
    Path: `${binDir}${path.delimiter}${existingPath}`,
};

try {
    const { result } = concurrently(
        [
            {
                name: 'server',
                command: `php artisan serve --host=0.0.0.0 --port=${APP_PORT}`,
                prefixColor: 'blue',
                env: lanEnv,
                cwd: root,
            },
            {
                name: 'vite',
                command: `vite --host=0.0.0.0 --port=${VITE_PORT}`,
                prefixColor: 'magenta',
                env: lanEnv,
                cwd: root,
            },
        ],
        {
            killOthersOn: ['failure', 'success'],
            cwd: root,
            env: lanEnv,
            prefixColors: ['blue', 'magenta'],
        },
    );

    await result;
} catch {
    process.exit(1);
}
