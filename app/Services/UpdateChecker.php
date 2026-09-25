<?php

namespace App\Services;

/**
 * Verifica se há uma versão mais nova publicada no GitHub (tag/release do
 * repositório), no máximo 1x por dia. O resultado da consulta HTTP fica em
 * cache num arquivo em database/, para não bater na API do GitHub — nem
 * segurar a abertura do sistema por uma chamada de rede — em toda requisição.
 */
class UpdateChecker
{
    private const REPO      = 'badernageral/horarios-academicos';
    private const INTERVALO       = 86400; // 1 dia
    private const INTERVALO_FALHA = 3600;  // falha (sem rede, API fora): tenta de novo em 1h

    // Só quando há atualização disponível — usado no aviso discreto do menu/topbar.
    public static function verificar(): ?array
    {
        $s = self::status();

        if ($s['tag'] === null || $s['atualizado']) {
            return null;
        }

        return [
            'versao_atual'      => $s['versao_atual'],
            'versao_disponivel' => $s['versao_disponivel'],
            'url'               => $s['url_release'],
        ];
    }

    // Status completo (atualizado ou não, com falha ou sem) — usado na tela /atualizacoes.
    public static function status(): array
    {
        $versaoAtual        = (string) (require ROOT_PATH . '/config/app.php')['version'];
        [$tag, $verificadoEm] = self::tagMaisRecente();
        $versaoDisponivel    = $tag !== null ? ltrim($tag, 'vV') : null;

        return [
            'versao_atual'      => $versaoAtual,
            'versao_disponivel' => $versaoDisponivel,
            'tag'               => $tag,
            'atualizado'        => $tag !== null ? version_compare($versaoDisponivel, $versaoAtual, '<=') : null,
            'verificado_em'     => $verificadoEm,
            'url_release'       => $tag !== null ? 'https://github.com/' . self::REPO . '/releases/tag/' . $tag : null,
            'url_releases'      => 'https://github.com/' . self::REPO . '/releases',
        ];
    }

    // Retorna [tag, verificadoEm]. Consulta o GitHub só se o cache tiver mais de 1 dia.
    private static function tagMaisRecente(): array
    {
        $cache = self::lerCache();

        $intervalo = ($cache['tag'] ?? null) !== null ? self::INTERVALO : self::INTERVALO_FALHA;

        if ($cache !== null && (time() - $cache['verificado_em']) < $intervalo) {
            return [$cache['tag'], $cache['verificado_em']];
        }

        $tag = self::consultarGithub();

        // Grava mesmo em falha (tag null), para não tentar de novo a cada
        // requisição enquanto a API do GitHub estiver fora ou sem rede.
        self::salvarCache($tag);

        return [$tag, time()];
    }

    private static function consultarGithub(): ?string
    {
        $url       = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
        $cabecalho = [
            'User-Agent: horarios-academicos-update-checker',
            'Accept: application/vnd.github+json',
        ];

        // cURL quando existir; senão, stream HTTP nativo do PHP (precisa só de
        // allow_url_fopen + openssl). Nem todo PHP tem a extensão curl — o do
        // servidor de desenvolvimento, por exemplo, não tem.
        $resposta = function_exists('curl_init')
            ? self::baixarCurl($url, $cabecalho)
            : self::baixarStream($url, $cabecalho);

        if ($resposta === null) {
            return null;
        }

        $dados = json_decode($resposta, true);

        return is_array($dados) && !empty($dados['tag_name']) ? (string) $dados['tag_name'] : null;
    }

    private static function baixarCurl(string $url, array $cabecalho): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => $cabecalho,
        ]);
        $resposta = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $resposta === false || $status !== 200 ? null : (string) $resposta;
    }

    private static function baixarStream(string $url, array $cabecalho): ?string
    {
        if (!ini_get('allow_url_fopen') || !extension_loaded('openssl')) {
            return null;
        }

        $ctx = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $cabecalho),
            'timeout'       => 4,
            'ignore_errors' => true, // lê o corpo mesmo em 4xx/5xx, para checar o status abaixo
        ]]);

        $resposta = @file_get_contents($url, false, $ctx);

        // A primeira linha dos cabeçalhos traz o status ("HTTP/1.1 200 OK").
        $cabecalhos = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        $status = isset($cabecalhos[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $cabecalhos[0], $m)
            ? (int) $m[1] : 0;

        return $resposta === false || $status !== 200 ? null : $resposta;
    }

    private static function cacheFile(): string
    {
        return ROOT_PATH . '/database/update_check.json';
    }

    private static function lerCache(): ?array
    {
        if (!is_file(self::cacheFile())) {
            return null;
        }

        $dados = json_decode((string) file_get_contents(self::cacheFile()), true);

        return is_array($dados) && isset($dados['verificado_em']) ? $dados : null;
    }

    private static function salvarCache(?string $tag): void
    {
        @file_put_contents(self::cacheFile(), json_encode([
            'verificado_em' => time(),
            'tag'           => $tag,
        ]));
    }
}
