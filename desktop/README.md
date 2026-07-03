# SGA Desktop (Windows)

Empacota o SGA como aplicativo desktop **offline por máquina**, sem instalar
nada. O Electron sobe o **servidor embutido do PHP** com backend **SQLite**
(um arquivo em `%APPDATA%`) — sem servidor de banco, sem MySQL/MariaDB.

> Não afeta o ambiente de desenvolvimento. Cada instalação tem seu próprio
> arquivo `sga.sqlite`, criado na primeira execução a partir do `schema.sql`.

## Binário a colocar em `desktop/runtime/` (não versionado)

1. **PHP portátil (Windows, x64, Thread Safe)** → `runtime/php/`
   - de https://windows.php.net/download (php-8.3.x-Win32-vs16-x64.zip)
   - garanta `runtime/php/ext/php_pdo_sqlite.dll` e `php_sqlite3.dll`

Copie também o `desktop/php.ini` para `runtime/php/php.ini` (habilita `pdo_sqlite`).

## Rodar em desenvolvimento

```
cd desktop
npm install
npm start        # cria o banco (1ª vez) + php -S + janela
```

## Gerar o instalador / portátil

```
npm run dist     # gera desktop/dist/ (instalador NSIS + versão portátil .exe)
```

O build no GitHub Actions (`.github/workflows/desktop-build.yml`) faz isso sem
uma máquina Windows: baixa o PHP e empacota o `.exe`.

## Pontos de atenção

- **Assets offline:** Bootstrap/Icons já são servidos de `assets/vendor/`
  (sem CDN), então o visual funciona sem internet.
- **Banco:** fica em `%APPDATA%\SGA Horarios\sga.sqlite`, preservado entre
  atualizações. `main.js` roda as migrations em toda abertura → instalar por
  cima atualiza o schema sem perder dados.
- **Backup:** menu Backup exporta/importa o arquivo `.sqlite` (via PHP, sem
  binários externos).
