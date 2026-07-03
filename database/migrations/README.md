# Migrations

Mudanças **incrementais** de schema, aplicadas por cima de um banco já existente
(atualização), sem perder dados. O `schema.sql` continua sendo o schema completo
usado para criar um banco **novo** do zero.

## Como criar uma migration

1. Crie um arquivo `NNN_descricao.sql` (numeração crescente, ex.: `001_add_campo_x.sql`).
   A ordem de aplicação é alfabética pelo nome — use zeros à esquerda.
2. Escreva comandos **incrementais e não destrutivos**:
   - `ALTER TABLE ... ADD COLUMN ...`
   - `CREATE TABLE IF NOT EXISTS ...`
   - `INSERT ...`
   - **Evite** `DROP TABLE`/`DROP COLUMN` sem necessidade — a ideia é preservar dados.
   Vários comandos no mesmo arquivo, separados por `;`.
3. Reflita a mesma mudança no `database/schema.sql` (para instalações novas).

## Aplicar

```
php database/migrate.php            # aplica as pendentes
php database/migrate.php status     # lista aplicadas x pendentes
php database/migrate.php baseline   # marca as atuais como aplicadas SEM rodar
```

- **Instalação nova** (banco criado pelo `schema.sql`): rode `baseline` uma vez
  logo após criar o banco — o `schema.sql` já contém tudo, então as migrations
  atuais são apenas marcadas como aplicadas. (No desktop, o `main.js` faz isso
  automaticamente.)
- **Banco existente** (produção/desktop antigo): rode `migrate.php` (up) — ele
  aplica só o que ainda falta.
- **Servidor atual, primeira vez:** rode `php database/migrate.php baseline` uma
  vez para colocá-lo sob controle; depois use `migrate.php` a cada deploy.
