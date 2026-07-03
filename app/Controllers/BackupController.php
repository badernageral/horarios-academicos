<?php

namespace App\Controllers;

use PDO;

class BackupController extends BaseController
{
    // Caminho do arquivo SQLite atual (via config).
    private function dbPath(): string
    {
        $cfg = require ROOT_PATH . '/config/database.php';
        return $cfg['path'] ?? (ROOT_PATH . '/database/sga.sqlite');
    }

    // ── Página: exportar / importar ───────────────────────────────
    public function index(): void
    {
        $dir      = ROOT_PATH . '/backups';
        $arquivos = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.sqlite') ?: [] as $f) {
                $arquivos[] = ['nome' => basename($f), 'tamanho' => filesize($f), 'data' => filemtime($f)];
            }
            usort($arquivos, fn($a, $b) => $b['data'] <=> $a['data']);
            $arquivos = array_slice($arquivos, 0, 10);
        }
        $flash = $this->getFlash();
        $this->render('backup/index', compact('arquivos', 'flash'));
    }

    // ── Exportar: snapshot consistente do .sqlite e download ──────
    public function exportar(): void
    {
        $dir = ROOT_PATH . '/backups';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $arquivo = $dir . '/sga_backup_' . date('Ymd_His') . '.sqlite';

        if (!$this->snapshot($this->dbPath(), $arquivo)) {
            @unlink($arquivo);
            $this->flash('danger', 'Falha ao gerar o backup do banco.');
            $this->redirect('/backup');
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
        header('Content-Length: ' . filesize($arquivo));
        readfile($arquivo);
        exit;
    }

    // ── Importar: substitui o banco por um .sqlite enviado ────────
    public function importar(): void
    {
        $up = $_FILES['arquivo'] ?? null;
        if (!$up || $up['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Selecione um arquivo .sqlite válido para importar.');
            $this->redirect('/backup');
            return;
        }
        if (!preg_match('/\.sqlite$/i', (string)$up['name'])) {
            $this->flash('danger', 'O arquivo deve ter a extensão .sqlite.');
            $this->redirect('/backup');
            return;
        }

        // Valida que é um banco SQLite íntegro antes de tocar no atual.
        try {
            $t  = new PDO('sqlite:' . $up['tmp_name']);
            $ic = $t->query('PRAGMA integrity_check')->fetchColumn();
            $t  = null;
            if ($ic !== 'ok') throw new \RuntimeException('integridade');
        } catch (\Throwable $e) {
            $this->flash('danger', 'O arquivo enviado não é um banco SQLite válido.');
            $this->redirect('/backup');
            return;
        }

        $path = $this->dbPath();
        $dir  = ROOT_PATH . '/backups';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // Rede de segurança: snapshot do estado atual antes de sobrescrever.
        $seguranca = $dir . '/pre_import_' . date('Ymd_His') . '.sqlite';
        $this->snapshot($path, $seguranca);

        // Substitui o arquivo vivo e remove WAL/SHM antigos (senão corrompem o novo).
        if (!copy($up['tmp_name'], $path)) {
            $this->flash('danger', 'Falha ao gravar o banco importado.');
            $this->redirect('/backup');
            return;
        }
        @unlink($path . '-wal');
        @unlink($path . '-shm');

        $this->flash('success', 'Backup importado com sucesso. O estado anterior foi salvo em backups/' . basename($seguranca) . '.');
        $this->redirect('/backup');
    }

    // ── Helper: snapshot consistente do banco (VACUUM INTO) ───────
    private function snapshot(string $origem, string $destino): bool
    {
        try {
            $pdo = new PDO('sqlite:' . $origem);
            $pdo->exec('VACUUM INTO ' . $pdo->quote($destino));
            $pdo = null;
        } catch (\Throwable $e) {
            return false;
        }
        return is_file($destino) && filesize($destino) > 0;
    }
}
