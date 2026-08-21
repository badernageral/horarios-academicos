<?php

namespace App\Core;

use PDO;

/**
 * Aplicação de migrations, compartilhada pelo CLI (database/migrate.php) e pela
 * tela de login (atualização automática ao abrir o sistema).
 *
 * REGRA DE SEGURANÇA: só aplica automaticamente quando `schema_migrations`
 * existe, isto é, quando o banco já está sob controle de migrations. Num banco
 * criado do schema.sql sem baseline, rodar as pendentes é DESTRUTIVO — a 001,
 * por exemplo, faz DROP TABLE disponibilidade_professor. Nesse caso quem decide
 * é o operador, com `php database/migrate.php baseline`.
 */
class Migrator
{
    private const LOCK = 'migrate.lock';

    /**
     * Verificação de bootstrap, chamada em TODA requisição (index.php).
     *
     * O gatilho não pode ser uma tela específica: quem já está com sessão
     * aberta quando o código é atualizado passaria direto do login e rodaria
     * código novo contra schema velho. Aqui vale para qualquer requisição,
     * como o main.js do desktop faz a cada abertura do app.
     *
     * O custo por requisição é um filemtime() no diretório de migrations. Só
     * quando essa data muda — ou seja, num deploy — é que o banco é consultado.
     *
     * @return array{aplicadas:string[], erro:?string, precisaBaseline:bool}
     */
    public static function verificarNoBoot(): array
    {
        $nada = ['aplicadas' => [], 'erro' => null, 'precisaBaseline' => false];

        $dir = ROOT_PATH . '/database/migrations';
        if (!is_dir($dir)) return $nada;

        // Guarda barata: nada mudou desde a última verificação desta sessão.
        $carimbo = (string) @filemtime($dir);
        if (isset($_SESSION['sga_migr_ok']) && $_SESSION['sga_migr_ok'] === $carimbo) {
            return $nada;
        }

        try {
            $pdo = Database::getInstance();

            if (!self::sobControle($pdo)) {
                // Sem `schema_migrations` não dá para saber o que já foi
                // aplicado, e aplicar às cegas é destrutivo (a 001 recria
                // disponibilidade_professor). Quem decide é o operador.
                // Não dá para usar pendentes(): sem a tabela ele devolve vazio.
                // O que importa aqui é se EXISTEM migrations a controlar.
                $precisa = (bool) (glob(ROOT_PATH . '/database/migrations/*.sql') ?: []);
                if (!$precisa) $_SESSION['sga_migr_ok'] = $carimbo;
                return ['aplicadas' => [], 'erro' => null, 'precisaBaseline' => $precisa];
            }

            $r = self::aplicar($pdo);
            if (!$r['erro']) $_SESSION['sga_migr_ok'] = $carimbo;

            return ['aplicadas' => $r['aplicadas'], 'erro' => $r['erro'], 'precisaBaseline' => false];
        } catch (\Throwable $e) {
            return ['aplicadas' => [], 'erro' => $e->getMessage(), 'precisaBaseline' => false];
        }
    }

    /** O banco está sob controle de migrations? */
    public static function sobControle(PDO $pdo): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = ? AND name = ?");
            $st->execute(['table', 'schema_migrations']);
            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;   // MySQL legado ou banco inacessível: não arrisca
        }
    }

    /** @return string[] nomes de arquivo ainda não aplicados, em ordem */
    public static function pendentes(PDO $pdo): array
    {
        $arquivos = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
        sort($arquivos, SORT_STRING);

        try {
            $aplicadas = $pdo->query("SELECT migration FROM schema_migrations")
                             ->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
        $aplicadas = array_flip($aplicadas);

        return array_values(array_filter(
            array_map('basename', $arquivos),
            fn($n) => !isset($aplicadas[$n])
        ));
    }

    /**
     * Aplica as pendentes. Devolve ['aplicadas' => string[], 'erro' => ?string].
     *
     * Um lock de arquivo evita que dois acessos simultâneos à tela de login
     * apliquem a mesma migration duas vezes.
     */
    public static function aplicar(PDO $pdo): array
    {
        $pendentes = self::pendentes($pdo);
        if (!$pendentes) return ['aplicadas' => [], 'erro' => null];

        $lockPath = sys_get_temp_dir() . '/' . self::LOCK;
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) return ['aplicadas' => [], 'erro' => 'Não foi possível criar o lock.'];

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['aplicadas' => [], 'erro' => null];   // outro processo já está aplicando
        }

        $aplicadas = [];
        $erro      = null;

        try {
            // Relê dentro do lock: o outro processo pode ter aplicado nesse meio-tempo
            foreach (self::pendentes($pdo) as $nome) {
                $sql = @file_get_contents(ROOT_PATH . '/database/migrations/' . $nome);
                if ($sql === false) throw new \RuntimeException("não consegui ler {$nome}");

                $pdo->exec($sql);
                $st = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
                $st->execute([$nome]);
                $aplicadas[] = $nome;
            }
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['aplicadas' => $aplicadas, 'erro' => $erro];
    }
}
