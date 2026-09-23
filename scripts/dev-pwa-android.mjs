/**
 * Serve KOSPAL on 127.0.0.1:8088 (avoids Grey Library on :8000) and
 * reverse that port to a connected Android device so Chrome can Install app.
 *
 * Requires: USB or wireless debugging with `adb devices` showing "device".
 * Keep `npm run dev:lan` running if you want Vite HMR on the phone.
 *
 * Usage: npm run pwa:android
 */
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PORT = Number(process.env.KOSPAL_PWA_PORT || 8088);
const APP_URL = `http://127.0.0.1:${PORT}`;
const adb = path.join(
    process.env.LOCALAPPDATA || '',
    'Android',
    'Sdk',
    'platform-tools',
    os.platform() === 'win32' ? 'adb.exe' : 'adb',
);

function run(command, args, opts = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, {
            cwd: root,
            stdio: ['ignore', 'pipe', 'pipe'],
            ...opts,
        });
        let out = '';
        child.stdout.on('data', (chunk) => {
            out += chunk.toString();
        });
        child.stderr.on('data', (chunk) => {
            out += chunk.toString();
        });
        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve(out.trim());
            } else {
                reject(new Error(`${command} ${args.join(' ')} failed (${code}): ${out}`));
            }
        });
    });
}

function print(message) {
    console.log(message);
}

const devices = await run(adb, ['devices']).catch(() => '');
const online = devices
    .split(/\r?\n/)
    .filter((line) => /\tdevice$/.test(line))
    .map((line) => line.split('\t')[0]);

if (online.length === 0) {
    console.error('No Android device is online (`adb devices` must show "device").');
    console.error('Enable USB or wireless debugging, then retry: npm run pwa:android');
    process.exit(1);
}

print(`Android device: ${online.join(', ')}`);
await run(adb, ['reverse', `tcp:${PORT}`, `tcp:${PORT}`]);
print(`adb reverse tcp:${PORT} -> tcp:${PORT}`);

const phpEnv = {
    ...process.env,
    APP_URL,
    SESSION_SECURE_COOKIE: 'false',
};

print('');
print('KOSPAL phone install (localhost = Chrome can Install app)');
print('────────────────────────────────────────────────────────');
print(`  On the phone, Chrome →  ${APP_URL}`);
print('  Menu ⋮ → Install app  (not Add to Home screen)');
print('  Remove any old KOSPAL shortcut first.');
print('  Leave this process running.');
print('────────────────────────────────────────────────────────');
print('');

const server = spawn(
    'php',
    ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`],
    { cwd: root, env: phpEnv, stdio: 'inherit', shell: true },
);

server.on('exit', (code) => {
    process.exit(code ?? 1);
});

try {
    await run(adb, [
        'shell',
        'am',
        'start',
        '-a',
        'android.intent.action.VIEW',
        '-d',
        APP_URL,
        'com.android.chrome',
    ]);
} catch {
    print(`Open Chrome on the phone to ${APP_URL}`);
}
