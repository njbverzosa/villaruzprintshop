-- ============================================================
-- Resequence `id` in multiple inventory tables
-- ============================================================

SET @row := 0;
UPDATE admins
    SET id = (@row := @row + 1)
    ORDER BY id;

SET @row := 0;
UPDATE cart
    SET id = (@row := @row + 1)
    ORDER BY id;

SET @row := 0;
UPDATE chat_account
    SET id = (@row := @row + 1)
    ORDER BY id;

SET @row := 0;
UPDATE chat_conversation
    SET id = (@row := @row + 1)
    ORDER BY id;

    SET @row := 0;
UPDATE contracts
    SET id = (@row := @row + 1)
    ORDER BY id;

    SET @row := 0;
UPDATE customers
    SET id = (@row := @row + 1)
    ORDER BY id;

    SET @row := 0;
UPDATE for_deliveries
    SET id = (@row := @row + 1)
    ORDER BY id;

    SET @row := 0;
UPDATE investors
    SET id = (@row := @row + 1)
    ORDER BY id;

    SET @row := 0;
UPDATE investors_inventory
    SET id = (@row := @row + 1)
    ORDER BY id;

        SET @row := 0;
UPDATE logs
    SET id = (@row := @row + 1)
    ORDER BY id;
    
        SET @row := 0;
UPDATE merchandise_inventory
    SET id = (@row := @row + 1)
    ORDER BY id;

            SET @row := 0;
UPDATE order_status_history
    SET id = (@row := @row + 1)
    ORDER BY id;
    


UPDATE merchandise_inventory
    SET product_number = CONCAT('PRD', LPAD(id, 5, '0'));