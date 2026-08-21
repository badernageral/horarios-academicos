-- Liberação do horário para a consulta pública.
--
-- Enquanto o horário está sendo elaborado ele não deve aparecer em /publico.
-- O padrão é 0 (não liberado): uma geração recém-criada fica invisível até
-- alguém liberá-la explicitamente na grade — inclusive as que já existem.
ALTER TABLE geracoes ADD COLUMN publico INTEGER NOT NULL DEFAULT 0;
