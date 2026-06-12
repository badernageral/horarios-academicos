# SGA – Sistema de Geração Automática de Horários Acadêmicos

Sistema web completo para geração automática de horários escolares e acadêmicos, com suporte a **horários em tempo real** (sem períodos fixos), durações variáveis de aula e intervalos configuráveis por curso.

## Requisitos

- PHP 8.3+
- MySQL 8.0+ ou MariaDB 10.6+
- Servidor web: Apache (com mod_rewrite) ou Nginx

## Instalação

### 1. Configurar banco de dados

Edite `config/database.php` com suas credenciais:

```php
return [
    'host'     => 'localhost',
    'dbname'   => 'horarios_academicos',
    'user'     => 'seu_usuario',
    'password' => 'sua_senha',
    ...
];
```

Ou via variáveis de ambiente:
```bash
export DB_HOST=localhost
export DB_NAME=horarios_academicos
export DB_USER=root
export DB_PASS=senha
```

### 2. Criar o banco

```bash
# Apenas o schema
php database/install.php

# Com dados de exemplo (recomendado para demonstração)
php database/install.php --seed
```

### 3. Configurar servidor web

**Apache** – aponte o DocumentRoot para `public/` e habilite `mod_rewrite`. O `.htaccess` já está configurado.

**PHP Built-in (desenvolvimento)**:
```bash
cd public
php -S localhost:8080
```

**Nginx**:
```nginx
root /path/to/horarios-academicos/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; include fastcgi_params; }
```

## Arquitetura

```
horarios-academicos/
├── app/
│   ├── Controllers/          # MVC – Controllers
│   ├── Models/               # MVC – Models (PDO)
│   ├── Services/
│   │   ├── ScheduleGenerator.php  ← Algoritmo central
│   │   ├── TimeHelper.php         ← Utilitários de tempo
│   │   └── Exporter.php           ← CSV / Excel / PDF
│   └── Views/                # Templates PHP
├── config/
│   ├── database.php
│   └── app.php
├── database/
│   ├── schema.sql            ← Estrutura completa do BD
│   ├── seed.sql              ← Dados de exemplo
│   └── install.php           ← Script de instalação
├── public/
│   ├── index.php             ← Front controller
│   ├── .htaccess
│   └── assets/css/ js/
└── routes.php
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
- Armazenamento: `hora_inicio TIME` e `hora_fim TIME` (ex: `07:00:00` → `07:45:00`)

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
