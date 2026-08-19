#!/usr/bin/env bash
#
# deploy.sh — verifica e instala o que falta para rodar o Horários Acadêmicos
#             num servidor Debian/Ubuntu (apt), configura o Apache e prepara
#             o banco SQLite.
#
# Uso:
#   sudo ./deploy.sh              # verifica, instala o que falta e configura
#   ./deploy.sh --dry-run         # só diagnostica, não altera nada (dispensa root)
#   sudo ./deploy.sh --yes        # não pergunta nada (apt -y)
#   sudo ./deploy.sh --baseline   # marca as migrations como aplicadas num banco
#                                 # que já está no estado do schema.sql
#
# O script é idempotente: rodar de novo não quebra nada e nunca sobrescreve
# um banco existente.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_NAME="$(basename "$APP_DIR")"
WEB_USER="www-data"
APACHE_CONF="/etc/apache2/conf-available/${APP_NAME}.conf"
PHP_MIN="8.3"

DRY_RUN=0
ASSUME_YES=0
DO_BASELINE=0

# ── Aparência ─────────────────────────────────────────────────────
if [[ -t 1 ]]; then
    C_OK=$'\e[32m'; C_ERR=$'\e[31m'; C_WARN=$'\e[33m'; C_INFO=$'\e[36m'; C_OFF=$'\e[0m'
else
    C_OK=''; C_ERR=''; C_WARN=''; C_INFO=''; C_OFF=''
fi
ok()   { printf '%s  ✔%s %s\n' "$C_OK"   "$C_OFF" "$*"; }
bad()  { printf '%s  ✘%s %s\n' "$C_ERR"  "$C_OFF" "$*"; }
warn() { printf '%s  ⚠%s %s\n' "$C_WARN" "$C_OFF" "$*"; }
info() { printf '%s  →%s %s\n' "$C_INFO" "$C_OFF" "$*"; }
step() { printf '\n%s== %s ==%s\n' "$C_INFO" "$*" "$C_OFF"; }
die()  { bad "$*"; exit 1; }

# ── Argumentos ────────────────────────────────────────────────────
for arg in "$@"; do
    case "$arg" in
        --dry-run)  DRY_RUN=1 ;;
        --yes|-y)   ASSUME_YES=1 ;;
        --baseline) DO_BASELINE=1 ;;
        --help|-h)  awk 'NR>1 && /^#/ { sub(/^# ?/,""); print; next } NR>1 { exit }' "$0"; exit 0 ;;
        *)          die "Opção desconhecida: $arg (use --help)" ;;
    esac
done

# Executa um comando, respeitando --dry-run.
run() {
    if (( DRY_RUN )); then
        printf '%s  [dry-run]%s %s\n' "$C_WARN" "$C_OFF" "$*"
    else
        "$@"
    fi
}

# ── 1. Pré-requisitos do próprio script ───────────────────────────
step "Ambiente"

command -v apt-get >/dev/null 2>&1 \
    || die "Este script só cobre distribuições com apt (Debian/Ubuntu)."

if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    info "Distribuição: ${PRETTY_NAME:-desconhecida}"
fi

SUDO=""
if [[ "$(id -u)" -ne 0 ]]; then
    if (( DRY_RUN )); then
        info "Sem root — modo --dry-run, nada será alterado."
    elif command -v sudo >/dev/null 2>&1; then
        SUDO="sudo"
        info "Sem root — usando sudo para os passos privilegiados."
    else
        die "Rode como root (sudo ./deploy.sh) ou use --dry-run."
    fi
fi

info "Aplicação: $APP_DIR"

# ── 2. Requisitos: verificação por CAPACIDADE ─────────────────────
# Checa o que o sistema sabe FAZER (binário presente, extensão carregada),
# não o nome do pacote: dependendo da distro o mesmo recurso vem como
# `php-sqlite3` (metapacote) ou `php8.3-sqlite3` (versionado), e comparar
# nomes dá falso negativo num sistema já provisionado.
#
# Propositalmente fora da lista: php-mysql (o driver padrão é SQLite) e
# php-mbstring (o código não pode depender de mb_*, é regra do projeto).
step "Requisitos"

tem_apache()  { command -v apache2 >/dev/null 2>&1; }
tem_php()     { command -v php >/dev/null 2>&1; }
tem_ext()     { tem_php && php -m 2>/dev/null | grep -qix "$1"; }

# Módulos ativos do Apache, com fallback para quem não pode rodar apache2ctl.
mods_apache() {
    apache2ctl -M 2>/dev/null || ls /etc/apache2/mods-enabled/ 2>/dev/null
}
tem_mod() { mods_apache | grep -qE "$1"; }
# O Apache precisa executar PHP por mod_php OU por php-fpm/proxy_fcgi.
tem_php_web() { tem_mod "php[0-9_.]*_module|php[0-9.]*\\.load|proxy_fcgi_module"; }

FALTANDO=()
precisa() {  # precisa <descrição> <pacote> <comando de teste...>
    local desc="$1" pkg="$2"; shift 2
    if "$@"; then
        ok "$desc"
    else
        bad "$desc — faltando (pacote: $pkg)"
        FALTANDO+=("$pkg")
    fi
}

precisa "Apache 2"                 apache2            tem_apache
precisa "PHP CLI"                  php-cli            tem_php
precisa "extensão pdo_sqlite"      php-sqlite3        tem_ext pdo_sqlite
precisa "PHP executado pelo Apache" libapache2-mod-php tem_php_web

# ── 3. Instalação do que faltou ───────────────────────────────────
if (( ${#FALTANDO[@]} )); then
    step "Instalação"
    info "Instalando: ${FALTANDO[*]}"
    APT_FLAGS=()
    if (( ASSUME_YES )); then APT_FLAGS+=(-y); fi
    run $SUDO env DEBIAN_FRONTEND=noninteractive apt-get update
    run $SUDO env DEBIAN_FRONTEND=noninteractive apt-get install "${APT_FLAGS[@]}" "${FALTANDO[@]}"

    # Reverifica: instalação parcial não pode passar batida.
    if (( ! DRY_RUN )); then
        step "Reverificação"
        tem_apache    && ok "Apache 2"                  || bad "Apache 2 continua ausente"
        tem_php       && ok "PHP CLI"                   || bad "PHP CLI continua ausente"
        tem_ext pdo_sqlite && ok "extensão pdo_sqlite"  || bad "pdo_sqlite continua ausente"
        tem_php_web   && ok "PHP executado pelo Apache" || warn "Apache ainda sem mod_php/proxy_fcgi"
    fi
fi

# ── 4. Versão do PHP e demais extensões ───────────────────────────
step "PHP"

if tem_php; then
    PHP_VER="$(php -r 'echo PHP_VERSION;')"
    if [[ "$(printf '%s\n%s\n' "$PHP_MIN" "$PHP_VER" | sort -V | head -1)" == "$PHP_MIN" ]]; then
        ok "PHP $PHP_VER (mínimo $PHP_MIN)"
    else
        bad "PHP $PHP_VER é anterior ao mínimo $PHP_MIN"
        warn "Atualize a distribuição ou adicione um repositório com PHP $PHP_MIN+"
        warn "(o script não adiciona PPAs de terceiros por conta própria)."
    fi

    for ext in pdo json session; do
        tem_ext "$ext" && ok "extensão $ext" || bad "extensão $ext ausente — o app não sobe sem ela"
    done
elif (( DRY_RUN )); then
    warn "PHP ainda não instalado (seria instalado agora)."
else
    die "PHP não encontrado mesmo após a instalação."
fi

# ── 4b. mod_rewrite ───────────────────────────────────────────────
# Todo o roteamento passa pelo front controller via .htaccess.
step "Apache: módulos"

if command -v a2enmod >/dev/null 2>&1; then
    if tem_mod "rewrite_module|rewrite\\.load"; then
        ok "mod_rewrite ativo"
    else
        info "Ativando mod_rewrite"
        run $SUDO a2enmod rewrite
    fi
    tem_php_web && ok "PHP servido pelo Apache" \
        || warn "Apache sem mod_php/proxy_fcgi — os .php sairiam como texto puro."
elif (( DRY_RUN )); then
    warn "Apache ainda não instalado (seria instalado agora)."
fi

# ── 5. AllowOverride para o .htaccess do front controller ─────────
# O roteamento inteiro depende do .htaccess da raiz do projeto; sem
# AllowOverride All o Apache o ignora e todas as rotas dão 404.
step "Configuração do diretório"

if [[ -f "$APACHE_CONF" ]]; then
    ok "$(basename "$APACHE_CONF") já existe"
else
    info "Criando $APACHE_CONF"
    CONF_BODY="<Directory ${APP_DIR}>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
"
    if (( DRY_RUN )); then
        printf '%s  [dry-run]%s escreveria:\n%s\n' "$C_WARN" "$C_OFF" "$CONF_BODY"
    else
        printf '%s' "$CONF_BODY" | $SUDO tee "$APACHE_CONF" >/dev/null
    fi
fi

if command -v a2enconf >/dev/null 2>&1; then
    if [[ -e "/etc/apache2/conf-enabled/${APP_NAME}.conf" ]]; then
        ok "conf habilitada"
    else
        run $SUDO a2enconf "$APP_NAME"
    fi
fi

# O BASE_PATH padrão do index.php é '/horarios-academicos'; se o diretório
# tiver outro nome, as URLs geradas não batem com o caminho servido.
if [[ "$APP_NAME" != "horarios-academicos" ]]; then
    warn "O diretório se chama '$APP_NAME', mas o BASE_PATH padrão é '/horarios-academicos'."
    warn "Ajuste o RewriteBase do .htaccess e defina SGA_BASE_PATH='/$APP_NAME'."
fi

# ── 6. Permissões e diretórios graváveis ──────────────────────────
# database/ precisa ser gravável pelo Apache (arquivos -wal/-shm do SQLite);
# backups/ recebe os snapshots do BackupController.
step "Permissões"

if id "$WEB_USER" >/dev/null 2>&1; then
    for d in database backups; do
        DIR="$APP_DIR/$d"
        [[ -d "$DIR" ]] || run $SUDO install -d -m 2775 "$DIR"
        run $SUDO chgrp -R "$WEB_USER" "$DIR"
        run $SUDO chmod -R g+w "$DIR"
        run $SUDO chmod g+s "$DIR"
        ok "$d/ gravável pelo grupo $WEB_USER"
    done

    # backups/ fica dentro do docroot: sem isto, um .sqlite com os dados e os
    # hashes de senha seria servido direto pelo Apache, sem passar pelo login.
    BK_HT="$APP_DIR/backups/.htaccess"
    if [[ -f "$BK_HT" ]]; then
        ok "backups/.htaccess presente"
    else
        info "Criando backups/.htaccess (bloqueio de acesso web)"
        if (( DRY_RUN )); then
            printf '%s  [dry-run]%s escreveria Require all denied em %s\n' "$C_WARN" "$C_OFF" "$BK_HT"
        else
            printf '# Snapshots do banco. Nunca devem ser servidos pelo navegador.\nRequire all denied\n' \
                | $SUDO tee "$BK_HT" >/dev/null
        fi
    fi
else
    warn "Usuário '$WEB_USER' não existe — pulando ajuste de permissões."
fi

# ── 7. Banco de dados ─────────────────────────────────────────────
# Nunca sobrescreve um banco existente: cria só se faltar, e depois só
# aplica migrations pendentes.
step "Banco SQLite"

DB_FILE="$APP_DIR/database/sga.sqlite"

php_as_web() {
    if (( DRY_RUN )); then
        printf '%s  [dry-run]%s php %s\n' "$C_WARN" "$C_OFF" "$*"
        return 0
    fi
    # Mesmo como root o sudo aqui é explícito: sem ele o php rodaria como
    # root e o .sqlite nasceria com dono errado para o Apache.
    if id "$WEB_USER" >/dev/null 2>&1 && command -v sudo >/dev/null 2>&1; then
        sudo -u "$WEB_USER" php "$@"
    else
        php "$@"
    fi
}

if [[ -f "$DB_FILE" ]]; then
    ok "Banco já existe ($DB_FILE) — preservado."

    TEM_CONTROLE=0
    if command -v php >/dev/null 2>&1; then
        TEM_CONTROLE=$(php -r '
            $p = $argv[1];
            try {
                $db = new PDO("sqlite:$p");
                $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type = ? AND name = ?");
                $q->execute(["table", "schema_migrations"]);
                echo $q->fetchColumn() ? 1 : 0;
            } catch (Throwable $e) { echo 0; }
        ' "$DB_FILE" 2>/dev/null || echo 0)
    fi

    if [[ "$TEM_CONTROLE" == "1" ]]; then
        info "Aplicando migrations pendentes"
        php_as_web "$APP_DIR/database/migrate.php"
    elif (( DO_BASELINE )); then
        info "Baseline: marcando as migrations atuais como aplicadas"
        php_as_web "$APP_DIR/database/migrate.php" baseline
    else
        warn "Banco existente ainda sem a tabela schema_migrations."
        warn "Se ele já está no estado do schema.sql, rode uma vez:"
        warn "    sudo ./deploy.sh --baseline"
        warn "Nada foi alterado no banco (evita marcar migrations que faltam aplicar)."
    fi
else
    info "Criando o banco a partir do schema.sql"
    php_as_web "$APP_DIR/database/install.php"
    info "Baseline das migrations no banco novo"
    php_as_web "$APP_DIR/database/migrate.php" baseline
fi

# ── 8. Recarregar o Apache ────────────────────────────────────────
step "Finalização"

if command -v apache2ctl >/dev/null 2>&1; then
    if (( DRY_RUN )); then
        warn "[dry-run] apache2ctl configtest + reload"
    elif $SUDO apache2ctl configtest >/dev/null 2>&1; then
        run $SUDO systemctl reload apache2
        ok "Apache recarregado"
    else
        bad "apache2ctl configtest falhou — o Apache NÃO foi recarregado:"
        $SUDO apache2ctl configtest || true
        exit 1
    fi
fi

printf '\n'
ok "Deploy concluído."
info "Acesse: http://localhost/${APP_NAME}/"
info "No primeiro acesso o sistema pede o cadastro do usuário inicial (/setup)."
