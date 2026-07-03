/**
 * SGA Desktop — orquestrador Electron (Windows).
 *
 * Ao iniciar:
 *   1. escolhe uma porta livre para o MySQL e outra para o PHP;
 *   2. na 1ª execução, cria o datadir do MariaDB em %APPDATA% e carrega o schema;
 *   3. sobe o mysqld (processo filho) apontando para esse datadir;
 *   4. sobe o `php -S` servindo o SGA na raiz (SGA_BASE_PATH='');
 *   5. abre a janela apontando para http://127.0.0.1:<phpPort>/.
 * Ao fechar: derruba o PHP e dá `mysqladmin shutdown` (encerramento limpo).
 *
 * IMPORTANTE: este orquestrador NÃO usa o MySQL do sistema (porta 3306).
 * Ele sobe uma instância própria, isolada, com datadir próprio.
 */
const { app, BrowserWindow } = require('electron');
const { spawn, spawnSync } = require('child_process');
const net  = require('net');
const path = require('path');
const fs   = require('fs');

// ── Layout (dev vs. empacotado) ───────────────────────────────────
// Empacotado: binários e código ficam em resources/. Dev: na pasta do projeto.
const PACKAGED  = app.isPackaged;
const ROOT      = PACKAGED ? process.resourcesPath : __dirname;
const APP_DIR   = PACKAGED ? path.join(ROOT, 'app') : path.join(__dirname, '..'); // raiz do SGA (index.php)
const ROUTER    = path.join(ROOT, 'router.php');   // fora do docroot: nunca é servido como estático
const PHP_DIR   = path.join(ROOT, 'runtime', 'php');
const PHP_EXE   = path.join(PHP_DIR, 'php.exe');
const PHP_INI   = path.join(PHP_DIR, 'php.ini');
const MARIA_DIR = path.join(ROOT, 'runtime', 'mariadb');
const MYSQLD    = path.join(MARIA_DIR, 'bin', 'mysqld.exe');
const MYSQL_CLI = path.join(MARIA_DIR, 'bin', 'mysql.exe');
const INSTALLDB = path.join(MARIA_DIR, 'bin', 'mariadb-install-db.exe'); // MySQL: usar `mysqld --initialize-insecure`
const MYSQLADM  = path.join(MARIA_DIR, 'bin', 'mysqladmin.exe');
const SCHEMA    = path.join(APP_DIR, 'database', 'schema.sql');

// Dados do usuário (graváveis) — nunca em Program Files.
const DATA_DIR  = path.join(app.getPath('userData'), 'mysql-data');
const DB_NAME   = 'horarios_academicos';

let mysqldProc = null;
let phpProc    = null;
let mysqlPort  = 0;

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

function waitForPort(port, timeoutMs = 20000) {
  const deadline = Date.now() + timeoutMs;
  return new Promise((resolve, reject) => {
    (function attempt() {
      const sock = net.connect(port, '127.0.0.1');
      sock.on('connect', () => { sock.destroy(); resolve(); });
      sock.on('error', () => {
        sock.destroy();
        if (Date.now() > deadline) return reject(new Error('Serviço não respondeu a tempo na porta ' + port));
        setTimeout(attempt, 300);
      });
    })();
  });
}

// ── Banco: primeira execução ──────────────────────────────────────
function initDatabaseIfNeeded() {
  if (fs.existsSync(path.join(DATA_DIR, 'mysql')) || fs.existsSync(path.join(DATA_DIR, 'ibdata1'))) {
    return false; // já inicializado
  }
  fs.mkdirSync(DATA_DIR, { recursive: true });
  // MariaDB no Windows: cria o datadir com root sem senha (padrão).
  // --basedir permite ao inicializador achar os templates em share/.
  // (não use --auth-root-authentication-method: é opção só de Unix.)
  const r = spawnSync(INSTALLDB, [
    `--datadir=${DATA_DIR}`,
    `--basedir=${MARIA_DIR}`,
  ], { cwd: MARIA_DIR, stdio: 'inherit' });
  if (r.status !== 0) throw new Error('Falha ao inicializar o datadir do MariaDB');
  return true;
}

function loadSchema() {
  // Cria o banco e carrega o schema.sql (tabelas + admin/admin + configs).
  spawnSync(MYSQL_CLI, ['--host=127.0.0.1', `--port=${mysqlPort}`, '-u', 'root', '-e',
    `CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4;`], { stdio: 'inherit' });
  const sql = fs.readFileSync(SCHEMA);
  const p = spawnSync(MYSQL_CLI, ['--host=127.0.0.1', `--port=${mysqlPort}`, '-u', 'root', DB_NAME],
    { input: sql, stdio: ['pipe', 'inherit', 'inherit'] });
  if (p.status !== 0) throw new Error('Falha ao carregar o schema.sql');
}

// ── Processos ─────────────────────────────────────────────────────
async function startMysql() {
  const firstRun = initDatabaseIfNeeded();
  mysqlPort = await freePort();
  mysqldProc = spawn(MYSQLD, [
    `--datadir=${DATA_DIR}`,
    `--port=${mysqlPort}`,
    '--bind-address=127.0.0.1',
  ], { cwd: MARIA_DIR });
  mysqldProc.stderr.on('data', d => console.log('[mysqld]', d.toString().trim()));
  await waitForPort(mysqlPort);
  if (firstRun) loadSchema();
}

async function startPhp() {
  const phpPort = await freePort();
  phpProc = spawn(PHP_EXE, [
    '-c', PHP_INI,
    '-S', `127.0.0.1:${phpPort}`,
    '-t', APP_DIR,
    ROUTER,
  ], {
    cwd: PHP_DIR, // resolve extension_dir="ext" do php.ini
    env: {
      ...process.env,
      // mysqldump/mysql (backup) resolvidos a partir dos binários do MariaDB.
      PATH: path.join(MARIA_DIR, 'bin') + path.delimiter + (process.env.PATH || ''),
      SGA_BASE_PATH: '',            // servir na raiz
      DB_HOST: '127.0.0.1',
      DB_PORT: String(mysqlPort),
      DB_NAME,
      DB_USER: 'root',
      DB_PASS: '',
    },
  });
  phpProc.stderr.on('data', d => console.log('[php]', d.toString().trim()));
  return phpPort;
}

// ── Encerramento limpo ────────────────────────────────────────────
function shutdown() {
  try { if (phpProc) phpProc.kill(); } catch (_) {}
  try {
    if (mysqldProc) {
      // mysqladmin shutdown garante flush do InnoDB (evita corrupção).
      spawnSync(MYSQLADM, ['--host=127.0.0.1', `--port=${mysqlPort}`, '-u', 'root', 'shutdown']);
    }
  } catch (_) {}
}

// ── Ciclo de vida do Electron ─────────────────────────────────────
app.whenReady().then(async () => {
  try {
    await startMysql();
    const phpPort = await startPhp();
    await waitForPort(phpPort, 10000);

    const win = new BrowserWindow({
      width: 1280, height: 840,
      title: 'SGA — Horários Acadêmicos',
      webPreferences: { contextIsolation: true },
    });
    win.setMenuBarVisibility(false);
    win.loadURL(`http://127.0.0.1:${phpPort}/`);
  } catch (e) {
    console.error('Falha ao iniciar o SGA Desktop:', e);
    app.quit();
  }
});

app.on('window-all-closed', () => { shutdown(); app.quit(); });
app.on('before-quit', shutdown);
process.on('exit', shutdown);
