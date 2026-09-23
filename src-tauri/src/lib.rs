mod server;

use std::sync::Mutex;

use tauri::Manager;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .manage(Mutex::new(None::<server::PhpServer>))
        .setup(|app| {
            if tauri::is_dev() {
                return Ok(());
            }

            match server::start(app.handle()) {
                Ok(php) => {
                    let url = php.url.clone();
                    if let Some(state) = app.try_state::<server::ManagedPhpServer>() {
                        if let Ok(mut guard) = state.lock() {
                            *guard = Some(php);
                        }
                    }

                    if let Some(window) = app.get_webview_window("main") {
                        let parsed = url
                            .parse()
                            .map_err(|e| format!("invalid server url {url}: {e}"))?;
                        window
                            .navigate(parsed)
                            .map_err(|e| format!("navigate to {url}: {e}"))?;
                    }
                }
                Err(err) => {
                    eprintln!("KOSPAL desktop server failed: {err}");
                    if let Ok(data_dir) = app.path().app_data_dir() {
                        let _ = std::fs::create_dir_all(&data_dir);
                        let _ = std::fs::write(data_dir.join("startup-error.log"), &err);
                    }
                    if let Some(window) = app.get_webview_window("main") {
                        let html = format!(
                            "<!doctype html><html><body style=\"font-family:Segoe UI,sans-serif;\
                             background:#0c0f14;color:#f5f5f5;padding:2rem\">\
                             <h1>KOSPAL failed to start</h1><pre style=\"white-space:pre-wrap\">{}</pre>\
                             </body></html>",
                            html_escape(&err)
                        );
                        let data_url = format!(
                            "data:text/html;charset=utf-8,{}",
                            urlencoding_encode(&html)
                        );
                        if let Ok(parsed) = data_url.parse() {
                            let _ = window.navigate(parsed);
                        }
                    }
                }
            }

            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running KOSPAL");
}

fn html_escape(input: &str) -> String {
    input
        .replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
}

fn urlencoding_encode(input: &str) -> String {
    let mut out = String::with_capacity(input.len() * 3);
    for b in input.bytes() {
        match b {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => {
                out.push(b as char)
            }
            _ => out.push_str(&format!("%{b:02X}")),
        }
    }
    out
}
