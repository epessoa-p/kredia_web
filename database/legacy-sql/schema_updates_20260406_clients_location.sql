-- Agrega soporte de ubicación para clientes
-- Fecha: 2026-04-06

ALTER TABLE clients
    ADD COLUMN location_url VARCHAR(500) NULL AFTER address,
    ADD COLUMN location_lat DECIMAL(10,7) NULL AFTER location_url,
    ADD COLUMN location_lng DECIMAL(10,7) NULL AFTER location_lat;

CREATE INDEX idx_clients_location_coords ON clients (location_lat, location_lng);
