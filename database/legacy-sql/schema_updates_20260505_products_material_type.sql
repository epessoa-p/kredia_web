-- Add material_type column to products table for jewelry category (category 2)
ALTER TABLE products ADD COLUMN material_type VARCHAR(255) NULL COMMENT 'Material type for jewelry (oro, plata, etc)' AFTER item_model;

-- Create index for material_type if needed for searches
CREATE INDEX idx_products_material_type ON products(material_type);
