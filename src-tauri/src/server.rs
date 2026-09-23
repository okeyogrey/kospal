use std::{
    fs,
    io::{Read, Write},
    net::{TcpListener, TcpStream},
    path::{Path, PathBuf},
    process::{Child, Command, Stdio},
    sync::Mutex,
    thread,
    time::{Duration, Instant},
};

#[cfg(windows)]
use std::os::windows::process::CommandExt;

use tauri::{AppHandle, Manager, Url};

/// Hide the console window that Windows creates for console-subsystem binaries like php.exe.
#[cfg(windows)]
fn hide_console(cmd: &mut Command) -> &mut Command {
    const CREATE_NO_WINDOW: u32 = 0x0800_0000;
    cmd.creation_flags(CREATE_NO_WINDOW)
}

#[cfg(not(windows))]
fn hide_console(cmd: &mut Command) -> &mut Command {
    cmd
}

pub struct PhpServer {
    child: Child,
    scheduler: Option<Child>,
    pub url: String,
}

impl Drop for PhpServer {
    fn drop(&mut self) {
        let _ = self.child.kill();
        let _ = self.child.wait();

        if let Some(scheduler) = self.scheduler.as_mut() {
            let _ = scheduler.kill();
            let _ = scheduler.wait();
        }
    }
}

pub type ManagedPhpServer = Mutex<Option<PhpServer>>;

pub fn start(app: &AppHandle) -> Result<PhpServer, String> {
    let resource_dir = app
        .path()
        .resource_dir()
        .map_err(|e| format!("resource dir: {e}"))?;

    let php_exe = resolve_php(&resource_dir)?;
    let app_root = prepare_app_root(app, &resource_dir)?;

    ensure_initialized(&php_exe, &app_root)?;

    let port = free_port()?;
    let url = format!("http://127.0.0.1:{port}");

    write_runtime_env(&app_root, &url)?;

    let mut serve = Command::new(&php_exe);
    hide_console(&mut serve);
    let mut child = serve
        .current_dir(&app_root)
        .args([
            "artisan",
            "serve",
            "--host=127.0.0.1",
            &format!("--port={port}"),
        ])
        .env("APP_URL", &url)
        .stdin(Stdio::null())
        .stdout(Stdio::null())
        .stderr(Stdio::null())
        .spawn()
        .map_err(|e| format!("failed to start PHP server ({php_exe:?}): {e}"))?;

    if let Err(err) = wait_until_ready(&url, Duration::from_secs(45)) {
        let _ = child.kill();
        let _ = child.wait();
        return Err(err);
    }

    let mut schedule = Command::new(&php_exe);
    hide_console(&mut schedule);
    let scheduler = schedule
        .current_dir(&app_root)
        .args(["artisan", "schedule:work"])
        .stdin(Stdio::null())
        .stdout(Stdio::null())
        .stderr(Stdio::null())
        .spawn()
        .ok();

    Ok(PhpServer {
        child,
        scheduler,
        url,
    })
}

fn resolve_php(resource_dir: &Path) -> Result<PathBuf, String> {
    let bundled = resource_dir
        .join("resources")
        .join("runtime")
        .join("php")
        .join("php.exe");
    if bundled.is_file() {
        return Ok(bundled);
    }

    // Fallback layout used by some Tauri resource mappings.
    let alt = resource_dir.join("runtime").join("php").join("php.exe");
    if alt.is_file() {
        return Ok(alt);
    }

    Err(format!(
        "Bundled PHP not found. Looked for:\n  {}\n  {}",
        bundled.display(),
        alt.display()
    ))
}

fn prepare_app_root(app: &AppHandle, resource_dir: &Path) -> Result<PathBuf, String> {
    let staged = [
        resource_dir.join("resources").join("app"),
        resource_dir.join("app"),
    ]
    .into_iter()
    .find(|p| p.join("artisan").is_file())
    .ok_or_else(|| {
        format!(
            "Bundled Laravel app not found under {}",
            resource_dir.display()
        )
    })?;

    let data_dir = app
        .path()
        .app_data_dir()
        .map_err(|e| format!("app data dir: {e}"))?;
    fs::create_dir_all(&data_dir).map_err(|e| format!("create app data dir: {e}"))?;

    let runtime_root = data_dir.join("app");
    let version_marker = data_dir.join(".bundle-version");
    let staged_version = fs::read_to_string(staged.join(".desktop-bundle-version"))
        .unwrap_or_else(|_| "0".into())
        .trim()
        .to_string();
    let installed_version = fs::read_to_string(&version_marker)
        .unwrap_or_default()
        .trim()
        .to_string();

    let needs_sync = !runtime_root.join("artisan").is_file() || installed_version != staged_version;
    if needs_sync {
        if runtime_root.exists() {
            // Preserve user SQLite + env across upgrades when possible.
            let preserve_db = runtime_root.join("database").join("database.sqlite");
            let preserve_env = runtime_root.join(".env");
            let tmp_db = data_dir.join("database.sqlite.bak");
            let tmp_env = data_dir.join("env.bak");
            if preserve_db.is_file() {
                let _ = fs::copy(&preserve_db, &tmp_db);
            }
            if preserve_env.is_file() {
                let _ = fs::copy(&preserve_env, &tmp_env);
            }

            fs::remove_dir_all(&runtime_root)
                .map_err(|e| format!("remove old runtime app: {e}"))?;

            copy_dir_recursive(&staged, &runtime_root)?;

            if tmp_db.is_file() {
                let dest = runtime_root.join("database");
                fs::create_dir_all(&dest).ok();
                let _ = fs::copy(&tmp_db, dest.join("database.sqlite"));
                let _ = fs::remove_file(&tmp_db);
            }
            if tmp_env.is_file() {
                let _ = fs::copy(&tmp_env, runtime_root.join(".env"));
                let _ = fs::remove_file(&tmp_env);
            }
        } else {
            copy_dir_recursive(&staged, &runtime_root)?;
        }

        fs::write(&version_marker, &staged_version)
            .map_err(|e| format!("write bundle version: {e}"))?;
    }

    // Ensure writable Laravel dirs exist.
    for rel in [
        "storage/app/public",
        "storage/framework/cache/data",
        "storage/framework/sessions",
        "storage/framework/views",
        "storage/logs",
        "bootstrap/cache",
        "database",
    ] {
        fs::create_dir_all(runtime_root.join(rel))
            .map_err(|e| format!("create {rel}: {e}"))?;
    }

    Ok(runtime_root)
}

fn ensure_initialized(php_exe: &Path, app_root: &Path) -> Result<(), String> {
    let marker = app_root.join(".desktop-initialized");
    if marker.is_file() {
        return Ok(());
    }

    run_artisan(php_exe, app_root, &["key:generate", "--force"])?;
    run_artisan(php_exe, app_root, &["db:prepare", "--force"])?;
    fs::write(&marker, "1").map_err(|e| format!("write init marker: {e}"))?;
    Ok(())
}

fn write_runtime_env(app_root: &Path, url: &str) -> Result<(), String> {
    let env_path = app_root.join(".env");
    let mut contents = if env_path.is_file() {
        fs::read_to_string(&env_path).map_err(|e| format!("read .env: {e}"))?
    } else {
        String::new()
    };

    upsert_env(&mut contents, "APP_URL", url);
    upsert_env(&mut contents, "APP_ENV", "production");
    upsert_env(&mut contents, "APP_DEBUG", "false");
    upsert_env(&mut contents, "KOSPAL_DEPLOYMENT_MODE", "desktop");
    upsert_env(&mut contents, "DB_CONNECTION", "sqlite");

    fs::write(&env_path, contents).map_err(|e| format!("write .env: {e}"))?;
    Ok(())
}

fn upsert_env(contents: &mut String, key: &str, value: &str) {
    let prefix = format!("{key}=");
    let replacement = format!("{key}={value}");
    let mut replaced = false;
    let mut out = String::new();
    for line in contents.lines() {
        if line.starts_with(&prefix) {
            out.push_str(&replacement);
            out.push('\n');
            replaced = true;
        } else {
            out.push_str(line);
            out.push('\n');
        }
    }
    if !replaced {
        out.push_str(&replacement);
        out.push('\n');
    }
    *contents = out;
}

fn run_artisan(php_exe: &Path, app_root: &Path, args: &[&str]) -> Result<(), String> {
    let mut cmd = Command::new(php_exe);
    hide_console(&mut cmd);
    cmd.current_dir(app_root).arg("artisan");
    for arg in args {
        cmd.arg(arg);
    }
    let output = cmd
        .output()
        .map_err(|e| format!("artisan {:?} failed to start: {e}", args))?;
    if !output.status.success() {
        let stdout = String::from_utf8_lossy(&output.stdout);
        let stderr = String::from_utf8_lossy(&output.stderr);
        return Err(format!(
            "artisan {:?} failed:\n{stderr}\n{stdout}",
            args
        ));
    }
    Ok(())
}

fn free_port() -> Result<u16, String> {
    let listener =
        TcpListener::bind("127.0.0.1:0").map_err(|e| format!("bind ephemeral port: {e}"))?;
    let port = listener
        .local_addr()
        .map_err(|e| format!("local_addr: {e}"))?
        .port();
    drop(listener);
    Ok(port)
}

fn wait_until_ready(url: &str, timeout: Duration) -> Result<(), String> {
    let parsed = Url::parse(url).map_err(|e| format!("parse url: {e}"))?;
    let host = parsed.host_str().unwrap_or("127.0.0.1");
    let port = parsed.port().unwrap_or(80);
    let deadline = Instant::now() + timeout;

    while Instant::now() < deadline {
        if http_ok(host, port) {
            return Ok(());
        }
        thread::sleep(Duration::from_millis(250));
    }

    Err(format!(
        "PHP server did not become ready at {url} within {}s",
        timeout.as_secs()
    ))
}

fn http_ok(host: &str, port: u16) -> bool {
    let Ok(mut stream) = TcpStream::connect((host, port)) else {
        return false;
    };
    let _ = stream.set_read_timeout(Some(Duration::from_secs(2)));
    let _ = stream.set_write_timeout(Some(Duration::from_secs(2)));
    let request = format!("GET / HTTP/1.1\r\nHost: {host}:{port}\r\nConnection: close\r\n\r\n");
    if stream.write_all(request.as_bytes()).is_err() {
        return false;
    }
    let mut buf = [0u8; 64];
    match stream.read(&mut buf) {
        Ok(n) if n > 0 => {
            let text = String::from_utf8_lossy(&buf[..n]);
            text.starts_with("HTTP/1.1 200")
                || text.starts_with("HTTP/1.1 302")
                || text.starts_with("HTTP/1.0 200")
                || text.starts_with("HTTP/1.0 302")
                || text.starts_with("HTTP/1.1 301")
                || text.starts_with("HTTP/1.1 303")
                || text.starts_with("HTTP/1.1 307")
                || text.starts_with("HTTP/1.1 308")
                || text.starts_with("HTTP/1.1 401")
                || text.starts_with("HTTP/1.1 403")
                || text.starts_with("HTTP/1.1 419")
                || text.starts_with("HTTP/1.1 500") // server is up; Laravel may still be booting routes
        }
        _ => false,
    }
}

fn copy_dir_recursive(from: &Path, to: &Path) -> Result<(), String> {
    fs::create_dir_all(to).map_err(|e| format!("mkdir {}: {e}", to.display()))?;
    for entry in fs::read_dir(from).map_err(|e| format!("read {}: {e}", from.display()))? {
        let entry = entry.map_err(|e| format!("entry: {e}"))?;
        let ty = entry.file_type().map_err(|e| format!("file_type: {e}"))?;
        let target = to.join(entry.file_name());
        if ty.is_dir() {
            copy_dir_recursive(&entry.path(), &target)?;
        } else if ty.is_file() {
            if let Some(parent) = target.parent() {
                fs::create_dir_all(parent).ok();
            }
            fs::copy(entry.path(), &target)
                .map_err(|e| format!("copy {} -> {}: {e}", entry.path().display(), target.display()))?;
        }
    }
    Ok(())
}
