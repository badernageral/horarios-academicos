# Horários Acadêmicos

Sistema web completo para geração automática de horários escolares e acadêmicos, com suporte a **horários em tempo real** (sem períodos fixos), durações variáveis de aula e intervalos configuráveis por curso.

## Requisitos

- PHP 8.3+ com a extensão **pdo_sqlite** (pacote `php-sqlite3`)
- Servidor web: Apache com `mod_rewrite` (mod_php ou php-fpm) — ou rode como **app desktop** (ver abaixo)
- **Não precisa de servidor de banco**: os dados ficam num único arquivo **SQLite**

## Instalação (web)

### 1. Servir a aplicação

Coloque o projeto sob o Apache e habilite `mod_rewrite`. O `.htaccess` incluído cuida
do roteamento (front controller `index.php`). Por padrão o app assume o subcaminho
`/horarios-academicos`; para servir na raiz do domínio, defina a env `SGA_BASE_PATH=''`.

Para desenvolvimento, o servidor embutido do PHP também funciona:
```bash
php -S localhost:8080 -t . desktop/router.php
```

### 2. Banco de dados (SQLite)

O banco é o arquivo `database/sga.sqlite`. Crie-o a partir do schema com:
```bash
php database/install.php
```
Ele precisa ser gravável pelo usuário do servidor web (ex.: `www-data`), assim como o
diretório `database/` (para os arquivos `-wal`/`-shm`). Configuração em
`config/database.php` (driver `sqlite` por padrão; `mysql` ainda disponível via
`DB_DRIVER=mysql`, para acessar bancos legados).

### 3. Primeiro acesso

Sem nenhum usuário cadastrado, o sistema abre em **`/setup`** e pede a criação do
**primeiro usuário** (acesso total). Depois disso, o login normal fica em `/login`.

### Atualizações de schema (migrations)

Mudanças de banco são incrementais, em `database/migrations/`:
```bash
php database/migrate.php          # aplica as pendentes
php database/migrate.php status   # lista aplicadas × pendentes
```

## Versão Desktop (Windows)

Além da versão web, o sistema roda como **aplicativo desktop para Windows**,
offline e por máquina — **sem instalar nada**. Basta baixar e abrir.

- **Como usar:** baixe o `.exe` na aba **[Releases](../../releases)** e execute.
  Na primeira abertura o app cria seu próprio banco local e pede o cadastro do
  **primeiro usuário**.
- **Como funciona:** um shell **Electron** (`desktop/main.js`) sobe o **servidor
  embutido do PHP** com backend **SQLite** (um arquivo em `%APPDATA%`) e abre a
  janela do sistema. O código PHP é o mesmo da versão web — nenhuma lógica é
  duplicada.
- **Dados:** cada instalação tem seu próprio `sga.sqlite`, preservado entre
  atualizações (as migrations rodam a cada abertura).

### Gerar o instalador (via GitHub Actions)

Não é preciso ter uma máquina Windows: o workflow
[`.github/workflows/desktop-build.yml`](.github/workflows/desktop-build.yml)
baixa o PHP e empacota o `.exe` num runner `windows-latest`.

- **Manual:** aba **Actions** → *Build Desktop (Windows)* → **Run workflow**
  (o instalador sai como artefato do run).
- **Por tag:** `git tag v1.0.0 && git push origin v1.0.0` — o `.exe` é anexado
  automaticamente à Release correspondente.

Detalhes de build local e dos binários em [`desktop/README.md`](desktop/README.md).

## Arquitetura

```
horarios-academicos/
├── index.php                ← Front controller
├── .htaccess                ← Rewrite (mod_rewrite)
├── routes.php
├── app/
│   ├── Core/                # Router, Database (PDO), View, Auth
│   ├── Controllers/         # MVC – Controllers
│   ├── Models/              # MVC – Models (PDO)
│   ├── Services/
│   │   ├── ScheduleGenerator.php  ← Algoritmo central
│   │   ├── TimeHelper.php         ← Utilitários de tempo
│   │   └── Exporter.php           ← CSV / Excel / PDF
│   └── Views/               # Templates PHP
├── assets/                  # CSS/JS + vendor (Bootstrap local, sem CDN)
├── config/
│   ├── database.php
│   └── app.php
├── database/
│   ├── schema.sql           ← Estrutura (SQLite)
│   ├── migrate.php          ← Runner de migrations
│   ├── migrations/          ← Mudanças incrementais de schema
│   └── sga.sqlite           ← Banco (gerado; não versionado)
└── desktop/                 ← Empacotamento Electron (Windows)
```

## Algoritmo de Geração

O `ScheduleGenerator` implementa:

1. **Fase 1 – Construção Greedy (MCF)**: Ordena atividades por dificuldade (Most Constrained First) e atribui cada uma ao melhor slot disponível.

2. **Fase 2 – Pontuação Soft**: Cada candidato recebe pontuação baseada em:
   - Janelas do professor (gaps entre aulas)
   - Janelas da turma
   - Distribuição semanal (evitar sobrecarregar dias)
   - Repetição de disciplina no mesmo dia
   - Proximidade ao centro do turno

3. **Fase 3 – Busca Local**: Tenta realocar atividades que falharam via swaps.

### Horários em Tempo Real (sem períodos fixos)

- Cada disciplina define sua **duração em minutos** (45, 60, 90min…)
- Intervalos (breaks) são configurados por curso com **hora_inicio** e **hora_fim**
- O algoritmo busca slots livres a partir dos limites do turno e adjacentes às ocupações existentes
- Armazenamento: `hora_inicio` e `hora_fim` como texto `"HH:MM:SS"` (ex: `07:00:00` → `07:45:00`)

## Restrições Hard (verificadas pelo algoritmo)

- ✅ Professor em dois locais ao mesmo tempo
- ✅ Sala ocupada simultaneamente
- ✅ Turma em duas aulas simultâneas
- ✅ Aula fora do turno do curso
- ✅ Aula durante intervalos configurados
- ✅ Professor fora de sua disponibilidade
- ✅ Carga horária diária e semanal do professor

## API REST

```
GET  /api/geracoes
GET  /api/geracao/{id}
POST /api/gerar                    { "descricao": "...", "pesos": {...} }
GET  /api/conflitos/{geracao_id}
GET  /api/horarios/turma/{t}/{g}
GET  /api/horarios/professor/{p}/{g}
GET  /api/horarios/sala/{s}/{g}
GET  /api/estatisticas/{geracao_id}
GET  /api/disciplinas
GET  /api/professores
```

## Exportação

- **CSV**: separado por `;`, UTF-8 com BOM (compatível com Excel)
- **Excel**: HTML table com Content-Type `application/vnd.ms-excel`
- **PDF**: HTML otimizado para impressão com `window.print()`
