/**
 * Horários Acadêmicos — orquestrador Electron desktop (Windows), backend SQLite.
 *
 * Sem servidor de banco: o SQLite é um arquivo em %APPDATA%. Ao iniciar:
 *   1. na 1ª execução, cria o banco a partir do schema.sql (via PHP/pdo_sqlite);
 *   2. aplica migrations pendentes;
 *   3. sobe o `php -S` servindo o SGA na raiz e abre a janela.
 * Ao fechar: mata o processo do PHP (o SQLite não precisa de shutdown).
 */
const { app, BrowserWindow, dialog } = require('electron');
const { spawn, spawnSync } = require('child_process');
const net  = require('net');
const path = require('path');
const fs   = require('fs');

// ── Layout (dev vs. empacotado) ───────────────────────────────────
const PACKAGED = app.isPackaged;
const ROOT     = PACKAGED ? process.resourcesPath : __dirname;
const APP_DIR  = PACKAGED ? path.join(ROOT, 'app') : path.join(__dirname, '..'); // raiz do SGA
const ROUTER   = path.join(ROOT, 'router.php');
const PHP_DIR  = path.join(ROOT, 'runtime', 'php');
const PHP_EXE  = path.join(PHP_DIR, 'php.exe');
const PHP_INI  = path.join(PHP_DIR, 'php.ini');
const SCHEMA   = path.join(APP_DIR, 'database', 'schema.sql');
const MIGRATE  = path.join(APP_DIR, 'database', 'migrate.php');

// Banco do usuário (gravável), preservado entre atualizações.
const DB_PATH  = path.join(app.getPath('userData'), 'sga.sqlite');

let phpProc = null;

// ── Utilidades ────────────────────────────────────────────────────
function freePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.unref();
    srv.on('error', reject);
    srv.listen(0, '127.0.0.1', () => {
      const port = srv.address().port;
      srv.close(() => resolve(port));
    });
  });
}

function waitForPort(port, timeoutMs = 10000) {
  const deadline = Date.now() + timeoutMs;
  return new Promise((resolve, reject) => {
    (function attempt() {
      const sock = net.connect(port, '127.0.0.1');
      sock.on('connect', () => { sock.destroy(); resolve(); });
      sock.on('error', () => {
        sock.destroy();
        if (Date.now() > deadline) return reject(new Error('PHP não respondeu na porta ' + port));
        setTimeout(attempt, 250);
      });
    })();
  });
}

function phpEnv(extra = {}) {
  return {
    ...process.env,
    SGA_BASE_PATH:  '',           // servir na raiz
    DB_DRIVER:      'sqlite',
    DB_SQLITE_PATH: DB_PATH,
    ...extra,
  };
}

// ── Banco ─────────────────────────────────────────────────────────
function ensureDatabase() {
  if (fs.existsSync(DB_PATH)) return false; // já criado
  fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
  // Cria a estrutura a partir do schema.sql (pdo_sqlite executa vários comandos).
  const boot = "$p=new PDO('sqlite:'.getenv('DB_SQLITE_PATH'));"
             + "$p->exec('PRAGMA foreign_keys=ON');"
             + "$p->exec(file_get_contents(getenv('SGA_SCHEMA')));";
  const r = spawnSync(PHP_EXE, ['-c', PHP_INI, '-r', boot],
    { env: phpEnv({ SGA_SCHEMA: SCHEMA }) });
  if (r.status !== 0) {
    throw new Error('Falha ao criar o banco: ' + (r.stderr ? r.stderr.toString() : ''));
  }
  return true;
}

function runMigrations(mode) {
  const r = spawnSync(PHP_EXE, ['-c', PHP_INI, MIGRATE, mode],
    { cwd: PHP_DIR, env: phpEnv(), stdio: 'inherit' });
  if (r.status !== 0) throw new Error('Falha ao aplicar migrations (' + mode + ')');
}

async function startPhp() {
  const port = await freePort();
  phpProc = spawn(PHP_EXE, ['-c', PHP_INI, '-S', `127.0.0.1:${port}`, '-t', APP_DIR, ROUTER],
    { cwd: PHP_DIR, env: phpEnv() });
  phpProc.stderr.on('data', d => console.log('[php]', d.toString().trim()));
  return port;
}

// ── Ciclo de vida ─────────────────────────────────────────────────
app.whenReady().then(async () => {
  try {
    const firstRun = ensureDatabase();
    if (firstRun) runMigrations('baseline'); // banco novo já tem o schema atual
    runMigrations('up');                     // aplica pendentes (atualização in-place)

    const port = await startPhp();
    await waitForPort(port);

    const win = new BrowserWindow({
      width: 1280, height: 840,
      title: 'Horários Acadêmicos',
      webPreferences: { contextIsolation: true },
    });
    win.setMenuBarVisibility(false);
    win.loadURL(`http://127.0.0.1:${port}/`);
  } catch (e) {
    dialog.showErrorBox('Horários Acadêmicos — erro ao iniciar', String(e && e.message || e));
    app.quit();
  }
});

function shutdown() { try { if (phpProc) phpProc.kill(); } catch (_) {} }
app.on('window-all-closed', () => { shutdown(); app.quit(); });
app.on('before-quit', shutdown);
process.on('exit', shutdown);
