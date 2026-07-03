# SGA Desktop (Windows)

Empacota o SGA como aplicativo desktop **offline por máquina**, sem instalar
Apache nem MySQL. O Electron orquestra um **MariaDB portátil** (compatível com
MySQL) + o **servidor embutido do PHP**, cada instalação com seu próprio banco
em `%APPDATA%`.

> Não afeta o ambiente de desenvolvimento: o app do Apache continua em
> `localhost/horarios-academicos` usando o MySQL da porta 3306. O desktop sobe
> uma instância própria, isolada, em porta dinâmica.

## Binários a colocar em `desktop/runtime/` (não versionados)

1. **PHP portátil (Windows, x64, Thread Safe)** → `runtime/php/`
   - de https://windows.php.net/download (php-8.3.x-Win32-vs16-x64.zip)
   - garanta `runtime/php/ext/php_pdo_mysql.dll` (já usa o `php.ini` deste diretório)
2. **MariaDB "noinstall" (zip)** → `runtime/mariadb/`
   - de https://mariadb.org/download (pacote ZIP)
   - deve conter `bin/mysqld.exe`, `bin/mysql.exe`, `bin/mysqladmin.exe`,
     `bin/mariadb-install-db.exe`

Copie também o `desktop/php.ini` para dentro de `runtime/php/php.ini` (ou ajuste
o caminho em `main.js`).

## Rodar em desenvolvimento

```
cd desktop
npm install
npm start        # sobe mariadb + php + janela
```

## Gerar o instalador / portátil

```
npm run dist     # gera desktop/dist/ (instalador NSIS + versão portátil .exe)
```

## Pontos de atenção

- **Assets offline:** o layout carrega Bootstrap/Bootstrap-Icons via **CDN
  (jsdelivr)**. Sem internet isso quebra o visual. Antes de distribuir, é
  preciso *vendorizar* esses arquivos (baixar para `assets/vendor/` e apontar o
  `header.php` para eles). Ainda não feito.
- **Encerramento:** sempre feche pela janela para o `mysqladmin shutdown` rodar
  e evitar corrupção do InnoDB.
- **Primeira execução:** cria o datadir e carrega `database/schema.sql`
  (banco vazio + usuário `admin`/`admin`). Para distribuir já com dados, troque
  o `loadSchema()` por um `mysqldump` dos dados reais.
