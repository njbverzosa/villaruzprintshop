SET @row := 0;
UPDATE merchandise_inventory
    SET id = (@row := @row + 1)
    ORDER BY id;

UPDATE merchandise_inventory
    SET product_number = CONCAT('PRD', LPAD(id, 5, '0'));