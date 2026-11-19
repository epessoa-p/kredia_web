-- Agrega atributos de recargo para ventas al credito.
-- Guarda el porcentaje adicional aplicado y su monto equivalente.

ALTER TABLE `sales`
    ADD COLUMN `credit_additional_percentage` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `payment_type`,
    ADD COLUMN `credit_additional_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `credit_additional_percentage`;
