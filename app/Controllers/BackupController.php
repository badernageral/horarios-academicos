<?php

namespace App\Controllers;

class BackupController extends BaseController
{
    // ── Página: exportar / importar ───────────────────────────────
    public function index(): void
    {
        $dir     = ROOT_PATH . '/backups';
        $arquivos = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.sql') ?: [] as $f) {
                $arquivos[] = ['nome' => basename($f), 'tamanho' => filesize($f), 'data' => filemtime($f)];
            }
            // mais recentes primeiro
            usort($arquivos, fn($a, $b) => $b['data'] <=> $a['data']);
            $arquivos = array_slice($arquivos, 0, 10);
        }
        $flash = $this->getFlash();
        $this->render('backup/index', compact('arquivos', 'flash'));
    }

    // ── Exportar: gera o dump e envia para download ───────────────
    public function exportar(): void
    {
        $cfg = require ROOT_PATH . '/config/database.php';
        $dir = ROOT_PATH . '/backups';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $arquivo = $dir . '/sga_backup_' . date('Ymd_His') . '.sql';
        if (!$this->dump($cfg, $arquivo)) {
            @unlink($arquivo);
            $this->flash('danger', 'Falha ao gerar o backup. Verifique se o mysqldump está disponível.');
            $this->redirect('/backup');
            return;
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
        header('Content-Length: ' . filesize($arquivo));
        readfile($arquivo);
        exit;
    }

    // ── Importar: restaura o banco a partir de um .sql enviado ────
    public function importar(): void
    {
        $up = $_FILES['arquivo'] ?? null;
        if (!$up || $up['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Selecione um arquivo .sql válido para importar.');
            $this->redirect('/backup');
            return;
        }
        if (!preg_match('/\.sql$/i', (string)$up['name'])) {
            $this->flash('danger', 'O arquivo deve ter a extensão .sql.');
            $this->redirect('/backup');
            return;
        }

        $cfg = require ROOT_PATH . '/config/database.php';
        $dir = ROOT_PATH . '/backups';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // Rede de segurança: salva o estado atual antes de sobrescrever.
        $seguranca = $dir . '/pre_import_' . date('Ymd_His') . '.sql';
        $this->dump($cfg, $seguranca);

        // Restaura: mysql lê o dump (DROP/CREATE/INSERT) na base atual.
        // Senha via ambiente (cross-platform; o filho herda MYSQL_PWD).
        putenv('MYSQL_PWD=' . $cfg['password']);
        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s %s < %s 2>&1',
            escapeshellarg($cfg['host']),
            escapeshellarg($cfg['port']),
            escapeshellarg($cfg['user']),
            escapeshellarg($cfg['dbname']),
            escapeshellarg($up['tmp_name'])
        );
        exec($cmd, $out, $code);

        if ($code !== 0) {
            $this->flash('danger', 'Falha ao importar o backup: ' . htmlspecialchars(implode(' ', array_slice($out, 0, 3))));
            $this->redirect('/backup');
            return;
        }

        $this->flash('success', 'Backup importado com sucesso. O estado anterior foi salvo em backups/' . basename($seguranca) . '.');
        $this->redirect('/backup');
    }

    // ── Helper: dump completo do banco para um arquivo ────────────
    private function dump(array $cfg, string $arquivo): bool
    {
        // Senha via ambiente (cross-platform; evita expor no comando).
        putenv('MYSQL_PWD=' . $cfg['password']);
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction %s > %s 2>%s',
            escapeshellarg($cfg['host']),
            escapeshellarg($cfg['port']),
            escapeshellarg($cfg['user']),
            escapeshellarg($cfg['dbname']),
            escapeshellarg($arquivo),
            $null
        );
        exec($cmd, $out, $code);
        return $code === 0 && is_file($arquivo) && filesize($arquivo) > 0;
    }
}
